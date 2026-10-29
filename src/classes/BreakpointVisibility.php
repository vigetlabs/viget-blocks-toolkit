<?php
/**
 * Breakpoint Visibility Support
 *
 * @package Viget\BlocksToolkit
 */

namespace Viget\BlocksToolkit;

/**
 * Breakpoint Visibility Class
 */
class BreakpointVisibility {

	/**
	 * Initialize the class.
	 */
	public function __construct() {
		// Add block breakpoint visibility CSS.
		self::breakpoint_visibility();

		// Hide blocks at each breakpoint.
		add_action( 'enqueue_block_assets', [ $this, 'add_breakpoint_css' ], 20 );

		// Localize JS vars.
		$this->localize_js_vars();
	}

	/**
	 * Get the built-in breakpoints.
	 *
	 * @return array<string, array{label: string, media: string}>
	 */
	public static function get_default_breakpoints(): array {
		return [
			'desktop' => [
				'label' => __( 'Hide on Desktop', 'viget-blocks-toolkit' ),
				'media' => '(min-width: 1024px)',
			],
			'tablet'  => [
				'label' => __( 'Hide on Tablet', 'viget-blocks-toolkit' ),
				'media' => '(min-width: 768px) and (max-width: 1023px)',
			],
			'mobile'  => [
				'label' => __( 'Hide on Mobile', 'viget-blocks-toolkit' ),
				'media' => '(max-width: 767px)',
			],
		];
	}

	/**
	 * Get the breakpoints in the Breakpoint Visibility panel: Desktop, Tablet, Mobile, and any a theme adds.
	 *
	 * Each one is a toggle label, and when it applies: a `selector` for an ancestor (like a class a
	 * script adds to `html`), a `media` query, or both. `hidden` takes its toggle out of the panel.
	 *
	 * The built-ins can't be removed or renamed. Static blocks save their attributes, so those blocks
	 * would stop validating. A built-in missing from the filter, or left without a condition, keeps its default.
	 *
	 * @return array<string, array{label: string, selector: string, media: string, hidden: bool, builtin: bool}>
	 */
	public static function get_breakpoints(): array {
		$defaults = self::get_default_breakpoints();

		/**
		 * Filters the breakpoints in the Breakpoint Visibility panel.
		 *
		 * @param array $breakpoints Breakpoints by slug, each with `label`, `selector` and/or `media`, and `hidden`.
		 */
		$breakpoints = [ ...$defaults, ...(array) apply_filters( 'vgtbt_breakpoint_visibility_breakpoints', $defaults ) ];
		$valid       = [];

		foreach ( $breakpoints as $slug => $breakpoint ) {
			$slug    = sanitize_key( $slug );
			$builtin = isset( $defaults[ $slug ] );

			// These are the panel's own attribute keys.
			if ( ! $slug || in_array( $slug, [ 'usecustom', 'custombreakpoint' ], true ) ) {
				continue;
			}

			$breakpoint = [ ...( $defaults[ $slug ] ?? [] ), ...(array) $breakpoint ];
			$selector   = self::sanitize_css_value( $breakpoint['selector'] ?? '' );
			$media      = self::sanitize_css_value( $breakpoint['media'] ?? '' );

			if ( $builtin && ! $selector && ! $media ) {
				$media = $defaults[ $slug ]['media'];
			}

			if ( ! $selector && ! $media ) {
				continue;
			}

			$valid[ $slug ] = [
				'label'    => (string) ( $breakpoint['label'] ?? $slug ),
				'selector' => $selector,
				'media'    => $media,
				'hidden'   => ! empty( $breakpoint['hidden'] ),
				'builtin'  => $builtin,
			];
		}

		return $valid;
	}

	/**
	 * Add the CSS that hides blocks at each breakpoint.
	 *
	 * @return void
	 */
	public function add_breakpoint_css(): void {
		$css = '';

		foreach ( self::get_breakpoints() as $slug => $breakpoint ) {
			$rule = "[data-visibility-{$slug}=\"hide\"] { display: none !important; }";

			if ( $breakpoint['selector'] ) {
				$rule = ":where({$breakpoint['selector']}) {$rule}";
			}

			if ( $breakpoint['media'] ) {
				$rule = "@media {$breakpoint['media']} { {$rule} }";
			}

			$css .= $rule . "\n";
		}

		if ( $css ) {
			wp_add_inline_style( 'vgtbt-block-styles', $css );
		}
	}

	/**
	 * Strip characters that would let a filtered value escape its rule or the style tag.
	 *
	 * @param mixed $value Filtered value.
	 *
	 * @return string
	 */
	private static function sanitize_css_value( mixed $value ): string {
		return is_string( $value ) ? trim( str_replace( [ '{', '}', ';', '<', '*/' ], '', $value ) ) : '';
	}

	/**
	 * Add breakpoint visibility block attributes.
	 *
	 * @return void
	 */
	private static function breakpoint_visibility(): void {
		add_filter(
			'render_block',
			function ( string $block_content, array $block ): string {
				// Skip if no visibility settings.
				if ( is_admin() || empty( $block['attrs']['breakpointVisibility'] ) ) {
					return $block_content;
				}

				$visibility = $block['attrs']['breakpointVisibility'];
				$block_id   = uniqid();
				$processor  = new \WP_HTML_Tag_Processor( $block_content );

				if ( ! $processor->next_tag() ) {
					return $block_content;
				}

				// Static blocks already saved these, so set them rather than appending duplicates.
				$processor->set_attribute( 'data-block', $block_id );

				if ( empty( $visibility['useCustom'] ) ) {
					foreach ( array_keys( self::get_breakpoints() ) as $breakpoint ) {
						if ( ! empty( $visibility[ $breakpoint ] ) ) {
							$processor->set_attribute( "data-visibility-{$breakpoint}", 'hide' );
						}
					}
				}

				$block_content = $processor->get_updated_html();

				if ( ! empty( $visibility['useCustom'] ) ) {
					$custom = $visibility['customBreakpoint'];
					$css    = self::generate_custom_breakpoint_css(
						$block_id,
						$custom['width'] ?? '768',
						$custom['unit'] ?? 'px',
						$custom['action'] ?? 'hide',
						$custom['mobileFirst'] ?? false
					);

					$block_content .= sprintf(
						"<style>%s</style>\n",
						$css
					);
				}

				return $block_content;
			},
			10,
			2
		);
	}

	/**
	 * Generate custom CSS for the block
	 *
	 * @param string $block_id     Block's Unique ID.
	 * @param string $width        Breakpoint width.
	 * @param string $unit         CSS unit.
	 * @param string $action       'show' or 'hide'.
	 * @param bool   $mobile_first Whether to use mobile-first approach.
	 *
	 * @return string
	 */
	private static function generate_custom_breakpoint_css(
		string $block_id,
		string $width,
		string $unit,
		string $action,
		bool $mobile_first
	): string {
		$condition = $mobile_first ? "(min-width: {$width}{$unit})" : "(max-width: {$width}{$unit})";

		// Only hide, so the block keeps its own display everywhere else.
		$media_query = 'show' === $action ? "@media not all and {$condition}" : "@media {$condition}";

		return "{$media_query} { [data-block=\"{$block_id}\"] { display: none !important; } }";
	}

	/**
	 * Localize JS vars.
	 *
	 * @return void
	 */
	private function localize_js_vars(): void {
		add_action(
			'enqueue_block_editor_assets',
			function () {
				wp_localize_script(
					'vgtbt-editor-scripts',
					'vgtbtBreakpointVisibility',
					[
						'excludeBlocks' => $this->get_exclude_blocks(),
						'breakpoints'   => array_map(
							fn( string $slug, array $breakpoint ): array => [
								'slug'    => $slug,
								'label'   => $breakpoint['label'],
								'hidden'  => $breakpoint['hidden'],
								'builtin' => $breakpoint['builtin'],
							],
							array_keys( self::get_breakpoints() ),
							self::get_breakpoints()
						),
					]
				);
			},
			20
		);
	}

	/**
	 * Get exclude blocks.
	 *
	 * @return array
	 */
	private function get_exclude_blocks(): array {
		return apply_filters(
			'vgtbt_breakpoint_visibility_exclude_blocks',
			[
				'core/rss',
				'gravityforms/form',
				'core/breadcrumbs',
			]
		);
	}
}
