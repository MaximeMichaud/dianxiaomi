<?php
/**
 * Dianxiaomi API.
 *
 * Handles parsing JSON request bodies and generating JSON responses
 *
 * @author      Dianxiaomi
 *
 * @category    API
 * @package     Dianxiaomi/API
 *
 * @since       1.0
 * @version     1.30
 */

/**
 * Alex 02/03/2024
 * Déclarations de type : Ajout de déclarations de type pour les paramètres et les valeurs de retour pour améliorer la robustesse et la prévisibilité du code.
 * JSONP callbacks are validated as JavaScript identifiers before being emitted.
 * Gestion des erreurs : Amélioration de la gestion des erreurs pour les réponses JSONP.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

/**
 * Dianxiaomi API JSON Handler.
 *
 * @since 1.0
 */
final class Dianxiaomi_API_JSON_Handler implements Dianxiaomi_API_Handler {
	/**
	 * Get the content type for the response.
	 *
	 * @since 2.1
	 *
	 * @return string
	 */
	public function get_content_type(): string {
		$charset = get_option( 'blog_charset' );
		return 'application/json; charset=' . ( is_string( $charset ) ? $charset : 'UTF-8' );
	}

	/**
	 * Parses the JSON body.
	 *
	 * @since 2.1
	 *
	 * @param string $data JSON string to be parsed.
	 *
	 * @return array<string, mixed>|WP_Error Parsed data, or an error for a non-empty invalid body.
	 */
	public function parse_body( string $data ): array|WP_Error {
		// An empty body is a no-op update. A truncated body must not look like one.
		if ( '' === trim( $data ) ) {
			return array();
		}
		$decoded = json_decode( $data, true );
		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $decoded ) ) {
			return new WP_Error( 'dianxiaomi_api_invalid_json', __( 'Invalid JSON', 'dianxiaomi' ), array( 'status' => 400 ) );
		}
		/** @var array<string, mixed> $decoded */
		return $decoded;
	}

	/**
	 * Generate a JSON response given an array of data.
	 *
	 * @since 2.1
	 *
	 * @param array<string, mixed> $data The response data.
	 *
	 * @return string JSON encoded response.
	 */
	public function generate_response( array $data ): string {
		if ( isset( $_GET['_jsonp'] ) ) {
			// Vérification du nonce
			$wpnonce = isset( $_GET['_wpnonce'] ) && is_string( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
			if ( ! $wpnonce || ! wp_verify_nonce( $wpnonce, 'dianxiaomi_jsonp' ) ) {
				status_header( 400 );
				$data = array(
					array(
						'code'    => 'dianxiaomi_api_nonce_invalid',
						'message' => __( 'Nonce verification failed', 'dianxiaomi' ),
					),
				);
				return $this->encode_json( $data );
			}

			// JSONP enabled by default
			if ( ! apply_filters( 'dianxiaomi_api_jsonp_enabled', true ) ) {
				status_header( 400 );
				$data = array(
					array(
						'code'    => 'dianxiaomi_api_jsonp_disabled',
						'message' => __( 'JSONP support is disabled on this site', 'dianxiaomi' ),
					),
				);
				return $this->encode_json( $data );
			}
			// Validate the original name; HTML sanitization cannot make JavaScript safe.
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Validated against an identifier-only grammar below.
			$jsonp_callback = is_string( $_GET['_jsonp'] ) ? wp_unslash( $_GET['_jsonp'] ) : '';
			if ( 1 !== preg_match( '/\A[a-zA-Z_$][a-zA-Z0-9_$]*(?:\.[a-zA-Z_$][a-zA-Z0-9_$]*)*\z/', $jsonp_callback ) ) {
				status_header( 400 );
				return $this->encode_json(
					array(
						array(
							'code'    => 'dianxiaomi_api_invalid_jsonp_callback',
							'message' => __( 'Invalid JSONP callback', 'dianxiaomi' ),
						),
					)
				);
			}
			return $jsonp_callback . '(' . $this->encode_json( $data ) . ')';
		}

		return $this->encode_json( $data );
	}

	/**
	 * Encode data as JSON with fallback to empty object.
	 *
	 * @since 1.40
	 *
	 * @param array<int|string, mixed> $data Data to encode.
	 *
	 * @return string JSON string.
	 */
	private function encode_json( array $data ): string {
		$json = wp_json_encode( $data );
		return false !== $json ? $json : '{}';
	}
}
