<?php
/**
 * Navigation Slug Handler
 *
 * Handles slug-based navigation menu references for the core/navigation block.
 * Allows referencing navigation menus by slug instead of hardcoded IDs.
 *
 * @package Viget\BlocksToolkit
 */

namespace Viget\BlocksToolkit;

use WP_Post;
use WP_REST_Response;
use WP_REST_Templates_Controller;

/**
 * Class NavigationSlugHandler
 */
class NavigationSlugHandler {

	/**
	 * Cache for navigation slug lookups to avoid repeated queries, keyed by site ID, then slug.
	 *
	 * @var array
	 */
	private static $slug_cache = [];

	/**
	 * Initialize the handler.
	 */
	public function __construct() {
		$this->init_hooks();
	}

	/**
	 * Initialize WordPress hooks.
	 */
	private function init_hooks() {
		// Hook into block rendering to resolve slug references.
		add_filter(
			'render_block_data',
			[
				$this,
				'resolve_navigation_slug',
			]
		);

		// Resolve slug references in templates and template parts, so the editor loads the same menus the front end renders.
		add_filter(
			'rest_request_after_callbacks',
			[
				$this,
				'resolve_rest_template_navigation_slugs',
			],
			10,
			2
		);

		// Hook into navigation post updates to keep references in sync.
		add_action(
			'post_updated',
			[
				$this,
				'handle_navigation_update',
			],
			10,
			3
		);
	}

	/**
	 * Resolve navigation slug references to IDs during block rendering.
	 *
	 * @param array $parsed_block The parsed block data.
	 *
	 * @return array Modified block data.
	 */
	public function resolve_navigation_slug( $parsed_block ) {
		// Only process core/navigation blocks.
		if ( empty( $parsed_block['blockName'] ) || 'core/navigation' !== $parsed_block['blockName'] ) {
			return $parsed_block;
		}

		// Check if refSlug attribute exists.
		if ( empty( $parsed_block['attrs']['refSlug'] ) ) {
			return $parsed_block;
		}

		$slug            = $parsed_block['attrs']['refSlug'];
		$navigation_post = $this->get_navigation_by_slug( $slug );

		if ( $navigation_post ) {
			// Update both ref and refSlug with current values to keep them in sync.
			$parsed_block['attrs']['ref']     = $navigation_post->ID;
			$parsed_block['attrs']['refSlug'] = $navigation_post->post_name;
		} else {
			// Slug not found - remove refSlug to prevent stale data, keep ref as fallback.
			unset( $parsed_block['attrs']['refSlug'] );
		}

		return $parsed_block;
	}

	/**
	 * Resolve navigation slug references in template and template part REST responses.
	 *
	 * Theme files and saved copies hold the menu IDs of whoever saved them, which differ between environments and
	 * sites. The editor loads menus by `ref`, so it gets the ID that matches `refSlug` here.
	 *
	 * @param WP_REST_Response|WP_Error|mixed $response Result to send to the client.
	 * @param array                           $handler  Route handler used for the request.
	 *
	 * @return WP_REST_Response|WP_Error|mixed
	 */
	public function resolve_rest_template_navigation_slugs( $response, $handler ) {
		if ( ! $response instanceof WP_REST_Response || ! is_array( $handler['callback'] ?? null ) || ! $handler['callback'][0] instanceof WP_REST_Templates_Controller ) {
			return $response;
		}

		$data = $response->get_data();

		if ( isset( $data['content']['raw'] ) ) {
			$data['content']['raw'] = $this->resolve_content_navigation_slugs( $data['content']['raw'] );
		} elseif ( wp_is_numeric_array( $data ) ) {
			foreach ( $data as $index => $template ) {
				if ( isset( $template['content']['raw'] ) ) {
					$data[ $index ]['content']['raw'] = $this->resolve_content_navigation_slugs( $template['content']['raw'] );
				}
			}
		}

		$response->set_data( $data );

		return $response;
	}

	/**
	 * Set each slug-referenced navigation block's `ref` to the ID of the menu with that slug.
	 *
	 * @param string $content Block markup.
	 *
	 * @return string
	 */
	private function resolve_content_navigation_slugs( string $content ): string {
		if ( ! str_contains( $content, '"refSlug"' ) ) {
			return $content;
		}

		return (string) preg_replace_callback(
			'/<!--\s+wp:navigation\s+(\{.*?\})\s+(\/?)-->/s',
			function ( array $matches ): string {
				$attributes = json_decode( $matches[1], true );

				if ( ! is_array( $attributes ) || empty( $attributes['refSlug'] ) ) {
					return $matches[0];
				}

				$navigation_post = $this->get_navigation_by_slug( $attributes['refSlug'] );

				if ( ! $navigation_post || (int) ( $attributes['ref'] ?? 0 ) === $navigation_post->ID ) {
					return $matches[0];
				}

				$attributes['ref'] = $navigation_post->ID;

				return '<!-- wp:navigation ' . serialize_block_attributes( $attributes ) . ' ' . $matches[2] . '-->';
			},
			$content
		);
	}

	/**
	 * Get navigation post by slug with caching.
	 *
	 * @param string $slug The navigation post slug.
	 *
	 * @return WP_Post|null The navigation post or null if not found.
	 */
	private function get_navigation_by_slug( string $slug ): ?WP_Post {
		$site_id = get_current_blog_id();

		// Check cache first. Lookups are per site, since each site has its own menus.
		if ( isset( self::$slug_cache[ $site_id ] ) && array_key_exists( $slug, self::$slug_cache[ $site_id ] ) ) {
			return self::$slug_cache[ $site_id ][ $slug ];
		}

		$navigation = get_posts(
			[
				'post_type'      => 'wp_navigation',
				'name'           => $slug,
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'no_found_rows'  => true,
			]
		);

		$result = ! empty( $navigation ) ? $navigation[0] : null;

		// Cache the result.
		self::$slug_cache[ $site_id ][ $slug ] = $result;

		return $result;
	}

	/**
	 * Handle navigation post updates to keep references in sync.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post_after Post object after the update.
	 * @param WP_Post $post_before Post object before the update.
	 */
	public function handle_navigation_update( $post_id, $post_after, $post_before ) {
		// Only process wp_navigation posts.
		if ( 'wp_navigation' !== $post_after->post_type ) {
			return;
		}

		// Check if the slug (post_name) changed.
		if ( $post_after->post_name !== $post_before->post_name ) {
			$this->update_navigation_references( $post_before->post_name, $post_after->post_name, $post_id );
		}

		// Clear cache for this navigation.
		$site_id = get_current_blog_id();

		unset( self::$slug_cache[ $site_id ][ $post_after->post_name ] );
		if ( $post_after->post_name !== $post_before->post_name ) {
			unset( self::$slug_cache[ $site_id ][ $post_before->post_name ] );
		}
	}

	/**
	 * Update navigation references in template parts and patterns.
	 *
	 * @param string $old_slug The old navigation slug.
	 * @param string $new_slug The new navigation slug.
	 * @param int    $post_id The navigation post ID.
	 */
	private function update_navigation_references( $old_slug, $new_slug, $post_id ) {
		// Find template parts and patterns that reference the old slug.
		$template_parts = get_posts(
			[
				'post_type'      => 'wp_template_part',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'meta_query'     => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Runs only when a navigation's slug changes.
					[
						'key'     => '_wp_template_part_area',
						'compare' => 'EXISTS',
					],
				],
			]
		);

		$patterns = get_posts(
			[
				'post_type'      => 'wp_block',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
			]
		);

		$posts_to_update = array_merge( $template_parts, $patterns );

		foreach ( $posts_to_update as $post_to_update ) {
			$content = $post_to_update->post_content;

			// Look for navigation blocks with the old slug.
			$pattern = '/<!-- wp:navigation\s+({[^}]*"refSlug":"' . preg_quote( $old_slug, '/' ) . '"[^}]*})\s*\/-->/';

			if ( ! preg_match( $pattern, $content, $matches ) ) {
				continue;
			}

			// Parse the attributes.
			$attributes = json_decode( $matches[1], true );

			if ( ! $attributes || ! isset( $attributes['refSlug'] ) ) {
				continue;
			}

			// Update both refSlug and ref.
			$attributes['refSlug'] = $new_slug;
			$attributes['ref']     = $post_id;

			// Replace the old block with updated attributes.
			$new_block   = '<!-- wp:navigation ' . wp_json_encode( $attributes ) . ' /-->';
			$new_content = preg_replace( $pattern, $new_block, $content );

			// Update the post.
			wp_update_post(
				[
					'ID'           => $post_to_update->ID,
					'post_content' => $new_content,
				]
			);
		}
	}

	/**
	 * Clear the slug cache.
	 */
	public static function clear_cache() {
		self::$slug_cache = [];
	}
}
