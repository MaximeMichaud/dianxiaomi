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
	public function test_meta_filter_returns_order_meta_for_the_stored_order( bool $hpos ): void {
		$order = $this->create_stored_order( $hpos );
		$order->update_meta_data( '_dianxiaomi_tracking_number', 'META1' );
		$order->save();

		$server = ( new \ReflectionProperty( $this->api, 'server' ) )->getValue( $this->api );
		$this->assertInstanceOf( \Dianxiaomi_API_Server::class, $server );
		$server->params['GET'] = array(
			'filter' => array(
				'meta' => 'true',
			),
		);

		$result = $this->api->get_order( $order->get_id() );

		$this->assertIsArray( $result );
		$stored = wc_get_order( $order->get_id() );
		$this->assertInstanceOf( \WC_Order::class, $stored );
		$this->assertArrayHasKey(
			'order_meta',
			$result['order'],
			get_class( $stored ) . ' keys=' . implode( ',', array_keys( $result['order'] ) )
		);
		$this->assertArrayNotHasKey( 'resource_meta', $result['order'] );
		$this->assertSame( array( 'META1' ), $result['order']['order_meta']['_dianxiaomi_tracking_number'] );
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
	public function test_paid_date_uses_the_same_utc_instant_as_other_dates( bool $hpos ): void {
		update_option( 'timezone_string', 'America/Toronto' );
		$order = $this->create_stored_order( $hpos );
		$order->set_date_paid( '2026-01-15T12:34:56Z' );
		$order->save();

		$result = $this->api->get_order( $order->get_id() );

		$this->assertIsArray( $result );
		$this->assertSame( '2026-01-15T12:34:56Z', $result['order']['payment_details']['paid'] );
		$this->assertSame( $result['order']['payment_details']['paid'], gmdate( 'Y-m-d\TH:i:s\Z', $order->get_date_paid()->getTimestamp() ) );
	}

	#[DataProvider( 'storage_modes' )]
	public function test_ship_order_keeps_courier_details_the_customer_needs( bool $hpos ): void {
		$order  = $this->create_stored_order( $hpos );
		$result = $this->api->ship_order(
			$order->get_id(),
			array(
				'tracking_provider'            => 'b2ceurope',
				'tracking_number'              => 'B2C1',
				'tracking_postal_code'         => '75001',
				'tracking_destination_country' => 'FR',
				'tracking_ship_date'           => '2024-06-01',
			)
		);

		$this->assertIsArray( $result );
		$this->assertSame( '75001', $result['order']['trackings'][0]['tracking_postal_code'] );
		$this->assertSame( 'FR', $result['order']['trackings'][0]['tracking_destination_country'] );
		$ship_meta = $result['order']['trackings'][0]['tracking_ship_date'];
		$this->assertMatchesRegularExpression( '/^[0-9]+$/', $ship_meta );

		$box = \Dianxiaomi::instance();
		ob_start();
		$box->meta_box( wc_get_order( $order->get_id() ) );
		$meta_html = (string) ob_get_clean();
		$document  = new \DOMDocument();
		$previous  = libxml_use_internal_errors( true );
		$document->loadHTML( $meta_html );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );
		$ship_input = ( new \DOMXPath( $document ) )->query( '//input[@id="dianxiaomi_tracking_shipdate"]' );
		$this->assertNotFalse( $ship_input );
		$this->assertSame( 1, $ship_input->length );
		$this->assertSame( '2024-06-01', $ship_input->item( 0 )->getAttribute( 'value' ) );

		$plugin                   = \Dianxiaomi::instance();
		$plugin->plugin           = 'dianxiaomi';
		$plugin->use_track_button = false;
		ob_start();
		$plugin->display_tracking_info( $order->get_id() );
		$html = (string) ob_get_clean();
		$this->assertStringContainsString( '(75001, FR)', $html );

		$again = $this->api->ship_order(
			$order->get_id(),
			array( 'tracking_number' => 'B2C2' )
		);
		$this->assertIsArray( $again );
		$this->assertSame( 'B2C2', $again['order']['trackings'][0]['tracking_number'] );
		$this->assertSame( '75001', $again['order']['trackings'][0]['tracking_postal_code'] );
		$this->assertSame( 'FR', $again['order']['trackings'][0]['tracking_destination_country'] );
	}

	#[DataProvider( 'storage_modes' )]
	public function test_ship_order_stores_the_catalog_carrier_name( bool $hpos ): void {
		$order  = $this->create_stored_order( $hpos );
		$number = 'NAME-' . wp_generate_uuid4();
		$this->api->ship_order(
			$order->get_id(),
			array(
				'status'            => 'completed',
				'tracking_provider' => 'ups',
				'tracking_number'   => $number,
			)
		);

		$stored = wc_get_order( $order->get_id() );
		$this->assertInstanceOf( \WC_Order::class, $stored );
		$this->assertSame( 'UPS', $stored->get_meta( '_dianxiaomi_tracking_provider_name' ) );
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

	/**
	 * A JSON number is still the tracking number the carrier issued.
	 */
	#[DataProvider( 'storage_modes' )]
	public function test_numeric_json_tracking_number_is_stored( bool $hpos ): void {
		$order  = $this->create_stored_order( $hpos );
		$server = new class( '/orders/' . $order->get_id() . '/ship' ) extends \Dianxiaomi_API_Server {
			public string $body = '';

			public function get_raw_data(): string {
				return $this->body;
			}
		};
		$api            = new \Dianxiaomi_API_Orders( $server );
		$server->method = 'PUT';
		$server->params = array(
			'GET'  => array(),
			'POST' => array(),
		);
		$server->body   = '{"status":"completed","tracking_provider":"ups","tracking_number":9400111899223}';
		try {
			$shipped = $server->dispatch();
			$this->assert_api_order( $shipped, $order->get_id(), 'completed', '9400111899223', 'ups' );
			$this->assert_persisted_order( $order->get_id(), 'completed', '9400111899223', 'ups' );
		} finally {
			remove_filter( 'dianxiaomi_api_endpoints', array( $api, 'register_routes' ) );
			foreach ( array( 'order', 'coupon', 'customer', 'product', 'report' ) as $resource ) {
				remove_filter( "dianxiaomi_api_{$resource}_response", array( $api, 'maybe_add_meta' ), 15 );
				remove_filter( "dianxiaomi_api_{$resource}_response", array( $api, 'filter_response_fields' ), 20 );
			}
		}
	}

	/**
	 * Editing an order must keep a JSON tracking number too.
	 */
	#[DataProvider( 'storage_modes' )]
	public function test_numeric_json_tracking_number_survives_an_edit( bool $hpos ): void {
		$order  = $this->create_stored_order( $hpos );
		$server = new class( '/orders/' . $order->get_id() ) extends \Dianxiaomi_API_Server {
			public string $body = '';

			public function get_raw_data(): string {
				return $this->body;
			}
		};
		$api            = new \Dianxiaomi_API_Orders( $server );
		$server->method = 'PUT';
		$server->params = array(
			'GET'  => array(),
			'POST' => array(),
		);
		$server->body   = '{"status":"completed","tracking_number":9400111899223}';
		try {
			$edited = $server->dispatch();
			$this->assert_api_order( $edited, $order->get_id(), 'completed', '9400111899223', '' );
			$this->assert_persisted_order( $order->get_id(), 'completed', '9400111899223', '' );
		} finally {
			remove_filter( 'dianxiaomi_api_endpoints', array( $api, 'register_routes' ) );
			foreach ( array( 'order', 'coupon', 'customer', 'product', 'report' ) as $resource ) {
				remove_filter( "dianxiaomi_api_{$resource}_response", array( $api, 'maybe_add_meta' ), 15 );
				remove_filter( "dianxiaomi_api_{$resource}_response", array( $api, 'filter_response_fields' ), 20 );
			}
		}
	}

	/**
	 * Postal codes and ship dates are often JSON numbers, not quoted strings.
	 */
	#[DataProvider( 'storage_modes' )]
	public function test_numeric_json_courier_details_are_stored( bool $hpos ): void {
		$order  = $this->create_stored_order( $hpos );
		$server = new class( '/orders/' . $order->get_id() . '/ship' ) extends \Dianxiaomi_API_Server {
			public string $body = '';

			public function get_raw_data(): string {
				return $this->body;
			}
		};
		$api            = new \Dianxiaomi_API_Orders( $server );
		$server->method = 'PUT';
		$server->params = array(
			'GET'  => array(),
			'POST' => array(),
		);
		$server->body   = '{"status":"completed","tracking_provider":"deutsch-post","tracking_number":"DE-NUM","tracking_postal_code":75001,"tracking_account_number":998877,"tracking_ship_date":1717200000}';
		try {
			$shipped = $server->dispatch();
			$this->assertIsArray( $shipped );
			$this->assertSame( 'completed', $shipped['order']['status'] );
			$this->assertSame( 'DE-NUM', $shipped['order']['trackings'][0]['tracking_number'] );
			$this->assertSame( '75001', $shipped['order']['trackings'][0]['tracking_postal_code'] );
			$this->assertSame( '998877', $shipped['order']['trackings'][0]['tracking_account_number'] );
			$this->assertSame( '1717200000', $shipped['order']['trackings'][0]['tracking_ship_date'] );
		} finally {
			remove_filter( 'dianxiaomi_api_endpoints', array( $api, 'register_routes' ) );
			foreach ( array( 'order', 'coupon', 'customer', 'product', 'report' ) as $resource ) {
				remove_filter( "dianxiaomi_api_{$resource}_response", array( $api, 'maybe_add_meta' ), 15 );
				remove_filter( "dianxiaomi_api_{$resource}_response", array( $api, 'filter_response_fields' ), 20 );
			}
		}
	}

	/**
	 * WooCommerce turns an unknown status into pending. The API must refuse it instead.
	 */
	#[DataProvider( 'storage_modes' )]
	public function test_unknown_status_does_not_reset_the_order_to_pending( bool $hpos ): void {
		$order = $this->create_stored_order( $hpos );

		$edited = $this->api->edit_order(
			$order->get_id(),
			array(
				'status'          => 'not-a-status',
				'tracking_number' => 'SHOULD-NOT-SAVE',
			)
		);
		$after_edit = new \WC_Order( $order->get_id() );
		$this->assertSame( 'processing', $after_edit->get_status() );
		$this->assertInstanceOf( \WP_Error::class, $edited );
		$this->assertSame( 400, $edited->get_error_data()['status'] );
		$this->assert_persisted_order( $order->get_id(), 'processing', '', '' );

		$shipped = $this->api->ship_order(
			$order->get_id(),
			array(
				'status'            => 'wc-not-a-status',
				'tracking_provider' => 'ups',
				'tracking_number'   => 'ALSO-NOT-SAVED',
			)
		);
		$this->assertInstanceOf( \WP_Error::class, $shipped );
		$this->assertSame( 400, $shipped->get_error_data()['status'] );
		$this->assert_persisted_order( $order->get_id(), 'processing', '', '' );

		$completed = $this->api->edit_order( $order->get_id(), array( 'status' => 'wc-completed' ) );
		$this->assert_api_order( $completed, $order->get_id(), 'completed', '', '' );
		$this->assert_persisted_order( $order->get_id(), 'completed', '', '' );
	}

	/**
	 * A broken JSON body must not look like a successful order update.
	 */
	#[DataProvider( 'storage_modes' )]
	public function test_invalid_json_body_does_not_count_as_an_order_update( bool $hpos ): void {
		$order  = $this->create_stored_order( $hpos );
		$server = new class( '/orders/' . $order->get_id() ) extends \Dianxiaomi_API_Server {
			public string $body = '{';

			public function get_raw_data(): string {
				return $this->body;
			}
		};
		$api = new \Dianxiaomi_API_Orders( $server );
		$server->method = 'PUT';
		$server->params = array(
			'GET'  => array(),
			'POST' => array(),
		);
		try {
			$edited = $server->dispatch();
			$this->assertInstanceOf( \WP_Error::class, $edited, is_array( $edited ) ? 'returned order ' . (string) ( $edited['order']['status'] ?? '' ) : '' );
			$this->assertSame( 400, $edited->get_error_data()['status'] );
			$this->assert_persisted_order( $order->get_id(), 'processing', '', '' );

			$server->path = '/orders/' . $order->get_id() . '/ship';
			$server->body = '{"tracking_number":';
			$shipped      = $server->dispatch();
			$this->assertInstanceOf( \WP_Error::class, $shipped );
			$this->assertSame( 400, $shipped->get_error_data()['status'] );
			$this->assert_persisted_order( $order->get_id(), 'processing', '', '' );

			$server->path = '/orders/' . $order->get_id();
			$server->body = '';
			$empty        = $server->dispatch();
			$this->assert_api_order( $empty, $order->get_id(), 'processing', '', '' );

			$server->body = '{"status":"completed"}';
			$completed    = $server->dispatch();
			$this->assert_api_order( $completed, $order->get_id(), 'completed', '', '' );
			$this->assert_persisted_order( $order->get_id(), 'completed', '', '' );
		} finally {
			remove_filter( 'dianxiaomi_api_endpoints', array( $api, 'register_routes' ) );
			foreach ( array( 'order', 'coupon', 'customer', 'product', 'report' ) as $resource ) {
				remove_filter( "dianxiaomi_api_{$resource}_response", array( $api, 'maybe_add_meta' ), 15 );
				remove_filter( "dianxiaomi_api_{$resource}_response", array( $api, 'filter_response_fields' ), 20 );
			}
		}
	}

	/**
	 * An empty ship request is not an order update.
	 */
	#[DataProvider( 'storage_modes' )]
	public function test_empty_ship_does_not_mark_the_order_as_updated( bool $hpos ): void {
		$order = $this->create_stored_order( $hpos );
		$order->set_date_modified( '2020-01-01T00:00:00Z' );
		$order->save();
		if ( ! $hpos ) {
			// CPT save() does not keep a backdated modification time. The order datastore does.
			$order->get_data_store()->update_order_from_object( $order );
		}
		$seed = $this->api->get_order( $order->get_id() );
		$this->assertSame( '2020-01-01T00:00:00Z', $seed['order']['updated_at'] );

		$server = new class( '/orders/' . $order->get_id() . '/ship' ) extends \Dianxiaomi_API_Server {
			public string $body = '{}';

			public function get_raw_data(): string {
				return $this->body;
			}
		};
		$api            = new \Dianxiaomi_API_Orders( $server );
		$server->method = 'PUT';
		$server->params = array(
			'GET'  => array(),
			'POST' => array(),
		);
		try {
			$shipped = $server->dispatch();
			$this->assertIsArray( $shipped );
			$this->assertSame( 'processing', $shipped['order']['status'] );
			$this->assertSame( '', $shipped['order']['trackings'][0]['tracking_number'] );
			$this->assertSame(
				'2020-01-01T00:00:00Z',
				$shipped['order']['updated_at'],
				'updated_at=' . (string) $shipped['order']['updated_at']
			);

			$server->body = '{"status":"completed","tracking_provider":"ups","tracking_number":"AFTER-EMPTY"}';
			$changed      = $server->dispatch();
			$this->assert_api_order( $changed, $order->get_id(), 'completed', 'AFTER-EMPTY', 'ups' );
			$stored = new \WC_Order( $order->get_id() );
			$this->assertGreaterThan(
				( new \DateTimeImmutable( '2020-01-01T00:00:00Z' ) )->getTimestamp(),
				$stored->get_date_modified()->getTimestamp()
			);
		} finally {
			remove_filter( 'dianxiaomi_api_endpoints', array( $api, 'register_routes' ) );
			foreach ( array( 'order', 'coupon', 'customer', 'product', 'report' ) as $resource ) {
				remove_filter( "dianxiaomi_api_{$resource}_response", array( $api, 'maybe_add_meta' ), 15 );
				remove_filter( "dianxiaomi_api_{$resource}_response", array( $api, 'filter_response_fields' ), 20 );
			}
		}
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

	/**
	 * A JSON number is still the tracking number the carrier issued.
	 */
	#[DataProvider( 'storage_modes' )]
	public function test_numeric_json_tracking_number_is_stored_beyond_php_integer_range( bool $hpos ): void {
		$order  = $this->create_stored_order( $hpos );
		$server = new class( '/orders/' . $order->get_id() . '/ship' ) extends \Dianxiaomi_API_Server {
			public string $body = '';

			public function get_raw_data(): string {
				return $this->body;
			}
		};
		$api            = new \Dianxiaomi_API_Orders( $server );
		$server->method = 'PUT';
		$server->params = array(
			'GET'  => array(),
			'POST' => array(),
		);
		$server->body   = '{"status":"completed","tracking_provider":"ups","tracking_number":9400111899223856571031}';
		try {
			$shipped = $server->dispatch();
			$this->assert_api_order( $shipped, $order->get_id(), 'completed', '9400111899223856571031', 'ups' );
			$this->assert_persisted_order( $order->get_id(), 'completed', '9400111899223856571031', 'ups' );
		} finally {
			remove_filter( 'dianxiaomi_api_endpoints', array( $api, 'register_routes' ) );
			foreach ( array( 'order', 'coupon', 'customer', 'product', 'report' ) as $resource ) {
				remove_filter( "dianxiaomi_api_{$resource}_response", array( $api, 'maybe_add_meta' ), 15 );
				remove_filter( "dianxiaomi_api_{$resource}_response", array( $api, 'filter_response_fields' ), 20 );
			}
		}
	}

	/**
	 * Editing an order must keep a JSON tracking number too.
	 */
	#[DataProvider( 'storage_modes' )]
	public function test_numeric_json_tracking_number_survives_an_edit_beyond_php_integer_range( bool $hpos ): void {
		$order  = $this->create_stored_order( $hpos );
		$server = new class( '/orders/' . $order->get_id() ) extends \Dianxiaomi_API_Server {
			public string $body = '';

			public function get_raw_data(): string {
				return $this->body;
			}
		};
		$api            = new \Dianxiaomi_API_Orders( $server );
		$server->method = 'PUT';
		$server->params = array(
			'GET'  => array(),
			'POST' => array(),
		);
		$server->body   = '{"status":"completed","tracking_number":9400111899223856571031}';
		try {
			$edited = $server->dispatch();
			$this->assert_api_order( $edited, $order->get_id(), 'completed', '9400111899223856571031', '' );
			$this->assert_persisted_order( $order->get_id(), 'completed', '9400111899223856571031', '' );
		} finally {
			remove_filter( 'dianxiaomi_api_endpoints', array( $api, 'register_routes' ) );
			foreach ( array( 'order', 'coupon', 'customer', 'product', 'report' ) as $resource ) {
				remove_filter( "dianxiaomi_api_{$resource}_response", array( $api, 'maybe_add_meta' ), 15 );
				remove_filter( "dianxiaomi_api_{$resource}_response", array( $api, 'filter_response_fields' ), 20 );
			}
		}
	}
}
