<?php
/**
 * Uninstall handler for Custom Quantity Selector for WooCommerce.
 *
 * Deletes stored plugin settings and per-product minimum quantity metadata
 * when the plugin is uninstalled from WordPress.
 *
 * @package Custom_Quantity_Selector_For_WooCommerce
 * @since   1.0.0
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$cqsfw_option_name = 'cqsfw_settings';
$cqsfw_meta_key    = '_cqsfw_min_quantity';

if ( is_multisite() ) {
	$cqsfw_site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);

	foreach ( $cqsfw_site_ids as $cqsfw_site_id ) {
		switch_to_blog( (int) $cqsfw_site_id );
		delete_option( $cqsfw_option_name );
		delete_metadata( 'post', 0, $cqsfw_meta_key, '', true );
		restore_current_blog();
	}
} else {
	delete_option( $cqsfw_option_name );
	delete_metadata( 'post', 0, $cqsfw_meta_key, '', true );
}