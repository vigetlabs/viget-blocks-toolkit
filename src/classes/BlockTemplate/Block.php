<?php
/**
 * Template Block
 *
 * @package Viget\BlocksToolkit
 */

namespace Viget\BlocksToolkit\BlockTemplate;

use Exception;

/**
 * Template Block
 */
class Block extends Template {

	/**
	 * Block
	 *
	 * @var string
	 */
	protected string $block;

	/**
	 * WP Block
	 *
	 * @var \WP_Block_Type
	 */
	protected \WP_Block_Type $wp_block;

	/**
	 * Attributes
	 *
	 * @var array
	 */
	protected array $attributes = [];

	/**
	 * Constructor
	 *
	 * @param string  $block Block.
	 * @param array   $attributes Attributes.
	 * @param Block[] $inner_blocks Inner blocks.
	 *
	 * @throws Exception Exception thrown when block is not registered.
	 */
	public function __construct( string $block, array $attributes = [], array $inner_blocks = [] ) {
		$registered = \WP_Block_Type_Registry::get_instance()->get_registered( $block );

		if ( ! $registered ) {
			throw new Exception( 'Block not registered' );
		}

		$this->block    = $block;
		$this->wp_block = $registered;

		foreach ( $attributes as $attribute => $value ) {
			$this->attr( $attribute, $value );
		}

		foreach ( $inner_blocks as $inner_block ) {
			$this->add( $inner_block );
		}

		return $this;
	}

	/**
	 * Get the block name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return $this->block;
	}

	/**
	 * Set Attribute
	 *
	 * @param string $key Key.
	 * @param mixed  $value Value.
	 *
	 * @return Block
	 * @throws Exception Exception thrown when attribute is not supported.
	 */
	public function attr( string $key, mixed $value ): Block {
		$supported = $this->get_supported_attributes();

		if ( ! in_array( $key, $supported, true ) ) {
			throw new Exception(
				sprintf(
					// translators: %1$s represents the attribute key, %2$s lists the supported attributes.
					esc_html__( 'Unsupported block attribute: %1$s. Must be one of: %2$s', 'viget-blocks-toolkit' ),
					esc_html( $key ),
					esc_html( implode( ', ', $supported ) )
				)
			);
		}

		$this->attributes[ $key ] = $value;

		return $this;
	}

	/**
	 * Alias method for attr()
	 *
	 * @param string $key Key.
	 * @param mixed  $value Value.
	 *
	 * @return Block
	 * @throws Exception Exception thrown when attribute is not supported.
	 */
	public function attribute( string $key, mixed $value ): Block {
		return $this->attr( $key, $value );
	}

	/**
	 * Get supported attributes
	 *
	 * @return array
	 */
	protected function get_supported_attributes(): array {
		$supported = [];

		if ( ! empty( $this->wp_block->attributes ) ) {
			foreach ( $this->wp_block->attributes as $key => $attribute ) {
				$supported[] = $key;
			}
		}

		if ( ! empty( $this->wp_block->supports ) ) {
			foreach ( $this->wp_block->supports as $key => $support ) {
				if ( $support ) {
					$supported[] = $key;
				}
			}
		}

		return $supported;
	}

	/**
	 * Add a template block.
	 *
	 * @param Block|string $block Template block.
	 *
	 * @return Block
	 * @throws Exception Exception thrown when block is not allowed.
	 */
	public function add( Block|string $block ): Block {
		$allowed    = $this->get_allowed_blocks();
		$block_name = is_string( $block ) ? $block : $block->get_name();

		if ( ! empty( $allowed ) && ! in_array( $block_name, $allowed, true ) ) {
			throw new Exception( 'Block not allowed' );
		}

		parent::add( $block );

		return $this;
	}

	/**
	 * Get allowed blocks
	 *
	 * @return array
	 */
	public function get_allowed_blocks(): array {
		$allowed = [];

		if ( ! empty( $this->wp_block->allowed_blocks ) ) {
			$allowed = $this->wp_block->allowed_blocks;
		}

		return $allowed;
	}

	/**
	 * Get the template.
	 *
	 * @return array
	 */
	public function get(): array {
		$template = [ $this->block ];

		if ( ! empty( $this->attributes ) || ! empty( $this->template ) ) {
			$template[] = $this->attributes;

			if ( ! empty( $this->template ) ) {
				$template[] = $this->template;
			}
		}

		return $template;
	}
}
