<?php
/*
Plugin Name: Woo To App Store Plugin
Plugin URI: https://www.wootoapp.com
Description: Enables various functionality required by Woo To App
Version: 0.0.1
Author: WooToApp
Author URI: https://www.wootoapp.com

Copyright: © 2017 WooToApp
*/

add_action( 'plugins_loaded', 'wta_init', 0 );


if(!function_exists("safe_json_encode")){
	function safe_json_encode( $mixed, $missing = "TRANSLIT" ) {
		$out = json_encode( $mixed );
		if ( $err = json_last_error() ) {
			iconv_r( "UTF-8", "UTF-8//$missing", $mixed );
			$out = json_encode( $mixed );
		}

		return $out;
	}
}

if(! function_exists("iconv_r")){

	function iconv_r( $charset_i, $charset_o, &$mixed ) {
		if ( is_string( $mixed ) ) {
			$mixed = iconv( $charset_i, $charset_o, $mixed );
		} else {
			if ( is_object( $mixed ) ) {
				$mixed = (array) $mixed;
			}
			if ( is_array( $mixed ) ) {
				foreach ( $mixed as $key => &$value ) {
					iconv_r( $charset_i, $charset_o, $value );
				}
			}
		}
	}
}

function wta_init() {
	class WooToApp {
		private static $_instance = null;

		protected $user = null;
		protected $coupon = null;

		public static function instance() {
			if ( is_null( self::$_instance ) ) {
				self::$_instance = new self();
			}

			return self::$_instance;
		}

		public function __construct() {


			/* endpoints */
			add_action( 'wp_ajax_wootoapp_execute', array( $this, 'wootoapp_execute_callback' ) );
			add_action( 'wp_ajax_nopriv_wootoapp_execute', array( $this, 'wootoapp_execute_callback' ) );


			add_filter( 'woocommerce_settings_tabs_array', array( $this, 'add_settings_tab' ), 50 );
			add_action( 'woocommerce_settings_tabs_settings_wootoapp', array( $this, 'settings_tab' ) );
			add_action( 'woocommerce_update_options_settings_wootoapp', array( $this, 'update_settings' ) );


			header( 'Access-Control-Allow-Credentials:true' );
			header( 'Access-Control-Allow-Headers:Authorization, Content-Type' );
			header( 'Access-Control-Allow-Methods:OPTIONS, GET, POST, PUT, PATCH, DELETE' );
			header( 'Access-Control-Allow-Origin: *' );
			header( 'Allow: GET' );

			if ( $_SERVER['REQUEST_METHOD'] == 'OPTIONS' ) {
				header( 'Access-Control-Allow-Origin: *' );
				header( 'Access-Control-Allow-Headers: X-Requested-With, Authorization, Content-Type' );
				header( "HTTP/1.1 200 OK" );
				die();
			}
			/* END endpoints */

		}


		public function add_settings_tab( $settings_tabs ) {
			$settings_tabs['settings_wootoapp'] = __( 'WooToApp', 'woocommerce-settings-tab-wootoapp' );

			return $settings_tabs;
		}

		public function settings_tab() {
		//	woocommerce_admin_fields( self::get_settings() );


			include_once("settings-page.php");
			?>


			<?php

		}

		public static function update_settings() {
			woocommerce_update_options( self::get_settings() );
		}

		public static function get_settings() {

			$settings = array(
				'wc_wootoapp_section_title'    => array(
					'name' => __( 'Settings', 'woocommerce-settings-tab-wootoapp' ),
					'type' => 'title',
					'desc' => '',
					'id'   => 'WC_settings_wootoapp_section_title'
				),
				'wc_wootoapp_site_id'          => array(
					'name'     => __( 'Enter your Site ID', 'woocommerce-settings-tab-wootoapp' ),
					'type'     => 'text',
					'desc'     => __( 'This will be on your intro email.',
						'woocommerce-settings-tab-wootoapp' ),
					'desc_tip' => true,
					'id'       => 'WC_settings_wootoapp_site_id'
				),
				'wc_wootoapp_secret_key'       => array(
					'name'     => __( 'Enter your Secret Key', 'woocommerce-settings-tab-wootoapp' ),
					'type'     => 'text',
					'css'      => 'min-width:350px;',
					'desc'     => __( 'This will be on your intro email.',
						'woocommerce-settings-tab-wootoapp' ),
					'desc_tip' => true,
					'id'       => 'WC_settings_wootoapp_secret_key'
				),
				'wc_wootoapp_logging_enabled'  => array(
					'name' => __( 'Enable Logging?', 'woocommerce-settings-tab-wootoapp' ),
					'type' => 'checkbox',
					'id'   => 'WC_settings_wootoapp_logging_enabled'
				),
				'wc_wootoapp_livemode_enabled' => array(
					'name' => __( 'Enable Live Mode? (YES if unsure)', 'woocommerce-settings-tab-wootoapp' ),
					'type' => 'checkbox',
					'id'   => 'WC_settings_wootoapp_livemode_enabled'
				),
				'wc_wootoapp_section_end'      => array(
					'type' => 'sectionend',
					'id'   => 'WC_settings_wootoapp_section_end'
				)
			);

			return apply_filters( 'WC_settings_wootoapp_settings', $settings );
		}

		public static function log( $message ) {
			if ( empty( self::$log ) ) {
				self::$log = new WC_Logger();
			}

			if ( get_option( "WC_settings_wootoapp_livemode_enabled" ) === "yes" ) {
				self::$log->add( 'WooToApp', $message );
			}
			//
		}

		public function wootoapp_execute_callback(){
			$user = $this->get_authenticated_user();

			if($user){
				$method = $_GET['method'];

				echo json_encode($this->execute_callback_authenticated($method, $user));
			}
			else{
				echo json_encode( ['error'=>'Could not authenticate']);
			}
			die();
		}

		public function execute_callback_authenticated($method, $user){

			global $wpdb;

			$request = json_decode( file_get_contents( 'php://input' ), true );;
			switch($method){
				case "get_shipping_quotation":
					$line_items = $request['line_items'];
					$user_id = $request['user_id'];



					wp_set_current_user( $user_id );
					$shipping_methods = $this->_get_shipping_methods( $line_items);

					return [ 'shipping_methods' => $shipping_methods, 'user_id'=>$user_id ];
					break;
			}
		}

		public function get_authenticated_user(){
			global $wpdb;

			$consumer_key    = $_SERVER['PHP_AUTH_USER'];
			$consumer_secret = $_SERVER['PHP_AUTH_PW'];

			$user = $this->get_user_data_by_consumer_key( $consumer_key );

			if ( ! hash_equals( $user->consumer_secret, $consumer_secret ) ) {


				return false;
			}

			return $user;

		}

		public function _add_items_to_cart($line_items, $c){
			foreach ( $line_items as $item ) {
				$c->add_to_cart( $item['product_id'], (int) $item['quantity'], 0, [], [] );
			}
		}
		/**
		 * @param array $quotation
		 *
		 * @return array
		 */
		public function _get_shipping_methods($line_items) {
			$c = WC()->cart;
			$c->empty_cart();
			$cust = new WC_Customer( wp_get_current_user()->ID );

			WC()->customer = $cust;

 			$this->_add_items_to_cart( $line_items, $c );

			WC()->cart->calculate_shipping();
			do_action( 'woocommerce_cart_totals_before_shipping' );

			$packages = WC()->shipping->get_packages();
			do_action( 'woocommerce_cart_totals_after_shipping' );

			$package = $packages[0];
			$rates   = $package['rates'];

			$methods_out = [];


			if ( count( $rates ) > 0 ) {
				foreach ( $rates as $shipping_option ) {
					$methods_out[] = array(
						'label'      => $shipping_option->label,
						'amount'     => number_format( floatval( $shipping_option->cost ), 2 ),
						'detail'     => '',
						'identifier' => $shipping_option->id
					);
				}
			}


			$c->calculate_shipping();

			return $methods_out;
		}


		/** ------------------------------------------------ **/

		private function get_user_data_by_consumer_key( $consumer_key ) {
			global $wpdb;

			$consumer_key = wc_api_hash( sanitize_text_field( $consumer_key ) );
			$user         = $wpdb->get_row( $wpdb->prepare( "
			SELECT key_id, user_id, permissions, consumer_key, consumer_secret, nonces
			FROM {$wpdb->prefix}woocommerce_api_keys
			WHERE consumer_key = %s
		", $consumer_key ) );

			return $user;
		}


	}

	$WooToApp = new WooToApp();
}