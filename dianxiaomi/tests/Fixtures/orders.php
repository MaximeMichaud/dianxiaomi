<?php
/**
 * Order Fixtures.
 *
 * Sample order data for testing.
 *
 * @package Dianxiaomi\Tests\Fixtures
 */

declare(strict_types=1);

return array(
	'basic_order'         => array(
		'id'               => 123,
		'status'           => 'processing',
		'billing_email'    => 'customer@example.com',
		'billing_country'  => 'US',
		'shipping_country' => 'US',
		'total'            => '99.99',
		'currency'         => 'USD',
	),
	'with_tracking'       => array(
		'id'                => 456,
		'status'            => 'completed',
		'billing_email'     => 'tracked@example.com',
		'tracking_number'   => 'TRACK123456789',
		'tracking_provider' => 'ups',
	),
	'international_order' => array(
		'id'               => 789,
		'status'           => 'processing',
		'billing_email'    => 'international@example.com',
		'billing_country'  => 'FR',
		'shipping_country' => 'CN',
		'total'            => '199.99',
		'currency'         => 'EUR',
	),
	'hpos_order'          => array(
		'id'     => 1001,
		'status' => 'on-hold',
		'meta'   => array(
			'_aftership_tracking_number'        => 'HPOS123',
			'_aftership_tracking_provider_name' => 'fedex',
		),
	),
);
