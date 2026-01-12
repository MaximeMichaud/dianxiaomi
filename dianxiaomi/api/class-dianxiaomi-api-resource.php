<?php
/**
 * Dianxiaomi API Resource class.
 *
 * Provides shared functionality for resource-specific API classes
 *
 * @author      Dianxiaomi
 *
 * @category    API
 * @package     Dianxiaomi/API
 *
 * @since       1.0
 * @version     1.30
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

use Dianxiaomi\Interfaces\Subscriber_Interface;
use Dianxiaomi\Traits\API_Response;
use Dianxiaomi\Traits\WooCommerce_Helper;

/**
 * Dianxiaomi API Resource class.
 *
 * Provides shared functionality for resource-specific API classes
 *
 * @author      Dianxiaomi
 *
 * @category    API
 * @package     Dianxiaomi/API
 *
 * @since       1.0
 */
class Dianxiaomi_API_Resource implements Subscriber_Interface {
	use API_Response;
	use WooCommerce_Helper;

	/** @var Dianxiaomi_API_Server the API server */
	protected Dianxiaomi_API_Server $server;

	/** @var string sub-classes override this to set a resource-specific base route */
	protected string $base;

	/**
	 * Get subscribed events for this class.
	 *
	 * @since 1.41
	 *
	 * @return array<string, string|array{0: string, 1: int}|array{0: string, 1: int, 2: int}> Array of event subscriptions.
	 */
	public static function get_subscribed_events(): array {
		return array(
			'dianxiaomi_api_endpoints'          => 'register_routes',
			'dianxiaomi_api_order_response'     => array( 'maybe_add_meta', 15, 2 ),
			'dianxiaomi_api_coupon_response'    => array( 'maybe_add_meta', 15, 2 ),
			'dianxiaomi_api_customer_response'  => array( 'maybe_add_meta', 15, 2 ),
			'dianxiaomi_api_product_response'   => array( 'maybe_add_meta', 15, 2 ),
			'dianxiaomi_api_report_response'    => array( 'maybe_add_meta', 15, 2 ),
		);
	}

	/**
	 * Setup class.
	 *
	 * @since 2.1
	 *
	 * @param Dianxiaomi_API_Server $server
	 */
	public function __construct( Dianxiaomi_API_Server $server ) {
		$this->server = $server;
		/** @phpstan-ignore argument.type */
		add_filter( 'dianxiaomi_api_endpoints', array( $this, 'register_routes' ) );
		foreach ( array( 'order', 'coupon', 'customer', 'product', 'report' ) as $resource ) {
			add_filter( "dianxiaomi_api_{$resource}_response", array( $this, 'maybe_add_meta' ), 15, 2 );
			add_filter( "dianxiaomi_api_{$resource}_response", array( $this, 'filter_response_fields' ), 20, 3 );
		}
	}

	/**
	 * Validate the request by checking:
	 * 1) the ID is a valid integer
	 * 2) the ID returns a valid post object and matches the provided post type
	 * 3) the current user has the proper permissions to read/edit/delete the post
	 *
	 * @since 2.1
	 *
	 * @param int|string $id      the post ID
	 * @param string     $type    the post type, either `shop_order`, `shop_coupon`, or `product`
	 * @param string     $context the context of the request, either `read`, `edit` or `delete`
	 *
	 * @return int|WP_Error valid post ID or WP_Error if any of the checks fails
	 */
	protected function validate_request( $id, string $type, string $context ) {
		$resource_name = $type === 'shop_order' || $type === 'shop_coupon' ? str_replace( 'shop_', '', $type ) : $type;
		$id            = absint( $id );
		if ( empty( $id ) ) {
			// translators: %s: resource name
			return new WP_Error( "dianxiaomi_api_invalid_{$resource_name}_id", sprintf( __( 'Invalid %s ID', 'dianxiaomi' ), $type ), array( 'status' => 404 ) );
		}

		$post = get_post( $id );
		if ( 'customer' !== $type && ( ! $post || $type !== $post->post_type ) ) {
			// translators: %s: resource name
			return new WP_Error( "dianxiaomi_api_invalid_{$resource_name}", sprintf( __( 'Invalid %s', 'dianxiaomi' ), $resource_name ), array( 'status' => 404 ) );
		}

		// For customer type, use ID; for other types, use the post object (which is validated above).
		$permission_target = 'customer' === $type ? $id : $post;
		if ( null === $permission_target ) {
			// translators: %s: resource name
			return new WP_Error( "dianxiaomi_api_invalid_{$resource_name}", sprintf( __( 'Invalid %s', 'dianxiaomi' ), $resource_name ), array( 'status' => 404 ) );
		}

		// translators: %1$s: context, %2$s: resource name
		return $this->check_permission( $permission_target, $context ) ? $id : new WP_Error( "dianxiaomi_api_user_cannot_{$context}_{$resource_name}", sprintf( __( 'You do not have permission to %1$s this %2$s', 'dianxiaomi' ), $context, $resource_name ), array( 'status' => 401 ) );
	}

	/**
	 * Add common request arguments to argument list before WP_Query is run.
	 *
	 * @since 2.1
	 *
	 * @param array<string, mixed> $base_args    Required arguments for the query (e.g. `post_type`, etc).
	 * @param array<string, mixed> $request_args Arguments provided in the request.
	 *
	 * @return array<string, mixed> Merged query arguments.
	 */
	protected function merge_query_args( array $base_args, array $request_args ): array {
		$args = $base_args;
		if ( ! empty( $request_args['created_at_min'] ) || ! empty( $request_args['created_at_max'] ) || ! empty( $request_args['updated_at_min'] ) || ! empty( $request_args['updated_at_max'] ) ) {
			$args['date_query'] = array();
			if ( ! empty( $request_args['created_at_min'] ) && is_string( $request_args['created_at_min'] ) ) {
				$args['date_query'][] = array(
					'column'    => 'post_date_gmt',
					'after'     => $this->server->parse_datetime( $request_args['created_at_min'] ),
					'inclusive' => true,
				);
			}
			if ( ! empty( $request_args['created_at_max'] ) && is_string( $request_args['created_at_max'] ) ) {
				$args['date_query'][] = array(
					'column'    => 'post_date_gmt',
					'before'    => $this->server->parse_datetime( $request_args['created_at_max'] ),
					'inclusive' => true,
				);
			}
			if ( ! empty( $request_args['updated_at_min'] ) && is_string( $request_args['updated_at_min'] ) ) {
				$args['date_query'][] = array(
					'column'    => 'post_modified_gmt',
					'after'     => $this->server->parse_datetime( $request_args['updated_at_min'] ),
					'inclusive' => true,
				);
			}
			if ( ! empty( $request_args['updated_at_max'] ) && is_string( $request_args['updated_at_max'] ) ) {
				$args['date_query'][] = array(
					'column'    => 'post_modified_gmt',
					'before'    => $this->server->parse_datetime( $request_args['updated_at_max'] ),
					'inclusive' => true,
				);
			}
		}
		if ( ! empty( $request_args['q'] ) ) {
			$args['s'] = $request_args['q'];
		}
		if ( ! empty( $request_args['limit'] ) ) {
			$args['posts_per_page'] = $request_args['limit'];
		}
		if ( ! empty( $request_args['offset'] ) ) {
			$args['offset'] = $request_args['offset'];
		}
		$args['paged'] = $request_args['page'] ?? 1;
		if ( ! empty( $request_args['orderby'] ) ) {
			$args['orderby'] = $request_args['orderby'];
		}
		if ( ! empty( $request_args['order'] ) ) {
			$args['order'] = $request_args['order'];
		}
		return $args;
	}

	/**
	 * Add meta to resources when requested by the client. Meta is added as a top-level
	 * `<resource_name>_meta` attribute (e.g. `order_meta`) as a list of key/value pairs.
	 *
	 * @since 2.1
	 *
	 * @param array<string, mixed> $data The resource data.
	 * @param object               $res  The resource object (e.g WC_Order).
	 *
	 * @return array<string, mixed> Resource data with optional meta.
	 */
	public function maybe_add_meta( array $data, object $res ): array {
		if ( isset( $this->server->params['GET']['filter']['meta'] ) && 'true' === $this->server->params['GET']['filter']['meta'] && is_object( $res ) && method_exists( $res, 'get_id' ) ) {
			switch ( get_class( $res ) ) {
				case 'WC_Order':
					$meta_name = 'order_meta';
					break;
				case 'WC_Coupon':
					$meta_name = 'coupon_meta';
					break;
				case 'WC_Product':
					$meta_name = 'product_meta';
					break;
				default:
					$meta_name = 'resource_meta';
					break;
			}
			// HPOS compatible: use get_meta_data() for WC objects, fallback to get_post_meta for others.
			if ( method_exists( $res, 'get_meta_data' ) ) {
				$meta_objects      = $res->get_meta_data();
				$meta_array        = array();
				foreach ( $meta_objects as $meta ) {
					$key = $meta->key;
					if ( ! isset( $meta_array[ $key ] ) ) {
						$meta_array[ $key ] = array();
					}
					$meta_array[ $key ][] = $meta->value;
				}
				$data[ $meta_name ] = $meta_array;
			} else {
				$data[ $meta_name ] = get_post_meta( $res->get_id() );
			}
		}
		return $data;
	}

	/**
	 * Filter response fields based on specified fields in the request.
	 *
	 * @since 2.1
	 *
	 * @param array<string, mixed>    $data   The full data array for the resource.
	 * @param object                  $res    The object that provided the response data, e.g. WC_Coupon or WC_Order.
	 * @param array<int, string>|null $fields List of fields requested to be returned.
	 *
	 * @return array<string, mixed> Filtered data array.
	 */
	public function filter_response_fields( array $data, object $res, ?array $fields ): array {
		if ( empty( $fields ) ) {
			return $data;
		}

		$fields     = explode( ',', implode( ',', $fields ) );
		$sub_fields = array();

		// Extraire les sous-champs
		foreach ( $fields as $field ) {
			if ( strpos( $field, '.' ) !== false ) {
				list( $name, $value ) = explode( '.', $field );
				if ( ! isset( $sub_fields[ $name ] ) ) {
					$sub_fields[ $name ] = array();
				}
				$sub_fields[ $name ][] = $value;
			}
		}

		// Itérer à travers les champs de niveau supérieur
		foreach ( $data as $data_field => $data_value ) {
			// Si un champ a des sous-champs et que le champ de niveau supérieur a des sous-champs à filtrer
			if ( is_array( $data_value ) && array_key_exists( $data_field, $sub_fields ) ) {
				// Itérer à travers chaque sous-champ
				foreach ( $data_value as $sub_field => $sub_field_value ) {
					// Supprimer les sous-champs non correspondants
					if ( ! in_array( $sub_field, $sub_fields[ $data_field ], true ) ) {
						/** @var array<string|int, mixed> $field_data */
						$field_data = $data[ $data_field ];
						unset( $field_data[ $sub_field ] );
						$data[ $data_field ] = $field_data;
					}
				}
			} elseif ( ! in_array( $data_field, $fields, true ) ) {
				// Supprimer les champs de niveau supérieur non correspondants
				unset( $data[ $data_field ] );
			}
		}

		return $data;
	}

	/**
	 * Delete a given resource.
	 *
	 * @since 2.1
	 *
	 * @param int    $id    the resource ID
	 * @param string $type  the resource post type, or `customer`
	 * @param bool   $force true to permanently delete resource, false to move to trash (not supported for `customer`)
	 *
	 * @return array|WP_Error
	 */
	protected function delete( int $id, string $type, bool $force = false ) {
		$resource_name = $type === 'shop_order' || $type === 'shop_coupon' ? str_replace( 'shop_', '', $type ) : $type;

		if ( 'customer' === $type ) {
			$result = wp_delete_user( $id );
			if ( $result ) {
				return array( 'message' => __( 'Permanently deleted customer', 'dianxiaomi' ) );
			} else {
				return new WP_Error( 'dianxiaomi_api_cannot_delete_customer', __( 'The customer cannot be deleted', 'dianxiaomi' ), array( 'status' => 500 ) );
			}
		} else {
			$result = $force ? wp_delete_post( $id, true ) : wp_trash_post( $id );
			if ( ! $result ) {
				// translators: %s: resource name
				return new WP_Error( "dianxiaomi_api_cannot_delete_{$resource_name}", sprintf( __( 'This %s cannot be deleted', 'dianxiaomi' ), $resource_name ), array( 'status' => 500 ) );
			}
			if ( $force ) {
				// translators: %s: resource name
				return array( 'message' => sprintf( __( 'Permanently deleted %s', 'dianxiaomi' ), $resource_name ) );
			} else {
				// translators: %s: resource name
				$this->server->send_status( 202 );
				// translators: %s: resource name
				return array( 'message' => sprintf( __( 'Deleted %s', 'dianxiaomi' ), $resource_name ) );
			}
		}
	}

	/**
	 * Checks if the given post is readable by the current user.
	 *
	 * @since 2.1
	 *
	 * @param WP_Post|int $post
	 *
	 * @return bool
	 */
	protected function is_readable( $post ) {
		return $this->check_permission( $post, 'read' );
	}

	/**
	 * Checks if the given post is editable by the current user.
	 *
	 * @since 2.1
	 *
	 * @param WP_Post|int $post
	 *
	 * @return bool
	 */
	protected function is_editable( $post ) {
		return $this->check_permission( $post, 'edit' );
	}

	/**
	 * Checks if the given post is deletable by the current user.
	 *
	 * @since 2.1
	 *
	 * @param WP_Post|int $post
	 *
	 * @return bool
	 */
	protected function is_deletable( $post ) {
		return $this->check_permission( $post, 'delete' );
	}

	/**
	 * Checks the permissions for the current user given a post and context.
	 *
	 * @since 2.1
	 *
	 * @param WP_Post|int $post
	 * @param string      $context the type of permission to check, either `read`, `write`, or `delete`
	 *
	 * @return bool true if the current user has the permissions to perform the context on the post
	 */
	private function check_permission( $post, string $context ): bool {
		if ( ! $post instanceof WP_Post ) {
			$post = get_post( $post );
		}
		if ( is_null( $post ) ) {
			return false;
		}
		$post_type = get_post_type_object( $post->post_type );
		if ( null === $post_type ) {
			return false;
		}
		switch ( $context ) {
			case 'read':
				return current_user_can( $post_type->cap->read_private_posts, $post->ID );
			case 'edit':
				return current_user_can( $post_type->cap->edit_post, $post->ID );
			case 'delete':
				return current_user_can( $post_type->cap->delete_post, $post->ID );
			default:
				return false;
		}
	}
}
