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

/**
 * Class NavigationSlugHandler
 */
class NavigationSlugHandler {

	/**
	 * Cache for navigation slug lookups to avoid repeated queries.
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
				'resolve_navigation_slug'
			]
		);

		// Hook into navigation post updates to keep references in sync
		add_action(
			'post_updated',
			[
				$this,
				'handle_navigation_update'
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
		// Only process core/navigation blocks
		if ( empty( $parsed_block['blockName'] ) || 'core/navigation' !== $parsed_block['blockName'] ) {
			return $parsed_block;
		}

		// Check if refSlug attribute exists
		if ( empty( $parsed_block['attrs']['refSlug'] ) ) {
			return $parsed_block;
		}

		$slug            = $parsed_block['attrs']['refSlug'];
		$navigation_post = $this->get_navigation_by_slug( $slug );

		if ( $navigation_post ) {
			// Update both ref and refSlug with current values to keep them in sync
			$parsed_block['attrs']['ref']     = $navigation_post->ID;
			$parsed_block['attrs']['refSlug'] = $navigation_post->post_name;
		} else {
			// Slug not found - remove refSlug to prevent stale data, keep ref as fallback
			unset( $parsed_block['attrs']['refSlug'] );
		}

		return $parsed_block;
	}

	/**
	 * Get navigation post by slug with caching.
	 *
	 * @param string $slug The navigation post slug.
	 *
	 * @return WP_Post|null The navigation post or null if not found.
	 */
	private function get_navigation_by_slug( string $slug ): ?WP_Post {
		// Check cache first
		if ( isset( self::$slug_cache[ $slug ] ) ) {
			return self::$slug_cache[ $slug ];
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

		// Cache the result
		self::$slug_cache[ $slug ] = $result;

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
		// Only process wp_navigation posts
		if ( 'wp_navigation' !== $post_after->post_type ) {
			return;
		}

		// Check if the slug (post_name) changed
		if ( $post_after->post_name !== $post_before->post_name ) {
			$this->update_navigation_references( $post_before->post_name, $post_after->post_name, $post_id );
		}

		// Clear cache for this navigation
		unset( self::$slug_cache[ $post_after->post_name ] );
		if ( $post_after->post_name !== $post_before->post_name ) {
			unset( self::$slug_cache[ $post_before->post_name ] );
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
		// Find template parts and patterns that reference the old slug
		$template_parts = get_posts(
			[
				'post_type'      => 'wp_template_part',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'meta_query'     => [
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

			// Look for navigation blocks with the old slug
			$pattern = '/<!-- wp:navigation\s+({[^}]*"refSlug":"' . preg_quote( $old_slug, '/' ) . '"[^}]*})\s*\/-->/';

			if ( ! preg_match( $pattern, $content, $matches ) ) {
				continue;
			}

			// Parse the attributes
			$attributes = json_decode( $matches[1], true );

			if ( ! $attributes || ! isset( $attributes['refSlug'] ) ) {
				continue;
			}

			// Update both refSlug and ref
			$attributes['refSlug'] = $new_slug;
			$attributes['ref']     = $post_id;

			// Replace the old block with updated attributes
			$new_block   = '<!-- wp:navigation ' . wp_json_encode( $attributes ) . ' /-->';
			$new_content = preg_replace( $pattern, $new_block, $content );

			// Update the post
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
