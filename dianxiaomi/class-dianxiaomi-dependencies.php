<?php
/**
 * Dianxiaomi Dependencies.
 *
 * @package Dianxiaomi
 */

declare(strict_types=1);

final class Dianxiaomi_Dependencies {
	/** @var array<int|string, string> Active plugins list. */
	private static array $active_plugins = array();

	/**
	 * Initialize the class by fetching active plugins.
	 */
	public static function init(): void {
		$plugins = get_option( 'active_plugins', array() );
		/** @var array<int|string, string> $active_plugins */
		$active_plugins       = is_array( $plugins ) ? $plugins : array();
		self::$active_plugins = $active_plugins;

		if ( is_multisite() ) {
			$sitewide_plugins = get_site_option( 'active_sitewide_plugins', array() );
			/** @var array<int|string, string> $sitewide_array */
			$sitewide_array       = is_array( $sitewide_plugins ) ? $sitewide_plugins : array();
			self::$active_plugins = array_merge( self::$active_plugins, $sitewide_array );
		}
	}

	/**
	 * Check if a plugin is active.
	 *
	 * @param string|array<int, string> $plugin Path to the plugin file relative to the plugins directory or array of such paths.
	 *
	 * @return bool True if the plugin is active, false otherwise.
	 */
	public static function plugin_active_check( string|array $plugin ): bool {
		if ( ! self::$active_plugins ) {
			self::init();
		}

		if ( is_array( $plugin ) ) {
			foreach ( $plugin as $path ) {
				if ( in_array( $path, self::$active_plugins, true ) || array_key_exists( $path, self::$active_plugins ) ) {
					return true;
				}
			}
			return false;
		} else {
			return in_array( $plugin, self::$active_plugins, true ) || array_key_exists( $plugin, self::$active_plugins );
		}
	}
	/**
	 * Check if WooCommerce is active.
	 *
	 * @return bool True if WooCommerce is active, false otherwise.
	 */
	public static function woocommerce_active_check(): bool {
		return self::plugin_active_check( 'woocommerce/woocommerce.php' );
	}
}
