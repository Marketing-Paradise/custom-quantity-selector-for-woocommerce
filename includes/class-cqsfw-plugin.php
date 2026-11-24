<?php
/**
 * Main plugin bootstrapper class.
 *
 * @package Custom_Quantity_Selector_For_WooCommerce
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CQSFW_Plugin.
 *
 * Handles plugin initialization, dependency loading, and shared settings access.
 *
 * @since 1.0.0
 */
final class CQSFW_Plugin {

	/**
	 * Option name stored in wp_options.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const OPTION_NAME = 'cqsfw_settings';

	/**
	 * Singleton instance.
	 *
	 * @since 1.0.0
	 * @var CQSFW_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Admin settings module instance.
	 *
	 * @since 1.0.0
	 * @var CQSFW_Settings|null
	 */
	private $settings = null;

	/**
	 * Frontend/WooCommerce quantity module instance.
	 *
	 * @since 1.0.0
	 * @var CQSFW_Quantity|null
	 */
	private $quantity = null;

	/**
	 * Retrieve or initialize the single plugin instance.
	 *
	 * @since 1.0.0
	 * @return CQSFW_Plugin|null
	 */
	public static function instance() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action( 'admin_notices', array( __CLASS__, 'render_woocommerce_missing_notice' ) );
			return null;
		}

		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Declare compatibility with WooCommerce features.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function declare_compatibility() {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
				'custom_order_tables',
				CQSFW_PLUGIN_FILE,
				true
			);

			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
				'cart_checkout_blocks',
				CQSFW_PLUGIN_FILE,
				false
			);
		}
	}

	/**
	 * Default plugin settings.
	 *
	 * @since 1.0.0
	 * @return array<string, float>
	 */
	public static function get_default_settings() {
		return array(
			'step'         => 1.0,
			'min_quantity' => 1.0,
		);
	}

	/**
	 * Retrieve validated plugin settings from the database.
	 *
	 * @since 1.0.0
	 * @return array<string, float>
	 */
	public static function get_settings() {
		$saved    = get_option( self::OPTION_NAME, array() );
		$defaults = self::get_default_settings();
		$settings = wp_parse_args( is_array( $saved ) ? $saved : array(), $defaults );

		$step         = round( (float) $settings['step'], 2 );
		$min_quantity = round( (float) $settings['min_quantity'], 2 );

		return array(
			'step'         => $step > 0 ? $step : $defaults['step'],
			'min_quantity' => $min_quantity > 0 ? $min_quantity : $defaults['min_quantity'],
		);
	}

	/**
	 * Render an admin notice if WooCommerce is missing.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function render_woocommerce_missing_notice() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			esc_html__( 'Custom Quantity Selector for WooCommerce requires WooCommerce to be installed and active.', 'custom-quantity-selector-for-woocommerce' )
		);
	}

	/**
	 * Private constructor to enforce Singleton pattern.
	 *
	 * @since 1.0.0
	 */
	private function __construct() {
		$this->load_dependencies();
		$this->init_modules();
	}

	/**
	 * Load required class files conditionally.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function load_dependencies() {
		if ( is_admin() ) {
			require_once CQSFW_PLUGIN_DIR . 'includes/class-cqsfw-settings.php';
		}

		require_once CQSFW_PLUGIN_DIR . 'includes/class-cqsfw-quantity.php';
	}

	/**
	 * Instantiate plugin modules.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function init_modules() {
		if ( is_admin() && class_exists( 'CQSFW_Settings' ) ) {
			$this->settings = new CQSFW_Settings();
		}

		if ( class_exists( 'CQSFW_Quantity' ) ) {
			$this->quantity = new CQSFW_Quantity();
		}
	}

	/**
	 * Get the settings module instance.
	 *
	 * @since 1.0.0
	 * @return CQSFW_Settings|null
	 */
	public function get_settings_module() {
		return $this->settings;
	}

	/**
	 * Get the quantity module instance.
	 *
	 * @since 1.0.0
	 * @return CQSFW_Quantity|null
	 */
	public function get_quantity_module() {
		return $this->quantity;
	}

	/**
	 * Prevent cloning of the Singleton instance.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function __clone() {}

	/**
	 * Prevent unserializing of the Singleton instance.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function __wakeup() {
		_doing_it_wrong(
			__FUNCTION__,
			esc_html__( 'Unserializing instances of this class is forbidden.', 'custom-quantity-selector-for-woocommerce' ),
			esc_html( CQSFW_VERSION )
		);
	}
}