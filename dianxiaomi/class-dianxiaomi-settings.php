<?php
/**
 * Dianxiaomi Admin.
 *
 * Handles Dianxiaomi-Admin endpoint requests
 *
 * @author      Dianxiaomi
 *
 * @category    Admin
 * @package     Dianxiaomi
 *
 * @since       1.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

if ( ! class_exists( 'Dianxiaomi_Dependencies' ) ) {
	require_once 'class-dianxiaomi-dependencies.php';
}

use Dianxiaomi\Interfaces\Subscriber_Interface;

class Dianxiaomi_Settings implements Subscriber_Interface {
	/**
	 * Settings page slug.
	 */
	private const PAGE_SLUG = 'dianxiaomi-setting-admin';

	/** @var array<string, mixed> */
	private array $options;

	/** @var array<int, array<string, mixed>> */
	private array $plugins;

	public function __construct() {
		$this->plugins = array(
			array(
				'value' => 'dianxiaomi',
				'label' => 'Dianxiaomi',
				'path'  => 'dianxiaomi/dianxiaomi.php',
			),
			array(
				'value' => 'wc-shipment-tracking',
				'label' => 'WooCommerce Shipment Tracking',
				'path'  => array( 'woocommerce-shipment-tracking/shipment-tracking.php', 'woocommerce-shipment-tracking/woocommerce-shipment-tracking.php' ),
			),
		);

		$event_manager = new \Dianxiaomi\EventManagement\Event_Manager();
		$event_manager->add_subscriber( $this );
	}

	/**
	 * Get subscribed events for the Event Manager.
	 *
	 * @return array<string, string>
	 */
	public static function get_subscribed_events(): array {
		return array(
			'admin_menu'          => 'add_plugin_page',
			'admin_init'          => 'page_init',
			'admin_print_styles'  => 'admin_styles',
			'admin_print_scripts' => 'library_scripts',
		);
	}

	/**
	 * Check if current screen is the settings page.
	 *
	 * @return bool True if on settings page.
	 */
	private function is_settings_screen(): bool {
		$screen = get_current_screen();
		if ( null === $screen ) {
			return false;
		}
		return 'settings_page_' . self::PAGE_SLUG === $screen->id;
	}

	/**
	 * Enqueue admin styles for settings page only.
	 */
	public function admin_styles(): void {
		if ( ! $this->is_settings_screen() ) {
			return;
		}
		$version = '1.0.0';
		// Use WooCommerce's SelectWoo/Select2 styles (already bundled).
		wp_enqueue_style( 'select2' );
		wp_enqueue_style( 'dianxiaomi_settings_styles', plugins_url( basename( __DIR__ ) ) . '/assets/css/admin.css', array(), $version );
	}

	/**
	 * Enqueue scripts for settings page only.
	 */
	public function library_scripts(): void {
		if ( ! $this->is_settings_screen() ) {
			return;
		}
		$version = '1.0.0';
		// Use WooCommerce's SelectWoo (already bundled).
		wp_enqueue_script( 'selectWoo' );
		wp_enqueue_script( 'dianxiaomi_settings_util', plugins_url( basename( __DIR__ ) ) . '/assets/js/util.js', array(), $version, true );
		wp_enqueue_script( 'dianxiaomi_settings_couriers', plugins_url( basename( __DIR__ ) ) . '/assets/js/couriers.js', array(), $version, true );
		wp_enqueue_script( 'dianxiaomi_settings_script', plugins_url( basename( __DIR__ ) ) . '/assets/js/setting.js', array( 'selectWoo' ), $version, true );
	}

	public function add_plugin_page(): void {
		add_options_page(
			'Dianxiaomi Settings Admin',
			'Dianxiaomi',
			'manage_options',
			'dianxiaomi-setting-admin',
			array( $this, 'create_admin_page' )
		);
	}

	public function create_admin_page(): void {
		$options       = get_option( 'dianxiaomi_option_name' );
		$this->options = is_array( $options ) ? $options : array();
		?>
		<div class="wrap">
			<h2>Dianxiaomi Settings</h2>
			<form method="post" action="options.php">
				<?php
				settings_fields( 'dianxiaomi_option_group' );
				do_settings_sections( 'dianxiaomi-setting-admin' );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	public function page_init(): void {
		register_setting(
			'dianxiaomi_option_group',
			'dianxiaomi_option_name',
			array( $this, 'sanitize' )
		);

		add_settings_section(
			'dianxiaomi_setting_section_id',
			'',
			array( $this, 'print_section_info' ),
			'dianxiaomi-setting-admin'
		);

		add_settings_field(
			'plugin',
			'Plugin',
			array( $this, 'plugin_callback' ),
			'dianxiaomi-setting-admin',
			'dianxiaomi_setting_section_id'
		);

		add_settings_field(
			'couriers',
			'Couriers',
			array( $this, 'couriers_callback' ),
			'dianxiaomi-setting-admin',
			'dianxiaomi_setting_section_id'
		);

		add_settings_field(
			'use_track_button',
			'Display Track Button at Order History Page',
			array( $this, 'track_button_callback' ),
			'dianxiaomi-setting-admin',
			'dianxiaomi_setting_section_id'
		);

		add_settings_field(
			'custom_domain',
			'Display Tracking Information at Custom Domain',
			array( $this, 'custom_domain_callback' ),
			'dianxiaomi-setting-admin',
			'dianxiaomi_setting_section_id'
		);

		add_settings_field(
			'track_message',
			'Content',
			array( $this, 'track_message_callback' ),
			'dianxiaomi-setting-admin',
			'dianxiaomi_setting_section_id'
		);
	}

	public function sanitize( array $input ): array {
		$new_input = array();

		if ( isset( $input['couriers'] ) ) {
			$new_input['couriers'] = sanitize_text_field( $input['couriers'] );
		}

		if ( isset( $input['custom_domain'] ) ) {
			$new_input['custom_domain'] = sanitize_text_field( $input['custom_domain'] );
		}

		if ( isset( $input['plugin'] ) ) {
			$new_input['plugin'] = sanitize_text_field( $input['plugin'] );
		}

		if ( isset( $input['track_message_1'] ) ) {
			$postfix                      = substr( $input['track_message_1'], -1 ) === ' ' ? ' ' : '';
			$new_input['track_message_1'] = sanitize_text_field( $input['track_message_1'] ) . $postfix;
		}

		if ( isset( $input['track_message_2'] ) ) {
			$postfix                      = substr( $input['track_message_2'], -1 ) === ' ' ? ' ' : '';
			$new_input['track_message_2'] = sanitize_text_field( $input['track_message_2'] ) . $postfix;
		}

		if ( isset( $input['use_track_button'] ) ) {
			$new_input['use_track_button'] = true;
		}

		return $new_input;
	}

	public function print_section_info(): void {
		echo '<p>' . esc_html__( 'Enter your settings below:', 'dianxiaomi' ) . '</p>';
	}

	public function couriers_callback(): void {
		$couriers_option = isset( $this->options['couriers'] ) && is_string( $this->options['couriers'] ) ? $this->options['couriers'] : '';
		$couriers        = $couriers_option !== '' ? explode( ',', $couriers_option ) : array();
		echo '<select data-placeholder="Please select couriers" id="couriers_select" class="wc-enhanced-select" multiple style="width:100%">';
		echo '</select>';
		echo '<input type="hidden" id="couriers" name="dianxiaomi_option_name[couriers]" value="' . esc_attr( implode( ',', $couriers ) ) . '"/>';
		echo '<br><a href="https://www.dianxiaomi.com/settings/courier" target="_blank">' . esc_html__( 'Update carrier list', 'dianxiaomi' ) . '</a>';
	}

	public function plugin_callback(): void {
		$options       = '';
		$plugin_option = isset( $this->options['plugin'] ) && is_string( $this->options['plugin'] ) ? $this->options['plugin'] : '';
		foreach ( $this->plugins as $plugin ) {
			$path  = isset( $plugin['path'] ) && is_string( $plugin['path'] ) ? $plugin['path'] : '';
			$value = isset( $plugin['value'] ) && is_string( $plugin['value'] ) ? $plugin['value'] : '';
			$label = isset( $plugin['label'] ) && is_string( $plugin['label'] ) ? $plugin['label'] : '';
			if ( $path !== '' && Dianxiaomi_Dependencies::plugin_active_check( $path ) ) {
				$option = '<option value="' . esc_attr( $value ) . '"';
				if ( $plugin_option === $value ) {
					$option .= ' selected="selected"';
				}
				$option  .= '>' . esc_html( $label ) . '</option>';
				$options .= $option;
			}
		}
		printf( '<select id="plugin" name="dianxiaomi_option_name[plugin]" class="dianxiaomi_dropdown">' . esc_html( $options ) . '</select>' );
	}

	public function custom_domain_callback(): void {
		$custom_domain = isset( $this->options['custom_domain'] ) && is_string( $this->options['custom_domain'] ) ? $this->options['custom_domain'] : 'https://t.17track.net/zh-cn#nums=';
		printf(
			'<input type="text" id="custom_domain" name="dianxiaomi_option_name[custom_domain]" value="%s" style="width:100%%">',
			esc_attr( $custom_domain )
		);
	}

	public function track_message_callback(): void {
		$track_message_1 = isset( $this->options['track_message_1'] ) && is_string( $this->options['track_message_1'] ) ? $this->options['track_message_1'] : 'Your order was shipped via ';
		$track_message_2 = isset( $this->options['track_message_2'] ) && is_string( $this->options['track_message_2'] ) ? $this->options['track_message_2'] : 'Tracking number is ';
		printf(
			'<input type="text" id="track_message_1" name="dianxiaomi_option_name[track_message_1]" value="%s" style="width:100%%">',
			esc_attr( $track_message_1 )
		);
		echo '<br/>';
		printf(
			'<input type="text" id="track_message_2" name="dianxiaomi_option_name[track_message_2]" value="%s" style="width:100%%">',
			esc_attr( $track_message_2 )
		);
		echo '<br/><br/><b>Demo:</b>';
		printf(
			'<div id="track_message_demo_1" style="width:100%%"></div>'
		);
	}

	public function track_button_callback(): void {
		printf(
			'<label><input type="checkbox" id="use_track_button" name="dianxiaomi_option_name[use_track_button]" %s>Use Track Button</label>',
			( isset( $this->options['use_track_button'] ) && $this->options['use_track_button'] === true ) ? 'checked="checked"' : ''
		);
	}
}

if ( is_admin() ) {
	$dianxiaomi_settings = new Dianxiaomi_Settings();
}
