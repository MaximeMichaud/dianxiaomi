<?php
/**
 * Dianxiaomi API Orders Class
 *
 * Handles requests to the /orders endpoint
 *
 * @author      Dianxiaomi
 * @category    API
 * @package     Dianxiaomi/API
 * @since       1.0
 */

if (!defined('ABSPATH')) exit; // Exit if accessed directly

class Dianxiaomi_API_Orders extends Dianxiaomi_API_Resource
{

	/** @var string $base the route base */
	protected $base = '/orders';

	/**
	 * Register the routes for this class
	 *
	 * GET /orders
	 * GET /orders/count
	 * GET|PUT /orders/<id>
	 * GET /orders/<id>/notes
	 *
	 * @since 2.1
	 * @param array $routes
	 * @return array
	 */
	public function register_routes($routes)
	{

		# GET /orders
		$routes[$this->base] = array(
			array(array($this, 'get_orders'), Dianxiaomi_API_Server::READABLE),
		);

		# GET /orders/count
		$routes[$this->base . '/count'] = array(
			array(array($this, 'get_orders_count'), Dianxiaomi_API_Server::READABLE),
		);

		# GET|PUT /orders/<id>
		$routes[$this->base . '/(?P<id>\d+)'] = array(
			array(array($this, 'get_order'), Dianxiaomi_API_Server::READABLE),
			array(array($this, 'edit_order'), Dianxiaomi_API_Server::EDITABLE | Dianxiaomi_API_Server::ACCEPT_DATA),
		);

		# POST /orders/<id>/ship
		$routes[$this->base . '/(?P<id>\d+)/ship'] = array(
			array(array($this, 'ship_order'), Dianxiaomi_API_Server::EDITABLE | Dianxiaomi_API_Server::ACCEPT_DATA),
		);
		# GET /orders/<id>/notes
		$routes[$this->base . '/(?P<id>\d+)/notes'] = array(
			array(array($this, 'get_order_notes'), Dianxiaomi_API_Server::READABLE),
		);

		# GET /orders/ping
		$routes[$this->base . '/ping'] = array(
			array(array($this, 'ping'), Dianxiaomi_API_Server::READABLE),
		);

		return $routes;
	}

	/**
	 * Get all orders
	 *
	 * @since 2.1
	 * @param string $fields
	 * @param array $filter
	 * @param string $status
	 * @param int $page
	 * @return array
	 */
	public function get_orders($fields = null, $filter = array(), $status = null, $page = 1, $updated_at_min = '')
	{

		if (!empty($status))
			$filter['status'] = $status;

		if (!empty($updated_at_min))
			$filter['updated_at_min'] = $updated_at_min;

		$filter['page'] = $page;

		$query = $this->query_orders($filter);

		$orders = array();

		foreach ($query->posts as $order_id) {

			if (!$this->is_readable($order_id))
				continue;

			$orders[] = current($this->get_order($order_id, $fields));
		}

		$this->server->add_pagination_headers($query);

		return array('orders' => $orders);
	}


	/**
	 * Get the order for the given ID
	 *
	 * @since 2.1
	 * @param int $id the order ID
	 * @param array $fields
	 * @return array
	 */
	public function get_order($id, $fields = null)
	{
		
		$id = $this->validate_request($id, 'shop_order', 'read');
	
		if (is_wp_error($id))
			return $id;
	
		// Utilisation de wc_get_order au lieu de new WC_Order et get_post
		$order = wc_get_order($id);
	
		if (!$order) {
			return new WP_Error('woocommerce_api_invalid_order', 'Invalid Order', array('status' => 404));
		}
	
		$order_data = array(
			'id' => $order->get_id(),
			'order_number' => $order->get_order_number(),
			'created_at' => $this->server->format_datetime($order->get_date_created()->date('Y-m-d H:i:s')),
			'updated_at' => $this->server->format_datetime($order->get_date_modified()->date('Y-m-d H:i:s')),
			'completed_at' => $order->get_date_completed() ? $this->server->format_datetime($order->get_date_completed()->date('Y-m-d H:i:s')) : '',
			'status' => $order->get_status(),
			'currency' => $order->get_currency(),
			'total' => wc_format_decimal($order->get_total(), 2),
			'subtotal' => wc_format_decimal($this->get_order_subtotal($order), 2),
			'total_line_items_quantity' => $order->get_item_count(),
			'total_tax' => wc_format_decimal($order->get_total_tax(), 2),
			'total_shipping' => wc_format_decimal($order->get_total_shipping(), 2),
			'shipping_tax' => wc_format_decimal($order->get_shipping_tax(), 2),
			'total_discount' => wc_format_decimal($order->get_total_discount(), 2),
			'shipping_methods' => $order->get_shipping_method(),
			'payment_details' => array(
				'method_id' => $order->get_payment_method(),
				'method_title' => $order->get_payment_method_title(),
				'paid' => $order->get_date_paid() ? $order->get_date_paid()->date('Y-m-d H:i:s') : null,
			),
			'billing_address' => array(
				'first_name' => $order->get_billing_first_name(),
				'last_name' => $order->get_billing_last_name(),
				'company' => $order->get_billing_company(),
				'address_1' => $order->get_billing_address_1(),
				'address_2' => $order->get_billing_address_2(),
				'city' => $order->get_billing_city(),
				'state' => $order->get_billing_state(),
				'postcode' => $order->get_billing_postcode(),
				'country' => $order->get_billing_country(),
				'email' => $order->get_billing_email(),
				'phone' => $order->get_billing_phone(),
			),
			'shipping_address' => array(
				'first_name' => $order->get_shipping_first_name(),
				'last_name' => $order->get_shipping_last_name(),
				'company' => $order->get_shipping_company(),
				'address_1' => $order->get_shipping_address_1(),
				'address_2' => $order->get_shipping_address_2(),
				'city' => $order->get_shipping_city(),
				'state' => $order->get_shipping_state(),
				'postcode' => $order->get_shipping_postcode(),
				'country' => $order->get_shipping_country(),
			),
			'note' => $order->get_customer_note(),
			'customer_ip' => $order->get_customer_ip_address(),
			'customer_id' => $order->get_customer_id(),  // Modification get_customer_user() 
			'view_order_url' => $order->get_view_order_url(),
			'line_items' => array(),
		);
	
		// Ajout des articles de la commande
		foreach ($order->get_items() as $item_id => $item) {
			$product = $item->get_product();
			$order_data['line_items'][] = array(
				'id' => $item_id,
				'subtotal' => wc_format_decimal($order->get_line_subtotal($item, false), 2),
				'total' => wc_format_decimal($order->get_line_total($item, false), 2),
				'total_tax' => wc_format_decimal($item->get_total_tax(), 2),
				'price' => wc_format_decimal($order->get_item_total($item, false, false), 2),
				'quantity' => $item->get_quantity(),
				'tax_class' => $item->get_tax_class(),
				'name' => $item->get_name(),
				'variation_id' => $item->get_variation_id(),
				'product_id' => $item->get_product_id(),
				'sku' => $product ? $product->get_sku() : null,
				'images' => $product ? $product->get_image() : null,
				'view_product_url' => $product ? get_permalink($product->get_id()) : null,
			);
		}
	
		// Gestion des métadonnées de suivi
		$tracking_data = array(
			'tracking_provider' => $order->get_meta('_dianxiaomi_tracking_provider'),
			'tracking_number' => $order->get_meta('_dianxiaomi_tracking_number'),
			'tracking_ship_date' => $order->get_meta('_dianxiaomi_tracking_shipdate'),
			'tracking_postal_code' => $order->get_meta('_dianxiaomi_tracking_postal'),
			'tracking_account_number' => $order->get_meta('_dianxiaomi_tracking_account'),
			'tracking_key' => $order->get_meta('_dianxiaomi_tracking_key'),
			'tracking_destination_country' => $order->get_meta('_dianxiaomi_tracking_destination_country'),
		);
		$order_data['trackings'][] = $tracking_data;
	
		
		return array('order' => apply_filters('dianxiaomi_api_order_response', $order_data, $order, $fields, $this->server));
	}
	/**
	 * Get the total number of orders
	 *
	 * @since 2.1
	 * @param string $status
	 * @param array $filter
	 * @return array
	 */
	public function get_orders_count($status = null, $filter = array())
	{

		if (!empty($status))
			$filter['status'] = $status;

		$query = $this->query_orders($filter);

		if (!current_user_can('read_private_shop_orders'))
			return new WP_Error('dianxiaomi_api_user_cannot_read_orders_count', __('You do not have permission to read the orders count', 'dianxiaomi'), array('status' => 401));

		return array('count' => (int)$query->found_posts);
	}

	/**
	 * Edit an order
	 *
	 * API v1 only allows updating the status of an order
	 *
	 * @since 2.1
	 * @param int $id the order ID
	 * @param array $data
	 * @return array
	 */
	public function edit_order($id, $data)
{
    $id = $this->validate_request($id, 'shop_order', 'edit');
    if (is_wp_error($id))
        return $id;

    $order = wc_get_order($id);
    if (!empty($data['status'])) {
        $order->update_status($data['status'], isset($data['note']) ? $data['note'] : '');
        $order->update_meta_data('_dianxiaomi_tracking_provider', $data['tracking_provider']);
        $order->update_meta_data('_dianxiaomi_tracking_number', $data['tracking_number']);
        $order->save();
    }

    return $this->get_order($id);
}

	/**
	 * Get the order for the given ID
	 *
	 * @since 2.1
	 * @param int $id the order ID
	 * @param array $fields
	 * @return array
	 */
	public function ship_order($id, $data)
{
    $id = $this->validate_request($id, 'shop_order', 'edit');

    if (is_wp_error($id))
        return $id;

    //  obtenir l'objet de commande
    $order = wc_get_order($id);

    if (!empty($data['status'])) {
        //  mettre à jour les métadonnées
        $order->update_meta_data('_dianxiaomi_tracking_number', $data['tracking_number']);
        $order->update_meta_data('_dianxiaomi_tracking_provider', $data['tracking_provider']);
        
        // Mettre à jour le statut de la commande
        $dianxiaomi_ignore_order_status = get_option('dianxiaomi_ignore_order_status', 'no');
        $order->update_status($dianxiaomi_ignore_order_status == 'yes' ? $order->get_status() : $data['status'], isset($data['note']) ? $data['note'] : '');

        // Sauvegardez les modifications 
        $order->save();
    }

    // Retournez les données de la commande mise à jour
    return $this->get_order($id);
}

	/**
	 * Delete an order
	 *
	 * @TODO enable along with POST in 2.2
	 * @param int $id the order ID
	 * @param bool $force true to permanently delete order, false to move to trash
	 * @return array
	 */
	public function delete_order($id, $force = false)
	{

		$id = $this->validate_request($id, 'shop_order', 'delete');

		return $this->delete($id, 'order', ('true' === $force));
	}

	/**
	 * Get the admin order notes for an order
	 *
	 * @since 2.1
	 * @param int $id the order ID
	 * @param string $fields fields to include in response
	 * @return array
	 */
	public function get_order_notes($id, $fields = null)
	{

		// ensure ID is valid order ID
		$id = $this->validate_request($id, 'shop_order', 'read');

		if (is_wp_error($id))
			return $id;

		$args = array(
			'post_id' => $id,
			'approve' => 'approve',
			'type' => 'order_note'
		);

		remove_filter('comments_clauses', array('WC_Comments', 'exclude_order_comments'), 10, 1);

		$notes = get_comments($args);

		add_filter('comments_clauses', array('WC_Comments', 'exclude_order_comments'), 10, 1);

		$order_notes = array();

		foreach ($notes as $note) {

			$order_notes[] = array(
				'id' => $note->comment_ID,
				'created_at' => $this->server->format_datetime($note->comment_date_gmt),
				'note' => $note->comment_content,
				'customer_note' => get_comment_meta($note->comment_ID, 'is_customer_note', true) ? true : false,
			);
		}

		return array('order_notes' => apply_filters('dianxiaomi_api_order_notes_response', $order_notes, $id, $fields, $notes, $this->server));
	}

	/**
	 * Helper method to get order post objects
	 *
	 * @since 2.1
	 * @param array $args request arguments for filtering query
	 * @return WP_Query
	 */
	private function query_orders($args)
	{

		function dianxiaomi_wpbo_get_woo_version_number()
		{
			// If get_plugins() isn't available, require it
			if (!function_exists('get_plugins'))
				require_once(ABSPATH . 'wp-admin/includes/plugin.php');

			// Create the plugins folder and file variables
			$plugin_folder = get_plugins('/' . 'woocommerce');
			$plugin_file = 'woocommerce.php';

			// If the plugin version number is set, return it
			if (isset($plugin_folder[$plugin_file]['Version'])) {
				return $plugin_folder[$plugin_file]['Version'];

			} else {
				// Otherwise return null
				return NULL;
			}
		}

		$woo_version = dianxiaomi_wpbo_get_woo_version_number();

		if ($woo_version >= 2.2) {
			// set base query arguments
			$query_args = array(
				'fields' => 'ids',
				'post_type' => 'shop_order',
				//			'post_status' => 'publish',
				'post_status' => array_keys(wc_get_order_statuses())
			);
		} else {
			// set base query arguments
			$query_args = array(
				'fields' => 'ids',
				'post_type' => 'shop_order',
				'post_status' => 'publish',
			);
		}

		// add status argument
		if (!empty($args['status'])) {

			$statuses = explode(',', $args['status']);

			$query_args['tax_query'] = array(
				array(
					'taxonomy' => 'shop_order_status',
					'field' => 'slug',
					'terms' => $statuses,
				),
			);

			unset($args['status']);
		}

		$query_args = $this->merge_query_args($query_args, $args);

        return new WP_Query($query_args);
	}

	/**
	 * Helper method to get the order subtotal
	 *
	 * @since 2.1
	 * @param WC_Order $order
	 * @return float
	 */
	private function get_order_subtotal($order)
	{

		$subtotal = 0;

		// subtotal
		foreach ($order->get_items() as $item) {

			$subtotal += (isset($item['line_subtotal'])) ? $item['line_subtotal'] : 0;
		}

		return $subtotal;
	}

	/**
	 * Get the total number of orders
	 *
	 * @since 2.1
	 * @param string $status
	 * @param array $filter
	 * @return array
	 */
	public function ping()
	{
		return "pong";
	}

}
