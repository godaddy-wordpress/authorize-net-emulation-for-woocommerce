<?php
/**
 * Plugin Name: Authorize.Net Emulation for WooCommerce
 * Plugin URI: http://www.woocommerce.com/products/ TODO: product URL
 * Documentation URI: http://www.woocommerce.com/products/ TODO: documentation URL
 * Description: TODO: plugin description
 * Author: SkyVerge
 * Author URI: http://www.woocommerce.com
 * Version: 1.0.0-dev.1
 * Text Domain: authorize-net-emulation-for-woocommerce
 * Domain Path: /i18n/languages/
 *
 * Copyright: (c) 2021, SkyVerge, Inc. (info@skyverge.com)
 *
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 *
 * @author    SkyVerge
 * @copyright Copyright (c) 2021, SkyVerge, Inc.
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 *
 * Woo: 99999:00000000000000000000000000000000 TODO: updater keys
 * WC requires at least: 3.5
 * WC tested up to: 5.0.0
 */

defined( 'ABSPATH' ) or exit;


/**
 * The plugin loader class.
 *
 * @since 1.0.0-dev.1
 */
class WC_Authorize_Net_Emulation_Loader {


    /** minimum PHP version required by this plugin */
    const MINIMUM_PHP_VERSION = '7.0';

    /** minimum WordPress version required by this plugin */
    const MINIMUM_WP_VERSION = '5.2';

    /** minimum WooCommerce version required by this plugin */
    const MINIMUM_WC_VERSION = '4.0';

    /** SkyVerge plugin framework version used by this plugin */
    const FRAMEWORK_VERSION = '5.10.3';

    /** the plugin name, for displaying notices */
    const PLUGIN_NAME = 'Authorize.Net Emulation for WooCommerce';


    /** @var WC_Authorize_Net_Emulation_Loader single instance of this class */
    private static $instance;

    /** @var array the admin notices to add */
    private $notices = [];


    /**
     * Constructs the class.
     *
     * @since 1.0.0-dev.1
     */
    protected function __construct() {

        register_activation_hook( __FILE__, [ $this, 'doActivationCheck' ] );

        add_action( 'admin_init', [ $this, 'checkEnvironment' ] );
        add_action( 'admin_init', [ $this, 'addPluginNotices' ] );

        add_action( 'admin_notices', [ $this, 'outputAdminNotices' ], 15 );

        add_filter( 'extra_plugin_headers', [ $this, 'addDocumentationHeader' ] );

        // if the environment check fails, initialize the plugin
        if ( $this->isEnvironmentCompatible() ) {
            add_action( 'plugins_loaded', [ $this, 'initPlugin' ] );
        }
    }


    /**
     * Cloning instances is forbidden due to singleton pattern.
     *
     * @since 1.0.0-dev.1
     */
    public function __clone() {

        _doing_it_wrong( __FUNCTION__, sprintf( 'You cannot clone instances of %s.', get_class( $this ) ), '1.0.0-dev.1' );
    }


    /**
     * Unserializing instances is forbidden due to singleton pattern.
     *
     * @since 1.0.0-dev.1
     */
    public function __wakeup() {

        _doing_it_wrong( __FUNCTION__, sprintf( 'You cannot unserialize instances of %s.', get_class( $this ) ), '1.0.0-dev.1' );
    }


    /**
     * Initializes the plugin.
     *
     * @since 1.0.0-dev.1
     */
    public function initPlugin() {

        if ( ! $this->pluginsCompatible() ) {
            return;
        }

        $this->loadFramework();

        require_once plugin_dir_path( __FILE__ ) . 'vendor/autoload.php';
        require_once plugin_dir_path( __FILE__ ) . 'src/Functions.php';

        // fire it up!
        wc_authorize_net_emulation();
    }


    /**
     * Loads the base framework classes.
     *
     * @since 1.0.0-dev.1
     */
    private function loadFramework() {

        if ( ! class_exists( '\\SkyVerge\\WooCommerce\\PluginFramework\\' . $this->getFrameworkVersionNamespace() . '\\SV_WC_Plugin' ) ) {
            require_once( plugin_dir_path( __FILE__ ) . 'vendor/skyverge/wc-plugin-framework/woocommerce/class-sv-wc-plugin.php' );
        }

        if ( ! class_exists( '\\SkyVerge\\WooCommerce\\PluginFramework\\' . $this->getFrameworkVersionNamespace() . '\\SV_WC_Payment_Gateway_Plugin' ) ) {
            require_once( plugin_dir_path( __FILE__ ) . 'vendor/skyverge/wc-plugin-framework/woocommerce/payment-gateway/class-sv-wc-payment-gateway-plugin.php' );
        }
    }


    /**
     * Gets the framework version in namespace form.
     *
     * @since 1.0.0-dev.1
     *
     * @return string
     */
    public function getFrameworkVersionNamespace(): string {

        return 'v' . str_replace( '.', '_', $this->getFrameworkVersion() );
    }


    /**
     * Gets the framework version used by this plugin.
     *
     * @since 1.0.0-dev.1
     *
     * @return string
     */
    public function getFrameworkVersion(): string {

        return self::FRAMEWORK_VERSION;
    }


    /**
     * Checks the server environment and other factors and deactivates plugins as necessary.
     *
     * Based on http://wptavern.com/how-to-prevent-wordpress-plugins-from-activating-on-sites-with-incompatible-hosting-environments
     *
     * @internal
     *
     * @since 1.0.0-dev.1
     */
    public function doActivationCheck() {

        if ( ! $this->isEnvironmentCompatible() ) {
            $this->deactivatePlugin();

            wp_die( self::PLUGIN_NAME . ' could not be activated. ' . $this->getEnvironmentMessage() );
        }
    }


    /**
     * Checks the environment on loading WordPress, just in case the environment changes after activation.
     *
     * @internal
     *
     * @since 1.0.0-dev.1
     */
    public function checkEnvironment() {

        if ( ! $this->isEnvironmentCompatible() && is_plugin_active( plugin_basename( __FILE__ ) ) ) {
            $this->deactivatePlugin();

            $this->addAdminNotice( 'bad_environment', 'error', self::PLUGIN_NAME . ' has been deactivated. ' . $this->getEnvironmentMessage() );
        }
    }


    /**
     * Adds notices for out-of-date WordPress and/or WooCommerce versions.
     *
     * @internal
     *
     * @since 1.0.0-dev.1
     */
    public function addPluginNotices() {

        if ( ! $this->isWordPressCompatible() ) {
            $this->addAdminNotice( 'update_wordpress', 'error', sprintf(
                '%s requires WordPress version %s or higher. Please %supdate WordPress &raquo;%s',
                '<strong>' . self::PLUGIN_NAME . '</strong>',
                self::MINIMUM_WP_VERSION,
                '<a href="' . esc_url( admin_url( 'update-core.php' ) ) . '">',
                '</a>'
            ) );
        }

        if ( ! $this->isWooCommerceCompatible() ) {
            $this->addAdminNotice( 'update_woocommerce', 'error', sprintf(
                '%1$s requires WooCommerce version %2$s or higher. Please %3$supdate WooCommerce%4$s to the latest version, or %5$sdownload the minimum required version &raquo;%6$s',
                '<strong>' . self::PLUGIN_NAME . '</strong>',
                self::MINIMUM_WC_VERSION,
                '<a href="' . esc_url( admin_url( 'update-core.php' ) ) . '">',
                '</a>',
                '<a href="' . esc_url( 'https://downloads.wordpress.org/plugin/woocommerce.' . self::MINIMUM_WC_VERSION . '.zip' ) . '">',
                '</a>'
            ) );
        }
    }


    /**
     * Determines if the required plugins are compatible.
     *
     * @since 1.0.0-dev.1
     *
     * @return bool
     */
    private function pluginsCompatible(): bool {

        return $this->isWordPressCompatible() && $this->isWooCommerceCompatible();
    }


    /**
     * Determines if the WordPress compatible.
     *
     * @since 1.0.0-dev.1
     *
     * @return bool
     */
    private function isWordPressCompatible(): bool {

        if ( ! self::MINIMUM_WP_VERSION ) {
            return true;
        }

        return version_compare( get_bloginfo( 'version' ), self::MINIMUM_WP_VERSION, '>=' );
    }


    /**
     * Determines if the WooCommerce compatible.
     *
     * @since 1.0.0-dev.1
     *
     * @return bool
     */
    private function isWooCommerceCompatible(): bool {

        if ( ! self::MINIMUM_WC_VERSION ) {
            return true;
        }

        return defined( 'WC_VERSION' ) && version_compare( WC_VERSION, self::MINIMUM_WC_VERSION, '>=' );
    }


    /**
     * Deactivates the plugin.
     *
     * @internal
     *
     * @since 1.0.0-dev.1
     */
    protected function deactivatePlugin() {

        deactivate_plugins( plugin_basename( __FILE__ ) );

        if ( isset( $_GET['activate'] ) ) {
            unset( $_GET['activate'] );
        }
    }


    /**
     * Adds an admin notice to be displayed.
     *
     * @param string $slug the slug for the notice
     * @param string $class the css class for the notice
     * @param string $message the notice message
     *
     * @since 1.0.0-dev.1
     */
    private function addAdminNotice( string $slug, string $class, string $message ) {

        $this->notices[ $slug ] = [
            'class'   => $class,
            'message' => $message,
        ];
    }


    /**
     * Displays any admin notices added with \SV_WC_Framework_Plugin_Loader::add_admin_notice()
     *
     * @internal
     *
     * @since 1.0.0-dev.1
     */
    public function outputAdminNotices() {

        foreach ( (array) $this->notices as $notice_key => $notice ) {
            ?>
            <div class="<?php echo esc_attr( $notice['class'] ); ?>">
                <p><?php echo wp_kses( $notice['message'], [ 'a' => [ 'href' => [] ] ] ); ?></p>
            </div>
            <?php
        }
    }


    /**
     * Adds the Documentation URI header.
     *
     * @param string[] $headers original headers
     *
     * @return string[]
     * @internal
     *
     * @since 1.0.0-dev.1
     *
     */
    public function addDocumentationHeader( array $headers ): array {

        $headers[] = 'Documentation URI';

        return $headers;
    }


    /**
     * Determines if the server environment is compatible with this plugin.
     *
     * Override this method to add checks for more than just the PHP version.
     *
     * @since 1.0.0-dev.1
     *
     * @return bool
     */
    private function isEnvironmentCompatible(): bool {

        return version_compare( PHP_VERSION, self::MINIMUM_PHP_VERSION, '>=' );
    }


    /**
     * Gets the message for display when the environment is incompatible with this plugin.
     *
     * @since 1.0.0-dev.1
     *
     * @return string
     */
    private function getEnvironmentMessage(): string {

        return sprintf( 'The minimum PHP version required for this plugin is %1$s. You are running %2$s.', self::MINIMUM_PHP_VERSION, PHP_VERSION );
    }


    /**
     * Gets the main \SV_WC_Framework_Plugin_Loader instance.
     *
     * Ensures only one instance can be loaded.
     *
     * @return WC_Authorize_Net_Emulation_Loader
     * @since 1.0.0-dev.1
     *
     */
    public static function getInstance(): WC_Authorize_Net_Emulation_Loader {

        if ( null === self::$instance ) {
            self::$instance = new self();
        }

        return self::$instance;
    }


}

// fire it up!
WC_Authorize_Net_Emulation_Loader::getInstance();
