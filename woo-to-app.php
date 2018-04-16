<?php

/*
Plugin Name:    Connector for WooToApp Mobile - WooCommerce Native Mobile App.
Plugin URI:     https://www.wootoapp.com
Description:    Enables various functionality required by WooToApp Mobile. WooToApp Mobile allows you to quickly and painlessly create a native mobile experience for your WooCommerce Store. Simply install and configure the plugin and we'll do the rest. WooToApp Mobile is free to use (branded) and offers paid subscriptions to release a standalone native mobile app.
Version:        1.0.7
Author:         WooToApp - Rhys Williams
Author          URI: https://www.wootoapp.com
License:        GPL2
License URI:    https://www.gnu.org/licenses/gpl-2.0.html


Connector for WooToApp Mobile is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 2 of the License, or
any later version.

Connector for WooToApp Mobile is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with Connector for WooToApp Mobile. If not, see https://www.gnu.org/licenses/gpl-2.0.html.
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

add_action( 'plugins_loaded', 'wta_init', 0 );

function wta_init() {
	class WooToApp {
		private static $_instance = null;

		public $notices = array();
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


			add_action( 'admin_notices', array( $this, 'admin_notices' ), 15 );

			if(isset( $_REQUEST['action']) && $_REQUEST['action'] === "wootoapp_execute"){
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
			}

			$settings = $this->LoadSettingsArray();

			if(! get_option( "wootoapp_blindkey" )){
				update_option("wootoapp_blindkey", wp_generate_password());
			}

			if ( (empty( $settings['secret_key'] ) || ! $settings['secret_key']) && ! ( isset( $_GET['page'], $_GET['tab'] ) && 'wc-settings' === $_GET['page'] && 'settings_wootoapp' === $_GET['tab'] ) ) {
				$setting_link = $this->get_setting_link();
				$this->add_admin_notice( 'prompt_connect', 'notice notice-warning',
					sprintf( __( 'WooToApp is almost ready. To get started, <a href="%s">complete the setup wizard</a>.',
						'wootoapp' ), $setting_link ) );
			}

			/* END endpoints */

		}

		public function admin_notices() {

			if($this->notices){
				foreach ( (array) $this->notices as $notice_key => $notice ) {
					echo "<div class='" . esc_attr( $notice['class'] ) . "'><p>";
					echo wp_kses( $notice['message'], array( 'a' => array( 'href' => array() ) ) );
					echo '</p></div>';
				}
			}

		}

		public function add_admin_notice( $slug, $class, $message ) {
			$this->notices[ $slug ] = array(
				'class'   => $class,
				'message' => $message,
			);
		}
		public function get_setting_link() {

			return admin_url( 'admin.php?page=wc-settings&tab=settings_wootoapp' );
		}
		public function wta_css_and_js() {
			$settings = $this->LoadSettingsArray();

			$args               = array(
				'taxonomy' => "product_cat",
			);
			$product_categories = get_terms( $args );

			$paypal_email = "";

			$paypal_opts = get_option( "woocommerce_paypal_settings" );
			if ( $paypal_opts ) {
				$paypal_email = $paypal_opts['email'];
			}

			$store_id   = str_replace( "\'", "", $settings['store_id'] );
			$secret_key = str_replace( "\'", "", $settings['secret_key'] );
			$cats_json  = json_encode( $product_categories );
			$currencies   = json_encode( get_woocommerce_currencies() );
			$siteurl = get_option("siteurl");
			$admin_email = get_option("admin_email");
			$store_name = get_option( "blogname" );
			$currency = get_woocommerce_currency();
			$pages      = json_encode( get_pages() );

			$blindkey = get_option( "wootoapp_blindkey" );
			$is_dev = strpos($_SERVER['SERVER_NAME'], ".local") !== false ? true : false;

			if($is_dev){
				wp_register_script( 'wta_js', "http://localhost:3000/static/js/bundle.js" );

			}
			else{
				wp_register_script( 'wta_js', "https://app.wootoapp.com/wta-wc-react.js" );

			}

			wp_add_inline_script( 'wta_js', <<<EOF
					    window.WooToApp = {
					        auth: {
					            id: '{$store_id}',
					            secret_key: '{$secret_key}'
					        },
					        blindkey: '{$blindkey}',
					        environment: "prod",
					        has_dev_params: false,
					        categories: $cats_json,
					        pages: $pages,
					        woo_currencies:$currencies,
					        currency: "$currency",
					        paypal_email: "$paypal_email",
					        store_url: "$siteurl",
					        store_name: "$store_name",
					        admin_email: "$admin_email"
					    }
					
					    window.WooToApp.log = window.WooToApp.environment == "prod" ? function(){} : console.log;
EOF
				, "before" );


			wp_enqueue_style( 'wta_css', "https://app.wootoapp.com/wta-wc-react.css" );
			wp_enqueue_script( 'wta_js' );

			wp_enqueue_media();
		}

		public function add_settings_tab( $settings_tabs ) {
			$settings_tabs['settings_wootoapp'] = __( 'WooToApp', 'woocommerce-settings-tab-wootoapp' );

			return $settings_tabs;
		}

		public function settings_tab() {
			$this->wta_css_and_js();
			include_once("settings-page.php");
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

				if(isset($_GET['blindkey'])){
					$blindkey = base64_decode( $_GET['blindkey'] );

					if ( $_GET['blindkey'] && $blindkey === get_option( "wootoapp_blindkey" ) ) {
						$method = $_GET['method'];
						echo json_encode( $this->execute_callback_authenticated( $method, $user ) );
						die();
					}
				}
				else{
					if(isset($_GET['unauthenticated']) && $_GET['unauthenticated'] === 'true'){
						// specifically apparently an unauthenticated request.
						$method = $_GET['method'];
						echo json_encode( $this->execute_callback_unauthenticated( $method) );
						die();

					}
				}

				echo json_encode( [ 'error' => 'Could not authenticate' ] );

			}
			die();
		}

		public function execute_callback_unauthenticated($method){
			global $wpdb;

			$request = json_decode( file_get_contents( 'php://input' ), true );;
			switch ( $method ) {
				case "payment_redirect":
					$redirect_to = $_GET['redirect_to'];
					$status = isset($_GET['status']) ? $_GET['status'] : "";
                    wp_redirect($redirect_to . "?status=" . $status, 302);
                    exit;
					break;
			}
		}

		public function execute_callback_authenticated( $method, $user ) {

			global $wpdb;

			$request = json_decode( file_get_contents( 'php://input' ), true );;
			switch ( $method ) {
				case "user_for_email":
					$u = get_user_by( "email", $request['email'] );
					wp_send_json( [ 'user' => $u, 'email_supplied' => $request['email'] ] );

					break;
				case "save_api_keys":
					// save the user API keys.

					$input = file_get_contents( 'php://input' );
					$vars = json_decode($input, true );

					$other_params = json_decode(base64_decode($_GET['params']));
					$consumer_secret = $vars['consumer_secret'];
					$consumer_key    = $vars['consumer_key'];

					update_option("WC_settings_wootoapp_ck", $consumer_key);
					update_option( "WC_settings_wootoapp_cs", $consumer_secret );

					// awesome we have everything. Go off and tell the WTA server, get client ID and secret and save them where app expects it.
					$response = wp_remote_post( "https://app.wootoapp.com/api/register/from-plugin", [
						'method'=>'POST',
						'timeout'=>45,
						'body'=>[
							'admin_email'=> $other_params->admin_email,
							'store_url'=> $other_params->store_url,
							'store_name'=> $other_params->store_name,
							'wta_mobile' => $other_params->wta_mobile,
							'consumer_key'=>$vars['consumer_key'],
							'consumer_secret'=>$vars['consumer_secret']
						]
					] );

					if ( is_wp_error( $response ) ) {
						$error_message = $response->get_error_message();
						error_log("Something went wrong: $error_message");
					} else {
						update_option( 'wta_response', $response['body'] );
						$keys = json_decode($response['body']);
						update_option( 'WC_settings_wootoapp_site_id', $keys->id );
						update_option( 'WC_settings_wootoapp_secret_key', $keys->secret_key );
					}
// Then woo will reload pg.
					break;
				case "authenticate":
					$creds                  = array();
					$creds['user_login']    = $request["email"];
					$creds['user_password'] = $request["password"];

					$user = wp_signon( $creds, false );

					if ( $user ) {
						if ( $user->errors ) {
							wp_send_json( [ 'result' => false, 'user' => null, 'errors' => $user->errors ] );

						} else {
							wp_send_json( [ 'result' => true, 'user' => $user ] );

						}
					}
					wp_send_json( [ 'result' => false, 'user' => null ] );

					break;

				case "get_shipping_quotation":
					$line_items = $request['line_items'];
					$user_id    = $request['user_id'];


					wp_set_current_user( $user_id );
					$shipping_methods = $this->_get_shipping_methods( $line_items );

					return [ 'shipping_methods' => $shipping_methods, 'user_id' => $user_id ];
					break;
				case "try_apply_coupon":
					$line_items = $request['line_items'];
					$user_id    = $request['user_id'];
					$coupon_code = $request['coupon_code'];


					wp_set_current_user( $user_id );

						$response = $this->_try_apply_coupon( $line_items, $coupon_code );
					$valid = $response['valid'];
					$discount = $response['discount'];
					$notices = $response['notices'];
					$coupons = $response['coupons'];

					$success= true;

					$response= compact('valid','discount','notices','coupons', 'success');

					wp_send_json($response);
					break;
				case "send_password_reset_email":
					$email = $request['email'];


					echo $this->reset_email( $email ) ? "true" : "false";

					break;
			}
		}

		public function reset_email($email){


				$user_data = get_user_by( 'email', trim( wp_unslash( $email ) ) );


			if ( ! $user_data ) {

				return false;
			}

			// Redefining user_login ensures we return the right case in the email.
			$user_login = $user_data->user_login;
			$user_email = $user_data->user_email;
			$key        = get_password_reset_key( $user_data );

			if ( is_wp_error( $key ) ) {
				return $key;
			}

			$message = __( 'Someone has requested a password reset for the following account:' ) . "\r\n\r\n";
			$message .= network_home_url( '/' ) . "\r\n\r\n";
			$message .= sprintf( __( 'Username: %s' ), $user_login ) . "\r\n\r\n";
			$message .= __( 'If this was a mistake, just ignore this email and nothing will happen.' ) . "\r\n\r\n";
			$message .= __( 'To reset your password, visit the following address:' ) . "\r\n\r\n";
			$message .= '<' . network_site_url( "wp-login.php?action=rp&key=$key&login=" . rawurlencode( $user_login ),
					'login' ) . ">\r\n";

			if ( is_multisite() ) {
				$blogname = get_network()->site_name;
			} else {
				/*
				 * The blogname option is escaped with esc_html on the way into the database
				 * in sanitize_option we want to reverse this for the plain text arena of emails.
				 */
				$blogname = wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );
			}

			/* translators: Password reset email subject. 1: Site name */
			$title = sprintf( __( '[%s] Password Reset' ), $blogname );

			$title = apply_filters( 'retrieve_password_title', $title, $user_login, $user_data );

			$message = apply_filters( 'retrieve_password_message', $message, $key, $user_login, $user_data );

			if ( $message && ! wp_mail( $user_email, wp_specialchars_decode( $title ), $message ) ) {
				wp_die( __( 'The email could not be sent.' ) . "<br />\n" . __( 'Possible reason: your host may have disabled the mail() function.' ) );
			}

			return true;

		}

		/**
		 * @return array|bool|null|object|void
		 */
		public function get_authenticated_user(){
			global $wpdb;

			$consumer_key    = $_SERVER['PHP_AUTH_USER'];
			$consumer_secret = $_SERVER['PHP_AUTH_PW'];

			if(!$consumer_key){
				$consumer_key = $_GET['consumer_key'];
				$consumer_secret = $_GET['consumer_secret'];
			}


			$user = $this->get_user_data_by_consumer_key( $consumer_key );

			if(!$user || !$consumer_secret){
				return false;
			}
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

		public function _try_apply_coupon($line_items, $coupon_code){

			$c = WC()->cart;
			foreach ( $c->coupons as $coup ) {
				$c->remove_coupon( $coup );
			}
			$c->empty_cart();
			$this->coupon = $coupon_code;


			$this->_add_items_to_cart( $line_items, $c );

			define( 'WOOCOMMERCE_CHECKOUT', true );
			$c->calculate_totals();

			$pre_total = $c->get_total();

			$valid = $c->add_discount( $coupon_code );
			$post_total = $c->get_total();
			// get the discount
			$discount = $c->discount_cart;

			$coupons      = $c->coupons;
			$coupon_array = [];
			foreach ( $coupons as $code => $coupon ) {
				$coupon_array[] = $code;
			}

			$payload = array( 'a'=>'b',
			                  'valid'    => $valid,
			                  'discount' => $discount,
			                  'notices'  => wc_get_notices(),
			                  'coupons'  => $coupon_array
			);
			wc_clear_notices();

			return $payload;


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


		/**
		 * @param $consumer_key
		 *
		 * @return array|null|object|void
		 */
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

		/**
		 * @return array
		 */
		public function LoadSettingsArray() {
			$settings               = [];
			$settings['store_id']   = get_option( 'WC_settings_wootoapp_site_id' );
			$settings['secret_key'] = get_option( 'WC_settings_wootoapp_secret_key' );

			return $settings;
		}


	}

	$WooToApp = new WooToApp();
}