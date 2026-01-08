<?php
/**
 * PHPUnit bootstrap file with WordPress/WooCommerce mocks.
 *
 * @package Dianxiaomi
 */

// Mock WordPress constants.
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}
if ( ! defined( 'WP_CONTENT_DIR' ) ) {
	define( 'WP_CONTENT_DIR', ABSPATH . 'wp-content' );
}
if ( ! defined( 'WP_PLUGIN_DIR' ) ) {
	define( 'WP_PLUGIN_DIR', WP_CONTENT_DIR . '/plugins' );
}
if ( ! defined( 'WPINC' ) ) {
	define( 'WPINC', 'wp-includes' );
}

// =============================================================================
// GLOBAL STATE FOR TESTING
// =============================================================================

global $wp_scripts_enqueued, $wp_styles_enqueued, $wp_scripts_registered, $wp_styles_registered;
global $wp_options, $wp_post_meta, $wp_actions, $wp_filters, $wp_meta_boxes;
global $wc_orders, $wc_products, $woocommerce;

$wp_scripts_enqueued    = array();
$wp_styles_enqueued     = array();
$wp_scripts_registered  = array();
$wp_styles_registered   = array();
$wp_options             = array();
$wp_post_meta           = array();
$wp_actions             = array();
$wp_filters             = array();
$wp_meta_boxes          = array();
$wc_orders              = array();
$wc_products            = array();

// =============================================================================
// WOOCOMMERCE CLASSES
// =============================================================================

/**
 * Mock WC_Order class.
 */
if ( ! class_exists( 'WC_Order' ) ) {
	class WC_Order {
		private int $id;
		private string $status = 'processing';
		private array $meta_data = array();
		private array $items = array();
		private string $billing_email = 'test@example.com';
		private string $billing_first_name = 'John';
		private string $billing_last_name = 'Doe';
		private string $shipping_country = 'US';
		private string $currency = 'USD';
		private float $total = 100.00;

		public function __construct( int $id = 0 ) {
			$this->id = $id;
		}

		public function get_id(): int {
			return $this->id;
		}

		public function get_status(): string {
			return $this->status;
		}

		public function set_status( string $status ): void {
			$this->status = $status;
		}

		public function get_meta( string $key, bool $single = true ) {
			if ( $single ) {
				return $this->meta_data[ $key ] ?? '';
			}
			return isset( $this->meta_data[ $key ] ) ? array( $this->meta_data[ $key ] ) : array();
		}

		public function update_meta_data( string $key, $value ): void {
			$this->meta_data[ $key ] = $value;
		}

		public function add_meta_data( string $key, $value, bool $unique = false ): void {
			$this->meta_data[ $key ] = $value;
		}

		public function delete_meta_data( string $key ): void {
			unset( $this->meta_data[ $key ] );
		}

		public function save(): int {
			return $this->id;
		}

		public function get_billing_email(): string {
			return $this->billing_email;
		}

		public function get_billing_first_name(): string {
			return $this->billing_first_name;
		}

		public function get_billing_last_name(): string {
			return $this->billing_last_name;
		}

		public function get_shipping_country(): string {
			return $this->shipping_country;
		}

		public function get_currency(): string {
			return $this->currency;
		}

		public function get_total(): float {
			return $this->total;
		}

		public function get_items( string $type = 'line_item' ): array {
			return $this->items;
		}

		public function get_order_number(): string {
			return (string) $this->id;
		}

		public function get_date_created(): ?WC_DateTime {
			return new WC_DateTime();
		}

		public function get_date_modified(): ?WC_DateTime {
			return new WC_DateTime();
		}

		// For testing: set internal data.
		public function set_meta_data_array( array $data ): void {
			$this->meta_data = $data;
		}

		public function set_billing_email( string $email ): void {
			$this->billing_email = $email;
		}

		public function set_total( float $total ): void {
			$this->total = $total;
		}
	}
}

/**
 * Mock WC_DateTime class.
 */
if ( ! class_exists( 'WC_DateTime' ) ) {
	class WC_DateTime extends DateTime {
		public function __construct( string $datetime = 'now', ?DateTimeZone $timezone = null ) {
			parent::__construct( $datetime, $timezone ?? new DateTimeZone( 'UTC' ) );
		}

		public function date( string $format ): string {
			return $this->format( $format );
		}

		public function date_i18n( string $format = '' ): string {
			return $this->format( $format ?: 'Y-m-d H:i:s' );
		}
	}
}

/**
 * Mock WC_Product class.
 */
if ( ! class_exists( 'WC_Product' ) ) {
	class WC_Product {
		private int $id;
		private string $name = 'Test Product';
		private string $sku = 'TEST-SKU';
		private float $price = 29.99;
		private string $status = 'publish';

		public function __construct( int $id = 0 ) {
			$this->id = $id;
		}

		public function get_id(): int {
			return $this->id;
		}

		public function get_name(): string {
			return $this->name;
		}

		public function get_sku(): string {
			return $this->sku;
		}

		public function get_price(): float {
			return $this->price;
		}

		public function get_status(): string {
			return $this->status;
		}

		public function is_visible(): bool {
			return $this->status === 'publish';
		}
	}
}

/**
 * Mock WC_Order_Item_Shipping class.
 */
if ( ! class_exists( 'WC_Order_Item_Shipping' ) ) {
	class WC_Order_Item_Shipping {
		private int $id;
		private string $method_title = 'Flat Rate';
		private string $method_id = 'flat_rate';
		private float $total = 10.00;
		private array $meta_data = array();

		public function __construct( int $id = 0 ) {
			$this->id = $id;
		}

		public function get_id(): int {
			return $this->id;
		}

		public function get_method_title(): string {
			return $this->method_title;
		}

		public function get_method_id(): string {
			return $this->method_id;
		}

		public function get_total(): float {
			return $this->total;
		}

		public function get_meta( string $key, bool $single = true ) {
			return $this->meta_data[ $key ] ?? '';
		}
	}
}

/**
 * Mock WooCommerce main class.
 */
if ( ! class_exists( 'WooCommerce' ) ) {
	class WooCommerce {
		public string $version = '9.0.0';
		public ?WC_Cart $cart = null;
		public ?WC_Customer $customer = null;
		public ?WC_Session $session = null;
		public ?WC_Countries $countries = null;

		private static ?WooCommerce $instance = null;

		public static function instance(): WooCommerce {
			if ( is_null( self::$instance ) ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		public function __construct() {
			$this->cart      = new WC_Cart();
			$this->countries = new WC_Countries();
		}

		public function api_request_url( string $request ): string {
			return home_url( '/wc-api/' . $request );
		}

		public function plugin_url(): string {
			return 'https://example.com/wp-content/plugins/woocommerce';
		}

		public function plugin_path(): string {
			return '/var/www/html/wp-content/plugins/woocommerce';
		}
	}
}

/**
 * Mock WC_Cart class.
 */
if ( ! class_exists( 'WC_Cart' ) ) {
	class WC_Cart {
		private array $cart_contents = array();

		public function get_cart(): array {
			return $this->cart_contents;
		}

		public function get_cart_contents_count(): int {
			return count( $this->cart_contents );
		}

		public function is_empty(): bool {
			return empty( $this->cart_contents );
		}

		public function get_total( string $context = 'view' ): float {
			return 0.00;
		}
	}
}

/**
 * Mock WC_Countries class.
 */
if ( ! class_exists( 'WC_Countries' ) ) {
	class WC_Countries {
		public function get_countries(): array {
			return array(
				'US' => 'United States',
				'CA' => 'Canada',
				'GB' => 'United Kingdom',
				'FR' => 'France',
				'DE' => 'Germany',
				'CN' => 'China',
			);
		}

		public function get_base_country(): string {
			return 'US';
		}

		public function get_allowed_countries(): array {
			return $this->get_countries();
		}

		public function get_shipping_countries(): array {
			return $this->get_countries();
		}
	}
}

/**
 * Mock WC_Customer class.
 */
if ( ! class_exists( 'WC_Customer' ) ) {
	class WC_Customer {
		private int $id = 1;

		public function get_id(): int {
			return $this->id;
		}

		public function get_billing_country(): string {
			return 'US';
		}

		public function get_shipping_country(): string {
			return 'US';
		}
	}
}

/**
 * Mock WC_Session class.
 */
if ( ! class_exists( 'WC_Session' ) ) {
	class WC_Session {
		private array $data = array();

		public function get( string $key, $default = null ) {
			return $this->data[ $key ] ?? $default;
		}

		public function set( string $key, $value ): void {
			$this->data[ $key ] = $value;
		}
	}
}

/**
 * Mock WC_Data_Store class.
 */
if ( ! class_exists( 'WC_Data_Store' ) ) {
	class WC_Data_Store {
		public static function load( string $object_type ): WC_Data_Store {
			return new self();
		}

		public function read( &$object ): void {
			// Mock implementation.
		}
	}
}

// =============================================================================
// WOOCOMMERCE FUNCTIONS
// =============================================================================

/**
 * WC() - Main WooCommerce instance.
 */
if ( ! function_exists( 'WC' ) ) {
	function WC(): WooCommerce {
		return WooCommerce::instance();
	}
}

/**
 * wc_get_order() - Get an order by ID.
 */
if ( ! function_exists( 'wc_get_order' ) ) {
	function wc_get_order( $order_id ): ?WC_Order {
		global $wc_orders;

		if ( $order_id instanceof WC_Order ) {
			return $order_id;
		}

		$order_id = absint( $order_id );

		if ( isset( $wc_orders[ $order_id ] ) ) {
			return $wc_orders[ $order_id ];
		}

		// Create a mock order if requested.
		if ( $order_id > 0 ) {
			$order                    = new WC_Order( $order_id );
			$wc_orders[ $order_id ] = $order;
			return $order;
		}

		return null;
	}
}

/**
 * wc_get_product() - Get a product by ID.
 */
if ( ! function_exists( 'wc_get_product' ) ) {
	function wc_get_product( $product_id ): ?WC_Product {
		global $wc_products;

		if ( $product_id instanceof WC_Product ) {
			return $product_id;
		}

		$product_id = absint( $product_id );

		if ( isset( $wc_products[ $product_id ] ) ) {
			return $wc_products[ $product_id ];
		}

		if ( $product_id > 0 ) {
			$product                      = new WC_Product( $product_id );
			$wc_products[ $product_id ] = $product;
			return $product;
		}

		return null;
	}
}

/**
 * wc_get_orders() - Query orders.
 */
if ( ! function_exists( 'wc_get_orders' ) ) {
	function wc_get_orders( array $args = array() ): array {
		global $wc_orders;
		return array_values( $wc_orders );
	}
}

/**
 * wc_create_order() - Create a new order.
 */
if ( ! function_exists( 'wc_create_order' ) ) {
	function wc_create_order( array $args = array() ): WC_Order {
		global $wc_orders;
		$id                   = count( $wc_orders ) + 1;
		$order                = new WC_Order( $id );
		$wc_orders[ $id ]   = $order;
		return $order;
	}
}

/**
 * wc_get_order_statuses() - Get order statuses.
 */
if ( ! function_exists( 'wc_get_order_statuses' ) ) {
	function wc_get_order_statuses(): array {
		return array(
			'wc-pending'    => 'Pending payment',
			'wc-processing' => 'Processing',
			'wc-on-hold'    => 'On hold',
			'wc-completed'  => 'Completed',
			'wc-cancelled'  => 'Cancelled',
			'wc-refunded'   => 'Refunded',
			'wc-failed'     => 'Failed',
		);
	}
}

/**
 * wc_price() - Format a price.
 */
if ( ! function_exists( 'wc_price' ) ) {
	function wc_price( $price, array $args = array() ): string {
		return '$' . number_format( (float) $price, 2 );
	}
}

/**
 * wc_format_datetime() - Format a datetime.
 */
if ( ! function_exists( 'wc_format_datetime' ) ) {
	function wc_format_datetime( $date, string $format = '' ): string {
		if ( $date instanceof WC_DateTime ) {
			return $date->format( $format ?: 'Y-m-d' );
		}
		return '';
	}
}

/**
 * wc_clean() - Clean variables.
 */
if ( ! function_exists( 'wc_clean' ) ) {
	function wc_clean( $var ) {
		if ( is_array( $var ) ) {
			return array_map( 'wc_clean', $var );
		}
		return is_scalar( $var ) ? sanitize_text_field( (string) $var ) : $var;
	}
}

/**
 * wc_sanitize_textarea() - Sanitize textarea.
 */
if ( ! function_exists( 'wc_sanitize_textarea' ) ) {
	function wc_sanitize_textarea( string $var ): string {
		return sanitize_textarea_field( $var );
	}
}

/**
 * wc_string_to_bool() - Convert string to bool.
 */
if ( ! function_exists( 'wc_string_to_bool' ) ) {
	function wc_string_to_bool( $string ): bool {
		return is_bool( $string ) ? $string : ( 'yes' === strtolower( (string) $string ) || '1' === (string) $string || 'true' === strtolower( (string) $string ) );
	}
}

/**
 * wc_bool_to_string() - Convert bool to string.
 */
if ( ! function_exists( 'wc_bool_to_string' ) ) {
	function wc_bool_to_string( bool $bool ): string {
		return $bool ? 'yes' : 'no';
	}
}

/**
 * is_woocommerce() - Check if on a WooCommerce page.
 */
if ( ! function_exists( 'is_woocommerce' ) ) {
	function is_woocommerce(): bool {
		return true;
	}
}

/**
 * woocommerce_wp_text_input() - Output a text input field.
 */
if ( ! function_exists( 'woocommerce_wp_text_input' ) ) {
	function woocommerce_wp_text_input( array $field ): void {
		$id          = $field['id'] ?? '';
		$label       = $field['label'] ?? '';
		$placeholder = $field['placeholder'] ?? '';
		$value       = $field['value'] ?? '';
		$class       = $field['class'] ?? '';
		$desc_tip    = $field['desc_tip'] ?? false;
		$description = $field['description'] ?? '';

		echo '<p class="form-field ' . esc_attr( $id ) . '_field ' . esc_attr( $class ) . '">';
		echo '<label for="' . esc_attr( $id ) . '">' . wp_kses_post( $label ) . '</label>';
		echo '<input type="text" class="short" name="' . esc_attr( $id ) . '" id="' . esc_attr( $id ) . '" value="' . esc_attr( $value ) . '" placeholder="' . esc_attr( $placeholder ) . '" />';
		if ( $description ) {
			echo '<span class="description">' . wp_kses_post( $description ) . '</span>';
		}
		echo '</p>';
	}
}

/**
 * woocommerce_wp_select() - Output a select field.
 */
if ( ! function_exists( 'woocommerce_wp_select' ) ) {
	function woocommerce_wp_select( array $field ): void {
		$id      = $field['id'] ?? '';
		$label   = $field['label'] ?? '';
		$options = $field['options'] ?? array();
		$value   = $field['value'] ?? '';

		echo '<p class="form-field ' . esc_attr( $id ) . '_field">';
		echo '<label for="' . esc_attr( $id ) . '">' . wp_kses_post( $label ) . '</label>';
		echo '<select name="' . esc_attr( $id ) . '" id="' . esc_attr( $id ) . '">';
		foreach ( $options as $key => $option ) {
			echo '<option value="' . esc_attr( $key ) . '"' . selected( $value, $key, false ) . '>' . esc_html( $option ) . '</option>';
		}
		echo '</select>';
		echo '</p>';
	}
}

// =============================================================================
// WORDPRESS FUNCTIONS
// =============================================================================

if ( ! function_exists( 'wp_enqueue_script' ) ) {
	function wp_enqueue_script( string $handle, string $src = '', array $deps = array(), $ver = false, $args = false ): void {
		global $wp_scripts_enqueued;
		$wp_scripts_enqueued[ $handle ] = array(
			'handle'    => $handle,
			'src'       => $src,
			'deps'      => $deps,
			'ver'       => $ver,
			'in_footer' => is_array( $args ) ? ( $args['in_footer'] ?? false ) : $args,
		);
	}
}

if ( ! function_exists( 'wp_enqueue_style' ) ) {
	function wp_enqueue_style( string $handle, string $src = '', array $deps = array(), $ver = false, string $media = 'all' ): void {
		global $wp_styles_enqueued;
		$wp_styles_enqueued[ $handle ] = array(
			'handle' => $handle,
			'src'    => $src,
			'deps'   => $deps,
			'ver'    => $ver,
			'media'  => $media,
		);
	}
}

if ( ! function_exists( 'wp_register_script' ) ) {
	function wp_register_script( string $handle, string $src = '', array $deps = array(), $ver = false, $args = false ): bool {
		global $wp_scripts_registered;
		$wp_scripts_registered[ $handle ] = array(
			'handle' => $handle,
			'src'    => $src,
			'deps'   => $deps,
			'ver'    => $ver,
		);
		return true;
	}
}

if ( ! function_exists( 'wp_register_style' ) ) {
	function wp_register_style( string $handle, string $src = '', array $deps = array(), $ver = false, string $media = 'all' ): bool {
		global $wp_styles_registered;
		$wp_styles_registered[ $handle ] = array(
			'handle' => $handle,
			'src'    => $src,
			'deps'   => $deps,
			'ver'    => $ver,
		);
		return true;
	}
}

if ( ! function_exists( 'wp_script_is' ) ) {
	function wp_script_is( string $handle, string $status = 'enqueued' ): bool {
		global $wp_scripts_enqueued, $wp_scripts_registered;
		if ( $status === 'registered' ) {
			return isset( $wp_scripts_registered[ $handle ] );
		}
		return isset( $wp_scripts_enqueued[ $handle ] );
	}
}

if ( ! function_exists( 'wp_style_is' ) ) {
	function wp_style_is( string $handle, string $status = 'enqueued' ): bool {
		global $wp_styles_enqueued, $wp_styles_registered;
		if ( $status === 'registered' ) {
			return isset( $wp_styles_registered[ $handle ] );
		}
		return isset( $wp_styles_enqueued[ $handle ] );
	}
}

if ( ! function_exists( 'plugin_dir_url' ) ) {
	function plugin_dir_url( string $file ): string {
		return 'https://example.com/wp-content/plugins/dianxiaomi/';
	}
}

if ( ! function_exists( 'plugin_dir_path' ) ) {
	function plugin_dir_path( string $file ): string {
		return dirname( $file ) . '/';
	}
}

if ( ! function_exists( 'plugins_url' ) ) {
	function plugins_url( string $path = '', string $plugin = '' ): string {
		return 'https://example.com/wp-content/plugins/' . $path;
	}
}

if ( ! function_exists( 'home_url' ) ) {
	function home_url( string $path = '' ): string {
		return 'https://example.com' . $path;
	}
}

if ( ! function_exists( 'admin_url' ) ) {
	function admin_url( string $path = '' ): string {
		return 'https://example.com/wp-admin/' . $path;
	}
}

if ( ! function_exists( 'load_plugin_textdomain' ) ) {
	function load_plugin_textdomain( string $domain, $deprecated = false, string $plugin_rel_path = '' ): bool {
		return true;
	}
}

if ( ! function_exists( '__' ) ) {
	function __( string $text, string $domain = 'default' ): string {
		return $text;
	}
}

if ( ! function_exists( '_e' ) ) {
	function _e( string $text, string $domain = 'default' ): void {
		echo $text;
	}
}

if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( string $text, string $domain = 'default' ): string {
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_html_e' ) ) {
	function esc_html_e( string $text, string $domain = 'default' ): void {
		echo htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $text ): string {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_attr__' ) ) {
	function esc_attr__( string $text, string $domain = 'default' ): string {
		return esc_attr( $text );
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $text ): string {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( string $url ): string {
		return filter_var( $url, FILTER_SANITIZE_URL ) ?: '';
	}
}

if ( ! function_exists( 'wp_kses_post' ) ) {
	function wp_kses_post( string $data ): string {
		return $data; // Simplified mock.
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( string $str ): string {
		return trim( strip_tags( $str ) );
	}
}

if ( ! function_exists( 'sanitize_textarea_field' ) ) {
	function sanitize_textarea_field( string $str ): string {
		return trim( strip_tags( $str ) );
	}
}

if ( ! function_exists( 'wp_unslash' ) ) {
	function wp_unslash( $value ) {
		return is_string( $value ) ? stripslashes( $value ) : $value;
	}
}

if ( ! function_exists( 'absint' ) ) {
	function absint( $maybeint ): int {
		return abs( (int) $maybeint );
	}
}

if ( ! function_exists( 'selected' ) ) {
	function selected( $selected, $current = true, bool $display = true ): string {
		$result = (string) $selected === (string) $current ? ' selected="selected"' : '';
		if ( $display ) {
			echo $result;
		}
		return $result;
	}
}

if ( ! function_exists( 'checked' ) ) {
	function checked( $checked, $current = true, bool $display = true ): string {
		$result = (string) $checked === (string) $current ? ' checked="checked"' : '';
		if ( $display ) {
			echo $result;
		}
		return $result;
	}
}

if ( ! function_exists( 'get_option' ) ) {
	function get_option( string $option, $default = false ) {
		global $wp_options;
		return $wp_options[ $option ] ?? $default;
	}
}

if ( ! function_exists( 'update_option' ) ) {
	function update_option( string $option, $value, $autoload = null ): bool {
		global $wp_options;
		$wp_options[ $option ] = $value;
		return true;
	}
}

if ( ! function_exists( 'delete_option' ) ) {
	function delete_option( string $option ): bool {
		global $wp_options;
		unset( $wp_options[ $option ] );
		return true;
	}
}

if ( ! function_exists( 'get_post_meta' ) ) {
	function get_post_meta( int $post_id, string $key = '', bool $single = false ) {
		global $wp_post_meta;
		if ( empty( $key ) ) {
			return $wp_post_meta[ $post_id ] ?? array();
		}
		$value = $wp_post_meta[ $post_id ][ $key ] ?? null;
		return $single ? ( $value ?? '' ) : ( $value !== null ? array( $value ) : array() );
	}
}

if ( ! function_exists( 'update_post_meta' ) ) {
	function update_post_meta( int $post_id, string $meta_key, $meta_value, $prev_value = '' ): bool {
		global $wp_post_meta;
		if ( ! isset( $wp_post_meta[ $post_id ] ) ) {
			$wp_post_meta[ $post_id ] = array();
		}
		$wp_post_meta[ $post_id ][ $meta_key ] = $meta_value;
		return true;
	}
}

if ( ! function_exists( 'delete_post_meta' ) ) {
	function delete_post_meta( int $post_id, string $meta_key, $meta_value = '' ): bool {
		global $wp_post_meta;
		if ( isset( $wp_post_meta[ $post_id ][ $meta_key ] ) ) {
			unset( $wp_post_meta[ $post_id ][ $meta_key ] );
		}
		return true;
	}
}

if ( ! function_exists( 'add_action' ) ) {
	function add_action( string $hook_name, $callback, int $priority = 10, int $accepted_args = 1 ): bool {
		global $wp_actions;
		if ( ! isset( $wp_actions[ $hook_name ] ) ) {
			$wp_actions[ $hook_name ] = array();
		}
		$wp_actions[ $hook_name ][] = array(
			'callback'      => $callback,
			'priority'      => $priority,
			'accepted_args' => $accepted_args,
		);
		return true;
	}
}

if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( string $hook_name, $callback, int $priority = 10, int $accepted_args = 1 ): bool {
		global $wp_filters;
		if ( ! isset( $wp_filters[ $hook_name ] ) ) {
			$wp_filters[ $hook_name ] = array();
		}
		$wp_filters[ $hook_name ][] = array(
			'callback'      => $callback,
			'priority'      => $priority,
			'accepted_args' => $accepted_args,
		);
		return true;
	}
}

if ( ! function_exists( 'do_action' ) ) {
	function do_action( string $hook_name, ...$args ): void {
		global $wp_actions;
		if ( isset( $wp_actions[ $hook_name ] ) ) {
			foreach ( $wp_actions[ $hook_name ] as $action ) {
				call_user_func_array( $action['callback'], array_slice( $args, 0, $action['accepted_args'] ) );
			}
		}
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( string $hook_name, $value, ...$args ) {
		global $wp_filters;
		if ( isset( $wp_filters[ $hook_name ] ) ) {
			foreach ( $wp_filters[ $hook_name ] as $filter ) {
				$value = call_user_func_array( $filter['callback'], array_merge( array( $value ), array_slice( $args, 0, $filter['accepted_args'] - 1 ) ) );
			}
		}
		return $value;
	}
}

if ( ! function_exists( 'add_meta_box' ) ) {
	function add_meta_box( string $id, string $title, callable $callback, $screen = null, string $context = 'advanced', string $priority = 'default', ?array $callback_args = null ): void {
		global $wp_meta_boxes;
		$wp_meta_boxes[ $id ] = array(
			'id'       => $id,
			'title'    => $title,
			'callback' => $callback,
			'screen'   => $screen,
			'context'  => $context,
			'priority' => $priority,
		);
	}
}

if ( ! function_exists( 'add_options_page' ) ) {
	function add_options_page( string $page_title, string $menu_title, string $capability, string $menu_slug, ?callable $callback = null, ?int $position = null ): string {
		return $menu_slug;
	}
}

if ( ! function_exists( 'register_setting' ) ) {
	function register_setting( string $option_group, string $option_name, $args = array() ): void {
		// Mock.
	}
}

if ( ! function_exists( 'add_settings_section' ) ) {
	function add_settings_section( string $id, string $title, $callback, string $page, array $args = array() ): void {
		// Mock.
	}
}

if ( ! function_exists( 'add_settings_field' ) ) {
	function add_settings_field( string $id, string $title, callable $callback, string $page, string $section = 'default', array $args = array() ): void {
		// Mock.
	}
}

if ( ! function_exists( 'settings_fields' ) ) {
	function settings_fields( string $option_group ): void {
		echo '<input type="hidden" name="option_page" value="' . esc_attr( $option_group ) . '" />';
	}
}

if ( ! function_exists( 'do_settings_sections' ) ) {
	function do_settings_sections( string $page ): void {
		// Mock.
	}
}

if ( ! function_exists( 'submit_button' ) ) {
	function submit_button( ?string $text = null, string $type = 'primary', string $name = 'submit', bool $wrap = true, $other_attributes = null ): void {
		echo '<input type="submit" name="' . esc_attr( $name ) . '" value="' . esc_attr( $text ?? 'Save Changes' ) . '" class="button button-' . esc_attr( $type ) . '" />';
	}
}

if ( ! function_exists( 'is_admin' ) ) {
	function is_admin(): bool {
		return true;
	}
}

/**
 * Mock get_current_screen() - returns null by default in tests.
 * Tests can override this by setting $GLOBALS['current_screen'].
 */
if ( ! function_exists( 'get_current_screen' ) ) {
	function get_current_screen(): ?object {
		if ( isset( $GLOBALS['current_screen'] ) ) {
			return $GLOBALS['current_screen'];
		}
		return null;
	}
}

/**
 * Helper to set current screen for testing.
 *
 * @param string $screen_id The screen ID to simulate.
 * @return object The mock screen object.
 */
function set_current_screen( string $screen_id ): object {
	$screen     = new stdClass();
	$screen->id = $screen_id;
	$GLOBALS['current_screen'] = $screen;
	return $screen;
}

/**
 * Reset current screen.
 */
function reset_current_screen(): void {
	unset( $GLOBALS['current_screen'] );
}

if ( ! function_exists( 'current_user_can' ) ) {
	function current_user_can( string $capability, ...$args ): bool {
		return true;
	}
}

if ( ! function_exists( 'wp_verify_nonce' ) ) {
	function wp_verify_nonce( $nonce, $action = -1 ): bool {
		return true; // Always valid in tests.
	}
}

if ( ! function_exists( 'wp_create_nonce' ) ) {
	function wp_create_nonce( $action = -1 ): string {
		return 'mock_nonce_' . md5( (string) $action );
	}
}

if ( ! function_exists( 'check_admin_referer' ) ) {
	function check_admin_referer( $action = -1, string $query_arg = '_wpnonce' ): bool {
		return true;
	}
}

if ( ! function_exists( 'plugin_basename' ) ) {
	function plugin_basename( string $file ): string {
		return 'dianxiaomi/' . basename( $file );
	}
}

if ( ! function_exists( 'wp_die' ) ) {
	function wp_die( $message = '', $title = '', $args = array() ): void {
		throw new Exception( $message );
	}
}

// =============================================================================
// MOCK HELPER CLASS
// =============================================================================

/**
 * Mock Dianxiaomi_Dependencies class.
 */
if ( ! class_exists( 'Dianxiaomi_Dependencies' ) ) {
	class Dianxiaomi_Dependencies {
		public static function woocommerce_active_check(): bool {
			return true;
		}

		public static function plugin_active_check( $paths ): bool {
			return true;
		}
	}
}

// =============================================================================
// TEST HELPER FUNCTIONS
// =============================================================================

/**
 * Reset all enqueued assets.
 */
function reset_wp_enqueues(): void {
	global $wp_scripts_enqueued, $wp_styles_enqueued;
	$wp_scripts_enqueued = array();
	$wp_styles_enqueued  = array();
}

/**
 * Reset all WooCommerce data.
 */
function reset_wc_data(): void {
	global $wc_orders, $wc_products;
	$wc_orders   = array();
	$wc_products = array();
}

/**
 * Reset all WordPress options.
 */
function reset_wp_options(): void {
	global $wp_options;
	$wp_options = array();
}

/**
 * Reset all post meta.
 */
function reset_wp_post_meta(): void {
	global $wp_post_meta;
	$wp_post_meta = array();
}

/**
 * Reset everything for a clean test.
 */
function reset_all(): void {
	reset_wp_enqueues();
	reset_wc_data();
	reset_wp_options();
	reset_wp_post_meta();
	reset_current_screen();
}

/**
 * Check if a script was enqueued.
 */
function was_script_enqueued( string $handle ): bool {
	global $wp_scripts_enqueued;
	return isset( $wp_scripts_enqueued[ $handle ] );
}

/**
 * Check if a style was enqueued.
 */
function was_style_enqueued( string $handle ): bool {
	global $wp_styles_enqueued;
	return isset( $wp_styles_enqueued[ $handle ] );
}

/**
 * Get enqueued script data.
 */
function get_enqueued_script( string $handle ): ?array {
	global $wp_scripts_enqueued;
	return $wp_scripts_enqueued[ $handle ] ?? null;
}

/**
 * Get enqueued style data.
 */
function get_enqueued_style( string $handle ): ?array {
	global $wp_styles_enqueued;
	return $wp_styles_enqueued[ $handle ] ?? null;
}

/**
 * Create a mock order for testing.
 */
function create_mock_order( int $id = 1, array $meta = array() ): WC_Order {
	global $wc_orders;
	$order = new WC_Order( $id );
	if ( ! empty( $meta ) ) {
		$order->set_meta_data_array( $meta );
	}
	$wc_orders[ $id ] = $order;
	return $order;
}

/**
 * Set WordPress options for testing.
 */
function set_wp_option( string $key, $value ): void {
	global $wp_options;
	$wp_options[ $key ] = $value;
}

/**
 * Set post meta for testing.
 */
function set_post_meta( int $post_id, string $key, $value ): void {
	global $wp_post_meta;
	if ( ! isset( $wp_post_meta[ $post_id ] ) ) {
		$wp_post_meta[ $post_id ] = array();
	}
	$wp_post_meta[ $post_id ][ $key ] = $value;
}

// =============================================================================
// SIMULATE WOOCOMMERCE REGISTERED SCRIPTS
// =============================================================================

// Register selectWoo as WooCommerce would.
wp_register_script( 'selectWoo', 'https://example.com/wp-content/plugins/woocommerce/assets/js/selectWoo/selectWoo.full.min.js', array( 'jquery' ), '1.0.9' );
wp_register_style( 'select2', 'https://example.com/wp-content/plugins/woocommerce/assets/css/select2.css', array(), '4.0.3' );

// Load Composer autoloader.
require_once dirname( __DIR__ ) . '/vendor/autoload.php';
