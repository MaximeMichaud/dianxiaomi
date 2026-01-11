<?php
/**
 * Dianxiaomi Settings Tests.
 *
 * @package Dianxiaomi\Tests\Unit
 */

declare(strict_types=1);

namespace Dianxiaomi\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Dianxiaomi_Settings;
use ReflectionMethod;

/**
 * Test class for Dianxiaomi_Settings.
 */
class SettingsTest extends TestCase {

	/**
	 * Settings instance.
	 *
	 * @var Dianxiaomi_Settings
	 */
	private Dianxiaomi_Settings $settings;

	/**
	 * Set up before each test.
	 */
	protected function setUp(): void {
		parent::setUp();
		\reset_all();
		$this->settings = new Dianxiaomi_Settings();
	}

	/**
	 * Clean up after each test.
	 */
	protected function tearDown(): void {
		\reset_current_screen();
		parent::tearDown();
	}

	/**
	 * Test get_subscribed_events returns expected hooks.
	 */
	public function test_get_subscribed_events_returns_admin_hooks(): void {
		$events = Dianxiaomi_Settings::get_subscribed_events();

		$this->assertIsArray( $events );
		$this->assertArrayHasKey( 'admin_menu', $events );
		$this->assertArrayHasKey( 'admin_init', $events );
		$this->assertArrayHasKey( 'admin_print_styles', $events );
		$this->assertArrayHasKey( 'admin_print_scripts', $events );

		$this->assertSame( 'add_plugin_page', $events['admin_menu'] );
		$this->assertSame( 'page_init', $events['admin_init'] );
		$this->assertSame( 'admin_styles', $events['admin_print_styles'] );
		$this->assertSame( 'library_scripts', $events['admin_print_scripts'] );
	}

	/**
	 * Test sanitize with valid couriers input.
	 */
	public function test_sanitize_couriers(): void {
		$input  = array( 'couriers' => 'ups,fedex,dhl' );
		$result = $this->settings->sanitize( $input );

		$this->assertArrayHasKey( 'couriers', $result );
		$this->assertSame( 'ups,fedex,dhl', $result['couriers'] );
	}

	/**
	 * Test sanitize with custom domain.
	 */
	public function test_sanitize_custom_domain(): void {
		$input  = array( 'custom_domain' => 'https://track.example.com/?num=' );
		$result = $this->settings->sanitize( $input );

		$this->assertArrayHasKey( 'custom_domain', $result );
		$this->assertSame( 'https://track.example.com/?num=', $result['custom_domain'] );
	}

	/**
	 * Test sanitize with plugin selection.
	 */
	public function test_sanitize_plugin(): void {
		$input  = array( 'plugin' => 'dianxiaomi' );
		$result = $this->settings->sanitize( $input );

		$this->assertArrayHasKey( 'plugin', $result );
		$this->assertSame( 'dianxiaomi', $result['plugin'] );
	}

	/**
	 * Test sanitize with track button enabled.
	 */
	public function test_sanitize_track_button_enabled(): void {
		$input  = array( 'use_track_button' => '1' );
		$result = $this->settings->sanitize( $input );

		$this->assertArrayHasKey( 'use_track_button', $result );
		$this->assertTrue( $result['use_track_button'] );
	}

	/**
	 * Test sanitize with track button disabled (not set).
	 */
	public function test_sanitize_track_button_disabled(): void {
		$input  = array( 'couriers' => 'ups' );
		$result = $this->settings->sanitize( $input );

		$this->assertArrayNotHasKey( 'use_track_button', $result );
	}

	/**
	 * Test sanitize with track messages.
	 */
	public function test_sanitize_track_messages(): void {
		$input = array(
			'track_message_1' => 'Your order was shipped via ',
			'track_message_2' => 'Tracking number is ',
		);

		$result = $this->settings->sanitize( $input );

		$this->assertArrayHasKey( 'track_message_1', $result );
		$this->assertArrayHasKey( 'track_message_2', $result );
		$this->assertSame( 'Your order was shipped via ', $result['track_message_1'] );
		$this->assertSame( 'Tracking number is ', $result['track_message_2'] );
	}

	/**
	 * Test sanitize preserves trailing space in track messages.
	 */
	public function test_sanitize_preserves_trailing_space(): void {
		$input = array(
			'track_message_1' => 'Shipped via ',
		);

		$result = $this->settings->sanitize( $input );

		$this->assertStringEndsWith( ' ', $result['track_message_1'] );
	}

	/**
	 * Test sanitize with malicious input.
	 */
	public function test_sanitize_strips_html(): void {
		$input = array(
			'couriers'      => '<script>alert("xss")</script>ups',
			'custom_domain' => '<img src=x onerror=alert(1)>https://evil.com',
		);

		$result = $this->settings->sanitize( $input );

		$this->assertStringNotContainsString( '<script>', $result['couriers'] );
		$this->assertStringNotContainsString( '<img', $result['custom_domain'] );
	}

	/**
	 * Test sanitize with empty input.
	 */
	public function test_sanitize_empty_input(): void {
		$input  = array();
		$result = $this->settings->sanitize( $input );

		$this->assertIsArray( $result );
		$this->assertEmpty( $result );
	}

	/**
	 * Test sanitize with all fields.
	 */
	public function test_sanitize_all_fields(): void {
		$input = array(
			'plugin'           => 'dianxiaomi',
			'couriers'         => 'ups,fedex',
			'custom_domain'    => 'https://track.example.com/',
			'use_track_button' => '1',
			'track_message_1'  => 'Shipped via ',
			'track_message_2'  => 'Track: ',
		);

		$result = $this->settings->sanitize( $input );

		$this->assertCount( 6, $result );
		$this->assertSame( 'dianxiaomi', $result['plugin'] );
		$this->assertSame( 'ups,fedex', $result['couriers'] );
		$this->assertTrue( $result['use_track_button'] );
	}

	/**
	 * Test that Settings implements Subscriber_Interface.
	 */
	public function test_implements_subscriber_interface(): void {
		$this->assertInstanceOf(
			\Dianxiaomi\Interfaces\Subscriber_Interface::class,
			$this->settings
		);
	}

	// =========================================================================
	// UI METHOD TESTS
	// =========================================================================

	/**
	 * Test admin_styles does nothing when not on settings screen.
	 */
	public function test_admin_styles_does_nothing_when_not_on_settings_screen(): void {
		// Screen is null by default.
		$this->settings->admin_styles();

		$this->assertFalse( \was_style_enqueued( 'select2' ) );
		$this->assertFalse( \was_style_enqueued( 'dianxiaomi_settings_styles' ) );
	}

	/**
	 * Test admin_styles does nothing on wrong screen.
	 */
	public function test_admin_styles_does_nothing_on_wrong_screen(): void {
		\set_current_screen( 'edit-post' );

		$this->settings->admin_styles();

		$this->assertFalse( \was_style_enqueued( 'select2' ) );
		$this->assertFalse( \was_style_enqueued( 'dianxiaomi_settings_styles' ) );
	}

	/**
	 * Test admin_styles enqueues styles on settings screen.
	 */
	public function test_admin_styles_enqueues_on_settings_screen(): void {
		\set_current_screen( 'settings_page_dianxiaomi-setting-admin' );

		$this->settings->admin_styles();

		$this->assertTrue( \was_style_enqueued( 'select2' ) );
		$this->assertTrue( \was_style_enqueued( 'dianxiaomi_settings_styles' ) );
	}

	/**
	 * Test admin_styles enqueues correct CSS file.
	 */
	public function test_admin_styles_enqueues_correct_css(): void {
		\set_current_screen( 'settings_page_dianxiaomi-setting-admin' );

		$this->settings->admin_styles();

		$style = \get_enqueued_style( 'dianxiaomi_settings_styles' );
		$this->assertNotNull( $style );
		$this->assertStringContainsString( 'admin.css', $style['src'] );
	}

	/**
	 * Test library_scripts does nothing when not on settings screen.
	 */
	public function test_library_scripts_does_nothing_when_not_on_settings_screen(): void {
		$this->settings->library_scripts();

		$this->assertFalse( \was_script_enqueued( 'selectWoo' ) );
		$this->assertFalse( \was_script_enqueued( 'dianxiaomi_settings_script' ) );
	}

	/**
	 * Test library_scripts does nothing on wrong screen.
	 */
	public function test_library_scripts_does_nothing_on_wrong_screen(): void {
		\set_current_screen( 'edit-post' );

		$this->settings->library_scripts();

		$this->assertFalse( \was_script_enqueued( 'selectWoo' ) );
		$this->assertFalse( \was_script_enqueued( 'dianxiaomi_settings_script' ) );
	}

	/**
	 * Test library_scripts enqueues scripts on settings screen.
	 */
	public function test_library_scripts_enqueues_on_settings_screen(): void {
		\set_current_screen( 'settings_page_dianxiaomi-setting-admin' );

		$this->settings->library_scripts();

		$this->assertTrue( \was_script_enqueued( 'selectWoo' ) );
		$this->assertTrue( \was_script_enqueued( 'dianxiaomi_settings_util' ) );
		$this->assertTrue( \was_script_enqueued( 'dianxiaomi_settings_couriers' ) );
		$this->assertTrue( \was_script_enqueued( 'dianxiaomi_settings_script' ) );
	}

	/**
	 * Test library_scripts enqueues scripts with correct deps.
	 */
	public function test_library_scripts_correct_dependencies(): void {
		\set_current_screen( 'settings_page_dianxiaomi-setting-admin' );

		$this->settings->library_scripts();

		$script = \get_enqueued_script( 'dianxiaomi_settings_script' );
		$this->assertNotNull( $script );
		$this->assertContains( 'selectWoo', $script['deps'] );
	}

	/**
	 * Test add_plugin_page adds options page.
	 */
	public function test_add_plugin_page_returns_slug(): void {
		// add_options_page returns the menu slug in mock.
		$result = $this->settings->add_plugin_page();

		// Method returns void but calls add_options_page.
		$this->assertNull( $result );
	}

	/**
	 * Test create_admin_page outputs HTML.
	 */
	public function test_create_admin_page_outputs_html(): void {
		\set_wp_option( 'dianxiaomi_option_name', array(
			'plugin'   => 'dianxiaomi',
			'couriers' => 'ups,fedex',
		) );

		ob_start();
		$this->settings->create_admin_page();
		$output = ob_get_clean();

		$this->assertStringContainsString( '<div class="wrap">', $output );
		$this->assertStringContainsString( 'Dianxiaomi Settings', $output );
		$this->assertStringContainsString( '<form method="post"', $output );
		$this->assertStringContainsString( 'options.php', $output );
	}

	/**
	 * Test create_admin_page outputs settings fields.
	 */
	public function test_create_admin_page_outputs_settings_fields(): void {
		\set_wp_option( 'dianxiaomi_option_name', array() );

		ob_start();
		$this->settings->create_admin_page();
		$output = ob_get_clean();

		// settings_fields outputs hidden input.
		$this->assertStringContainsString( 'dianxiaomi_option_group', $output );
	}

	/**
	 * Test create_admin_page outputs submit button.
	 */
	public function test_create_admin_page_outputs_submit_button(): void {
		\set_wp_option( 'dianxiaomi_option_name', array() );

		ob_start();
		$this->settings->create_admin_page();
		$output = ob_get_clean();

		$this->assertStringContainsString( '<input type="submit"', $output );
	}

	/**
	 * Test page_init registers settings.
	 */
	public function test_page_init_registers_settings(): void {
		// This should not throw any errors.
		$this->settings->page_init();

		// If we get here, the function executed successfully.
		$this->assertTrue( true );
	}

	/**
	 * Test print_section_info outputs text.
	 */
	public function test_print_section_info_outputs_text(): void {
		ob_start();
		$this->settings->print_section_info();
		$output = ob_get_clean();

		$this->assertStringContainsString( '<p>', $output );
		$this->assertStringContainsString( 'Enter your settings below:', $output );
	}

	/**
	 * Test couriers_callback outputs select element.
	 */
	public function test_couriers_callback_outputs_select(): void {
		// Set options via reflection since create_admin_page sets them.
		$reflection = new \ReflectionClass( $this->settings );
		$property   = $reflection->getProperty( 'options' );
		$property->setAccessible( true );
		$property->setValue( $this->settings, array( 'couriers' => 'ups,fedex' ) );

		ob_start();
		$this->settings->couriers_callback();
		$output = ob_get_clean();

		$this->assertStringContainsString( '<select', $output );
		$this->assertStringContainsString( 'couriers_select', $output );
		$this->assertStringContainsString( 'wc-enhanced-select', $output );
		$this->assertStringContainsString( 'multiple', $output );
		$this->assertStringContainsString( '<input type="hidden"', $output );
		$this->assertStringContainsString( 'ups,fedex', $output );
	}

	/**
	 * Test couriers_callback with empty options.
	 */
	public function test_couriers_callback_with_empty_options(): void {
		$reflection = new \ReflectionClass( $this->settings );
		$property   = $reflection->getProperty( 'options' );
		$property->setAccessible( true );
		$property->setValue( $this->settings, array() );

		ob_start();
		$this->settings->couriers_callback();
		$output = ob_get_clean();

		$this->assertStringContainsString( '<select', $output );
		$this->assertStringContainsString( 'value=""', $output );
	}

	/**
	 * Test couriers_callback outputs update link.
	 */
	public function test_couriers_callback_outputs_update_link(): void {
		$reflection = new \ReflectionClass( $this->settings );
		$property   = $reflection->getProperty( 'options' );
		$property->setAccessible( true );
		$property->setValue( $this->settings, array() );

		ob_start();
		$this->settings->couriers_callback();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'dianxiaomi.com/settings/courier', $output );
		$this->assertStringContainsString( 'target="_blank"', $output );
	}

	/**
	 * Test plugin_callback outputs select element.
	 */
	public function test_plugin_callback_outputs_select(): void {
		// Set dianxiaomi as active plugin.
		\set_wp_option( 'active_plugins', array( 'dianxiaomi/dianxiaomi.php' ) );

		// Reset Dependencies static to pick up new option.
		$dep_reflection = new \ReflectionClass( \Dianxiaomi_Dependencies::class );
		$dep_property   = $dep_reflection->getProperty( 'active_plugins' );
		$dep_property->setAccessible( true );
		$dep_property->setValue( null, array() );

		$reflection = new \ReflectionClass( $this->settings );
		$property   = $reflection->getProperty( 'options' );
		$property->setAccessible( true );
		$property->setValue( $this->settings, array( 'plugin' => 'dianxiaomi' ) );

		ob_start();
		$this->settings->plugin_callback();
		$output = ob_get_clean();

		$this->assertStringContainsString( '<select', $output );
		$this->assertStringContainsString( 'plugin', $output );
		$this->assertStringContainsString( 'dianxiaomi_dropdown', $output );
	}

	/**
	 * Test plugin_callback shows only active plugins.
	 */
	public function test_plugin_callback_shows_active_plugins(): void {
		// Set only dianxiaomi as active (not wc-shipment-tracking).
		\set_wp_option( 'active_plugins', array( 'dianxiaomi/dianxiaomi.php' ) );

		$dep_reflection = new \ReflectionClass( \Dianxiaomi_Dependencies::class );
		$dep_property   = $dep_reflection->getProperty( 'active_plugins' );
		$dep_property->setAccessible( true );
		$dep_property->setValue( null, array() );

		$reflection = new \ReflectionClass( $this->settings );
		$property   = $reflection->getProperty( 'options' );
		$property->setAccessible( true );
		$property->setValue( $this->settings, array() );

		ob_start();
		$this->settings->plugin_callback();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Dianxiaomi', $output );
	}

	/**
	 * Test custom_domain_callback outputs text input.
	 */
	public function test_custom_domain_callback_outputs_input(): void {
		$reflection = new \ReflectionClass( $this->settings );
		$property   = $reflection->getProperty( 'options' );
		$property->setAccessible( true );
		$property->setValue( $this->settings, array( 'custom_domain' => 'https://custom.example.com/' ) );

		ob_start();
		$this->settings->custom_domain_callback();
		$output = ob_get_clean();

		$this->assertStringContainsString( '<input type="text"', $output );
		$this->assertStringContainsString( 'custom_domain', $output );
		$this->assertStringContainsString( 'https://custom.example.com/', $output );
	}

	/**
	 * Test custom_domain_callback uses default value.
	 */
	public function test_custom_domain_callback_default_value(): void {
		$reflection = new \ReflectionClass( $this->settings );
		$property   = $reflection->getProperty( 'options' );
		$property->setAccessible( true );
		$property->setValue( $this->settings, array() );

		ob_start();
		$this->settings->custom_domain_callback();
		$output = ob_get_clean();

		$this->assertStringContainsString( 't.17track.net', $output );
	}

	/**
	 * Test track_message_callback outputs two text inputs.
	 */
	public function test_track_message_callback_outputs_inputs(): void {
		$reflection = new \ReflectionClass( $this->settings );
		$property   = $reflection->getProperty( 'options' );
		$property->setAccessible( true );
		$property->setValue( $this->settings, array(
			'track_message_1' => 'Shipped via ',
			'track_message_2' => 'Track: ',
		) );

		ob_start();
		$this->settings->track_message_callback();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'track_message_1', $output );
		$this->assertStringContainsString( 'track_message_2', $output );
		$this->assertStringContainsString( 'Shipped via ', $output );
		$this->assertStringContainsString( 'Track: ', $output );
	}

	/**
	 * Test track_message_callback uses default values.
	 */
	public function test_track_message_callback_default_values(): void {
		$reflection = new \ReflectionClass( $this->settings );
		$property   = $reflection->getProperty( 'options' );
		$property->setAccessible( true );
		$property->setValue( $this->settings, array() );

		ob_start();
		$this->settings->track_message_callback();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Your order was shipped via ', $output );
		$this->assertStringContainsString( 'Tracking number is ', $output );
	}

	/**
	 * Test track_message_callback outputs demo area.
	 */
	public function test_track_message_callback_outputs_demo(): void {
		$reflection = new \ReflectionClass( $this->settings );
		$property   = $reflection->getProperty( 'options' );
		$property->setAccessible( true );
		$property->setValue( $this->settings, array() );

		ob_start();
		$this->settings->track_message_callback();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Demo:', $output );
		$this->assertStringContainsString( 'track_message_demo_1', $output );
	}

	/**
	 * Test track_button_callback outputs checkbox.
	 */
	public function test_track_button_callback_outputs_checkbox(): void {
		$reflection = new \ReflectionClass( $this->settings );
		$property   = $reflection->getProperty( 'options' );
		$property->setAccessible( true );
		$property->setValue( $this->settings, array( 'use_track_button' => true ) );

		ob_start();
		$this->settings->track_button_callback();
		$output = ob_get_clean();

		$this->assertStringContainsString( '<input type="checkbox"', $output );
		$this->assertStringContainsString( 'use_track_button', $output );
		$this->assertStringContainsString( 'checked="checked"', $output );
	}

	/**
	 * Test track_button_callback unchecked when false.
	 */
	public function test_track_button_callback_unchecked(): void {
		$reflection = new \ReflectionClass( $this->settings );
		$property   = $reflection->getProperty( 'options' );
		$property->setAccessible( true );
		$property->setValue( $this->settings, array( 'use_track_button' => false ) );

		ob_start();
		$this->settings->track_button_callback();
		$output = ob_get_clean();

		$this->assertStringNotContainsString( 'checked="checked"', $output );
	}

	/**
	 * Test track_button_callback label.
	 */
	public function test_track_button_callback_label(): void {
		$reflection = new \ReflectionClass( $this->settings );
		$property   = $reflection->getProperty( 'options' );
		$property->setAccessible( true );
		$property->setValue( $this->settings, array() );

		ob_start();
		$this->settings->track_button_callback();
		$output = ob_get_clean();

		$this->assertStringContainsString( '<label>', $output );
		$this->assertStringContainsString( 'Use Track Button', $output );
	}

	/**
	 * Test is_settings_screen returns false when screen is null.
	 */
	public function test_is_settings_screen_returns_false_for_null_screen(): void {
		$method = new ReflectionMethod( Dianxiaomi_Settings::class, 'is_settings_screen' );
		$method->setAccessible( true );

		$result = $method->invoke( $this->settings );

		$this->assertFalse( $result );
	}

	/**
	 * Test is_settings_screen returns false for wrong screen.
	 */
	public function test_is_settings_screen_returns_false_for_wrong_screen(): void {
		\set_current_screen( 'dashboard' );

		$method = new ReflectionMethod( Dianxiaomi_Settings::class, 'is_settings_screen' );
		$method->setAccessible( true );

		$result = $method->invoke( $this->settings );

		$this->assertFalse( $result );
	}

	/**
	 * Test is_settings_screen returns true for settings screen.
	 */
	public function test_is_settings_screen_returns_true_for_settings_screen(): void {
		\set_current_screen( 'settings_page_dianxiaomi-setting-admin' );

		$method = new ReflectionMethod( Dianxiaomi_Settings::class, 'is_settings_screen' );
		$method->setAccessible( true );

		$result = $method->invoke( $this->settings );

		$this->assertTrue( $result );
	}

	/**
	 * Test constructor initializes plugins array.
	 */
	public function test_constructor_initializes_plugins(): void {
		$reflection = new \ReflectionClass( $this->settings );
		$property   = $reflection->getProperty( 'plugins' );
		$property->setAccessible( true );
		$plugins = $property->getValue( $this->settings );

		$this->assertIsArray( $plugins );
		$this->assertCount( 2, $plugins );
		$this->assertEquals( 'dianxiaomi', $plugins[0]['value'] );
		$this->assertEquals( 'wc-shipment-tracking', $plugins[1]['value'] );
	}

	/**
	 * Test constructor adds subscriber to event manager.
	 */
	public function test_constructor_adds_subscriber(): void {
		global $wp_filters;

		// Reset filters.
		$wp_filters = array();

		// Create new instance which should register filters.
		$settings = new Dianxiaomi_Settings();

		// Check that hooks were added.
		$this->assertArrayHasKey( 'admin_menu', $wp_filters );
		$this->assertArrayHasKey( 'admin_init', $wp_filters );
		$this->assertArrayHasKey( 'admin_print_styles', $wp_filters );
		$this->assertArrayHasKey( 'admin_print_scripts', $wp_filters );
	}
}
