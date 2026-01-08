<?php
/**
 * API Response Trait.
 *
 * Provides standardized methods for API responses.
 *
 * @package Dianxiaomi\Traits
 */

declare(strict_types=1);

namespace Dianxiaomi\Traits;

use WP_Error;

/**
 * Trait for consistent API response handling.
 *
 * @since 1.41
 */
trait API_Response {
	/**
	 * Create a success response.
	 *
	 * @param array<string, mixed> $data   Response data.
	 * @param int                  $status HTTP status code.
	 *
	 * @return array{success: true, data: array<string, mixed>, status: int}
	 */
	protected function success( array $data, int $status = 200 ): array {
		return array(
			'success' => true,
			'data'    => $data,
			'status'  => $status,
		);
	}

	/**
	 * Create an error response.
	 *
	 * @param string $message Error message.
	 * @param string $code    Error code.
	 * @param int    $status  HTTP status code.
	 *
	 * @return WP_Error
	 */
	protected function error( string $message, string $code, int $status = 400 ): WP_Error {
		return new WP_Error( $code, $message, array( 'status' => $status ) );
	}

	/**
	 * Create a not found error response.
	 *
	 * @param string $resource_type Resource type that was not found.
	 *
	 * @return WP_Error
	 */
	protected function not_found( string $resource_type ): WP_Error {
		return $this->error(
			/* translators: %s: resource type */
			sprintf( __( '%s not found.', 'dianxiaomi' ), $resource_type ),
			'not_found',
			404
		);
	}

	/**
	 * Create an unauthorized error response.
	 *
	 * @param string $message Optional custom message.
	 *
	 * @return WP_Error
	 */
	protected function unauthorized( string $message = '' ): WP_Error {
		return $this->error(
			'' !== $message ? $message : __( 'You are not authorized to perform this action.', 'dianxiaomi' ),
			'unauthorized',
			401
		);
	}

	/**
	 * Create a forbidden error response.
	 *
	 * @param string $message Optional custom message.
	 *
	 * @return WP_Error
	 */
	protected function forbidden( string $message = '' ): WP_Error {
		return $this->error(
			'' !== $message ? $message : __( 'You do not have permission to perform this action.', 'dianxiaomi' ),
			'forbidden',
			403
		);
	}

	/**
	 * Create a validation error response.
	 *
	 * @param string $message Validation error message.
	 * @param string $field   Field that failed validation.
	 *
	 * @return WP_Error
	 */
	protected function validation_error( string $message, string $field = '' ): WP_Error {
		$data = array( 'status' => 422 );
		if ( $field ) {
			$data['field'] = $field;
		}
		return new WP_Error( 'validation_error', $message, $data );
	}

	/**
	 * Create a server error response.
	 *
	 * @param string $message Optional custom message.
	 *
	 * @return WP_Error
	 */
	protected function server_error( string $message = '' ): WP_Error {
		return $this->error(
			'' !== $message ? $message : __( 'An internal server error occurred.', 'dianxiaomi' ),
			'server_error',
			500
		);
	}
}
