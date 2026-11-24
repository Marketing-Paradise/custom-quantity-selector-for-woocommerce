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
				'desc'              => __( 'Minimum quantity allowed per product on product pages and in the cart (e.g., 0.5, 1, 5).', 'custom-quantity-selector-for-woocommerce' ),
				'desc_tip'          => true,
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