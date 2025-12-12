<?php
/**
 * FAQPage Schema Handler
 *
 * @package Viget\BlocksToolkit\Schema
 */

namespace Viget\BlocksToolkit\Schema;

use WP_HTML_Tag_Processor;

/**
 * FAQPage Schema Handler Class
 */
class FAQPageSchema extends BaseSchema {

	/**
	 * Array to store FAQ data from accordion blocks.
	 *
	 * @var array
	 */
	private $faq_items = [];

	/**
	 * Get the Schema.org type this handler manages.
	 *
	 * @return string
	 */
	public function get_schema_type(): string {
		return 'FAQPage';
	}

	/**
	 * Register hooks for this schema type.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		// Render hook for accordion blocks.
		add_filter(
			'render_block_core/accordion',
			[ $this, 'render_accordion_faqs' ],
			10,
			2
		);

		// Primary Rank Math hook - called early in rendering process.
		add_filter(
			'rank_math/snippet/rich_snippet_faqpage_entity',
			[ $this, 'filter_rank_math_faqpage_entity' ],
			10,
			1
		);

		// Fallback Rank Math hook - extract from JSON-LD.
		add_filter(
			'rank_math/json_ld',
			[ $this, 'filter_rank_math_json_ld' ],
			10,
			1
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

		// Bail early if there's no Accordion block.
		if ( ! $processor->next_tag( [ 'class_name' => 'wp-block-accordion' ] ) ) {
			return $block_content;
		}

		// Add attributes to wrapping accordion block.
		$processor->set_attribute( 'itemscope', true );
		$processor->set_attribute( 'itemtype', 'https://schema.org/FAQPage' );

		$faq_items = $this->process_accordion_items( $processor, $block_content );

		if ( ! empty( $faq_items ) ) {
			$this->faq_items = array_merge( $this->faq_items, $faq_items );
		}

		return $processor->get_updated_html();
	}

	/**
	 * Process accordion items to add microdata attributes and extract FAQ data.
	 *
	 * @param WP_HTML_Tag_Processor $processor The HTML tag processor.
	 * @param string                 $html      The original HTML content.
	 *
	 * @return array Array of FAQ items.
	 */
	private function process_accordion_items( WP_HTML_Tag_Processor $processor, string $html ): array {
		$faq_items = $this->extract_faq_data_from_html( $html );

		$processor->next_tag( [ 'class_name' => 'wp-block-accordion' ] );

		while ( $processor->next_tag( [ 'class_name' => 'wp-block-accordion-item' ] ) ) {
			$processor->set_attribute( 'itemscope', true );
			$processor->set_attribute( 'itemprop', 'mainEntity' );
			$processor->set_attribute( 'itemtype', 'https://schema.org/Question' );

			if ( $processor->next_tag( [ 'class_name' => 'wp-block-accordion-heading__toggle-title' ] ) ) {
				$processor->set_attribute( 'itemprop', 'name' );
			}

			if ( ! $processor->next_tag( [ 'class_name' => 'wp-block-accordion-panel' ] ) ) {
				continue;
			}

			$processor->set_attribute( 'itemscope', true );
			$processor->set_attribute( 'itemprop', 'acceptedAnswer' );
			$processor->set_attribute( 'itemtype', 'https://schema.org/Answer' );

			$found_text_element = false;
			$tags_to_try = [ 'p', 'div', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'ul', 'ol', 'li', 'span' ];
			foreach ( $tags_to_try as $tag ) {
				if ( $processor->next_tag( $tag ) ) {
					$processor->set_attribute( 'itemprop', 'text' );
					$found_text_element = true;
					break;
				}
			}

			if ( ! $found_text_element ) {
				$processor->next_tag( [ 'class_name' => 'wp-block-accordion-panel' ] );
				$processor->set_attribute( 'itemprop', 'text' );
			}
		}

		return $faq_items;
	}

	/**
	 * Extract FAQ data from HTML.
	 *
	 * @param string $html The HTML content.
	 *
	 * @return array Array of FAQ items.
	 */
	private function extract_faq_data_from_html( string $html ): array {
		$faq_items = [];
		$questions = [];

		$question_pattern = '/<[^>]*class="[^"]*wp-block-accordion-heading__toggle-title[^"]*"[^>]*>(.*?)<\/[^>]+>/is';

		if ( preg_match_all( $question_pattern, $html, $question_matches ) ) {
			foreach ( $question_matches[1] as $question_html ) {
				$questions[] = $this->extract_text_content( $question_html );
			}
		}

		$answers    = [];
		$item_parts = preg_split( '/<[^>]*class="[^"]*wp-block-accordion-item[^"]*"[^>]*>/i', $html );
		array_shift( $item_parts );

		foreach ( $item_parts as $item_html ) {
			if ( preg_match( '/<[^>]*class="[^"]*wp-block-accordion-panel[^"]*"[^>]*>(.*?)(?=<\/[^>]*wp-block-accordion-item|<\/[^>]*wp-block-accordion[^>]*>|$)/is', $item_html, $panel_match ) ) {
				$answers[] = $this->extract_text_content( $panel_match[1] );
			}
		}

		$count = min( count( $questions ), count( $answers ) );
		for ( $i = 0; $i < $count; $i++ ) {
			if ( empty( $questions[ $i ] ) || empty( $answers[ $i ] ) ) {
				continue;
			}

			$faq_items[] = [
				'@type'          => 'Question',
				'name'           => $questions[ $i ],
				'acceptedAnswer' => [
					'@type' => 'Answer',
					'text'  => $answers[ $i ],
				],
			];
		}

		return $faq_items;
	}

	/**
	 * Filter Rank Math FAQPage entity to append accordion FAQs.
	 *
	 * @param array|null $entity The Rank Math FAQPage entity, or null if not present.
	 *
	 * @return array|null Modified entity or null.
	 */
	public function filter_rank_math_faqpage_entity( $entity ) {
		if ( \is_array( $entity ) && isset( $entity['mainEntity'] ) && \is_array( $entity['mainEntity'] ) ) {
			$this->rank_math_data = $entity['mainEntity'];
		}

		if ( ! empty( $this->faq_items ) ) {
			if ( \is_array( $entity ) && isset( $entity['mainEntity'] ) && \is_array( $entity['mainEntity'] ) ) {
				$entity['mainEntity'] = array_merge(
					$entity['mainEntity'],
					$this->faq_items
				);
			} elseif ( \is_array( $entity ) ) {
				$entity['mainEntity'] = $this->faq_items;
			} else {
				$entity = [
					'@context' => 'https://schema.org',
					'@type'    => 'FAQPage',
					'mainEntity' => $this->faq_items,
				];
			}
			$this->set_rank_math_processed();
		} elseif ( \is_array( $entity ) && isset( $entity['mainEntity'] ) ) {
			$this->set_rank_math_processed();
		}

		return $entity;
	}

	/**
	 * Filter Rank Math JSON-LD to extract FAQPage data.
	 *
	 * @param array $data The Rank Math schema data.
	 *
	 * @return array Filtered schema data.
	 */
	public function filter_rank_math_json_ld( array $data ): array {
		if ( ! isset( $data['@graph'] ) || ! is_array( $data['@graph'] ) ) {
			foreach ( $data as $key => $item ) {
				if ( ! \is_array( $item ) ) {
					continue;
				}

				$has_faqpage = false;
				if ( isset( $item['@type'] ) ) {
					$types = \is_array( $item['@type'] ) ? $item['@type'] : [ $item['@type'] ];
					$has_faqpage = in_array( 'FAQPage', $types, true );
				}

				if ( ! $has_faqpage && ( 'faqs' === strtolower( $key ) || 'faqpage' === strtolower( $key ) ) ) {
					if ( isset( $item['mainEntity'] ) && \is_array( $item['mainEntity'] ) ) {
						$has_faqpage = true;
					}
				}

				if ( $has_faqpage && isset( $item['mainEntity'] ) && \is_array( $item['mainEntity'] ) && ! empty( $item['mainEntity'] ) ) {
					$this->rank_math_data = $item['mainEntity'];
					unset( $data[ $key ] );
				}
			}

			return $data;
		}

		foreach ( $data['@graph'] as $index => $item ) {
			if ( ! isset( $item['@type'] ) ) {
				continue;
			}

			$types = \is_array( $item['@type'] ) ? $item['@type'] : [ $item['@type'] ];

			if ( ! \in_array( 'FAQPage', $types, true ) || ! isset( $item['mainEntity'] ) || ! \is_array( $item['mainEntity'] ) || empty( $item['mainEntity'] ) ) {
				continue;
			}

			$this->rank_math_data = $item['mainEntity'];

			if ( \count( $types ) === 1 ) {
				unset( $data['@graph'][ $index ] );
			} else {
				$new_types = array_filter( $types, function( $type ) {
					return 'FAQPage' !== $type;
				} );
				$data['@graph'][ $index ]['@type'] = \count( $new_types ) === 1 ? reset( $new_types ) : array_values( $new_types );
				unset( $data['@graph'][ $index ]['mainEntity'] );
			}
		}

		$data['@graph'] = array_values( $data['@graph'] );

		return $data;
	}

	/**
	 * Get the schema data for JSON-LD output.
	 *
	 * @return array
	 */
	public function get_schema_data(): array {
		$faq_data = [
			'@context'   => 'https://schema.org',
			'@type'      => 'FAQPage',
			'mainEntity' => [],
		];

		$all_faqs = [];

		if ( isset( $this->rank_math_data ) && is_array( $this->rank_math_data ) && ! empty( $this->rank_math_data ) ) {
			$all_faqs = array_merge( $all_faqs, $this->rank_math_data );
		}

		if ( isset( $this->faq_items ) && \is_array( $this->faq_items ) && ! empty( $this->faq_items ) ) {
			$all_faqs = array_merge( $all_faqs, $this->faq_items );
		}

		if ( empty( $all_faqs ) ) {
			return [];
		}

		$faq_data['mainEntity'] = $all_faqs;

		return $faq_data;
	}
}

