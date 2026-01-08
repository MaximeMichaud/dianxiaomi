<?php
/**
 * WooCommerce Helper Trait.
 *
 * Provides helper methods for WooCommerce integration.
 *
 * @package Dianxiaomi\Traits
 */

declare(strict_types=1);

namespace Dianxiaomi\Traits;

use WC_Order;
use WC_Product;

/**
 * Trait for WooCommerce-related helpers.
 *
 * @since 1.41
 */
trait WooCommerce_Helper {
	/**
	 * Check if WooCommerce is active.
	 *
	 * @return bool True if WooCommerce is active.
	 */
	protected function is_woocommerce_active(): bool {
		return class_exists( 'WooCommerce' );
	}

	/**
	 * Get a WooCommerce order by ID.
	 *
	 * @param int $order_id Order ID.
	 *
	 * @return WC_Order|null Order object or null if not found.
	 */
	protected function get_wc_order( int $order_id ): ?WC_Order {
		if ( ! $this->is_woocommerce_active() ) {
			return null;
		}

		$order = \wc_get_order( $order_id );
		return $order instanceof WC_Order ? $order : null;
	}

	/**
	 * Get a WooCommerce product by ID.
	 *
	 * @param int $product_id Product ID.
	 *
	 * @return WC_Product|null Product object or null if not found.
	 */
	protected function get_product( int $product_id ): ?WC_Product {
		if ( ! $this->is_woocommerce_active() ) {
			return null;
		}

		$product = \wc_get_product( $product_id );
		return $product instanceof WC_Product ? $product : null;
	}

	/**
	 * Get the WooCommerce version.
	 *
	 * @return string WooCommerce version or empty string if not active.
	 */
	protected function get_woocommerce_version(): string {
		if ( ! $this->is_woocommerce_active() || ! defined( 'WC_VERSION' ) ) {
			return '';
		}
		return WC_VERSION;
	}

	/**
	 * Check if HPOS (High-Performance Order Storage) is enabled.
	 *
	 * @return bool True if HPOS is enabled.
	 */
	protected function is_hpos_enabled(): bool {
		if ( ! $this->is_woocommerce_active() ) {
			return false;
		}

		if ( class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) ) {
			return \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
		}

		return false;
	}

	/**
	 * Get the order edit URL (works with both legacy and HPOS).
	 *
	 * @param int $order_id Order ID.
	 *
	 * @return string Order edit URL.
	 */
	protected function get_order_edit_url( int $order_id ): string {
		if ( $this->is_hpos_enabled() ) {
			return \admin_url( 'admin.php?page=wc-orders&action=edit&id=' . $order_id );
		}
		return \admin_url( 'post.php?post=' . $order_id . '&action=edit' );
	}

	/**
	 * Get order meta (works with both legacy and HPOS).
	 *
	 * @param WC_Order|int $order    Order object or ID.
	 * @param string       $meta_key Meta key.
	 * @param bool         $single   Return single value.
	 *
	 * @return mixed Meta value.
	 */
	protected function get_order_meta( WC_Order|int $order, string $meta_key, bool $single = true ): mixed {
		if ( is_int( $order ) ) {
			$order = $this->get_wc_order( $order );
		}

		if ( ! $order instanceof WC_Order ) {
			return $single ? '' : array();
		}

		return $order->get_meta( $meta_key, $single );
	}

	/**
	 * Update order meta (works with both legacy and HPOS).
	 *
	 * @param WC_Order|int $order      Order object or ID.
	 * @param string       $meta_key   Meta key.
	 * @param mixed        $meta_value Meta value.
	 *
	 * @return bool True on success.
	 */
	protected function update_order_meta( WC_Order|int $order, string $meta_key, mixed $meta_value ): bool {
		if ( is_int( $order ) ) {
			$order = $this->get_wc_order( $order );
		}

		if ( ! $order instanceof WC_Order ) {
			return false;
		}

		$order->update_meta_data( $meta_key, $meta_value );
		$order->save();
		return true;
	}

	/**
	 * Get order screen IDs (legacy + HPOS).
	 *
	 * @return array<int, string> Order screen IDs.
	 */
	protected function get_order_screen_ids(): array {
		return array( 'shop_order', 'woocommerce_page_wc-orders' );
	}

	/**
	 * Check if current screen is an order screen.
	 *
	 * @return bool True if on order edit screen.
	 */
	protected function is_order_edit_screen(): bool {
		$screen = \get_current_screen();
		if ( null === $screen ) {
			return false;
		}
		return in_array( $screen->id, $this->get_order_screen_ids(), true );
	}
}
