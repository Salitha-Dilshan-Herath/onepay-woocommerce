<?php
/*
Plugin Name: onepay Payment Gateway For WooCommerce
Plugin URI: https://github.com/onepay-srilanka/onepay-woocommerce
Description: onepay Payment Gateway allows you to accept payment on your Woocommerce store via Visa, MasterCard, AMEX, & Lanka QR services.
Version: 1.0.0
Author: onepay
Author URI: https://www.onepay.lk
License: GPLv3 or later
WC tested up to: 5.8
*/

add_action('plugins_loaded', 'woocommerce_gateway_onepay_init', 0);
define('onepay_IMG', WP_PLUGIN_URL . "/" . plugin_basename(dirname(__FILE__)) . '/assets/img/');

function woocommerce_gateway_onepay_init() {
	if ( !class_exists( 'WC_Payment_Gateway' ) ) return;

	/**
 	 * Gateway class
 	 */
	class WC_Gateway_onepay extends WC_Payment_Gateway {

	     /**
         * Make __construct()
         **/	
		public function __construct(){
			
			$this->id 					= 'onepay'; // ID for WC to associate the gateway values
            $this -> icon = WP_PLUGIN_URL . "/" . plugin_basename(dirname(__FILE__ )) . '/assets/img/logo_onepay.png';
			$this->method_title 		= 'onepay'; // Gateway Title as seen in Admin Dashboad
			$this->method_description	= 'The Digital Payment Service Provider of Sri Lanka'; // Gateway Description as seen in Admin Dashboad
			$this->has_fields 			= false; // Inform WC if any fileds have to be displayed to the visitor in Frontend 
			
			$this->init_form_fields();	// defines your settings to WC
			$this->init_settings();		// loads the Gateway settings into variables for WC
						
			$this->liveurl 			= 'https://merchant-api-live-v2.onepay.lk/api/ipg/gateway/request-transaction/';



			$this->title 			= $this->settings['title']; // Title as displayed on Frontend
			$this->description 		= $this->settings['description']; // Description as displayed on Frontend

			$this->salt_string 		= $this->settings['salt_string'];
			$this->app_id 		= $this->settings['app_id'];
            $this->auth_token 		    = $this->settings['auth_token'];
			$this->redirect_page	= $this->settings['redirect_page']; // Define the Redirect Page.

			
            $this->msg['message']	= '';
            $this->msg['class'] 	= '';
			
			add_action('init', array(&$this, 'check_onepay_response'));
            add_action('woocommerce_api_' . strtolower(get_class($this)), array($this, 'check_onepay_response')); //update for woocommerce >2.0

            if ( version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>=' ) ) {
                    add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( &$this, 'process_admin_options' ) ); //update for woocommerce >2.0
                 } else {
                    add_action( 'woocommerce_update_options_payment_gateways', array( &$this, 'process_admin_options' ) ); // WC-1.6.6
                }
            add_action('woocommerce_receipt_onepay', array(&$this, 'receipt_page'));	
		} //END-__construct
		
        /**
         * Initiate Form Fields in the Admin Backend
         **/
		function init_form_fields(){

			$this->form_fields = array(
				// Activate the Gateway
				'enabled' => array(
					'title' 			=> __('Enable/Disable', 'onepayipg'),
					'type' 			=> 'checkbox',
					'label' 			=> __('Enable onepay', 'onepayipg'),
					'default' 		=> 'yes',
					'description' 	=> 'Show in the Payment List as a payment option'
				),
				// Title as displayed on Frontend
      			'title' => array(
					'title' 			=> __('Title', 'onepayipg'),
					'type'			=> 'text',
					'default' 		=> __('onepay', 'onepayipg'),
					'description' 	=> __('This controls the title which the user sees during checkout.', 'onepayipg'),
					'desc_tip' 		=> true
				),
				// Description as displayed on Frontend
      			'description' => array(
					'title' 			=> __('Description:', 'onepayipg'),
					'type' 			=> 'textarea',
					'default' 		=> __(' Pay by Visa, MasterCard, AMEX, or Lanka QR via onepay.', 'onepayipg'),
					'description' 	=> __('This controls the description which the user sees during checkout.', 'onepayipg'),
					'desc_tip' 		=> true
				),
				// LIVE App ID
				'app_id' => array(
					'title' 		=> __('App ID', 'onepayipg'),
					'type' 			=> 'text',
					'description' 	=> __('Your onepay App ID'),
					'desc_tip' 		=> true
				),
				// LIVE App ID
				'auth_token' => array(
					'title' 		=> __('App Token', 'onepayipg'),
					'type' 			=> 'text',
					'description' 	=> __('Your onepay App token'),
					'desc_tip' 		=> true
				),
				'salt_string' => array(
					'title' 		=> __('Hash Salt', 'onepayipg'),
					'type' 			=> 'text',
					'description' 	=> __('Your onepay Hash Salt String'),
					'desc_tip' 		=> true
				),
  				// Page for Redirecting after Transaction
      			'redirect_page' => array(
					'title' 			=> __('Return Page'),
					'type' 			=> 'select',
					'options' 		=> $this->onepay_get_pages('Select Page'),
					'description' 	=> __('Page to redirect the customer after payment', 'onepayipg'),
					'desc_tip' 		=> true
                )
			);

		} //END-init_form_fields
		
        /**
         * Admin Panel Options
         * - Show info on Admin Backend
         **/
		public function admin_options(){
			echo '<h3>'.esc_html__('onepay', 'onepayipg').'</h3>';
			echo '<p>'.esc_html__('WooCommerce Payment Plugin of onepay Payment Gateway, The Digital Payment Service Provider of Sri Lanka').'</p>';
			echo '<div style="background-color: #ffd5ba;color: #a04701;padding: 5px 20px">';
			echo '<h4><span class="dashicons dashicons-warning"></span>Important!!</h4>';
			echo '<p>If you want to enable sandbox create a development app in onepay merchant portal.</p>';
			echo '</div>';
			echo '<table class="form-table">';
			// Generate the HTML For the settings form.
			$this->generate_settings_html();
			echo '</table>';
		} //END-admin_options

        /**
         *  There are no payment fields, but we want to show the description if set.
         **/
		function payment_fields(){
			if( $this->description ) {
				echo wpautop( wptexturize( $this->description ) );
			}
		} //END-payment_fields
		
        /**
         * Receipt Page
         **/
		function receipt_page($order){
			echo '<p><strong>' . esc_html__('Thank you for your order.', 'onepayipg').'</strong><br/>' . esc_html__('The payment page will open soon.', 'onepay').'</p>';
			echo $this->generate_onepay_form($order);
		} //END-receipt_page
    
        /**
         * Generate button link
         **/
		function generate_onepay_form($order_id){
			global $woocommerce;
			$order = new WC_Order( $order_id );

			// Redirect URL
			if ( $this->redirect_page == '' || $this->redirect_page == 0 ) {
				$redirect_url = get_site_url() . "/";
			} else {
				$redirect_url = get_permalink( $this->redirect_page );
			}

			$redirect_url .= 'wc-api/WC_Gateway_onepay';

			// Redirect URL : For WooCoomerce 2.0
			if ( version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>=' ) ) {
				$notify_url = add_query_arg( 'wc-api', get_class( $this ), $redirect_url );
			}

            $productinfo = "Order $order_id";

			$txnid = $order_id.'_'.date("ymds");
			

			$onepay_args = array(
				'transaction_redirect_url' => esc_url_raw($redirect_url),
                'customer_first_name' => sanitize_text_field($order -> get_billing_first_name()),
                'customer_last_name' => sanitize_text_field($order -> get_billing_last_name()),
                'customer_email' => sanitize_email($order -> get_billing_email()),
                'customer_phone_number' => sanitize_text_field($order -> get_billing_phone()),
                'reference' => sanitize_text_field($order_id),
                'amount' => floatval($order -> get_total()),
				'app_id' => sanitize_text_field($this->app_id),
				'is_sdk' => 1,
				'sdk_type' => "woocommerce",
				'authorization' => sanitize_text_field($this->auth_token)
			);

			$hash_args = array(
				'transaction_redirect_url' => esc_url_raw($redirect_url),
                'customer_first_name' => sanitize_text_field($order -> get_billing_first_name()),
                'customer_last_name' => sanitize_text_field($order -> get_billing_last_name()),
                'customer_email' => sanitize_email($order -> get_billing_email()),
                'customer_phone_number' => sanitize_text_field($order -> get_billing_phone()),
                'reference' => sanitize_text_field(strval($order_id)),
                'amount' => strval(floatval($order -> get_total())),
				'app_id' => sanitize_text_field($this->app_id),
				'is_sdk' => "1",
				'sdk_type' => "woocommerce",
				'authorization' => sanitize_text_field($this->auth_token)
			);
			$result_body = json_encode($hash_args,JSON_UNESCAPED_SLASHES);

			$data=json_encode($hash_args,JSON_UNESCAPED_SLASHES);
			$hash_salt=sanitize_text_field($this->salt_string);
			$data .= $hash_salt;
			$hash_result = hash('sha256',$data);

            
            
			$onepay_args_array = array();

			foreach($onepay_args as $key => $value){
				$onepay_args_array[] = "<input type='hidden' name='$key' value='$value'/>";
			}

			$phone=$order->billing_phone;

			
			// $is_correct=preg_match('/^[0-9]+$/',$phone );
			if(sanitize_text_field($this->auth_token)=="" || sanitize_text_field($this->app_id)=="" || sanitize_text_field($this->salt_string)==""){
				$is_correct=0;
			}else{
				$is_correct=1;
			}

			$this->liveurl .= "?hash=$hash_result";


			return '	<form action="'.$this->liveurl.'" method="post" id="onepay_payment_form">
  				' . implode('', $onepay_args_array) . '
				<input type="submit" class="button-alt" id="submit_onepay_payment_form" value="'.__('Pay via onepay', 'onepayipg').'" /> <a class="button cancel" href="'.$order->get_cancel_order_url().'">'.__('Cancel order &amp; restore cart', 'onepayipg').'</a>
					<script type="text/javascript">
					jQuery(function(){

						if('.$is_correct.')
						{

							jQuery("#submit_onepay_payment_form").click();

						}else{
							alert("Please add onepay payment configurations before proceeding");
						}
					

						
				
				});
					</script>
				</form>';	



		
		} 

        /**
         * Process the payment and return the result
         **/
        function process_payment($order_id){
			global $woocommerce;
            $order = new WC_Order($order_id);
			
			if ( version_compare( WOOCOMMERCE_VERSION, '2.1.0', '>=' ) ) { // For WC 2.1.0
			  	$checkout_payment_url = $order->get_checkout_payment_url( true );
			} else {
				$checkout_payment_url = get_permalink( get_option ( 'woocommerce_pay_page_id' ) );
			}

			return array(
				'result' => 'success', 
				'redirect' => add_query_arg(
					'order', 
					$order->id, 
					add_query_arg(
						'key', 
						$order->order_key, 
						$checkout_payment_url						
					)
				)
			);
		} //END-process_payment

        /**
         * Check for valid gateway server callback
         **/
        function check_onepay_response(){


		   global $woocommerce;

		   echo '<p><strong>' . esc_html__('Thank you for your order.', 'onepayipg').'</strong><br/>' . esc_html__('You will be redirected soon....', 'onepay').'</p>';
	

			if( isset($_REQUEST['merchant_transaction_id']) && isset($_REQUEST['hash']) && isset($_REQUEST['onepay_transaction_id']) ){
				$order_id = sanitize_text_field($_REQUEST['merchant_transaction_id']);
				if($order_id != ''){
					try{
						$order = new WC_Order( $order_id );
						$status = (int)sanitize_text_field($_REQUEST['status']);
						$hash_string = sanitize_text_field($_REQUEST['hash']);
					
						$request_args = array(
							'onepay_transaction_id' => sanitize_text_field($_REQUEST['onepay_transaction_id']),
							'merchant_transaction_id' => sanitize_text_field($_REQUEST['merchant_transaction_id']),
							'status' => $status
						);


						$json_string=json_encode($request_args);

                        
                        $verified = true;
                        if ($hash_string) {


				


							$json_hash_result = hash('sha256',$json_string);
					


                            if (($hash_string != $json_hash_result)) {
								$verified = false;
							
                            }
                        } 
						
                        $trans_authorised = false;
						
						if( $order->status !=='completed' ){
							if($verified){
							
								if($status==1){
									$trans_authorised = true;
									$this->msg['message'] = "Thank you for shopping with us. Your account has been charged and your transaction is successful.";
									$this->msg['class'] = 'woocommerce-message';
									if($order->status == 'processing'){
										$order->add_order_note('onepay transaction ID: '.sanitize_text_field($_REQUEST['onepay_transaction_id']));
									}else{
										$order->payment_complete();
										$order->add_order_note('onepay payment successful.<br/>onepay transaction ID: '.sanitize_text_field($_REQUEST['onepay_transaction_id']));
										$woocommerce->cart->empty_cart();
									}
								}else if($status==0){
									$trans_authorised = true;
									$this->msg['class'] = 'woocommerce-error';
									$this->msg['message'] = "Thank you for shopping with us. However, the transaction has been failed. We will keep you informed";
									$order->add_order_note('Transaction ERROR.'.sanitize_text_field($_REQUEST['onepay_transaction_id']));
									$order->update_status('on-hold');
									$woocommerce -> cart -> empty_cart();
								}

							}else{
								$this->msg['class'] = 'error';
								$this->msg['message'] = "Security Error. Illegal access detected.";
								$order->add_order_note('Checksum ERROR: '.json_encode($json_string));
							}

							if($trans_authorised==false){
								$order->update_status('failed');
							}

						}

					}catch(Exception $e){
                        $msg = "Error";
					}
				}


	
			}
			if ( $this->redirect_page == '' || $this->redirect_page == 0 ) {
				$redirect_url = get_permalink( get_option('woocommerce_myaccount_page_id') );
			} else {
				$redirect_url = get_permalink( $this->redirect_page );
			}

			
			wp_redirect( $redirect_url );
			exit;

		} //END-check_onepay_response
		

        /**
         * Get Page list from WordPress
         **/
		function onepay_get_pages($title = false, $indent = true) {
			$wp_pages = get_pages('sort_column=menu_order');
			$page_list = array();
			if ($title) $page_list[] = $title;
			foreach ($wp_pages as $page) {
				$prefix = '';
				// show indented child pages?
				if ($indent) {
                	$has_parent = $page->post_parent;
                	while($has_parent) {
                    	$prefix .=  ' - ';
                    	$next_page = get_post($has_parent);
                    	$has_parent = $next_page->post_parent;
                	}
            	}
            	// add to page list array array
            	$page_list[$page->ID] = $prefix . $page->post_title;
        	}
        	return $page_list;
		} 

	} //END-class
	
	/**
 	* Add the Gateway to WooCommerce
 	**/
	function woocommerce_add_gateway_onepay_gateway($methods) {
		$methods[] = 'WC_Gateway_onepay';
		return $methods;
	}//END-wc_add_gateway
	
	add_filter('woocommerce_payment_gateways', 'woocommerce_add_gateway_onepay_gateway' );
	
} //END-init

/**
* 'Settings' link on plugin page
**/
add_filter( 'plugin_action_links', 'onepay_add_action_plugin', 10, 5 );
function onepay_add_action_plugin( $actions, $plugin_file ) {
	static $plugin;

	if (!isset($plugin))
		$plugin = plugin_basename(__FILE__);
	if ($plugin == $plugin_file) {

			$settings = array('settings' => '<a href="admin.php?page=wc-settings&tab=checkout&section=wc_gateway_onepay">' . __('Settings') . '</a>');
		
    			$actions = array_merge($settings, $actions);
			
		}
		
		return $actions;
}//END-settings_add_action_link