<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$shipping_class_link = version_compare( WC_VERSION, '2.6', '>=' ) ? admin_url( 'admin.php?page=wc-settings&tab=shipping&section=classes' ) : admin_url( 'edit-tags.php?taxonomy=product_shipping_class&post_type=product' );

/**
 * Array of settings
 */
return array(
	'title'            => array(
		'title'           => __( 'Method Title', 'woocommerce-shipping-getswift' ),
		'type'            => 'text',
		'description'     => __( 'This controls the title which the user sees during checkout.', 'woocommerce-shipping-getswift' ),
		'default'         => __( 'GetSwift', 'woocommerce-shipping-getswift' ),
		'desc_tip'        => true
	),
);
