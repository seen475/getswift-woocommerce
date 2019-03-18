<?php
/**
 * Backwards compat.
 *
 *
 * @since 1.0.0
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$active_plugins = get_option( 'active_plugins', array() );
foreach ( $active_plugins as $key => $active_plugin ) {
	if ( strstr( $active_plugin, '/shipping-getswift.php' ) ) {
		$active_plugins[ $key ] = str_replace( '/shipping-getswift.php', '/woocommerce-shipping-getswift.php', $active_plugin );
	}
}
update_option( 'active_plugins', $active_plugins );
