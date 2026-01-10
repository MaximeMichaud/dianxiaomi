<?php
/**
 * WordPress Stub Trait.
 *
 * Provides stub methods for WordPress functions used in tests.
 *
 * @package Dianxiaomi\Tests
 */

declare(strict_types=1);

namespace Dianxiaomi\Tests;

/**
 * Trait for WordPress function stubs.
 */
trait WPStubTrait {

	/**
	 * Plugin constants.
	 *
	 * @var array
	 */
	protected array $constants = array();

	/**
	 * Reset stub properties to defaults.
	 */
	protected function reset_stub_properties(): void {
		$this->constants = array(
			'DIANXIAOMI_VERSION'           => '1.5.2',
			'DIANXIAOMI_WP_VERSION'         => '5.8',
			'DIANXIAOMI_WP_VERSION_TESTED'  => '6.9',
			'DIANXIAOMI_PHP_VERSION'        => '8.1',
			'DIANXIAOMI_WC_VERSION'         => '8.0',
			'DIANXIAOMI_WC_VERSION_TESTED'  => '10.4',
			'DIANXIAOMI_FILE'               => '/var/www/html/wp-content/plugins/dianxiaomi/dianxiaomi.php',
			'DIANXIAOMI_PATH'               => '/var/www/html/wp-content/plugins/dianxiaomi/',
			'DIANXIAOMI_URL'                => 'https://example.com/wp-content/plugins/dianxiaomi/',
		);
	}

	/**
	 * Get a constant value for testing.
	 *
	 * @param string $name    Constant name.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	protected function get_constant( string $name, $default = null ) {
		return $this->constants[ $name ] ?? $default;
	}

	/**
	 * Set a constant value for testing.
	 *
	 * @param string $name  Constant name.
	 * @param mixed  $value Constant value.
	 */
	protected function set_constant( string $name, $value ): void {
		$this->constants[ $name ] = $value;
	}

	/**
	 * Stub wp_parse_url function.
	 *
	 * @param string $url       URL to parse.
	 * @param int    $component Component to retrieve.
	 * @return mixed
	 */
	protected function stub_wp_parse_url( string $url, int $component = -1 ) {
		return parse_url( $url, $component );
	}

	/**
	 * Stub wp_json_encode function.
	 *
	 * @param mixed $data    Data to encode.
	 * @param int   $options JSON options.
	 * @param int   $depth   Max depth.
	 * @return string|false
	 */
	protected function stub_wp_json_encode( $data, int $options = 0, int $depth = 512 ) {
		return json_encode( $data, $options, $depth );
	}
}
