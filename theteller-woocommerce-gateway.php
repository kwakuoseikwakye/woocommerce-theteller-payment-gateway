<?php

/**
 * Plugin Name: WooCommerce PaySwitch Theteller Payment Gateway
 * Plugin URI: https://theteller.net
 * Description: PaySwitch Theteller Payment gateway for WooCommerce
 * Version: 1.0.2
 * Author: Kwaku Osei Kwakye
 * Author URI: https://github.com/kwakuoseikwakye
 * Requires at least: 5.0
 * Tested up to: 6.5
 * WC requires at least: 5.0
 * WC tested up to: 8.9
 */

if (!defined('ABSPATH')) {
      exit("Unauthorized access. Permission denied");
}

if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
      exit("Woocommerce is not defined or active. Kindly activate or install Woocommerce.");
}

add_action('plugins_loaded', 'woocommerce_theteller_init', 0);

function woocommerce_theteller_init()
{
      if (!class_exists('WC_Payment_Gateway') || empty(class_exists('WC_Payment_Gateway'))) {
            exit("Payment Gateway does not exist.");
      }

      class WC_Theteller extends WC_Payment_Gateway
      {
            /**
             * Whether or not logging is enabled
             *
             * @var bool
             */
            public static $log_enabled = false;

            /**
             * Logger instance
             *
             * @var WC_Logger
             */
            public static $log = false;

            public const BASE_URLS = [
                  'prod' => 'https://checkout.theteller.net/initiate',
                  'test' => 'https://checkout-test.theteller.net/initiate'
            ];

            protected $api_base_url;

            private $merchant_id;
            private $merchant_name;
            private $apiuser;
            private $apikey;
            private $go_live;
            private $theteller_smpp;
            private $smpp_user;
            private $smpp_password;
            private $smpp_sender;
            private $currency;
            private $channel;
            private $msg;
            private $keys = [];

            public function __construct()
            {
                  $this->id = 'theteller';
                  $this->method_title = __('PaySwitch Theteller', 'woocommerce');
                  $this->method_description = __('Pay with Mobile Money and Card via Theteller Checkout.', 'woocommerce');
                  $this->icon = apply_filters('woocommerce_theteller_icon', plugins_url('assets/images/logo.png', __FILE__));
                  $this->has_fields = false;

                  $this->init_form_fields();
                  $this->init_settings();


                  $this->title = $this->settings['title'];
                  $this->description = $this->settings['description'];
                  $this->merchant_name = $this->settings['merchant_name'];
                  $this->merchant_id = $this->settings['merchant_id'];
                  $this->apiuser = $this->settings['apiuser'];
                  $this->apikey = $this->settings['apikey'];
                  $this->go_live = $this->settings['go_live'];
                  $this->theteller_smpp = $this->settings['theteller_smpp'];
                  $this->smpp_user = $this->settings['smpp_user'];
                  $this->smpp_password = $this->settings['smpp_password'];
                  $this->smpp_sender = $this->settings['smpp_sender'];
                  $this->currency = $this->settings['currency'];
                  $this->channel = $this->settings['channel'];

                  $this->api_base_url = $this->settings['go_live'] === 'yes' ? self::BASE_URLS['prod'] : self::BASE_URLS['test'];

                  $this->msg['message'] = "";
                  $this->msg['class'] = "";

                  if (isset($_GET["theteller-response-notice"]) || isset($_GET["theteller-response-notice"]) != null) {
                        wc_add_notice(isset($_GET["theteller-response-notice"]), "error");
                  }

                  if (isset($_GET["theteller-error-notice"]) || isset($_GET["theteller-error-notice"]) != null) {
                        wc_add_notice(isset($_GET["theteller-error-notice"]), "error");
                  }

                  if (isset($_GET["order_id"]) || isset($_GET["order_id"]) != null) {

                        //Process payment..
                        $this->process_payment($_GET["order_id"]);
                  }


                  if (version_compare(WC()->version, '5.0.0', '>=')) {
                        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
                  } else {
                        add_action('woocommerce_update_options_payment_gateways', [$this, 'process_admin_options']);
                  }
            }

            //Iniatialization of config form...
            function init_form_fields()
            {
                  $this->form_fields = array(

                        'enabled' => array(
                              'title' => __('Enable/Disable', 'theteller'),
                              'type' => 'checkbox',
                              'label' => __('Enable Theteller Payment Gateway as a payment option on the checkout page.', 'theteller'),
                              'default' => 'no'
                        ),

                        'go_live' => array(
                              'title'       => __('Go Live', 'theteller'),
                              'label'       => __('Check to live environment', 'client'),
                              'type'        => 'checkbox',
                              'description' => __('Ensure that you have all your credentials details set.', 'client'),
                              'default'     => 'no',
                              'desc_tip'    => true
                        ),

                        'title' => array(
                              'title' => __('Title', 'theteller'),
                              'type' => 'text',
                              'description' => __('This controls the title which the user sees during checkout.', 'theteller'),
                              'disabled' => true,
                              'placeholder' => 'Payment using Mobile Money & Card',
                              'default' => __('Payment using Mobile Money & Card', 'theteller')
                        ),

                        'description' => array(
                              'title' => __('Description', 'theteller'),
                              'type' => 'textarea',
                              'description' => __('This controls the description which the user sees during checkout.', 'client'),
                              'disabled' => true,
                              'placeholder' => 'Pay securely by Credit , Debit card or Mobile Money through PaySwitch Theteller Checkout',
                              'default' => __('Pay securely by Credit , Debit card or Mobile Money through PaySwitch Theteller Checkout.', 'client')
                        ),

                        'currency' => array(
                              'title' => __('Currency', 'theteller'),
                              'type' => 'select',
                              'options' => array('GHS', 'USD', 'EURO', 'GBP'),
                              'description' => __('Select your currency. Default is : GHS', 'client')
                        ),


                        'channel' => array(
                              'title' => __('Channel', 'theteller'),
                              'type' => 'select',
                              'options' => array('Card Only', 'Mobile Money Only', 'Both'),
                              'description' => __('Select channel that you want to allow on the checkout page. Default is : Both ', 'client')
                        ),



                        'merchant_name' => array(
                              'title' => __('Merchant Name / Shop name / Company Name ', 'theteller'),
                              'type' => 'text',
                              'description' => __('This will be use for the payment description. ')
                        ),


                        'merchant_id' => array(
                              'title' => __('Merchant ID', 'theteller'),
                              'type' => 'text',
                              'description' => __('Merchant ID given during registration.')
                        ),

                        'apiuser' => array(
                              'title' => __('API User', 'theteller'),
                              'type' => 'text',
                              'description' => __('API User given during registration.', 'theteller')
                        ),

                        'apikey' => array(
                              'title' => __('API Key', 'theteller'),
                              'type' => 'text',
                              'description' => __('API Key given during registration.', 'theteller')
                        ),


                        'theteller_smpp' => array(
                              'title'       => __('Enable/Disable Theteller SMS', 'theteller'),
                              'type'        => 'checkbox',
                              'description' => __('This feature allows you to send SMS to customer after successful purchase. Ensure that you have all your credentials details set.', 'client'),
                              'default'     => 'no',
                        ),

                        'smpp_user' => array(
                              'title' => __('SMS UserID', 'theteller'),
                              'type' => 'text',
                              'description' => __('SMS API User given to Merchant by Theteller SMPP', 'theteller')
                        ),

                        'smpp_password' => array(
                              'title' => __('SMS UserPass', 'theteller'),
                              'type' => 'text',
                              'description' => __('SMS API Password given to Merchant by Theteller SMPP', 'theteller')
                        ),

                        'smpp_sender' => array(
                              'title' => __('SMS SenderID', 'theteller'),
                              'type' => 'text',
                              'description' => __('Sender ID must be registred on Theteller SMPP. 11 characters Maximum'),
                        )
                  );
            }

            public function admin_options()
            {
                  echo '<h3>' . esc_html(__('Theteller Payment Gateway', 'theteller')) . '</h3>';
                  echo '<p>' . esc_html(__('Accept payments from cards to mobile money with Theteller.', 'theteller')) . '</p>';
                  echo '<table class="form-table">';

                  // Generate the HTML for the settings form.
                  $this->generate_settings_html();

                  echo '</table>';
            }

            function payment_fields()
            {
                  if ($this->description)
                        echo wpautop(wptexturize($this->description));
            }

            function send_request_to_theteller_api($order_id)
            {
                  global $woocommerce;

                  // Getting settings...
                  $merchantname = $this->merchant_name;
                  $merchantid = $this->merchant_id;
                  $api_base_url = $this->api_base_url;
                  $apiuser = $this->apiuser;
                  $apikey = $this->apikey;
                  $order = new WC_Order($order_id); // Use new WC_Order() function instead of creating a new WC_Order object.
                  $amount = $order->get_total();
                  $customer_email = $order->get_billing_email();
                  $currency = $this->currency;
                  $channel = $this->channel;



                  // Redirect URL
                  $redirect_url = wc_get_checkout_url() . '?order_id=' . $order_id . '&theteller_response';

                  // Convert amount to minor integer
                  $minor = match (true) {
                        is_numeric($amount) && ($number = (int) ($amount * 100)) >= 0 && $number <= 999999999999 =>
                        str_pad($number, 12, '0', STR_PAD_LEFT),

                        is_string($amount) && strlen($amount) === 12 && ctype_digit($amount) =>
                        $amount,

                        default => '',
                  };

                  //set the order id in the session
                  WC()->session->set('theteller_wc_order_id', $order_id);

                  // Generating a unique transaction ID
                  $transaction_id = random_int(100000000000, 999999999999); // Generate a 12-digit random number
                  WC()->session->set('theteller_wc_transaction_id', $transaction_id);

                  // Hashing order details
                  $key_options = $merchantid . $transaction_id . $amount . $customer_email;
                  $theteller_wc_hash_key = hash('sha512', $key_options);
                  WC()->session->set('theteller_wc_hash_key', $theteller_wc_hash_key);


                  // Currency mapping
                  $currency_map = ['GHS', 'USD', 'EUR', 'GBP'];
                  $currency = isset($currency_map[$currency]) ? $currency_map[$currency] : 'GHS';

                  // Channel mapping
                  $channel_map = ['card', 'momo', 'both'];
                  $channel = isset($channel_map[$channel]) ? $channel_map[$channel] : 'both';

                  // Payload to send to API
                  $postdata = [
                        'body' => json_encode([
                              "merchant_id" => $merchantid,
                              'transaction_id' => $transaction_id,
                              'desc' => "Payment to " . $merchantname . "",
                              'amount' => $minor,
                              'email' => $customer_email,
                              'redirect_url' => $redirect_url,
                              'payment_method' => $channel,
                              'currency' => $currency
                        ]),
                        "method" => "POST",
                        "timeout" => 60,
                        "redirection" => 10,
                        "httpversion" => "1.0",
                        "blocking" => true,
                        "headers" => [
                              'Content-Type' => 'application/json',
                              'cache-control' => 'no-cache',
                              "Authorization" => "Basic " . base64_encode($apiuser . ':' . $apikey)
                        ],
                  ];

                  // Making Request
                  $response = wp_remote_post($api_base_url, $postdata);

                  // Handling response
                  if (is_wp_error($response)) {
                        $this->log('API Request Failed: ' . $response->get_error_message(), 'error');
                        $error_message = "An error occurred while processing the request";
                        echo $error_message;
                  } else {
                        //Getting response body...
                        $response_data = json_decode(wp_remote_retrieve_body($response), true);
                  }

                  // Extracting response values
                  $code = $response_data['code'] ?? null;
                  $status = $response_data['status'] ?? null;
                  $token = $response_data['token'] ?? null;
                  $description = $response_data['description'] ?? null;
                  $checkout_url = $response_data['checkout_url'] ?? null;


                  // Checking response and returning appropriate URLs
                  if ($status == "success" && $code == "200" && $token != "") {
                        // Redirect to checkout page
                        return $checkout_url;
                  } else {
                        return $redirect_url . "&theteller-response-notice=" . $description;
                  }
            } //end of send_request_to_theteller_api()...


            //Processing payment...
            public function process_payment($order_id)
            {
                  WC()->session->set('theteller_wc_order_id', $order_id);
                  $order = new WC_Order($order_id);

                  $redirect_url = $this->send_request_to_theteller_api($order_id);

                  // Get the current request URI
                  $request_uri = $_SERVER['REQUEST_URI'];

                  // Parse the request URI using WordPress functions
                  $parsed_url = wp_parse_url($request_uri);

                  // Check if there are query parameters
                  if (isset($parsed_url['query'])) {
                        // Parse the query string into an array using WordPress function
                        $query_params = wp_parse_args($parsed_url['query']);

                        // Now you can access the query parameters
                        if (isset($query_params['code'])) {
                              $param_value = $query_params['code'];
                              if ($param_value == "000") {
                                    // Execute code when the condition is met
                                    return $this->updateOrderStatus();
                              }
                        }
                  }


                  return [
                        'result' => 'success',
                        'redirect' => $redirect_url,
                  ];
            }

            function updateOrderStatus()
            {
                  global $woocommerce;

                  //Getting Order ID from Session...
                  $wc_order_id = WC()->session->get('theteller_wc_order_id');
                  $order = new WC_Order($wc_order_id);


                  $theteller_wc_hash_key = WC()->session->get('theteller_wc_hash_key');

                  if (empty($theteller_wc_hash_key)) {
                        $this->log('Checking Response: Invalid hash key or empty', 'error');
                        die("<h2 style=color:red>Ooups ! something went wrong </h2>");
                  }

                  if (empty($wc_order_id)) {
                        $message = "Code 0001 : Data has been tampered. Order ID is {$wc_order_id}";
                        $message_type = "error";

                        $this->log('Order ID does not exist in session', 'error');

                        $order->add_order_note($message);
                        $redirect_url = $order->get_cancel_order_url();
                        wp_redirect($redirect_url);
                        exit();
                  }

                  if ($order->get_status() !== 'pending' && $order->get_status() !== 'processing') {
                        $this->log('Order does not exist or already processed', 'error');
                        die("<h2 style=color:red>Order has been processed or expired. Try another one </h2>");
                  }

                  try {
                        $notification_message['message'] = "Thank you for shopping with us. Your transaction was successful, payment has been received. Your Order ID is {$wc_order_id}";
                        $notification_message['message_type'] = "success";


                        $order->payment_complete();
                        $order->update_status('completed');

                        //Check if Theteller SMPP is enabled...
                        if ($this->settings['theteller_smpp'] === "yes") {
                              $phonenumber = ltrim($order->billing_phone, '0');
                              $customer_phonenumber = $order->billing_postcode . (int)$phonenumber;
                              $api_base_url = "https://smpp.theteller.net/send/single";
                              $smpp_user = $this->smpp_user;
                              $smpp_password = $this->smpp_password;
                              $smpp_sender = $this->smpp_sender;
                              $merchantname = $this->merchant_name;

                              //Payload to send to API...
                              $postdata = [
                                    'body' => json_encode([
                                          "sender"  => $smpp_sender,
                                          'phonenumber'  => $customer_phonenumber,
                                          'message'  => "Payment to {$merchantname} was successful. ",
                                    ]),
                                    'timeout' => 60,
                                    'redirection' => 5,
                                    'httpversion' => '1.0',
                                    'blocking' => true,
                                    'sslverify' => true,
                                    'headers' => [
                                          'Content-Type' => 'application/json',
                                          'cache-control' => 'no-cache',
                                          'Expect' => '',
                                          'Authorization' => 'Basic ' . base64_encode("{$smpp_user}:{$smpp_password}"),
                                    ],
                              ];

                              wp_remote_post($api_base_url, $postdata);
                        }

                        $woocommerce->cart->empty_cart();
                        WC()->session->__unset('theteller_wc_hash_key');
                        WC()->session->__unset('theteller_wc_order_id');
                        // WC()->session->__unset('theteller_wc_transaction_id');
                        add_post_meta($wc_order_id, '_theteller_hash', $theteller_wc_hash_key, true);
                        wp_redirect($this->get_return_url($order));

                        if (version_compare(WC()->version, "4.0") >= 0) {
                              add_post_meta($wc_order_id, '_theteller_hash', $theteller_wc_hash_key, true);
                        }
                        update_post_meta($wc_order_id, '_theteller_wc_message', $notification_message);
                  } catch (Exception $e) {
                        $this->log('Payment Exception ' . $e->getMessage(), 'error');
                        $order->add_order_note('Error: ' . $e->getMessage());
                        $redirect_url = $order->get_cancel_order_url();
                        wp_redirect($redirect_url);
                        exit();
                  }
            }

            //show message either error or success...
            function showMessage($content)
            {
                  return '<div class="box ' . $this->msg['class'] . '-box">' . $this->msg['message'] . '</div>' . $content;
            }

            function get_pages($includeTitle = false, $useIndentation = true)
            {
                  if (!$includeTitle) {
                        return array();
                  }

                  $wp_pages = get_pages('sort_column=menu_order');
                  $page_list = array();

                  foreach ($wp_pages as $page) {
                        $prefix = '';
                        // show indented child pages?
                        if ($useIndentation) {
                              $has_parent = $page->post_parent;
                              while ($has_parent) {
                                    $prefix .= ' - ';
                                    $next_page = get_page($has_parent);
                                    $has_parent = $next_page->post_parent;
                              }
                        }
                        // add to page list array array
                        $page_list[$page->ID] = $prefix . $page->post_title;
                  }

                  return $page_list;
            }


            function check_theteller_response()
            {
                  global $woocommerce;


                  //Getting Order ID from Session...
                  $wc_order_id = WC()->session->get('theteller_wc_oder_id');
                  $order = new WC_Order($wc_order_id);

                  //Getting Transaction ID from Session...
                  $wc_transaction_id = WC()->session->get('theteller_wc_transaction_id');

                  $theteller_wc_hash_key = WC()->session->get('theteller_wc_hash_key');

                  if (empty($theteller_wc_hash_key)) {
                        $this->log('Checking Response: Invalid hash key or empty', 'error');
                        die("<h2 style=color:red>Ooups ! something went wrong </h2>");
                  }

                  if (empty($wc_transaction_id)) {
                        $message = "Code 0001 : Data has been tampered. Order ID is {$wc_order_id}";
                        $message_type = "error";

                        $this->log('Transaction ID does not exist in session', 'error');

                        $order->add_order_note($message);
                        $redirect_url = $order->get_cancel_order_url();
                        wp_redirect($redirect_url);
                        exit();
                  }

                  if ($order->get_status() !== 'pending' && $order->get_status() !== 'processing') {
                        $this->log('Order does not exist or already processed', 'error');
                        die("<h2 style=color:red>Order has been processed or expired. Try another one </h2>");
                  }

                  try {
                        $status_check_base_url = $this->settings['go_live'] === "yes" ? "https://prod.theteller.net/v1.1/users/transactions/{$wc_transaction_id}/status" : "https://test.theteller.net/v1.1/users/transactions/{$wc_transaction_id}/status";

                        //Getting settings..
                        $merchant_id = $this->settings['merchant_id'];

                        //Sending Request...
                        $response = wp_remote_get(
                              $status_check_base_url,
                              [
                                    'timeout' => 30,
                                    'redirection' => 10,
                                    'httpversion' => '1.0',
                                    'blocking' => true,
                                    'sslverify' => false,
                                    'headers' => ['Cache-Control' => 'no-cache', 'Merchant-Id' => $merchant_id],
                              ]
                        );

                        //Checking no error..
                        if (is_wp_error($response)) {
                              $this->log('API Request Failed: ' . $response->get_error_message(), 'error');
                              echo "An error occurred while processing the request";
                              return;
                        }

                        //Getting response body...
                        $response_data = json_decode(wp_remote_retrieve_body($response), true);

                        $transaction_status = $response_data['status'] ?? null;
                        $transaction_code = $response_data['code'] ?? null;
                        $transaction_reason = $response_data['reason'] ?? null;
                        $transaction_transaction_id = $response_data['transaction_id'] ?? null;
                        $transaction_amount = $response_data['amount'] ?? null;
                        $transaction_currency = $response_data['currency'] ?? null;

                        $notification_message = [];

                        if ($transaction_status === 'approved' || $transaction_status === 'Approved' && $transaction_code === '000') {
                              $notification_message['message'] = "Thank you for shopping with us. Your transaction was successful, payment has been received. Your Order ID is {$wc_order_id}";
                              $notification_message['message_type'] = "success";


                              $order->payment_complete($wc_transaction_id);
                              $order->update_status('completed');
                              $order->add_order_note("Theteller Responses : <br /> Code : {$transaction_code} <br/> Status : {$transaction_status} <br/> Amount : {$transaction_amount} <br/> Currency : {$transaction_currency} <br/> Transaction ID : {$transaction_transaction_id} <br /> Reason: {$transaction_reason}");

                              //Check if Theteller SMPP is enabled...
                              if ($this->settings['theteller_smpp'] === "yes") {
                                    $phonenumber = ltrim($order->billing_phone, '0');
                                    $customer_phonenumber = $order->billing_postcode . (int)$phonenumber;
                                    $api_base_url = "https://smpp.theteller.net/send/single";
                                    $smpp_user = $this->smpp_user;
                                    $smpp_password = $this->smpp_password;
                                    $smpp_sender = $this->smpp_sender;
                                    $merchantname = $this->merchant_name;

                                    //Payload to send to API...
                                    $postdata = [
                                          'body' => json_encode([
                                                "sender"  => $smpp_sender,
                                                'phonenumber'  => $customer_phonenumber,
                                                'message'  => "Payment to {$merchantname} was successful. Transaction ID: {$transaction_transaction_id}",
                                          ]),
                                          'timeout' => 60,
                                          'redirection' => 5,
                                          'httpversion' => '1.0',
                                          'blocking' => true,
                                          'sslverify' => true,
                                          'headers' => [
                                                'Content-Type' => 'application/json',
                                                'cache-control' => 'no-cache',
                                                'Expect' => '',
                                                'Authorization' => 'Basic ' . base64_encode("{$smpp_user}:{$smpp_password}"),
                                          ],
                                    ];

                                    wp_remote_post($api_base_url, $postdata);
                              }

                              $woocommerce->cart->empty_cart();
                              WC()->session->__unset('theteller_wc_hash_key');
                              WC()->session->__unset('theteller_wc_order_id');
                              WC()->session->__unset('theteller_wc_transaction_id');
                              wp_redirect($this->get_return_url($order));
                              exit();
                        } else {
                              $notification_message['message'] = "Thank you for shopping with us. However, the transaction has been declined.";
                              $notification_message['message_type'] = "error";

                              $order->payment_complete($wc_transaction_id);
                              $order->update_status('failed');
                              $order->add_order_note("Theteller Responses : <br /> Code : {$transaction_code} <br/> Status : {$transaction_status} <br/> Amount : {$transaction_amount} <br/> Currency : {$transaction_currency} <br/> Transaction ID : {$transaction_transaction_id} <br /> Reason: {$transaction_reason}");

                              $woocommerce->cart->empty_cart();
                              WC()->session->__unset('theteller_wc_hash_key');
                              WC()->session->__unset('theteller_wc_order_id');
                              WC()->session->__unset('theteller_wc_transaction_id');
                              wp_redirect($this->get_return_url($order));
                              exit();
                        }


                        if (version_compare(WC()->version, "4.0") >= 0) {
                              add_post_meta($wc_order_id, '_theteller_hash', $theteller_wc_hash_key, true);
                        }
                        update_post_meta($wc_order_id, '_theteller_wc_message', $notification_message);
                  } catch (Exception $e) {
                        $this->log('Payment Exception ' . $e->getMessage(), 'error');
                        $order->add_order_note('Error: ' . $e->getMessage());
                        $redirect_url = $order->get_cancel_order_url();
                        wp_redirect($redirect_url);
                        exit();
                  }
            }


            public function log($message, $level = 'info')
            {
                  if (self::$log_enabled) {
                        if (empty(self::$log)) {
                              self::$log = wc_get_logger();
                        }
                        self::$log->log($level, $message, ['source' => 'theteller']);
                  }
            }

            public static function woocommerce_add_theteller_gateway($methods)
            {
                  $methods[] = 'WC_Theteller';
                  return $methods;
            }

            public static function woocommerce_add_theteller_settings_link($links)
            {
                  $settings_link = '<a href="admin.php?page=wc-settings&tab=checkout&section=wc_theteller">Settings</a>';
                  array_unshift($links, $settings_link);
                  return $links;
            }
      }

      // ... add filters for gateway and settings link

      $plugin = plugin_basename(__FILE__);
      add_filter("plugin_action_links_$plugin", [WC_Theteller::class, 'woocommerce_add_theteller_settings_link']);
      add_filter('woocommerce_payment_gateways', [WC_Theteller::class, 'woocommerce_add_theteller_gateway']);
}

add_action('before_woocommerce_init', function () {
      if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
      }
});
