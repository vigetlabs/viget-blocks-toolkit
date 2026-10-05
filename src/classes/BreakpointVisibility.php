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

		// Localize JS vars.
		$this->localize_js_vars();
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
					foreach ( [ 'desktop', 'tablet', 'mobile' ] as $breakpoint ) {
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
