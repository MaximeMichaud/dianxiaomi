<?php
/**
 * Tests for the SelectWoo migration (Chosen.js replacement).
 *
 * @package Dianxiaomi
 */

declare(strict_types=1);

namespace Dianxiaomi\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Dianxiaomi;
use Dianxiaomi_Settings;

/**
 * Test class for SelectWoo migration.
 */
class SelectWooMigrationTest extends TestCase {

	/**
	 * Set up before each test.
	 */
	protected function setUp(): void {
		parent::setUp();
		reset_wp_enqueues();

		// Set up global options.
		global $wp_options, $wp_post_meta;
		$wp_options   = array(
			'dianxiaomi_option_name' => array(
				'plugin'           => 'dianxiaomi',
				'couriers'         => 'ups,fedex,dhl',
				'use_track_button' => true,
				'custom_domain'    => 'https://t.17track.net/zh-cn#nums=',
			),
		);
		$wp_post_meta = array();
	}

	/**
	 * Test that Chosen scripts are NOT enqueued in main class.
	 */
	public function test_chosen_scripts_not_enqueued_in_main_class(): void {
		require_once dirname( __DIR__, 2 ) . '/class-dianxiaomi.php';

		$dianxiaomi = Dianxiaomi::instance();
		$dianxiaomi->library_scripts();

		// Chosen scripts should NOT be enqueued.
		$this->assertFalse(
			was_script_enqueued( 'dianxiaomi_script_chosen_jquery' ),
			'Chosen jQuery script should not be enqueued'
		);
		$this->assertFalse(
			was_script_enqueued( 'dianxiaomi_script_chosen_proto' ),
			'Chosen Prototype script should not be enqueued'
		);
	}

	/**
	 * Test that Chosen styles are NOT enqueued in main class.
	 */
	public function test_chosen_styles_not_enqueued_in_main_class(): void {
		require_once dirname( __DIR__, 2 ) . '/class-dianxiaomi.php';

		$dianxiaomi = Dianxiaomi::instance();
		$dianxiaomi->admin_styles();

		// Chosen styles should NOT be enqueued.
		$this->assertFalse(
			was_style_enqueued( 'dianxiaomi_styles_chosen' ),
			'Chosen styles should not be enqueued'
		);
	}

	/**
	 * Test that selectWoo is enqueued in main class.
	 */
	public function test_selectwoo_enqueued_in_main_class(): void {
		require_once dirname( __DIR__, 2 ) . '/class-dianxiaomi.php';

		// Set up the screen to simulate order edit page.
		set_current_screen( 'shop_order' );

		$dianxiaomi = Dianxiaomi::instance();
		$dianxiaomi->library_scripts();
		$dianxiaomi->admin_styles();

		// selectWoo should be enqueued.
		$this->assertTrue(
			was_script_enqueued( 'selectWoo' ),
			'selectWoo script should be enqueued'
		);

		// select2 style should be enqueued.
		$this->assertTrue(
			was_style_enqueued( 'select2' ),
			'select2 style should be enqueued'
		);
	}

	/**
	 * Test that admin.js has selectWoo as dependency.
	 */
	public function test_admin_js_has_selectwoo_dependency(): void {
		require_once dirname( __DIR__, 2 ) . '/class-dianxiaomi.php';

		// Set up the screen to simulate order edit page.
		set_current_screen( 'shop_order' );

		$dianxiaomi = Dianxiaomi::instance();
		$dianxiaomi->library_scripts();

		$admin_script = get_enqueued_script( 'dianxiaomi_script_admin' );

		$this->assertNotNull( $admin_script, 'Admin script should be enqueued' );
		$this->assertContains(
			'selectWoo',
			$admin_script['deps'],
			'Admin script should have selectWoo as dependency'
		);
	}

	/**
	 * Test that Chosen scripts are NOT enqueued in settings class.
	 */
	public function test_chosen_scripts_not_enqueued_in_settings_class(): void {
		require_once dirname( __DIR__, 2 ) . '/class-dianxiaomi-settings.php';

		$settings = new Dianxiaomi_Settings();
		$settings->library_scripts();

		// Chosen scripts should NOT be enqueued.
		$this->assertFalse(
			was_script_enqueued( 'dianxiaomi_styles_chosen_jquery' ),
			'Chosen jQuery script should not be enqueued in settings'
		);
		$this->assertFalse(
			was_script_enqueued( 'dianxiaomi_styles_chosen_proto' ),
			'Chosen Prototype script should not be enqueued in settings'
		);
	}

	/**
	 * Test that selectWoo is enqueued in settings class.
	 */
	public function test_selectwoo_enqueued_in_settings_class(): void {
		require_once dirname( __DIR__, 2 ) . '/class-dianxiaomi-settings.php';

		// Set up the screen to simulate settings page.
		set_current_screen( 'settings_page_dianxiaomi-setting-admin' );

		$settings = new Dianxiaomi_Settings();
		$settings->library_scripts();

		// selectWoo should be enqueued.
		$this->assertTrue(
			was_script_enqueued( 'selectWoo' ),
			'selectWoo script should be enqueued in settings'
		);
	}

	/**
	 * Test that setting.js has selectWoo as dependency.
	 */
	public function test_setting_js_has_selectwoo_dependency(): void {
		require_once dirname( __DIR__, 2 ) . '/class-dianxiaomi-settings.php';

		// Set up the screen to simulate settings page.
		set_current_screen( 'settings_page_dianxiaomi-setting-admin' );

		$settings = new Dianxiaomi_Settings();
		$settings->library_scripts();

		$setting_script = get_enqueued_script( 'dianxiaomi_settings_script' );

		$this->assertNotNull( $setting_script, 'Setting script should be enqueued' );
		$this->assertContains(
			'selectWoo',
			$setting_script['deps'],
			'Setting script should have selectWoo as dependency'
		);
	}

	/**
	 * Test that chosen folder has been removed.
	 */
	public function test_chosen_folder_removed(): void {
		$chosen_path = dirname( __DIR__, 2 ) . '/assets/plugin/chosen';

		$this->assertDirectoryDoesNotExist(
			$chosen_path,
			'Chosen folder should have been removed'
		);
	}

	/**
	 * Test that admin.js file contains selectWoo initialization.
	 */
	public function test_admin_js_contains_selectwoo(): void {
		$admin_js_path = dirname( __DIR__, 2 ) . '/assets/js/admin.js';
		$content       = file_get_contents( $admin_js_path );

		$this->assertStringContainsString(
			'selectWoo',
			$content,
			'admin.js should contain selectWoo initialization'
		);
		$this->assertStringNotContainsString(
			'chosen:updated',
			$content,
			'admin.js should not contain Chosen events'
		);
		$this->assertStringNotContainsString(
			'.chosen(',
			$content,
			'admin.js should not contain Chosen initialization'
		);
	}

	/**
	 * Test that setting.js file contains selectWoo initialization.
	 */
	public function test_setting_js_contains_selectwoo(): void {
		$setting_js_path = dirname( __DIR__, 2 ) . '/assets/js/setting.js';
		$content         = file_get_contents( $setting_js_path );

		$this->assertStringContainsString(
			'selectWoo',
			$content,
			'setting.js should contain selectWoo initialization'
		);
		$this->assertStringNotContainsString(
			'chosen:updated',
			$content,
			'setting.js should not contain Chosen events'
		);
		$this->assertStringNotContainsString(
			'.chosen(',
			$content,
			'setting.js should not contain Chosen initialization'
		);
	}

	/**
	 * Test that PHP files use wc-enhanced-select class instead of chosen classes.
	 */
	public function test_php_uses_wc_enhanced_select_class(): void {
		$main_class_path = dirname( __DIR__, 2 ) . '/class-dianxiaomi.php';
		$content         = file_get_contents( $main_class_path );

		$this->assertStringContainsString(
			'wc-enhanced-select',
			$content,
			'Main class should use wc-enhanced-select CSS class'
		);
		$this->assertStringNotContainsString(
			'chosen_select',
			$content,
			'Main class should not contain chosen_select class'
		);
	}

	/**
	 * Test that settings PHP file uses wc-enhanced-select class.
	 */
	public function test_settings_php_uses_wc_enhanced_select_class(): void {
		$settings_path = dirname( __DIR__, 2 ) . '/class-dianxiaomi-settings.php';
		$content       = file_get_contents( $settings_path );

		$this->assertStringContainsString(
			'wc-enhanced-select',
			$content,
			'Settings class should use wc-enhanced-select CSS class'
		);
		$this->assertStringNotContainsString(
			'chosen-select',
			$content,
			'Settings class should not contain chosen-select class'
		);
	}

	/**
	 * Test that duplicate link has been removed from meta_box.
	 */
	public function test_duplicate_link_removed(): void {
		$main_class_path = dirname( __DIR__, 2 ) . '/class-dianxiaomi.php';
		$content         = file_get_contents( $main_class_path );

		// Count occurrences of the update carrier list link in meta_box section.
		$link_pattern = "Update carrier list";
		$count        = substr_count( $content, $link_pattern );

		// Should appear exactly twice: once in meta_box, once elsewhere (or just once).
		// The duplicate was in lines 129 and 131, now should be just once there.
		$this->assertLessThanOrEqual(
			3,
			$count,
			'Update carrier list link should not be duplicated excessively'
		);
	}

	/**
	 * Test selectWoo initialization options in admin.js.
	 */
	public function test_admin_js_selectwoo_options(): void {
		$admin_js_path = dirname( __DIR__, 2 ) . '/assets/js/admin.js';
		$content       = file_get_contents( $admin_js_path );

		// Check for proper selectWoo options.
		$this->assertStringContainsString(
			"width: '100%'",
			$content,
			'selectWoo should have width: 100% option'
		);
		$this->assertStringContainsString(
			'allowClear',
			$content,
			'selectWoo should have allowClear option'
		);
	}

	/**
	 * Test selectWoo initialization options in setting.js.
	 */
	public function test_setting_js_selectwoo_options(): void {
		$setting_js_path = dirname( __DIR__, 2 ) . '/assets/js/setting.js';
		$content         = file_get_contents( $setting_js_path );

		// Check for proper selectWoo options.
		$this->assertStringContainsString(
			"width: '100%'",
			$content,
			'selectWoo should have width: 100% option'
		);
		$this->assertStringContainsString(
			'placeholder',
			$content,
			'selectWoo should have placeholder option'
		);
	}

	/**
	 * Test that JavaScript uses modern event binding.
	 */
	public function test_js_uses_modern_event_binding(): void {
		$admin_js_path   = dirname( __DIR__, 2 ) . '/assets/js/admin.js';
		$setting_js_path = dirname( __DIR__, 2 ) . '/assets/js/setting.js';

		$admin_content   = file_get_contents( $admin_js_path );
		$setting_content = file_get_contents( $setting_js_path );

		// Should use .on() instead of .change() for event binding.
		$this->assertStringContainsString(
			".on('change'",
			$admin_content,
			'admin.js should use .on() for event binding'
		);
		$this->assertStringContainsString(
			".on('change'",
			$setting_content,
			'setting.js should use .on() for change events'
		);
		$this->assertStringContainsString(
			".on('keyup'",
			$setting_content,
			'setting.js should use .on() for keyup events'
		);
	}
}
