<?php
/**
 * Uninstall routine for Viget Blocks Toolkit
 *
 * Blocks and their settings live in post content and the theme, so they're left
 * alone. Only the plugin's caches are removed: the GitHub updater's release
 * cache, and each site's block icon and block ID transients.
 *
 * @package Viget\BlocksToolkit
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$vgtbt_transient_key = 'vgtbt_github_updater_' . md5( 'vigetlabs/viget-blocks-toolkit' );

delete_site_transient( $vgtbt_transient_key );
delete_site_transient( $vgtbt_transient_key . '_error' );

$vgtbt_site_ids = is_multisite() ? get_sites( [ 'fields' => 'ids' ] ) : [ get_current_blog_id() ];

foreach ( $vgtbt_site_ids as $vgtbt_site_id ) {
	switch_to_blog( $vgtbt_site_id );
	delete_transient( 'vgtbt_icons_checksum' );
	delete_transient( 'vgtbt_block_ids' );
	restore_current_blog();
}
