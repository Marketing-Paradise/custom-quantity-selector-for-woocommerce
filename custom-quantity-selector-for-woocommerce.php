<?php
/**
 * Plugin Name:       Custom Quantity Selector for WooCommerce
 * Description:       Configure a global custom quantity step and minimum quantity for WooCommerce products.
 * Version:           1.0.0
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Author:            Marketing Paradise, Rafael Martín - Górriz Trillo
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       custom-quantity-selector-for-woocommerce
 * Domain Path:       /languages
 * Requires Plugins:  woocommerce
 * WC requires at least: 8.5
 * WC tested up to:   11.3
 *
 * @package Custom_Quantity_Selector_For_WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'CQSFW_VERSION' ) ) {
	define( 'CQSFW_VERSION', '1.0.0' );
}

if ( ! defined( 'CQSFW_PLUGIN_FILE' ) ) {
	define( 'CQSFW_PLUGIN_FILE', __FILE__ );
}

if ( ! defined( 'CQSFW_PLUGIN_DIR' ) ) {
	define( 'CQSFW_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'CQSFW_PLUGIN_BASENAME' ) ) {
	define( 'CQSFW_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
}

require_once CQSFW_PLUGIN_DIR . 'includes/class-cqsfw-plugin.php';

add_action( 'before_woocommerce_init', array( 'CQSFW_Plugin', 'declare_compatibility' ) );
add_action( 'plugins_loaded', array( 'CQSFW_Plugin', 'instance' ) );