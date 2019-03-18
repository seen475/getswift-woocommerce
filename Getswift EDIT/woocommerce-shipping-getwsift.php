<?php
/**
 * Plugin Name: WooCommerce getSwift Shipping
 * Plugin URI: https://example.com
 * Description: Obtain shipping rates dynamically via the getSwift API for your orders.
 * Version: 1.0.0
 * Author: Automattic
 * Author URI: https://example
 *
 * Copyright: 2009-2011 Automattic.
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 *
 */

/**
 * Required functions
 */
if ( ! function_exists( 'woothemes_queue_update' ) )
	require_once( 'woo-includes/woo-functions.php' );


/**
 * Plugin activation check
 */
function wc_getSwift_activation_check(){

}

register_activation_hook( __FILE__, 'wc_getSwift_activation_check');

class WC_Shipping_getSwift_Init {
	/**
	 * Plugin's version.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public $version = '2.0.1';

	/** @var object Class Instance */
	private static $instance;

	/**
	 * Get the class instance
	 */
	public static function get_instance() {
		return null === self::$instance ? ( self::$instance = new self ) : self::$instance;
	}

	/**
	 * Initialize the plugin's public actions
	 */
	public function __construct() {
		if ( class_exists( 'WC_Shipping_Method' ) ) {
			add_action( 'admin_init', array( $this, 'maybe_install' ), 5 );
			add_action( 'init', array( $this, 'load_textdomain' ) );
			add_action( 'woocommerce_shipping_init', array( $this, 'includes' ) );
			add_filter( 'woocommerce_shipping_methods', array( $this, 'add_method' ) );
			add_action( 'admin_notices', array( $this, 'environment_check' ) );
			add_action( 'admin_notices', array( $this, 'upgrade_notice' ) );
            add_action( 'woocommerce_thankyou', array($this, 'getswift_woocommerce_thankyou'), 99 );
            add_action( 'rest_api_init', array($this, 'register_getswift_webhooks'));

			$getwsift_settings = get_option( 'woocommerce_getswift_settings', array() );

		} else {
			add_action( 'admin_notices', array( $this, 'wc_deactivated' ) );
		}
	}

	/**
	 * environment_check function.
	 */
	public function environment_check() {
		if ( version_compare( WC_VERSION, '2.6.0', '<' ) ) {
			return;
		}

		if ( ! in_array( get_woocommerce_currency(), array( 'USD', 'CAD' ) ) || ! in_array( WC()->countries->get_base_country(), array( 'US', 'CA' ) ) ) {
			echo '<div class="error">
				<p>' . __( 'GetSwift requires that the WooCommerce currency is set to US Dollars and that the base country/region is set to United States.', 'woocommerce-shipping-getswift' ) . '</p>
			</div>';
		}
	}

	/**
	 * woocommerce_init_shipping_table_rate function.
	 *
	 * @access public
	 * @since 1.0.0
	 * @version 1.0.0
	 * @return void
	 */
	public function includes() {
		if ( version_compare( WC_VERSION, '2.6.0', '<' ) ) {
			include_once(dirname(__FILE__) . '/includes/class-wc-shipping-getswift-deprecated.php');
		} else {
			include_once(dirname(__FILE__) . '/includes/class-wc-shipping-getswift.php');
		}
	}

	/**
	 * Add getSwift shipping method to WC
	 *
	 * @access public
	 * @param mixed $methods
	 * @return void
	 */
	public function add_method( $methods ) {
		if ( version_compare( WC_VERSION, '2.6.0', '<' ) ) {
			$methods[] = 'WC_Shipping_getSwift';
		} else {
			$methods['getswift'] = 'WC_Shipping_getSwift';
		}

		return $methods;
	}

	/**
	 * Localisation
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'woocommerce-shipping-getswift', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	}


	/**
	 * WooCommerce not installed notice
	 */
	public function wc_deactivated() {
		echo '<div class="error"><p>' . sprintf( __( 'WooCommerce GetSwift Shipping requires %s to be installed and active.', 'woocommerce-shipping-getswift' ), '<a href="https://woocommerce.com" target="_blank">WooCommerce</a>' ) . '</p></div>';
	}

	/**
	 * See if we need to install any upgrades
	 * and call the install
	 *
	 * @access public
	 * @since 1.0.0
	 * @version 1.0.0
	 * @return bool
	 */
	public function maybe_install() {
		// only need to do this for versions less than 1.0.0 to migrate
		// settings to shipping zone instance
		if ( ! defined( 'DOING_AJAX' )
		     && ! defined( 'IFRAME_REQUEST' )
		     && version_compare( WC_VERSION, '2.6.0', '>=' )
		     && version_compare( get_option( 'wc_getswift_version' ), '2.0.1', '<' ) ) {

			$this->install();

		}

		return true;
	}

	/**
	 * Update/migration script
	 *
	 * @since 1.0.0
	 * @version 1.0.0
	 * @access public
	 * @return bool
	 */
	public function install() {
		// get all saved settings and cache it
		$getswift_settings = get_option( 'woocommerce_getswift_settings', false );

		// settings exists
		if ( $getswift_settings ) {
			global $wpdb;

			// unset un-needed settings
			unset( $getswift_settings['enabled'] );
			unset( $getswift_settings['availability'] );
			unset( $getswift_settings['countries'] );

			// add it to the "rest of the world" zone when no fedex.
			if ( ! $this->is_zone_has_getswift( 0 ) ) {
				$wpdb->query( $wpdb->prepare( "INSERT INTO {$wpdb->prefix}woocommerce_shipping_zone_methods ( zone_id, method_id, method_order, is_enabled ) VALUES ( %d, %s, %d, %d )", 0, 'getswift', 1, 1 ) );
				// add settings to the newly created instance to options table
				$instance = $wpdb->insert_id;
				add_option( 'woocommerce_getswift_' . $instance . '_settings', $getswift_settings );
			}

			update_option( 'woocommerce_getswift_show_upgrade_notice', 'yes' );
		}

		update_option( 'wc_getswift_version', $this->version );
	}

	/**
	 * Show the user a notice for plugin updates
	 *
	 * @since 1.0.0
	 */
	public function upgrade_notice() {
		$show_notice = get_option( 'woocommerce_getswift_show_upgrade_notice' );

		if ( 'yes' !== $show_notice ) {
			return;
		}

		$query_args = array( 'page' => 'wc-settings', 'tab' => 'shipping' );
		$zones_admin_url = add_query_arg( $query_args, get_admin_url() . 'admin.php' );
		?>
		<div class="notice notice-success is-dismissible wc-fedex-notice">
			<p><?php echo sprintf( __( 'getSwift now supports shipping zones. The zone settings were added to a new getSwift method on the "Rest of the World" Zone. See the zones %shere%s ', 'woocommerce-shipping-getswift' ),'<a href="' .$zones_admin_url. '">','</a>' ); ?></p>
		</div>

		<?php
	}

	/**
	 * Turn of the dismisable upgrade notice.
	 * @since 1.0.0
	 */
	public function dismiss_upgrade_notice() {
		update_option( 'woocommerce_getswift_show_upgrade_notice', 'no' );
	}

	/**
	 * Helper method to check whether given zone_id has getswift method instance.
	 *
	 * @since 1.0.0
	 *
	 * @param int $zone_id Zone ID
	 *
	 * @return bool True if given zone_id has fedex method instance
	 */
	public function is_zone_has_getswift( $zone_id ) {
		global $wpdb;

		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(instance_id) FROM {$wpdb->prefix}woocommerce_shipping_zone_methods WHERE method_id = 'getswift' AND zone_id = %d", $zone_id ) ) > 0;
	}

	public function convertTime($dec)
	{
		// start by converting to seconds
		$seconds = ($dec * 3600);
		// we're given hours, so let's get those the easy way
		$hours = floor($dec);
		// since we've "calculated" hours, let's remove them from the seconds variable
		$seconds -= $hours * 3600;
		// calculate minutes left
		$minutes = floor($seconds / 60);
		// remove those from seconds as well
		$seconds -= $minutes * 60;
		// return the time formatted HH:MM:SS
		return $this->lz($hours).$this->lz($minutes);
	}
	
	// lz = leading zero
	public function lz($num)
	{
		return (strlen($num) < 2) ? "0{$num}" : $num;
	}


	public function getswift_woocommerce_thankyou($order_id){

		
		
		$dropoffearly = get_post_meta( $order_id, 'swift_dropoffearly', true );
		$dropofflatest = get_post_meta( $order_id, 'swift_dropofflatest' , true);
		$offset = get_option('gmt_offset');
		
		
		$tzoffset = "";
		if ($offset > 0)
		{
			$tzoffset = $tzoffset."+";
		}	
		else
		{
			$tzoffset = $tzoffset."-";
		}	

		$tzoffset =$tzoffset. $this->convertTime(abs($offset));
		
		
        $order = new WC_Order( $order_id );

        $order_items = $order->get_items();

        $items = array();
        foreach ($order_items as $item){
            $product = $order->get_product_from_item( $item );
            $sku = $product->get_sku();

            $items[] = array(
              'quantity' => $item['qty'],
              'sku' => $sku,
              'description' => $item['name'],
              'price' => $item['line_subtotal'],

            );
        }

		$settings = (array)get_option('woocommerce_getswift_settings');
		
		$pickup_user = $settings['pickup_user'];
	    $api_key = $settings['api_key'];
	    $name = $settings['name'];
	    $phone = $settings['phone'];
	    $email = $settings['email'];
	    $address = $settings['address'];


		echo "<h2>GetSwift Detail</h2>";

		if ($pickup_user == "yes")
		{
			$fname = get_post_meta( $order_id, 'swift_pickupfname', true );
			$lname = get_post_meta( $order_id, 'swift_pickuplname', true );
			$name = $lname .' '.$fname;
			$phone = get_post_meta( $order_id, 'swift_pickupphone', true );
			$email = get_post_meta( $order_id, 'swift_pickupemail', true );
			$address = get_post_meta( $order_id, 'swift_pickupaddress', true );

			echo "<h4>Pickup Details</h4>";
			echo $name;
			echo "<br>";
			echo $address;
			echo "<br>";
			echo $phone;
			echo "<br>";
			echo $email;
			echo "<br>";
		}
		if ($dropoffearly)
		{	
			echo "<br>";
			echo 'Dropoff Early time:' .$dropoffearly;
			$early = date_create_from_format('m/d/Y H:i', $dropoffearly, new DateTimeZone($tzoffset));
			$dropoffearly = $early->format('c');
			
		}
		
		if ($dropofflatest)
		{
			echo "<br>";
			echo 'Dropoff Latest time:' .$dropofflatest;
			$early = date_create_from_format('m/d/Y H:i', $dropofflatest, new DateTimeZone($tzoffset));
			$dropofflatest = $early->format('c');
			
		}

        $url = "https://app.getswift.co/api/v2/deliveries";

        $ch = curl_init($url);

		$site_url = get_site_url();
		

		
	

        $request = array(
            'apiKey'    => $api_key,
            'booking'   => array(
				'deliveryInstructions' =>$order->customer_note,
              'pickupDetail'=>array(
                'name'      =>  $name,
                'phone'     =>  $phone,
                'email'     =>  $email,
                'address'   =>  $address
				),
              'dropoffDetail' =>array(
                "name"      => $order->billing_first_name.' '.$order->billing_last_name,
                "phone"     => $order->billing_phone,
                "email"     => $order->billing_email,
                "address"   => $order->billing_address_1.' '. $order->billing_address_2, $order->billing_city.','.$order->billing_state.','.$order->billing_postcode
                ),
                'items' => $items,
                'webhooks'  => array(
                        array(
                           "eventName"=>"job/finished",
                           "url"=> $site_url."/wp-json/getswift/jobfinished"
                )
             ),
            )
		);
		
		if ($dropoffearly && $dropofflatest)
		{
			$dt = new DateTime("now", new DateTimeZone($tzoffset));
			$request['booking']['pickupTime'] =$dt->format('c');
				
			$request['booking']['dropoffWindow'] = array(
					'earliestTime' => $dropoffearly,
					'latestTime' =>$dropofflatest
				);
		}



        $request_json = json_encode($request);
		
		//print_r($request_json);
		
        curl_setopt( $ch, CURLOPT_POSTFIELDS, $request_json );
        curl_setopt( $ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));

        //Return response instead of printing.
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
        # Send request.
        $result = curl_exec($ch);
       

		if (!curl_errno($ch)) {
			switch ($http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE)) {
				case 200:  # OK
				$result = json_decode($result);
				
				update_option($result->delivery->id,$order_id);
				echo "<br>";
				echo "<br>";
				echo 'Created Successfully GetSwift Job.';
				  break;
				default:
				echo "<br>";
				  echo 'Unexpected HTTP code: ', $http_code, "\n";
			  }
		  }
		  curl_close($ch);
      

    }
    public function register_getswift_webhooks(){
        register_rest_route( 'getswift','/jobfinished', array(
            'methods' => 'POST',
            'callback' => array($this, 'getswift_job_finished'),
        ) );
    }

   public function getswift_job_finished( WP_REST_Request $data ) {
        $body = json_decode($data->get_body());
        $job_id = $body->Data->Job->JobIdentifier;

        $order_id = get_option($job_id);

        global $woocommerce;
        $order = new WC_Order( $order_id );
        $order->update_status('completed');

        return $order_id;
   }
}

add_action( 'plugins_loaded' , array('WC_Shipping_getSwift_Init', 'get_instance' ), 0 );

function admin_load_js(){
	wp_register_style('kv_js_time_style' , plugin_dir_url( __FILE__ ). 'css/jquery-ui-timepicker-addon.css');
	wp_enqueue_style('kv_js_time_style');	
	wp_enqueue_style('jquery-style', 'http://ajax.googleapis.com/ajax/libs/jqueryui/1.10.4/themes/smoothness/jquery-ui.css');
	wp_enqueue_script('jquery-script', 'http://code.jquery.com/ui/1.10.4/jquery-ui.js');
	wp_enqueue_script('jquery-slideraccess' ,  plugin_dir_url( __FILE__ ). 'js/jquery-ui-sliderAccess.js',  array('jquery' ));	
	wp_enqueue_script('jquery-time-picker' ,  plugin_dir_url( __FILE__ ). 'js/jquery-ui-timepicker-addon.js',  array('jquery' ));	
	wp_enqueue_script('custom' ,  plugin_dir_url( __FILE__ ). 'js/custom.js',  array('jquery' ));	
}
add_action('wp_enqueue_scripts', 'admin_load_js');




//add_filter('woocommerce_checkout_fields', 'customise_checkout_field');


function customise_checkout_field2($fields)
{
	$settings = (array)get_option('woocommerce_getswift_settings');
	
	$pickup_user = $settings['pickup_user'];

	if ($pickup_user == "yes")
	{
	
		$fields['order']['swift_pickupfname'] = array(
			'type' => 'text',
			'label' => __('First Name'),
			'required' => false,
			'class' => array('my-field-class form-row-first') ,
			'clear'     => true
		);
		$fields['order']['swift_pickuplname'] = array(
			'type' => 'text',
			'label' => __('Last Name'),
			'required' => false,
			'class' => array('my-field-class form-row-last') ,
			'clear'     => true
		);
		$fields['order']['swift_pickupemail'] = array(
			'type' => 'email',
			'label' => __('Email address'),
			'required' => false,
			'class' => array('my-field-class form-row-wide') ,
			'clear'     => true
		);
		$fields['order']['swift_pickupphone'] = array(
			'type' => 'text',
			'label' => __('Phone'),
			'required' => true,
			'class' => array('my-field-class form-row-wide') ,
			'clear'     => true
		);
		$fields['order']['swift_pickupaddress'] = array(
			'type' => 'text',
			'label' => __('Address'),
			'required' => true,
			'class' => array('my-field-class form-row-wide') ,
			'clear'     => true
		);
	
	}



	$fields['order']['swift_dropoffearly'] = array(
        'type' => 'text',
        'label' => __('GetSwift Dropoff Earliest time'),
		'required' => false,
        'class' => array('my-field-class mydate form-row-wide') ,
        'clear'     => true
	);
	$fields['order']['swift_dropofflatest'] = array(
        'type' => 'text',
        'label' => __('GetSwift Dropoff Latest time'),
		'required' => false,
        'class' => array('my-field-class mydate form-row-wide') ,
        'clear'     => true
    );
    return $fields;
}

function customise_checkout_field1($checkout)
{
	$settings = (array)get_option('woocommerce_getswift_settings');
	
	$pickup_user = $settings['pickup_user'];

	echo '<div id="my_custom_checkout_field">';
	if ($pickup_user == "yes")
	{
		echo '<h3>' . __('Pickup Details') . '</h3>';
		woocommerce_form_field( 'swift_pickupfname', array(
			'type' => 'text',
			'label' => __('First Name'),
			'required' => false,
			'class' => array('my-field-class form-row-first') ,
			'clear'     => true
		), $checkout->get_value( 'swift_pickupfname' ));
		
		woocommerce_form_field( 'swift_pickuplname',array(
			'type' => 'text',
			'label' => __('Last Name'),
			'required' => false,
			'class' => array('my-field-class form-row-last') ,
			'clear'     => true
		), $checkout->get_value( 'swift_pickuplname' ));
	
		woocommerce_form_field( 'swift_pickupemail', array(
			'type' => 'email',
			'label' => __('Email address'),
			'required' => false,
			'class' => array('my-field-class form-row-wide') ,
			'clear'     => true
		), $checkout->get_value( 'swift_pickupemail' ));

		woocommerce_form_field( 'swift_pickupphone', array(
			'type' => 'text',
			'label' => __('Phone'),
			'required' => true,
			'class' => array('my-field-class form-row-wide') ,
			'clear'     => true
		), $checkout->get_value( 'swift_pickupphone' ));

		woocommerce_form_field( 'swift_pickupaddress', array(
			'type' => 'text',
			'label' => __('Address'),
			'required' => true,
			'class' => array('my-field-class form-row-wide') ,
			'clear'     => true
		), $checkout->get_value( 'swift_pickupaddress' ));

	}

	echo '<br>';
	
	woocommerce_form_field( 'swift_dropoffearly', array(
        'type' => 'text',
        'label' => __('GetSwift Dropoff Earliest time'),
		'required' => false,
        'class' => array('my-field-class mydate form-row-wide') ,
        'clear'     => true
	), $checkout->get_value( 'swift_dropoffearly' ));

	woocommerce_form_field( 'swift_dropofflatest', array(
        'type' => 'text',
        'label' => __('GetSwift Dropoff Latest time'),
		'required' => false,
        'class' => array('my-field-class mydate form-row-wide') ,
        'clear'     => true
    ), $checkout->get_value( 'swift_dropofflatest' ));


	echo '</div>';

  
}


add_action('woocommerce_after_order_notes', 'customise_checkout_field1');

function customise_checkout_field_label($checkout)
{

	echo '<div id="custom_checkout_field"><h3>' . __('Pickup Details') . '</h3></div>';

}
add_action('woocommerce_checkout_process', 'customise_checkout_field_process');

function customise_checkout_field_process()
{

   // if the field is set, if not then show an error message.
	if ($_POST['swift_dropoffearly'] && !$_POST['swift_dropofflatest'])
		wc_add_notice(__('Please enter both GetSwift dropoff earliest time and latest time.') , 'error');
	if (!$_POST['swift_dropoffearly'] && $_POST['swift_dropofflatest'])
		wc_add_notice(__('Please enter both GetSwift dropoff earliest time and latest time.') , 'error');
	
	if ($_POST['swift_dropoffearly'] && $_POST['swift_dropofflatest'])
	{
		$early = date_create_from_format('m/d/Y H:i', $_POST['swift_dropoffearly']);
		$latest = date_create_from_format('m/d/Y H:i', $_POST['swift_dropofflatest']);
	
		if ($early > $latest)
		{
			wc_add_notice(__('Invalid GetSwift dropoff earliest time and latest time. Check times') , 'error');
		}
		
	}
}

/**
 * Update the order meta with field value
 */
add_action( 'woocommerce_checkout_update_order_meta', 'swift_custom_checkout_field_update_order_meta' );

function swift_custom_checkout_field_update_order_meta( $order_id ) {
 
	if (! empty( $_POST['swift_pickupfname'] ) )	{
		update_post_meta( $order_id, 'swift_pickupfname', sanitize_text_field( $_POST['swift_pickupfname'] ) );
	}
	if (! empty( $_POST['swift_pickuplname'] ) )	{
		update_post_meta( $order_id, 'swift_pickuplname', sanitize_text_field( $_POST['swift_pickuplname'] ) );
	}
	if (! empty( $_POST['swift_pickupemail'] ) )	{
		update_post_meta( $order_id, 'swift_pickupemail', sanitize_text_field( $_POST['swift_pickupemail'] ) );
	}
	if (! empty( $_POST['swift_pickupphone'] ) )	{
		update_post_meta( $order_id, 'swift_pickupphone', sanitize_text_field( $_POST['swift_pickupphone'] ) );
	}
	if (! empty( $_POST['swift_pickupaddress']  ) )	{
		update_post_meta( $order_id, 'swift_pickupaddress', sanitize_text_field( $_POST['swift_pickupaddress'] ) );
	}
	if ( ! empty( $_POST['swift_dropoffearly'] ) &&  ! empty( $_POST['swift_dropofflatest'] )   ) {
		update_post_meta( $order_id, 'swift_dropoffearly', sanitize_text_field( $_POST['swift_dropoffearly'] ) );
		update_post_meta( $order_id, 'swift_dropofflatest', sanitize_text_field( $_POST['swift_dropofflatest'] ) );
	}
}



