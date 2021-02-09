# Authorize.Net Emulation for WooCommerce

## Plugin requirements
- PHP 7.0+
- WordPress 5.2+
- WooCommerce 3.5+

## Local development
1. Clone this repository
1. Run `cd /path/to/cloned/authorize-net-emulation-for-woocommerce`
1. Run `composer install`
1. Run `npm install`
1. Run `npx sake compile`
1. If you cloned the repository outside your development WordPress installation, symlink it into your plugins folder

### Assets
- Run `npx sake compile` to re-compile all plugin assets
- Run `npx sake compile:scripts` to only re-compile the JavaScript
- Run `npx sake compile:styles` to only re-compile the styles
