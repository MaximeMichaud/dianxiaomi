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
class Dianxiaomi_API_Authentication {

	public function __construct() {
		add_filter( 'dianxiaomi_api_check_authentication', array( $this, 'authenticate' ), 0 );
	}

	public function authenticate( $user ) {
		if ( '/' === get_dianxiaomi_instance()->api->server->path ) {
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
		$qskey   = isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : null;  // Sanitize input
		// Vérification de la clé API
		$api_key = $headers[ $key ] ?? $headers[ $key1 ] ?? $headers[ $key2 ] ?? $qskey;
		if ( empty( $api_key ) ) {
			throw new Exception( esc_html__( 'Dianxiaomi\'s WordPress Key is missing', 'dianxiaomi' ), 404 );
		}
		// Valider la clé API et obtenir l'utilisateur
		$user = $this->get_user_by_api_key( $api_key );
		// Génération du nonce si la clé API est valide
		$nonce = wp_create_nonce( 'dianxiaomi_action' );
		// Ajouter le nonce à la requête
		$_GET['_wpnonce']     = $nonce;
		$_POST['_wpnonce']    = $nonce;
		$_REQUEST['_wpnonce'] = $nonce;
		// Vérification du nonce
		$wpnonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : (isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '');
		if ( ! wp_verify_nonce( $wpnonce, 'dianxiaomi_action' ) ) {
			throw new Exception( esc_html__( 'Nonce verification failed', 'dianxiaomi' ), 403 );
		}
		return $user;
	}

	private function get_user_by_api_key( $api_key ) {
		global $wpdb;
		$cache_key = 'dianxiaomi_user_' . md5( $api_key );
		$user_id   = wp_cache_get( $cache_key );
		if ( false === $user_id ) {
			$user_id = get_users(
				array(
					'meta_key'   => 'dianxiaomi_wp_api_key',
					'meta_value' => $api_key,
					'number'     => 1,
					'fields'     => 'ID',
				)
			);
			$user_id = ! empty( $user_id ) ? $user_id[0] : false;
			if ( $user_id ) {
				wp_cache_set( $cache_key, $user_id, '', 3600 );
			}
		}
		if ( ! $user_id ) {
			throw new Exception( esc_html__( 'Dianxiaomi\'s WordPress API Key is invalid', 'dianxiaomi' ), 401 );
		}
		return new WP_User( $user_id );
	}
}
