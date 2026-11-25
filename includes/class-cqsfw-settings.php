<?php
/**
 * WooCommerce admin settings module.
 *
 * @package Custom_Quantity_Selector_For_WooCommerce
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CQSFW_Settings.
 *
 * Registers the "Quantity" subsection under WooCommerce > Settings > Products,
 * adds the per-product minimum quantity field in Product data > Inventory,
 * sanitizes decimal inputs, and adds a direct Settings action link.
 *
 * @since 1.0.0
 */
class CQSFW_Settings {

	/**
	 * WooCommerce Products tab subsection slug.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const SECTION_ID = 'cqsfw_quantity';

	/**
	 * Register admin hooks.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		add_filter( 'woocommerce_get_sections_products', array( $this, 'add_settings_section' ) );
		add_filter( 'woocommerce_get_settings_products', array( $this, 'get_settings_fields' ), 10, 2 );
		add_filter(
			'woocommerce_admin_settings_sanitize_option_' . CQSFW_Plugin::OPTION_NAME,
			array( $this, 'sanitize_setting' ),
			10,
			3
		);
		add_filter( 'plugin_action_links_' . CQSFW_PLUGIN_BASENAME, array( $this, 'add_plugin_action_links' ) );

		// Per-product minimum quantity override in Product data > Inventory.
		add_action( 'woocommerce_product_options_inventory_product_data', array( $this, 'add_product_min_quantity_field' ) );
		add_action( 'woocommerce_admin_process_product_object', array( $this, 'save_product_min_quantity_field' ) );
	}

	/**
	 * Add the "Quantity" subsection under WooCommerce > Settings > Products.
	 *
	 * @since 1.0.0
	 * @param array<string, string> $sections Existing product settings sections.
	 * @return array<string, string>
	 */
	public function add_settings_section( $sections ) {
		$sections[ self::SECTION_ID ] = __( 'Quantity', 'custom-quantity-selector-for-woocommerce' );
		return $sections;
	}

	/**
	 * Define the settings fields for the "Quantity" subsection.
	 *
	 * @since 1.0.0
	 * @param array<int, array<string, mixed>> $settings        Existing settings fields.
	 * @param string                           $current_section Active section slug.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_settings_fields( $settings, $current_section ) {
		if ( self::SECTION_ID !== $current_section ) {
			return $settings;
		}

		return array(
			array(
				'title' => __( 'Custom quantity selector', 'custom-quantity-selector-for-woocommerce' ),
				'desc'  => __( 'Configure the global quantity increment (step) and minimum purchase quantity for products. Both values accept numbers greater than zero with up to two decimal places.', 'custom-quantity-selector-for-woocommerce' ),
				'type'  => 'title',
				'id'    => 'cqsfw_quantity_options',
			),
			array(
				'title'             => __( 'Quantity step', 'custom-quantity-selector-for-woocommerce' ),
				'desc'              => __( 'Increment value used when changing product quantities (e.g., 0.5, 1, 2.25).', 'custom-quantity-selector-for-woocommerce' ),
				'desc_tip'          => true,
				'id'                => CQSFW_Plugin::OPTION_NAME . '[step]',
				'type'              => 'number',
				'default'           => '1',
				'custom_attributes' => array(
					'step' => '0.01',
					'min'  => '0.01',
				),
			),
			array(
				'title'             => __( 'Minimum quantity', 'custom-quantity-selector-for-woocommerce' ),
				'desc'              => __( 'This global setting can be overridden individually on each product (Product data > Inventory).', 'custom-quantity-selector-for-woocommerce' ),
				'desc_tip'          => __( 'Minimum quantity allowed per product on product pages and in the cart (e.g., 0.5, 1, 5).', 'custom-quantity-selector-for-woocommerce' ),
				'id'                => CQSFW_Plugin::OPTION_NAME . '[min_quantity]',
				'type'              => 'number',
				'default'           => '1',
				'custom_attributes' => array(
					'step' => '0.01',
					'min'  => '0.01',
				),
			),
			array(
				'type' => 'sectionend',
				'id'   => 'cqsfw_quantity_options',
			),
		);
	}

	/**
	 * Render the per-product Minimum quantity field under Product data > Inventory.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function add_product_min_quantity_field() {
		global $post, $product_object;

		$product = $product_object;

		if ( ! $product instanceof WC_Product && isset( $post->ID ) ) {
			$product = wc_get_product( $post->ID );
		}

		$global_settings = CQSFW_Plugin::get_settings();
		$global_min      = $global_settings['min_quantity'];
		$formatted_min   = function_exists( 'wc_format_localized_decimal' )
			? wc_format_localized_decimal( (string) $global_min )
			: (string) $global_min;

		$current_value = '';

		if ( $product instanceof WC_Product ) {
			$saved_meta = $product->get_meta( CQSFW_Plugin::PRODUCT_MIN_QTY_META_KEY, true );

			if ( '' !== $saved_meta && null !== $saved_meta ) {
				$current_value = (string) $saved_meta;
			}
		}

		echo '<div class="options_group hide_if_external hide_if_grouped">';

		wp_nonce_field( 'cqsfw_save_product_min_quantity', 'cqsfw_product_min_quantity_nonce' );

		woocommerce_wp_text_input(
			array(
				'id'                => CQSFW_Plugin::PRODUCT_MIN_QTY_META_KEY,
				'value'             => $current_value,
				'label'             => __( 'Minimum quantity', 'custom-quantity-selector-for-woocommerce' ),
				'placeholder'       => (string) $global_min,
				'type'              => 'number',
				'desc_tip'          => false,
				'description'       => sprintf(
					/* translators: %s: Global minimum quantity value. */
					__( 'Overrides the global minimum quantity for this product. Leave empty to use the global setting (currently: %s).', 'custom-quantity-selector-for-woocommerce' ),
					esc_html( $formatted_min )
				),
				'custom_attributes' => array(
					'step' => '0.01',
					'min'  => '0.01',
				),
			)
		);

		echo '</div>';
	}

	/**
	 * Validate and save the per-product Minimum quantity meta value.
	 *
	 * @since 1.0.0
	 * @param WC_Product $product Product being saved.
	 * @return void
	 */
	public function save_product_min_quantity_field( $product ) {
		if ( ! $product instanceof WC_Product ) {
			return;
		}

		if ( ! isset( $_POST['cqsfw_product_min_quantity_nonce'] ) ) {
			return;
		}

		$nonce = sanitize_text_field( wp_unslash( $_POST['cqsfw_product_min_quantity_nonce'] ) );

		if ( ! wp_verify_nonce( $nonce, 'cqsfw_save_product_min_quantity' ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $product->get_id() ) ) {
			return;
		}

		$meta_key = CQSFW_Plugin::PRODUCT_MIN_QTY_META_KEY;

		if ( ! isset( $_POST[ $meta_key ] ) ) {
			return;
		}

		$raw_value  = sanitize_text_field( wp_unslash( $_POST[ $meta_key ] ) );
		$normalized = str_replace( ',', '.', trim( $raw_value ) );

		if ( '' === $normalized ) {
			$product->delete_meta_data( $meta_key );
			return;
		}

		if ( ! is_numeric( $normalized ) ) {
			$this->add_product_validation_error();
			return;
		}

		$parsed = round( (float) $normalized, 2 );

		if ( 0 >= $parsed ) {
			$this->add_product_validation_error();
			return;
		}

		$product->update_meta_data( $meta_key, $parsed );
	}

	/**
	 * Sanitize and validate each numeric field before saving to wp_options.
	 *
	 * @since 1.0.0
	 * @param mixed                $value     Sanitized value provided by WooCommerce.
	 * @param array<string, mixed> $option    Setting field definition.
	 * @param mixed                $raw_value Raw submitted value.
	 * @return mixed
	 */
	public function sanitize_setting( $value, $option, $raw_value ) {
		$option_id = isset( $option['id'] ) ? (string) $option['id'] : '';
		$field_key = $this->extract_field_key( $option_id );

		if ( null === $field_key ) {
			return $value;
		}

		$normalized = str_replace( ',', '.', trim( (string) $raw_value ) );

		if ( '' === $normalized || ! is_numeric( $normalized ) ) {
			$this->add_validation_error( $field_key );
			return $this->get_fallback_value( $field_key );
		}

		$parsed = round( (float) $normalized, 2 );

		if ( 0 >= $parsed ) {
			$this->add_validation_error( $field_key );
			return $this->get_fallback_value( $field_key );
		}

		return $parsed;
	}

	/**
	 * Add a direct "Settings" link in the Plugins list table.
	 *
	 * @since 1.0.0
	 * @param array<int, string> $links Existing plugin action links.
	 * @return array<int, string>
	 */
	public function add_plugin_action_links( $links ) {
		$settings_url = admin_url( 'admin.php?page=wc-settings&tab=products&section=' . self::SECTION_ID );

		$settings_link = sprintf(
			'<a href="%1$s">%2$s</a>',
			esc_url( $settings_url ),
			esc_html__( 'Settings', 'custom-quantity-selector-for-woocommerce' )
		);

		array_unshift( $links, $settings_link );

		return $links;
	}

	/**
	 * Extract the internal array key from a WooCommerce setting ID.
	 *
	 * @since 1.0.0
	 * @param string $option_id WooCommerce field ID.
	 * @return string|null
	 */
	private function extract_field_key( $option_id ) {
		if ( CQSFW_Plugin::OPTION_NAME . '[step]' === $option_id ) {
			return 'step';
		}

		if ( CQSFW_Plugin::OPTION_NAME . '[min_quantity]' === $option_id ) {
			return 'min_quantity';
		}

		return null;
	}

	/**
	 * Register a WooCommerce admin settings error for an invalid field.
	 *
	 * @since 1.0.0
	 * @param string $field_key Setting key ('step' or 'min_quantity').
	 * @return void
	 */
	private function add_validation_error( $field_key ) {
		if ( ! class_exists( 'WC_Admin_Settings' ) ) {
			return;
		}

		if ( 'step' === $field_key ) {
			$message = __( 'Quantity step must be a number greater than 0 (up to 2 decimal places). Previous value was kept.', 'custom-quantity-selector-for-woocommerce' );
		} else {
			$message = __( 'Minimum quantity must be a number greater than 0 (up to 2 decimal places). Previous value was kept.', 'custom-quantity-selector-for-woocommerce' );
		}

		WC_Admin_Settings::add_error( $message );
	}

	/**
	 * Register a WooCommerce product meta box error for an invalid product minimum quantity.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function add_product_validation_error() {
		if ( ! class_exists( 'WC_Admin_Meta_Boxes' ) ) {
			return;
		}

		WC_Admin_Meta_Boxes::add_error(
			__( 'Product minimum quantity must be a number greater than 0 (up to 2 decimal places). Previous value was kept.', 'custom-quantity-selector-for-woocommerce' )
		);
	}

	/**
	 * Return the previously stored valid value (or default) when validation fails.
	 *
	 * @since 1.0.0
	 * @param string $field_key Setting key ('step' or 'min_quantity').
	 * @return float
	 */
	private function get_fallback_value( $field_key ) {
		$current_settings = CQSFW_Plugin::get_settings();
		return $current_settings[ $field_key ];
	}
}