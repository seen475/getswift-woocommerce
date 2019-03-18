<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_Shipping_Fedex class.
 *
 * @extends WC_Shipping_Method
 */
class WC_Shipping_getSwift extends WC_Shipping_Method {
	private $default_boxes;
	private $found_rates;
	private $services;

	/**
	 * Constructor
	 */
	public function __construct( $instance_id = 0 ) {
		$this->id                               = 'getswift';
		$this->instance_id                      = absint( $instance_id );
		$this->method_title                     = __( 'GetSwift', 'woocommerce-shipping-getswift' );
		$this->method_description               = __( 'The GetSwift extension obtains rates dynamically from the getSwift API during cart/checkout.', 'woocommerce-shipping-getswift' );
		$this->rateservice_version              = 16;
		$this->addressvalidationservice_version = 2;
		$this->default_boxes                    = '';
		$this->services                         = '';
		$this->supports                         = array(
			'shipping-zones',
			'instance-settings',
			'settings',
		);
		$this->init();
	}

	/**
	 * is_available function.
	 *
	 * @param array $package
	 * @return bool
	 */
	public function is_available( $package ) {
		if ( empty( $package['destination']['country'] ) ) {
			return false;
		}

		return apply_filters( 'woocommerce_shipping_' . $this->id . '_is_available', true, $package );
	}

	/**
	 * Initialize settings
	 *
	 * @version 1.0.0
	 * @since 1.0.0
	 * @return void
	 */
	private function set_settings() {
		// Define user set variables
		$this->title                      = $this->get_option( 'title', $this->method_title );
		$this->api_key                    = $this->get_option( 'api_key' );

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
	}

	/**
	 * init function.
	 */
	private function init() {
		// Load the settings.
		$this->init_form_fields();
		$this->set_settings();

		add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'load_admin_scripts' ) );
	}

	/**
	 * Process settings on save
	 *
	 * @access public
	 * @since 1.0.0
	 * @version 1.0.0
	 * @return void
	 */
	public function process_admin_options() {
		parent::process_admin_options();

		$this->set_settings();
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
	 * init_form_fields function.
	 */
	public function init_form_fields() {
		$this->instance_form_fields = include( dirname( __FILE__ ) . '/data/data-settings.php' );

		$this->form_fields = array(
		    'api'              => array(
				'title'           => __( 'API Settings', 'woocommerce-shipping-getswift' ),
				'type'            => 'title',
		    ),
		    'api_key'           => array(
				'title'           => __( 'API Key', 'woocommerce-shipping-getswift' ),
				'type'            => 'text',
				'description'     => '',
				'default'         => '',
				'custom_attributes' => array(
					'autocomplete' => 'off'
				)
			),
			'pickup_user'         => array(
                'title'           => __( 'Pickup Address in Checkout', 'woocommerce-shipping-getswift' ),
				'type'            => 'checkbox',
				'description'     => 'Customer can set Pickup Address in Checkout Page',
                'default'         => 'false',
                'custom_attributes' => array(
                    'autocomplete' => 'off'
                )
            ),
            'pickup'              => array(
                'title'           => __( 'Pickup Settings', 'woocommerce-shipping-getswift' ),
                'type'            => 'title',
            ),
            'name'           => array(
                'title'           => __( 'Name', 'woocommerce-shipping-getswift' ),
                'type'            => 'text',
                'description'     => '',
                'default'         => '',
                'custom_attributes' => array(
                    'autocomplete' => 'off'
                )
            ),
            'phone'           => array(
                'title'           => __( 'Phone', 'woocommerce-shipping-getswift' ),
                'type'            => 'text',
                'description'     => '',
                'default'         => '',
                'custom_attributes' => array(
                    'autocomplete' => 'off'
                )
            ),
            'email'           => array(
                'title'           => __( 'E-mail', 'woocommerce-shipping-getswift' ),
                'type'            => 'email',
                'description'     => '',
                'default'         => '',
                'custom_attributes' => array(
                    'autocomplete' => 'off'
                )
            ),
            'address'           => array(
                'title'           => __( 'Address', 'woocommerce-shipping-getswift' ),
                'type'            => 'text',
                'description'     => '',
                'default'         => '',
                'custom_attributes' => array(
                    'autocomplete' => 'off'
                )
            ),
        );
	}


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
