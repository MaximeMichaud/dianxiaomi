<?php
/**
 * WooCommerce Helper Trait Tests.
 *
 * @package Dianxiaomi\Tests\Unit
 */

declare(strict_types=1);

namespace Dianxiaomi\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Dianxiaomi\Traits\WooCommerce_Helper;
use WC_Order;
use WC_Product;

/**
 * Test class that uses the WooCommerce_Helper trait.
 */
class TestWCHelperClass {
	use WooCommerce_Helper;

	/**
	 * Expose protected is_woocommerce_active method.
	 */
	public function test_is_woocommerce_active(): bool {
		return $this->is_woocommerce_active();
	}

	/**
	 * Expose protected get_wc_order method.
	 */
	public function test_get_wc_order( int $order_id ): ?WC_Order {
		return $this->get_wc_order( $order_id );
	}

	/**
	 * Expose protected get_product method.
	 */
	public function test_get_product( int $product_id ): ?WC_Product {
		return $this->get_product( $product_id );
	}

	/**
	 * Expose protected get_woocommerce_version method.
	 */
	public function test_get_woocommerce_version(): string {
		return $this->get_woocommerce_version();
	}

	/**
	 * Expose protected is_hpos_enabled method.
	 */
	public function test_is_hpos_enabled(): bool {
		return $this->is_hpos_enabled();
	}

	/**
	 * Expose protected get_order_edit_url method.
	 */
	public function test_get_order_edit_url( int $order_id ): string {
		return $this->get_order_edit_url( $order_id );
	}

	/**
	 * Expose protected get_order_meta method.
	 */
	public function test_get_order_meta( WC_Order|int $order, string $meta_key, bool $single = true ): mixed {
		return $this->get_order_meta( $order, $meta_key, $single );
	}

	/**
	 * Expose protected update_order_meta method.
	 */
	public function test_update_order_meta( WC_Order|int $order, string $meta_key, mixed $meta_value ): bool {
		return $this->update_order_meta( $order, $meta_key, $meta_value );
	}

	/**
	 * Expose protected get_order_screen_ids method.
	 */
	public function test_get_order_screen_ids(): array {
		return $this->get_order_screen_ids();
	}

	/**
	 * Expose protected is_order_edit_screen method.
	 */
	public function test_is_order_edit_screen(): bool {
		return $this->is_order_edit_screen();
	}
}

/**
 * Test class for WooCommerce_Helper trait.
 */
class WooCommerceHelperTraitTest extends TestCase {

	/**
	 * Test class instance.
	 *
	 * @var TestWCHelperClass
	 */
	private TestWCHelperClass $helper;

	/**
	 * Set up before each test.
	 */
	protected function setUp(): void {
		parent::setUp();
		\reset_all();
		$this->helper = new TestWCHelperClass();
	}

	/**
	 * Clean up after each test.
	 */
	protected function tearDown(): void {
		\reset_current_screen();
		parent::tearDown();
	}

	/**
	 * Test is_woocommerce_active returns true when WC class exists.
	 */
	public function test_is_woocommerce_active_returns_true(): void {
		// WooCommerce class is mocked in bootstrap.
		$this->assertTrue( $this->helper->test_is_woocommerce_active() );
	}

	/**
	 * Test get_wc_order returns WC_Order.
	 */
	public function test_get_wc_order_returns_order(): void {
		$order = \create_mock_order( 123 );

		$result = $this->helper->test_get_wc_order( 123 );

		$this->assertInstanceOf( WC_Order::class, $result );
		$this->assertEquals( 123, $result->get_id() );
	}

	/**
	 * Test get_wc_order creates new order if not cached.
	 */
	public function test_get_wc_order_creates_new_order(): void {
		$result = $this->helper->test_get_wc_order( 999 );

		$this->assertInstanceOf( WC_Order::class, $result );
		$this->assertEquals( 999, $result->get_id() );
	}

	/**
	 * Test get_wc_order returns null for invalid id.
	 */
	public function test_get_wc_order_returns_null_for_zero(): void {
		$result = $this->helper->test_get_wc_order( 0 );

		$this->assertNull( $result );
	}

	/**
	 * Test get_product returns WC_Product.
	 */
	public function test_get_product_returns_product(): void {
		$result = $this->helper->test_get_product( 456 );

		$this->assertInstanceOf( WC_Product::class, $result );
		$this->assertEquals( 456, $result->get_id() );
	}

	/**
	 * Test get_product returns null for invalid id.
	 */
	public function test_get_product_returns_null_for_zero(): void {
		$result = $this->helper->test_get_product( 0 );

		$this->assertNull( $result );
	}

	/**
	 * Test get_woocommerce_version returns empty without WC_VERSION.
	 */
	public function test_get_woocommerce_version_returns_empty(): void {
		// WC_VERSION is not defined in tests.
		$result = $this->helper->test_get_woocommerce_version();

		$this->assertEquals( '', $result );
	}

	/**
	 * Test is_hpos_enabled returns false when OrderUtil not present.
	 */
	public function test_is_hpos_enabled_returns_false(): void {
		// OrderUtil class doesn't exist in test env.
		$result = $this->helper->test_is_hpos_enabled();

		$this->assertFalse( $result );
	}

	/**
	 * Test get_order_edit_url returns legacy URL when HPOS disabled.
	 */
	public function test_get_order_edit_url_returns_legacy_url(): void {
		$result = $this->helper->test_get_order_edit_url( 100 );

		$this->assertStringContainsString( 'post.php', $result );
		$this->assertStringContainsString( 'post=100', $result );
		$this->assertStringContainsString( 'action=edit', $result );
	}

	/**
	 * Test get_order_edit_url includes order id.
	 */
	public function test_get_order_edit_url_includes_order_id(): void {
		$result = $this->helper->test_get_order_edit_url( 42 );

		$this->assertStringContainsString( '42', $result );
	}

	/**
	 * Test get_order_meta with order object.
	 */
	public function test_get_order_meta_with_order_object(): void {
		$order = \create_mock_order( 1, array( '_tracking_number' => 'ABC123' ) );

		$result = $this->helper->test_get_order_meta( $order, '_tracking_number' );

		$this->assertEquals( 'ABC123', $result );
	}

	/**
	 * Test get_order_meta with order id.
	 */
	public function test_get_order_meta_with_order_id(): void {
		$order = \create_mock_order( 200, array( '_status' => 'shipped' ) );

		$result = $this->helper->test_get_order_meta( 200, '_status' );

		$this->assertEquals( 'shipped', $result );
	}

	/**
	 * Test get_order_meta returns empty string for missing key.
	 */
	public function test_get_order_meta_returns_empty_for_missing(): void {
		$order = \create_mock_order( 300, array() );

		$result = $this->helper->test_get_order_meta( 300, '_nonexistent' );

		$this->assertEquals( '', $result );
	}

	/**
	 * Test get_order_meta returns empty for invalid order.
	 */
	public function test_get_order_meta_returns_empty_for_invalid(): void {
		$result = $this->helper->test_get_order_meta( 0, '_key' );

		$this->assertEquals( '', $result );
	}

	/**
	 * Test get_order_meta single false returns array.
	 */
	public function test_get_order_meta_single_false_returns_array(): void {
		$order = \create_mock_order( 400, array( '_key' => 'value' ) );

		$result = $this->helper->test_get_order_meta( 400, '_key', false );

		$this->assertIsArray( $result );
	}

	/**
	 * Test update_order_meta with order object.
	 */
	public function test_update_order_meta_with_order_object(): void {
		$order = \create_mock_order( 500 );

		$result = $this->helper->test_update_order_meta( $order, '_new_key', 'new_value' );

		$this->assertTrue( $result );
		$this->assertEquals( 'new_value', $order->get_meta( '_new_key' ) );
	}

	/**
	 * Test update_order_meta with order id.
	 */
	public function test_update_order_meta_with_order_id(): void {
		$order = \create_mock_order( 600 );

		$result = $this->helper->test_update_order_meta( 600, '_meta_key', 'meta_value' );

		$this->assertTrue( $result );
	}

	/**
	 * Test update_order_meta returns false for invalid order.
	 */
	public function test_update_order_meta_returns_false_for_invalid(): void {
		$result = $this->helper->test_update_order_meta( 0, '_key', 'value' );

		$this->assertFalse( $result );
	}

	/**
	 * Test get_order_screen_ids returns array.
	 */
	public function test_get_order_screen_ids_returns_array(): void {
		$result = $this->helper->test_get_order_screen_ids();

		$this->assertIsArray( $result );
		$this->assertContains( 'shop_order', $result );
		$this->assertContains( 'woocommerce_page_wc-orders', $result );
	}

	/**
	 * Test get_order_screen_ids contains both legacy and HPOS.
	 */
	public function test_get_order_screen_ids_contains_both_screens(): void {
		$result = $this->helper->test_get_order_screen_ids();

		$this->assertCount( 2, $result );
	}

	/**
	 * Test is_order_edit_screen returns false when no screen.
	 */
	public function test_is_order_edit_screen_returns_false_no_screen(): void {
		$result = $this->helper->test_is_order_edit_screen();

		$this->assertFalse( $result );
	}

	/**
	 * Test is_order_edit_screen returns false for wrong screen.
	 */
	public function test_is_order_edit_screen_returns_false_wrong_screen(): void {
		\set_current_screen( 'edit-post' );

		$result = $this->helper->test_is_order_edit_screen();

		$this->assertFalse( $result );
	}

	/**
	 * Test is_order_edit_screen returns true for shop_order.
	 */
	public function test_is_order_edit_screen_returns_true_for_shop_order(): void {
		\set_current_screen( 'shop_order' );

		$result = $this->helper->test_is_order_edit_screen();

		$this->assertTrue( $result );
	}

	/**
	 * Test is_order_edit_screen returns true for HPOS screen.
	 */
	public function test_is_order_edit_screen_returns_true_for_hpos(): void {
		\set_current_screen( 'woocommerce_page_wc-orders' );

		$result = $this->helper->test_is_order_edit_screen();

		$this->assertTrue( $result );
	}

	/**
	 * Test is_hpos_enabled returns true when OrderUtil says so.
	 */
	public function test_is_hpos_enabled_returns_true_when_enabled(): void {
		\Automattic\WooCommerce\Utilities\OrderUtil::set_hpos_enabled( true );

		$result = $this->helper->test_is_hpos_enabled();

		$this->assertTrue( $result );
	}

	/**
	 * Test get_order_edit_url returns HPOS URL when HPOS enabled.
	 */
	public function test_get_order_edit_url_returns_hpos_url_when_enabled(): void {
		\Automattic\WooCommerce\Utilities\OrderUtil::set_hpos_enabled( true );

		$result = $this->helper->test_get_order_edit_url( 150 );

		$this->assertStringContainsString( 'admin.php', $result );
		$this->assertStringContainsString( 'page=wc-orders', $result );
		$this->assertStringContainsString( 'id=150', $result );
		$this->assertStringNotContainsString( 'post.php', $result );
	}

	/**
	 * Test get_order_edit_url returns legacy URL when HPOS disabled.
	 */
	public function test_get_order_edit_url_returns_legacy_url_when_hpos_disabled(): void {
		\Automattic\WooCommerce\Utilities\OrderUtil::set_hpos_enabled( false );

		$result = $this->helper->test_get_order_edit_url( 200 );

		$this->assertStringContainsString( 'post.php', $result );
		$this->assertStringContainsString( 'post=200', $result );
		$this->assertStringNotContainsString( 'wc-orders', $result );
	}
}
