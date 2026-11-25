<?php
/**
 * Uninstall handler for Custom Quantity Selector for WooCommerce.
 *
 * Deletes stored plugin settings when the plugin is uninstalled from WordPress.
 *
 * @package Custom_Quantity_Selector_For_WooCommerce
 * @since   1.0.0
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$cqsfw_option_name = 'cqsfw_settings';

if ( is_multisite() ) {
	$cqsfw_site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);

	foreach ( $cqsfw_site_ids as $cqsfw_site_id ) {
		delete_blog_option( (int) $cqsfw_site_id, $cqsfw_option_name );
	}
} else {
	delete_option( $cqsfw_option_name );
}