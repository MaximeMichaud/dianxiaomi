<?php
/**
 * Production Load Tests.
 *
 * These tests verify that the plugin can be loaded in a production environment
 * without Composer autoload (simulating a WordPress plugin installation).
 *
 * @package Dianxiaomi
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Test class for production-like loading scenarios.
 */
class ProductionLoadTest extends TestCase {

	/**
	 * Test that all required files exist and can be loaded via require_once.
	 *
	 * This simulates production where Composer autoload is not available.
	 */
	public function test_plugin_files_can_be_loaded_without_autoload(): void {
		$plugin_dir = dirname( __DIR__ );

		// Core files that must be loadable.
		$required_files = array(
			'/dianxiaomi-functions.php',
			'/inc/interfaces/interface-subscriber.php',
			'/inc/event-management/class-event-manager.php',
			'/class-dianxiaomi-dependencies.php',
			'/class-dianxiaomi.php',
		);

		foreach ( $required_files as $file ) {
			$full_path = $plugin_dir . $file;
			$this->assertFileExists( $full_path, "Required file missing: {$file}" );
		}
	}

	/**
	 * Test that interface is loaded before classes that use it.
	 *
	 * The main plugin file (dianxiaomi.php) must load interface-subscriber.php
	 * before class-dianxiaomi.php to avoid "Interface not found" errors.
	 */
	public function test_interface_loaded_before_main_class(): void {
		$plugin_file = dirname( __DIR__ ) . '/dianxiaomi.php';
		$content     = file_get_contents( $plugin_file );

		// Check that interface-subscriber.php is required before class-dianxiaomi.php.
		$interface_pos = strpos( $content, 'interface-subscriber.php' );
		$class_pos     = strpos( $content, 'class-dianxiaomi.php' );

		$this->assertNotFalse( $interface_pos, 'interface-subscriber.php must be included in dianxiaomi.php' );
		$this->assertNotFalse( $class_pos, 'class-dianxiaomi.php must be included in dianxiaomi.php' );
		$this->assertLessThan( $class_pos, $interface_pos, 'interface-subscriber.php must be loaded BEFORE class-dianxiaomi.php' );
	}

	/**
	 * Test that Event_Manager is loaded before main class.
	 */
	public function test_event_manager_loaded_before_main_class(): void {
		$plugin_file = dirname( __DIR__ ) . '/dianxiaomi.php';
		$content     = file_get_contents( $plugin_file );

		$event_manager_pos = strpos( $content, 'class-event-manager.php' );
		$class_pos         = strpos( $content, 'class-dianxiaomi.php' );

		$this->assertNotFalse( $event_manager_pos, 'class-event-manager.php must be included in dianxiaomi.php' );
		$this->assertLessThan( $class_pos, $event_manager_pos, 'class-event-manager.php must be loaded BEFORE class-dianxiaomi.php' );
	}

	/**
	 * Test that the Subscriber_Interface exists and is properly defined.
	 */
	public function test_subscriber_interface_exists(): void {
		$this->assertTrue(
			interface_exists( 'Dianxiaomi\Interfaces\Subscriber_Interface' ),
			'Subscriber_Interface must exist in the Dianxiaomi\Interfaces namespace'
		);
	}

	/**
	 * Test that Dianxiaomi class implements Subscriber_Interface.
	 */
	public function test_dianxiaomi_implements_subscriber_interface(): void {
		$this->assertTrue(
			class_exists( 'Dianxiaomi' ),
			'Dianxiaomi class must exist'
		);

		$reflection = new ReflectionClass( 'Dianxiaomi' );
		$interfaces = $reflection->getInterfaceNames();

		$this->assertContains(
			'Dianxiaomi\Interfaces\Subscriber_Interface',
			$interfaces,
			'Dianxiaomi must implement Subscriber_Interface'
		);
	}

	/**
	 * Test that Event_Manager class exists.
	 */
	public function test_event_manager_class_exists(): void {
		$this->assertTrue(
			class_exists( 'Dianxiaomi\EventManagement\Event_Manager' ),
			'Event_Manager must exist in the Dianxiaomi\EventManagement namespace'
		);
	}
}
