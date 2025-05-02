<?php
/**
 * Plugin Name: WC Name Your Price Buy Now 
 * Plugin URI: https://github.com/backcourt/wc-nyp-buy-now
 * Description: Add a Buy Now button with a set price to WooCommerce Name Your Price products.
 * Version: 1.0.0
 * Author: Backcourt Development
 * Author URI: http://www.backcourt.io
 * 
 * Update URI: backcourt/wc-nyp-buy-now
 *
 * Requires at least: 4.4.0
 * Tested up to: 6.8.0
 *
 * WC requires at least: 4.0.0
 * WC tested up to: 9.9.0
 *
 * Requires PHP: 8.1
 *
 * Text Domain: wc-nyp-buy-now
 * Domain Path: /languages/
 *
 * Copyright: © 2025 Backcourt Development.
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 *
 * @package WooCommerce Name Your Price
 */

defined( 'ABSPATH' ) || exit;

use Backcourt\MNMVariable\Vendor\Fragen;

/**
 * Add Git Updater Lite
 */
if ( file_exists( __DIR__ . '/packages/autoload.php' ) ) {
	require_once __DIR__ . '/packages/autoload.php';
	( new Fragen\Git_Updater\Lite( __FILE__ ) )->run();
}

/**
 * Declare Features compatibility
 */
add_action( 'before_woocommerce_init', function () {

	if ( ! class_exists( 'Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
		return;
	}

	// HPOS (Custom Order tables) compatibility.
	\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', plugin_basename( __FILE__ ), true );

	// Cart/Checkout Blocks compatibility.
	\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', plugin_basename( __FILE__ ), true );
} );

/**
 * Add Button after Add to Cart Button
 */
function wc_nyp_buy_now() {
	global $product;
	
	if ( class_exists( 'WC_Name_Your_Price_Helpers' ) && WC_Name_Your_Price_Helpers::is_nyp( $product ) ) {
		// translators: %s is the buy now price.
		echo '<button name="wc_nyp_buy_now" type="submit" style="float:none" value="' . esc_attr( $product->get_id() ) . '" class="button wc-nyp-buy-now ' . esc_attr( wp_theme_get_element_class_name( 'button' ) ) . '" >' . sprintf( esc_html__( 'Buy now for %s', 'wc-nyp-buy-now' ), wp_kses_post( wc_price( $product->get_meta( '_wc_nyp_buy_now_price', true ) ) ) ) . '</button>';
	}
}
add_action( 'woocommerce_after_add_to_cart_button', 'wc_nyp_buy_now' );

/**
 * Listen for quick add to cart requests and switch the REQUEST vars so Woo will handle the add to cart correctly.
 */
function wc_nyp_quick_add_to_cart() {
	
	if (
		! class_exists( 'WC_Name_Your_Price_Helpers' ) ||
		! isset( $_REQUEST['wc_nyp_buy_now'] ) ||
		! WC_Name_Your_Price_Helpers::is_nyp( wp_unslash( $_REQUEST['wc_nyp_buy_now'] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	) {
		return;
	}

	// Manipulate the request to make it look like a normal NYP add to cart.
	$_REQUEST['add-to-cart'] = $add_to_cart_id;

	$product = wc_get_product( $add_to_cart_id );

	if ( ! $product ) {
		return;
	}

	// Set the price to the buy now price.
	$_REQUEST['nyp'] = $product->get_meta( '_wc_nyp_buy_now_price', true );
}
add_action( 'wp_loaded', 'wc_nyp_quick_add_to_cart' );

/**
 * Add Buy now price input to product metabox
 *
 * @param  object WC_Product $product_object
 */
function wc_nyp_buy_now_price_input( $product_object ) {

	// Buy now price.
	woocommerce_wp_text_input(
		array(
			'id'            => '_wc_nyp_buy_now_price',
			'class'         => 'wc_input_price short',
			'wrapper_class' => '',
			'label'         => esc_html__( 'Buy now price', 'wc-nyp-buy-now' ) . ' (' . get_woocommerce_currency_symbol() . ')',
			'desc_tip'      => 'true',
			'description'   => esc_html__( 'Adds a button next to add to cart button to quickly add to cart with set price. Leave blank to not add buy now option. Must be greater than or equal to the set minimum price.', 'wc-nyp-buy-now' ),
			'data_type'     => 'price',
			'value'         => $product_object->get_meta( '_wc_nyp_buy_now_price', true, 'edit' ),
		)
	);
}
add_action( 'wc_nyp_options_pricing', 'wc_nyp_buy_now_price_input', 50, 2 );

/**
 * Save extra meta info
 *
 * @param object $product
 */
function wc_nyp_buy_now_save_product_meta( $product ) {

	// phpcs:disable WordPress.Security.NonceVerification
	$buynow  = '';
	$minimum = '';

	$buynow = isset( $_POST['_wc_nyp_buy_now_price'] ) ? wc_format_decimal( wc_clean( wp_unslash( $_POST['_wc_nyp_buy_now_price'] ) ) ) : '';
	$product->update_meta_data( '_wc_nyp_buy_now_price', $buynow );

	$minimum = $product->get_meta( '_min_price', true, 'edit' );

	// Show error if minimum price is higher than the buy now price.
	if ( $buynow && $minimum && $minimum > $buynow ) {
		// Translators: %d variation ID.
		$error_notice = esc_html__( 'The suggested price must be higher than the minimum for Name Your Price products. Please review your prices.', 'wc-nyp-buy-now' );
		WC_Admin_Meta_Boxes::add_error( $error_notice );
	}
}
add_action( 'woocommerce_admin_process_product_object', 'wc_nyp_buy_now_save_product_meta' );
