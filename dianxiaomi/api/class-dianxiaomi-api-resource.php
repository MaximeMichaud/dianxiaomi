<?php
/**
 * Dianxiaomi API Resource class
 *
 * Provides shared functionality for resource-specific API classes
 * @author      Dianxiaomi
 * @category    API
 * @package     Dianxiaomi/API
 * @since       1.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class Dianxiaomi_API_Resource {

	/** @var mixed the API server */
    protected $server;

    /** @var string sub-classes override this to set a resource-specific base route */
    protected $base;

    /**
     * Constructor for setting up the API resource class
     *
     * @param mixed $server API server instance, can be of any type.
     */
    public function __construct(mixed $server) {
        $this->server = $server;
        add_filter('dianxiaomi_api_endpoints', array($this, 'register_routes'));
        add_filter("dianxiaomi_api_response", array($this, 'filter_response_fields'), 20, 3);
    }

    /**
     * Validate the request by checking the ID, type, and permissions.
     *
     * @param int|string $id the post ID
     * @param string $type the post type, either `shop_order`, `shop_coupon`, or `product`
     * @param string $context the context of the request, either `read`, `edit` or `delete`
     * @return int|WP_Error valid post ID or WP_Error if any of the checks fails
     */
    protected function validate_request($id, $type, $context) {
        $resource_name = ($type === 'shop_order' || $type === 'shop_coupon') ? str_replace('shop_', '', $type) : $type;
        $id = absint($id);

        if (!$id) {
            return new WP_Error("dianxiaomi_api_invalid_id", __("Invalid ID provided", 'dianxiaomi'), ['status' => 400]);
        }

        $post = get_post($id);
        if (!$post || $post->post_type !== $type) {
            return new WP_Error("dianxiaomi_api_invalid_post_type", __("Invalid post type", 'dianxiaomi'), ['status' => 404]);
        }

        if (!$this->check_permission($post, $context)) {
            return new WP_Error("dianxiaomi_api_permission_denied", __("Permission denied", 'dianxiaomi'), ['status' => 403]);
        }

        return $id;
    }

    /**
     * Check permissions for the current user given a post and context.
     *
     * @param WP_Post|int $post Post object or ID
     * @param string $context Type of permission to check (read, edit, delete)
     * @return bool True if the current user has the permissions, false otherwise
     */
    private function check_permission($post, $context) {
        if (!is_a($post, 'WP_Post')) {
            $post = get_post($post);
        }

        if (is_null($post)) {
            return false;
        }

        $post_type = get_post_type_object($post->post_type);
        $capability = '';

        switch ($context) {
            case 'read':
                $capability = $post_type->cap->read_private_posts;
                break;
            case 'edit':
                $capability = $post_type->cap->edit_post;
                break;
            case 'delete':
                $capability = $post_type->cap->delete_post;
                break;
            default:
                return false; // Invalid context
        }

        return current_user_can($capability, $post->ID);
    }

    /**
     * Wrapper functions for readability, editability, and deletability checks
     */
    protected function is_readable($post) {
        return $this->check_permission($post, 'read');
    }

    protected function is_editable($post) {
        return $this->check_permission($post, 'edit');
    }

    protected function is_deletable($post) {
        return $this->check_permission($post, 'delete');
    }

    /**
     * Filter the fields included in the response based on specified fields.
     *
     * @param array $data Response data
     * @param object $resource Resource object
     * @param array|string $fields Fields to include in the response
     * @return array Filteredsponse data
     */
    public function filter_response_fields($data, $resource, $fields) {
        if (!is_array($data) || empty($fields)) {
            return $data;
        }

        $fields = is_string($fields) ? explode(',', $fields) : $fields;
        $filtered_data = [];

        foreach ($data as $key => $value) {
            if (in_array($key, $fields)) {
                $filtered_data[$key] = $value;
            }
        }

        return $filtered_data;
    }

    /**
     * Register routes for the API resources.
     */
    public function register_routes() {
        add_action('rest_api_init', function () {
            register_rest_route('dianxiaomi/v1', '/resource/', array(
                'methods' => 'GET',
                'callback' => array($this, 'get_resources'),
            ));
        });
    }

    /**
     * Example callback method for getting resources.
     */
    public function get_resources($request) {
        // Implement fetching resources based on request parameters
        return new WP_REST_Response('This is a response', 200);
    }

    /**
     * Delete a resource based on the ID and type.
     *
     * @param int $id The resource ID
     * @param string $type The resource type
     * @param bool $force Whether to force deletion
     * @return array|WP_Error
     */
    protected function delete($id, $type, $force = false) {
        $resource_name = ($type === 'shop_order' || $type === 'shop_coupon') ? str_replace('shop_', '', $type) : $type;

        if ($type === 'customer') {
            $result = wp_delete_user($id);
            if ($result) {
                return array('message' => __('Permanently deleted customer', 'dianxiaomi'));
            } else {
                return new WP_Error('dianxiaomi_api_cannot_delete_customer', __('The customer cannot be deleted', 'dianxiaomi'), array('status' => 500));
            }
        } else {
            $result = ($force) ? wp_delete_post($id, true) : wp_trash_post($id);
            if (!$result) {
                return new WP_Error("dianxiaomi_api_cannot_delete_{$resource_name}", sprintf(__('This %s cannot be deleted', 'dianxiaomi'), $resource_name), array('status' => 500));
            }

            if ($force) {
                return array('message' => sprintf(__('Permanently deleted %s', 'dianxiaomi'), $resource_name));
            } else {
                $this->server->send_status('202');
                return array('message' => sprintf(__('Deleted %s', 'dianxiaomi'), $resource_name));
            }
        }
    }
}