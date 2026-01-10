<?php
/**
 * Plugin Name: Dianxiaomi Enhanced for WooCommerce
 * Plugin URI: https://github.com/MaximeMichaud/dianxiaomi
 * Description: Enhances WooCommerce by adding tracking numbers, carrier names and automating tracking number imports to Dianxiaomi.
 * Version: 1.5.2
 * Author: Dianxiaomi & Maxime Michaud
 * Author URI: https://github.com/MaximeMichaud/dianxiaomi
 * Requires at least: 5.8
 * Tested up to: 6.9
 * Requires PHP: 8.1
 * WC requires at least: 8.0
 * WC tested up to: 10.4
 * Requires Plugins: woocommerce
 * Text Domain: dianxiaomi
 * Domain Path: /languages
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * Copyright 2015-2026 Dianxiaomi, Maxime Michaud
 *
 * @package dianxiaomi
 */

declare(strict_types=1);

/**
 * Security Note: Prevent direct access to the file.
 */
defined( 'ABSPATH' ) || die( 'No script kiddies please!' );

// Plugin version constants.
define( 'DIANXIAOMI_VERSION', '1.5.2' );
define( 'DIANXIAOMI_WP_VERSION', '5.8' );
define( 'DIANXIAOMI_WP_VERSION_TESTED', '6.9' );
define( 'DIANXIAOMI_PHP_VERSION', '8.1' );
define( 'DIANXIAOMI_WC_VERSION', '8.0' );
define( 'DIANXIAOMI_WC_VERSION_TESTED', '10.4' );
define( 'DIANXIAOMI_FILE', __FILE__ );
define( 'DIANXIAOMI_PATH', plugin_dir_path( DIANXIAOMI_FILE ) );
define( 'DIANXIAOMI_URL', plugin_dir_url( DIANXIAOMI_FILE ) );

/**
 * Include required functions if they are not already defined.
 */
if ( ! function_exists( 'is_woocommerce_active' ) ) {
	require_once 'dianxiaomi-functions.php';
}

/**
 * Declare compatibility with WooCommerce HPOS before WooCommerce initializes.
 */
add_action( 'before_woocommerce_init', 'before_woocommerce_hpos' );

function before_woocommerce_hpos(): void {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
}

/**
 * Check if WooCommerce is active and initialize the plugin.
 */
if ( is_woocommerce_active() ) {
	/**
	 * Load required interfaces and classes.
	 */
	require_once __DIR__ . '/inc/interfaces/interface-subscriber.php';
	require_once __DIR__ . '/inc/event-management/class-event-manager.php';

	/**
	 * Define the Dianxiaomi class if it hasn't been defined.
	 */
	if ( ! class_exists( 'Dianxiaomi' ) ) {
		require_once 'class-dianxiaomi.php';

		/**
		 * Register this class globally.
		 */
		if ( ! function_exists( 'get_dianxiaomi_instance' ) ) {
			function get_dianxiaomi_instance(): Dianxiaomi {
				return Dianxiaomi::Instance();
			}
		}
	}

	$GLOBALS['dianxiaomi'] = get_dianxiaomi_instance();
}
