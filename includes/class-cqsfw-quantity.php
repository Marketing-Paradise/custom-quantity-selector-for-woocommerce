<?php
/**
 * WooCommerce quantity selector and decimal support module.
 *
 * @package Custom_Quantity_Selector_For_WooCommerce
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CQSFW_Quantity.
 *
 * Enables decimal quantities and applies the configured step and minimum
 * quantity across product pages, variations, shop loops, and the cart.
 *
 * @since 1.0.0
 */
class CQSFW_Quantity {

	/**
	 * Register WooCommerce quantity hooks.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		// Allow decimal quantities by replacing WooCommerce's default intval filter.
		remove_filter( 'woocommerce_stock_amount', 'intval' );
		add_filter( 'woocommerce_stock_amount', array( $this, 'filter_stock_amount' ) );

		// Apply step and minimum quantity across classic templates and shop loops.
		add_filter( 'woocommerce_quantity_input_args', array( $this, 'filter_quantity_input_args' ), 10, 2 );
		add_filter( 'woocommerce_available_variation', array( $this, 'filter_variation_quantity' ), 10, 3 );
		add_filter( 'woocommerce_loop_add_to_cart_args', array( $this, 'filter_loop_add_to_cart_args' ), 10, 2 );

		// Conditional Store API filters when configured values are whole integers.
		add_filter( 'woocommerce_store_api_product_quantity_minimum', array( $this, 'filter_store_api_minimum' ), 10, 3 );
		add_filter( 'woocommerce_store_api_product_quantity_multiple_of', array( $this, 'filter_store_api_multiple_of' ), 10, 3 );
	}

	/**
	 * Sanitize stock and cart item quantities as floats instead of integers.
	 *
	 * @since 1.0.0
	 * @param int|float|string $amount Raw quantity or stock amount.
	 * @return float
	 */
	public function filter_stock_amount( $amount ) {
		return floatval( $amount );
	}

	/**
	 * Modify quantity input attributes on single product pages and the cart.
	 *
	 * @since 1.0.0
	 * @param array<string, mixed> $args    Quantity input arguments.
	 * @param WC_Product|null      $product Product instance.
	 * @return array<string, mixed>
	 */
	public function filter_quantity_input_args( $args, $product ) {
		if ( ! $this->should_apply_to_product( $product ) ) {
			return $args;
		}

		$settings     = CQSFW_Plugin::get_settings();
		$step         = $settings['step'];
		$min_quantity = $settings['min_quantity'];
		$input_name   = isset( $args['input_name'] ) ? (string) $args['input_name'] : '';
		$is_grouped   = 0 === strpos( $input_name, 'quantity[' );
		$has_decimals = ! $this->is_whole_number( $step ) || ! $this->is_whole_number( $min_quantity );

		$args['step']      = $step;
		$args['inputmode'] = $has_decimals ? 'decimal' : 'numeric';
		$args['pattern']   = $has_decimals ? '' : '[0-9]*';

		// Do not force a non-zero minimum on grouped product child rows.
		if ( ! $is_grouped ) {
			$args['min_value'] = $min_quantity;
		}

		// Set initial value on product pages without overwriting existing cart quantities.
		if ( ! is_cart() && 'quantity' === $input_name ) {
			$current_input  = isset( $args['input_value'] ) ? (float) $args['input_value'] : 0.0;
			$default_wc_min = (float) $product->get_min_purchase_quantity();

			if ( $current_input < $min_quantity || abs( $current_input - $default_wc_min ) < 0.0001 ) {
				$args['input_value'] = $min_quantity;
			}
		}

		return $args;
	}

	/**
	 * Apply the minimum quantity to variable product variation data.
	 *
	 * @since 1.0.0
	 * @param array<string, mixed> $variation_data Variation data sent to the frontend.
	 * @param WC_Product_Variable  $product        Parent variable product.
	 * @param WC_Product_Variation $variation      Variation product instance.
	 * @return array<string, mixed>
	 */
	public function filter_variation_quantity( $variation_data, $product, $variation ) {
		if ( ! $this->should_apply_to_product( $variation ) ) {
			return $variation_data;
		}

		$settings                  = CQSFW_Plugin::get_settings();
		$variation_data['min_qty'] = $settings['min_quantity'];

		return $variation_data;
	}

	/**
	 * Set the default quantity added from shop and archive "Add to cart" buttons.
	 *
	 * @since 1.0.0
	 * @param array<string, mixed> $args    Loop button arguments.
	 * @param WC_Product           $product Product instance.
	 * @return array<string, mixed>
	 */
	public function filter_loop_add_to_cart_args( $args, $product ) {
		if ( ! $this->should_apply_to_product( $product ) ) {
			return $args;
		}

		$settings         = CQSFW_Plugin::get_settings();
		$args['quantity'] = $settings['min_quantity'];

		return $args;
	}

	/**
	 * Apply minimum quantity to Store API when the configured value is a whole integer.
	 *
	 * @since 1.0.0
	 * @param int                 $value     Current minimum quantity.
	 * @param WC_Product          $product   Product instance.
	 * @param array<string, mixed> $cart_item Cart item data.
	 * @return int
	 */
	public function filter_store_api_minimum( $value, $product, $cart_item ) {
		unset( $cart_item );

		if ( ! $this->should_apply_to_product( $product ) ) {
			return $value;
		}

		$settings = CQSFW_Plugin::get_settings();

		if ( ! $this->is_whole_number( $settings['min_quantity'] ) ) {
			return $value;
		}

		return max( 1, (int) round( $settings['min_quantity'] ) );
	}

	/**
	 * Apply step to Store API only when step and minimum are compatible whole integers.
	 *
	 * @since 1.0.0
	 * @param int                 $value     Current step multiplier.
	 * @param WC_Product          $product   Product instance.
	 * @param array<string, mixed> $cart_item Cart item data.
	 * @return int
	 */
	public function filter_store_api_multiple_of( $value, $product, $cart_item ) {
		unset( $cart_item );

		if ( ! $this->should_apply_to_product( $product ) ) {
			return $value;
		}

		$settings = CQSFW_Plugin::get_settings();
		$step     = $settings['step'];
		$min_qty  = $settings['min_quantity'];

		if ( ! $this->is_whole_number( $step ) || ! $this->is_whole_number( $min_qty ) ) {
			return $value;
		}

		$int_step = (int) round( $step );
		$int_min  = (int) round( $min_qty );

		// Store API requires quantities to be exact multiples of multiple_of.
		if ( $int_step > 0 && 0 === ( $int_min % $int_step ) ) {
			return $int_step;
		}

		return $value;
	}

	/**
	 * Determine whether custom quantity rules should apply to a given product.
	 *
	 * @since 1.0.0
	 * @param mixed $product Product object.
	 * @return bool
	 */
	private function should_apply_to_product( $product ) {
		if ( ! $product instanceof WC_Product ) {
			return false;
		}

		if ( $product->is_sold_individually() ) {
			return false;
		}

		return true;
	}

	/**
	 * Check whether a float represents an exact whole integer.
	 *
	 * @since 1.0.0
	 * @param float $number Number to inspect.
	 * @return bool
	 */
	private function is_whole_number( $number ) {
		return abs( $number - round( $number ) ) < 0.0001;
	}
}