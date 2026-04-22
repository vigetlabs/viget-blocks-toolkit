<?php
/**
 * Assets
 *
 * @package Viget\BlocksToolkit
 */

namespace Viget\BlocksToolkit;

/**
 * Whether `enqueue_block_assets` is running for the iframed editor canvas.
 *
 * On block editor screens, WordPress runs `enqueue_block_assets` twice:
 *
 * 1. Main pass (editor chrome + setup): `wp_should_load_block_editor_scripts_and_styles()` is true.
 * 2. Iframe content pass inside `_wp_get_iframed_editor_assets()`: that filter is forced false so
 *    only content-appropriate assets are collected for the post editor iframe.
 *
 * Styles and inline CSS meant for blocks inside the iframe must attach only on pass 2. Adding them
 * on pass 1 binds them to the parent document styles and triggers the iframe console warning.
 *
 * @return bool
 */
function vgtbt_is_iframed_editor_asset_pass(): bool {
	if ( ! is_admin() ) {
		return false;
	}

	global $current_screen;
	if ( ! ( $current_screen instanceof \WP_Screen ) || ! $current_screen->is_block_editor() ) {
		return false;
	}

	if ( ! function_exists( 'wp_should_load_block_editor_scripts_and_styles' ) ) {
		return false;
	}

	return ! wp_should_load_block_editor_scripts_and_styles();
}

add_action(
	'init',
	function () {
		$editor_asset_file  = include VGTBT_PLUGIN_PATH . 'build/index.asset.php';
		$iframe_asset_file  = include VGTBT_PLUGIN_PATH . 'build/iframe-editor.asset.php';
		$style_asset_file   = include VGTBT_PLUGIN_PATH . 'build/style.asset.php';
		$dependencies       = array_merge( $editor_asset_file['dependencies'], [ 'wp-blocks', 'wp-dom-ready' ] );
		$iframe_dependencies = array_merge( $iframe_asset_file['dependencies'], [ 'wp-blocks', 'wp-hooks' ] );

		wp_register_script(
			'vgtbt-editor-scripts',
			VGTBT_PLUGIN_URL . 'build/index.js',
			$dependencies,
			$editor_asset_file['version'],
			[ 'in_footer' => true ]
		);

		wp_register_script(
			'vgtbt-editor-iframe',
			VGTBT_PLUGIN_URL . 'build/iframe-editor.js',
			$iframe_dependencies,
			$iframe_asset_file['version'],
			[ 'in_footer' => true ]
		);

		wp_register_style(
			'vgtbt-block-styles',
			VGTBT_PLUGIN_URL . 'build/style.css',
			[],
			$style_asset_file['version']
		);

		wp_set_script_translations(
			'vgtbt-editor-scripts',
			'viget-blocks-toolkit',
			VGTBT_PLUGIN_URL . 'languages'
		);

		wp_set_script_translations(
			'vgtbt-editor-iframe',
			'viget-blocks-toolkit',
			VGTBT_PLUGIN_URL . 'languages'
		);
	}
);

add_action(
	'admin_enqueue_scripts',
	function () {
		wp_enqueue_style(
			'vgtbt-admin-css',
			VGTBT_PLUGIN_URL . 'assets/css/admin.css',
			[],
			VGTBT_VERSION
		);
	}
);

add_action(
	'enqueue_block_editor_assets',
	function () {
		wp_enqueue_script( 'vgtbt-editor-scripts' );
	},
	30
);

add_action(
	'enqueue_block_assets',
	function () {
		wp_enqueue_style( 'vgtbt-block-styles' );

		if ( vgtbt_is_iframed_editor_asset_pass() ) {
			// Block canvas runs in an iframe; use a minimal handle (no wp-edit-post, etc.) so the
			// bundle can print inside `_wp_get_iframed_editor_assets()`.
			wp_enqueue_script( 'vgtbt-editor-iframe' );
		}

		if ( ! vgtbt_is_iframed_editor_asset_pass() ) {
			return;
		}

		$editor_css_path = VGTBT_PLUGIN_PATH . 'build/editor.css';
		if ( file_exists( $editor_css_path ) ) {
			$editor_css = file_get_contents( $editor_css_path );
			if ( false !== $editor_css ) {
				wp_add_inline_style( 'vgtbt-block-styles', $editor_css );
			}
		}
	},
	30
);
