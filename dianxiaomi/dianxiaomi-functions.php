<?php
/**
 * Functions used by plugins.
 *
 * @package dianxiaomi
 */

declare(strict_types=1);

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

if ( ! function_exists( 'dianxiaomi_courier_name' ) ) {
	/**
	 * Carrier name shown to the customer.
	 *
	 * The admin meta box stores the catalog name. The ship API only sends the slug,
	 * so an unknown or missing catalog entry falls back to that slug.
	 *
	 * @param string $slug Courier slug.
	 *
	 * @return string Display name.
	 */
	function dianxiaomi_courier_name( string $slug ): string {
		if ( '' === $slug ) {
			return '';
		}

		/** @var array<string, string>|null $names */
		static $names = null;
		if ( ! is_array( $names ) ) {
			/** @var array<string, string> $loaded */
			$loaded = array();
			$path = __DIR__ . '/assets/js/couriers.js';
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local plugin catalog, not a remote URL.
			$js = is_readable( $path ) ? file_get_contents( $path ) : false;
			if ( is_string( $js ) && preg_match_all( '/"slug"\s*:\s*"((?:\\\\.|[^"\\\\])*)"\s*,\s*"name"\s*:\s*"((?:\\\\.|[^"\\\\])*)"/', $js, $matches ) > 0 ) {
				foreach ( $matches[1] as $index => $courier_slug ) {
					$loaded[ stripcslashes( $courier_slug ) ] = stripcslashes( $matches[2][ $index ] );
				}
			}
			$names = $loaded;
		}

		return $names[ $slug ] ?? $slug;
	}
}
