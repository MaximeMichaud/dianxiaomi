<?php
/**
 * Plugin Name: Dianxiaomi Enhanced for WooCommerce
 * Plugin URI: http://dianxiaomi.com/
 * Description: Enhances WooCommerce by adding tracking numbers, carrier names and automating tracking number imports to Dianxiaomi.
 * Version: 1.40
 * Author: Dianxiaomi & Maxime Michaud
 * Author URI: https://github.com/MaximeMichaud/dianxiaomi
 * Requires at least: 5.8
 * Requires PHP: 8.0
 * WC requires at least: 4.0
 * WC tested up to: 9.3
 * Requires Plugins: woocommerce
 * Copyright: © Dianxiaomi, Maxime Michaud
 *
 * @package dianxiaomi
 */

declare(strict_types=1);

/**
 * Security Note: Prevent direct access to the file.
 */
defined( 'ABSPATH' ) || die( 'No script kiddies please!' );

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
