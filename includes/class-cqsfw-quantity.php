<?php
/**
 * WooCommerce quantity selector, decimal support, and server-side validation module.
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
 * Enables decimal quantities, applies the configured step and minimum quantity
 * across templates, and enforces server-side cart and checkout validation.
 *
 * @since 1.0.0
 */
class CQSFW_Quantity {

	/**
	 * Register WooCommerce quantity and validation hooks.
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

		// Server-side validation on add to cart, cart update, and cart/checkout checks.
		add_filter( 'woocommerce_add_to_cart_validation', array( $this, 'validate_add_to_cart' ), 10, 4 );
		add_filter( 'woocommerce_update_cart_validation', array( $this, 'validate_update_cart' ), 10, 4 );
		add_action( 'woocommerce_check_cart_items', array( $this, 'validate_cart_items' ) );
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
		unset( $product );

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
	 * @param int                  $value     Current minimum quantity.
	 * @param WC_Product           $product   Product instance.
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
	 * @param int                  $value     Current step multiplier.
	 * @param WC_Product           $product   Product instance.
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
	 * Validate submitted quantity when adding a product to the cart.
	 *
	 * @since 1.0.0
	 * @param bool      $passed       Whether validation has passed so far.
	 * @param int       $product_id   Product ID.
	 * @param int|float $quantity     Requested quantity.
	 * @param int       $variation_id Variation ID (optional; AJAX calls pass 3 args).
	 * @return bool
	 */
	public function validate_add_to_cart( $passed, $product_id, $quantity, $variation_id = 0 ) {
		if ( ! $passed ) {
			return false;
		}

		$target_id = absint( $variation_id ) > 0 ? absint( $variation_id ) : absint( $product_id );
		$product   = wc_get_product( $target_id );

		if ( ! $this->should_apply_to_product( $product ) ) {
			return $passed;
		}

		return $this->validate_quantity( (float) $quantity, $product );
	}

	/**
	 * Validate submitted quantity when updating an item in the cart.
	 *
	 * @since 1.0.0
	 * @param bool                 $passed        Whether validation has passed so far.
	 * @param string               $cart_item_key Cart item key.
	 * @param array<string, mixed> $values        Cart item data.
	 * @param int|float            $quantity      New requested quantity.
	 * @return bool
	 */
	public function validate_update_cart( $passed, $cart_item_key, $values, $quantity ) {
		unset( $cart_item_key );

		if ( ! $passed ) {
			return false;
		}

		$parsed_quantity = (float) $quantity;

		// Allow zero quantity so customers can remove an item from the cart.
		if ( 0.0 === $parsed_quantity ) {
			return $passed;
		}

		$product = isset( $values['data'] ) ? $values['data'] : null;

		if ( ! $this->should_apply_to_product( $product ) ) {
			return $passed;
		}

		return $this->validate_quantity( $parsed_quantity, $product );
	}

	/**
	 * Verify all current cart item quantities on cart and checkout pages.
	 *
	 * Prevents checkout if settings changed after items were added or if a
	 * combined quantity in the cart no longer matches the configured rules.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function validate_cart_items() {
		if ( ! function_exists( 'WC' ) || ! WC()->cart || WC()->cart->is_empty() ) {
			return;
		}

		foreach ( WC()->cart->get_cart() as $cart_item ) {
			$product = isset( $cart_item['data'] ) ? $cart_item['data'] : null;

			if ( ! $this->should_apply_to_product( $product ) ) {
				continue;
			}

			$quantity = isset( $cart_item['quantity'] ) ? (float) $cart_item['quantity'] : 0.0;
			$this->validate_quantity( $quantity, $product );
		}
	}

	/**
	 * Validate a quantity against minimum quantity, 2-decimal precision, and step rules.
	 *
	 * @since 1.0.0
	 * @param float      $quantity Quantity to validate.
	 * @param WC_Product $product  Product instance.
	 * @return bool
	 */
	private function validate_quantity( $quantity, $product ) {
		$settings     = CQSFW_Plugin::get_settings();
		$step         = $settings['step'];
		$min_quantity = $settings['min_quantity'];
		$product_name = $product->get_name();

		if ( $quantity < ( $min_quantity - 0.0001 ) ) {
			$this->add_minimum_error_notice( $product_name, $min_quantity );
			return false;
		}

		if ( ! $this->has_valid_precision( $quantity ) ) {
			$this->add_step_error_notice( $product_name, $step, $min_quantity );
			return false;
		}

		// Scale values to integer hundredths to avoid IEEE-754 floating-point modulo errors.
		$qty_scaled  = (int) round( $quantity * 100 );
		$min_scaled  = (int) round( $min_quantity * 100 );
		$step_scaled = (int) round( $step * 100 );

		if ( $qty_scaled < $min_scaled ) {
			$this->add_minimum_error_notice( $product_name, $min_quantity );
			return false;
		}

		if ( $step_scaled > 0 && 0 !== ( ( $qty_scaled - $min_scaled ) % $step_scaled ) ) {
			$this->add_step_error_notice( $product_name, $step, $min_quantity );
			return false;
		}

		return true;
	}

	/**
	 * Add a formatted error notice when a quantity is below the configured minimum.
	 *
	 * @since 1.0.0
	 * @param string $product_name Product display name.
	 * @param float  $min_quantity Configured minimum quantity.
	 * @return void
	 */
	private function add_minimum_error_notice( $product_name, $min_quantity ) {
		$message = sprintf(
			/* translators: 1: Product name, 2: Minimum allowed quantity. */
			__( 'The minimum allowed quantity for "%1$s" is %2$s.', 'custom-quantity-selector-for-woocommerce' ),
			esc_html( $product_name ),
			esc_html( $this->format_quantity( $min_quantity ) )
		);

		$this->add_unique_error_notice( $message );
	}

	/**
	 * Add a formatted error notice when a quantity does not match the configured step.
	 *
	 * @since 1.0.0
	 * @param string $product_name Product display name.
	 * @param float  $step         Configured quantity step.
	 * @param float  $min_quantity Configured minimum quantity.
	 * @return void
	 */
	private function add_step_error_notice( $product_name, $step, $min_quantity ) {
		$message = sprintf(
			/* translators: 1: Product name, 2: Minimum allowed quantity, 3: Quantity step increment. */
			__( 'Quantity for "%1$s" must start at %2$s and increase in steps of %3$s.', 'custom-quantity-selector-for-woocommerce' ),
			esc_html( $product_name ),
			esc_html( $this->format_quantity( $min_quantity ) ),
			esc_html( $this->format_quantity( $step ) )
		);

		$this->add_unique_error_notice( $message );
	}

	/**
	 * Register a WooCommerce error notice only if an identical notice is not already queued.
	 *
	 * @since 1.0.0
	 * @param string $message Escaped notice message.
	 * @return void
	 */
	private function add_unique_error_notice( $message ) {
		if ( ! function_exists( 'wc_add_notice' ) ) {
			return;
		}

		if ( function_exists( 'wc_has_notice' ) && wc_has_notice( $message, 'error' ) ) {
			return;
		}

		wc_add_notice( $message, 'error' );
	}

	/**
	 * Format a numeric quantity using WooCommerce's localized decimal separator.
	 *
	 * @since 1.0.0
	 * @param float $quantity Quantity value.
	 * @return string
	 */
	private function format_quantity( $quantity ) {
		if ( function_exists( 'wc_format_localized_decimal' ) ) {
			return wc_format_localized_decimal( (string) $quantity );
		}

		return (string) $quantity;
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

	/**
	 * Check whether a quantity has at most two decimal places.
	 *
	 * @since 1.0.0
	 * @param float $number Number to inspect.
	 * @return bool
	 */
	private function has_valid_precision( $number ) {
		$scaled = $number * 100;
		return abs( $scaled - round( $scaled ) ) < 0.0001;
	}
}