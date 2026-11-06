<?php
/**
 * Integration coverage for order queries, pagination and dates.
 *
 * @package Dianxiaomi\Tests\Integration
 */

declare(strict_types=1);

namespace Dianxiaomi\Tests\Integration;

use Automattic\WooCommerce\Utilities\OrderUtil;

require_once dirname( __DIR__, 2 ) . '/api/interface-dianxiaomi-api-handler.php';
require_once dirname( __DIR__, 2 ) . '/api/class-dianxiaomi-api-json-handler.php';
require_once dirname( __DIR__, 2 ) . '/api/class-dianxiaomi-api-server.php';
require_once dirname( __DIR__, 2 ) . '/api/class-dianxiaomi-api-resource.php';
require_once dirname( __DIR__, 2 ) . '/api/class-dianxiaomi-api-orders.php';

class ApiOrderQueryTest extends TestCase {
	private \Dianxiaomi_API_Server $server;
	private \Dianxiaomi_API_Orders $api;
	private array $orders = array();
	private array $options = array();
	private array $request_globals;
	private int $user_id;
	private mixed $original_status;

	protected function set_up(): void {
		parent::set_up();
		$this->request_globals = array( $_GET, $_POST, $_REQUEST, $_SERVER );
		$this->user_id = get_current_user_id();
		$admins = get_users( array( 'role' => 'administrator', 'number' => 1 ) );
		$this->assertNotEmpty( $admins );
		wp_set_current_user( $admins[0]->ID );
		foreach ( array( 'woocommerce_custom_orders_table_enabled', 'woocommerce_custom_orders_table_data_sync_enabled', 'timezone_string', 'gmt_offset', 'home' ) as $name ) {
			$this->options[ $name ] = get_option( $name );
		}
		update_option( 'woocommerce_custom_orders_table_data_sync_enabled', 'no' );
		update_option( 'timezone_string', 'UTC' );
		update_option( 'gmt_offset', 0 );
		$this->original_status = $GLOBALS['wp_post_statuses']['wc-dxm-query'] ?? null;
		register_post_status( 'wc-dxm-query', array( 'public' => true, 'label' => 'Query fixture' ) );
		add_filter( 'wc_order_statuses', array( $this, 'register_status' ) );
		add_filter( 'pre_http_request', array( $this, 'block_http' ) );
		$_SERVER['REQUEST_URI'] = '/dianxiaomi-api/v1/orders';
		$this->server = new class( '/orders' ) extends \Dianxiaomi_API_Server {
			public array $sent_headers = array();

			public function header( string $key, string $value, bool $replace = true ): void {
				if ( $replace ) {
					$this->sent_headers[ $key ] = array();
				}
				$this->sent_headers[ $key ][] = $value;
			}
		};
		$this->api = new \Dianxiaomi_API_Orders( $this->server );
	}

	protected function tear_down(): void {
		try {
			foreach ( $this->orders as $order ) {
				$order->delete( true );
			}
			$this->orders = array();
			remove_filter( 'dianxiaomi_api_endpoints', array( $this->api, 'register_routes' ) );
			foreach ( array( 'order', 'coupon', 'customer', 'product', 'report' ) as $resource ) {
				remove_filter( "dianxiaomi_api_{$resource}_response", array( $this->api, 'maybe_add_meta' ), 15 );
				remove_filter( "dianxiaomi_api_{$resource}_response", array( $this->api, 'filter_response_fields' ), 20 );
			}
			remove_filter( 'wc_order_statuses', array( $this, 'register_status' ) );
			remove_filter( 'pre_http_request', array( $this, 'block_http' ) );
			if ( null === $this->original_status ) {
				unset( $GLOBALS['wp_post_statuses']['wc-dxm-query'] );
			} else {
				$GLOBALS['wp_post_statuses']['wc-dxm-query'] = $this->original_status;
			}
			foreach ( $this->options as $name => $value ) {
				false === $value ? delete_option( $name ) : update_option( $name, $value );
			}
			wp_set_current_user( $this->user_id );
			list( $_GET, $_POST, $_REQUEST, $_SERVER ) = $this->request_globals;
		} finally {
			parent::tear_down();
		}
	}

	public function register_status( array $statuses ): array {
		$statuses['wc-dxm-query'] = 'Query fixture';
		return $statuses;
	}

	public function block_http(): \WP_Error {
		return new \WP_Error( 'test_http_blocked', 'External requests are disabled during integration tests.' );
	}

	public static function storage_modes(): array {
		return array( 'CPT' => array( false ), 'HPOS' => array( true ) );
	}

	private function use_storage( bool $hpos ): void {
		update_option( 'woocommerce_custom_orders_table_enabled', $hpos ? 'yes' : 'no' );
		$this->assertSame( $hpos, OrderUtil::custom_orders_table_usage_is_enabled() );
	}

	private function create_query_order( string $created ): \WC_Order {
		$order = new \WC_Order();
		$order->set_status( 'dxm-query' );
		$order->set_date_created( $created );
		$order->save();
		$this->orders[] = $order;
		return $order;
	}

	private function links(): array {
		$links = array();
		foreach ( $this->server->sent_headers['Link'] ?? array() as $header ) {
			$this->assertSame( 1, preg_match( '/^<(.+)>; rel="([^"]+)"$/', $header, $matches ) );
			parse_str( (string) wp_parse_url( $matches[1], PHP_URL_QUERY ), $query );
			$links[ $matches[2] ] = (int) $query['page'];
		}
		return $links;
	}

	/** @dataProvider storage_modes */
	public function test_pagination_links_follow_the_requested_page( bool $hpos ): void {
		$this->use_storage( $hpos );
		$ids = array();
		foreach ( range( 1, 5 ) as $day ) {
			$ids[] = $this->create_query_order( "2026-01-0{$day}T12:00:00Z" )->get_id();
		}
		$ids = array_reverse( $ids );
		$expected_links = array(
			1 => array( 'next' => 2, 'last' => 3 ),
			2 => array( 'first' => 1, 'prev' => 1, 'next' => 3, 'last' => 3 ),
			3 => array( 'first' => 1, 'prev' => 2 ),
		);
		foreach ( $expected_links as $page => $links ) {
			$this->server->sent_headers = array();
			$_SERVER['REQUEST_URI'] = '/dianxiaomi-api/v1/orders?filter[posts_per_page]=2&page=' . $page;
			$result = $this->api->get_orders( null, array( 'posts_per_page' => 2 ), 'dxm-query', $page );
			$this->assertSame( array_slice( $ids, ( $page - 1 ) * 2, 2 ), array_column( $result['orders'], 'id' ) );
			$this->assertSame( array( '5' ), $this->server->sent_headers['X-WC-Total'] );
			$this->assertSame( array( '3' ), $this->server->sent_headers['X-WC-TotalPages'] );
			$this->assertSame( $links, $this->links(), "Incorrect Link headers for page {$page}" );
		}
	}
}
