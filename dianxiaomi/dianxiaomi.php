<?php
/**
 * Plugin Name: Dianxiaomi - WooCommerce ERP Compatibility php 8.2
 * Plugin URI: http://dianxiaomi.com/
 * Description: Add tracking number and carrier name to WooCommerce, display tracking info at order history page, auto import tracking numbers to Dianxiaomi.
 * Version: 1.0.20
 * Author: Dianxiaomi (Alex Modified)
 * Updated: 2024-06-04
 * Author URI: https://github.com/whywilson/dianxiaomi-for-woocommerce/releases
 * Copyright: © Dianxiaomi
 *
 * @package dianxiaomi
 */

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
			function get_dianxiaomi_instance() {
				return Dianxiaomi::Instance();
			}
		}
	}

	$GLOBALS['dianxiaomi'] = get_dianxiaomi_instance();
}
