<?php
/**
 * Integration tests for order editing with both WooCommerce storage engines.
 *
 * @package Dianxiaomi\Tests\Integration
 */

declare(strict_types=1);

namespace Dianxiaomi\Tests\Integration;

use Automattic\WooCommerce\Utilities\OrderUtil;

/**
 * Exercise the real meta box callback and order metadata persistence.
 */
class HposMetaBoxTest extends TestCase {
	private array $original_options = array();
	private array $original_globals = array();
	private array $original_post;
	private array $orders = array();
	private array $original_hooks = array();

	protected function set_up(): void {
		parent::set_up();
		$this->original_hooks = array_map( static fn( $hook ) => clone $hook, $GLOBALS['wp_filter'] );
		require_once ABSPATH . 'wp-admin/includes/template.php';
		require_once ABSPATH . 'wp-admin/includes/screen.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-screen.php';
		require_once WC_ABSPATH . 'includes/admin/wc-admin-functions.php';
		require_once WC_ABSPATH . 'includes/admin/class-wc-admin-menus.php';

		foreach ( array( 'woocommerce_custom_orders_table_enabled', 'woocommerce_custom_orders_table_data_sync_enabled', 'woocommerce_auto_flip_authoritative_table_roles' ) as $name ) {
			$this->original_options[ $name ] = get_option( $name );
		}
		foreach ( array( 'post', 'wp_meta_boxes', 'current_screen' ) as $name ) {
			$this->original_globals[ $name ] = array( array_key_exists( $name, $GLOBALS ), $GLOBALS[ $name ] ?? null );
		}
		if ( false === has_action( 'woocommerce_process_shop_order_meta', array( $this->get_plugin(), 'save_meta_box' ) ) ) {
			add_action( 'woocommerce_process_shop_order_meta', array( $this->get_plugin(), 'save_meta_box' ), 0, 2 );
		}
		$this->original_post = $_POST;
		$_POST = array();
		// Switch test storage without synchronizing fixtures owned by other test classes.
		add_filter( 'wc_allow_changing_orders_storage_while_sync_is_pending', '__return_true' );
		update_option( 'woocommerce_custom_orders_table_data_sync_enabled', 'no' );
		update_option( 'woocommerce_auto_flip_authoritative_table_roles', 'no' );
	}

	protected function tear_down(): void {
		$_POST = array();
		foreach ( $this->orders as $order ) {
			$order->delete( true );
		}
		foreach ( $this->original_options as $name => $value ) {
			if ( false === $value ) {
				delete_option( $name );
			} else {
				update_option( $name, $value );
			}
		}
		foreach ( $this->original_globals as $name => [ $existed, $value ] ) {
			if ( $existed ) {
				$GLOBALS[ $name ] = $value;
			} else {
				unset( $GLOBALS[ $name ] );
			}
		}
		// Loading WooCommerce admin classes also registers hooks; restore those too.
		$GLOBALS['wp_filter'] = $this->original_hooks;
		$_POST = $this->original_post;
		parent::tear_down();
	}

	public static function storage_modes(): array {
		return array( 'HPOS without synchronization' => array( true ), 'classic posts' => array( false ) );
	}

	private function use_storage( bool $hpos ): void {
		update_option( 'woocommerce_custom_orders_table_enabled', $hpos ? 'yes' : 'no' );
		$this->assertSame( $hpos, OrderUtil::custom_orders_table_usage_is_enabled() );
		$this->assertSame( 'no', get_option( 'woocommerce_custom_orders_table_data_sync_enabled' ) );
	}

	private function create_fixture(): \WC_Order {
		$order = $this->create_order();
		$this->orders[] = $order;
		return $order;
	}

	/** @dataProvider storage_modes */
	public function test_box_is_registered_on_the_active_order_screen( bool $hpos ): void {
		$this->use_storage( $hpos );
		$GLOBALS['wp_meta_boxes'] = array();
		$this->get_plugin()->add_meta_box();
		$screen = $hpos ? wc_get_page_screen_id( 'shop-order' ) : 'shop_order';
		$this->assertArrayHasKey( 'woocommerce-dianxiaomi', $GLOBALS['wp_meta_boxes'][ $screen ]['side']['high'] ?? array() );
	}

	/** @dataProvider storage_modes */
	public function test_box_renders_the_supplied_order_without_a_global_post( bool $hpos ): void {
		$this->use_storage( $hpos );
		$order = $this->create_fixture();
		$this->set_tracking_info( $order, 'ups', 'RENDER-' . $order->get_id(), 'UPS' );
		unset( $GLOBALS['post'] );

		ob_start();
		try {
			$this->get_plugin()->meta_box( $hpos ? $order : get_post( $order->get_id() ) );
			$html = ob_get_contents();
		} finally {
			ob_end_clean();
		}
		$this->assertStringContainsString( 'dianxiaomi_nonce', $html );
		$this->assertStringContainsString( 'value="RENDER-' . $order->get_id() . '"', $html );
		$this->assertStringContainsString( 'id="dianxiaomi_tracking_provider_hidden" value="ups"', $html );
	}

	/** @dataProvider storage_modes */
	public function test_order_editor_save_persists_tracking_in_the_authoritative_database( bool $hpos ): void {
		global $wpdb;
		$this->use_storage( $hpos );
		$order = $this->create_fixture();
		$number = 'SAVE-' . $order->get_id();
		$_POST = array(
			'dianxiaomi_nonce'                  => wp_create_nonce( 'dianxiaomi_save_meta_box' ),
			'dianxiaomi_tracking_provider'      => 'ups',
			'dianxiaomi_tracking_provider_name' => 'UPS',
			'dianxiaomi_tracking_number'        => $number,
		);
		do_action( 'woocommerce_process_shop_order_meta', $order->get_id(), $hpos ? $order : get_post( $order->get_id() ) );

		$table = $hpos ? $wpdb->prefix . 'wc_orders_meta' : $wpdb->postmeta;
		$id_column = $hpos ? 'order_id' : 'post_id';
		$stored = $wpdb->get_results( $wpdb->prepare( "SELECT meta_key, meta_value FROM $table WHERE $id_column = %d AND meta_key IN ('_dianxiaomi_tracking_number', '_dianxiaomi_tracking_provider', '_dianxiaomi_tracking_provider_name')", $order->get_id() ), OBJECT_K );
		$this->assertSame( $number, $stored['_dianxiaomi_tracking_number']->meta_value ?? null );
		$this->assertSame( 'ups', $stored['_dianxiaomi_tracking_provider']->meta_value ?? null );
		$this->assertSame( 'UPS', $stored['_dianxiaomi_tracking_provider_name']->meta_value ?? null );

		$fresh = new \WC_Order( $order->get_id() );
		$this->assertSame( $number, $fresh->get_meta( '_dianxiaomi_tracking_number' ) );
		if ( $hpos ) {
			$this->assertSame( '0', $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $wpdb->postmeta WHERE post_id = %d AND meta_key = '_dianxiaomi_tracking_number'", $order->get_id() ) ) );
		}
	}
}
