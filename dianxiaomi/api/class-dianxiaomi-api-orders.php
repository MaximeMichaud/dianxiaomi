<?php
/**
 * Dianxiaomi API Orders Class.
 *
 * Handles requests to the /orders endpoint
 *
 * @author      Dianxiaomi
 *
 * @category    API
 * @package     Dianxiaomi/API
 *
 * @since       1.10
 * @version     1.30
 */

/**
 * Alex 02/03/2024
 * 1. Déclarations de type : Ajout de déclarations de type strictes pour les paramètres et les valeurs de retour dans toutes les méthodes.
 * 2. Gestion des erreurs : Utilisation de l'objet WP_Error pour gérer les erreurs de manière cohérente à travers les méthodes.
 * Cela permet de retourner des messages d'erreur explicites et de gérer les cas d'erreur de manière uniforme.
 * 3. Compatibilité avec WooCommerce : Ajout d'une méthode pour récupérer la version de WooCommerce installée .
 * 4. Améliorations de la logique : Simplification et clarification de la logique dans les méthodes, notamment en utilisant l'opérateur de coalescence nulle (??) pour gérer les valeurs non définies et en optimisant les structures conditionnelles.
 * 5. Documentation : Mise à jour des commentaires et de la documentation inline pour refléter les changements.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly
/**
 * @since 1.10
 */
final class Dianxiaomi_API_Orders extends Dianxiaomi_API_Resource {
	/** @var string the route base */
	protected string $base = '/orders';

	// Compteur de requêtes
	private static int $request_count = 0;

	/**
	 * Register the routes for this class.
	 *
	 * GET /orders
	 * GET /orders/count
	 * GET|PUT /orders/<id>
	 * GET /orders/<id>/notes
	 *
	 * @since 2.1
	 *
	 * @param array<string, array<int, array<int, mixed>>> $routes Existing routes.
	 *
	 * @return array<string, array<int, array<int, mixed>>> Modified routes.
	 */
	public function register_routes( array $routes ): array {
		$routes[ $this->base ] = array(
			array( array( $this, 'get_orders' ), Dianxiaomi_API_Server::READABLE ),
		);

		$routes[ $this->base . '/count' ] = array(
			array( array( $this, 'get_orders_count' ), Dianxiaomi_API_Server::READABLE ),
		);

		$routes[ $this->base . '/(?P<id>\d+)' ] = array(
			array( array( $this, 'get_order' ), Dianxiaomi_API_Server::READABLE ),
			array( array( $this, 'edit_order' ), Dianxiaomi_API_Server::EDITABLE | Dianxiaomi_API_Server::ACCEPT_DATA ),
		);

		$routes[ $this->base . '/(?P<id>\d+)/ship' ] = array(
			array( array( $this, 'ship_order' ), Dianxiaomi_API_Server::EDITABLE | Dianxiaomi_API_Server::ACCEPT_DATA ),
		);

		$routes[ $this->base . '/(?P<id>\d+)/notes' ] = array(
			array( array( $this, 'get_order_notes' ), Dianxiaomi_API_Server::READABLE ),
		);

		$routes[ $this->base . '/ping' ] = array(
			array( array( $this, 'ping' ), Dianxiaomi_API_Server::READABLE ),
		);

		return $routes;
	}

	/**
	 * Get all orders.
	 *
	 * @since 2.1
	 *
	 * @param string|null          $fields         Fields to include.
	 * @param array<string, mixed> $filter         Query filters.
	 * @param string|null          $status         Order status filter.
	 * @param int                  $page           Page number.
	 * @param string               $updated_at_min Minimum update date.
	 *
	 * @return array<string, mixed> Orders data.
	 */
	public function get_orders( ?string $fields = null, array $filter = array(), ?string $status = null, int $page = 1, string $updated_at_min = '' ): array {
		if ( ! empty( $status ) ) {
			$filter['status'] = $status;
		}

		if ( ! empty( $updated_at_min ) ) {
			$filter['updated_at_min'] = $updated_at_min;
		}

		$filter['page'] = $page;

		$query = $this->query_orders( $filter );

		$orders = array();

		$fields_array = null !== $fields ? explode( ',', $fields ) : null;
		foreach ( $query->posts as $order_id ) {
			$order_id = $order_id instanceof WP_Post ? $order_id->ID : (int) $order_id;
			if ( ! $this->is_readable( $order_id ) ) {
				continue;
			}
			$order_result = $this->get_order( $order_id, $fields_array );
			if ( ! is_wp_error( $order_result ) ) {
				$orders[] = current( $order_result );
			}
		}

		$this->server->add_pagination_headers( $query );

		return array( 'orders' => $orders );
	}

	/**
	 * Get the order for the given ID.
	 *
	 * @since 2.1
	 *
	 * @param int                     $id     The order ID.
	 * @param array<int, string>|null $fields Fields to include.
	 *
	 * @return array<string, mixed>|WP_Error Order data or error.
	 */
	public function get_order( int $id, ?array $fields = null ): array|WP_Error {
		$id = $this->validate_request( $id, 'shop_order', 'read' );

		if ( is_wp_error( $id ) ) {
			return $id;
		}

		$order = wc_get_order( $id );

		if ( ! $order instanceof WC_Order ) {
			return $this->not_found( __( 'Order', 'dianxiaomi' ) );
		}

		$date_created   = $order->get_date_created();
		$date_modified  = $order->get_date_modified();
		$date_completed = $order->get_date_completed();
		$date_paid      = $order->get_date_paid();

		$order_data = array(
			'id'                        => $order->get_id(),
			'order_number'              => $order->get_order_number(),
			'created_at'                => $date_created ? $this->server->format_datetime( $date_created->date( 'Y-m-d H:i:s' ) ) : '',
			'updated_at'                => $date_modified ? $this->server->format_datetime( $date_modified->date( 'Y-m-d H:i:s' ) ) : '',
			'completed_at'              => $date_completed ? $this->server->format_datetime( $date_completed->date( 'Y-m-d H:i:s' ) ) : '',
			'status'                    => $order->get_status(),
			'currency'                  => $order->get_currency(),
			'total'                     => wc_format_decimal( $order->get_total(), 2 ),
			'subtotal'                  => wc_format_decimal( $this->get_order_subtotal( $order ), 2 ),
			'total_line_items_quantity' => $order->get_item_count(),
			'total_tax'                 => wc_format_decimal( $order->get_total_tax(), 2 ),
			'total_shipping'            => wc_format_decimal( $order->get_shipping_total(), 2 ),
			'shipping_tax'              => wc_format_decimal( $order->get_shipping_tax(), 2 ),
			'total_discount'            => wc_format_decimal( $order->get_total_discount(), 2 ),
			'shipping_methods'          => $order->get_shipping_method(),
			'payment_details'           => array(
				'method_id'    => $order->get_payment_method(),
				'method_title' => $order->get_payment_method_title(),
				'paid'         => $date_paid ? $date_paid->date( 'Y-m-d H:i:s' ) : null,
			),
			'billing_address'           => array(
				'first_name' => $order->get_billing_first_name(),
				'last_name'  => $order->get_billing_last_name(),
				'company'    => $order->get_billing_company(),
				'address_1'  => $order->get_billing_address_1(),
				'address_2'  => $order->get_billing_address_2(),
				'city'       => $order->get_billing_city(),
				'state'      => $order->get_billing_state(),
				'postcode'   => $order->get_billing_postcode(),
				'country'    => $order->get_billing_country(),
				'email'      => $order->get_billing_email(),
				'phone'      => $order->get_billing_phone(),
			),
			'shipping_address'          => array(
				'first_name' => $order->get_shipping_first_name(),
				'last_name'  => $order->get_shipping_last_name(),
				'company'    => $order->get_shipping_company(),
				'address_1'  => $order->get_shipping_address_1(),
				'address_2'  => $order->get_shipping_address_2(),
				'city'       => $order->get_shipping_city(),
				'state'      => $order->get_shipping_state(),
				'postcode'   => $order->get_shipping_postcode(),
				'country'    => $order->get_shipping_country(),
			),
			'note'                      => $order->get_customer_note(),
			'customer_ip'               => $order->get_customer_ip_address(),
			'customer_id'               => $order->get_customer_id(),
			'view_order_url'            => $order->get_view_order_url(),
			'line_items'                => array(),
		);

		// Ajout des articles de la commande
		foreach ( $order->get_items() as $item_id => $item ) {
			if ( ! $item instanceof WC_Order_Item_Product ) {
				continue;
			}
			$product      = $item->get_product();
			$has_product  = $product instanceof WC_Product;
			$order_data['line_items'][] = array(
				'id'               => $item_id,
				'subtotal'         => wc_format_decimal( $order->get_line_subtotal( $item, false ), 2 ),
				'total'            => wc_format_decimal( $order->get_line_total( $item, false ), 2 ),
				'total_tax'        => wc_format_decimal( $item->get_total_tax(), 2 ),
				'price'            => wc_format_decimal( $order->get_item_total( $item, false, false ), 2 ),
				'quantity'         => $item->get_quantity(),
				'tax_class'        => $item->get_tax_class(),
				'name'             => $item->get_name(),
				'variation_id'     => $item->get_variation_id(),
				'product_id'       => $item->get_product_id(),
				'sku'              => $has_product ? $product->get_sku() : null,
				'images'           => $has_product ? $product->get_image() : null,
				'view_product_url' => $has_product ? get_permalink( $product->get_id() ) : null,
			);
		}

		// Gestion des métadonnées de suivi
		$provider_meta = $order->get_meta( '_dianxiaomi_tracking_provider' );
		$number_meta   = $order->get_meta( '_dianxiaomi_tracking_number' );
		$ship_meta     = $order->get_meta( '_dianxiaomi_tracking_shipdate' );
		$postal_meta   = $order->get_meta( '_dianxiaomi_tracking_postal' );
		$account_meta  = $order->get_meta( '_dianxiaomi_tracking_account' );
		$key_meta      = $order->get_meta( '_dianxiaomi_tracking_key' );
		$country_meta  = $order->get_meta( '_dianxiaomi_tracking_destination_country' );

		$tracking_data             = array(
			'tracking_provider'            => is_string( $provider_meta ) ? $provider_meta : '',
			'tracking_number'              => is_string( $number_meta ) ? $number_meta : '',
			'tracking_ship_date'           => is_string( $ship_meta ) ? $ship_meta : '',
			'tracking_postal_code'         => is_string( $postal_meta ) ? $postal_meta : '',
			'tracking_account_number'      => is_string( $account_meta ) ? $account_meta : '',
			'tracking_key'                 => is_string( $key_meta ) ? $key_meta : '',
			'tracking_destination_country' => is_string( $country_meta ) ? $country_meta : '',
		);
		/** @var array<int, array<string, string>> $trackings */
		$trackings                 = array( $tracking_data );
		$order_data['trackings']   = $trackings;

		return array( 'order' => apply_filters( 'dianxiaomi_api_order_response', $order_data, $order, $fields, $this->server ) );
	}

	/**
	 * Edit an order.
	 *
	 * API v1 only allows updating the status of an order
	 *
	 * @since 2.1
	 *
	 * @param int                  $id   The order ID.
	 * @param array<string, mixed> $data Order data to update.
	 *
	 * @return array<string, mixed>|WP_Error Updated order data or error.
	 */
	public function edit_order( int $id, array $data ): array|WP_Error {
		$id = $this->validate_request( $id, 'shop_order', 'edit' );
		if ( is_wp_error( $id ) ) {
			return $id;
		}

		$order = wc_get_order( $id );

		if ( ! $order instanceof WC_Order ) {
			return $this->not_found( __( 'Order', 'dianxiaomi' ) );
		}

		if ( ! empty( $data['status'] ) ) {
			$status   = is_string( $data['status'] ) ? $data['status'] : '';
			$note     = isset( $data['note'] ) && is_string( $data['note'] ) ? $data['note'] : '';
			$provider = isset( $data['tracking_provider'] ) && is_string( $data['tracking_provider'] ) ? $data['tracking_provider'] : '';
			$number   = isset( $data['tracking_number'] ) && is_string( $data['tracking_number'] ) ? $data['tracking_number'] : '';
			$order->update_status( $status, $note );
			$order->update_meta_data( '_dianxiaomi_tracking_provider', $provider );
			$order->update_meta_data( '_dianxiaomi_tracking_number', $number );
			$order->save();

			// Incrémenter le compteur
			++self::$request_count;

			if ( self::$request_count >= 1500 ) {
				// Pause de 1 seconde
				sleep( 1 );
				// Réinitialiser le compteu
				self::$request_count = 0;
			}
		}

		return $this->get_order( $id );
	}
	/**
	 * Get the total number of orders.
	 *
	 * @since 2.1
	 *
	 * @param string|null          $status Optional status to filter the count.
	 * @param array<string, mixed> $filter Additional filters for the query.
	 *
	 * @return array<string, int>|WP_Error Returns the count of orders or a WP_Error object if permissions are insufficient.
	 */
	public function get_orders_count( ?string $status = null, array $filter = array() ): array|WP_Error {
		if ( ! empty( $status ) ) {
			$filter['status'] = $status;
		}
		$query = $this->query_orders( $filter );
		if ( ! current_user_can( 'read_private_shop_orders' ) ) {
			return $this->forbidden( __( 'You do not have permission to read the orders count', 'dianxiaomi' ) );
		}
		return array( 'count' => (int) $query->found_posts );
	}
	/**
	 * Ship an order.
	 *
	 * @since 2.1
	 *
	 * @param int                  $id   The order ID.
	 * @param array<string, mixed> $data Data containing shipping and order status information.
	 *
	 * @return array<string, mixed>|WP_Error Returns the updated order data or a WP_Error object if an error occurs.
	 */
	public function ship_order( int $id, array $data ): array|WP_Error {
		$validated_id = $this->validate_request( $id, 'shop_order', 'edit' );

		if ( is_wp_error( $validated_id ) ) {
			return $validated_id;
		}

		// Obtenir l'objet de commande
		$order = wc_get_order( $validated_id );

		if ( ! $order instanceof WC_Order ) {
			return $this->not_found( __( 'Order', 'dianxiaomi' ) );
		}

		// Mettre à jour les métadonnées si elles sont fournies
		if ( ! empty( $data['tracking_number'] ) && is_string( $data['tracking_number'] ) ) {
			$order->update_meta_data( '_dianxiaomi_tracking_number', $data['tracking_number'] );
		}
		if ( ! empty( $data['tracking_provider'] ) && is_string( $data['tracking_provider'] ) ) {
			$order->update_meta_data( '_dianxiaomi_tracking_provider', $data['tracking_provider'] );
		}

		// Vérifier si le statut de la commande doit être ignoré
		$ignore_status = get_option( 'dianxiaomi_ignore_order_status', 'no' );

		// Mettre à jour le statut de la commande si nécessaire
		if ( ! empty( $data['status'] ) && is_string( $data['status'] ) && $ignore_status !== 'yes' ) {
			$note = isset( $data['note'] ) && is_string( $data['note'] ) ? $data['note'] : '';
			$order->set_status( $data['status'], $note );
		}

		// Sauvegarder les modifications
		$order->save();

		// Retourner les données de la commande mise à jour
		return $this->get_order( $validated_id );
	}
	/**
	 * Delete an order.
	 *
	 * @TODO enable along with POST in 2.2
	 *
	 * @param int  $id    The order ID.
	 * @param bool $force True to permanently delete order, false to move to trash.
	 *
	 * @return array<string, string>|WP_Error Returns the result of the deletion or a WP_Error object if an error occurs.
	 */
	public function delete_order( int $id, bool $force = false ): array|WP_Error {
		$validated_id = $this->validate_request( $id, 'shop_order', 'delete' );

		if ( is_wp_error( $validated_id ) ) {
			return $validated_id;
		}

		$result = $this->delete( $validated_id, 'order', $force );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		/** @var array<string, string> $result */
		return $result;
	}

	/**
	 * Get order notes.
	 *
	 * @since 2.1
	 *
	 * @param int $id The order ID.
	 *
	 * @return array<string, array<int, array<string, mixed>>> Order notes data.
	 */
	public function get_order_notes( int $id ): array {
		$args = array(
			'post_id' => $id,
			'approve' => 'approve',
			'type'    => 'order_note',
		);

		$notes       = get_comments( $args );
		$order_notes = array();

		if ( ! is_array( $notes ) ) {
			return array( 'order_notes' => array() );
		}

		foreach ( $notes as $note ) {
			if ( ! $note instanceof WP_Comment ) {
				continue;
			}
			$comment_id    = (int) $note->comment_ID;
			$order_notes[] = array(
				'id'            => $comment_id,
				'created_at'    => $this->server->format_datetime( $note->comment_date_gmt ),
				'note'          => $note->comment_content,
				'customer_note' => (bool) get_comment_meta( $comment_id, 'is_customer_note', true ),
			);
		}

		/** @var array<int, array<string, mixed>> $filtered_notes */
		$filtered_notes = apply_filters( 'dianxiaomi_api_order_notes_response', $order_notes, $id, $this->server );
		return array( 'order_notes' => $filtered_notes );
	}

	/**
	 * Helper method to get order post objects.
	 *
	 * @since 2.1
	 *
	 * @param array<string, mixed> $args Request arguments for filtering query.
	 *
	 * @return WP_Query
	 */
	private function query_orders( array $args ): WP_Query {
		$woo_version = $this->get_woocommerce_version();

		$query_args = array(
			'fields'      => 'ids',
			'post_type'   => 'shop_order',
			'post_status' => version_compare( $woo_version, '2.2', '>=' ) ? array_keys( wc_get_order_statuses() ) : 'publish',
		);

		if ( ! empty( $args['status'] ) && is_string( $args['status'] ) ) {
			$statuses                  = explode( ',', $args['status'] );
			$query_args['post_status'] = array_map(
				function ( $status ) {
					return 'wc-' . $status;
				},
				$statuses
			);
			unset( $args['status'] );
		}

		$query_args = array_merge( $query_args, $args );

		return new WP_Query( $query_args );
	}

	/**
	 * Helper method to get the order subtotal.
	 *
	 * @since 2.1
	 *
	 * @param WC_Order $order
	 *
	 * @return float
	 */
	private function get_order_subtotal( WC_Order $order ): float {
		$subtotal = 0.0;
		foreach ( $order->get_items() as $item ) {
			if ( $item instanceof WC_Order_Item_Product ) {
				$subtotal += (float) $item->get_subtotal();
			}
		}
		return $subtotal;
	}

	/**
	 * Ping method to check API status.
	 *
	 * @return array<string, string> Status response.
	 */
	public function ping(): array {
		return array(
			'status' => 'success',
			'message' => 'pong',
		);
	}
}
