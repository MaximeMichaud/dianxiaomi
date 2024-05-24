<?php
class Dianxiaomi_API_Orders extends Dianxiaomi_API_Resource {
    protected $base = '/orders';

    public function register_routes() {
        add_action('rest_api_init', function () {
            register_rest_route('dianxiaomi/v1', $this->base, array(
                'methods' => 'GET',
                'callback' => array($this, 'get_orders'),
                'permission_callback' => array($this, 'permissions_check')
            ));
    
            register_rest_route('dianxiaomi/v1', $this->base . '/count', array(
                'methods' => 'GET',
                'callback' => array($this, 'get_orders_count'),
                'permission_callback' => array($this, 'permissions_check')
            ));
    
            register_rest_route('dianxiaomi/v1', $this->base . '/(?P<id>\d+)', array(
                array(
                    'methods' => 'GET',
                    'callback' => array($this, 'get_order'),
                    'permission_callback' => array($this, 'permissions_check')
                ),
                array(
                    'methods' => 'POST',
                    'callback' => array($this, 'edit_order'),
                    'permission_callback' => array($this, 'permissions_check')
                )
            ));
    
            register_rest_route('dianxiaomi/v1', $this->base . '/(?P<id>\d+)/ship', array(
                'methods' => 'POST',
                'callback' => array($this, 'ship_order'),
                'permission_callback' => array($this, 'permissions_check')
            ));
    
            register_rest_route('dianxiaomi/v1', $this->base . '/(?P<id>\d+)/notes', array(
                'methods' => 'GET',
                'callback' => array($this, 'get_order_notes'),
                'permission_callback' => array($this, 'permissions_check')
            ));
    
            register_rest_route('dianxiaomi/v1', $this->base . '/ping', array(
                'methods' => 'GET',
                'callback' => array($this, 'ping'),
                'permission_callback' => array($this, 'permissions_check')
            ));
        });
    } 
    public function get_orders($fields = null, $filter = array(), $status = null, $page = 1, $updated_at_min = '') {
        $filter['status'] = $status ?? '';
        $filter['updated_at_min'] = $updated_at_min;
        $filter['page'] = $page;

        $query = $this->query_orders($filter);
        $orders = array();

        foreach ($query->posts as $order_id) {
            $order = wc_get_order($order_id);
            if ($order && $this->is_readable($order_id)) {
                $orders[] = $this->prepare_order_data($order, $fields);
            }
        }

        $this->server->add_pagination_headers($query);
        return array('orders' => $orders);
    }

    public function get_order($id, $fields = null) {
        $id = $this->validate_request($id, 'shop_order', 'read');
        if (is_wp_error($id)) {
            return $id;
        }

        $order = wc_get_order($id);
        if (!$order) {
            return new WP_Error('woocommerce_api_no_order_found', __('No order found with this ID.', 'woocommerce'), array('status' => 404));
        }

        return $this->prepare_order_data($order, $fields);
    }

    private function prepare_order_data(WC_Order $order, $fields) {
        $data = array(
            'id' => $order->get_id(),
            'order_number' => $order->get_order_number(),
            'created_at' => $this->server->format_datetime($order->get_date_created()->getTimestamp()),
            'updated_at' => $this->server->format_datetime($order->get_date_modified()->getTimestamp()),
            'completed_at' => $this->server->format_datetime($order->get_date_completed() ? $order->get_date_completed()->getTimestamp() : null, true),
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
                'paid' => $order->get_date_paid() ? $order->get_date_paid()->getTimestamp() : null,
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
            'customer_id' => $order->get_customer_id(),
            'view_order_url' => $order->get_view_order_url(),
            'line_items' => $this->get_order_line_items($order),
        );

        return apply_filters('dianxiaomi_api_order_response', $data, $order, $fields, $this->server);
    }

    private function get_order_line_items(WC_Order $order) {
        $items = array();
        foreach ($order->get_items() as $item_id => $item) {
            $product = $item->get_product();
            $items[] = array(
                'id' => $item_id,
                'name' => $item->get_name(),
                'product_id' => $item->get_product_id(),
                'variation_id' => $item->get_variation_id(),
                'quantity' => $item->get_quantity(),
                'subtotal' => wc_format_decimal($item->get_subtotal(), 2),
                'total' => wc_format_decimal($item->get_total(), 2),
                'sku' => $product ? $product->get_sku() : null,
                'price' => $product ? wc_format_decimal($product->get_price(), 2) : null,
            );
        }
        return $items;
    }

    private function get_order_subtotal(WC_Order $order) {
        $subtotal = 0;
        foreach ($order->get_items() as $item) {
            $subtotal += $item->get_subtotal();
        }
        return $subtotal;
    }

    public function get_orders_count($status = null, $filter = array()) {
        if (!empty($status)) {
            $filter['status'] = $status;
        }

        $query = $this->query_orders($filter);

        if (!current_user_can('read_private_shop_orders')) {
            return new WP_Error('dianxiaomi_api_user_cannot_read_orders_count', __('You do not have permission to read the orders count', 'dianxiaomi'), array('status' => 401));
        }

        return array('count' => (int) $query->found_posts);
    }

    private function query_orders($args) {
        $query_args = array(
            'fields' => 'ids',
            'post_type' => 'shop_order',
            'post_status' => array_keys(wc_get_order_statuses()),
        );

        if (!empty($args['status'])) {
            $statuses = explode(',', $args['status']);
            $query_args['tax_query'] = array(
                array(
                    'taxonomy' => 'shop_order_status',
                    'field' => 'slug',
                    'terms' => $statuses,
                ),
            );
        }

        $query_args = $this->merge_query_args($query_args, $args);
        return new WP_Query($query_args);
    }

	function merge_query_args($query_args, $args) {
        return array_merge($query_args, $args);
    }

    public function ping() {
        return 'pong';
    }
}
