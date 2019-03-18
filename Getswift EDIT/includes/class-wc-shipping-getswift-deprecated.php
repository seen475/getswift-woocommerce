<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_Shipping_Fedex class deprecated.
 *
 * This class serves only WC < 2.6 and will be removed by WC 2.8
 * @extends WC_Shipping_Method
 */
class WC_Shipping_getSwift extends WC_Shipping_Method {
	private $default_boxes;
	private $found_rates;
	private $services;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->id                               = 'getSwift';
		$this->method_title                     = __( 'getSwift', 'woocommerce-shipping-getswift' );
		$this->method_description               = __( 'The <strong>getSwift</strong> extension obtains rates dynamically from the getSwift API during cart/checkout.', 'woocommerce-shipping-getswift' );
		$this->rateservice_version              = 16;
		$this->addressvalidationservice_version = 2;
		$this->default_boxes                    = '';
		$this->services                         = '';
		$this->init();
	}

	/**
	 * init function.
	 */
	private function init() {
		// Load the settings.
		$this->init_form_fields();
		$this->init_settings();

		// Define user set variables
		$this->title           = $this->get_option( 'title', $this->method_title );
		$this->availability    = $this->get_option( 'availability', 'all' );
		$this->enabled         = $this->get_option( 'enabled', $this->enabled );
		$this->countries       = $this->get_option( 'countries', array() );
		$this->origin          = apply_filters( 'woocommerce_getswift_origin_postal_code', str_replace( ' ', '', strtoupper( $this->get_option( 'origin' ) ) ) );
		$this->origin_country  = apply_filters( 'woocommerce_getswift_origin_country_code', WC()->countries->get_base_country() );
		$this->account_number  = $this->get_option( 'account_number' );
		$this->meter_number    = $this->get_option( 'meter_number' );
		$this->smartpost_hub   = $this->get_option( 'smartpost_hub' );
		$this->api_key         = $this->get_option( 'api_key' );
		$this->api_pass        = $this->get_option( 'api_pass' );
		$this->production      = ( $bool = $this->get_option( 'production' ) ) && $bool == 'yes' ? true : false;
		$this->debug           = ( $bool = $this->get_option( 'debug' ) ) && $bool == 'yes' ? true : false;
		$this->insure_contents = ( $bool = $this->get_option( 'insure_contents' ) ) && $bool == 'yes' ? true : false;
		$this->request_type    = $this->get_option( 'request_type', 'LIST' );
		$this->packing_method  = $this->get_option( 'packing_method', 'per_item' );
		$this->boxes           = $this->get_option( 'boxes', array( ));
		$this->custom_services = $this->get_option( 'services', array( ));
		$this->offer_rates     = $this->get_option( 'offer_rates', 'all' );
		$this->residential     = ( $bool = $this->get_option( 'residential' ) ) && $bool == 'yes' ? true : false;
		$this->freight_enabled = ( $bool = $this->get_option( 'freight_enabled' ) ) && $bool == 'yes' ? true : false;
		$this->fedex_one_rate  = ( $bool = $this->get_option( 'fedex_one_rate' ) ) && $bool == 'yes' ? true : false;
		$this->direct_distribution  = ( $bool = $this->get_option( 'direct_distribution' ) ) && $bool == 'yes' ? true : false;
		$this->fedex_one_rate_package_ids = array(
			'FEDEX_SMALL_BOX',
			'FEDEX_MEDIUM_BOX',
			'FEDEX_LARGE_BOX',
			'FEDEX_EXTRA_LARGE_BOX',
			'FEDEX_PAK',
			'FEDEX_ENVELOPE',
		);

		if ( $this->freight_enabled ) {
			$this->freight_class               = $this->get_option( 'freight_class' );
			$this->freight_number              = $this->get_option( 'freight_number', $this->account_number );
			$this->freight_billing_street      = $this->get_option( 'freight_billing_street' );
			$this->freight_billing_street_2    = $this->get_option( 'freight_billing_street_2' );
			$this->freight_billing_city        = $this->get_option( 'freight_billing_city' );
			$this->freight_billing_state       = $this->get_option( 'freight_billing_state' );
			$this->freight_billing_postcode    = $this->get_option( 'freight_billing_postcode' );
			$this->freight_billing_country     = $this->get_option( 'freight_billing_country' );
			$this->freight_shipper_street      = $this->get_option( 'freight_shipper_street' );
			$this->freight_shipper_street_2    = $this->get_option( 'freight_shipper_street_2' );
			$this->freight_shipper_city        = $this->get_option( 'freight_shipper_city' );
			$this->freight_shipper_state       = $this->get_option( 'freight_shipper_state' );
			$this->freight_shipper_postcode    = $this->get_option( 'freight_shipper_postcode' );
			$this->freight_shipper_country     = $this->get_option( 'freight_shipper_country' );
			$this->freight_shipper_residential = ( $bool = $this->get_option( 'freight_shipper_residential' ) ) && $bool == 'yes' ? true : false;
			$this->freight_class               = str_replace( array( 'CLASS_', '.' ), array( '', '_' ), $this->freight_class );

			// Make the city field show in the calculator (for freight)
			add_filter( 'woocommerce_shipping_calculator_enable_city', '__return_true' );

			// Add freight class option for shipping classes (for freight)
			if ( is_admin() && ! class_exists( 'WC_getSwift_Freight_Mapping' ) ) {
				//include( 'class-wc-fedex-freight-mapping.php' );
			}
		}

		// Insure contents requires matching currency to country
		switch ( WC()->countries->get_base_country() ) {
			case 'US' :
				if ( 'USD' !== get_woocommerce_currency() ) {
					$this->insure_contents = false;
				}
			break;
			case 'CA' :
				if ( 'CAD' !== get_woocommerce_currency() ) {
					$this->insure_contents = false;
				}
			break;
		}

		add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'load_admin_scripts' ) );
	}

	/**
	 * Load admin scripts
	 *
	 * @since 1.0.0
	 * @version 1.0.0
	 * @return void
	 */
	public function load_admin_scripts() {
		wp_enqueue_script( 'jquery-ui-sortable' );
	}

	/**
	 * is_available function.
	 *
	 * @param array $package
	 * @return bool
	 */
	public function is_available( $package ) {
		if ( 'no' === $this->enabled || empty( $package['destination']['country'] ) ) {
			return false;
		}

		if ( 'specific' === $this->availability ) {
			if ( is_array( $this->countries ) && ! in_array( $package['destination']['country'], $this->countries ) ) {
				return false;
			}
		} elseif ( 'excluding' === $this->availability ) {
			if ( is_array( $this->countries ) && ( in_array( $package['destination']['country'], $this->countries ) || ! $package['destination']['country'] ) ) {
				return false;
			}
		}
		
		return apply_filters( 'woocommerce_shipping_' . $this->id . '_is_available', true, $package );
	}

	/**
	 * Output a message
	 */
	public function debug( $message, $type = 'notice' ) {
		if ( $this->debug ) {
			wc_add_notice( $message, $type );
		}
	}

	/**
	 * environment_check function.
	 */
	private function environment_check() {
		if ( ! in_array( get_woocommerce_currency(), array( 'USD', 'CAD' ) ) || ! in_array( WC()->countries->get_base_country(), array( 'US', 'CA' ) ) ) {
			echo '<div class="error">
				<p>' . __( 'FedEx requires that the WooCommerce currency is set to US Dollars and that the base country/region is set to United States.', 'woocommerce-shipping-getswift' ) . '</p>
			</div>';
		} elseif ( ! $this->origin && $this->enabled == 'yes' ) {
			echo '<div class="error">
				<p>' . __( 'FedEx is enabled, but the origin postcode has not been set.', 'woocommerce-shipping-getswift' ) . '</p>
			</div>';
		}
	}

	/**
	 * admin_options function.
	 */
	public function admin_options() {
		// Check users environment supports this method
		$this->environment_check();

		// Show settings
		parent::admin_options();
	}

	/**
	 * init_form_fields function.
	 */
	public function init_form_fields() {
		$this->form_fields  = include( 'data/data-settings-deprecated.php' );
	}

	/**
	 * calculate_shipping function.
	 *
	 * @param mixed $package
	 */
	public function calculate_shipping( $package = array() ) {

		$this->add_found_rates();
	}

    /**
     * Add found rates to WooCommerce
     */
    public function add_found_rates() {
        $rate = array(
            'id' => $this->get_rate_id(),
            'label' => $this->title,
            'cost' => '0.00',
            'calc_tax' => 'per_item'
        );

        // Register the rate
        $this->add_rate( $rate );

    }

}
