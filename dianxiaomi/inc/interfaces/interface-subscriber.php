<?php
/**
 * Subscriber Interface.
 *
 * @package Dianxiaomi\Interfaces
 */

declare(strict_types=1);

namespace Dianxiaomi\Interfaces;

/**
 * Interface for event subscribers.
 *
 * Classes implementing this interface declare which WordPress hooks they subscribe to.
 * The Event_Manager reads these subscriptions and registers them with WordPress.
 *
 * @since 1.41
 */
interface Subscriber_Interface {
	/**
	 * Returns an array of events this subscriber wants to listen to.
	 *
	 * The array key is the event name (WordPress hook).
	 * The value can be:
	 *   - A string: method name to call (priority 10, 1 argument)
	 *   - An array: [method_name, priority] or [method_name, priority, accepted_args]
	 *   - An array of arrays: for multiple callbacks on the same hook
	 *
	 * Example:
	 * ```php
	 * return array(
	 *     'init' => 'on_init',
	 *     'save_post' => array( 'on_save_post', 10, 2 ),
	 *     'the_content' => array(
	 *         array( 'filter_content_early', 5 ),
	 *         array( 'filter_content_late', 99 ),
	 *     ),
	 * );
	 * ```
	 *
	 * @return array<string, string|array<int, int|string>|array<int, array<int, int|string>>>
	 */
	public static function get_subscribed_events(): array;
}
