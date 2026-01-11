<?php
/**
 * Mock WooCommerce OrderUtil class for HPOS testing.
 *
 * @package Dianxiaomi\Tests
 */

namespace Automattic\WooCommerce\Utilities;

/**
 * Mock OrderUtil class.
 */
class OrderUtil {
	/**
	 * Whether HPOS is enabled.
	 *
	 * @var bool
	 */
	private static bool $hpos_enabled = false;

	/**
	 * Check if custom orders table usage is enabled.
	 *
	 * @return bool
	 */
	public static function custom_orders_table_usage_is_enabled(): bool {
		return self::$hpos_enabled;
	}

	/**
	 * Test helper to set HPOS status.
	 *
	 * @param bool $enabled Whether HPOS is enabled.
	 */
	public static function set_hpos_enabled( bool $enabled ): void {
		self::$hpos_enabled = $enabled;
	}
}
