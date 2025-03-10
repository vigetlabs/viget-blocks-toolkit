<?php
/**
 * Helper functions
 *
 * @package Viget\BlocksToolkit
 */

use Viget\BlocksToolkit\Core;

if ( ! function_exists( 'vgtbt' ) ) {
	/**
	 * Viget Blocks Toolkit Core API instance.
	 *
	 * @return Core
	 */
	function vgtbt(): Core { // phpcs:ignore
		return Core::instance();
	}
}

if ( ! function_exists( 'block_attrs' ) ) {
	/**
	 * Render the block attributes.
	 *
	 * @since 1.0.0
	 *
	 * @param array  $block The block array.
	 * @param string $custom_class A custom class.
	 * @param array  $attrs Array of attributes.
	 */
	function block_attrs( array $block, string $custom_class = '', array $attrs = [] ): void { // phpcs:ignore
		$id = ! empty( $attrs['id'] ) ? $attrs['id'] : get_block_id( $block );
		$id = apply_filters( 'vgtbt_block_id_attr', $id, $block );

		if ( is_admin() ) {
			if ( ! empty( $block['anchor'] ) ) {
				$attrs['data-id'] = $block['anchor'];
			} else {
				$attrs['data-id'] = $id;
			}
		} else {
			$attrs['id'] = $id;
		}

		$block_class = get_block_class( $block, $custom_class );

		if ( ! empty( $attrs['class'] ) ) {
			$attrs['class'] .= ' ' . $block_class;
		} else {
			$attrs['class'] = $block_class;
		}

		$block_styles = ! is_admin() ? get_core_styles( $block ) : '';
		if ( ! empty( $attrs['style'] ) ) {
			$attrs['style'] .= $block_styles;
		} else {
			$attrs['style'] = $block_styles;
		}

		$jsx_attr = apply_filters( 'vgtbt_block_jsx_attr', false );
		if ( $jsx_attr && ! array_key_exists( 'data-supports-jsx', $attrs ) && ! empty( $block['supports']['jsx'] ) ) {
			$attrs['data-supports-jsx'] = 'true';
		}

		$attrs = apply_filters( 'vgtbt_block_attrs', $attrs, $block );

		// Prepare Extra attributes.
		$extra = [
			'class' => $attrs['class'],
			'style' => $attrs['style'],
		];

		if ( ! is_preview() ) {
			unset( $attrs['class'] );
			unset( $attrs['style'] );
		}

		if ( ! empty( $attrs['id'] ) ) {
			$extra['id'] = $attrs['id'];
			unset( $attrs['id'] );
		}

		$block_supports = WP_Block_Supports::get_instance();

		if ( is_null( $block_supports::$block_to_render ) ) {
			$attrs = array_merge( $attrs, $extra );
		}

		foreach ( $attrs as $key => $value ) {
			if ( is_null( $value ) ) {
				continue;
			}
			echo ' ' . esc_attr( $key ) . '="' . esc_attr( $value ) . '"';
		}

		echo ' '; // Prep for additional block_attrs.

		do_action( 'vgtbt_block_attr', $block );

		if ( is_null( $block_supports::$block_to_render ) ) {
			return;
		}

		echo wp_kses_data( get_block_wrapper_attributes( $extra ) );
	}
}

if ( ! function_exists( 'get_block_id' ) ) {
	/**
	 * Get the block ID attribute.
	 *
	 * @since 1.0.0
	 *
	 * @param array $block The block array.
	 * @param bool  $ignore_anchor IF anchor should be ignored.
	 *
	 * @return string
	 */
	function get_block_id( array $block, bool $ignore_anchor = false ): string { // phpcs:ignore
		if ( ! empty( $block['anchor'] ) && ! $ignore_anchor ) {
			$id = $block['anchor'];
		} elseif ( ! empty( $block['blockId'] ) ) {
			return $block['blockId'];
		} else {
			$prefix = str_replace( 'acf/', '', $block['name'] );
			if ( empty( $block['id'] ) ) {
				$block['id'] = uniqid();
			}
			$id = $prefix . '_' . $block['id'];
		}

		return apply_filters( 'vgtbt_block_id', $id, $block );
	}
}

if ( ! function_exists( 'get_block_class' ) ) {
	/**
	 * Get the block class attribute.
	 *
	 * @since 1.0.0
	 *
	 * @param array  $block The block array.
	 * @param string $custom_class Any custom classes.
	 *
	 * @return string
	 */
	function get_block_class( array $block, string $custom_class = '' ): string { // phpcs:ignore
		$classes = [
			'wp-block',
			'acf-block',
			'acf-block-' . str_replace( 'acf/', '', $block['name'] ),
		];

		$core_classes = get_core_classes( $block );

		if ( $core_classes ) {
			$classes = array_merge( $classes, $core_classes );
		}

		if ( ! empty( $block['className'] ) ) {
			$classes[] = $block['className'];
		}

		if ( ! empty( $custom_class ) ) {
			$classes[] = $custom_class;
		}

		if ( ! empty( $block['align'] ) ) {
			$classes[] = 'align' . $block['align'];
		}

		if ( ! empty( $block['data']['limit_visibility'] ) ) {
			$classes[] = 'vgtbt-limit-visibility';
		}

		return apply_filters( 'vgtbt_block_class', implode( ' ', $classes ), $block );
	}
}

if ( ! function_exists( 'vgtbt_render_block' ) ) {
	/**
	 * Render an ACF block with specific properties.
	 *
	 * @since 1.0.0
	 *
	 * @param string $block_name The block name.
	 * @param array  $props Block properties.
	 */
	function vgtbt_render_block( string $block_name, array $props = [] ): void {
		if ( ! str_starts_with( $block_name, 'acf/' ) ) {
			$block_name = "acf/$block_name";
		}

		$block = array_merge(
			[
				'name' => $block_name,
			],
			$props
		);

		if ( empty( $block['id'] ) ) {
			$block['id'] = uniqid();
		}

		if ( ! function_exists( 'acf_render_block' ) ) {
			render_block( $block );
		} else {
			acf_render_block( $block );
		}
	}
}

if ( ! function_exists( 'get_block_from_blocks' ) ) {
	/**
	 * Retrieves the first instance of the specified block type.
	 *
	 * @since 1.0.0
	 *
	 * @param string $name The name of block to retrieve.
	 * @param array  $blocks Array of blocks to search through.
	 *
	 * @return array|false
	 */
	function get_block_from_blocks( string $name, array $blocks ): array|false { // phpcs:ignore
		foreach ( $blocks as $block ) {
			if ( $name === $block['blockName'] ) {
				return $block;
			}

			if ( ! empty( $block['innerBlocks'] ) ) {
				$result = get_block_from_blocks( $name, $block['innerBlocks'] );

				if ( $result ) {
					return $result;
				}
			}
		}

		return false;
	}
}

if ( ! function_exists( 'get_block_fields' ) ) {
	/**
	 * Get fields for a block
	 *
	 * @param string $block_name The block name.
	 *
	 * @return array
	 */
	function get_block_fields( string $block_name ): array { // phpcs:ignore
		if ( ! str_starts_with( $block_name, 'acf/' ) ) {
			$block_name = "acf/$block_name";
		}

		if ( ! function_exists( 'acf_get_field_groups' ) ) {
			return [];
		}

		$field_groups = acf_get_field_groups();
		$fields       = [];

		foreach ( $field_groups as $field_group ) {
			foreach ( $field_group['location'] as $locations ) {
				foreach ( $locations as $location ) {
					if ( empty( $location['operator'] ) || '==' !== $location['operator'] || empty( $location['param'] ) || 'block' !== $location['param'] ) {
						continue;
					}

					if ( ! in_array( $location['value'], [ 'all', $block_name ], true ) ) {
						continue;
					}

					// Fields may not be loaded yet.
					if ( empty( $field_group['fields'] ) ) {
						$group  = json_decode( file_get_contents( $field_group['local_file'] ), true );
						$fields = array_merge( $fields, $group['fields'] );
					} else {
						$fields = array_merge( $fields, $field_group['fields'] );
					}
				}
			}
		}

		return $fields;
	}
}

if ( ! function_exists( 'get_field_property' ) ) {
	/**
	 * Get a property from a field.
	 *
	 * @param string  $selector The field selector.
	 * @param string  $property The field property.
	 * @param ?string $group_id The Group ID.
	 *
	 * @return string
	 */
	function get_field_property( string $selector, string $property, ?string $group_id = null ): string { // phpcs:ignore
		if ( ! function_exists( 'acf_get_fields' ) ) {
			return '';
		}

		if ( null !== $group_id ) {
			$fields = acf_get_fields( $group_id );
			foreach ( $fields as $field_array ) {
				if ( $selector === $field_array['name'] ) {
					$field = $field_array;
					break;
				}
			}
		} else {
			$field = get_field_object( $selector );
		}

		if ( ! $field || ! is_array( $field ) ) {
			return '';
		}

		if ( empty( $field[ $property ] ) ) {
			return '';
		}

		return $field[ $property ];
	}
}

if ( ! function_exists( 'inner_blocks' ) ) {
	/**
	 * Escape and encode ACF Block InnerBlocks template and allowed blocks
	 *
	 * @since 1.0.0
	 *
	 * @param array $props {
	 *     The properties array.
	 *
	 *     @type array  $allowedBlocks The allowed blocks.
	 *     @type array  $template The block template.
	 *     @type string $templateLock The template lock.
	 *     @type string $className The class name.
	 * }
	 *
	 * @return void
	 */
	function inner_blocks( array $props = [] ): void { // phpcs:ignore
		$json_encode = [ 'allowedBlocks', 'template' ];
		$attributes  = '';

		foreach ( $props as $attr => $value ) {
			$attr_value  = in_array( $attr, $json_encode, true ) ? wp_json_encode( $value ) : $value;
			$attributes .= ' ' . esc_attr( $attr ) . '="' . esc_attr( $attr_value ) . '"';
		}

		printf(
			'<InnerBlocks%s />',
			$attributes // phpcs:ignore
		);
	}
}

if ( ! function_exists( 'print_admin_message' ) ) {
	/**
	 * Output notice to admins only in Block Editor
	 *
	 * @since 1.0.0
	 *
	 * @param string $notice The message.
	 * @param string $class The class name.
	 *
	 * @return void
	 */
	function print_admin_message( string $notice = '', string $class = 'vgtbt-admin-message' ): void { // phpcs:ignore
		if ( ! is_admin() || ! $notice ) {
			return;
		}

		printf(
			'<div class="%s"><p style="padding: 3em; text-align: center;">%s</p></div>',
			esc_attr( $class ),
			nl2br( esc_html( $notice ) )
		);
	}
}

if ( ! function_exists( 'is_acf_saving_field' ) ) {
	/**
	 * Check if the current screen is an ACF edit screen.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	function is_acf_saving_field(): bool { // phpcs:ignore
		global $pagenow;

		if ( doing_action( 'acf/update_field_group' ) ) {
			return true;
		}

		if ( 'post.php' !== $pagenow ) {
			return false;
		}

		if ( empty( $_GET['post'] ) || empty( $_GET['action'] ) ) { // phpcs:ignore
			return false;
		}

		$post_id = sanitize_text_field( wp_unslash( $_GET['post'] ) ); // phpcs:ignore
		$action  = sanitize_text_field( wp_unslash( $_GET['action'] ) ); // phpcs:ignore

		if ( 'edit' === $action && 'acf-field-group' === get_post_type( $post_id ) ) {
			return true;
		}

		return false;
	}
}

if ( ! function_exists( 'get_core_classes' ) ) {
	/**
	 * Get core block classes
	 *
	 * @param array $block The block array.
	 *
	 * @return array
	 */
	function get_core_classes( array $block ): array { // phpcs:ignore
		$classes = [];

		if ( ! empty( $block['backgroundColor'] ) ) {
			$classes[] = 'has-' . $block['backgroundColor'] . '-background-color';
			$classes[] = 'has-background';
		} elseif ( ! empty( $block['attributes']['backgroundColor']['default'] ) ) {
			$classes[] = 'has-' . $block['attributes']['backgroundColor']['default'] . '-background-color';
			$classes[] = 'has-background';
		}

		if ( ! empty( $block['textColor'] ) ) {
			$classes[] = 'has-' . $block['textColor'] . '-color';
			$classes[] = 'has-text-color';
		} elseif ( ! empty( $block['attributes']['textColor']['default'] ) ) {
			$classes[] = 'has-' . $block['attributes']['textColor']['default'] . '-color';
			$classes[] = 'has-text-color';
		}

		return $classes;
	}
}

if ( ! function_exists( 'get_core_styles' ) ) {
	/**
	 * Get core block styles
	 *
	 * @param array $block The block array.
	 *
	 * @return string
	 */
	function get_core_styles( array $block ): string { // phpcs:ignore
		if ( ! empty( $block['style'] ) ) {
			$styles = wp_style_engine_get_styles( $block['style'] );
			return $styles['css'];
		}

		return '';
	}
}
