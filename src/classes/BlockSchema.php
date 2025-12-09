<?php
/**
 * Block Schema Support
 *
 * @package Viget\BlocksToolkit
 */

namespace Viget\BlocksToolkit;

use WP_HTML_Tag_Processor;

/**
 * Block Schema Class
 */
class BlockSchema {

	/**
	 * Initialize the class.
	 */
	public function __construct() {
		$this->add_render_hooks();
	}

	/**
	 * Add render hooks for blocks with schema support.
	 *
	 * @return void
	 */
	private function add_render_hooks(): void {
		add_filter(
			'render_block_core/accordion',
			[ $this, 'render_accordion_faqs' ],
			10,
			2
		);
	}

	/**
	 * Render FAQ Schema for accordion blocks.
	 *
	 * @param string $block_content The block content.
	 * @param array  $block         The block array.
	 *
	 * @return string
	 */
	public function render_accordion_faqs( string $block_content, array $block ): string {
		// Check if FAQ Schema is enabled via attribute.
		if ( empty( $block['attrs']['useFaqSchema'] ) ) {
			return $block_content;
		}

		$processor = new WP_HTML_Tag_Processor( $block_content );

		// Add attributes to wrapping accordion block.
		$processor->set_attribute( 'itemscope', true );
		$processor->set_attribute( 'itemtype', 'https://schema.org/FAQPage' );

		// Loop through accordion items and add attributes.
		while ( $processor->next_tag( [ 'class_name' => 'wp-block-accordion-item' ] ) ) {
			$processor->set_attribute( 'itemscope', true );
			$processor->set_attribute( 'itemprop', 'mainEntity' );
			$processor->set_attribute( 'itemtype', 'https://schema.org/Question' );

			// Add attributes to the title element.
			if ( $processor->next_tag( [ 'class_name' => 'wp-block-accordion-heading__toggle-title' ] ) ) {
				$processor->set_attribute( 'itemprop', 'name' );
			}

			// Add attributes to the panel.
			if ( $processor->next_tag( [ 'class_name' => 'wp-block-accordion-panel' ] ) ) {
				$processor->set_attribute( 'itemscope', true );
				$processor->set_attribute( 'itemprop', 'acceptedAnswer' );
				$processor->set_attribute( 'itemtype', 'https://schema.org/Answer' );

				// Add attribute to first paragraph.
				if ( $processor->next_tag( 'p' ) ) {
					$processor->set_attribute( 'itemprop', 'text' );
				}
			}
		}

		return $processor->get_updated_html();
	}
}

