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
 * Sécurité améliorée pour JSONP : Utilisation de htmlspecialchars pour nettoyer la sortie JSONP et éviter les attaques XSS.
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
class Dianxiaomi_API_JSON_Handler implements Dianxiaomi_API_Handler {
	/**
	 * Get the content type for the response.
	 *
	 * @since 2.1
	 *
	 * @return string
	 */
	public function get_content_type(): string {
		return 'application/json; charset=' . get_option( 'blog_charset' );
	}

	/**
	 * Parses the JSON body.
	 *
	 * @since 2.1
	 *
	 * @param string $data JSON string to be parsed.
	 *
	 * @return array<string, mixed> Parsed data as an associative array.
	 */
	public function parse_body( string $data ): array {
		$decoded = json_decode( $data, true );
		return is_array( $decoded ) ? $decoded : array();
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
			$wpnonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
			if ( ! $wpnonce || ! wp_verify_nonce( $wpnonce, 'dianxiaomi_jsonp' ) ) {
				WC()->api->server->send_status( 400 );
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
				WC()->api->server->send_status( 400 );
				$data = array(
					array(
						'code'    => 'dianxiaomi_api_jsonp_disabled',
						'message' => __( 'JSONP support is disabled on this site', 'dianxiaomi' ),
					),
				);
				return $this->encode_json( $data );
			}
			$jsonp_callback = sanitize_text_field( wp_unslash( $_GET['_jsonp'] ) );
			return htmlspecialchars( $jsonp_callback ) . '(' . $this->encode_json( $data ) . ')';
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
