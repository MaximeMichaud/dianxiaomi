<?php
/**
 * Dianxiaomi API Authentication Class.
 *
 * @author      Dianxiaomi
 *
 * @category    API
 * @package     Dianxiaomi/API
 *
 * @since       1
 */

/** Alex 02/03/2024
 * Utilisation de tableaux courts :
 * Remplacement des array() par [] pour une syntaxe plus moderne et concise.
 * Opérateur null coalescent :
 * Utilisation de ?? pour gérer les cas o les indices de tableau ou les variables peuvent ne pas être définis.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

if ( ! function_exists( 'getallheaders' ) ) {
	function getallheaders() {
		$headers = array();
		foreach ( $_SERVER as $name => $value ) {
			if ( substr( $name, 0, 5 ) === 'HTTP_' ) {
				$header_key             = str_replace( ' ', '-', ucwords( strtolower( str_replace( '_', ' ', substr( $name, 5 ) ) ) ) );
				$headers[ $header_key ] = $value;
			}
		}
		return $headers;
	}
}

class Dianxiaomi_API_Authentication {
	public function __construct() {
		add_filter( 'dianxiaomi_api_check_authentication', array( $this, 'authenticate' ), 0 );
	}

	public function authenticate( $user ) {
		if ( '/' === getDianxiaomiInstance()->api->server->path ) {
			return new WP_User( 0 );
		}

		try {
			$user = $this->perform_authentication();
		} catch ( Exception $e ) {
			$user = new WP_Error( 'dianxiaomi_api_authentication_error', esc_html( $e->getMessage() ), array( 'status' => $e->getCode() ) );
		}

		return $user;
	}
	private function perform_authentication() {
		$headers = getallheaders();
		$headers = json_decode( wp_json_encode( $headers ), true );
		$key     = 'AFTERSHIP_WP_KEY';
		$key1    = str_replace( ' ', '-', ucwords( strtolower( str_replace( '_', ' ', $key ) ) ) );
		$key2    = 'DIANXIAOMI-WP-KEY';
		$qskey   = $_GET['key'] ?? null;  // Utilisation de l'opérateur null coalescent

		$api_key = $headers[$key] ?? $headers[$key1] ?? $headers[$key2] ?? $qskey;

		if ( empty( $api_key ) ) {
			throw new Exception( esc_html__( 'Dianxiaomi\'s WordPress Key is missing', 'dianxiaomi' ), 404 );
		}

		return $this->get_user_by_api_key( $api_key );
	}

	private function get_user_by_api_key( $api_key ) {
		$user_query = new WP_User_Query( array(
			'meta_key'   => 'dianxiaomi_wp_api_key',
			'meta_value' => $api_key,
		) );

		$users = $user_query->get_results();

		if ( empty( $users[0] ) ) {
			throw new Exception( esc_html__( 'Dianxiaomi\'s WordPress API Key is invalid', 'dianxiaomi' ), 401 );
		}

		return $users[0];
	}
}
