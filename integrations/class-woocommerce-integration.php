<?php
/**
 * Optional WooCommerce configuration monitoring only.
 *
 * @package WPDecisionLog
 */

namespace WPDecisionLog;

/**
 * WooCommerce Integration service.
 */
class WooCommerce_Integration {

	const OPTIONS = array(
		'woocommerce_currency',
		'woocommerce_price_num_decimals',
		'woocommerce_enable_ajax_add_to_cart',
		'woocommerce_cart_redirect_after_add',
		'woocommerce_calc_taxes',
		'woocommerce_prices_include_tax',
		'woocommerce_manage_stock',
	);
	/**
	 * Register the service hooks.
	 */
	public function __construct() {
		if ( self::active() ) {
			add_action( 'updated_option', array( $this, 'option' ), 10, 3 );
			add_action( 'save_post_product', array( $this, 'product' ), 10, 3 );
		}
	}
	/**
	 * Detect the optional integration without requiring it.
	 *
	 * @return bool
	 */
	public static function active() {
		return class_exists( 'WooCommerce' );
	}
	/**
	 * Handle only explicitly allowlisted option updates.
	 *
	 * @param string $name Table, setting or theme label.
	 *
	 * @return void
	 */
	public function option( $name ) {
		if ( in_array( $name, self::OPTIONS, true ) ) {
			Change_Monitor::changed( 'option', $name, 'woocommerce' );
		}
	}
	/**
	 * Record an existing WooCommerce product post update.
	 *
	 * @param int      $id Decision identifier.
	 * @param \WP_Post $post Saved product post.
	 * @param bool     $update Whether the post already existed.
	 *
	 * @return void
	 */
	public function product( $id, $post, $update ) {
		if ( $update && ! wp_is_post_revision( $id ) && ! wp_is_post_autosave( $id ) ) {
			Change_Monitor::changed( 'post', (string) $id, 'woocommerce' );
		}
	}
}
