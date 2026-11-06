<?php
/**
 * Integration coverage for order API authorization and request arguments.
 *
 * @package Dianxiaomi\Tests\Integration
 */

declare(strict_types=1);

namespace Dianxiaomi\Tests\Integration;

use PHPUnit\Framework\Attributes\DataProvider;

class ApiPermissionsTest extends TestCase {

	private \Dianxiaomi_API_Server $server;
	private \Dianxiaomi_API_Orders $orders;
	private \Dianxiaomi_API_Authentication $authentication;
	private ?\Dianxiaomi_API_Server $previous_server;
	private \WC_Order $order;
	private int $previous_user_id;
	private array $request_globals;
	private array $storage_options = array();
	private array $users = array();
	private array $api_keys = array();
	private array $posts = array();

	protected function set_up(): void {
		parent::set_up();

		foreach ( array(
			'api/interface-dianxiaomi-api-handler.php',
			'api/class-dianxiaomi-api-json-handler.php',
			'api/class-dianxiaomi-api-server.php',
			'api/class-dianxiaomi-api-authentication.php',
			'inc/traits/trait-api-response.php',
			'inc/traits/trait-woocommerce-helper.php',
			'api/class-dianxiaomi-api-resource.php',
			'api/class-dianxiaomi-api-orders.php',
		) as $file ) {
			require_once dirname( __DIR__, 2 ) . '/' . $file;
		}

		$this->previous_user_id = get_current_user_id();
		$this->request_globals = array( $_GET, $_POST, $_REQUEST, $_SERVER );
		$this->previous_server = $this->get_plugin()->api->server ?? null;

		// Exercise permissions independently of the separate HPOS validation changes.
		foreach ( array( 'woocommerce_custom_orders_table_data_sync_enabled', 'woocommerce_custom_orders_table_enabled' ) as $option ) {
			$this->storage_options[ $option ] = get_option( $option );
			update_option( $option, 'no' );
		}

		wp_set_current_user( 0 );
		$_GET                      = array();
		$_POST                     = array();
		$_REQUEST                  = array();
		$_SERVER['REQUEST_METHOD'] = 'GET';

		$this->server = new class( '/orders' ) extends \Dianxiaomi_API_Server {
			/** Capture transport headers without emitting them after PHPUnit CLI output. */
			public function header( string $key, string $value, bool $replace = true ): void {
				$this->headers[ $key ] = $value;
			}
		};
		$this->get_plugin()->api->server = $this->server;
		$this->authentication           = new \Dianxiaomi_API_Authentication();
		$this->orders                   = new \Dianxiaomi_API_Orders( $this->server );
		$this->order                    = $this->create_order();
		$note_id                        = $this->order->add_order_note( 'Private fulfillment instructions.' );
		$this->assertSame( 'order_note', get_comment( $note_id )->comment_type );
	}

	protected function tear_down(): void {
		$this->order->delete( true );
		foreach ( $this->posts as $post_id ) {
			wp_delete_post( $post_id, true );
		}
		foreach ( $this->users as $user_id ) {
			wp_delete_user( $user_id );
		}
		foreach ( $this->api_keys as $api_key ) {
			wp_cache_delete( 'dianxiaomi_user_' . md5( $api_key ) );
		}

		remove_filter( 'dianxiaomi_api_check_authentication', array( $this->authentication, 'authenticate' ), 0 );
		remove_filter( 'dianxiaomi_api_endpoints', array( $this->orders, 'register_routes' ) );
		foreach ( array( 'order', 'coupon', 'customer', 'product', 'report' ) as $resource ) {
			remove_filter( "dianxiaomi_api_{$resource}_response", array( $this->orders, 'maybe_add_meta' ), 15 );
			remove_filter( "dianxiaomi_api_{$resource}_response", array( $this->orders, 'filter_response_fields' ), 20 );
		}

		if ( null === $this->previous_server ) {
			unset( $this->get_plugin()->api->server );
		} else {
			$this->get_plugin()->api->server = $this->previous_server;
		}
		foreach ( $this->storage_options as $option => $value ) {
			if ( false === $value ) {
				delete_option( $option );
			} else {
				update_option( $option, $value );
			}
		}
		list( $_GET, $_POST, $_REQUEST, $_SERVER ) = $this->request_globals;
		wp_set_current_user( $this->previous_user_id );

		parent::tear_down();
	}

	public static function authorized_roles(): array {
		return array(
			'administrator' => array( 'administrator' ),
			'shop manager'  => array( 'shop_manager' ),
		);
	}

	public static function unauthorized_requests(): array {
		$cases = array();
		foreach ( array( '', 'subscriber', 'customer', 'editor' ) as $role ) {
			foreach ( array( 'orders', 'order', 'notes' ) as $resource ) {
				$cases[ $role . ':' . $resource ] = array( $role, $resource );
			}
		}
		return $cases;
	}

	public static function demoted_requests(): array {
		$cases = array();
		foreach ( array( 'administrator', 'shop_manager' ) as $role ) {
			foreach ( array( 'orders', 'order', 'notes' ) as $resource ) {
				$cases[ $role . ':' . $resource ] = array( $role, $resource );
			}
		}
		return $cases;
	}

	#[DataProvider( 'authorized_roles' )]
	public function test_authorized_roles_can_read_orders_details_and_notes( string $role ): void {
		$user = $this->create_api_user( $role );
		wp_set_current_user( $user->ID );
		$this->assert_order_data( 'orders', $this->orders->get_orders() );
		$this->assert_order_data( 'order', $this->orders->get_order( $this->order->get_id() ) );
		$this->assert_order_data( 'notes', $this->orders->get_order_notes( $this->order->get_id() ) );

		foreach ( array( 'orders', 'order', 'notes' ) as $resource ) {
			$this->assert_order_data( $resource, $this->authenticated_request( $user, $resource ) );
		}
	}

	#[DataProvider( 'unauthorized_requests' )]
	public function test_users_without_order_permissions_cannot_read_resources( string $role, string $resource ): void {
		$user = $this->create_api_user( $role );
		wp_set_current_user( $user->ID );
		$this->assertFalse( current_user_can( 'read_private_shop_orders' ) );
		$result = match ( $resource ) {
			'orders' => $this->orders->get_orders(),
			'order'  => $this->orders->get_order( $this->order->get_id() ),
			'notes'  => $this->orders->get_order_notes( $this->order->get_id() ),
		};
		$this->assert_denied( $resource, $result );
		$this->assert_denied( $resource, $this->authenticated_request( $user, $resource ) );
	}

	#[DataProvider( 'demoted_requests' )]
	public function test_existing_api_key_loses_access_after_role_demotion( string $role, string $resource ): void {
		$user = $this->create_api_user( $role );
		$key  = get_user_meta( $user->ID, 'dianxiaomi_wp_api_key', true );
		$this->assert_order_data( $resource, $this->authenticated_request( $user, $resource ) );

		$user->set_role( 'subscriber' );
		$this->assertSame( $key, get_user_meta( $user->ID, 'dianxiaomi_wp_api_key', true ) );
		$result = $this->authenticated_request( $user, $resource );
		$this->assertFalse( current_user_can( 'read_private_shop_orders' ) );
		$this->assert_denied( $resource, $result );
	}

	public function test_detail_cannot_bypass_permission_validation(): void {
		$user = $this->create_api_user( 'subscriber' );
		wp_set_current_user( $user->ID );
		$this->assert_denied( 'order', $this->orders->get_order( $this->order->get_id(), null, true ) );
	}

	public function test_order_list_requires_permission_even_when_empty(): void {
		wp_set_current_user( 0 );
		$this->assert_denied( 'orders', $this->orders->get_orders( null, array(), 'cancelled' ) );
	}

	public function test_notes_reject_missing_order_instead_of_returning_success(): void {
		$user = $this->create_api_user( 'administrator' );
		wp_set_current_user( $user->ID );
		$result = $this->orders->get_order_notes( 0 );
		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 404, $result->get_error_data()['status'] );

		$id = $this->order->get_id();
		$this->order->delete( true );
		$this->server->path = '/orders/' . $id . '/notes';
		$result             = $this->server->dispatch();
		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 404, $result->get_error_data()['status'] );
	}

	public function test_notes_reject_non_order_posts(): void {
		$user = $this->create_api_user( 'administrator' );
		wp_set_current_user( $user->ID );
		$post_id       = wp_insert_post( array( 'post_title' => 'Not an order', 'post_status' => 'publish' ) );
		$this->posts[] = $post_id;
		$this->server->path = '/orders/' . $post_id . '/notes';
		$result             = $this->server->dispatch();
		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 404, $result->get_error_data()['status'] );
	}

	#[DataProvider( 'authorized_roles' )]
	public function test_detail_route_accepts_comma_separated_fields( string $role ): void {
		$user     = $this->create_api_user( $role );
		$params   = array( 'fields' => 'id,status' );
		$expected = array( 'id' => $this->order->get_id(), 'status' => 'processing' );
		$list     = $this->authenticated_request( $user, 'orders', $params );
		$this->assertContains( $expected, $list['orders'] );

		$detail = $this->authenticated_request( $user, 'order', $params );
		$this->assertSame( $expected, $detail['order'] );
	}

	public function test_detail_route_accepts_nested_fields(): void {
		$user   = $this->create_api_user( 'shop_manager' );
		$result = $this->authenticated_request( $user, 'order', array( 'fields' => 'id,billing_address.first_name' ) );
		$this->assertSame(
			array( 'id' => $this->order->get_id(), 'billing_address' => array( 'first_name' => '' ) ),
			$result['order']
		);
	}

	public function test_detail_retains_array_and_default_fields(): void {
		$user = $this->create_api_user( 'administrator' );
		wp_set_current_user( $user->ID );
		$default = $this->orders->get_order( $this->order->get_id() );
		$this->assertSame( $this->order->get_id(), $default['order']['id'] );
		$this->assertArrayHasKey( 'billing_address', $default['order'] );
		$this->assertSame( $default, $this->orders->get_order( $this->order->get_id(), null ) );
		$this->assertSame( $default, $this->orders->get_order( $this->order->get_id(), array() ) );
		$this->assertSame(
			array( 'order' => array( 'id' => $this->order->get_id(), 'status' => 'processing' ) ),
			$this->orders->get_order( $this->order->get_id(), array( 'id', 'status' ) )
		);
	}

	public function test_detail_fields_still_require_read_permission(): void {
		$user = $this->create_api_user( 'subscriber' );
		$this->assert_denied( 'order', $this->authenticated_request( $user, 'order', array( 'fields' => 'id,status' ) ) );
	}

	public static function api_key_headers(): array {
		return array(
			'Dianxiaomi header' => array( 'HTTP_DIANXIAOMI_WP_KEY' ),
			'legacy AfterShip header' => array( 'HTTP_AFTERSHIP_WP_KEY' ),
		);
	}

	#[DataProvider( 'api_key_headers' )]
	public function test_api_key_headers_authenticate_without_a_query_key( string $header ): void {
		$user = $this->create_api_user( 'administrator' );
		wp_set_current_user( 0 );
		$_GET = array();
		$_REQUEST = array( '_wpnonce' => wp_create_nonce( 'dianxiaomi_action' ) );
		$_SERVER[ $header ] = get_user_meta( $user->ID, 'dianxiaomi_wp_api_key', true );

		$this->assertInstanceOf( \WP_User::class, $this->server->check_authentication() );
		$this->assertSame( $user->ID, get_current_user_id() );
		$this->assert_order_data( 'orders', $this->server->dispatch() );
	}

	public function test_api_key_header_still_requires_a_valid_nonce(): void {
		$user = $this->create_api_user( 'administrator' );
		$_SERVER['HTTP_DIANXIAOMI_WP_KEY'] = get_user_meta( $user->ID, 'dianxiaomi_wp_api_key', true );
		$_REQUEST['_wpnonce'] = 'invalid-header-nonce';
		$result = $this->server->check_authentication();

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( array( 'status' => 403 ), $result->get_error_data() );
		$this->assertSame( 0, get_current_user_id() );
	}

	public function test_invalid_api_key_header_is_rejected(): void {
		$_SERVER['HTTP_DIANXIAOMI_WP_KEY'] = 'invalid-local-regression-key';
		$_REQUEST = array( '_wpnonce' => wp_create_nonce( 'dianxiaomi_action' ) );
		$result = $this->server->check_authentication();

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( array( 'status' => 401 ), $result->get_error_data() );
		$this->assertSame( 0, get_current_user_id() );
	}

	private function create_api_user( string $role ): \WP_User {
		$user_id = wp_insert_user(
			array(
				'user_login' => 'api-permissions-' . wp_generate_uuid4(),
				'user_pass'  => wp_generate_password( 32 ),
				'role'       => $role,
			)
		);
		$this->assertIsInt( $user_id );
		$this->users[] = $user_id;
		$key           = bin2hex( random_bytes( 24 ) );
		$this->api_keys[] = $key;
		update_user_meta( $user_id, 'dianxiaomi_wp_api_key', $key );
		return new \WP_User( $user_id );
	}

	/** Use the real authentication filter and registered route dispatcher. */
	private function authenticated_request( \WP_User $user, string $resource, array $params = array() ): mixed {
		wp_set_current_user( 0 );
		$_GET     = array( 'key' => get_user_meta( $user->ID, 'dianxiaomi_wp_api_key', true ) );
		$_REQUEST = array( '_wpnonce' => wp_create_nonce( 'dianxiaomi_action' ) );
		$this->server->path = match ( $resource ) {
			'orders' => '/orders',
			'order'  => '/orders/' . $this->order->get_id(),
			'notes'  => '/orders/' . $this->order->get_id() . '/notes',
		};
		$this->server->params = array( 'GET' => $params, 'POST' => array() );
		$authenticated       = $this->server->check_authentication();
		$this->assertInstanceOf( \WP_User::class, $authenticated );
		$this->assertSame( $user->ID, get_current_user_id() );
		return $this->server->dispatch();
	}

	private function assert_denied( string $resource, mixed $result ): void {
		$this->assertInstanceOf( \WP_Error::class, $result, 'Order data must not be disclosed without permission.' );
		$this->assertSame( 'orders' === $resource ? 403 : 401, $result->get_error_data()['status'] );
	}

	private function assert_order_data( string $resource, mixed $result ): void {
		$this->assertIsArray( $result );
		switch ( $resource ) {
			case 'orders':
				$this->assertContains( $this->order->get_id(), array_column( $result['orders'], 'id' ) );
				break;
			case 'order':
				$this->assertSame( $this->order->get_id(), $result['order']['id'] );
				break;
			case 'notes':
				$this->assertIsArray( $result['order_notes'] );
				$this->assertContains( 'Private fulfillment instructions.', array_column( $result['order_notes'], 'note' ) );
				break;
		}
	}
}
