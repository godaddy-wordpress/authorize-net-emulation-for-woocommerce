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
 * needs please refer to http://www.woocommerce.com/products/ for more information. TODO: docs url
 *
 * @author      SkyVerge
 * @copyright   Copyright (c) 2021, SkyVerge, Inc.
 * @license     http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

namespace SkyVerge\WooCommerce\Authorize_Net\Emulation;

use SkyVerge\WooCommerce\PluginFramework\v5_10_3 as Framework;

defined( 'ABSPATH' ) or exit;

/**
 * Authorize.Net Emulation main plugin class.
 *
 * @since 1.0.0
 */
class Plugin extends Framework\SV_WC_Payment_Gateway_Plugin {


    /** plugin version number */
    const VERSION = '1.0.0';

    /** plugin id */
    const PLUGIN_ID = 'authorize_net_emulation';

    /** plugin meta prefix */
    const PLUGIN_PREFIX = 'authorize_net_emulation_';

    /** @var Plugin single instance of this plugin */
    protected static $instance;


    /**
     * Constructs the class.
     *
     * @since 1.0.0
     */
    public function __construct() {

        parent::__construct(
            self::PLUGIN_ID,
            self::VERSION,
            [
                'text_domain' => 'authorize-net-emulation-for-woocommerce',
                'require_ssl' => true,
                'supports'    => [ self::FEATURE_CAPTURE_CHARGE ],
            ]
        );

        $this->setup_hooks();
    }


    /**
     * Sets up the action & filter hooks.
     *
     * @since 1.0.0
     */
    private function setup_hooks() {

        if ( ! strncmp( get_option( 'woocommerce_default_country' ), 'US:', 3 ) ) {

            // remove blank arrays from the state fields, otherwise it's hidden
            add_action( 'woocommerce_states', array( $this, 'remove_empty_state_arrays' ), 1 );

            //  require the billing fields
            add_filter( 'woocommerce_get_country_locale', array( $this, 'require_billing_fields' ), 100 );
        }
    }


    /**
     * Removes blank State array values from countries.
     *
     * Before requiring all billing fields, the state array has to be removed of blank arrays, otherwise
     * the field is hidden.
     *
     * @internal
     *
     * @see WC_Countries::__construct()
     *
     * @since 1.0.0-dev.1
     *
     * @param array $countries the available countries
     * @return array the available countries
     */
    public function remove_empty_state_arrays( $countries ) {

        foreach ( $countries as $country_code => $states ) {

            if ( is_array( $countries[ $country_code ] ) && empty( $countries[ $country_code ] ) ) {
                $countries[ $country_code ] = null;
            }
        }

        return $countries;
    }


    /**
     * Sets all state billing fields as required.
     *
     * This is hooked in when using a European payment processor.
     *
     * @internal
     *
     * @since 1.0.0
     *
     * @param array $locales countries and locale-specific address field info
     * @return array
     */
    public function require_billing_fields( $locales ) {

        foreach ( $locales as $country_code => $fields ) {

            if ( isset( $locales[ $country_code ]['state']['required'] ) ) {
                $locales[ $country_code ]['state']['required'] = true;
                $locales[ $country_code ]['state']['label']    = $this->get_state_label( $country_code );
            }
        }

        return $locales;
    }


    /**
     * Gets a label for states that don't have one set by WooCommerce.
     *
     * @param string $country_code the 2-letter country code for the billing country
     * @return string the label for the "billing state" field at checkout
     * @since 1.0.0
     *
     */
    protected function get_state_label( string $country_code ): string {

        switch ( $country_code ) {

            case 'AF':
            case 'AT':
            case 'BI':
            case 'KR':
            case 'PL':
            case 'PT':
            case 'LK':
            case 'SE':
            case 'VN':
                $label = __( 'Province', 'authorize-net-emulation-for-woocommerce' );
                break;

            case 'AX':
            case 'YT':
                $label = __( 'Island', 'authorize-net-emulation-for-woocommerce' );
                break;

            case 'DE':
                $label = __( 'State', 'authorize-net-emulation-for-woocommerce' );
                break;

            case 'EE':
            case 'NO':
                $label = __( 'County', 'authorize-net-emulation-for-woocommerce' );
                break;

            case 'FI':
            case 'IL':
            case 'LB':
                $label = __( 'District', 'authorize-net-emulation-for-woocommerce' );
                break;

            default:
                $label = __( 'Region', 'authorize-net-emulation-for-woocommerce' );
        }

        return $label;
    }


    /**
     * Determine if TLS v1.2 is required for API requests.
     *
     * @see SV_WC_Plugin::require_tls_1_2()
     *
     * @since 1.0.0
     *
     * @return bool
     */
    public function require_tls_1_2(): bool {

        return true;
    }


    /**
     * Gets the plugin documentation URL.
     *
     * @see SV_WC_Plugin::get_documentation_url()
     *
     * @since 1.0.0
     *
     * @return string
     */
    public function get_documentation_url(): string {

        // TODO: Replace with the documentation URL once it's available. {IT 2021-02-10}
        return '';
    }


    /**
     * Gets the plugin support URL.
     *
     * @see SV_WC_Plugin::get_support_url()
     *
     * @since 1.0.0
     *
     * @return string
     */
    public function get_support_url(): string {

        // TODO: Replace with the support URL once it's available. {IT 2021-02-10}
        return '';
    }


    /**
     * Gets the plugin sales page URL.
     *
     * @see SV_WC_Plugin::get_sales_page_url()
     *
     * @since 1.0.0
     *
     * @return string
     */
    public function get_sales_page_url(): string {

        // TODO: Replace with the sales page URL once it's available. {IT 2021-02-10}
        return '';
    }


    /**
     * Returns the plugin name, localized.
     *
     * @see SV_WC_Plugin::get_plugin_name()
     *
     * @since 1.0.0
     *
     * @return string the plugin name
     */
    public function get_plugin_name(): string {

        return __( 'Authorize.Net Emulation for WooCommerce', 'authorize-net-emulation-for-woocommerce' );
    }


    /**
     * Returns __DIR__.
     *
     * @since 1.0.0
     *
     * @see SV_WC_Plugin::get_file()
     * @return string the full path and filename of the plugin file
     */
    protected function get_file(): string {

        return __DIR__;
    }


    /**
     * Main Authorize.Net Emulation Instance, ensures only one instance is/can be loaded
     *
     * @see wc_authorize_net_emulation()
     *
     * @since 1.0.0
     *
     * @return Plugin
     */
    public static function instance(): Plugin {

        if ( null === self::$instance ) {
            self::$instance = new self();
        }

        return self::$instance;
    }
}
