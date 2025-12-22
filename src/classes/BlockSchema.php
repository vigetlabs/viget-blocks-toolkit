<?php
/**
 * Block Schema Support
 *
 * @package Viget\BlocksToolkit
 */

namespace Viget\BlocksToolkit;

use Viget\BlocksToolkit\Schema\BaseSchema;
use Viget\BlocksToolkit\Schema\FAQPageSchema;

/**
 * Block Schema Class
 * Coordinates schema type handlers and manages output.
 */
class BlockSchema {

	/**
	 * Array of registered schema handlers.
	 *
	 * @var BaseSchema[]
	 */
	private $schema_handlers = [];

	/**
	 * Initialize the class.
	 */
	public function __construct() {
		$this->register_schema_handlers();
		$this->add_output_hooks();
	}

	/**
	 * Register schema type handlers.
	 *
	 * @return void
	 */
	private function register_schema_handlers(): void {
		// Register FAQPage schema handler.
		$faq_handler = new FAQPageSchema();
		$this->schema_handlers[] = $faq_handler;
		$faq_handler->register_hooks();

		// Register Rank Math JSON-LD filter for all handlers.
		add_filter( 'rank_math/json_ld', [ $this, 'filter_rank_math_json_ld' ], 20 );
	}

	/**
	 * Add hooks for outputting JSON-LD schemas.
	 * Outputs just before the closing </body> tag.
	 *
	 * @return void
	 */
	private function add_output_hooks(): void {
		// Output our combined JSON-LD schemas.
		add_action( 'wp_print_footer_scripts', [ $this, 'render_all_json_ld' ], 20 );
	}

	/**
	 * Filter Rank Math JSON-LD to allow schema handlers to extract their data.
	 *
	 * @param array $data The Rank Math schema data.
	 *
	 * @return array Filtered schema data.
	 */
	public function filter_rank_math_json_ld( array $data ): array {
		// Let each schema handler filter the Rank Math data.
		foreach ( $this->schema_handlers as $handler ) {
			$data = $handler->filter_rank_math_json_ld( $data );
		}

		return $data;
	}

	/**
	 * Render all collected JSON-LD schemas.
	 * Outputs just before the closing </body> tag.
	 *
	 * @return void
	 */
	public function render_all_json_ld(): void {
		// Collect schema data from all handlers.
		foreach ( $this->schema_handlers as $handler ) {
			$schema_data = $handler->get_schema_data();
			if ( empty( $schema_data ) ) {
				continue;
			}

			if ( $handler->is_rank_math_processed() ) {
				continue;
			}

			$json = wp_json_encode( $schema_data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );
			printf(
				'<script type="application/ld+json" id="vgtbt-schema-%s">%s</script>' . "\n",
				esc_attr( $schema_data['@type'] ),
				$json
			);
		}
	}
}

