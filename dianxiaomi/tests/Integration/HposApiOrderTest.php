<?php
/**
 * Order API integration tests for CPT and HPOS without compatibility sync.
 *
 * @package Dianxiaomi\Tests\Integration
 */

declare(strict_types=1);

namespace Dianxiaomi\Tests\Integration;

use Automattic\WooCommerce\Utilities\OrderUtil;
use PHPUnit\Framework\Attributes\DataProvider;

class HposApiOrderTest extends TestCase {

	private \Dianxiaomi_API_Orders $api;
	private array $orders = array();
	private array $products = array();
	private array $users = array();
	private array $options = array();
	private array $request_globals;
	private int $original_user;
	private \Closure $allow_storage_switch;
	private \Closure $block_http;

	protected function set_up(): void {
		parent::set_up();

		$this->original_user   = get_current_user_id();
		$this->request_globals = array( $_GET, $_POST, $_REQUEST );
		foreach ( array( 'woocommerce_custom_orders_table_enabled', 'woocommerce_custom_orders_table_data_sync_enabled', 'dianxiaomi_ignore_order_status', 'timezone_string' ) as $option ) {
			$this->options[ $option ] = get_option( $option );
		}

		// Other integration tests may leave orders in either store. Only our fixtures are changed.
		$this->allow_storage_switch = static fn() => true;
		$this->block_http           = static fn() => new \WP_Error( 'test_http_blocked', 'External requests are disabled during order API tests.' );
		add_filter( 'wc_allow_changing_orders_storage_while_sync_is_pending', $this->allow_storage_switch );
		add_filter( 'pre_http_request', $this->block_http );
		update_option( 'woocommerce_custom_orders_table_data_sync_enabled', 'no' );
		update_option( 'dianxiaomi_ignore_order_status', 'no' );

		$plugin_dir = dirname( __DIR__, 2 );
		require_once $plugin_dir . '/api/interface-dianxiaomi-api-handler.php';
		require_once $plugin_dir . '/api/class-dianxiaomi-api-json-handler.php';
		require_once $plugin_dir . '/api/class-dianxiaomi-api-server.php';
		require_once $plugin_dir . '/api/class-dianxiaomi-api-resource.php';
		require_once $plugin_dir . '/api/class-dianxiaomi-api-orders.php';

		wp_set_current_user( $this->create_user( 'shop_manager' ) );
		$_GET     = array();
		$_POST    = array();
		$_REQUEST = array();
		$this->api = new \Dianxiaomi_API_Orders( new \Dianxiaomi_API_Server( '/orders' ) );
	}

	protected function tear_down(): void {
		try {
			foreach ( array_reverse( $this->orders ) as $order ) {
				$order->delete( true );
			}
			foreach ( $this->products as $product ) {
				$product->delete( true );
			}
			foreach ( $this->users as $user_id ) {
				wp_delete_user( $user_id );
			}
			foreach ( $this->options as $option => $value ) {
				if ( false === $value ) {
					delete_option( $option );
				} else {
					update_option( $option, $value );
				}
			}
		} finally {
			if ( isset( $this->api ) ) {
				remove_filter( 'dianxiaomi_api_endpoints', array( $this->api, 'register_routes' ) );
				foreach ( array( 'order', 'coupon', 'customer', 'product', 'report' ) as $resource ) {
					remove_filter( "dianxiaomi_api_{$resource}_response", array( $this->api, 'maybe_add_meta' ), 15 );
					remove_filter( "dianxiaomi_api_{$resource}_response", array( $this->api, 'filter_response_fields' ), 20 );
				}
			}
			remove_filter( 'wc_allow_changing_orders_storage_while_sync_is_pending', $this->allow_storage_switch );
			remove_filter( 'pre_http_request', $this->block_http );
			list( $_GET, $_POST, $_REQUEST ) = $this->request_globals;
			wp_set_current_user( $this->original_user );
			parent::tear_down();
		}
	}

	public static function storage_modes(): array {
		return array(
			'CPT'               => array( false ),
			'HPOS without sync' => array( true ),
		);
	}

	private function create_user( string $role ): int {
		$user_id = wp_insert_user(
			array(
				'user_login' => 'hpos-api-' . wp_generate_uuid4(),
				'user_pass'  => wp_generate_password( 32 ),
				'role'       => $role,
			)
		);
		$this->assertIsInt( $user_id );
		$this->users[] = $user_id;
		return $user_id;
	}

	private function create_stored_order( bool $hpos ): \WC_Order {
		update_option( 'woocommerce_custom_orders_table_enabled', $hpos ? 'yes' : 'no' );
		$this->assertSame( $hpos, OrderUtil::custom_orders_table_usage_is_enabled() );
		$this->assertSame( 'no', get_option( 'woocommerce_custom_orders_table_data_sync_enabled' ) );

		$order          = $this->create_order();
		$this->orders[] = $order;
		$this->assertSame( $hpos ? 'shop_order_placehold' : 'shop_order', get_post_type( $order->get_id() ) );
		$this->assertInstanceOf( \WC_Order::class, wc_get_order( $order->get_id() ) );
		return $order;
	}

	private function assert_api_order( mixed $response, int $id, string $status, string $number, string $provider ): void {
		$this->assertIsArray( $response, is_wp_error( $response ) ? $response->get_error_code() . ': ' . $response->get_error_message() : '' );
		$this->assertSame( $id, $response['order']['id'] );
		$this->assertSame( $status, $response['order']['status'] );
		$this->assertSame( $number, $response['order']['trackings'][0]['tracking_number'] );
		$this->assertSame( $provider, $response['order']['trackings'][0]['tracking_provider'] );
	}

	private function assert_persisted_order( int $id, string $status, string $number, string $provider ): void {
		// A new instance and forced metadata read verify durable state, not the response object.
		$order = new \WC_Order( $id );
		$order->read_meta_data( true );
		$this->assertSame( $status, $order->get_status() );
		$this->assertSame( $number, $order->get_meta( '_dianxiaomi_tracking_number' ) );
		$this->assertSame( $provider, $order->get_meta( '_dianxiaomi_tracking_provider' ) );
		$this->assert_api_order( $this->api->get_order( $id ), $id, $status, $number, $provider );
	}

	#[DataProvider( 'storage_modes' )]
	public function test_get_order_reads_the_authoritative_store( bool $hpos ): void {
		$order  = $this->create_stored_order( $hpos );
		$number = 'READ-' . wp_generate_uuid4();
		$this->set_tracking_info( $order, 'ups', $number );

		$this->assert_api_order( $this->api->get_order( $order->get_id() ), $order->get_id(), 'processing', $number, 'ups' );
	}

	#[DataProvider( 'storage_modes' )]
	public function test_authorized_order_notes_include_internal_and_customer_notes( bool $hpos ): void {
		update_option( 'timezone_string', 'America/Toronto' );
		$order       = $this->create_stored_order( $hpos );
		$internal_id = $order->add_order_note( 'Internal fulfillment instructions.' );
		$customer_id = $order->add_order_note( 'Your order has shipped.', 1 );
		$other       = $this->create_stored_order( $hpos );
		$foreign_id  = $other->add_order_note( 'Another order.' );
		$filter      = has_filter( 'comments_clauses', array( 'WC_Comments', 'exclude_order_comments' ) );

		$result = $this->api->get_order_notes( $order->get_id() );
		$this->assertIsArray( $result );
		$notes = array_column( $result['order_notes'], null, 'id' );
		$this->assertArrayHasKey( $internal_id, $notes );
		$this->assertArrayHasKey( $customer_id, $notes );
		$this->assertArrayNotHasKey( $foreign_id, $notes );
		$this->assertSame( 'Internal fulfillment instructions.', $notes[ $internal_id ]['note'] );
		$this->assertFalse( $notes[ $internal_id ]['customer_note'] );
		$this->assertSame( 'Your order has shipped.', $notes[ $customer_id ]['note'] );
		$this->assertTrue( $notes[ $customer_id ]['customer_note'] );
		$this->assertSame( str_replace( ' ', 'T', get_comment( $internal_id )->comment_date_gmt ) . 'Z', $notes[ $internal_id ]['created_at'] );
		$this->assertSame( $filter, has_filter( 'comments_clauses', array( 'WC_Comments', 'exclude_order_comments' ) ) );
	}

	#[DataProvider( 'storage_modes' )]
	public function test_edit_order_persists_status_and_tracking( bool $hpos ): void {
		$order  = $this->create_stored_order( $hpos );
		$number = 'EDIT-' . wp_generate_uuid4();
		$result = $this->api->edit_order(
			$order->get_id(),
			array( 'status' => 'on-hold', 'tracking_provider' => 'ups', 'tracking_number' => $number )
		);

		$this->assert_api_order( $result, $order->get_id(), 'on-hold', $number, 'ups' );
		$this->assert_persisted_order( $order->get_id(), 'on-hold', $number, 'ups' );
	}

	#[DataProvider( 'storage_modes' )]
	public function test_status_only_edit_keeps_existing_tracking( bool $hpos ): void {
		$order  = $this->create_stored_order( $hpos );
		$number = 'KEEP-' . wp_generate_uuid4();
		$this->set_tracking_info( $order, 'ups', $number );

		$result = $this->api->edit_order( $order->get_id(), array( 'status' => 'completed' ) );

		$this->assert_api_order( $result, $order->get_id(), 'completed', $number, 'ups' );
		$this->assert_persisted_order( $order->get_id(), 'completed', $number, 'ups' );

		$cleared = $this->api->edit_order(
			$order->get_id(),
			array(
				'status'            => 'on-hold',
				'tracking_provider' => '',
				'tracking_number'   => '',
			)
		);
		$this->assert_api_order( $cleared, $order->get_id(), 'on-hold', '', '' );
		$this->assert_persisted_order( $order->get_id(), 'on-hold', '', '' );
	}

	#[DataProvider( 'storage_modes' )]
	public function test_ship_order_persists_status_and_tracking( bool $hpos ): void {
		$order  = $this->create_stored_order( $hpos );
		$number = 'SHIP-' . wp_generate_uuid4();
		$result = $this->api->ship_order(
			$order->get_id(),
			array( 'status' => 'completed', 'tracking_provider' => 'fedex', 'tracking_number' => $number )
		);

		$this->assert_api_order( $result, $order->get_id(), 'completed', $number, 'fedex' );
		$this->assert_persisted_order( $order->get_id(), 'completed', $number, 'fedex' );
	}

	#[DataProvider( 'storage_modes' )]
	public function test_invalid_ids_and_non_orders_are_rejected( bool $hpos ): void {
		$order            = $this->create_stored_order( $hpos );
		$product          = $this->create_product();
		$this->products[] = $product;
		$refund           = new \WC_Order_Refund();
		$refund->set_parent_id( $order->get_id() );
		$refund->save();
		$this->orders[] = $refund;

		foreach ( array( 0, 'not-an-id', PHP_INT_MAX, $product->get_id(), $refund->get_id() ) as $id ) {
			foreach ( array( 'get_order', 'edit_order', 'ship_order' ) as $method ) {
				$result = 'get_order' === $method ? $this->api->get_order( $id ) : $this->api->$method( $id, array( 'status' => 'completed', 'tracking_number' => 'REJECTED' ) );
				$this->assertInstanceOf( \WP_Error::class, $result );
				$this->assertSame( 404, $result->get_error_data()['status'] );
			}
		}
		$this->assert_persisted_order( $order->get_id(), 'processing', '', '' );
		$this->assertSame( '', get_post_meta( $product->get_id(), '_dianxiaomi_tracking_number', true ) );
	}

	#[DataProvider( 'storage_modes' )]
	public function test_insufficient_permissions_do_not_allow_reading_or_mutating_an_order( bool $hpos ): void {
		$order   = $this->create_stored_order( $hpos );
		$manager = get_current_user_id();
		wp_set_current_user( $this->create_user( 'subscriber' ) );

		foreach ( array( 'get_order' => 'read', 'edit_order' => 'edit', 'ship_order' => 'edit' ) as $method => $context ) {
			$result = 'get_order' === $method ? $this->api->get_order( $order->get_id() ) : $this->api->$method( $order->get_id(), array( 'status' => 'completed', 'tracking_number' => 'REJECTED' ) );
			$this->assertInstanceOf( \WP_Error::class, $result );
			$this->assertSame( "dianxiaomi_api_user_cannot_{$context}_order", $result->get_error_code() );
			$this->assertSame( 401, $result->get_error_data()['status'] );
		}

		wp_set_current_user( $manager );
		$this->assert_persisted_order( $order->get_id(), 'processing', '', '' );
	}

	#[DataProvider( 'storage_modes' )]
	public function test_read_permission_does_not_grant_edit_permission( bool $hpos ): void {
		$order = $this->create_stored_order( $hpos );
		$user  = new \WP_User( $this->create_user( 'subscriber' ) );
		$user->add_cap( 'read_private_shop_orders' );
		wp_set_current_user( $user->ID );

		$this->assert_api_order( $this->api->get_order( $order->get_id() ), $order->get_id(), 'processing', '', '' );
		foreach ( array( 'edit_order', 'ship_order' ) as $method ) {
			$result = $this->api->$method( $order->get_id(), array( 'status' => 'completed', 'tracking_number' => 'REJECTED' ) );
			$this->assertInstanceOf( \WP_Error::class, $result );
			$this->assertSame( 'dianxiaomi_api_user_cannot_edit_order', $result->get_error_code() );
			$this->assertSame( 401, $result->get_error_data()['status'] );
		}
		$this->assert_persisted_order( $order->get_id(), 'processing', '', '' );
	}
}
