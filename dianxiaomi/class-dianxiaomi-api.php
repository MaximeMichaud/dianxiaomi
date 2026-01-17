<?php
/**
 * Dianxiaomi API.
 *
 * Handles Dianxiaomi-API endpoint requests
 *
 * @author      Dianxiaomi
 *
 * @category    API
 * @package     Dianxiaomi
 *
 * @since       1.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

/**
 * Dianxiaomi API class.
 *
 * @since 2.0
 */
#[AllowDynamicProperties]
class Dianxiaomi_API {
	/** This is the major version for the REST API and takes
	 * first-order position in endpoint URLs.
	 */
	public const VERSION = 1;

	/** @var Dianxiaomi_API_Server the REST API server */
	public Dianxiaomi_API_Server $server;

	/** @var Dianxiaomi_API_Authentication Authentication handler */
	public Dianxiaomi_API_Authentication $authentication;

	/**
	 * Setup class.
	 *
	 * @access public
	 *
	 * @since 2.0
	 */
	public function __construct() {
		// add query vars
		add_filter( 'query_vars', array( $this, 'add_query_vars' ), 0 );

		// register API endpoints
		add_action( 'init', array( $this, 'add_endpoint' ), 0 );

		// handle REST/legacy API request
		add_action( 'parse_request', array( $this, 'handle_api_requests' ), 0 );
	}

	/**
	 * Add query vars function.
	 *
	 * @access public
	 *
	 * @since 2.0
	 *
	 * @param array<int, string> $vars Query variables.
	 *
	 * @return array<int, string>
	 */
	public function add_query_vars( array $vars ): array {
		$vars[] = 'dianxiaomi-api';
		$vars[] = 'dianxiaomi-api-route';
		return $vars;
	}

	/**
	 * Add endpoint function.
	 *
	 * @access public
	 *
	 * @since 2.0
	 */
	public function add_endpoint(): void {
		// REST API
		add_rewrite_rule( '^dianxiaomi-api\/v' . self::VERSION . '/?$', 'index.php?dianxiaomi-api-route=/', 'top' );
		add_rewrite_rule( '^dianxiaomi-api\/v' . self::VERSION . '(.*)?', 'index.php?dianxiaomi-api-route=$matches[1]', 'top' );

		// legacy API for payment gateway IPNs
		add_rewrite_endpoint( 'dianxiaomi-api', EP_ALL );
	}

	/**
	 * Handle API requests.
	 *
	 * @access public
	 *
	 * @since 2.0
	 */
	public function handle_api_requests(): void {
		global $wp;

		if ( ! $wp instanceof WP ) {
			return;
		}

		// Get sanitized API parameter.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce checked below, sanitized on next line.
		$api_raw   = isset( $_GET['dianxiaomi-api'] ) ? wp_unslash( $_GET['dianxiaomi-api'] ) : '';
		$api_param = is_string( $api_raw ) ? sanitize_text_field( $api_raw ) : '';
		if ( ! empty( $api_param ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce checked below, sanitized on next line.
			$nonce_raw = isset( $_GET['_wpnonce'] ) ? wp_unslash( $_GET['_wpnonce'] ) : '';
			$nonce     = is_string( $nonce_raw ) ? sanitize_text_field( $nonce_raw ) : '';
			if ( ! wp_verify_nonce( $nonce, 'dianxiaomi_api_nonce' ) ) {
				wp_die( 'Nonce verification failed', 'Error', array( 'response' => 403 ) );
			}
			$wp->query_vars['dianxiaomi-api'] = $api_param;
		}

		// Get sanitized API route parameter.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Route parsing before authentication, sanitized on next line.
		$route_raw = isset( $_GET['dianxiaomi-api-route'] ) ? wp_unslash( $_GET['dianxiaomi-api-route'] ) : '';
		$api_route = is_string( $route_raw ) ? sanitize_text_field( $route_raw ) : '';
		if ( ! empty( $api_route ) ) {
			$wp->query_vars['dianxiaomi-api-route'] = $api_route;
		}

		// REST API request
		$rest_route = isset( $wp->query_vars['dianxiaomi-api-route'] ) && is_string( $wp->query_vars['dianxiaomi-api-route'] ) ? $wp->query_vars['dianxiaomi-api-route'] : '';
		if ( ! empty( $rest_route ) ) {
			define( 'AFTERSHIP_API_REQUEST', true );

			// load required files
			$this->includes();

			$this->server = new Dianxiaomi_API_Server( $rest_route );

			// load API resource classes
			$this->register_resources( $this->server );

			// Fire off the request
			$this->server->serve_request();

			exit;
		}

		// legacy API requests
		$legacy_api = isset( $wp->query_vars['dianxiaomi-api'] ) && is_string( $wp->query_vars['dianxiaomi-api'] ) ? $wp->query_vars['dianxiaomi-api'] : '';
		if ( ! empty( $legacy_api ) ) {
			// Buffer, we won't want any output here
			ob_start();

			// Get API trigger
			$api = strtolower( esc_attr( $legacy_api ) );

			// Load class if exists
			if ( class_exists( $api ) ) {
				$api_class = new $api();
			}

			// Trigger actions
			do_action( 'woocommerce_api_' . $api );

			// Done, clear buffer and exit
			ob_end_clean();
			die( '1' );
		}
	}

	/**
	 * Include required files for REST API request.
	 *
	 * @since 2.1
	 */
	private function includes(): void {
		// API server / response handlers
		include_once 'api/class-dianxiaomi-api-server.php';
		include_once 'api/interface-dianxiaomi-api-handler.php';
		include_once 'api/class-dianxiaomi-api-json-handler.php';

		// authentication
		include_once 'api/class-dianxiaomi-api-authentication.php';
		$this->authentication = new Dianxiaomi_API_Authentication();

		// Traits required by API Resource class (must be loaded before the class that uses them)
		include_once __DIR__ . '/inc/traits/trait-api-response.php';
		include_once __DIR__ . '/inc/traits/trait-woocommerce-helper.php';

		include_once 'api/class-dianxiaomi-api-resource.php';

		// self api
		include_once 'api/class-dianxiaomi-api-orders.php';

		// allow plugins to load other response handlers or resource classes
		do_action( 'woocommerce_api_loaded' );
	}

	/**
	 * Register available API resources.
	 *
	 * @since 2.1
	 *
	 * @param Dianxiaomi_API_Server $server the REST server
	 */
	public function register_resources( Dianxiaomi_API_Server $server ): void {
		/** @var array<int, string> $api_classes */
		$api_classes = apply_filters(
			'dianxiaomi_api_classes',
			array(
				'Dianxiaomi_API_Orders',
			)
		);

		foreach ( $api_classes as $api_class ) {
			if ( is_string( $api_class ) && class_exists( $api_class ) ) {
				$this->$api_class = new $api_class( $server );
			}
		}
	}
}
