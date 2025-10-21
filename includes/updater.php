<?php
/**
 * GitHub Plugin Updater
 *
 * A reusable class for WordPress plugins hosted on GitHub to enable
 * automatic updates from the WordPress dashboard.
 *
 * @package Viget\BlocksToolkit
 */

use stdClass;
use WP_Upgrader;

/**
 * GitHub Plugin Updater Class
 */
class GitHub_Plugin_Updater {

	/**
	 * Plugin file path
	 *
	 * @var string
	 */
	private $plugin_file;

	/**
	 * GitHub repository owner
	 *
	 * @var string
	 */
	private $github_owner;

	/**
	 * GitHub repository name
	 *
	 * @var string
	 */
	private $github_repo;

	/**
	 * Plugin slug
	 *
	 * @var string
	 */
	private $plugin_slug;

	/**
	 * Current plugin version
	 *
	 * @var string
	 */
	private $plugin_version;

	/**
	 * Plugin basename
	 *
	 * @var string
	 */
	private $plugin_basename;

	/**
	 * Constructor
	 *
	 * @param string $plugin_file Path to the main plugin file.
	 * @param string $github_owner GitHub repository owner.
	 * @param string $github_repo GitHub repository name.
	 */
	public function __construct( $plugin_file, $github_owner, $github_repo ) {
		// Only run in the admin area.
		if ( ! is_admin() ) {
			return;
		}

		$this->plugin_file     = $plugin_file;
		$this->github_owner    = $github_owner;
		$this->github_repo     = $github_repo;
		$this->plugin_basename = plugin_basename( $plugin_file );

		// Get plugin data.
		$plugin_data          = get_plugin_data( $plugin_file );
		$this->plugin_slug    = dirname( $this->plugin_basename );
		$this->plugin_version = $plugin_data['Version'];

		// Hook into WordPress.
		add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'check_for_update' ] );
		add_filter( 'plugins_api', [ $this, 'plugin_info' ], 10, 3 );
		add_action( 'upgrader_process_complete', [ $this, 'purge_cache' ], 10, 2 );
	}

	/**
	 * Check for plugin updates.
	 *
	 * @param stdClass $transient Update transient object.
	 *
	 * @return stdClass Modified transient object.
	 */
	public function check_for_update( $transient ) {
		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		$release_info = $this->get_release_info();

		if ( ! $release_info || ! isset( $release_info->tag_name ) ) {
			return $transient;
		}

		// Remove 'v' prefix from tag name for version comparison.
		$latest_version = ltrim( $release_info->tag_name, 'v' );

		// Check if there's a newer version.
		if ( version_compare( $this->plugin_version, $latest_version, '<' ) ) {
			$package_url = $this->get_package_url( $release_info );

			if ( $package_url ) {
				$transient->response[ $this->plugin_basename ] = (object) [
					'slug'         => $this->plugin_slug,
					'plugin'       => $this->plugin_basename,
					'new_version'  => $latest_version,
					'url'          => $release_info->html_url,
					'package'      => $package_url,
					'icons'        => [],
					'banners'      => [],
					'banners_rtl'  => [],
					'tested'       => '',
					'requires_php' => '',
				];
			}
		}

		return $transient;
	}

	/**
	 * Provide plugin information for the update details modal
	 *
	 * @param false|object|array $result The result object or array.
	 * @param string             $action The type of information being requested.
	 * @param object             $args Plugin API arguments.
	 *
	 * @return false|object Plugin information or false.
	 */
	public function plugin_info( $result, $action, $args ) {
		if ( 'plugin_information' !== $action ) {
			return $result;
		}

		if ( ! isset( $args->slug ) || $args->slug !== $this->plugin_slug ) {
			return $result;
		}

		$release_info = $this->get_release_info();

		if ( ! $release_info ) {
			return $result;
		}

		$latest_version = ltrim( $release_info->tag_name, 'v' );

		return (object) [
			'name'              => $this->plugin_slug,
			'slug'              => $this->plugin_slug,
			'version'           => $latest_version,
			'author'            => $release_info->author->login,
			'author_profile'    => $release_info->author->html_url,
			'last_updated'      => $release_info->published_at,
			'homepage'          => $release_info->html_url,
			'short_description' => 'Latest version from GitHub',
			'sections'          => [
				'changelog' => $this->format_changelog( $release_info->body ),
			],
			'download_link'     => $this->get_package_url( $release_info ),
			'requires'          => '',
			'tested'            => '',
			'requires_php'      => '',
		];
	}

	/**
	 * Clear cache after successful update
	 *
	 * @param WP_Upgrader $upgrader Upgrader instance.
	 * @param array       $options Update options.
	 */
	public function purge_cache( $upgrader, $options ) {
		if ( 'update' !== $options['action'] || 'plugin' !== $options['type'] ) {
			return;
		}

		if ( empty( $options['plugins'] ) || ! in_array( $this->plugin_basename, $options['plugins'], true ) ) {
			return;
		}

		delete_site_transient( $this->get_transient_key() );
	}

	/**
	 * Get release information from GitHub API.
	 *
	 * @return object|false Release information or false on failure.
	 */
	private function get_release_info() {
		$transient_key = $this->get_transient_key();
		$release_info  = get_site_transient( $transient_key );

		if ( false === $release_info ) {
			$api_url = sprintf(
				'https://api.github.com/repos/%s/%s/releases/latest',
				$this->github_owner,
				$this->github_repo
			);

			$response = wp_remote_get(
				$api_url,
				[
					'timeout' => 10,
					'headers' => [
						'Accept' => 'application/vnd.github.v3+json',
						'User-Agent' => 'WordPress-Plugin-Updater',
					],
				]
			);

			if ( is_wp_error( $response ) ) {
				return false;
			}

			$response_code = wp_remote_retrieve_response_code( $response );
			if ( 200 !== $response_code ) {
				return false;
			}

			$release_info = json_decode( wp_remote_retrieve_body( $response ) );

			if ( ! $release_info ) {
				return false;
			}

			// Cache for 12 hours.
			set_site_transient( $transient_key, $release_info, 12 * HOUR_IN_SECONDS );
		}

		return $release_info;
	}

	/**
	 * Get download URL for the release package.
	 *
	 * @param stdClass $release_info Release information from GitHub API.
	 *
	 * @return string|false Download URL or false if not found.
	 */
	private function get_package_url( $release_info ): string|false {
		if ( ! isset( $release_info->assets ) || ! is_array( $release_info->assets ) ) {
			return false;
		}

		// Look for a ZIP file asset.
		foreach ( $release_info->assets as $asset ) {
			if ( ! empty( $asset->content_type ) && in_array( $asset->content_type, [ 'application/zip', 'application/octet-stream' ], true ) ) {
				return $asset->browser_download_url;
			}
		}

		return false;
	}

	/**
	 * Format changelog text.
	 *
	 * @param string $changelog Raw changelog text.
	 * @return string Formatted changelog.
	 */
	private function format_changelog( $changelog ) {
		if ( empty( $changelog ) ) {
			return 'No changelog available.';
		}

		// Convert markdown links to HTML
		$changelog = preg_replace( '/\[([^\]]+)\]\(([^)]+)\)/', '<a href="$2">$1</a>', $changelog );

		// Convert line breaks to HTML
		$changelog = nl2br( esc_html( $changelog ) );

		return $changelog;
	}

	/**
	 * Get transient key for caching
	 *
	 * @return string Transient key.
	 */
	private function get_transient_key() {
		return 'github_updater_' . md5( $this->github_owner . '/' . $this->github_repo );
	}
}
