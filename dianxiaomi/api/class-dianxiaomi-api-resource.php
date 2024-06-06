<?php
/**
 * Dianxiaomi API Resource class
 *
 * Provides shared functionality for resource-specific API classes
 *
 * @author      Dianxiaomi
 * @category    API
 * @package     Dianxiaomi/API
 * @since       1.0
 */

if (!defined('ABSPATH')) exit; // Exit if accessed directly

class Dianxiaomi_API_Resource
{
    /** @var Dianxiaomi_API_Server the API server */
    protected Dianxiaomi_API_Server $server;

    /** @var string sub-classes override this to set a resource-specific base route */
    protected string $base;

    /**
     * Setup class
     *
     * @since 2.1
     * @param Dianxiaomi_API_Server $server
     */
    public function __construct(Dianxiaomi_API_Server $server)
    {
        $this->server = $server;
        add_filter('dianxiaomi_api_endpoints', [$this, 'register_routes']);
        foreach (['order', 'coupon', 'customer', 'product', 'report'] as $resource) {
            add_filter("dianxiaomi_api_{$resource}_response", [$this, 'maybe_add_meta'], 15, 2);
            add_filter("dianxiaomi_api_{$resource}_response", [$this, 'filter_response_fields'], 20, 3);
        }
    }

    /**
     * Validate the request by checking:
     * 1) the ID is a valid integer
     * 2) the ID returns a valid post object and matches the provided post type
     * 3) the current user has the proper permissions to read/edit/delete the post
     *
     * @since 2.1
     * @param int|string $id the post ID
     * @param string $type the post type, either `shop_order`, `shop_coupon`, or `product`
     * @param string $context the context of the request, either `read`, `edit` or `delete`
     * @return int|WP_Error valid post ID or WP_Error if any of the checks fails
     */
    protected function validate_request($id, string $type, string $context): int|WP_Error
    {
        $resource_name = $type === 'shop_order' || $type === 'shop_coupon' ? str_replace('shop_', '', $type) : $type;
        $id = absint($id);
        if (empty($id)) {
            return new WP_Error("dianxiaomi_api_invalid_{$resource_name}_id", sprintf(__('Invalid %s ID', 'dianxiaomi'), $type), ['status' => 404]);
        }

        $post = get_post($id);
        if ('customer' !== $type && (!$post || $type !== $post->post_type)) {
            return new WP_Error("dianxiaomi_api_invalid_{$resource_name}", sprintf(__('Invalid %s', 'dianxiaomi'), $resource_name), ['status' => 404]);
        }

        return $this->check_permission($post, $context) ? $id : new WP_Error("dianxiaomi_api_user_cannot_{$context}_{$resource_name}", sprintf(__('You do not have permission to %s this %s', 'dianxiaomi'), $context, $resource_name), ['status' => 401]);
    }

    /**
     * Add common request arguments to argument list before WP_Query is run
     *
     * @since 2.1
     * @param array $base_args required arguments for the query (e.g. `post_type`, etc)
     * @param array $request_args arguments provided in the request
     * @return array
     */
    protected function merge_query_args(array $base_args, array $request_args): array
    {
        $args = $base_args;
        if (!empty($request_args['created_at_min']) || !empty($request_args['created_at_max']) || !empty($request_args['updated_at_min']) || !empty($request_args['updated_at_max'])) {
            $args['date_query'] = [];
            if (!empty($request_args['created_at_min'])) {
                $args['date_query'][] = ['column' => 'post_date_gmt', 'after' => $this->server->parse_datetime($request_args['created_at_min']), 'inclusive' => true];
            }
            if (!empty($request_args['created_at_max'])) {
                $args['date_query'][] = ['column' => 'post_date_gmt', 'before' => $this->server->parse_datetime($request_args['created_at_max']), 'inclusive' => true];
            }
            if (!empty($request_args['updated_at_min'])) {
                $args['date_query'][] = ['column' => 'post_modified_gmt', 'after' => $this->server->parse_datetime($request_args['updated_at_min']), 'inclusive' => true];
            }
            if (!empty($request_args['updated_at_max'])) {
                $args['date_query'][] = ['column' => 'post_modified_gmt', 'before' => $this->server->parse_datetime($request_args['updated_at_max']), 'inclusive' => true];
            }
        }
        if (!empty($request_args['q'])) {
            $args['s'] = $request_args['q'];
        }
        if (!empty($request_args['limit'])) {
            $args['posts_per_page'] = $request_args['limit'];
        }
        if (!empty($request_args['offset'])) {
            $args['offset'] = $request_args['offset'];
        }
        $args['paged'] = $request_args['page'] ?? 1;
        if (!empty($request_args['orderby'])) {
            $args['orderby'] = $request_args['orderby'];
        }
        if (!empty($request_args['order'])) {
            $args['order'] = $request_args['order'];
        }
        return $args;
    }

    /**
     * Add meta to resources when requested by the client. Meta is added as a top-level
     * `<resource_name>_meta` attribute (e.g. `order_meta`) as a list of key/value pairs
     *
     * @since 2.1
     * @param array $data the resource data
     * @param object $resource the resource object (e.g WC_Order)
     * @return array
     */
    public function maybe_add_meta(array $data, object $resource): array
    {
        if (isset($this->server->params['GET']['filter']['meta']) && 'true' === $this->server->params['GET']['filter']['meta'] && is_object($resource)) {
            $meta_name = match (get_class($resource)) {
                'WC_Order' => 'order_meta',
				'WC_Coupon' => 'coupon_meta',
                'WC_Product' => 'product_meta',
                default => 'resource_meta'
            };
            $data[$meta_name] = get_post_meta($resource->get_id());
        }
        return $data;
    }

/**
 * Filter response fields based on specified fields in the request
 *
 * @since 2.1
 * @param array $data the full data array for the resource
 * @param object $resource the object that provided the response data, e.g. WC_Coupon or WC_Order
 * @param array $fields list of fields requested to be returned
 * @return array tableau de données filtré
 */
public function filter_response_fields(array $data, object $resource, ?string $fields): array
{
    if (empty($fields)) {
        return $data;
    }

    $fields = explode(',', $fields);
    $sub_fields = [];

    // Extraire les sous-champs
    foreach ($fields as $field) {
        if (strpos($field, '.') !== false) {
            [$name, $value] = explode('.', $field);
            $sub_fields[$name] = $value;
        }
    }

    // Itérer à travers les champs de niveau supérieur
    foreach ($data as $data_field => $data_value) {
        // Si un champ a des sous-champs et que le champ de niveau supérieur a des sous-champs à filtrer
        if (is_array($data_value) && array_key_exists($data_field, $sub_fields)) {
            // Itérer à travers chaque sous-champ
            foreach ($data_value as $sub_field => $sub_field_value) {
                // Supprimer les sous-champs non correspondants
                if (!in_array($sub_field, $sub_fields[$data_field])) {
                    unset($data[$data_field][$sub_field]);
                }
            }
        } else {
            // Supprimer les champs de niveau supérieur non correspondants
            if (!in_array($data_field, $fields)) {
                unset($data[$data_field]);
            }
        }
    }

    return $data;
}

    /**
     * Delete a given resource
     *
     * @since 2.1
     * @param int $id the resource ID
     * @param string $type the resource post type, or `customer`
     * @param bool $force true to permanently delete resource, false to move to trash (not supported for `customer`)
     * @return array|WP_Error
     */
    protected function delete(int $id, string $type, bool $force = false): array|WP_Error
    {
        $resource_name = $type === 'shop_order' || $type === 'shop_coupon' ? str_replace('shop_', '', $type) : $type;

        if ('customer' === $type) {
            $result = wp_delete_user($id);
            if ($result) {
                return ['message' => __('Permanently deleted customer', 'dianxiaomi')];
            } else {
                return new WP_Error('dianxiaomi_api_cannot_delete_customer', __('The customer cannot be deleted', 'dianxiaomi'), ['status' => 500]);
            }
        } else {
            $result = $force ? wp_delete_post($id, true) : wp_trash_post($id);
            if (!$result) {
                return new WP_Error("dianxiaomi_api_cannot_delete_{$resource_name}", sprintf(__('This %s cannot be deleted', 'dianxiaomi'), $resource_name), ['status' => 500]);
            }
            if ($force) {
                return ['message' => sprintf(__('Permanently deleted %s', 'dianxiaomi'), $resource_name)];
            } else {
                $this->server->send_status('202');
                return ['message' => sprintf(__('Deleted %s', 'dianxiaomi'), $resource_name)];
            }
        }
    }

    /**
     * Checks if the given post is readable by the current user
     *
     * @since 2.1
     * @param WP_Post|int $post
     * @return bool
     */
    protected function is_readable($post): bool
    {
        return $this->check_permission($post, 'read');
    }

    /**
     * Checks if the given post is editable by the current user
     *
     * @since 2.1
     * @param WP_Post|int $post
     * @return bool
     */
    protected function is_editable($post): bool
    {
        return $this->check_permission($post, 'edit');
    }

    /**
     * Checks if the given post is deletable by the current user
     *
     * @since 2.1
     * @param WP_Post|int $post
     * @return bool
     */
    protected function is_deletable($post): bool
    {
        return $this->check_permission($post, 'delete');
    }

    /**
     * Checks the permissions for the current user given a post and context
     *
     * @since 2.1
     * @param WP_Post|int $post
     * @param string $context the type of permission to check, either `read`, `write`, or `delete`
     * @return bool true if the current user has the permissions to perform the context on the post
     */
    private function check_permission($post, string $context): bool
    {
        if (!is_a($post, 'WP_Post')) {
            $post = get_post($post);
        }
        if (is_null($post)) {
            return false;
        }
        $post_type = get_post_type_object($post->post_type);
        switch ($context) {
            case 'read':
                return current_user_can($post_type->cap->read_private_posts, $post->ID);
            case 'edit':
                return current_user_can($post_type->cap->edit_post, $post->ID);
            case 'delete':
                return current_user_can($post_type->cap->delete_post, $post->ID);
            default:
                return false;
        }
    }
}