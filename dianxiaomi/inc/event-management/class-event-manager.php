<?php
/**
 * Event Manager.
 *
 * @package Dianxiaomi\EventManagement
 */

declare(strict_types=1);

namespace Dianxiaomi\EventManagement;

use Dianxiaomi\Interfaces\Subscriber_Interface;

/**
 * Manages event subscribers and their registration with WordPress hooks.
 *
 * @since 1.41
 */
class Event_Manager {
	/**
	 * Registered subscribers.
	 *
	 * @var array<string, Subscriber_Interface>
	 */
	private array $subscribers = array();

	/**
	 * Register a subscriber with the event manager.
	 *
	 * @param Subscriber_Interface $subscriber The subscriber to register.
	 */
	public function add_subscriber( Subscriber_Interface $subscriber ): void {
		$class_name = $subscriber::class;

		if ( isset( $this->subscribers[ $class_name ] ) ) {
			return;
		}

		$this->subscribers[ $class_name ] = $subscriber;

		foreach ( $subscriber::get_subscribed_events() as $hook_name => $params ) {
			$this->add_subscriber_callback( $subscriber, $hook_name, $params );
		}
	}

	/**
	 * Remove a subscriber from the event manager.
	 *
	 * @param Subscriber_Interface $subscriber The subscriber to remove.
	 */
	public function remove_subscriber( Subscriber_Interface $subscriber ): void {
		$class_name = $subscriber::class;

		if ( ! isset( $this->subscribers[ $class_name ] ) ) {
			return;
		}

		foreach ( $subscriber::get_subscribed_events() as $hook_name => $params ) {
			$this->remove_subscriber_callback( $subscriber, $hook_name, $params );
		}

		unset( $this->subscribers[ $class_name ] );
	}

	/**
	 * Add a callback to a WordPress hook.
	 *
	 * @param string                        $hook_name     The hook name.
	 * @param callable|array{object,string} $callback      The callback (function or [object, method]).
	 * @param int                           $priority      The priority.
	 * @param int                           $accepted_args Number of accepted arguments.
	 *
	 * @phpstan-param callable|array{0: object, 1: string} $callback
	 */
	public function add_callback( string $hook_name, callable|array $callback, int $priority = 10, int $accepted_args = 1 ): void {
		// WordPress accepts array callbacks [object, method] even though stubs say callable.
		\add_filter( $hook_name, $callback, $priority, $accepted_args ); // @phpstan-ignore argument.type
	}

	/**
	 * Remove a callback from a WordPress hook.
	 *
	 * @param string                        $hook_name The hook name.
	 * @param callable|array{object,string} $callback  The callback (function or [object, method]).
	 * @param int                           $priority  The priority.
	 *
	 * @phpstan-param callable|array{0: object, 1: string} $callback
	 */
	public function remove_callback( string $hook_name, callable|array $callback, int $priority = 10 ): void {
		\remove_filter( $hook_name, $callback, $priority );
	}

	/**
	 * Check if a subscriber is registered.
	 *
	 * @param string $class_name The subscriber class name.
	 *
	 * @return bool True if registered.
	 */
	public function has_subscriber( string $class_name ): bool {
		return isset( $this->subscribers[ $class_name ] );
	}

	/**
	 * Get all registered subscribers.
	 *
	 * @return array<string, Subscriber_Interface>
	 */
	public function get_subscribers(): array {
		return $this->subscribers;
	}

	/**
	 * Add a subscriber callback to a hook.
	 *
	 * @param Subscriber_Interface                                             $subscriber The subscriber.
	 * @param string                                                           $hook_name  The hook name.
	 * @param string|array<int, int|string>|array<int, array<int, int|string>> $params     The callback parameters.
	 */
	private function add_subscriber_callback( Subscriber_Interface $subscriber, string $hook_name, string|array $params ): void {
		// Simple string: method name only.
		if ( is_string( $params ) ) {
			$this->add_callback( $hook_name, array( $subscriber, $params ) );
			return;
		}

		// Check if this is multiple callbacks (array of arrays).
		if ( $this->is_multiple_callbacks( $params ) ) {
			/** @var array<int, array<int, int|string>> $params */
			foreach ( $params as $callback_params ) {
				$this->add_single_callback( $subscriber, $hook_name, $callback_params );
			}
			return;
		}

		// Single callback with parameters.
		/** @var array<int, int|string> $params */
		$this->add_single_callback( $subscriber, $hook_name, $params );
	}

	/**
	 * Remove a subscriber callback from a hook.
	 *
	 * @param Subscriber_Interface                                             $subscriber The subscriber.
	 * @param string                                                           $hook_name  The hook name.
	 * @param string|array<int, int|string>|array<int, array<int, int|string>> $params     The callback parameters.
	 */
	private function remove_subscriber_callback( Subscriber_Interface $subscriber, string $hook_name, string|array $params ): void {
		if ( is_string( $params ) ) {
			$this->remove_callback( $hook_name, array( $subscriber, $params ) );
			return;
		}

		if ( $this->is_multiple_callbacks( $params ) ) {
			/** @var array<int, array<int, int|string>> $params */
			foreach ( $params as $callback_params ) {
				$this->remove_single_callback( $subscriber, $hook_name, $callback_params );
			}
			return;
		}

		/** @var array<int, int|string> $params */
		$this->remove_single_callback( $subscriber, $hook_name, $params );
	}

	/**
	 * Add a single callback with its parameters.
	 *
	 * @param Subscriber_Interface   $subscriber The subscriber.
	 * @param string                 $hook_name  The hook name.
	 * @param array<int, int|string> $params     [method, priority?, accepted_args?].
	 */
	private function add_single_callback( Subscriber_Interface $subscriber, string $hook_name, array $params ): void {
		$method        = (string) $params[0];
		$priority      = isset( $params[1] ) ? (int) $params[1] : 10;
		$accepted_args = isset( $params[2] ) ? (int) $params[2] : 1;

		$this->add_callback( $hook_name, array( $subscriber, $method ), $priority, $accepted_args );
	}

	/**
	 * Remove a single callback with its parameters.
	 *
	 * @param Subscriber_Interface   $subscriber The subscriber.
	 * @param string                 $hook_name  The hook name.
	 * @param array<int, int|string> $params     [method, priority?, accepted_args?].
	 */
	private function remove_single_callback( Subscriber_Interface $subscriber, string $hook_name, array $params ): void {
		$method   = (string) $params[0];
		$priority = isset( $params[1] ) ? (int) $params[1] : 10;

		$this->remove_callback( $hook_name, array( $subscriber, $method ), $priority );
	}

	/**
	 * Check if params represent multiple callbacks.
	 *
	 * Multiple callbacks: array( array( method, priority ), array( method, priority ) )
	 * Single callback: array( method, priority, args )
	 *
	 * @param array<int, int|string|array<int, int|string>> $params The parameters to check.
	 *
	 * @return bool True if multiple callbacks.
	 */
	private function is_multiple_callbacks( array $params ): bool {
		return isset( $params[0] ) && is_array( $params[0] );
	}
}
