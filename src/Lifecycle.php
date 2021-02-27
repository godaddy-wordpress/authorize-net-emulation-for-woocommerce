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

defined( 'ABSPATH' ) or exit;

use SkyVerge\WooCommerce\Authorize_Net\Emulation\Plugin;
use SkyVerge\WooCommerce\PluginFramework\v5_10_3 as Framework;

/**
 * The plugin lifecycle handler.
 *
 * @since 1.0.0-dev.1
 */
class Lifecycle extends Framework\Plugin\Lifecycle {


	/**
	 * Lifecycle constructor.
	 *
	 * @param Plugin $plugin plugin instance
	 */
	public function __construct( Plugin $plugin ) {

		parent::__construct( $plugin );
	}


	/**
	 * Performs installation tasks.
	 *
	 * @since 1.0.0-dev.1
	 */
	protected function install() {

		// flag the plugin as installed
		update_option( 'wc_authorize_net_emulation_plugin_installed', 'yes' );

		// disable the emulation gateway included in Authorize.Net CIM
		delete_option( 'wc_authorize_net_emulation_enabled' );
	}


}
