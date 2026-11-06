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
	}

	protected function tear_down(): void {
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
}
