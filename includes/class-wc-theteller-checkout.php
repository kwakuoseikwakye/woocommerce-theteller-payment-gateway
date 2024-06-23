<?php
class WC_Theteller extends WC_Payment_Gateway
{
      public static $log_enabled = false;
      public static $log = false;

      public const BASE_URLS = [
            'prod' => 'https://checkout.theteller.net/initiate',
            'test' => 'https://checkout-test.theteller.net/initiate'
      ];

      protected $api_base_url;

      /**
       * Thteller merchant ID.
       *
       * @var string
       */
      public $merchant_id;

      /**
       * Theteller merchant name.
       *
       * @var string
       */
      public $merchant_name;

      /**
       * Theteller api user credentials.
       *
       * @var string
       */
      public $apiuser;

      /**
       * Theteller api key.
       *
       * @var string
       */
      public $apikey;

      /**
       * Should mark as live?.
       *
       * @var bool
       */
      public $go_live;

      /**
       * currency to transact.
       *
       * @var string
       */
      public $currency;

      /**
       * payment channel.
       *
       * @var string
       */
      public $channel;

      /**
       * Output messages.
       *
       * @var string
       */
      public $msg;

      public function __construct()
      {
            $this->id = 'theteller';
            $this->method_title = __('PaySwitch Theteller', 'woocommerce');
            $this->method_description = __('Pay with Mobile Money and Card via Theteller Checkout.', 'woocommerce');
            $this->icon = apply_filters('woocommerce_theteller_icon', plugins_url('assets/images/logo.png', __FILE__));
            $this->has_fields = false;

            $this->title = $this->settings['title'];
            $this->description = $this->settings['description'];
            $this->enabled     = $this->settings['enabled'];

            $this->merchant_name = $this->settings['merchant_name'];
            $this->currency = $this->settings['currency'];
            $this->channel = $this->settings['channel'];
            $this->merchant_id = $this->settings['merchant_id'];

            $this->apiuser = $this->settings['apiuser'];
            $this->apikey = $this->settings['apikey'];
            $this->go_live = $this->settings['go_live'];

            $this->api_base_url = $this->settings['go_live'] == 'yes' ? self::BASE_URLS['prod'] : self::BASE_URLS['test'];
            $this->msg['message'] = "";
            $this->msg['class'] = "";

            $this->init_form_fields();
            $this->init_settings();

            if (isset($_GET["theteller-response-notice"]) || isset($_GET["theteller-response-notice"]) != null) {
                  wc_add_notice(isset($_GET["theteller-response-notice"]), "error");
            }

            if (isset($_GET["theteller-error-notice"]) || isset($_GET["theteller-error-notice"]) != null) {
                  wc_add_notice(isset($_GET["theteller-error-notice"]), "error");
            }

            if (isset($_GET["order_id"]) || isset($_GET["order_id"]) != null) {
                  $this->process_payment($_GET["order_id"]);
            }

            if (version_compare(WC()->version, '8.0.0', '>=')) {
                  add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
            } else {
                  add_action('woocommerce_update_options_payment_gateways', [$this, 'process_admin_options']);
            }
      }

      public function init_form_fields()
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
                  )
            );
      }

      public function admin_options(): void
      {
            echo '<h3>' . esc_html(__('Theteller Payment Gateway', 'theteller')) . '</h3>';
            echo '<p>' . esc_html(__('Accept payments from cards to mobile money with Theteller.', 'theteller')) . '</p>';
            echo '<table class="form-table">';

            // Generate the HTML for the settings form.
            $this->generate_settings_html();

            echo '</table>';
      }

      public function payment_fields(): void
      {
            if ($this->description) {
                  echo wpautop(wptexturize($this->description));
            }
      }

      public function send_request_to_theteller_api($order_id)
      {
            // Getting settings...
            $merchantname = $this->merchant_name;
            $merchantid = $this->merchant_id;
            $api_base_url = $this->api_base_url;
            $apiuser = $this->apiuser;
            $apikey = $this->apikey;
            $amount = $order->get_total();
            $customer_email = $order->get_billing_email();
            $currency = $this->currency;
            $channel = $this->channel;
            $order = new WC_Order($order_id);
            // Redirect URL
            $redirect_url = wc_get_checkout_url() . '?order_id=' . $order_id . '&theteller_response';

            // Convert amount to minor integer
            $minor = match (true) {
                  is_numeric($amount) && ($number = (int) ($amount * 100)) >= 0 && $number <= 999999999999 => str_pad($number, 12, '0', STR_PAD_LEFT),
                  is_string($amount) && strlen($amount) === 12 && ctype_digit($amount) => $amount,
                  default => '',
            };

            if (empty($minor)) {
                  return ''; // Return empty if minor amount conversion fails
            }

            // Set the order ID in the session
            WC()->session->set('theteller_wc_order_id', $order_id);

            // Generating a unique transaction ID
            $transaction_id = '';
            for ($i = 0; $i < 12; $i++) {
                  $transaction_id .= random_int(0, 9);
            } // Generate a 12-digit random number
            WC()->session->set('theteller_wc_transaction_id', $transaction_id);

            // Hashing order details
            $key_options = $merchantid . $transaction_id . $amount . $customer_email;
            $theteller_wc_hash_key = hash('sha512', $key_options);
            WC()->session->set('theteller_wc_hash_key', $theteller_wc_hash_key);

            // Currency mapping
            $currency_map = ['GHS', 'USD', 'EUR', 'GBP'];
            $currency = in_array($currency, $currency_map) ? $currency : 'GHS';

            // Channel mapping
            $channel_map = ['card', 'momo', 'both'];
            $channel = in_array($channel, $channel_map) ? $channel : 'both';

            // Payload to send to API
            $postdata = [
                  'body' => json_encode([
                        'merchant_id' => $merchantid,
                        'transaction_id' => $transaction_id,
                        'desc' => "Payment to " . $merchantname,
                        'amount' => $minor,
                        'email' => $customer_email,
                        'redirect_url' => $redirect_url,
                        'payment_method' => $channel,
                        'currency' => $currency
                  ]),
                  'method' => 'POST',
                  'timeout' => 60,
                  'redirection' => 10,
                  'httpversion' => '1.0',
                  'blocking' => true,
                  'headers' => [
                        'Content-Type' => 'application/json',
                        'cache-control' => 'no-cache',
                        'Authorization' => 'Basic ' . base64_encode($apiuser . ':' . $apikey)
                  ],
            ];

            // Making the request
            $response = wp_remote_post($api_base_url, $postdata);

            // Handling response
            if (is_wp_error($response)) {
                  $error_message = $response->get_error_message();
                  $this->log('API Request Failed: ' . $error_message, 'error');
                  wc_add_notice(__('Payment error: ', 'woocommerce') . $error_message, 'error');
                  return $redirect_url . '&theteller-response-notice=' . urlencode($error_message);
            }

            // Getting response body...
            $response_data = json_decode(wp_remote_retrieve_body($response), true);

            // Extracting response values
            $code = $response_data['code'] ?? null;
            $status = $response_data['status'] ?? null;
            $token = $response_data['token'] ?? null;
            $description = $response_data['description'] ?? null;
            $checkout_url = $response_data['checkout_url'] ?? null;

            // Checking response and returning appropriate URLs
            if ($status === 'success' && $code === '200' && !empty($token)) {
                  // Redirect to checkout page
                  return $checkout_url;
            } else {
                  return $redirect_url . '&theteller-response-notice=' . urlencode($description);
            }
      }

      public function process_payment($order_id)
      {
            $order_id = $_GET['order_id'];
            WC()->session->set('theteller_wc_order_id', $order_id);
            $order = wc_get_order($order_id);

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
                              return $this->update_order_status();
                        }
                  }
            }

            return [
                  'result' => 'success',
                  'redirect' => $redirect_url,
            ];
      }

      public function update_order_status()
      {
            global $woocommerce;

            // Getting Order ID from Session
            $wc_order_id = WC()->session->get('theteller_wc_order_id');
            $order = wc_get_order($wc_order_id);

            if (!$order) {
                  $this->log('Order ID does not exist or is invalid', 'error');
                  wc_add_notice(__('Invalid order ID.', 'woocommerce'), 'error');
                  wp_safe_redirect(wc_get_page_permalink('shop'));
                  exit();
            }

            $theteller_wc_hash_key = WC()->session->get('theteller_wc_hash_key');

            if (empty($theteller_wc_hash_key)) {
                  $this->log('Checking Response: Invalid hash key or empty', 'error');
                  wc_add_notice(__('Something went wrong. Invalid hash key.', 'woocommerce'), 'error');
                  wp_safe_redirect($order->get_cancel_order_url());
                  exit();
            }

            if (empty($wc_order_id)) {
                  $this->log('Order ID does not exist in session', 'error');
                  wc_add_notice(__('Data has been tampered. Order ID is missing.', 'woocommerce'), 'error');
                  wp_safe_redirect($order->get_cancel_order_url());
                  exit();
            }

            if ($order->get_status() !== 'pending' && $order->get_status() !== 'processing') {
                  $this->log('Order does not exist or already processed', 'error');
                  wc_add_notice(__('Order has been processed or expired. Try another one.', 'woocommerce'), 'error');
                  wp_safe_redirect(wc_get_page_permalink('shop'));
                  exit();
            }

            try {
                  $order->update_status('completed', __('Payment completed successfully', 'woocommerce'));
                  $order->add_order_note(__('Theteller payment completed successfully.', 'woocommerce'));

                  // Clear cart and session data
                  $woocommerce->cart->empty_cart();
                  WC()->session->__unset('theteller_wc_hash_key');
                  WC()->session->__unset('theteller_wc_order_id');

                  // Add metadata to the order
                  add_post_meta($wc_order_id, '_theteller_hash', $theteller_wc_hash_key, true);
                  update_post_meta($wc_order_id, '_theteller_wc_message', [
                        'message' => "Thank you for shopping with us. Your transaction was successful, payment has been received. Your Order ID is {$wc_order_id}",
                        'message_type' => 'success'
                  ]);

                  // Redirect to the order received page
                  wp_safe_redirect($this->get_return_url($order));
                  exit();
            } catch (Exception $e) {
                  $this->log('Payment Exception: ' . $e->getMessage(), 'error');
                  $order->add_order_note('Error: ' . $e->getMessage());
                  wc_add_notice(__('Payment error: ', 'woocommerce') . $e->getMessage(), 'error');
                  wp_safe_redirect($order->get_cancel_order_url());
                  exit();
            }
      }

      function showMessage($content)
      {
            return '<div class="box ' . $this->msg['class'] . '-box">' . $this->msg['message'] . '</div>' . $content;
      }

      function get_pages(bool $includeTitle = false, bool $useIndentation = true): array
      {
            if (!$includeTitle) {
                  return [];
            }

            $wp_pages = get_pages(['sort_column' => 'menu_order']);
            $page_list = [];

            foreach ($wp_pages as $page) {
                  $prefix = '';

                  // Show indented child pages?
                  if ($useIndentation) {
                        $has_parent = $page->post_parent;
                        while ($has_parent) {
                              $prefix .= ' - ';
                              $next_page = get_post($has_parent);
                              if ($next_page && is_object($next_page)) {
                                    $has_parent = $next_page->post_parent;
                              } else {
                                    $has_parent = 0;
                              }
                        }
                  }

                  // Add to page list array
                  $page_list[$page->ID] = $prefix . $page->post_title;
            }

            return $page_list;
      }

      function check_theteller_response()
      {
            global $woocommerce;


            //Getting Order ID from Session...
            $wc_order_id = WC()->session->get('theteller_wc_oder_id');
            $order = wc_get_order($wc_order_id);

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
                  $merchant_id = $this->merchant_id;

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

                  add_post_meta($wc_order_id, '_theteller_hash', $theteller_wc_hash_key, true);
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
}
