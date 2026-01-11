<?php
/**
 * PHPUnit bootstrap file for WordPress integration tests.
 *
 * Loads WordPress and WooCommerce in the wp-env environment.
 *
 * @package Dianxiaomi
 */

// Composer autoloader.
require_once dirname( __DIR__ ) . '/vendor/autoload.php';

// Load WordPress.
$wp_load = '/var/www/html/wp-load.php';
if ( file_exists( $wp_load ) ) {
	require_once $wp_load;
} else {
	echo "WordPress not found. Run tests with wp-env:\n";
	echo "  npm run start\n";
	echo "  npm run test\n";
	exit( 1 );
}

// Ensure WooCommerce is loaded.
if ( ! class_exists( 'WooCommerce' ) ) {
	echo "WooCommerce not found. Make sure it's installed in wp-env.\n";
	exit( 1 );
}

// Load our plugin if not already loaded.
if ( ! class_exists( 'Dianxiaomi' ) ) {
	require_once dirname( __DIR__ ) . '/dianxiaomi.php';
}
