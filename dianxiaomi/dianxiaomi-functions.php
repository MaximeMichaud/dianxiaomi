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
	/**
	 * Check if WooCommerce is active.
	 *
	 * @return bool True if WooCommerce is active.
	 */
	function is_woocommerce_active(): bool {
		return Dianxiaomi_Dependencies::woocommerce_active_check();
	}
}

/**
 * Get Dianxiaomi currency (wrapper for WooCommerce function).
 */
if ( ! function_exists( 'get_dianxiaomi_currency' ) ) {
	/**
	 * Get the store currency code.
	 *
	 * @return string Currency code.
	 */
	function get_dianxiaomi_currency(): string {
		return function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'USD';
	}
}

/**
 * Get Dianxiaomi currency symbol (wrapper for WooCommerce function).
 */
if ( ! function_exists( 'get_dianxiaomi_currency_symbol' ) ) {
	/**
	 * Get the store currency symbol.
	 *
	 * @return string Currency symbol.
	 */
	function get_dianxiaomi_currency_symbol(): string {
		return function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : '$';
	}
}

/**
 * Get Dianxiaomi API URL.
 */
if ( ! function_exists( 'get_dianxiaomi_api_url' ) ) {
	/**
	 * Get the Dianxiaomi API URL for a given route.
	 *
	 * @param string $route API route.
	 *
	 * @return string Full API URL.
	 */
	function get_dianxiaomi_api_url( string $route = '' ): string {
		return home_url( '/dianxiaomi-api/v1' . $route, 'https' );
	}
}

/**
 * Date formatting helper (wrapper for wp_date).
 */
if ( ! function_exists( 'dianxiaomi_wpdate' ) ) {
	/**
	 * Format a date using WordPress locale settings.
	 *
	 * @param string   $format    PHP date format.
	 * @param int|null $timestamp Unix timestamp. Defaults to current time.
	 *
	 * @return string Formatted date string.
	 */
	function dianxiaomi_wpdate( string $format, ?int $timestamp = null ): string {
		$result = wp_date( $format, $timestamp );
		return false !== $result ? $result : '';
	}
}
