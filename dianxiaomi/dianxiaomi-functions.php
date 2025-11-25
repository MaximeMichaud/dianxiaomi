<?php
/**
 * Functions used by plugins.
 *
 * @package dianxiaomi
 */

if ( ! class_exists( 'Dianxiaomi_Dependencies' ) ) {
	require_once 'class-dianxiaomi-dependencies.php';
}

/**
 * WC Detection.
 */
if ( ! function_exists( 'is_woocommerce_active' ) ) {
	function is_woocommerce_active(): bool {
		return Dianxiaomi_Dependencies::woocommerce_active_check();
	}
}
