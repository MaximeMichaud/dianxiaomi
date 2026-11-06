<?php
/**
 * Integration tests for complete API responses and JSONP encoding.
 *
 * @package Dianxiaomi\Tests\Integration
 */

declare(strict_types=1);

namespace Dianxiaomi\Tests\Integration;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Isolate HTTP headers while using the real WordPress runtime and API handler.
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState( false )]
class ApiResponseTest extends TestCase {
	private array $original_get;
	private int $original_user_id;
	private int $original_status;
	private array $original_options = array();
	private array $filters = array();

	protected function set_up(): void {
		parent::set_up();

		require_once dirname( __DIR__, 2 ) . '/api/interface-dianxiaomi-api-handler.php';
		require_once dirname( __DIR__, 2 ) . '/api/class-dianxiaomi-api-json-handler.php';

		$this->original_get     = $_GET;
		$this->original_user_id = get_current_user_id();
		$this->original_status  = http_response_code() ?: 200;
		$_GET                   = array();
		wp_set_current_user( 0 );
		status_header( 200 );
		$this->set_option( 'dianxiaomi_api_enabled', 'yes' );
	}

	protected function tear_down(): void {
		foreach ( $this->filters as list( $hook, $callback ) ) {
			remove_filter( $hook, $callback );
		}
		foreach ( $this->original_options as $name => $value ) {
			if ( null === $value ) {
				delete_option( $name );
			} else {
				update_option( $name, $value );
			}
		}
		$_GET = $this->original_get;
		wp_set_current_user( $this->original_user_id );
		status_header( $this->original_status );

		parent::tear_down();
	}

	/**
	 * Reject executable statements and malformed names without sanitizing them into callbacks.
	 */
	#[DataProvider( 'invalid_jsonp_callbacks' )]
	public function test_jsonp_rejects_invalid_callbacks( mixed $callback ): void {
		$_GET['_jsonp']   = $callback;
		$_GET['_wpnonce'] = wp_create_nonce( 'dianxiaomi_jsonp' );

		$body = ( new \Dianxiaomi_API_JSON_Handler() )->generate_response( array( 'value' => 'private response data' ) );

		$this->assertSame( 400, http_response_code() );
		$data = json_decode( $body, true, 512, JSON_THROW_ON_ERROR );
		$this->assertSame( 'dianxiaomi_api_invalid_jsonp_callback', $data[0]['code'] );
		$this->assertStringNotContainsString( 'private response data', $body );
	}

	public static function invalid_jsonp_callbacks(): array {
		return array(
			'assignment and comment' => array( 'globalThis.apiJsonInjected=1;//' ),
			'function invocation'    => array( 'alert(1);//' ),
			'bracket expression'     => array( 'client[alert(1)]' ),
			'HTML tags'              => array( '<b>callback</b>' ),
			'encoded whitespace'     => array( 'call%0aback' ),
			'trailing newline'       => array( "callback\n" ),
			'leading digit'          => array( '1callback' ),
			'empty property'         => array( 'client..callback' ),
			'empty string'           => array( '' ),
			'array parameter'        => array( array( 'callback' ) ),
		);
	}

	/**
	 * Allow simple and dotted JavaScript identifiers, including dollar signs.
	 */
	#[DataProvider( 'valid_jsonp_callbacks' )]
	public function test_jsonp_preserves_valid_callbacks_and_encoded_data( string $callback ): void {
		$_GET['_jsonp']   = $callback;
		$_GET['_wpnonce'] = wp_create_nonce( 'dianxiaomi_jsonp' );
		$payload          = array( 'value' => "Quotes \" ' & <tag> \\ </script><script>alert(1)</script> café 雪\n" );

		$body = ( new \Dianxiaomi_API_JSON_Handler() )->generate_response( $payload );

		$this->assertSame( 200, http_response_code() );
		$this->assertStringStartsWith( $callback . '(', $body );
		$this->assertStringEndsWith( ')', $body );
		$this->assertStringNotContainsString( '</script>', $body );
		$json = substr( $body, strlen( $callback ) + 1, -1 );
		$this->assertSame( $payload, json_decode( $json, true, 512, JSON_THROW_ON_ERROR ) );
	}

	public static function valid_jsonp_callbacks(): array {
		return array(
			'simple name' => array( 'callback' ),
			'dotted name' => array( 'client.receive' ),
			'JS symbols'  => array( '_client.$receive2' ),
		);
	}

	public function test_public_index_emits_decodable_json(): void {
		$this->set_option( 'blogname', 'API response fixture' );
		$body = $this->serve_response();
		$data = json_decode( $body, true, 512, JSON_THROW_ON_ERROR );

		$this->assertSame( 200, http_response_code() );
		$this->assertSame( 'API response fixture', $data['store']['name'] );
		$this->assertSame( WC()->version, $data['store']['wc_version'] );
		$this->assertSame( 0, get_current_user_id() );
	}

	public function test_response_preserves_special_characters_as_json_data(): void {
		$payload = array( 'value' => "Quotes \" ' & <tag> \\ </script><script>alert(1)</script> café 雪\n\t\0" );
		$this->add_filter( 'dianxiaomi_api_index', static fn () => $payload );

		$body = $this->serve_response();

		$this->assertSame( $payload, json_decode( $body, true, 512, JSON_THROW_ON_ERROR ) );
		$this->assertStringNotContainsString( '</script>', $body );
	}

	public function test_authentication_error_emits_decodable_json_and_status(): void {
		$body = $this->serve_response( '/orders' );
		$data = json_decode( $body, true, 512, JSON_THROW_ON_ERROR );

		$this->assertSame( 404, http_response_code() );
		$this->assertSame( 'dianxiaomi_api_authentication_error', $data['errors'][0]['code'] );
		$this->assertNotEmpty( $data['errors'][0]['message'] );
	}

	#[DataProvider( 'api_disabled_settings' )]
	public function test_disabled_api_emits_decodable_json_and_status( bool $use_filter ): void {
		$message = 'The API is "disabled" & unavailable.';
		$this->add_filter(
			'gettext_dianxiaomi',
			static fn ( $translation ) => 'The WooCommerce API is disabled on this site' === $translation ? $message : $translation
		);
		if ( $use_filter ) {
			$this->add_filter( 'dianxiaomi_api_enabled', static fn () => false );
		} else {
			$this->set_option( 'dianxiaomi_api_enabled', 'no' );
		}

		$body = $this->serve_response();
		$data = json_decode( $body, true, 512, JSON_THROW_ON_ERROR );

		$this->assertSame( 404, http_response_code() );
		$this->assertSame( 'dianxiaomi_api_disabled', $data['errors']['code'] );
		$this->assertSame( $message, $data['errors']['message'] );
	}

	public static function api_disabled_settings(): array {
		return array(
			'option' => array( false ),
			'filter' => array( true ),
		);
	}

	public function test_jsonp_response_preserves_the_callback_and_json_payload(): void {
		$payload = array( 'value' => '" & </script><script>alert(1)</script>' );
		$this->add_filter( 'dianxiaomi_api_index', static fn () => $payload );

		$body = $this->serve_response(
			'/',
			'GET',
			array(
				'_jsonp'   => 'client.receive',
				'_wpnonce' => wp_create_nonce( 'dianxiaomi_jsonp' ),
			)
		);

		$this->assertSame( 200, http_response_code() );
		$this->assertStringStartsWith( 'client.receive(', $body );
		$this->assertStringEndsWith( ')', $body );
		$this->assertStringNotContainsString( '</script>', $body );
		$json = substr( $body, strlen( 'client.receive(' ), -1 );
		$this->assertSame( $payload, json_decode( $json, true, 512, JSON_THROW_ON_ERROR ) );
	}

	#[DataProvider( 'jsonp_rejection_reasons' )]
	public function test_jsonp_rejections_emit_json_without_a_callback( string $reason, string $code ): void {
		$query = array(
			'_jsonp'   => 'client.receive',
			'_wpnonce' => wp_create_nonce( 'dianxiaomi_jsonp' ),
		);
		if ( 'nonce' === $reason ) {
			unset( $query['_wpnonce'] );
		} elseif ( 'disabled' === $reason ) {
			$this->add_filter( 'dianxiaomi_api_jsonp_enabled', static fn () => false );
		} else {
			$query['_jsonp'] = 'globalThis.apiJsonInjected=1;//';
		}

		$body = $this->serve_response( '/', 'GET', $query );
		$data = json_decode( $body, true, 512, JSON_THROW_ON_ERROR );

		$this->assertSame( 400, http_response_code() );
		$this->assertSame( $code, $data[0]['code'] );
		$this->assertStringNotContainsString( 'client.receive', $body );
		$this->assertStringNotContainsString( 'apiJsonInjected', $body );
	}

	public static function jsonp_rejection_reasons(): array {
		return array(
			'invalid nonce'    => array( 'nonce', 'dianxiaomi_api_nonce_invalid' ),
			'disabled JSONP'   => array( 'disabled', 'dianxiaomi_api_jsonp_disabled' ),
			'unsafe callback' => array( 'callback', 'dianxiaomi_api_invalid_jsonp_callback' ),
		);
	}

	#[DataProvider( 'head_routes' )]
	public function test_head_response_has_no_body( string $path, int $status ): void {
		$this->assertSame( '', $this->serve_response( $path, 'HEAD' ) );
		$this->assertSame( $status, http_response_code() );
	}

	public static function head_routes(): array {
		return array(
			'public index'         => array( '/', 200 ),
			'authentication error' => array( '/orders', 404 ),
		);
	}

	public function test_custom_serve_hook_is_not_encoded_twice(): void {
		$this->add_filter(
			'dianxiaomi_api_serve_request',
			static function () {
				echo '{"custom":"response"}';
				return true;
			}
		);

		$this->assertSame( array( 'custom' => 'response' ), json_decode( $this->serve_response(), true, 512, JSON_THROW_ON_ERROR ) );
	}

	private function set_option( string $name, mixed $value ): void {
		if ( ! array_key_exists( $name, $this->original_options ) ) {
			$this->original_options[ $name ] = get_option( $name, null );
		}
		update_option( $name, $value );
	}

	private function add_filter( string $hook, callable $callback ): void {
		$this->filters[] = array( $hook, $callback );
		add_filter( $hook, $callback );
	}

	/**
	 * Exercise real authentication, dispatch, encoding and output, restoring request state.
	 */
	private function serve_response( string $path = '/', string $method = 'GET', array $query = array() ): string {
		require_once dirname( __DIR__, 2 ) . '/api/class-dianxiaomi-api-server.php';
		require_once dirname( __DIR__, 2 ) . '/api/class-dianxiaomi-api-authentication.php';

		$api             = get_dianxiaomi_instance()->api;
		$original_server = $api->server ?? null;
		$request_state   = array( $_GET, $_POST, $_REQUEST, $_SERVER );
		$authentication  = new \Dianxiaomi_API_Authentication();

		try {
			$_GET                      = $query;
			$_POST                     = array();
			$_REQUEST                  = $query;
			$_SERVER['REQUEST_METHOD'] = $method;
			$api->server               = new \Dianxiaomi_API_Server( $path );
			ob_start();
			try {
				$api->server->serve_request();
				return (string) ob_get_contents();
			} finally {
				ob_end_clean();
			}
		} finally {
			remove_filter( 'dianxiaomi_api_check_authentication', array( $authentication, 'authenticate' ), 0 );
			list( $_GET, $_POST, $_REQUEST, $_SERVER ) = $request_state;
			if ( null === $original_server ) {
				unset( $api->server );
			} else {
				$api->server = $original_server;
			}
		}
	}
}
