<?php
/**
 * Post Field Groups
 *
 * Lets an ACF block edit post-level ACF field groups inline.
 *
 * @package Viget\BlocksToolkit
 */

namespace Viget\BlocksToolkit;

/**
 * Class PostFieldGroups
 */
class PostFieldGroups {

	/**
	 * Context key the editor sends unsaved field values under.
	 */
	const CONTEXT_KEY = 'vgtbtPostFields';

	/**
	 * Field group keys by block name.
	 *
	 * @var ?array
	 */
	private ?array $block_groups = null;

	/**
	 * Initialize the hooks.
	 */
	public function __construct() {
		add_action( 'enqueue_block_editor_assets', [ $this, 'localize_block_groups' ], 31 );
		add_filter( 'acf/load_field_group', [ $this, 'enable_field_group_rest' ] );
		add_filter( 'acf/load_fields', [ $this, 'enable_field_bindings' ], 10, 2 );
		add_action( 'wp_ajax_acf/ajax/fetch-block', [ $this, 'setup_preview_values' ], 1 );
	}

	/**
	 * Get the post field groups declared by each block in block.json (`acf.postFieldGroups`).
	 *
	 * @return array Field group keys, keyed by block name.
	 */
	public function get_block_groups(): array {
		if ( null !== $this->block_groups ) {
			return $this->block_groups;
		}

		$this->block_groups = [];

		foreach ( BlockRegistration::get_all_blocks() as $block ) {
			$groups = $block['acf']['postFieldGroups'] ?? [];

			if ( empty( $block['name'] ) || ! $groups || ! is_array( $groups ) ) {
				continue;
			}

			$name = str_contains( $block['name'], '/' ) ? $block['name'] : 'acf/' . $block['name'];

			$this->block_groups[ $name ] = array_values( array_filter( $groups, 'is_string' ) );
		}

		return $this->block_groups;
	}

	/**
	 * Whether a field group key is used by any block.
	 *
	 * @param string $key Field group key.
	 *
	 * @return bool
	 */
	public function is_block_group( string $key ): bool {
		return in_array( $key, array_merge( [], ...array_values( $this->get_block_groups() ) ), true );
	}

	/**
	 * Whether a field group has a post type location rule.
	 *
	 * @param array $field_group Field group.
	 *
	 * @return bool
	 */
	public function has_post_type_location( array $field_group ): bool {
		foreach ( (array) ( $field_group['location'] ?? [] ) as $rules ) {
			foreach ( (array) $rules as $rule ) {
				if ( 'post_type' === ( $rule['param'] ?? '' ) && '==' === ( $rule['operator'] ?? '' ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Pass the editor each block's field groups that show on the current post.
	 *
	 * @return void
	 */
	public function localize_block_groups(): void {
		$post = get_post();

		if ( ! $post || ! function_exists( 'acf_get_field_groups' ) || ! $this->get_block_groups() ) {
			return;
		}

		$visible = wp_list_pluck(
			acf_get_field_groups(
				[
					'post_id'   => $post->ID,
					'post_type' => $post->post_type,
				]
			),
			'key'
		);
		$blocks  = [];

		foreach ( $this->get_block_groups() as $name => $keys ) {
			foreach ( $keys as $key ) {
				$field_group = acf_get_field_group( $key );

				if ( ! $field_group || ! $this->has_post_type_location( $field_group ) ) {
					_doing_it_wrong(
						__METHOD__,
						/* translators: 1: Field group key, 2: Block name. */
						esc_html( sprintf( __( 'Field group %1$s in %2$s postFieldGroups must exist and have a post type location.', 'viget-blocks-toolkit' ), $key, $name ) ),
						'1.2.0'
					);
					continue;
				}

				if ( in_array( $key, $visible, true ) ) {
					$blocks[ $name ][] = $key;
				}
			}
		}

		wp_add_inline_script(
			'vgtbt-editor-scripts',
			'window.vgtbtPostFieldGroups = ' . wp_json_encode( (object) $blocks ) . ';',
			'before'
		);
	}

	/**
	 * Expose block field groups to the REST API.
	 *
	 * @param array $field_group Field group.
	 *
	 * @return array
	 */
	public function enable_field_group_rest( array $field_group ): array {
		if ( $this->is_block_group( $field_group['key'] ?? '' ) ) {
			$field_group['show_in_rest'] = 1;
		}

		return $field_group;
	}

	/**
	 * Allow access to block field group values in the editor.
	 *
	 * @param array $fields Fields.
	 * @param array $parent_item Field group or parent field.
	 *
	 * @return array
	 */
	public function enable_field_bindings( array $fields, array $parent_item ): array {
		if ( ! $this->is_block_group( $parent_item['key'] ?? '' ) ) {
			return $fields;
		}

		return $this->set_bindings( $fields );
	}

	/**
	 * Turn on allow_in_bindings for fields and their sub fields.
	 *
	 * @param array $fields Fields.
	 *
	 * @return array
	 */
	private function set_bindings( array $fields ): array {
		foreach ( $fields as &$field ) {
			$field['allow_in_bindings'] = 1;

			if ( ! empty( $field['sub_fields'] ) ) {
				$field['sub_fields'] = $this->set_bindings( $field['sub_fields'] );
			}
		}

		return $fields;
	}

	/**
	 * Load a block preview with the post's unsaved field values from the editor.
	 *
	 * Runs before ACF's own fetch-block handler.
	 *
	 * @return void
	 */
	public function setup_preview_values(): void {
		if ( ! function_exists( 'acf_verify_ajax' ) || ! acf_verify_ajax() ) {
			return;
		}

		$context = json_decode( wp_unslash( $_REQUEST['context'] ?? '' ), true ); // phpcs:ignore WordPress.Security -- JSON, only rendered; nonce verified by acf_verify_ajax().
		$values  = is_array( $context ) ? json_decode( (string) ( $context[ self::CONTEXT_KEY ] ?? '' ), true ) : null;
		$post_id = absint( $_REQUEST['post_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification -- verified by acf_verify_ajax().

		if ( ! is_array( $values ) || ! $values || ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Capturing runs each field's update_value, and the taxonomy field's would assign terms to the post.
		$taxonomy = acf_get_field_type( 'taxonomy' );
		$removed  = $taxonomy && remove_filter( 'acf/update_value/type=taxonomy', [ $taxonomy, 'update_value' ], 10 );

		acf_setup_meta( $values, $post_id );

		if ( $removed ) {
			add_filter( 'acf/update_value/type=taxonomy', [ $taxonomy, 'update_value' ], 10, 3 );
		}
	}
}
