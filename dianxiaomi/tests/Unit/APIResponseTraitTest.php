<?php
/**
 * API Response Trait Tests.
 *
 * @package Dianxiaomi\Tests\Unit
 */

declare(strict_types=1);

namespace Dianxiaomi\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Dianxiaomi\Traits\API_Response;
use WP_Error;

/**
 * Test class that uses the API_Response trait.
 */
class TestAPIResponseClass {
	use API_Response;

	/**
	 * Expose protected success method.
	 */
	public function test_success( array $data, int $status = 200 ): array {
		return $this->success( $data, $status );
	}

	/**
	 * Expose protected error method.
	 */
	public function test_error( string $message, string $code, int $status = 400 ): WP_Error {
		return $this->error( $message, $code, $status );
	}

	/**
	 * Expose protected not_found method.
	 */
	public function test_not_found( string $resource_type ): WP_Error {
		return $this->not_found( $resource_type );
	}

	/**
	 * Expose protected unauthorized method.
	 */
	public function test_unauthorized( string $message = '' ): WP_Error {
		return $this->unauthorized( $message );
	}

	/**
	 * Expose protected forbidden method.
	 */
	public function test_forbidden( string $message = '' ): WP_Error {
		return $this->forbidden( $message );
	}

	/**
	 * Expose protected validation_error method.
	 */
	public function test_validation_error( string $message, string $field = '' ): WP_Error {
		return $this->validation_error( $message, $field );
	}

	/**
	 * Expose protected server_error method.
	 */
	public function test_server_error( string $message = '' ): WP_Error {
		return $this->server_error( $message );
	}
}

/**
 * Test class for API_Response trait.
 */
class APIResponseTraitTest extends TestCase {

	/**
	 * Test class instance.
	 *
	 * @var TestAPIResponseClass
	 */
	private TestAPIResponseClass $api;

	/**
	 * Set up before each test.
	 */
	protected function setUp(): void {
		parent::setUp();
		\reset_all();
		$this->api = new TestAPIResponseClass();
	}

	/**
	 * Test success returns correct structure.
	 */
	public function test_success_returns_correct_structure(): void {
		$result = $this->api->test_success( array( 'id' => 1 ) );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'success', $result );
		$this->assertArrayHasKey( 'data', $result );
		$this->assertArrayHasKey( 'status', $result );
	}

	/**
	 * Test success returns true for success key.
	 */
	public function test_success_returns_true(): void {
		$result = $this->api->test_success( array() );

		$this->assertTrue( $result['success'] );
	}

	/**
	 * Test success returns data.
	 */
	public function test_success_returns_data(): void {
		$data   = array( 'id' => 123, 'name' => 'Test' );
		$result = $this->api->test_success( $data );

		$this->assertEquals( $data, $result['data'] );
	}

	/**
	 * Test success default status is 200.
	 */
	public function test_success_default_status_is_200(): void {
		$result = $this->api->test_success( array() );

		$this->assertEquals( 200, $result['status'] );
	}

	/**
	 * Test success custom status.
	 */
	public function test_success_custom_status(): void {
		$result = $this->api->test_success( array(), 201 );

		$this->assertEquals( 201, $result['status'] );
	}

	/**
	 * Test error returns WP_Error.
	 */
	public function test_error_returns_wp_error(): void {
		$result = $this->api->test_error( 'Test error', 'test_code' );

		$this->assertInstanceOf( WP_Error::class, $result );
	}

	/**
	 * Test error sets correct message.
	 */
	public function test_error_sets_message(): void {
		$result = $this->api->test_error( 'Test error message', 'test_code' );

		$this->assertEquals( 'Test error message', $result->get_error_message() );
	}

	/**
	 * Test error sets correct code.
	 */
	public function test_error_sets_code(): void {
		$result = $this->api->test_error( 'Test error', 'custom_error_code' );

		$this->assertEquals( 'custom_error_code', $result->get_error_code() );
	}

	/**
	 * Test error default status is 400.
	 */
	public function test_error_default_status_is_400(): void {
		$result = $this->api->test_error( 'Test error', 'test_code' );
		$data   = $result->get_error_data();

		$this->assertEquals( 400, $data['status'] );
	}

	/**
	 * Test error custom status.
	 */
	public function test_error_custom_status(): void {
		$result = $this->api->test_error( 'Test error', 'test_code', 500 );
		$data   = $result->get_error_data();

		$this->assertEquals( 500, $data['status'] );
	}

	/**
	 * Test not_found returns WP_Error.
	 */
	public function test_not_found_returns_wp_error(): void {
		$result = $this->api->test_not_found( 'Order' );

		$this->assertInstanceOf( WP_Error::class, $result );
	}

	/**
	 * Test not_found has 404 status.
	 */
	public function test_not_found_has_404_status(): void {
		$result = $this->api->test_not_found( 'Order' );
		$data   = $result->get_error_data();

		$this->assertEquals( 404, $data['status'] );
	}

	/**
	 * Test not_found includes resource type in message.
	 */
	public function test_not_found_includes_resource_type(): void {
		$result = $this->api->test_not_found( 'Order' );

		$this->assertStringContainsString( 'Order', $result->get_error_message() );
		$this->assertStringContainsString( 'not found', $result->get_error_message() );
	}

	/**
	 * Test not_found has correct code.
	 */
	public function test_not_found_has_correct_code(): void {
		$result = $this->api->test_not_found( 'Product' );

		$this->assertEquals( 'not_found', $result->get_error_code() );
	}

	/**
	 * Test unauthorized returns WP_Error.
	 */
	public function test_unauthorized_returns_wp_error(): void {
		$result = $this->api->test_unauthorized();

		$this->assertInstanceOf( WP_Error::class, $result );
	}

	/**
	 * Test unauthorized has 401 status.
	 */
	public function test_unauthorized_has_401_status(): void {
		$result = $this->api->test_unauthorized();
		$data   = $result->get_error_data();

		$this->assertEquals( 401, $data['status'] );
	}

	/**
	 * Test unauthorized has correct code.
	 */
	public function test_unauthorized_has_correct_code(): void {
		$result = $this->api->test_unauthorized();

		$this->assertEquals( 'unauthorized', $result->get_error_code() );
	}

	/**
	 * Test unauthorized uses default message.
	 */
	public function test_unauthorized_uses_default_message(): void {
		$result = $this->api->test_unauthorized();

		$this->assertStringContainsString( 'not authorized', $result->get_error_message() );
	}

	/**
	 * Test unauthorized uses custom message.
	 */
	public function test_unauthorized_uses_custom_message(): void {
		$result = $this->api->test_unauthorized( 'Custom auth error' );

		$this->assertEquals( 'Custom auth error', $result->get_error_message() );
	}

	/**
	 * Test forbidden returns WP_Error.
	 */
	public function test_forbidden_returns_wp_error(): void {
		$result = $this->api->test_forbidden();

		$this->assertInstanceOf( WP_Error::class, $result );
	}

	/**
	 * Test forbidden has 403 status.
	 */
	public function test_forbidden_has_403_status(): void {
		$result = $this->api->test_forbidden();
		$data   = $result->get_error_data();

		$this->assertEquals( 403, $data['status'] );
	}

	/**
	 * Test forbidden has correct code.
	 */
	public function test_forbidden_has_correct_code(): void {
		$result = $this->api->test_forbidden();

		$this->assertEquals( 'forbidden', $result->get_error_code() );
	}

	/**
	 * Test forbidden uses default message.
	 */
	public function test_forbidden_uses_default_message(): void {
		$result = $this->api->test_forbidden();

		$this->assertStringContainsString( 'permission', $result->get_error_message() );
	}

	/**
	 * Test forbidden uses custom message.
	 */
	public function test_forbidden_uses_custom_message(): void {
		$result = $this->api->test_forbidden( 'Access denied' );

		$this->assertEquals( 'Access denied', $result->get_error_message() );
	}

	/**
	 * Test validation_error returns WP_Error.
	 */
	public function test_validation_error_returns_wp_error(): void {
		$result = $this->api->test_validation_error( 'Invalid input' );

		$this->assertInstanceOf( WP_Error::class, $result );
	}

	/**
	 * Test validation_error has 422 status.
	 */
	public function test_validation_error_has_422_status(): void {
		$result = $this->api->test_validation_error( 'Invalid input' );
		$data   = $result->get_error_data();

		$this->assertEquals( 422, $data['status'] );
	}

	/**
	 * Test validation_error has correct code.
	 */
	public function test_validation_error_has_correct_code(): void {
		$result = $this->api->test_validation_error( 'Invalid input' );

		$this->assertEquals( 'validation_error', $result->get_error_code() );
	}

	/**
	 * Test validation_error includes message.
	 */
	public function test_validation_error_includes_message(): void {
		$result = $this->api->test_validation_error( 'Email is required' );

		$this->assertEquals( 'Email is required', $result->get_error_message() );
	}

	/**
	 * Test validation_error includes field when provided.
	 */
	public function test_validation_error_includes_field(): void {
		$result = $this->api->test_validation_error( 'Invalid email', 'email' );
		$data   = $result->get_error_data();

		$this->assertArrayHasKey( 'field', $data );
		$this->assertEquals( 'email', $data['field'] );
	}

	/**
	 * Test validation_error does not include field when not provided.
	 */
	public function test_validation_error_no_field_when_empty(): void {
		$result = $this->api->test_validation_error( 'Invalid input', '' );
		$data   = $result->get_error_data();

		$this->assertArrayNotHasKey( 'field', $data );
	}

	/**
	 * Test server_error returns WP_Error.
	 */
	public function test_server_error_returns_wp_error(): void {
		$result = $this->api->test_server_error();

		$this->assertInstanceOf( WP_Error::class, $result );
	}

	/**
	 * Test server_error has 500 status.
	 */
	public function test_server_error_has_500_status(): void {
		$result = $this->api->test_server_error();
		$data   = $result->get_error_data();

		$this->assertEquals( 500, $data['status'] );
	}

	/**
	 * Test server_error has correct code.
	 */
	public function test_server_error_has_correct_code(): void {
		$result = $this->api->test_server_error();

		$this->assertEquals( 'server_error', $result->get_error_code() );
	}

	/**
	 * Test server_error uses default message.
	 */
	public function test_server_error_uses_default_message(): void {
		$result = $this->api->test_server_error();

		$this->assertStringContainsString( 'internal server error', $result->get_error_message() );
	}

	/**
	 * Test server_error uses custom message.
	 */
	public function test_server_error_uses_custom_message(): void {
		$result = $this->api->test_server_error( 'Database connection failed' );

		$this->assertEquals( 'Database connection failed', $result->get_error_message() );
	}
}
