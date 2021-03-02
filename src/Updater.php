<?php
/**
 * Authorize.Net Emulation for WooCommerce
 *
 * This source file is subject to the GNU General Public License v3.0
 * that is bundled with this package in the file license.txt.
 * It is also available through the world-wide-web at this URL:
 * http://www.gnu.org/licenses/gpl-3.0.html
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@skyverge.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Authorize.Net Emulation for WooCommerce to newer
 * versions in the future. If you wish to customize Authorize.Net Emulation for WooCommerce for your
 * needs please refer to https://docs.woocommerce.com/document/authorize-net/#emulation-mode for more information.
 *
 * @author      SkyVerge
 * @copyright   Copyright (c) 2021, SkyVerge, Inc.
 * @license     http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

namespace SkyVerge\WooCommerce\Authorize_Net\Emulation;

use stdClass;

defined( 'ABSPATH' ) or exit;


/**
 * Adds the plugin updater API.
 *
 * @since 1.0.0-dev.1
 */
class Updater {


	/** @var string $repe the Github repo from which updates are fetched */
	private $repo = 'skyverge/authorize-net-emulation-for-woocommerce';

	/** @var string $api_url the URL from which updates are retrieved */
	private $api_url;

	/** @var string $api_url the URL from which release readme file is retrieved */
	private $readme_url;

	/** @var string $download_url the URL from which the update file can be downloaded */
	private $download_url;

	/** @var string $plugin_file the plugin file */
	private $plugin_file;

	/** @var string $name the plugin name */
	private $name;

	/** @var string $slug the plugin slug */
	private $slug;

	/** @var mixed $version the current plugin version */
	private $version;

	/** @var string $cache_key key to cache requests */
	private $cache_key = 'authorize_net_emulation_for_woocommerce_version_info';

	/** @var array $api_url_available checks if the URL is available */
	private $api_url_available;


	/**
	 * Class constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {

		$this->api_url      = 'https://api.github.com/repos/' . $this->repo . '/releases/latest';
		$this->readme_url   = 'https://raw.githubusercontent.com/' . $this->repo . '/__VERSION__/readme.txt';
		$this->download_url = 'https://github.com/' . $this->repo . '/releases/download/initial/authorize-net-emulation-for-woocommerce.zip';

		$this->plugin_file = $this->get_plugin()->get_plugin_file();
		$this->name        = plugin_basename( $this->plugin_file );
		$this->slug        = basename( $this->plugin_file, '.php' );
		$this->version     = $this->get_plugin()->get_version();

		// set up hooks
		$this->init();
	}


	/**
	 * Set up WordPress filters to hook into WP's update process.
	 *
	 * @since 1.0.0
	 */
	public function init() {

		add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'check_update' ] );

		add_filter( 'plugins_api', [ $this, 'plugins_api_filter' ], 10, 3 );

		add_action( "after_plugin_row_{$this->name}", [ $this, 'show_update_notification' ], 10, 2 );
		add_action( 'admin_init', [ $this, 'show_changelog' ] );
	}


	/**
	 * Check for Updates at the defined API endpoint and modify the update array.
	 *
	 * This function dives into the update API just when WordPress creates its update array,
	 * then adds a custom API call and injects the custom plugin data retrieved from the API.
	 * It is reassembled from parts of the native WordPress plugin update code.
	 * See wp-includes/update.php line 121 for the original wp_update_plugins() function.
	 *
	 * @since 1.0.0
	 *
	 * @param array|object $_transient_data Update array build by WordPress.
	 * @return array Modified update array with custom plugin data.
	 */
	public function check_update( $_transient_data ) {

		global $pagenow;

		if ( ! is_object( $_transient_data ) ) {
			$_transient_data = new stdClass;
		}

		if ( 'plugins.php' === $pagenow && is_multisite() ) {
			return $_transient_data;
		}

		if ( ! empty( $_transient_data->response ) && ! empty( $_transient_data->response[ $this->name ] ) ) {
			return $_transient_data;
		}

		$version_info = $this->get_latest_plugin_info();

		if ( false !== $version_info && is_object( $version_info ) && isset( $version_info->new_version ) ) {

			if ( version_compare( $this->version, $version_info->new_version, '<' ) ) {
				$_transient_data->response[ $this->name ] = $version_info;
			}

			$_transient_data->last_checked           = current_time( 'timestamp' );
			$_transient_data->checked[ $this->name ] = $this->version;
		}

		return $_transient_data;
	}


	/**
	 * Gets cached plugin version information.
	 *
	 * @since 1.0.0
	 *
	 * @return mixed|false
	 */
	public function get_cached_version_info() {

		$cache = get_option( $this->cache_key );

		if ( empty( $cache['timeout'] ) || current_time( 'timestamp' ) > $cache['timeout'] ) {
			return false; // Cache is expired
		}

		return json_decode( $cache['value'] );
	}


	/**
	 * Fetches the latest release/version info and returns it in an array consumable by WP.
	 *
	 * @since 1.0.0
	 *
	 * @return array
	 */
	private function fetch_version_info() {

		if ( ! $this->api_status_check() ) {
			return [];
		}

		// fetch latest version tag and download URL
		$release = $this->fetch_latest_release();

		// use data from the local readme if the release object has incomplete data
		if ( empty( $release->tag_name ) || empty( $release->assets[0]->browser_download_url ) ) {
			return $this->parse_readme( $this->get_local_readme() );
		}

		// fetch readme (description, changelog, etc)
		return array_replace( [
			'new_version' => $release->tag_name,
			'package'     => $release->assets[0]->browser_download_url,
		], $this->parse_readme( $this->fetch_release_readme( $release->tag_name ) ) );
	}


	/**
	 * Gets the latest release info from Github.
	 *
	 * @since 1.0.0
	 *
	 * @return stdClass|null
	 */
	private function fetch_latest_release() {

		$request = wp_remote_get( $this->api_url, [
			'timeout'   => 25,
			'sslverify' => $this->verify_ssl(),
		] );

		if ( is_wp_error( $request ) ) {
			return null;
		}

		return json_decode( wp_remote_retrieve_body( $request ) );
	}


	/**
	 * Gets the readme file contents for the given release tag.
	 *
	 * @since 1.0.0
	 *
	 * @param string $tag
	 * @return string
	 */
	private function fetch_release_readme( string $tag ) {

		$request = wp_remote_get( str_replace( '__VERSION__', $tag, $this->readme_url ), [
			'timeout'   => 25,
			'sslverify' => $this->verify_ssl(),
		] );

		if ( is_wp_error( $request ) ) {
			return '';
		}

		return wp_remote_retrieve_body( $request );
	}


	/**
	 * Gets the local readme file contents.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	private function get_local_readme(): string {

		return file_get_contents( $this->get_plugin()->get_plugin_path() . '/readme.txt' );
	}


	/**
	 * Parses the readme contents into an array, usable by WP updater.
	 *
	 * @since 1.0.0
	 *
	 * @param string $readme
	 * @return array
	 */
	private function parse_readme( string $readme ): array {

		return ( new ReadmeParser( $readme ) )->parse();
	}


	/**
	 * Gets the latest plugin info.
	 *
	 * @since 1.0.0
	 *
	 * @return bool|object
	 */
	private function get_latest_plugin_info() {

		$version_info = $this->get_cached_version_info();

		if ( ! $version_info ) {
			$version_info = (object) array_replace( $this->get_base_plugin_information(), $this->fetch_version_info() );

			$this->set_version_info_cache( $version_info );
		}

		return $version_info;
	}


	/**
	 * Performs a status check on the API url.
	 *
	 * @since 1.0.0
	 *
	 * @return bool true if the url is available
	 */
	protected function api_status_check(): bool {

		if ( is_null( $this->api_url_available ) ) {

			$test_url_parts = parse_url( $this->api_url );

			$scheme = ! empty( $test_url_parts['scheme'] ) ? $test_url_parts['scheme'] : 'http';
			$host   = ! empty( $test_url_parts['host'] ) ? $test_url_parts['host'] : '';
			$port   = ! empty( $test_url_parts['port'] ) ? ':' . $test_url_parts['port'] : '';

			if ( empty( $host ) ) {

				$this->api_url_available = false;

			} else {

				$test_url = "{$scheme}://{$host}{$port}";
				$response = wp_remote_get( $test_url, [
					'timeout'   => 25,
					'sslverify' => $this->verify_ssl(),
				] );

				$this->api_url_available = ! is_wp_error( $response );
			}
		}

		return $this->api_url_available;
	}


	/**
	 * Returns if the SSL of the store should be verified.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	private function verify_ssl(): bool {

		return (bool) apply_filters( 'authorize_net_emulation_for_woocommerce_verify_ssl', true, $this );
	}


	/**
	 * Sets up a cache for plugin version info.
	 *
	 * @since 1.0.0
	 *
	 * @param string $value
	 */
	public function set_version_info_cache( $value = '' ) {

		$data = [
			'timeout' => strtotime( '+3 hours', current_time( 'timestamp' ) ),
			'value'   => json_encode( $value ),
		];

		update_option( $this->cache_key, $data, 'no' );
	}


	/**
	 * Show update notification row.
	 *
	 * Needed for multisite subsites, because WP won't tell you otherwise!
	 *
	 * @since 1.0.0
	 *
	 * @param string $file plugin file
	 * @param object $plugin
	 */
	public function show_update_notification( string $file, $plugin ) {

		if ( is_network_admin() || ! is_multisite() || ! current_user_can( 'update_plugins' ) || $file !== $this->name ) {
			return;
		}

		// remove our filter on the site transient
		remove_filter( 'pre_set_site_transient_update_plugins', [ $this, 'check_update' ], 10 );

		$update_cache = get_site_transient( 'update_plugins' );
		$update_cache = is_object( $update_cache ) ? $update_cache : new stdClass();

		if ( empty( $update_cache->response ) || empty( $update_cache->response[ $this->name ] ) ) {

			$version_info = $this->get_latest_plugin_info();

			if ( version_compare( $this->version, $version_info->new_version, '<' ) ) {
				$update_cache->response[ $this->name ] = $version_info;
			}

			$update_cache->last_checked           = current_time( 'timestamp' );
			$update_cache->checked[ $this->name ] = $this->version;

			set_site_transient( 'update_plugins', $update_cache );

		} else {
			$version_info = $update_cache->response[ $this->name ];
		}

		// restore our filter
		add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'check_update' ] );

		if ( ! empty( $update_cache->response[ $this->name ] ) && version_compare( $this->version, $version_info->new_version, '<' ) ) {

			// build a plugin list row, with update notification
			echo '<tr class="plugin-update-tr" id="' . $this->slug . '-update" data-slug="' . $this->slug . '" data-plugin="' . $this->slug . '/' . $file . '">';
			echo '<td colspan="3" class="plugin-update colspanchange">';
			echo '<div class="update-message notice inline notice-warning notice-alt">';
			echo '<p>';

			$changelog_link = self_admin_url( 'index.php?authorize_net_emulation_for_woocommerce_view_changelog=1&TB_iframe=true&width=772&height=911' );

			printf(
				__( 'There is a new version of %1$s available. %2$sView version %3$s details%4$s or %5$supdate now%6$s.', 'authorize-net-emulation-for-woocommerce' ),
				esc_html( $version_info->name ),
				'<a target="_blank" class="thickbox" href="' . esc_url( $changelog_link ) . '">',
				esc_html( $version_info->new_version ),
				'</a>',
				'<a href="' . esc_url( wp_nonce_url( self_admin_url( 'update.php?action=upgrade-plugin&plugin=' ) . $this->name, 'upgrade-plugin_' . $this->name ) ) . '">',
				'</a>'
			);

			do_action( "in_plugin_update_message-{$file}", $plugin, $version_info );

			echo '</p></div></td></tr>';
		}
	}


	/**
	 * Convert some objects to arrays when injecting data into the update API.
	 *
	 * Some data like sections, banners, and icons are expected to be an associative array, however due to the JSON
	 * decoding, they are objects. This method allows us to pass in the object and return an associative array.
	 *
	 * @param stdClass $data
	 *
	 * @return array
	 * @since 1.0.0
	 *
	 */
	private function convert_object_to_array( $data ): array {

		$new_data = [];

		foreach ( $data as $key => $value ) {
			$new_data[ $key ] = $value;
		}

		return $new_data;
	}


	/**
	 * Updates information on the "View version x.x details" page with custom data.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $_data
	 * @param string $_action
	 * @param object $_args
	 * @return object $_data
	 */
	public function plugins_api_filter( $_data, $_action = '', $_args = null ) {

		if ( $_action !== 'plugin_information' || ! isset( $_args->slug ) || ( $_args->slug !== $this->slug ) ) {
			return $_data;
		}

		$_data = $this->get_latest_plugin_info();

		// convert objects into associative arrays - we're getting an object, but core expects an array
		if ( isset( $_data->sections ) && ! is_array( $_data->sections ) ) {
			$_data->sections = $this->convert_object_to_array( $_data->sections );
		}

		return $_data;
	}


	/**
	 * Shows the plugin changelog.
	 *
	 * @since 1.0.0
	 */
	public function show_changelog() {

		if ( empty( $_REQUEST['authorize_net_emulation_for_woocommerce_view_changelog'] ) ) {
			return;
		}

		if ( ! current_user_can( 'update_plugins' ) ) {
			wp_die(
				__( 'You do not have permission to install plugin updates', 'authorize-net-emulation-for-woocommerce' ),
				__( 'Error', 'authorize-net-emulation-for-woocommerce' ),
				[ 'response' => 403 ]
			);
		}

		$version_info = $this->get_latest_plugin_info();

		if ( ! empty( $version_info ) && isset( $version_info->sections->changelog ) ) {
			echo '<div style="background:#fff;padding:10px;">' . $version_info->sections->changelog . '</div>';
		}

		exit;
	}


	/**
	 * Gets basic information about the plugin. Used to fill in the 'blanks' for the plugin info retrieved from Github.
	 *
	 * @since 1.0.0
	 *
	 * @return array
	 */
	private function get_base_plugin_information(): array {

		return [
			'name'          => $this->get_plugin()->get_plugin_name(),
			'slug'          => $this->slug,
			'plugin'        => $this->plugin_file,
			'url'           => $this->get_plugin()->get_sales_page_url(),
			'author'        => '<a href="https://skyverge.com">SkyVerge</a>',
			'requires'      => '5.2',
			'tested'        => '5.6',
			'requires_php'  => '7.0',
			'homepage'      => $this->get_plugin()->get_sales_page_url(),
			'sections'      => [],
			'download_link' => $this->download_url,
		];
	}


	/**
	 * Gets the plugin instance.
	 *
	 * @since 1.0.0
	 *
	 * @return Plugin
	 */
	private function get_plugin(): Plugin {

		return wc_authorize_net_emulation();
	}


}
