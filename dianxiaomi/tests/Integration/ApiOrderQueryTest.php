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
		// WooCommerce's test-only switch avoids syncing fixtures owned by other suites.
		add_filter( 'wc_allow_changing_orders_storage_while_sync_is_pending', array( $this, 'allow_storage_switch' ) );
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
			remove_filter( 'wc_allow_changing_orders_storage_while_sync_is_pending', array( $this, 'allow_storage_switch' ) );
			wp_set_current_user( $this->user_id );
			list( $_GET, $_POST, $_REQUEST, $_SERVER ) = $this->request_globals;
		} finally {
			parent::tear_down();
		}
	}

	public function allow_storage_switch(): bool {
		return true;
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

	private function set_modified_date( \WC_Order $order, string $date ): void {
		$order->set_date_modified( $date );
		$order->save();
		if ( ! OrderUtil::custom_orders_table_usage_is_enabled() ) {
			// Preserve historical fixture dates through WooCommerce's CPT import path.
			$order->get_data_store()->update_order_from_object( $order );
		}
		$stored = new \WC_Order( $order->get_id() );
		$this->assertSame( ( new \DateTimeImmutable( $date ) )->getTimestamp(), $stored->get_date_modified()->getTimestamp() );
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

	public static function query_filters(): array {
		$cases = array(
			'ascending date' => array( array( 'order' => 'asc' ), array( 1, 2, 0 ) ),
			'modified descending' => array( array( 'orderby' => 'modified' ), array( 1, 2, 0 ) ),
			'ID descending' => array( array( 'orderby' => 'ID' ), array( 2, 1, 0 ) ),
			'offset' => array( array( 'offset' => '1', 'posts_per_page' => 1 ), array( 2 ) ),
			'combined' => array( array( 'orderby' => 'id', 'order' => 'ASC', 'offset' => 1, 'posts_per_page' => 1 ), array( 1 ) ),
			'invalid strings' => array( array( 'orderby' => 'not_a_column', 'order' => 'invalid', 'offset' => '-1' ), array( 0, 2, 1 ) ),
			'invalid types' => array( array( 'orderby' => array( 'ID' ), 'order' => array( 'ASC' ), 'offset' => '1.5' ), array( 0, 2, 1 ) ),
		);
		$data = array();
		foreach ( self::storage_modes() as $mode => [ $hpos ] ) {
			foreach ( $cases as $name => [ $filter, $expected ] ) {
				$data[ $mode . ' ' . $name ] = array( $hpos, $filter, $expected );
			}
		}
		return $data;
	}

	/** @dataProvider query_filters */
	public function test_order_filters_preserve_sorting_and_offset( bool $hpos, array $filter, array $expected ): void {
		$this->use_storage( $hpos );
		$ids = array();
		foreach ( array( 3, 1, 2 ) as $day ) {
			$order = $this->create_query_order( "2026-01-0{$day}T12:00:00Z" );
			$modified_day = 4 - $day;
			$this->set_modified_date( $order, "2026-02-0{$modified_day}T12:00:00Z" );
			$ids[] = $order->get_id();
		}
		$result = $this->api->get_orders( null, $filter, 'dxm-query' );
		$this->assertSame( array_map( static fn( int $index ): int => $ids[ $index ], $expected ), array_column( $result['orders'], 'id' ) );
		$this->assertSame( array( '3' ), $this->server->sent_headers['X-WC-Total'] );
		$this->assertSame( array( isset( $filter['posts_per_page'] ) ? '3' : '1' ), $this->server->sent_headers['X-WC-TotalPages'] );
	}

	public static function update_bounds(): array {
		$bounds = array(
			'UTC' => '2026-05-06T12:00:00Z',
			'positive offset' => '2026-05-06T17:00:00+05:00',
			'negative offset' => '2026-05-06T08:00:00-04:00',
			'timestamp' => (string) ( new \DateTimeImmutable( '2026-05-06T12:00:00Z' ) )->getTimestamp(),
		);
		$data = array();
		foreach ( self::storage_modes() as $mode => [ $hpos ] ) {
			foreach ( $bounds as $name => $bound ) {
				$data[ $mode . ' ' . $name ] = array( $hpos, $bound );
			}
		}
		return $data;
	}

	/** @dataProvider update_bounds */
	public function test_updated_at_min_filters_the_instant_inclusively( bool $hpos, string $bound ): void {
		$this->use_storage( $hpos );
		update_option( 'timezone_string', 'America/Toronto' );
		$ids = array();
		foreach ( array( '11:59:59', '12:00:00', '12:00:01' ) as $time ) {
			$date = "2026-05-06T{$time}Z";
			$order = $this->create_query_order( $date );
			$this->set_modified_date( $order, $date );
			$ids[] = $order->get_id();
		}
		$result = $this->api->get_orders( null, array(), 'dxm-query', 1, $bound );
		$this->assertSame( array( $ids[2], $ids[1] ), array_column( $result['orders'], 'id' ) );
		$this->assertSame( array( '2' ), $this->server->sent_headers['X-WC-Total'] );
	}

	public function test_datetime_parser_converts_offsets_to_utc(): void {
		$this->assertSame( '2026-05-06 12:00:00', $this->server->parse_datetime( '2026-05-06T17:00:00+05:00' ) );
		$this->assertSame( '2026-05-06 12:00:00', $this->server->parse_datetime( '2026-05-06T08:00:00-04:00' ) );
		$this->assertSame( '2026-05-06 12:00:00', $this->server->parse_datetime( '2026-05-06T12:00:00.000Z' ) );
		$this->assertSame( '1970-01-01 00:00:00', $this->server->parse_datetime( 'not-a-date' ) );
	}

	public static function response_dates(): array {
		$data = array();
		foreach ( self::storage_modes() as $mode => [ $hpos ] ) {
			foreach ( array( 'winter' => '2026-01-15', 'summer' => '2026-07-15' ) as $season => $day ) {
				foreach ( array( 'created_at' => 'date_created', 'updated_at' => 'date_modified', 'completed_at' => 'date_completed' ) as $field => $property ) {
					$data[ $mode . ' ' . $season . ' ' . $field ] = array( $hpos, $day, $field, $property );
				}
			}
		}
		return $data;
	}

	/** @dataProvider response_dates */
	public function test_response_dates_preserve_utc_instants_in_toronto( bool $hpos, string $day, string $field, string $property ): void {
		$this->use_storage( $hpos );
		update_option( 'timezone_string', 'America/Toronto' );
		$order = $this->create_query_order( $day . 'T12:34:56Z' );
		$order->set_date_completed( $day . 'T14:34:56Z' );
		$this->set_modified_date( $order, $day . 'T15:34:56Z' );
		$stored = new \WC_Order( $order->get_id() );
		$result = $this->api->get_orders( null, array(), 'dxm-query' );
		$this->assertCount( 1, $result['orders'] );
		$timestamp = $stored->{ 'get_' . $property }()->getTimestamp();
		$this->assertSame( gmdate( 'Y-m-d\TH:i:s\Z', $timestamp ), $result['orders'][0][ $field ], $field );
		$this->assertSame( $timestamp, ( new \DateTimeImmutable( $result['orders'][0][ $field ] ) )->getTimestamp(), $field );
	}

	/** @dataProvider storage_modes */
	public function test_response_keeps_missing_completion_date_empty( bool $hpos ): void {
		$this->use_storage( $hpos );
		$this->create_query_order( '2026-01-15T12:34:56Z' );
		$result = $this->api->get_orders( null, array(), 'dxm-query' );
		$this->assertSame( '', $result['orders'][0]['completed_at'] );
	}

	public function test_pagination_links_preserve_the_home_port_and_request_path(): void {
		$home_url = static fn(): string => 'http://localhost:9905/shop';
		add_filter( 'home_url', $home_url );
		try {
			$this->create_query_order( '2026-01-01T12:00:00Z' );
			$this->create_query_order( '2026-01-02T12:00:00Z' );
			$_SERVER['REQUEST_URI'] = '/shop/dianxiaomi-api/v1/orders?filter%5Bposts_per_page%5D=1';
			$this->api->get_orders( null, array( 'posts_per_page' => 1 ), 'dxm-query' );
			$this->assertNotEmpty( $this->server->sent_headers['Link'] );
			foreach ( $this->server->sent_headers['Link'] as $header ) {
				$this->assertSame( 1, preg_match( '/^<(.+)>; rel="([^"]+)"$/', $header, $matches ) );
				$url = wp_parse_url( $matches[1] );
				$this->assertSame( 'localhost', $url['host'] );
				$this->assertSame( 9905, $url['port'] ?? null );
				$this->assertSame( '/shop/dianxiaomi-api/v1/orders', $url['path'] );
			}
		} finally {
			remove_filter( 'home_url', $home_url );
		}
	}

	public static function encoded_bounds(): array {
		return array(
			'CPT top level' => array( false, false ),
			'CPT filter' => array( false, true ),
			'HPOS top level' => array( true, false ),
			'HPOS filter' => array( true, true ),
		);
	}

	/** @dataProvider encoded_bounds */
	public function test_dispatch_preserves_already_decoded_timezone_offsets( bool $hpos, bool $nested ): void {
		$this->use_storage( $hpos );
		$ids = array();
		foreach ( array( '11:59:59', '12:00:00' ) as $time ) {
			$order = $this->create_query_order( "2026-05-06T{$time}Z" );
			$this->set_modified_date( $order, "2026-05-06T{$time}Z" );
			$ids[] = $order->get_id();
		}
		$params = array( 'status' => 'dxm-query' );
		$bound = '2026-05-06T17:00:00+05:00';
		if ( $nested ) {
			$params['filter']['updated_at_min'] = $bound;
		} else {
			$params['updated_at_min'] = $bound;
		}
		// PHP decodes the query string once before the API receives $_GET.
		parse_str( http_build_query( $params, '', '&', PHP_QUERY_RFC3986 ), $decoded );
		$this->server->params = array( 'GET' => $decoded, 'POST' => array() );
		$this->server->method = 'GET';
		$result = $this->server->dispatch();
		$this->assertIsArray( $result );
		$this->assertSame( array( $ids[1] ), array_column( $result['orders'], 'id' ) );
	}

	/** @dataProvider encoded_bounds */
	public function test_following_pagination_links_preserves_encoded_filters( bool $hpos, bool $nested ): void {
		$this->use_storage( $hpos );
		$ids = array();
		foreach ( array( '11:59:59', '12:00:00', '12:00:01' ) as $time ) {
			$order = $this->create_query_order( "2026-05-06T{$time}Z" );
			$this->set_modified_date( $order, "2026-05-06T{$time}Z" );
			$ids[] = $order->get_id();
		}
		$params = array( 'status' => 'dxm-query', 'filter' => array( 'posts_per_page' => '1' ) );
		$bound = '2026-05-06T17:00:00+05:00';
		if ( $nested ) {
			$params['filter']['updated_at_min'] = $bound;
		} else {
			$params['updated_at_min'] = $bound;
		}
		$_SERVER['REQUEST_URI'] = '/dianxiaomi-api/v1/orders?' . http_build_query( $params, '', '&', PHP_QUERY_RFC3986 );
		$result = $this->api->get_orders( null, $params['filter'], 'dxm-query', 1, $params['updated_at_min'] ?? '' );
		$this->assertSame( array( $ids[2] ), array_column( $result['orders'], 'id' ) );
		$next_url = null;
		foreach ( $this->server->sent_headers['Link'] as $header ) {
			if ( preg_match( '/^<(.+)>; rel="next"$/', $header, $matches ) ) {
				$next_url = $matches[1];
			}
		}
		$this->assertNotNull( $next_url );
		parse_str( (string) wp_parse_url( $next_url, PHP_URL_QUERY ), $decoded );
		$this->assertSame( $bound, $nested ? $decoded['filter']['updated_at_min'] : $decoded['updated_at_min'] );
		$this->assertSame( '2', $decoded['page'] );
		$this->server->params = array( 'GET' => $decoded, 'POST' => array() );
		$this->server->method = 'GET';
		$result = $this->server->dispatch();
		$this->assertIsArray( $result );
		$this->assertSame( array( $ids[1] ), array_column( $result['orders'], 'id' ) );
	}

	/** @dataProvider storage_modes */
	public function test_limit_filter_sets_the_page_size( bool $hpos ): void {
		$this->use_storage( $hpos );
		foreach ( range( 1, 3 ) as $day ) {
			$this->create_query_order( "2026-03-0{$day}T12:00:00Z" );
		}

		$result = $this->api->get_orders( null, array( 'limit' => '1' ), 'dxm-query' );

		$this->assertCount( 1, $result['orders'] );
		$this->assertSame( array( '3' ), $this->server->sent_headers['X-WC-Total'] );
		$this->assertSame( array( '3' ), $this->server->sent_headers['X-WC-TotalPages'] );
	}

	/** @dataProvider storage_modes */
	public function test_status_filter_accepts_a_wc_prefix( bool $hpos ): void {
		$this->use_storage( $hpos );
		$order  = $this->create_query_order( '2026-04-01T12:00:00Z' );
		$result = $this->api->get_orders( null, array(), 'wc-dxm-query' );

		$this->assertSame( array( $order->get_id() ), array_column( $result['orders'], 'id' ) );
	}

	/** @dataProvider storage_modes */
	public function test_updated_at_max_filters_the_instant_inclusively( bool $hpos ): void {
		$this->use_storage( $hpos );
		update_option( 'timezone_string', 'America/Toronto' );
		$ids = array();
		foreach ( array( '11:59:59', '12:00:00', '12:00:01' ) as $time ) {
			$date  = "2026-05-06T{$time}Z";
			$order = $this->create_query_order( $date );
			$this->set_modified_date( $order, $date );
			$ids[] = $order->get_id();
		}

		$result = $this->api->get_orders( null, array( 'updated_at_max' => '2026-05-06T12:00:00Z' ), 'dxm-query' );

		$this->assertSame( array( $ids[1], $ids[0] ), array_column( $result['orders'], 'id' ) );
		$this->assertSame( array( '2' ), $this->server->sent_headers['X-WC-Total'] );
	}

	/** @dataProvider storage_modes */
	public function test_updated_at_range_keeps_both_bounds( bool $hpos ): void {
		$this->use_storage( $hpos );
		update_option( 'timezone_string', 'America/Toronto' );
		$ids = array();
		foreach ( array( '11:59:59', '12:00:00', '12:00:01' ) as $time ) {
			$date  = "2026-05-06T{$time}Z";
			$order = $this->create_query_order( $date );
			$this->set_modified_date( $order, $date );
			$ids[] = $order->get_id();
		}

		$result = $this->api->get_orders(
			null,
			array(
				'updated_at_min' => '2026-05-06T12:00:00Z',
				'updated_at_max' => '2026-05-06T12:00:00Z',
			),
			'dxm-query'
		);

		$this->assertSame( array( $ids[1] ), array_column( $result['orders'], 'id' ) );
		$this->assertSame( array( '1' ), $this->server->sent_headers['X-WC-Total'] );
	}
}
