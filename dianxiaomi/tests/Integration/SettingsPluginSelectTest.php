<?php
/**
 * Integration regression for the plugin selector on the settings page.
 *
 * @package Dianxiaomi\Tests\Integration
 */

declare(strict_types=1);

namespace Dianxiaomi\Tests\Integration;

class SettingsPluginSelectTest extends TestCase {
	public function test_active_plugin_is_a_selectable_option_after_loading_settings(): void {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		require_once ABSPATH . 'wp-admin/includes/template.php';
		$original = get_option( 'dianxiaomi_option_name' );
		$globals = array();
		foreach ( array( 'wp_settings_sections', 'wp_settings_fields', 'wp_registered_settings', 'new_allowed_options', 'new_whitelist_options' ) as $name ) {
			$globals[ $name ] = array( array_key_exists( $name, $GLOBALS ), $GLOBALS[ $name ] ?? null );
		}
		$settings = new \Dianxiaomi_Settings();
		try {
			update_option( 'dianxiaomi_option_name', array( 'plugin' => 'dianxiaomi' ) );
			$settings->page_init();
			ob_start();
			try {
				$settings->create_admin_page();
				$html = ob_get_contents();
			} finally {
				ob_end_clean();
			}
			$document = new \DOMDocument();
			$previous_errors = libxml_use_internal_errors( true );
			try {
				$document->loadHTML( $html );
			} finally {
				libxml_clear_errors();
				libxml_use_internal_errors( $previous_errors );
			}
			$xpath = new \DOMXPath( $document );
			$matches = $xpath->query( '//select[@id="plugin"]/option[@value="dianxiaomi"]' );
			$this->assertSame( 1, $matches->length, 'The settings selector needs an option element, not escaped HTML text.' );
			$this->assertSame( 'Dianxiaomi', $matches->item( 0 )->textContent );
			$this->assertTrue( $matches->item( 0 )->hasAttribute( 'selected' ) );
		} finally {
			foreach ( \Dianxiaomi_Settings::get_subscribed_events() as $hook => $callback ) {
				remove_action( $hook, array( $settings, $callback ) );
			}
			unregister_setting( 'dianxiaomi_option_group', 'dianxiaomi_option_name' );
			foreach ( $globals as $name => [ $existed, $value ] ) {
				if ( $existed ) {
					$GLOBALS[ $name ] = $value;
				} else {
					unset( $GLOBALS[ $name ] );
				}
			}
			if ( false === $original ) {
				delete_option( 'dianxiaomi_option_name' );
			} else {
				update_option( 'dianxiaomi_option_name', $original );
			}
		}
	}

	public function test_track_button_checkbox_stays_checked_for_stored_string_one(): void {
		$this->assertSettingsHtml(
			array(
				'plugin'           => 'dianxiaomi',
				'use_track_button' => '1',
			),
			function ( \DOMXPath $xpath ): void {
				$matches = $xpath->query( '//input[@id="use_track_button" and @checked]' );
				$this->assertSame( 1, $matches->length, 'A stored "1" enables the track button and must render checked.' );
			}
		);
	}

	/**
	 * @param array<string, mixed>    $options
	 * @param callable(\DOMXPath):void $assert
	 */
	private function assertSettingsHtml( array $options, callable $assert ): void {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		require_once ABSPATH . 'wp-admin/includes/template.php';
		$original = get_option( 'dianxiaomi_option_name' );
		$globals  = array();
		foreach ( array( 'wp_settings_sections', 'wp_settings_fields', 'wp_registered_settings', 'new_allowed_options', 'new_whitelist_options' ) as $name ) {
			$globals[ $name ] = array( array_key_exists( $name, $GLOBALS ), $GLOBALS[ $name ] ?? null );
		}
		$settings = new \Dianxiaomi_Settings();
		try {
			update_option( 'dianxiaomi_option_name', $options );
			$settings->page_init();
			ob_start();
			try {
				$settings->create_admin_page();
				$html = ob_get_contents();
			} finally {
				ob_end_clean();
			}
			$document        = new \DOMDocument();
			$previous_errors = libxml_use_internal_errors( true );
			try {
				$document->loadHTML( (string) $html );
			} finally {
				libxml_clear_errors();
				libxml_use_internal_errors( $previous_errors );
			}
			$assert( new \DOMXPath( $document ) );
		} finally {
			foreach ( \Dianxiaomi_Settings::get_subscribed_events() as $hook => $callback ) {
				remove_action( $hook, array( $settings, $callback ) );
			}
			unregister_setting( 'dianxiaomi_option_group', 'dianxiaomi_option_name' );
			foreach ( $globals as $name => [ $existed, $value ] ) {
				if ( $existed ) {
					$GLOBALS[ $name ] = $value;
				} else {
					unset( $GLOBALS[ $name ] );
				}
			}
			if ( false === $original ) {
				delete_option( 'dianxiaomi_option_name' );
			} else {
				update_option( 'dianxiaomi_option_name', $original );
			}
		}
	}
}
