<?php
/**
 * Base Schema Class
 *
 * @package Viget\BlocksToolkit\Schema
 */

namespace Viget\BlocksToolkit\Schema;

/**
 * Abstract base class for Schema.org type handlers.
 */
abstract class BaseSchema {

	/**
	 * The Schema.org type this handler manages.
	 *
	 * @var string
	 */
	protected $schema_type;

	/**
	 * Array to store extracted schema data.
	 *
	 * @var array
	 */
	protected $schema_data = [];

	/**
	 * Array to store Rank Math schema data for this type.
	 *
	 * @var array
	 */
	protected $rank_math_data = [];

	/**
	 * Whether Rank Math has already processed this schema type.
	 *
	 * @var bool
	 */
	protected $rank_math_processed = false;

	/**
	 * Get the Schema.org type this handler manages.
	 *
	 * @return string
	 */
	abstract public function get_schema_type(): string;

	/**
	 * Register hooks for this schema type.
	 *
	 * @return void
	 */
	abstract public function register_hooks(): void;

	/**
	 * Get the schema data for JSON-LD output.
	 *
	 * @return array
	 */
	abstract public function get_schema_data(): array;

	/**
	 * Extract text content from HTML, stripping all tags.
	 *
	 * @param string $html The HTML string.
	 * @return string The extracted text content.
	 */
	protected function extract_text_content( string $html ): string {
		// Strip all HTML tags and get all text content, regardless of tag type.
		$text = wp_strip_all_tags( $html );

		// Normalize whitespace - replace multiple spaces/newlines with single space.
		$text = preg_replace( '/\s+/', ' ', $text );

		return trim( $text );
	}

	/**
	 * Filter Rank Math schema to extract data for this schema type.
	 *
	 * @param array $data The Rank Math schema data.
	 * @return array Filtered schema data.
	 */
	public function filter_rank_math_json_ld( array $data ): array {
		if ( ! isset( $data['@graph'] ) || ! is_array( $data['@graph'] ) ) {
			return $data;
		}

		$schema_type = $this->get_schema_type();

		// Extract and remove this schema type from Rank Math's schema graph.
		foreach ( $data['@graph'] as $index => $item ) {
			if ( isset( $item['@type'] ) ) {
				$types = is_array( $item['@type'] ) ? $item['@type'] : [ $item['@type'] ];
				if ( in_array( $schema_type, $types, true ) ) {
					// Store Rank Math data for this schema type.
					$this->rank_math_data = $item;
					// Remove from Rank Math's output (we'll output combined version).
					unset( $data['@graph'][ $index ] );
				}
			}
		}

		// Re-index array.
		$data['@graph'] = array_values( $data['@graph'] );

		return $data;
	}

	/**
	 * Get Rank Math data for this schema type.
	 *
	 * @return array
	 */
	public function get_rank_math_data(): array {
		return $this->rank_math_data;
	}

	/**
	 * Check if Rank Math has processed this schema type.
	 *
	 * @return bool
	 */
	public function is_rank_math_processed(): bool {
		return $this->rank_math_processed;
	}

	/**
	 * Mark Rank Math as having processed this schema type.
	 *
	 * @return void
	 */
	public function set_rank_math_processed(): void {
		$this->rank_math_processed = true;
	}
}

