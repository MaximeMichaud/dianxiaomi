<?php
/**
 * Event Manager Tests.
 *
 * @package Dianxiaomi\Tests
 */

declare(strict_types=1);

namespace Dianxiaomi\Tests;

use PHPUnit\Framework\TestCase;
use Dianxiaomi\EventManagement\Event_Manager;
use Dianxiaomi\Interfaces\Subscriber_Interface;

/**
 * Test class for Event_Manager.
 */
class EventManagerTest extends TestCase {
	/**
	 * Event manager instance.
	 *
	 * @var Event_Manager
	 */
	private Event_Manager $event_manager;

	/**
	 * Track registered hooks.
	 *
	 * @var array<string, array<int, array{callback: callable|array<mixed>, priority: int, args: int}>>
	 */
	private array $registered_hooks = array();

	/**
	 * Set up test fixtures.
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->event_manager    = new Event_Manager();
		$this->registered_hooks = array();
	}

	/**
	 * Test adding a subscriber with simple string callbacks.
	 */
	public function test_add_subscriber_with_string_callbacks(): void {
		$subscriber = new class() implements Subscriber_Interface {
			/**
			 * Get subscribed events.
			 *
			 * @return array<string, string>
			 */
			public static function get_subscribed_events(): array {
				return array(
					'init'       => 'on_init',
					'admin_init' => 'on_admin_init',
				);
			}

			/**
			 * On init callback.
			 */
			public function on_init(): void {}

			/**
			 * On admin init callback.
			 */
			public function on_admin_init(): void {}
		};

		$this->event_manager->add_subscriber( $subscriber );

		$this->assertTrue( $this->event_manager->has_subscriber( $subscriber::class ) );
		$this->assertCount( 1, $this->event_manager->get_subscribers() );
	}

	/**
	 * Test adding a subscriber with array callbacks (priority).
	 */
	public function test_add_subscriber_with_priority(): void {
		$subscriber = new class() implements Subscriber_Interface {
			/**
			 * Get subscribed events.
			 *
			 * @return array<string, array<int, int|string>>
			 */
			public static function get_subscribed_events(): array {
				return array(
					'save_post' => array( 'on_save_post', 20 ),
				);
			}

			/**
			 * On save post callback.
			 */
			public function on_save_post(): void {}
		};

		$this->event_manager->add_subscriber( $subscriber );

		$this->assertTrue( $this->event_manager->has_subscriber( $subscriber::class ) );
	}

	/**
	 * Test adding a subscriber with array callbacks (priority and args).
	 */
	public function test_add_subscriber_with_priority_and_args(): void {
		$subscriber = new class() implements Subscriber_Interface {
			/**
			 * Get subscribed events.
			 *
			 * @return array<string, array<int, int|string>>
			 */
			public static function get_subscribed_events(): array {
				return array(
					'save_post' => array( 'on_save_post', 10, 3 ),
				);
			}

			/**
			 * On save post callback.
			 *
			 * @param int      $post_id Post ID.
			 * @param \WP_Post $post    Post object.
			 * @param bool     $update  Whether this is an update.
			 */
			public function on_save_post( int $post_id, \WP_Post $post, bool $update ): void {}
		};

		$this->event_manager->add_subscriber( $subscriber );

		$this->assertTrue( $this->event_manager->has_subscriber( $subscriber::class ) );
	}

	/**
	 * Test adding a subscriber with multiple callbacks on same hook.
	 */
	public function test_add_subscriber_with_multiple_callbacks(): void {
		$subscriber = new class() implements Subscriber_Interface {
			/**
			 * Get subscribed events.
			 *
			 * @return array<string, array<int, array<int, int|string>>>
			 */
			public static function get_subscribed_events(): array {
				return array(
					'the_content' => array(
						array( 'filter_early', 5 ),
						array( 'filter_late', 99 ),
					),
				);
			}

			/**
			 * Filter early callback.
			 *
			 * @param string $content Content.
			 *
			 * @return string
			 */
			public function filter_early( string $content ): string {
				return $content;
			}

			/**
			 * Filter late callback.
			 *
			 * @param string $content Content.
			 *
			 * @return string
			 */
			public function filter_late( string $content ): string {
				return $content;
			}
		};

		$this->event_manager->add_subscriber( $subscriber );

		$this->assertTrue( $this->event_manager->has_subscriber( $subscriber::class ) );
	}

	/**
	 * Test that adding same subscriber twice does nothing.
	 */
	public function test_add_subscriber_twice_does_nothing(): void {
		$subscriber = new class() implements Subscriber_Interface {
			/**
			 * Get subscribed events.
			 *
			 * @return array<string, string>
			 */
			public static function get_subscribed_events(): array {
				return array(
					'init' => 'on_init',
				);
			}

			/**
			 * On init callback.
			 */
			public function on_init(): void {}
		};

		$this->event_manager->add_subscriber( $subscriber );
		$this->event_manager->add_subscriber( $subscriber );

		$this->assertCount( 1, $this->event_manager->get_subscribers() );
	}

	/**
	 * Test removing a subscriber.
	 */
	public function test_remove_subscriber(): void {
		$subscriber = new class() implements Subscriber_Interface {
			/**
			 * Get subscribed events.
			 *
			 * @return array<string, string>
			 */
			public static function get_subscribed_events(): array {
				return array(
					'init' => 'on_init',
				);
			}

			/**
			 * On init callback.
			 */
			public function on_init(): void {}
		};

		$this->event_manager->add_subscriber( $subscriber );
		$this->assertTrue( $this->event_manager->has_subscriber( $subscriber::class ) );

		$this->event_manager->remove_subscriber( $subscriber );
		$this->assertFalse( $this->event_manager->has_subscriber( $subscriber::class ) );
		$this->assertCount( 0, $this->event_manager->get_subscribers() );
	}

	/**
	 * Test removing a non-existent subscriber does nothing.
	 */
	public function test_remove_nonexistent_subscriber(): void {
		$subscriber = new class() implements Subscriber_Interface {
			/**
			 * Get subscribed events.
			 *
			 * @return array<string, string>
			 */
			public static function get_subscribed_events(): array {
				return array(
					'init' => 'on_init',
				);
			}

			/**
			 * On init callback.
			 */
			public function on_init(): void {}
		};

		// Should not throw.
		$this->event_manager->remove_subscriber( $subscriber );

		$this->assertFalse( $this->event_manager->has_subscriber( $subscriber::class ) );
	}

	/**
	 * Test has_subscriber returns false for unregistered subscriber.
	 */
	public function test_has_subscriber_returns_false_for_unregistered(): void {
		$this->assertFalse( $this->event_manager->has_subscriber( 'NonExistentClass' ) );
	}

	/**
	 * Test get_subscribers returns empty array initially.
	 */
	public function test_get_subscribers_returns_empty_initially(): void {
		$this->assertCount( 0, $this->event_manager->get_subscribers() );
		$this->assertSame( array(), $this->event_manager->get_subscribers() );
	}

	/**
	 * Test add_callback method.
	 */
	public function test_add_callback(): void {
		$callback_called = false;
		$callback        = function ( $value ) use ( &$callback_called ) {
			$callback_called = true;
			return $value;
		};

		$this->event_manager->add_callback( 'test_filter_hook', $callback );

		// Trigger the filter.
		apply_filters( 'test_filter_hook', 'test_value' );

		$this->assertTrue( $callback_called );
	}

	/**
	 * Test remove_callback method.
	 */
	public function test_remove_callback(): void {
		$callback_count = 0;
		$callback       = function ( $value ) use ( &$callback_count ) {
			++$callback_count;
			return $value;
		};

		$this->event_manager->add_callback( 'test_filter_remove', $callback );

		// Trigger once.
		apply_filters( 'test_filter_remove', 'value' );
		$this->assertSame( 1, $callback_count );

		// Remove and trigger again.
		$this->event_manager->remove_callback( 'test_filter_remove', $callback );
		apply_filters( 'test_filter_remove', 'value' );

		// Count should still be 1.
		$this->assertSame( 1, $callback_count );
	}
}
