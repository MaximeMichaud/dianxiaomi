<?php
/**
 * Dianxiaomi API Orders Class
 *
 * Handles requests to the /orders endpoint
 *
 * @author      Dianxiaomi
 * @category    API
 * @package     Dianxiaomi/API
 * @since       1.10
 */
/**
 * Alex 02/03/2024
 * 1. Déclarations de type : Ajout de déclarations de type strictes pour les paramètres et les valeurs de retour dans toutes les méthodes. 
 * 2. Gestion des erreurs : Utilisation de l'objet WP_Error pour gérer les erreurs de manière cohérente à travers les méthodes. 
 * Cela permet de retourner des messages d'erreur explicites et de gérer les cas d'erreur de manière uniforme.
 * 3. Compatibilité avec WooCommerce : Ajout d'une méthode pour récupérer la version de WooCommerce installée .
 * 4. Améliorations de la logique : Simplification et clarification de la logique dans les méthodes, notamment en utilisant l'opérateur de coalescence nulle (??) pour gérer les valeurs non définies et en optimisant les structures conditionnelles.
 * 5. Documentation : Mise à jour des commentaires et de la documentation inline pour refléter les changements
 * 
 */

if (!defined('ABSPATH')) exit; // Exit if accessed directly

class Dianxiaomi_API_Orders extends Dianxiaomi_API_Resource
{
    /** @var string $base the route base */
    protected string $base = '/orders';

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
    public function register_routes(array $routes): array
    {
        $routes[$this->base] = [
            [[$this, 'get_orders'], Dianxiaomi_API_Server::READABLE],
        ];

        $routes[$this->base . '/count'] = [
            [[$this, 'get_orders_count'], Dianxiaomi_API_Server::READABLE],
        ];

        $routes[$this->base . '/(?P<id>\d+)'] = [
            [[$this, 'get_order'], Dianxiaomi_API_Server::READABLE],
            [[$this, 'edit_order'], Dianxiaomi_API_Server::EDITABLE | Dianxiaomi_API_Server::ACCEPT_DATA],
        ];

        $routes[$this->base . '/(?P<id>\d+)/ship'] = [
            [[$this, 'ship_order'], Dianxiaomi_API_Server::EDITABLE | Dianxiaomi_API_Server::ACCEPT_DATA],
        ];

        $routes[$this->base . '/(?P<id>\d+)/notes'] = [
            [[$this, 'get_order_notes'], Dianxiaomi_API_Server::READABLE],
        ];

        $routes[$this->base . '/ping'] = [
            [[$this, 'ping'], Dianxiaomi_API_Server::READABLE],
        ];

        return $routes;
    }

    /**
     * Get all orders
     *
     * @since 2.1
     * @param string|null $fields
     * @param array $filter
     * @param string|null $status
     * @param int $page
     * @param string $updated_at_min
     * @return array
     */
    public function get_orders(?string $fields = null, array $filter = [], ?string $status = null, int $page = 1, string $updated_at_min = ''): array
    {
        if (!empty($status)) {
            $filter['status'] = $status;
        }

        if (!empty($updated_at_min)) {
            $filter['updated_at_min'] = $updated_at_min;
        }

        $filter['page'] = $page;

        $query = $this->query_orders($filter);

        $orders = [];

        foreach ($query->posts as $order_id) {
            if (!$this->is_readable($order_id)) {
                continue;
            }
            $orders[] = current($this->get_order($order_id, $fields));
        }

        $this->server->add_pagination_headers($query);

        return ['orders' => $orders];
    }

    /**
     * Get the order for the given ID
     *
     * @since 2.1
     * @param int $id the order ID
     * @param array|null $fields
     * @return array
     */
    public function get_order(int $id, ?array $fields = null): array
    {
        $id = $this->validate_request($id, 'shop_order', 'read');

        if (is_wp_error($id)) {
            return $id;
        }

        $order = wc_get_order($id);

        if (!$order) {
            return new WP_Error('woocommerce_api_invalid_order', 'Invalid Order', ['status' => 404]);
        }

        $order_data = [
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
            'payment_details' => [
                'method_id' => $order->get_payment_method(),
                'method_title' => $order->get_payment_method_title(),
                'paid' => $order->get_date_paid() ? $order->get_date_paid()->date('Y-m-d H:i:s') : null,
            ],
            'billing_address' => [
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
            ],
            'shipping_address' => [
                'first_name' => $order->get_shipping_first_name(),
                'last_name' => $order->get_shipping_last_name(),
                'company' => $order->get_shipping_company(),
                'address_1' => $order->get_shipping_address_1(),
                'address_2' => $order->get_shipping_address_2(),
                'city' => $order->get_shipping_city(),
                'state' => $order->get_shipping_state(),
                'postcode' => $order->get_shipping_postcode(),
                'country' => $order->get_shipping_country(),
            ],
            'note' => $order->get_customer_note(),
            'customer_ip' => $order->get_customer_ip_address(),
            'customer_id' => $order->get_customer_id(),
            'view_order_url' => $order->get_view_order_url(),
            'line_items' => [],
        ];

        // Ajout des articles de la commande
        foreach ($order->get_items() as $item_id => $item) {
            $product = $item->get_product();
            $order_data['line_items'][] = [
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
            ];
        }

        // Gestion des métadonnées de suivi
        $tracking_data = [
            'tracking_provider' => $order->get_meta('_dianxiaomi_tracking_provider'),
            'tracking_number' => $order->get_meta('_dianxiaomi_tracking_number'),
            'tracking_ship_date' => $order->get_meta('_dianxiaomi_tracking_shipdate'),
            'tracking_postal_code' => $order->get_meta('_dianxiaomi_tracking_postal'),
            'tracking_account_number' => $order->get_meta('_dianxiaomi_tracking_account'),
            'tracking_key' => $order->get_meta('_dianxiaomi_tracking_key'),
            'tracking_destination_country' => $order->get_meta('_dianxiaomi_tracking_destination_country'),
        ];
        $order_data['trackings'][] = $tracking_data;

        return ['order' => apply_filters('dianxiaomi_api_order_response', $order_data, $order, $fields, $this->server)];
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
    public function edit_order(int $id, array $data): array
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
 * Get the total number of orders
 *
 * @since 2.1
 * @param string|null $status Optional status to filter the count.
 * @param array $filter Additional filters for the query.
 * @return array|WP_Error Returns the count of orders or a WP_Error object if permissions are insufficient.
 */
public function get_orders_count(?string $status = null, array $filter = []): array|WP_Error
{
    if (!empty($status)) {
        $filter['status'] = $status;
    }

    $query = $this->query_orders($filter);

    if (!current_user_can('read_private_shop_orders')) {
        return new WP_Error('dianxiaomi_api_user_cannot_read_orders_count', __('You do not have permission to read the orders count', 'dianxiaomi'), ['status' => 401]);
    }

    return ['count' => (int)$query->found_posts];
}
    /**
     * Ship an order
     *
     * @since 2.1
     * @param int $id the order ID
     * @param array $data
     * @return array
     */
   /**
 * Ship an order
 *
 * @since 2.1
 * @param int $id the order ID
 * @param array $data Data containing shipping and order status information.
 * @return array|WP_Error Returns the updated order data or a WP_Error object if an error occurs.
 */
public function ship_order(int $id, array $data): array|WP_Error
{
    $validated_id = $this->validate_request($id, 'shop_order', 'edit');

    if (is_wp_error($validated_id)) {
        return $validated_id;
    }

    // Obtenir l'objet de commande
    $order = wc_get_order($validated_id);

    if (!$order) {
        return new WP_Error('invalid_order', 'La commande n\'existe pas', ['status' => 404]);
    }

    // Mettre à jour les métadonnées si elles sont fournies
    if (!empty($data['tracking_number'])) {
        $order->update_meta_data('_dianxiaomi_tracking_number', $data['tracking_number']);
    }
    if (!empty($data['tracking_provider'])) {
        $order->update_meta_data('_dianxiaomi_tracking_provider', $data['tracking_provider']);
    }

    // Vérifier si le statut de la commande doit être ignoré
    $ignore_status = get_option('dianxiaomi_ignore_order_status', 'no');

    // Mettre à jour le statut de la commande si nécessaire
    if (!empty($data['status']) && $ignore_status !== 'yes') {
        $order->set_status($data['status'], isset($data['note']) ? $data['note'] : '');
    }

    // Sauvegarder les modifications
    $order->save();

    // Retourner les données de la commande mise à jour
    return $this->get_order($validated_id);
}
/**
 * Delete an order
 *
 * @TODO enable along with POST in 2.2
 * @param int $id the order ID
 * @param bool $force true to permanently delete order, false to move to trash
 * @return array|WP_Error Returns the result of the deletion or a WP_Error object if an error occurs.
 */
public function delete_order(int $id, bool $force = false): array|WP_Error
{
    $validated_id = $this->validate_request($id, 'shop_order', 'delete');

    if (is_wp_error($validated_id)) {
        return $validated_id;
    }

    return $this->delete($validated_id, 'order', $force);
}

    /**
     * Get order notes
     *
     * @since 2.1
     * @param int $id the order ID
     * @return array
     */
    public function get_order_notes(int $id): array
    {
        $args = [
            'post_id' => $id,
            'approve' => 'approve',
            'type' => 'order_note'
        ];

        $notes = get_comments($args);
        $order_notes = [];

        foreach ($notes as $note) {
            $order_notes[] = [
                'id' => $note->comment_ID,
                'created_at' => $this->server->format_datetime($note->comment_date_gmt),
                'note' => $note->comment_content,
                'customer_note' => get_comment_meta($note->comment_ID, 'is_customer_note', true) ? true : false,
            ];
        }

        return ['order_notes' => apply_filters('dianxiaomi_api_order_notes_response', $order_notes, $id, $this->server)];
    }

   /**
 * Helper method to get order post objects
 *
 * @since 2.1
 * @param array $args request arguments for filtering query
 * @return WP_Query
 */
private function query_orders(array $args): WP_Query
{
    $woo_version = $this->get_woocommerce_version_number();

    $query_args = [
        'fields' => 'ids',
        'post_type' => 'shop_order',
        'post_status' => $woo_version >= '2.2' ? array_keys(wc_get_order_statuses()) : 'publish',
    ];

    if (!empty($args['status'])) {
        $statuses = explode(',', $args['status']);
        $query_args['tax_query'] = [
            [
                'taxonomy' => 'shop_order_status',
                'field' => 'slug',
                'terms' => $statuses,
            ],
        ];
        unset($args['status']);
    }

    $query_args = array_merge($query_args, $args);

    return new WP_Query($query_args);
}

/**
 * Helper method to get the WooCommerce version number
 *
 * @return string|null
 */
private function get_woocommerce_version_number(): ?string
{
    if (!function_exists('get_plugins')) {
        require_once(ABSPATH . 'wp-admin/includes/plugin.php');
    }

    $plugin_folder = get_plugins('/' . 'woocommerce');
    $plugin_file = 'woocommerce.php';

    return $plugin_folder[$plugin_file]['Version'] ?? null;
}

/**
 * Helper method to get the order subtotal
 *
 * @since 2.1
 * @param WC_Order $order
 * @return float
 */
private function get_order_subtotal(WC_Order $order): float
{
    $subtotal = 0;
    foreach ($order->get_items() as $item) {
        $subtotal += $item['line_subtotal'] ?? 0;
    }
    return $subtotal;
}

/**
 * Ping method to check API status
 *
 * @return string
 */
public function ping(): string
{
    return "pong";
}
}