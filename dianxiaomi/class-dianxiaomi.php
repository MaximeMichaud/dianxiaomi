<?php
/**
 * Main Dianxiaomi class.
 *
 * @package Dianxiaomi
 */

declare(strict_types=1);

use Dianxiaomi\Interfaces\Subscriber_Interface;

final class Dianxiaomi implements Subscriber_Interface {
	protected static ?Dianxiaomi $_instance = null;

	/** @var Dianxiaomi_API API instance */
	public Dianxiaomi_API $api;

	/** @var array<string, mixed> Dianxiaomi fields configuration */
	public array $dianxiaomi_fields;

	/** @var string Plugin identifier */
	public string $plugin = '';

	/** @var bool Whether to use track button */
	public bool $use_track_button = false;

	/** @var string Custom domain for tracking */
	public string $custom_domain = '';

	/** @var array<int, string> Available couriers */
	public array $couriers = array();

	/**
	 * Singleton instance method.
	 */
	public static function instance(): Dianxiaomi {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * Constructor: Initialize the plugin.
	 */
	public function __construct() {
		$this->includes();
		$this->api = new Dianxiaomi_API();
		$this->initialize_options();
	}

	/**
	 * Include necessary files.
	 */
	private function includes(): void {
		include_once 'dianxiaomi-fields.php';
		// phpcs:ignore WordPress.PHP.DontExtract.extract_undefined
		/** @var array<string, mixed> $fields_array */
		$fields_array            = isset( $dianxiaomi_fields ) && is_array( $dianxiaomi_fields ) ? $dianxiaomi_fields : array();
		$this->dianxiaomi_fields = $fields_array;

		include_once 'class-dianxiaomi-api.php';
		include_once 'class-dianxiaomi-settings.php';
	}

	/**
	 * Load and set options.
	 */
	private function initialize_options(): void {
		$options = get_option( 'dianxiaomi_option_name' );
		if ( is_array( $options ) ) {
			$this->plugin           = isset( $options['plugin'] ) && is_string( $options['plugin'] ) ? $options['plugin'] : '';
			$this->use_track_button = ! empty( $options['use_track_button'] );
			$this->custom_domain    = isset( $options['custom_domain'] ) && is_string( $options['custom_domain'] ) ? $options['custom_domain'] : '';

			// Handle couriers as string (comma-separated) or array.
			$couriers = $options['couriers'] ?? array();
			if ( is_string( $couriers ) ) {
				$this->couriers = array_filter( array_map( 'trim', explode( ',', $couriers ) ) );
			} elseif ( is_array( $couriers ) ) {
				/** @var array<int, string> $couriers */
				$this->couriers = $couriers;
			} else {
				$this->couriers = array();
			}

			$this->register_hooks();
		}
	}

	/**
	 * Get subscribed events for the Event Manager.
	 *
	 * @return array<string, string|array<int, int|string>>
	 */
	public static function get_subscribed_events(): array {
		return array(
			'admin_print_scripts'              => 'library_scripts',
			'in_admin_footer'                  => 'include_footer_script',
			'admin_print_styles'               => 'admin_styles',
			'add_meta_boxes'                   => 'add_meta_box',
			'woocommerce_process_shop_order_meta' => array( 'save_meta_box', 0, 2 ),
			'plugins_loaded'                   => 'load_plugin_textdomain',
			'woocommerce_view_order'           => 'display_tracking_info',
			'woocommerce_email_before_order_table' => 'email_display',
		);
	}

	/**
	 * Register hooks using Event Manager.
	 *
	 * @deprecated 1.41 Use Event_Manager::add_subscriber() instead.
	 */
	private function register_hooks(): void {
		$event_manager = new \Dianxiaomi\EventManagement\Event_Manager();
		$event_manager->add_subscriber( $this );
	}

	/**
	 * Load plugin textdomain for translations.
	 */
	public function load_plugin_textdomain(): void {
		load_plugin_textdomain( 'dianxiaomi', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	}

	/**
	 * Check if current screen is a WooCommerce order page.
	 *
	 * @return bool True if on order edit screen.
	 */
	private function is_order_screen(): bool {
		$screen = get_current_screen();
		if ( null === $screen ) {
			return false;
		}
		// Support both legacy (shop_order) and HPOS (woocommerce_page_wc-orders) screens.
		return in_array( $screen->id, array( 'shop_order', 'woocommerce_page_wc-orders' ), true );
	}

	/**
	 * Enqueue admin styles for order pages only.
	 */
	public function admin_styles(): void {
		if ( ! $this->is_order_screen() ) {
			return;
		}
		$version = '1.5.4'; // plugin version
		// Use WooCommerce's SelectWoo/Select2 styles (already bundled).
		wp_enqueue_style( 'select2' );
		wp_enqueue_style( 'dianxiaomi_styles', plugin_dir_url( __FILE__ ) . 'assets/css/admin.css', array(), $version );
	}

	/**
	 * Enqueue scripts for order pages only.
	 */
	public function library_scripts(): void {
		if ( ! $this->is_order_screen() ) {
			return;
		}
		$version = '1.5.4'; // plugin version
		// Use WooCommerce's SelectWoo (already bundled).
		wp_enqueue_script( 'selectWoo' );
		wp_enqueue_script( 'dianxiaomi_script_util', plugin_dir_url( __FILE__ ) . 'assets/js/util.js', array(), $version, true );
		wp_enqueue_script( 'dianxiaomi_script_couriers', plugin_dir_url( __FILE__ ) . 'assets/js/couriers.js', array(), $version, true );
		wp_enqueue_script( 'dianxiaomi_script_admin', plugin_dir_url( __FILE__ ) . 'assets/js/admin.js', array( 'selectWoo' ), $version, true );
	}

	/**
	 * Enqueue footer scripts for order pages only.
	 */
	public function include_footer_script(): void {
		if ( ! $this->is_order_screen() ) {
			return;
		}
		$version = '1.5.4'; // plugin version
		wp_enqueue_script( 'dianxiaomi_script_footer', plugin_dir_url( __FILE__ ) . 'assets/js/footer.js', array(), $version, true );
	}

	/**
	 * Add a meta box for shipment info on the order page.
	 */
	public function add_meta_box(): void {
		add_meta_box( 'woocommerce-dianxiaomi', __( 'Dianxiaomi', 'wc_dianxiaomi' ), array( $this, 'meta_box' ), 'shop_order', 'side', 'high' );
	}

	/**
	 * Display the meta box for shipment info on the order page.
	 */
	public function meta_box(): void {
		global $post;
		// HPOS compatible: get order object.
		if ( ! $post instanceof WP_Post ) {
			return;
		}
		$order = wc_get_order( $post->ID );
		if ( ! $order instanceof WC_Order ) {
			return;
		}

		$selected_provider = $order->get_meta( '_dianxiaomi_tracking_provider', true );

		// Security nonce for save_meta_box().
		wp_nonce_field( 'dianxiaomi_save_meta_box', 'dianxiaomi_nonce' );

		echo '<div id="dianxiaomi_wrapper">';
		echo '<p class="form-field"><label for="dianxiaomi_tracking_provider">' . esc_html__( 'Carrier:', 'wc_dianxiaomi' ) . '</label><br/><select id="dianxiaomi_tracking_provider" name="dianxiaomi_tracking_provider" class="wc-enhanced-select" style="width:100%">';
		if ( $selected_provider === '' ) {
			$selected_text = 'selected="selected"';
		} else {
			$selected_text = '';
		}
		echo '<br><a href="options-general.php?page=dianxiaomi-setting-admin">' . esc_html__( 'Update carrier list', 'wc_dianxiaomi' ) . '</a>';
		echo '</select>';
		echo '<br><a href="options-general.php?page=dianxiaomi-setting-admin">' . esc_html__( 'Update carrier list', 'wc_dianxiaomi' ) . '</a>';
		$provider_value = is_string( $selected_provider ) ? $selected_provider : '';
		echo '<input type="hidden" id="dianxiaomi_tracking_provider_hidden" value="' . esc_attr( $provider_value ) . '"/>';
		$couriers_json = wp_json_encode( $this->couriers );
		echo '<input type="hidden" id="dianxiaomi_couriers_selected" value="' . esc_attr( $couriers_json !== false ? $couriers_json : '[]' ) . '"/>';

		foreach ( $this->dianxiaomi_fields as $field ) {
			if ( ! is_array( $field ) ) {
				continue;
			}
			$field_id          = isset( $field['id'] ) && is_string( $field['id'] ) ? $field['id'] : '';
			$field_type        = isset( $field['type'] ) && is_string( $field['type'] ) ? $field['type'] : '';
			$field_label       = isset( $field['label'] ) && is_string( $field['label'] ) ? $field['label'] : '';
			$field_placeholder = isset( $field['placeholder'] ) && is_string( $field['placeholder'] ) ? $field['placeholder'] : '';
			$field_description = isset( $field['description'] ) && is_string( $field['description'] ) ? $field['description'] : '';
			$field_class       = isset( $field['class'] ) && is_string( $field['class'] ) ? $field['class'] : '';

			if ( $field_type === 'date' ) {
				$date       = $order->get_meta( '_' . $field_id, true );
				$date_value = is_numeric( $date ) ? (int) $date : null;
				woocommerce_wp_text_input(
					array(
						'id'          => $field_id,
						'label'       => esc_html( $field_label ),
						'placeholder' => $field_placeholder,
						'description' => $field_description,
						'class'       => $field_class,
						'value'       => $date_value ? dianxiaomi_wpdate( 'Y-m-d', $date_value ) : '',
					)
				);
			} else {
				$meta_value = $order->get_meta( '_' . $field_id, true );
				woocommerce_wp_text_input(
					array(
						'id'          => $field_id,
						'label'       => esc_html( $field_label ),
						'placeholder' => $field_placeholder,
						'description' => $field_description,
						'class'       => $field_class,
						'value'       => is_string( $meta_value ) ? $meta_value : '',
					)
				);
			}
		}
		echo '</div>'; // End of dianxiaomi_wrapper
	}

	/**
	 * Order Downloads Save.
	 *
	 * Function for processing and storing all order downloads.
	 *
	 * @param int              $post_id Order/Post ID.
	 * @param WP_Post|WC_Order $post    Post object (legacy) or Order object (HPOS).
	 */
	public function save_meta_box( int $post_id, object $post ): void {
		// Vérifier le nonce pour la sécurité
		// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce verified below, sanitized on next line.
		$nonce_raw        = isset( $_POST['dianxiaomi_nonce'] ) ? wp_unslash( $_POST['dianxiaomi_nonce'] ) : '';
		$dianxiaomi_nonce = is_string( $nonce_raw ) ? sanitize_text_field( $nonce_raw ) : '';
		if ( ! $dianxiaomi_nonce || ! wp_verify_nonce( $dianxiaomi_nonce, 'dianxiaomi_save_meta_box' ) ) {
			return;
		}

		// HPOS compatible: get order object (works for both WP_Post and WC_Order).
		$order = $post instanceof WC_Order ? $post : wc_get_order( $post_id );
		if ( ! $order instanceof WC_Order ) {
			return;
		}

		if ( isset( $_POST['dianxiaomi_tracking_number'] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized on next line.
			$provider_raw      = isset( $_POST['dianxiaomi_tracking_provider'] ) ? wp_unslash( $_POST['dianxiaomi_tracking_provider'] ) : '';
			$tracking_provider = is_string( $provider_raw ) ? sanitize_text_field( $provider_raw ) : '';
			$order->update_meta_data( '_dianxiaomi_tracking_provider', $tracking_provider );

			foreach ( $this->dianxiaomi_fields as $field ) {
				if ( ! is_array( $field ) ) {
					continue;
				}
				$field_id   = isset( $field['id'] ) && is_string( $field['id'] ) ? $field['id'] : '';
				$field_type = isset( $field['type'] ) && is_string( $field['type'] ) ? $field['type'] : '';
				if ( $field_id === '' ) {
					continue;
				}
				// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized on next line.
				$raw_value   = isset( $_POST[ $field_id ] ) ? wp_unslash( $_POST[ $field_id ] ) : '';
				$field_value = is_string( $raw_value ) ? sanitize_text_field( $raw_value ) : '';
				if ( $field_type === 'date' ) {
					$field_value = (string) strtotime( $field_value );
				}
				$order->update_meta_data( '_' . $field_id, sanitize_text_field( $field_value ) );
			}

			// HPOS: save all meta changes at once (more efficient).
			$order->save();
		}
	}

	/**
	 * Display the API key info for a user.
	 *
	 * @param WP_User $user
	 */
	public function add_api_key_field( WP_User $user ): void {
		if ( ! current_user_can( 'manage_dianxiaomi' ) || ! current_user_can( 'edit_user', $user->ID ) ) {
			return;
		}

		echo '<h3>Dianxiaomi</h3>';
		echo '<table class="form-table">';
		echo '<tbody>';
		echo '<tr>';
		echo '<th><label for="dianxiaomi_wp_api_key">' . esc_html__( 'Dianxiaomi\'s WordPress API Key', 'dianxiaomi' ) . '</label></th>';
		echo '<td>';
		$api_key = $user->get( 'dianxiaomi_wp_api_key' );
		$api_key = is_string( $api_key ) ? $api_key : '';
		if ( empty( $api_key ) ) {
			echo '<input name="dianxiaomi_wp_generate_api_key" type="checkbox" id="dianxiaomi_wp_generate_api_key" value="0" />';
			echo '<span class="description">' . esc_html__( 'Generate API Key', 'dianxiaomi' ) . '</span>';
		} else {
			echo '<code id="dianxiaomi_wp_api_key">' . esc_html( $api_key ) . '</code><br />';
			echo '<input name="dianxiaomi_wp_generate_api_key" type="checkbox" id="dianxiaomi_wp_generate_api_key" value="0" />';
			echo '<span class="description">' . esc_html__( 'Revoke API Key', 'dianxiaomi' ) . '</span>';
		}
		echo '</td>';
		echo '</tr>';
		echo '</tbody>';
		echo '</table>';
	}

	/**
	 * Generate and save (or delete) the API keys for a user.
	 *
	 * @param int $user_id
	 */
	public function generate_api_key( int $user_id ): void {
		if ( ! current_user_can( 'edit_user', $user_id ) ) {
			return;
		}
		// Vérifier le nonce pour la sécurité
		// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce verified below, sanitized on next line.
		$nonce_raw        = isset( $_POST['dianxiaomi_nonce'] ) ? wp_unslash( $_POST['dianxiaomi_nonce'] ) : '';
		$dianxiaomi_nonce = is_string( $nonce_raw ) ? sanitize_text_field( $nonce_raw ) : '';
		if ( ! $dianxiaomi_nonce || ! wp_verify_nonce( $dianxiaomi_nonce, 'dianxiaomi_generate_api_key' ) ) {
			return;
		}
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}
		if ( isset( $_POST['dianxiaomi_wp_generate_api_key'] ) ) {
			$existing_key = $user->get( 'dianxiaomi_wp_api_key' );
			if ( empty( $existing_key ) ) {
				$api_key = 'ck_' . hash( 'md5', $user->user_login . gmdate( 'U' ) . wp_rand() );
				update_user_meta( $user_id, 'dianxiaomi_wp_api_key', $api_key );
			} else {
				delete_user_meta( $user_id, 'dianxiaomi_wp_api_key' );
			}
		}
	}

	/**
	 * Display Shipment info in the frontend (order view/tracking page).
	 *
	 * @param int  $order_id
	 * @param bool $for_email
	 */
	public function display_tracking_info( int $order_id, bool $for_email = false ): void {
		if ( $this->plugin === 'dianxiaomi' ) {
			$this->display_order_dianxiaomi( $order_id, $for_email );
		} elseif ( $this->plugin === 'wc-shipment-tracking' ) {
			$this->display_order_wc_shipment_tracking( $order_id, $for_email );
		}
	}

	private function display_order_dianxiaomi( int $order_id, bool $for_email ): void {
		$order = wc_get_order( $order_id );
		if ( ! $order instanceof WC_Order ) {
			return;
		}

		$values = array();
		foreach ( $this->dianxiaomi_fields as $field ) {
			if ( ! is_array( $field ) ) {
				continue;
			}
			$field_id   = isset( $field['id'] ) && is_string( $field['id'] ) ? $field['id'] : '';
			$field_type = isset( $field['type'] ) && is_string( $field['type'] ) ? $field['type'] : '';
			if ( $field_id === '' ) {
				continue;
			}
			$meta_value = $order->get_meta( '_' . $field_id );
			$meta_str   = is_string( $meta_value ) ? $meta_value : '';
			if ( $field_type === 'date' && $meta_str !== '' ) {
				$values[ $field_id ] = date_i18n( __( 'l jS F Y', 'wc_shipment_tracking' ), strtotime( $meta_str ) );
			} else {
				$values[ $field_id ] = $meta_str;
			}
		}

		$tracking_provider_meta = $order->get_meta( '_dianxiaomi_tracking_provider' );
		$tracking_number_meta   = $order->get_meta( '_dianxiaomi_tracking_number' );
		$provider_name_meta     = $order->get_meta( '_dianxiaomi_tracking_provider_name' );

		$dianxiaomi_tracking_provider      = is_string( $tracking_provider_meta ) ? $tracking_provider_meta : '';
		$dianxiaomi_tracking_number        = is_string( $tracking_number_meta ) ? $tracking_number_meta : '';
		$dianxiaomi_tracking_provider_name = is_string( $provider_name_meta ) ? $provider_name_meta : '';

		if ( $dianxiaomi_tracking_provider === '' || $dianxiaomi_tracking_number === '' ) {
			return;
		}

		$options         = get_option( 'dianxiaomi_option_name' );
		$track_message_1 = is_array( $options ) && isset( $options['track_message_1'] ) && is_string( $options['track_message_1'] ) ? $options['track_message_1'] : 'Your order was shipped via ';
		$track_message_2 = is_array( $options ) && isset( $options['track_message_2'] ) && is_string( $options['track_message_2'] ) ? $options['track_message_2'] : 'Tracking number is ';

		$required_fields_values = array();
		$required_fields_meta   = $order->get_meta( '_dianxiaomi_tracking_required_fields' );
		$required_fields_str    = is_string( $required_fields_meta ) ? $required_fields_meta : '';
		if ( $required_fields_str !== '' ) {
			$provider_required_fields = explode( ',', $required_fields_str );

			foreach ( $provider_required_fields as $field ) {
				if ( isset( $values[ $field ] ) && is_string( $values[ $field ] ) ) {
					$required_fields_values[] = $values[ $field ];
				}
			}
		}

		$required_fields_msg = ! empty( $required_fields_values ) ? ' (' . join( ', ', $required_fields_values ) . ')' : '';

		$custom_domain = $this->custom_domain !== '' ? $this->custom_domain : 'https://t.17track.net/en#nums=';
		$tracking_url  = $custom_domain . $dianxiaomi_tracking_number;

		echo esc_html( $track_message_1 ) . esc_html( $dianxiaomi_tracking_provider_name ) . '<br/>' . esc_html( $track_message_2 ) . '<a target="_blank" href="' . esc_url( $tracking_url ) . '">' . esc_html( $dianxiaomi_tracking_number ) . '</a>' . esc_html( $required_fields_msg );

		if ( ! $for_email && $this->use_track_button ) {
			$this->display_track_button( $dianxiaomi_tracking_provider, $dianxiaomi_tracking_number, $required_fields_values );
		}
	}

	private function display_order_wc_shipment_tracking( int $order_id, bool $for_email ): void {
		if ( $for_email || ! $this->use_track_button ) {
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order instanceof WC_Order ) {
			return;
		}

		$tracking_meta   = $order->get_meta( '_tracking_number', true );
		$tracking        = is_string( $tracking_meta ) ? $tracking_meta : '';
		if ( $tracking === '' ) {
			return;
		}
		$sharp           = strpos( $tracking, '#' );
		$colon           = strpos( $tracking, ':' );
		$required_fields = array();
		if ( $sharp !== false && $colon !== false && $sharp >= $colon ) {
			return;
		} elseif ( $sharp === false && $colon !== false ) {
			return;
		} elseif ( $sharp !== false ) {
			$tracking_provider = substr( $tracking, 0, $sharp );
			if ( $colon !== false ) {
				$tracking_number = substr( $tracking, $sharp + 1, $colon - $sharp - 1 );
				$temp            = substr( $tracking, $sharp + 1 );
				$required_fields = explode( ':', $temp );
			} else {
				$tracking_number = substr( $tracking, $sharp + 1 );
			}
		} else {
			$tracking_provider = '';
			$tracking_number   = $tracking;
		}
		if ( $tracking_number !== '' ) {
			$this->display_track_button( $tracking_provider, $tracking_number, $required_fields );
		}
	}

	/**
	 * Display shipment info in customer emails.
	 *
	 * @access public
	 */
	public function email_display( WC_Order $order ): void {
		$this->display_tracking_info( $order->get_id(), true );
	}

	/**
	 * Display tracking button with provider info.
	 *
	 * @param string             $tracking_provider      Tracking provider slug.
	 * @param string             $tracking_number        Tracking number.
	 * @param array<int, string> $required_fields_values Additional required field values.
	 */
	private function display_track_button( string $tracking_provider, string $tracking_number, array $required_fields_values ): void {
		$js = '(function(e,t,n){})(document,"script","trackdog-jssdk")';

		$handle = 'dianxiaomi-trackdog';
		wp_register_script( $handle, '', array(), DIANXIAOMI_VERSION, true );
		wp_enqueue_script( $handle );
		wp_add_inline_script( $handle, $js );

		if ( count( $required_fields_values ) ) {
			$tracking_number = $tracking_number . ':' . join( ':', $required_fields_values );
		}

		$temp_url  = '';
		$temp_slug = ' data-slug="' . $tracking_provider . '"';
		if ( $this->custom_domain !== '' ) {
			$temp_url  = '" data-domain="' . $this->custom_domain;
			$temp_slug = '';
		}

		$this->display_track_button_html( $this->custom_domain, $tracking_number, $tracking_provider );

		echo '<br><br>';
	}


	private function display_track_button_html( string $custom_domain, string $tracking_number, string $tracking_provider ): void {
		$css = '<style>.btn{position:relative; border-radius: 4px;text-decoration: none !important; border:2px solid #1e88e5;text-align:left;background-color:#1e88e5;color:#fff !important;font-family: "Helvetica Neue",Helvetica,Arial,sans-serif;} .btn:hover{border-color: #1c95ff;background-color: #1c95ff;} .btn span{font-size: 16px;vertical-align: middle;}.btn.a:focus,.btn.a:hover{border-color:#1c95ff;background-color:#1c95ff;}.btn.a{padding:10px 6px 12px;border-radius:4px;outline:0} .btn.a{background-color:transparent}.btn.a:active,.btn.a:hover{outline:0}*,:after,:before{box-sizing:border-box}.tracking-widget .fluid-input-wrapper{display:block;overflow:hidden}.-has-tracking-number .fluid-input-wrapper{float:left}.tracking-widget input{padding:2px 6px 3px;width:100%}.tracking-widget .btn{float:right;padding:4px 10px 3px 36px;margin-left:7px}.tracking-widget .-has-tracking-number .btn,.tracking-widget .-hidden-tracking-number .btn{float:none}.tracking-widget .text-large{font-size:17.5px;padding:10px 6px 12px}.tracking-widget .btn-large{font-size:17.5px;padding:10px 20px 12px 58px}.tracking-widget .text-small{padding:2px 6px 3px;font-size:12px}.tracking-widget .btn-small{padding:2px 10px 3px 32px;font-size:12px}.icon-trackdog{left:9px;top:7px;width:17px;height:19px}.tracking-widget .btn-small .icon-trackdog{left:9px;top:7px;height:19px;width:16px}.icon-trackdog,.icon-trackdog.-large{height:28px;width:24px}.tracking-widget .btn-large .icon-trackdog{left:20px;top:7px;height:28px;width:24px}.ie9 .tracking-widget .btn-small .icon-trackdog{top:0}.-hidden-tracking-number .btn{margin-left:0}.tracking-widget+.tracking-widget{margin-top:20px}.icon-trackdog{position:absolute;display:inline-block;background-repeat:no-repeat;background-position:0 0}.tracking-widget .icon-trackdog{height:21px}.tracking-copyright{font-size:12px;padding:3px 3px 0;text-align:left}.tracking-preset{line-height:28px}.tracking-preset.large{line-height:47px}.tracking-preset.small{font-size:14px;line-height:24px} .tracking-widget .btn{padding: 1px 20px;}</style>';

		echo wp_kses_post( $css );

		// Build tracking URL.
		$go_url = $custom_domain;

		// Check if 17track and add tracking params.
		if ( strpos( $custom_domain, '17track' ) !== false ) {
			$go_url = $custom_domain . $tracking_number . '&pf=wc_d&pf_c=' . rawurlencode( $tracking_provider );
		}

		// Only add protocol prefix if URL doesn't already have a scheme.
		$has_scheme = preg_match( '#^https?://#i', $go_url ) === 1;
		$href       = $has_scheme ? esc_url( $go_url ) : '//' . esc_url( $go_url );

		// Show track button.
		$html = '<div class="tracking-widget"><div class="tracking-widget -has-tracking-number"><a class="btn" href="' . $href . '" target="_blank"><span class="btn_text">' . esc_html__( 'Track', 'wc_dianxiaomi' ) . '</span></a></div></div>';
		echo wp_kses_post( $html );
	}
}
