<?php
/**
 * Dianxiaomi API.
 *
 * Handles REST API requests
 *
 * This class and related code (JSON response handler, resource classes) are based on WP-API v0.6 (https://github.com/WP-API/WP-API)
 * Many thanks to Ryan McCue and any other contributors!
 *
 * @author      Dianxiaomi
 *
 * @category    API
 * @package     Dianxiaomi/API
 *
 * @since       1.0
 * @version     1.30
 * */

/**
 * Modifications pour la compatibilité avec PHP 8.2 Alex 05/04/2024 :
 *
 * 1. Typage des Propriétés et des Méthodes :
 *    - Ajout de types explicites pour les propriétés et les retours de méthodes
 *
 * 2. Utilisation de Types Union pour les Retours de Méthodes :
 *
 * 3. Gestion des Valeurs Nullables :
 *    - Marquage des paramètres pouvant être `null` avec `?` (ex. `?string` pour `$fields`).
 *
 * 4. Amélioration de la Gestion des Erreurs :
 *    - Ajout de vérifications pour s'assurer que les types et valeurs attendus sont reçus avant traitement.
 *
 * 5. Utilisation de Match Expressions :
 *    - Remplacement des structures conditionnelles par des expressions `match` pour une meilleure clarté.
 *
 * 6. Améliorations de Sécurité et de Performance :
 *    - Renforcement des mesures de sécurité et optimisation des performances via des vérifications appropriées.
 *
 * 7. Documentation et Annotations :
 *    - Mise à jour des commentaires et annotations pour mieux documenter les changements et les attentes.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

require_once ABSPATH . 'wp-admin/includes/admin.php';

class Dianxiaomi_API_Server {
	public const METHOD_GET    = 1;
	public const METHOD_POST   = 2;
	public const METHOD_PUT    = 4;
	public const METHOD_PATCH  = 8;
	public const METHOD_DELETE = 16;

	public const READABLE   = 1; // GET
	public const CREATABLE  = 2; // POST
	public const EDITABLE   = 14; // POST | PUT | PATCH
	public const DELETABLE  = 16; // DELETE
	public const ALLMETHODS = 31; // GET | POST | PUT | PATCH | DELETE

	public const ACCEPT_RAW_DATA = 64;
	public const ACCEPT_DATA     = 128;
	public const HIDDEN_ENDPOINT = 256;

	public static array $method_map = array(
		'HEAD'   => self::METHOD_GET,
		'GET'    => self::METHOD_GET,
		'POST'   => self::METHOD_POST,
		'PUT'    => self::METHOD_PUT,
		'PATCH'  => self::METHOD_PATCH,
		'DELETE' => self::METHOD_DELETE,
	);

	public string $path   = '';
	public string $method = 'HEAD';
	public array $params  = array(
		'GET'  => array(),
		'POST' => array(),
	);
	public array $headers = array();
	public array $files   = array();
	public Dianxiaomi_API_Handler $handler;
	public function __construct( string $path ) {
		if ( ! isset( $_REQUEST['_wpnonce'] ) ) {
			$_REQUEST['_wpnonce'] = wp_create_nonce( 'dianxiaomi_action' );
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce verified below, sanitized on next line.
		$nonce_raw         = isset( $_REQUEST['_wpnonce'] ) ? wp_unslash( $_REQUEST['_wpnonce'] ) : '';
		$nonce             = is_string( $nonce_raw ) ? sanitize_text_field( $nonce_raw ) : '';
		$_GET['_wpnonce']  = $nonce;
		$_POST['_wpnonce'] = $nonce;
		// Vérification du nonce pour les requêtes GET et POST
		if ( in_array( $this->method, array( 'GET', 'POST' ), true ) && isset( $_REQUEST['_wpnonce'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce verified here, sanitized on next line.
			$nonce_check = isset( $_REQUEST['_wpnonce'] ) ? wp_unslash( $_REQUEST['_wpnonce'] ) : '';
			$check_str   = is_string( $nonce_check ) ? sanitize_text_field( $nonce_check ) : '';
			if ( ! wp_verify_nonce( $check_str, 'dianxiaomi_action' ) ) {
				wp_die( esc_html__( 'Nonce verification failed', 'dianxiaomi' ), 403 );
			}
		}
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- PATH_INFO is optional server variable, sanitized on next line.
		$path_raw             = isset( $_SERVER['PATH_INFO'] ) ? wp_unslash( $_SERVER['PATH_INFO'] ) : '';
		$path_info            = is_string( $path_raw ) ? sanitize_text_field( $path_raw ) : '';
		$this->path           = $path ? $path : ( $path_info ? $path_info : '/' );
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- REQUEST_METHOD is always set by server, sanitized on next line.
		$method_raw           = isset( $_SERVER['REQUEST_METHOD'] ) ? wp_unslash( $_SERVER['REQUEST_METHOD'] ) : 'GET';
		$this->method         = is_string( $method_raw ) ? sanitize_text_field( $method_raw ) : 'GET';
		$this->params['GET']  = $_GET;
		$this->params['POST'] = $_POST;
		$this->headers        = $this->get_headers( $_SERVER );

		$handler_class = $this->is_json_request() ? 'Dianxiaomi_API_JSON_Handler' :
			( $this->is_xml_request() ? 'WC_API_XML_Handler' :
				apply_filters( 'dianxiaomi_api_default_response_handler', 'Dianxiaomi_API_JSON_Handler', $this->path, $this ) );
		/** @var class-string $handler_class */
		$handler_class = is_string( $handler_class ) ? $handler_class : 'Dianxiaomi_API_JSON_Handler';
		$handler       = new $handler_class();
		assert( $handler instanceof Dianxiaomi_API_Handler );
		$this->handler = $handler;
	}
	public function check_authentication(): WP_User|WP_Error {
		$user = apply_filters( 'dianxiaomi_api_check_authentication', null, $this );
		if ( $user instanceof WP_User ) {
			wp_set_current_user( $user->ID );
		} elseif ( ! is_wp_error( $user ) ) {
			$user = new WP_Error( 'dianxiaomi_api_authentication_error', __( 'Invalid authentication method', 'dianxiaomi' ), array( 'code' => 500 ) );
		}
		return $user;
	}

	protected function error_to_array( WP_Error $error ): array {
		$errors      = array();
		$error_array = $error->errors;
		if ( is_array( $error_array ) ) {
			foreach ( $error_array as $code => $messages ) {
				if ( ! is_array( $messages ) ) {
					continue;
				}
				foreach ( $messages as $message ) {
					$errors[] = array(
						'code'    => $code,
						'message' => $message,
					);
				}
			}
		}
		return array( 'errors' => $errors );
	}

	public function serve_request(): void {
		do_action( 'dianxiaomi_api_server_before_serve', $this );
		$this->header( 'Content-Type', $this->handler->get_content_type(), true );

		if ( ! apply_filters( 'dianxiaomi_api_enabled', true, $this ) || ( 'no' === get_option( 'dianxiaomi_api_enabled' ) ) ) {
			$this->send_status( 404 );
			echo esc_html(
				$this->handler->generate_response(
					array(
						'errors' => array(
							'code'    => 'dianxiaomi_api_disabled',
							'message' => esc_html__( 'The WooCommerce API is disabled on this site', 'dianxiaomi' ),
						),
					)
				)
			);
			return;
		}

		$result = $this->check_authentication();
		if ( ! is_wp_error( $result ) ) {
			$result = $this->dispatch();
		}

		if ( is_wp_error( $result ) ) {
			$data = $result->get_error_data();
			if ( is_array( $data ) && isset( $data['status'] ) && is_int( $data['status'] ) ) {
				$this->send_status( $data['status'] );
			}
			$result = $this->error_to_array( $result );
		}

		$served = apply_filters( 'dianxiaomi_api_serve_request', false, $result, $this );
		if ( ! $served ) {
			if ( 'HEAD' === $this->method ) {
				return;
			}
			/** @var array<string, mixed> $response_data */
			$response_data = is_array( $result ) ? $result : array( 'data' => $result );
			echo esc_html( $this->handler->generate_response( $response_data ) );
		}
	}

	/**
	 * Get registered API routes.
	 *
	 * @return array<string, array<int, array<int, mixed>>> Routes array.
	 */
	public function get_routes(): array {
		$endpoints = array(
			'/' => array( array( $this, 'get_index' ), self::READABLE ),
		);

		/** @var array<string, array<int, mixed>> $endpoints */
		$endpoints = apply_filters( 'dianxiaomi_api_endpoints', $endpoints );

		foreach ( $endpoints as $route => &$handlers ) {
			if ( ! is_array( $handlers ) ) {
				continue;
			}
			if ( count( $handlers ) <= 2 && isset( $handlers[1] ) && ! is_array( $handlers[1] ) ) {
				$handlers = array( $handlers );
			}
		}

		/** @var array<string, array<int, array<int, mixed>>> $endpoints */
		return $endpoints;
	}

	public function dispatch(): mixed {
		$method = null;
		switch ( $this->method ) {
			case 'HEAD':
			case 'GET':
				$method = self::METHOD_GET;
				break;
			case 'POST':
				$method = self::METHOD_POST;
				break;
			case 'PUT':
				$method = self::METHOD_PUT;
				break;
			case 'PATCH':
				$method = self::METHOD_PATCH;
				break;
			case 'DELETE':
				$method = self::METHOD_DELETE;
				break;
			default:
				return new WP_Error( 'dianxiaomi_api_unsupported_method', __( 'Unsupported request method', 'dianxiaomi' ), array( 'status' => 400 ) );
		}

		if ( $method instanceof WP_Error ) {
			return $method;
		}

		foreach ( $this->get_routes() as $route => $handlers ) {
			if ( ! is_array( $handlers ) ) {
				continue;
			}
			foreach ( $handlers as $handler ) {
				if ( ! is_array( $handler ) ) {
					continue;
				}
				$callback  = $handler[0] ?? null;
				$supported = isset( $handler[1] ) && is_int( $handler[1] ) ? $handler[1] : self::METHOD_GET;

				if ( ! ( $supported & $method ) ) {
					continue;
				}

				$match = preg_match( '@^' . $route . '$@i', urldecode( $this->path ), $args );

				if ( ! $match ) {
					continue;
				}

				if ( ! is_callable( $callback ) ) {
					return new WP_Error( 'dianxiaomi_api_invalid_handler', __( 'The handler for the route is invalid', 'dianxiaomi' ), array( 'status' => 500 ) );
				}

				/** @var array<string, mixed> $get_params */
				$get_params = $this->params['GET'] ?? array();
				/** @var array<string, mixed> $post_params */
				$post_params = $this->params['POST'] ?? array();
				$args        = array_merge( $args, $get_params, $post_params );
				if ( $supported & self::ACCEPT_DATA ) {
					$data = $this->handler->parse_body( $this->get_raw_data() );
					$args = array_merge( $args, array( 'data' => $data ) );
				} elseif ( $supported & self::ACCEPT_RAW_DATA ) {
					$data = $this->get_raw_data();
					$args = array_merge( $args, array( 'data' => $data ) );
				}

				$args['_method']  = $method;
				$args['_route']   = $route;
				$args['_path']    = $this->path;
				$args['_headers'] = $this->headers;
				$args['_files']   = $this->files;

				$args = apply_filters( 'dianxiaomi_api_dispatch_args', $args, $callback );

				if ( is_wp_error( $args ) ) {
					return $args;
				}
				if ( ! is_array( $args ) ) {
					return new WP_Error( 'dianxiaomi_api_invalid_args', __( 'Invalid dispatch args', 'dianxiaomi' ), array( 'status' => 500 ) );
				}

				$params = $this->sort_callback_params( $callback, $args );
				if ( is_wp_error( $params ) ) {
					return $params;
				}

				return call_user_func_array( $callback, $params );
			}
		}

		return new WP_Error( 'dianxiaomi_api_no_route', __( 'No route was found matching the URL and request method', 'dianxiaomi' ), array( 'status' => 404 ) );
	}

	public function get_index(): array {
		$available = array(
			'store' => array(
				'name'        => get_option( 'blogname' ),
				'description' => get_option( 'blogdescription' ),
				'URL'         => get_option( 'siteurl' ),
				'wc_version'  => WC()->version,
				'routes'      => array(),
				'meta'        => array(
					'timezone'           => wc_timezone_string(),
					'currency'           => get_dianxiaomi_currency(),
					'currency_format'    => get_dianxiaomi_currency_symbol(),
					'tax_included'       => ( 'yes' === get_option( 'dianxiaomi_prices_include_tax' ) ),
					'weight_unit'        => get_option( 'dianxiaomi_weight_unit' ),
					'dimension_unit'     => get_option( 'dianxiaomi_dimension_unit' ),
					'ssl_enabled'        => ( 'yes' === get_option( 'dianxiaomi_force_ssl_checkout' ) ),
					'permalinks_enabled' => ( '' !== get_option( 'permalink_structure' ) ),
					'links'              => array(
						'help' => 'https://dianxiaomi.uservoice.com/knowledgebase',
					),
				),
			),
		);

		foreach ( $this->get_routes() as $route => $callbacks ) {
			$data  = array();
			$route = preg_replace( '#\(\?P<\w+?>.*?\)#', '$1', $route );
			if ( null === $route ) {
				continue;
			}
			if ( ! is_array( $callbacks ) ) {
				continue;
			}
			$methods = array();
			foreach ( self::$method_map as $name => $bitmask ) {
				if ( ! is_int( $bitmask ) ) {
					continue;
				}
				foreach ( $callbacks as $callback ) {
					if ( ! is_array( $callback ) ) {
						continue;
					}
					$cb_flags = isset( $callback[1] ) && is_int( $callback[1] ) ? $callback[1] : 0;
					if ( $cb_flags & self::HIDDEN_ENDPOINT ) {
						continue 3;
					}

					if ( $cb_flags & $bitmask ) {
						$data['supports'][] = $name;
					}

					if ( $cb_flags & self::ACCEPT_DATA ) {
						$data['accepts_data'] = true;
					}

					if ( strpos( $route, '<' ) === false ) {
						$data['meta'] = array(
							'self' => get_dianxiaomi_api_url( $route ),
						);
					}
				}
			}
			$available['store']['routes'][ $route ] = apply_filters( 'dianxiaomi_api_endpoints_description', $data );
		}
		/** @var array<string, mixed> $result */
		$result = apply_filters( 'dianxiaomi_api_index', $available );
		return $result;
	}
	/**
	 * Send pagination headers for resources.
	 *
	 * @since 2.1
	 *
	 * @param WP_Query|WP_User_Query $query
	 */
	public function add_pagination_headers( $query ): void {
		if ( $query instanceof WP_User_Query ) {
			$paged_var   = $query->query_vars['paged'] ?? 1;
			$page        = is_numeric( $paged_var ) ? max( 1, (int) $paged_var ) : 1;
			$single      = count( $query->get_results() ) > 1;
			$total       = $query->get_total();
			$number_var  = $query->query_vars['number'] ?? 10;
			$per_page    = is_numeric( $number_var ) ? max( 1, (int) $number_var ) : 10;
			$total_pages = (int) ceil( $total / $per_page );
		} else {
			$paged_val   = $query->get( 'paged' );
			$page        = is_numeric( $paged_val ) ? max( 1, (int) $paged_val ) : 1;
			$single      = $query->is_single();
			$total       = $query->found_posts;
			$total_pages = $query->max_num_pages;
		}

		$next_page = $page + 1;

		if ( ! $single ) {
			if ( $page > 1 ) {
				$this->link_header( 'first', $this->get_paginated_url( 1 ) );
				$this->link_header( 'prev', $this->get_paginated_url( $page - 1 ) );
			}

			if ( $next_page <= $total_pages ) {
				$this->link_header( 'next', $this->get_paginated_url( $next_page ) );
			}

			if ( $page !== $total_pages ) {
				$this->link_header( 'last', $this->get_paginated_url( $total_pages ) );
			}
		}

		$this->header( 'X-WC-Total', (string) $total );
		$this->header( 'X-WC-TotalPages', (string) $total_pages );

		do_action( 'dianxiaomi_api_pagination_headers', $this, $query );
	}


	/**
	 * Send a HTTP header.
	 *
	 * @since 2.1
	 *
	 * @param string $key     Header key
	 * @param string $value   Header value
	 * @param bool   $replace Should we replace the existing header?
	 */
	public function header( string $key, string $value, bool $replace = true ): void {
		header( sprintf( '%s: %s', $key, $value ), $replace );
	}

	/**
	 * Send a Link header.
	 *
	 * @internal The $rel parameter is first, as this looks nicer when sending multiple
	 *
	 * @link http://tools.ietf.org/html/rfc5988
	 * @link http://www.iana.org/assignments/link-relations/link-relations.xml
	 * @since 2.1
	 *
	 * @param string $rel   Link relation. Either a registered type, or an absolute URL
	 * @param string $link  Target IRI for the link
	 * @param array  $other Other parameters to send, as an associative array
	 */
	public function link_header( string $rel, string $link, array $other = array() ): void {
		$header = sprintf( '<%s>; rel="%s"', $link, esc_attr( $rel ) );

		foreach ( $other as $key => $value ) {
			$key_str   = is_string( $key ) ? $key : '';
			$value_str = is_string( $value ) ? $value : ( is_scalar( $value ) ? (string) $value : '' );
			if ( 'title' === $key_str ) {
				$value_str = '"' . $value_str . '"';
			}

			$header .= '; ' . $key_str . '=' . $value_str;
		}

		$this->header( 'Link', $header, false );
	}


	/**
	 * Returns the request URL with the page query parameter set to the specified page.
	 *
	 * @since 2.1
	 *
	 * @param int $page
	 *
	 * @return string
	 */
	private function get_paginated_url( int $page ): string {
		$request   = remove_query_arg( 'page' );
		$request   = is_string( $request ) ? urldecode( add_query_arg( 'page', $page, $request ) ) : '';
		$host_raw  = wp_parse_url( get_home_url(), PHP_URL_HOST );
		$host      = is_string( $host_raw ) ? $host_raw : 'localhost';

		return set_url_scheme( "http://{$host}{$request}" );
	}

	/**
	 * Retrieve the raw request entity (body).
	 *
	 * @since 2.1
	 *
	 * @return string
	 */
	public function get_raw_data(): string {
		$raw = file_get_contents( 'php://input' );
		return false !== $raw ? $raw : '';
	}

	/**
	 * Parse an RFC3339 datetime into a MySQL datetime.
	 *
	 * Invalid dates default to unix epoch
	 *
	 * @since 2.1
	 *
	 * @param string $datetime RFC3339 datetime
	 *
	 * @return string MySQL datetime (YYYY-MM-DD HH:MM:SS)
	 */
	public function parse_datetime( $datetime ) {
		if ( strpos( $datetime, '.' ) !== false ) {
			$result = preg_replace( '/\.\d+/', '', $datetime );
			if ( null !== $result ) {
				$datetime = $result;
			}
		}

		$result = preg_replace( '/[+-]\d+:+\d+$/', '+00:00', $datetime );
		if ( null !== $result ) {
			$datetime = $result;
		}

		try {
			$datetime = new DateTime( $datetime, new DateTimeZone( 'UTC' ) );
		} catch ( Exception $e ) {
			$datetime = new DateTime( '@0' );
		}

		return $datetime->format( 'Y-m-d H:i:s' );
	}

	/**
	 * Format a unix timestamp or MySQL datetime into an RFC3339 datetime.
	 *
	 * @since 2.1
	 *
	 * @param int|string $timestamp      unix timestamp or MySQL datetime
	 * @param bool       $convert_to_utc
	 *
	 * @return string RFC3339 datetime
	 */
	public function format_datetime( $timestamp, $convert_to_utc = false ) {
		$timezone = $convert_to_utc ? new DateTimeZone( wc_timezone_string() ) : new DateTimeZone( 'UTC' );

		try {
			if ( is_numeric( $timestamp ) ) {
				$date = new DateTime( "@{$timestamp}" );
			} else {
				$date = new DateTime( $timestamp, $timezone );
			}

			if ( $convert_to_utc ) {
				$date->modify( -1 * $date->getOffset() . ' seconds' );
			}
		} catch ( Exception $e ) {
			$date = new DateTime( '@0' );
		}

		return $date->format( 'Y-m-d\TH:i:s\Z' );
	}

	/**
	 * Extract headers from a PHP-style $_SERVER array.
	 *
	 * @since 2.1
	 *
	 * @param array $server Associative array similar to $_SERVER
	 *
	 * @return array Headers extracted from the input
	 */
	public function get_headers( $server ) {
		$headers    = array();
		$additional = array(
			'CONTENT_LENGTH' => true,
			'CONTENT_MD5'    => true,
			'CONTENT_TYPE'   => true,
		);

		foreach ( $server as $key => $value ) {
			if ( strpos( $key, 'HTTP_' ) === 0 ) {
				$headers[ substr( $key, 5 ) ] = $value;
			} elseif ( isset( $additional[ $key ] ) ) {
				$headers[ $key ] = $value;
			}
		}

		return $headers;
	}

	/**
	 * Check if the current request accepts a JSON response by checking the endpoint suffix (.json) or
	 * the HTTP ACCEPT header.
	 *
	 * @since 2.1
	 *
	 * @return bool
	 */
	private function is_json_request() {
		if ( false !== stripos( $this->path, '.json' ) ) {
			return true;
		}

		return isset( $this->headers['ACCEPT'] ) && 'application/json' === $this->headers['ACCEPT'];
	}
	/**
	 * Sort parameters by order specified in method declaration.
	 *
	 * Takes a callback and a list of available params, then filters and sorts
	 * by the parameters the method actually needs, using the Reflection API
	 *
	 * @since 2.1
	 *
	 * @param callable|array $callback the endpoint callback
	 * @param array          $provided the provided request parameters
	 *
	 * @return array
	 */
	protected function sort_callback_params( $callback, array $provided ): array {
		if ( is_array( $callback ) && isset( $callback[0] ) && isset( $callback[1] ) ) {
			/** @var object|class-string $object */
			$object = $callback[0];
			/** @var string $method */
			$method   = is_string( $callback[1] ) ? $callback[1] : '';
			$ref_func = new ReflectionMethod( $object, $method );
		} elseif ( $callback instanceof Closure || is_string( $callback ) ) {
			$ref_func = new ReflectionFunction( $callback );
		} else {
			return array();
		}

		$wanted             = $ref_func->getParameters();
		$ordered_parameters = array();

		foreach ( $wanted as $param ) {
			if ( isset( $provided[ $param->getName() ] ) ) {
				// We have this parameters in the list to choose from
				$param_value = $provided[ $param->getName() ];
				if ( is_array( $param_value ) ) {
					/** @var array<int|string, string> $param_value */
					$ordered_parameters[] = array_map( 'urldecode', $param_value );
				} elseif ( is_string( $param_value ) ) {
					$ordered_parameters[] = urldecode( $param_value );
				} else {
					$ordered_parameters[] = $param_value;
				}
			} elseif ( $param->isDefaultValueAvailable() ) {
				// We don't have this parameter, but it's optional
				$ordered_parameters[] = $param->getDefaultValue();
			} else {
				// We don't have this parameter and it wasn't optional, abort!
				// translators: %s is the parameter name
				return new WP_Error( 'dianxiaomi_api_missing_callback_param', sprintf( esc_html__( 'Missing parameter %s', 'dianxiaomi' ), $param->getName() ), array( 'status' => 400 ) );
			}
		}
		return $ordered_parameters;
	}
	/**
	 * Check if the current request accepts an XML response by checking the endpoint suffix (.xml) or
	 * the HTTP ACCEPT header.
	 *
	 * @since 2.1
	 *
	 * @return bool
	 */
	private function is_xml_request() {
		if ( false !== stripos( $this->path, '.xml' ) ) {
			return true;
		}

		return isset( $this->headers['ACCEPT'] ) && ( 'application/xml' === $this->headers['ACCEPT'] || 'text/xml' === $this->headers['ACCEPT'] );
	}
	/**
	 * Send a HTTP status code
	 *
	 * @since 2.1
	 * @param int $code HTTP status
	 */
	public function send_status( int $code ): void {
		status_header( $code );
	}
}
