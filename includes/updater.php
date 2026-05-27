<?php
/**
 * GitHub Plugin Updater
 *
 * A reusable class for WordPress plugins hosted on GitHub to enable
 * automatic updates from the WordPress dashboard.
 *
 * @package Viget\BlocksToolkit
 */

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
	 * Plugin display name.
	 *
	 * @var string
	 */
	private $plugin_name;

	/**
	 * Minimum WordPress version required by plugin.
	 *
	 * @var string
	 */
	private $plugin_requires_wp;

	/**
	 * Minimum PHP version required by plugin.
	 *
	 * @var string
	 */
	private $plugin_requires_php;

	/**
	 * Whether updater hooks were already registered.
	 *
	 * @var bool
	 */
	private $hooks_registered = false;

	/**
	 * Constructor
	 *
	 * @param string $plugin_file Path to the main plugin file.
	 * @param string $github_owner GitHub repository owner.
	 * @param string $github_repo GitHub repository name.
	 */
	public function __construct( $plugin_file, $github_owner, $github_repo ) {
		$this->plugin_file     = $plugin_file;
		$this->github_owner    = $github_owner;
		$this->github_repo     = $github_repo;
		$this->plugin_basename = plugin_basename( $plugin_file );
		// Keep updater identity stable regardless of install directory or ZIP root folder name.
		$this->plugin_slug = strtolower( (string) $github_repo );

		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$plugin_data = get_plugin_data( $plugin_file, false, false );

		$this->plugin_name         = ! empty( $plugin_data['Name'] ) ? $plugin_data['Name'] : $this->plugin_slug;
		$this->plugin_version      = ! empty( $plugin_data['Version'] ) ? $plugin_data['Version'] : '0.0.0';
		$this->plugin_requires_wp  = ! empty( $plugin_data['RequiresWP'] ) ? $plugin_data['RequiresWP'] : '';
		$this->plugin_requires_php = ! empty( $plugin_data['RequiresPHP'] ) ? $plugin_data['RequiresPHP'] : '';

		$this->register_hooks();
	}

	/**
	 * Register update hooks one time.
	 *
	 * @return void
	 */
	private function register_hooks() {
		if ( $this->hooks_registered ) {
			return;
		}

		add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'check_for_update' ] );
		add_filter( 'plugins_api', [ $this, 'plugin_info' ], 10, 3 );
		add_action( 'upgrader_process_complete', [ $this, 'purge_cache' ], 10, 2 );

		$this->hooks_registered = true;
	}

	/**
	 * Check for plugin updates.
	 *
	 * @param object $transient Update transient object.
	 *
	 * @return object Modified transient object.
	 */
	public function check_for_update( $transient ) {
		if ( ! is_object( $transient ) || empty( $transient->checked ) ) {
			return $transient;
		}

		$release_info = $this->get_release_info();

		if ( ! $release_info || ! isset( $release_info->tag_name ) ) {
			return $transient;
		}

		// Remove 'v' prefix from tag name for version comparison.
		$latest_version = ltrim( $release_info->tag_name, 'v' );
		$package_url    = $this->get_package_url( $release_info );

		// Check if there's a newer version.
		if ( version_compare( $this->plugin_version, $latest_version, '<' ) ) {
			if ( $package_url ) {
				$transient->response[ $this->plugin_basename ] = (object) [
					'slug'         => $this->plugin_slug,
					'plugin'       => $this->plugin_basename,
					'new_version'  => $latest_version,
					'url'          => $this->get_release_html_url( $release_info ),
					'package'      => $package_url,
					'icons'        => [],
					'banners'      => [],
					'banners_rtl'  => [],
					'requires'     => $this->plugin_requires_wp,
					'tested'       => '',
					'requires_php' => $this->plugin_requires_php,
				];
				unset( $transient->no_update[ $this->plugin_basename ] );
			}
		} elseif ( $package_url ) {
			$transient->no_update[ $this->plugin_basename ] = (object) [
				'slug'         => $this->plugin_slug,
				'plugin'       => $this->plugin_basename,
				'new_version'  => $this->plugin_version,
				'url'          => $this->get_release_html_url( $release_info ),
				'package'      => $package_url,
				'icons'        => [],
				'banners'      => [],
				'banners_rtl'  => [],
				'requires'     => $this->plugin_requires_wp,
				'tested'       => '',
				'requires_php' => $this->plugin_requires_php,
			];
			unset( $transient->response[ $this->plugin_basename ] );
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

		if ( ! $release_info || empty( $release_info->tag_name ) ) {
			return $result;
		}

		$latest_version = ltrim( $release_info->tag_name, 'v' );
		$author_login   = ! empty( $release_info->author->login ) ? $release_info->author->login : $this->github_owner;
		$author_profile = ! empty( $release_info->author->html_url ) ? $release_info->author->html_url : sprintf( 'https://github.com/%s', $this->github_owner );
		$published_at   = ! empty( $release_info->published_at ) ? $release_info->published_at : '';
		$changelog      = ! empty( $release_info->body ) ? (string) $release_info->body : '';

		return (object) [
			'name'              => $this->plugin_name,
			'slug'              => $this->plugin_slug,
			'version'           => $latest_version,
			'author'            => $author_login,
			'author_profile'    => $author_profile,
			'last_updated'      => $published_at,
			'homepage'          => $this->get_release_html_url( $release_info ),
			'short_description' => esc_html__( 'Latest version from GitHub', 'viget-blocks-toolkit' ),
			'sections'          => [
				'changelog' => $this->format_changelog( $changelog ),
			],
			'download_link'     => $this->get_package_url( $release_info ) ?: '',
			'requires'          => $this->plugin_requires_wp,
			'tested'            => '',
			'requires_php'      => $this->plugin_requires_php,
		];
	}

	/**
	 * Clear cache after successful update
	 *
	 * @param \WP_Upgrader $upgrader Upgrader instance.
	 * @param array        $options Update options.
	 */
	public function purge_cache( $upgrader, $options ) {
		if ( 'update' !== $options['action'] || 'plugin' !== $options['type'] ) {
			return;
		}

		if ( empty( $options['plugins'] ) || ! in_array( $this->plugin_basename, $options['plugins'], true ) ) {
			return;
		}

		delete_site_transient( $this->get_transient_key() );
		delete_site_transient( $this->get_error_transient_key() );
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
			$error_transient_key = $this->get_error_transient_key();
			$recent_error        = get_site_transient( $error_transient_key );

			if ( false !== $recent_error ) {
				return false;
			}

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
						'Accept'     => 'application/vnd.github.v3+json',
						'User-Agent' => 'WordPress-Plugin-Updater',
					],
				]
			);

			if ( is_wp_error( $response ) ) {
				set_site_transient( $error_transient_key, true, 15 * MINUTE_IN_SECONDS );
				return false;
			}

			$response_code = wp_remote_retrieve_response_code( $response );
			if ( 200 !== $response_code ) {
				set_site_transient( $error_transient_key, true, 15 * MINUTE_IN_SECONDS );
				return false;
			}

			$release_info = json_decode( wp_remote_retrieve_body( $response ) );

			if ( ! is_object( $release_info ) ) {
				set_site_transient( $error_transient_key, true, 15 * MINUTE_IN_SECONDS );
				return false;
			}

			// Cache for 12 hours.
			set_site_transient( $transient_key, $release_info, 12 * HOUR_IN_SECONDS );
			delete_site_transient( $error_transient_key );
		}

		return $release_info;
	}

	/**
	 * Get download URL for the release package.
	 *
	 * @param object $release_info Release information from GitHub API.
	 *
	 * @return string|false Download URL or false if not found.
	 */
	private function get_package_url( $release_info ): string|false {
		if ( ! isset( $release_info->assets ) || ! is_array( $release_info->assets ) ) {
			return false;
		}

		$zip_assets = [];

		// Collect ZIP-like assets from the release.
		foreach ( $release_info->assets as $asset ) {
			$asset_name         = ! empty( $asset->name ) ? strtolower( (string) $asset->name ) : '';
			$asset_content_type = ! empty( $asset->content_type ) ? strtolower( (string) $asset->content_type ) : '';
			$asset_url          = ! empty( $asset->browser_download_url ) ? (string) $asset->browser_download_url : '';

			if ( '' === $asset_url ) {
				continue;
			}

			$is_zip_content_type = in_array( $asset_content_type, [ 'application/zip', 'application/octet-stream' ], true );
			$is_zip_filename     = '' !== $asset_name && str_ends_with( $asset_name, '.zip' );

			if ( ! $is_zip_content_type && ! $is_zip_filename ) {
				continue;
			}

			$zip_assets[] = $asset;
		}

		if ( empty( $zip_assets ) ) {
			return false;
		}

		$preferred_filename = strtolower( $this->github_repo . '.zip' );

		foreach ( $zip_assets as $asset ) {
			$asset_name = ! empty( $asset->name ) ? strtolower( (string) $asset->name ) : '';
			if ( $preferred_filename === $asset_name && ! empty( $asset->browser_download_url ) ) {
				return (string) $asset->browser_download_url;
			}
		}

		return (string) $zip_assets[0]->browser_download_url;
	}

	/**
	 * Format changelog text.
	 *
	 * @param string $changelog Raw changelog text.
	 * @return string Formatted changelog.
	 */
	private function format_changelog( $changelog ) {
		if ( empty( $changelog ) ) {
			return esc_html__( 'No changelog available.', 'viget-blocks-toolkit' );
		}

		$link_placeholders = [];

		$changelog = preg_replace_callback(
			'/\[([^\]]+)\]\(([^)\s]+)\)/',
			static function( $matches ) use ( &$link_placeholders ) {
				$placeholder = '__VGTBT_LINK_' . count( $link_placeholders ) . '__';

				$link_placeholders[ $placeholder ] = sprintf(
					'<a href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a>',
					esc_url( $matches[2] ),
					esc_html( $matches[1] )
				);

				return $placeholder;
			},
			(string) $changelog
		);

		$changelog = esc_html( $changelog );

		if ( ! empty( $link_placeholders ) ) {
			$changelog = str_replace(
				array_keys( $link_placeholders ),
				array_values( $link_placeholders ),
				$changelog
			);
		}

		// Convert line breaks to HTML
		$changelog = nl2br( $changelog );

		$allowed_tags = [
			'a'  => [
				'href'   => [],
				'rel'    => [],
				'target' => [],
			],
			'br' => [],
		];

		return wp_kses( $changelog, $allowed_tags );
	}

	/**
	 * Get transient key for caching
	 *
	 * @return string Transient key.
	 */
	private function get_transient_key() {
		return 'github_updater_' . md5( $this->github_owner . '/' . $this->github_repo );
	}

	/**
	 * Get transient key used for short-lived API failure backoff.
	 *
	 * @return string Transient key.
	 */
	private function get_error_transient_key() {
		return $this->get_transient_key() . '_error';
	}

	/**
	 * Get release URL with repository fallback.
	 *
	 * @param object $release_info Release information.
	 *
	 * @return string
	 */
	private function get_release_html_url( $release_info ): string {
		if ( ! empty( $release_info->html_url ) ) {
			return (string) $release_info->html_url;
		}

		return sprintf( 'https://github.com/%s/%s', $this->github_owner, $this->github_repo );
	}
}
