<?php
/**
 * Uninstall routine for Viget Blocks Toolkit
 *
 * Blocks and their settings live in post content and the theme, so they're left
 * alone. Only the plugin's caches are removed: the GitHub updater's release
 * cache, and each site's generated icons.json and icon checksum and block ID
 * transients.
 *
 * @package Viget\BlocksToolkit
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// The release cache was unprefixed before 1.2.0.
foreach ( [ 'vgtbt_github_updater_', 'github_updater_' ] as $vgtbt_prefix ) {
	$vgtbt_transient_key = $vgtbt_prefix . md5( 'vigetlabs/viget-blocks-toolkit' );

	delete_site_transient( $vgtbt_transient_key );
	delete_site_transient( $vgtbt_transient_key . '_error' );
}

$vgtbt_clean_site = function () {
	delete_transient( 'vgtbt_icons_checksum' );
	delete_transient( 'vgtbt_block_ids' );

	$icons_dir = wp_get_upload_dir()['basedir'] . '/viget-blocks-toolkit';

	wp_delete_file( $icons_dir . '/icons.json' );

	if ( is_dir( $icons_dir ) && ! ( new FilesystemIterator( $icons_dir ) )->valid() ) {
		rmdir( $icons_dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Removes the plugin's own empty uploads folder.
	}
};

// switch_to_blog() only exists on multisite.
if ( is_multisite() ) {
	$vgtbt_site_ids = get_sites(
		[
			'fields' => 'ids',
			'number' => 0,
		]
	);

	foreach ( $vgtbt_site_ids as $vgtbt_site_id ) {
		switch_to_blog( $vgtbt_site_id );
		$vgtbt_clean_site();
		restore_current_blog();
	}
} else {
	$vgtbt_clean_site();
}
