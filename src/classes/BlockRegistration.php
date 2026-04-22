<?php
/**
 * ACF Block Registration
 *
 * @package Viget\BlocksToolkit
 */

namespace Viget\BlocksToolkit;

use stdClass;
use Timber\Timber;
use WP_Block;

/**
 * Block Registration Class
 */
class BlockRegistration {

	/**
	 * Keep track of all block IDs
	 */
	private const BLOCK_IDS_TRANSIENT = 'vgtbt_block_ids';

	/**
	 * Cached array of blocks.
	 *
	 * @var array
	 */
	public static array $blocks = [];

	/**
	 * Local cache of block IDs.
	 *
	 * @var array
	 */
	public static array $block_ids = [];

	/**
	 * Whether block pattern markup has been attached to the editor script handle.
	 *
	 * @var bool
	 */
	private static bool $block_patterns_script_data_registered = false;

	/**
	 * Register ACF Blocks.
	 */
	public static function init(): void {
		// Automate block registration.
		self::register_blocks();

		// Localize block pattern markup for editor seeding.
		self::localize_block_patterns_for_editor();

		// Set default block render callback for ACF blocks.
		self::set_default_callback();

		// Disable inner blocks wrapper.
		self::disable_inner_blocks_wrap();

		// Allow for core block style de-registration.
		self::unregister_block_styles();

		// Allow for core block variation de-registration.
		self::unregister_block_variations();

		// Reset block IDs on a new request.
		self::reset_block_ids();

		// Add unique, persistent IDs to each ACF block.
		self::create_block_id();
	}

	/**
	 * Register the Theme blocks
	 *
	 * @return void
	 */
	public static function register_blocks(): void {
		add_action(
			'acf/init',
			function () {
				$blocks = self::get_all_blocks();

				foreach ( $blocks as $block ) {
					$include_path = $block['path'] . '/block.php';

					// Autoload block.php within block directory.
					if ( file_exists( $include_path ) ) {
						require_once $include_path;
					}

					register_block_type( $block['path'] . '/block.json' );
				}
			}
		);
	}

	/**
	 * Automatically adds block render callbacks.
	 *
	 * @return void
	 */
	public static function set_default_callback(): void {
		add_filter(
			'block_type_metadata',
			function ( array $metadata ): array {
				if ( ! function_exists( 'acf_is_acf_block_json' ) || ! \acf_is_acf_block_json( $metadata ) ) {
					return $metadata;
				}

				if ( ! empty( $metadata['acf']['renderCallback'] ) || ! empty( $metadata['acf']['renderTemplate'] ) || empty( $metadata['name'] ) ) {
					return $metadata;
				}

				$metadata['acf']['renderCallback'] = function ( array $block, string $content = '', bool $is_preview = false, int $post_id = 0, WP_Block|stdClass|null $wp_block = null, array|bool $context = [], bool $is_ajax_render = false ) use ( $metadata ): void {
					$block_name    = str_replace( 'acf/', '', $block['name'] );
					$block['slug'] = sanitize_title( $block_name );
					if ( empty( $block['path'] ) ) {
						$block['path'] = self::get_block_location( $block_name );
					}
					if ( empty( $block['url'] ) ) {
						$block['url'] = self::path_to_url( $block['path'] );
					}

					$block['tagName'] = $metadata['tagName'] ?? 'section';

					$block['blockPattern'] = self::resolve_runtime_attribute( $block, $metadata, 'blockPattern', '' );
					$block['templateLock'] = self::resolve_runtime_attribute( $block, $metadata, 'templateLock', '' );
					$block['lock']         = self::resolve_runtime_lock( $block, $metadata );
					$block['sync']         = (bool) self::resolve_runtime_attribute( $block, $metadata, 'sync', false );

					// Pass the block template data to the block.
					$block['template'] = self::get_inner_blocks( $block, $metadata );

					if ( $block['sync'] && ! empty( $block['blockPattern'] ) ) {
						$pattern_markup = BlockPatternResolver::resolve( (string) $block['blockPattern'], (string) $block['path'] );
						if ( '' !== trim( $pattern_markup ) ) {
							if ( ! empty( $block['lock'] ) && is_array( $block['lock'] ) ) {
								$pattern_markup = self::merge_default_lock_in_markup( $pattern_markup, $block['lock'] );
							}
							$content = $pattern_markup;
						}
					}

					// Check for inner blocks.
					$block['hasInnerBlocks'] = \strlen( trim( str_replace( '<p></p>', '', $content ) ) ) > 0;

					/**
					 * Filter the block data.
					 *
					 * @param array $block The block data.
					 * @param string $content The content.
					 * @param bool $is_preview Whether the block is in preview mode.
					 * @param int $post_id The post ID.
					 * @param \WP_Block $wp_block The WP Block instance.
					 * @param array|bool $context The block context.
					 * @param bool $is_ajax_render Whether the block is being rendered in AJAX.
					 *
					 * @return array
					 */
					$block = apply_filters( 'vgtbt_block_data', $block, $content, $is_preview, $post_id, $wp_block, $context, $is_ajax_render );

					$twig = $block['path'] . '/render.twig';

					if ( class_exists( '\Timber\Timber' ) && file_exists( $twig ) ) {
						self::render_twig_block( $twig, $block, $content, $is_preview, $post_id, $wp_block, $context, $is_ajax_render );
						return;
					}

					$render = $block['path'] . '/render.php';

					if ( ! file_exists( $render ) ) {
						if ( ! wp_get_current_user() ) {
							return;
						}

						if ( ! empty( $block['supports']['jsx'] ) ) {
							$render = VGTBT_PLUGIN_PATH . '/views/jsx.php';
						} else {
							$render = VGTBT_PLUGIN_PATH . '/views/default.php';
						}
					}

					$GLOBALS['vgtbt_current_block_template_lock'] = $block['templateLock'];
					require $render;
					unset( $GLOBALS['vgtbt_current_block_template_lock'] );
				};

				return $metadata;
			},
			5
		);
	}

	/**
	 * Get All Available Blocks
	 *
	 * @return array
	 */
	public static function get_all_blocks(): array {
		if ( ! empty( self::$blocks ) ) {
			return self::$blocks;
		}

		$locations = self::get_block_locations();

		foreach ( $locations as $location ) {
			if ( ! is_dir( $location ) ) {
				continue;
			}

			self::get_blocks_in_dir( $location, self::$blocks );
		}

		return self::$blocks;
	}

	/**
	 * Get locations where custom blocks can be found.
	 *
	 * @return array
	 */
	public static function get_block_locations(): array {
		return array_unique(
			apply_filters(
				'vgtbt_block_locations',
				[
					get_template_directory() . '/blocks',
					get_stylesheet_directory() . '/blocks',
					self::get_custom_blocks_dir(),
				]
			)
		);
	}

	/**
	 * Get blocks in directory recursively
	 *
	 * @param string $path  Path to search inside.
	 * @param array  $blocks Passed by reference.
	 *
	 * @return void
	 */
	public static function get_blocks_in_dir( string $path, array &$blocks = [] ): void {
		$group = glob( trailingslashit( $path ) . '**/block.json' );

		foreach ( $group as $block_path ) {
			$block = json_decode( file_get_contents( $block_path ), true );

			$block['path'] = dirname( $block_path );
			$block['url']  = self::path_to_url( $block['path'] );

			$blocks[] = $block;

			self::get_blocks_in_dir( $block['path'], $blocks );
		}
	}

	/**
	 * Convert path to URL
	 *
	 * @param string $path Path to convert to URL.
	 *
	 * @return string
	 */
	public static function path_to_url( string $path ): string {
		$url = str_replace(
			wp_normalize_path( untrailingslashit( ABSPATH ) ),
			site_url(),
			wp_normalize_path( $path )
		);

		return esc_url_raw( $url );
	}

	/**
	 * Get block array
	 *
	 * @param string $block_name The name of the block.
	 *
	 * @return array|false
	 */
	public static function get_block( string $block_name ): array|false {
		$block_path = self::get_block_location( $block_name, 'json' );

		if ( ! $block_path ) {
			return false;
		}

		$block = json_decode( file_get_contents( $block_path ), true );

		$block['path'] = dirname( $block_path );
		$block['url']  = self::path_to_url( $block['path'] );

		return $block;
	}

	/**
	 * Get inner blocks template
	 *
	 * @param array $block The block array.
	 * @param array $metadata The block metadata.
	 *
	 * @return array
	 */
	public static function get_inner_blocks( array $block, array $metadata = [] ): array {
		$template = [];
		if ( ! empty( $metadata['acf']['template'] ) ) {
			$template = $metadata['acf']['template'];
		} elseif ( ! empty( $metadata['acf']['innerBlocks'] ) ) {
			$template = $metadata['acf']['innerBlocks'];
		}

		if ( empty( $template ) ) {
			$json_path = $block['path'] . '/template.json';

			if ( file_exists( $json_path ) ) {
				$json = json_decode( file_get_contents( $json_path ), true );
				if ( ! empty( $json['template'] ) ) {
					$template = $json['template'];
				}
			}
		}

		if ( ! empty( $block['blockPattern'] ) ) {
			$pattern_slug = trim( (string) $block['blockPattern'] );
			$block_path   = isset( $block['path'] ) ? (string) $block['path'] : '';
			$markup       = BlockPatternResolver::resolve( $pattern_slug, $block_path );

			if ( '' !== trim( $markup ) ) {
				$parsed = parse_blocks( $markup );
				$built  = self::parsed_blocks_to_inner_template( $parsed );
				if ( ! empty( $built ) ) {
					$template = $built;
				} else {
					$template = [];
				}
			} else {
				$template = [];
			}
		}

		if ( ! empty( $block['lock'] ) && is_array( $block['lock'] ) ) {
			$template = self::merge_default_lock_recursive( $template, $block['lock'] );
		}

		return $template;
	}

	/**
	 * Convert parse_blocks() output into InnerBlocks `template` array shape.
	 *
	 * @param array $parsed_blocks Parsed blocks from parse_blocks().
	 *
	 * @return array
	 */
	public static function parsed_blocks_to_inner_template( array $parsed_blocks ): array {
		$out = [];

		foreach ( $parsed_blocks as $parsed ) {
			if ( empty( $parsed['blockName'] ) || ! is_string( $parsed['blockName'] ) ) {
				continue;
			}

			$attrs = ( isset( $parsed['attrs'] ) && is_array( $parsed['attrs'] ) ) ? $parsed['attrs'] : [];
			$entry = array(
				$parsed['blockName'],
				$attrs,
			);

			if ( ! empty( $parsed['innerBlocks'] ) && is_array( $parsed['innerBlocks'] ) ) {
				$inner = self::parsed_blocks_to_inner_template( $parsed['innerBlocks'] );
				if ( ! empty( $inner ) ) {
					$entry[] = $inner;
				}
			}

			$out[] = $entry;
		}

		return $out;
	}

	/**
	 * Add block pattern markup data for editor seeding.
	 *
	 * Attaches localized data on {@see 'allowed_block_types_all'} so it runs before
	 * `_wp_get_iframed_editor_assets()` (which needs the data when the Site Editor builds
	 * settings before {@see 'enqueue_block_editor_assets'}).
	 *
	 * @return void
	 */
	public static function localize_block_patterns_for_editor(): void {
		add_filter(
			'allowed_block_types_all',
			static function ( $allowed ) {
				self::register_block_patterns_script_data();
				return $allowed;
			},
			0,
			2
		);
	}

	/**
	 * Localize pattern markup onto the editor script handle (once per request).
	 *
	 * @return void
	 */
	public static function register_block_patterns_script_data(): void {
		if ( self::$block_patterns_script_data_registered ) {
			return;
		}

		if ( ! wp_script_is( 'vgtbt-editor-iframe', 'registered' ) ) {
			return;
		}

		self::$block_patterns_script_data_registered = true;

		$all_blocks = self::get_all_blocks();
		$payload    = [];

		foreach ( $all_blocks as $metadata ) {
			$block_name = $metadata['name'] ?? '';
			if ( '' === $block_name ) {
				continue;
			}

			if ( ! str_contains( $block_name, '/' ) ) {
				$block_name = 'acf/' . sanitize_title( $block_name );
			}

			$pattern_slugs = self::get_block_pattern_slugs( $metadata );
			if ( empty( $pattern_slugs ) ) {
				continue;
			}

			$patterns = [];
			foreach ( $pattern_slugs as $slug ) {
				$markup = BlockPatternResolver::resolve( $slug, $metadata['path'] );
				if ( '' === trim( $markup ) ) {
					continue;
				}

				$patterns[ $slug ] = $markup;
			}

			if ( empty( $patterns ) ) {
				continue;
			}

			$payload[ $block_name ] = [
				'patterns'       => $patterns,
				'defaultPattern' => self::get_attribute_default( $metadata, 'blockPattern', '' ),
				'defaultLock'    => self::resolve_metadata_lock( $metadata ),
			];
		}

		if ( empty( $payload ) ) {
			return;
		}

		wp_localize_script(
			'vgtbt-editor-iframe',
			'vgtbtBlockPatterns',
			[
				'blocks' => $payload,
			]
		);
	}

	/**
	 * Resolve a runtime attribute from block attrs or metadata defaults.
	 *
	 * @param array  $block Block data.
	 * @param array  $metadata Block metadata.
	 * @param string $attribute Attribute name.
	 * @param mixed  $fallback Fallback value.
	 *
	 * @return mixed
	 */
	public static function resolve_runtime_attribute( array $block, array $metadata, string $attribute, mixed $fallback ): mixed {
		if ( array_key_exists( $attribute, $block ) ) {
			return $block[ $attribute ];
		}

		return self::get_attribute_default( $metadata, $attribute, $fallback );
	}

	/**
	 * Resolve runtime lock object.
	 *
	 * @param array $block Block data.
	 * @param array $metadata Block metadata.
	 *
	 * @return array
	 */
	public static function resolve_runtime_lock( array $block, array $metadata ): array {
		$defaults = self::resolve_metadata_lock( $metadata );

		if ( array_key_exists( 'lock', $block ) && is_array( $block['lock'] ) ) {
			if ( empty( $block['lock'] ) ) {
				return $defaults;
			}

			return array_merge( $defaults, $block['lock'] );
		}

		return $defaults;
	}

	/**
	 * Resolve metadata default lock.
	 *
	 * @param array $metadata Block metadata.
	 *
	 * @return array
	 */
	public static function resolve_metadata_lock( array $metadata ): array {
		$from_supports = [];
		if ( ! empty( $metadata['supports']['lock'] ) && is_array( $metadata['supports']['lock'] ) ) {
			$from_supports = $metadata['supports']['lock'];
		}

		$from_attr = self::get_attribute_default( $metadata, 'lock', [] );
		if ( ! is_array( $from_attr ) ) {
			$from_attr = [];
		}

		// Attribute defaults override supports (per-key) when both are set.
		return array_merge( $from_supports, $from_attr );
	}

	/**
	 * Get attribute default value from block metadata.
	 *
	 * @param array  $metadata Block metadata.
	 * @param string $attribute Attribute name.
	 * @param mixed  $fallback Fallback value.
	 *
	 * @return mixed
	 */
	public static function get_attribute_default( array $metadata, string $attribute, mixed $fallback ): mixed {
		if ( empty( $metadata['attributes'][ $attribute ] ) || ! is_array( $metadata['attributes'][ $attribute ] ) ) {
			return $fallback;
		}

		if ( ! array_key_exists( 'default', $metadata['attributes'][ $attribute ] ) ) {
			return $fallback;
		}

		return $metadata['attributes'][ $attribute ]['default'];
	}

	/**
	 * Get distinct blockPattern slugs from defaults + variations.
	 *
	 * @param array $metadata Block metadata.
	 *
	 * @return array
	 */
	public static function get_block_pattern_slugs( array $metadata ): array {
		$slugs = [];

		$default = self::get_attribute_default( $metadata, 'blockPattern', '' );
		if ( is_string( $default ) && '' !== trim( $default ) ) {
			$slugs[] = trim( $default );
		}

		if ( ! empty( $metadata['variations'] ) && is_array( $metadata['variations'] ) ) {
			foreach ( $metadata['variations'] as $variation ) {
				$slug = $variation['attributes']['blockPattern'] ?? '';
				if ( is_string( $slug ) && '' !== trim( $slug ) ) {
					$slugs[] = trim( $slug );
				}
			}
		}

		return array_values( array_unique( $slugs ) );
	}

	/**
	 * Apply default lock recursively to a template array.
	 *
	 * @param array $template Block template array.
	 * @param array $lock Default lock values.
	 *
	 * @return array
	 */
	public static function merge_default_lock_recursive( array $template, array $lock ): array {
		if ( empty( $template ) || empty( $lock ) ) {
			return $template;
		}

		foreach ( $template as $index => $template_block ) {
			if ( ! is_array( $template_block ) || empty( $template_block[0] ) ) {
				continue;
			}

			if ( empty( $template_block[1] ) || ! is_array( $template_block[1] ) ) {
				$template_block[1] = [];
			}

			if ( empty( $template_block[1]['lock'] ) ) {
				$template_block[1]['lock'] = $lock;
			}

			if ( ! empty( $template_block[2] ) && is_array( $template_block[2] ) ) {
				$template_block[2] = self::merge_default_lock_recursive( $template_block[2], $lock );
			}

			$template[ $index ] = $template_block;
		}

		return $template;
	}

	/**
	 * Apply default lock recursively to serialized block markup.
	 *
	 * @param string $markup Block markup.
	 * @param array  $lock Default lock values.
	 *
	 * @return string
	 */
	public static function merge_default_lock_in_markup( string $markup, array $lock ): string {
		if ( '' === trim( $markup ) || empty( $lock ) ) {
			return $markup;
		}

		$blocks = parse_blocks( $markup );
		if ( empty( $blocks ) || ! is_array( $blocks ) ) {
			return $markup;
		}

		$with_lock = self::merge_default_lock_in_parsed_blocks( $blocks, $lock );
		return serialize_blocks( $with_lock );
	}

	/**
	 * Apply default lock recursively to parsed blocks.
	 *
	 * @param array $blocks Parsed blocks.
	 * @param array $lock Default lock values.
	 *
	 * @return array
	 */
	public static function merge_default_lock_in_parsed_blocks( array $blocks, array $lock ): array {
		foreach ( $blocks as $index => $parsed_block ) {
			if ( empty( $parsed_block['blockName'] ) ) {
				continue;
			}

			if ( empty( $parsed_block['attrs'] ) || ! is_array( $parsed_block['attrs'] ) ) {
				$parsed_block['attrs'] = [];
			}

			if ( empty( $parsed_block['attrs']['lock'] ) ) {
				$parsed_block['attrs']['lock'] = $lock;
			}

			if ( ! empty( $parsed_block['innerBlocks'] ) && is_array( $parsed_block['innerBlocks'] ) ) {
				$parsed_block['innerBlocks'] = self::merge_default_lock_in_parsed_blocks( $parsed_block['innerBlocks'], $lock );
			}

			$blocks[ $index ] = $parsed_block;
		}

		return $blocks;
	}

	/**
	 * Get path to custom uploaded blocks.
	 *
	 * @return string
	 */
	public static function get_custom_blocks_dir(): string {
		$uploads_dir = wp_upload_dir();

		return $uploads_dir['basedir'] . '/acf-blocks';
	}

	/**
	 * Get path to block by name.
	 *
	 * @param string $block_name The block name.
	 * @param string $return What should be returned.
	 *
	 * @return false|string
	 */
	public static function get_block_location( string $block_name, string $return = 'directory' ): false|string {
		if ( str_contains( $block_name, '/' ) && ! str_starts_with( $block_name, 'acf/' ) ) {
			return false;
		}

		$block_name = str_replace( 'acf/', '', $block_name );
		$blocks     = self::get_all_blocks();

		foreach ( $blocks as $block ) {
			if ( $block_name !== $block['name'] ) {
				continue;
			}

			if ( 'json' === $return ) {
				return $block['path'] . '/block.json';
			}

			return $block['path'];
		}

		return false;
	}

	/**
	 * Disable inner blocks wrap
	 *
	 * @return void
	 */
	private static function disable_inner_blocks_wrap(): void {
		add_filter(
			'acf/blocks/wrap_frontend_innerblocks',
			function ( bool $wrap, string $name ): bool {
				if ( ! str_starts_with( $name, 'acf/' ) ) {
					return $wrap;
				}

				return false;
			},
			10,
			2
		);
	}

	/**
	 * Render Twig block
	 *
	 * @param string     $template The template filename.
	 * @param array      $block The block array.
	 * @param string     $content The content.
	 * @param bool       $is_preview If in preview mode.
	 * @param int        $post_id The post ID.
	 * @param ?WP_Block  $wp_block The WP Block instance.
	 * @param array|bool $block_context The block context.
	 * @param bool       $is_ajax_render If is in AJAX render.
	 *
	 * @return void
	 */
	public static function render_twig_block( string $template, array $block = [], string $content = '', bool $is_preview = false, int $post_id = 0, ?WP_Block $wp_block = null, array|bool $block_context = [], bool $is_ajax_render = false ): void {
		$context = get_queried_object() ? Timber::context() : [];

		// Add additional context to the block.
		$additional = [
			'fields'         => get_fields(),
			'block'          => $block,
			'content'        => $content,
			'is_preview'     => $is_preview,
			'post_id'        => $post_id,
			'wp_block'       => $wp_block,
			'context'        => $block_context,
			'is_ajax_render' => $is_ajax_render,
		];

		$context = array_merge( $context, $additional );

		// Render the block.
		Timber::render( $template, $context );
	}

	/**
	 * Generate a unique block ID for each ACF block
	 *
	 * @return void
	 */
	public static function create_block_id(): void {
		add_filter(
			'acf/pre_save_block',
			function ( array $attributes ): array {
				$wp_block = \WP_Block_Type_Registry::get_instance()->get_registered( $attributes['name'] );
				if ( ! str_starts_with( $attributes['name'], 'acf/' ) || empty( $wp_block?->attributes['blockId'] ) ) {
					return $attributes;
				}

				if ( empty( $attributes['blockId'] ) ) {
					$attributes['blockId'] = uniqid();
				} else {
					// Ensure the block ID is unique.
					while ( self::block_id_exists( $attributes['blockId'] ) ) {
						$attributes['blockId'] = uniqid();
					}
				}

				self::store_block_id( $attributes['blockId'] );

				return $attributes;
			}
		);
	}

	/**
	 * Store a block ID to check for duplicates
	 *
	 * @param string $block_id The Block ID.
	 *
	 * @return void
	 */
	public static function store_block_id( string $block_id ): void {
		self::load_block_ids();

		if ( ! in_array( $block_id, self::$block_ids, true ) ) {
			self::$block_ids[] = $block_id;
			set_transient( self::BLOCK_IDS_TRANSIENT, self::$block_ids, 5 );
		}
	}

	/**
	 * Check if a block ID already exists
	 *
	 * @param string $block_id The Block ID.
	 *
	 * @return bool
	 */
	public static function block_id_exists( string $block_id ): bool {
		self::load_block_ids();
		return in_array( $block_id, self::$block_ids, true );
	}

	/**
	 * Load block IDs from transient
	 *
	 * @return void
	 */
	private static function load_block_ids(): void {
		if ( empty( self::$block_ids ) ) {
			$transient = get_transient( self::BLOCK_IDS_TRANSIENT );
			if ( $transient ) {
				self::$block_ids = $transient;
			}
		}
	}

	/**
	 * Reset the block IDs on a new request.
	 *
	 * @return void
	 */
	private static function reset_block_ids(): void {
		add_action(
			'acf/init',
			function () {
				if ( empty( self::$block_ids ) ) {
					delete_transient( self::BLOCK_IDS_TRANSIENT );
				}
			}
		);
	}

	/**
	 * Allow for core block style de-registration.
	 *
	 * @return void
	 */
	private static function unregister_block_styles(): void {
		add_action(
			'enqueue_block_assets',
			function () {
				$unregister_styles = apply_filters( 'vgtbt_unregister_block_styles', [] );

				wp_localize_script(
					'vgtbt-editor-scripts',
					'vgtbtStyles',
					[
						'unregister' => $unregister_styles,
					]
				);
			},
			20
		);
	}

	/**
	 * Allow for core block variation de-registration.
	 *
	 * @return void
	 */
	private static function unregister_block_variations(): void {
		add_action(
			'enqueue_block_assets',
			function () {
				$unregister_variations = apply_filters( 'vgtbt_unregister_block_variations', [] );

				wp_localize_script(
					'vgtbt-editor-scripts',
					'vgtbtVariations',
					[
						'unregister' => $unregister_variations,
					]
				);
			},
			20
		);
	}
}
