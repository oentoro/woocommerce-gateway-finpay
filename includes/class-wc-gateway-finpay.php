<?php

/**
 * WC_Gateway_Finpay class
 *
 * @author   Oentoro <caisaroentoro@gmail.com>
 * @package  WooCommerce Finpay Payments Gateway
 * @since    1.0.0
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Finpay Gateway.
 *
 * @class    WC_Gateway_Finpay
 * @version  1.0.7
 */
class WC_Gateway_Finpay extends WC_Payment_Gateway
{

	/**
	 * Payment gateway instructions.
	 * @var string
	 *
	 */
	protected $instructions;

	/**
	 * Whether the gateway is visible for non-admin users.
	 * @var boolean
	 *
	 */
	protected $hide_for_non_admin_users;

	/**
	 * Unique id for the gateway.
	 * @var string
	 *
	 */
	public $id = 'finpay';

	private $sandbox_url = 'https://devo.finnet.co.id/pg/payment/card/initiate';
	private $production_url = 'https://live.finnet.co.id/pg/payment/card/initiate';

	/**
	 * Constructor for the gateway.
	 */
	public function __construct()
	{

		$this->icon               = apply_filters('woocommerce_finpay_gateway_icon', '');
		$this->has_fields         = false;
		$this->supports           = array(
			'pre-orders',
			'products',
			'subscriptions',
			'subscription_cancellation',
			'subscription_suspension',
			'subscription_reactivation',
			'subscription_amount_changes',
			'subscription_date_changes',
			'multiple_subscriptions'
		);

		$this->method_title       = _x('Finpay Payment', 'Finpay payment method', 'woocommerce-gateway-finpay');
		$this->method_description = __('Allows finpay payments.', 'woocommerce-gateway-finpay');

		// Load the settings.
		$this->init_form_fields();
		$this->init_settings();

		// Actions.
		add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
		add_action('woocommerce_scheduled_subscription_payment_finpay', array($this, 'process_subscription_payment'), 10, 2);
		add_action('woocommerce_api_' . $this->id, array($this, 'webhook'));
	}

	/**
	 * Initialise Gateway Settings Form Fields.
	 */
	public function init_form_fields()
	{

		$this->form_fields = array(
			'enabled'       => array(
				'title'     => __('Enable/Disable', 'finpay-woocommerce'),
				'type'      => 'checkbox',
				'label'     => __('Enable Finpay Payment', 'finpay-woocommerce'),
				'default'   => 'no'
			),
			'select_finpay_environment' => array(
				'title'           => __('Environment', 'finpay-woocommerce'),
				'type'            => 'select',
				'default'         => 'sandbox',
				'description'     => __('Select the finpay Environment', 'finpay-woocommerce'),
				'options'         => array(
					'sandbox'           => __('Sandbox', 'finpay-woocommerce'),
					'production'        => __('Production', 'finpay-woocommerce'),
				),
			),
			'username_sandbox'  => array(
				'title'         => __("Merchant ID - Sandbox", 'finpay-woocommerce'),
				'type'          => 'text',
				'description'   => sprintf(__('Input your finpay\'s Merchant ID. Get the Merchant ID <a href="%s" target="_blank">here</a>', 'finpay-woocommerce'), $this->sandbox_url),
				'default'       => '',
				'class'         => 'sandbox_settings toggle-finpay',
			),

			'password_sandbox'  => array(
				'title'         => __("Merchant Key - Sandbox", 'finpay-woocommerce'),
				'type'          => 'password',
				'description'   => sprintf(__('Input your finpay\'s Merchant Key. Get the password <a href="%s" target="_blank">here</a>', 'finpay-woocommerce'), $this->sandbox_url),
				'default'       => '',
				'class'         => 'sandbox_settings toggle-finpay'
			),

			'username_production'    => array(
				'title'         => __("Merchant ID - Production", 'finpay-woocommerce'),
				'type'          => 'text',
				'description'   => sprintf(__('Input your <b>Production</b> Merchant ID. Get the key <a href="%s" target="_blank">here</a>', 'finpay-woocommerce'), $this->production_url),
				'default'       => '',
				'class'         => 'production_settings toggle-finpay',
			),

			'password_production'     => array(
				'title'         => __("Merchant Key - Production", 'finpay-woocommerce'),
				'type'          => 'password',
				'description'   => sprintf(__('Input your <b>Production</b> Merchant Key. Get the key <a href="%s" target="_blank">here</a>', 'finpay-woocommerce'), $this->production_url),
				'default'       => '',
				'class'         => 'production_settings toggle-finpay'
			),

			'timeout'    => array(
				'title'         => __("Timeout", 'finpay-woocommerce'),
				'type'          => 'text',
				'description'   => __('Input your <b>Timeout</b>', 'finpay-woocommerce'),
				'default'       => '1440',
			)
		);
	}

	/**
	 * Process the payment and return the result.
	 *
	 * @param  int  $order_id
	 * @return array
	 */
	public function process_payment($order_id)
	{
		$logger = new WC_Logger;

		$order = wc_get_order($order_id);
		$env = $this->get_option('select_finpay_environment');
		if ($env == "sandbox") {
			$url = $this->sandbox_url;
			$username = $this->get_option('username_sandbox');
			$password = $this->get_option('password_sandbox');
		} else {
			$url = $this->production_url;
			$username = $this->get_option('username_production');
			$password = $this->get_option('password_production');
		}

		$phone = $order->get_billing_phone();
		$zero = $phone[0];

		if ($zero == '0') {
			$phone = '+62' . ltrim($phone, "0");
		}

		$customer = [
			'email' => $order->get_billing_email(),
			'firstName' => $order->get_billing_first_name(),
			'lastName' => $order->get_billing_last_name(),
			'mobilePhone' => $phone
		];

		$items = [];

		foreach ($order->get_items() as $item) {

			$item_name    = $item->get_name(); // Name of the product
			$quantity     = $item->get_quantity();

			## Access Order Items data properties (in an array of values) ##
			$item_data    = $item->get_data();
			$quantity     = $item_data['quantity'];
			$line_subtotal     = $item_data['subtotal'];
			$items[] = [
				'name' => $item_name,
				'quantity' => intval($quantity),
				'unitPrice' => floatval($line_subtotal),
			];
		}

		$order_data = [
			'id' => $order_id,
			'amount' => floatval($order->get_total()),
			'item' => $items,
			'description' => 'Order ID: ' . $order_id,
		];

		$body = [
			'merchant_id' => $username,
			'merchant_pwd' => $password,
			'customer' => $customer,
			'order' => $order_data,

			'url' => [
				'callbackUrl' => home_url('/') . 'wc-api/finpay?order=' . $order_id,
				'backUrl' => get_permalink(wc_get_page_id('shop')),
				'failUrl' => home_url('/') . 'wc-api/finpay?status=gagal',
				'successUrl' => home_url('/') . 'wc-api/finpay?status=sukses'
			]
		];
		$logger -> info('REQUEST FINPAY: '.json_encode($body));

		$auth = "Basic " . base64_encode($username . ":" . $password);
		$response = wp_remote_post($url, [
			'headers' => [
				'Accept' => 'application/json',
				'Content-Type' => 'application/json',
				'Authorization' =>  $auth
			],
			'body' => json_encode($body),
		]);


		if (is_wp_error($response)) {
			
			$error_message = $response->get_error_message();
			$logger -> error('ERROR KETIKA REQUEST KE FINPAY '.$error_message);
			$message = __('Order payment failed: ' . $error_message, 'woocommerce-gateway-finpay');
			$order->update_status('failed', $message);
			throw new Exception($message);
		}

		// var_dump($response); exit();
		$response = json_decode($response['body']);
		if ($response->responseCode == '2000000') {
			$logger -> info('set cookie');
			$this->set_finish_url_user_cookies($order);
			WC()->cart->empty_cart();
			$order->update_status('pending', __('Awaiting payment', 'woothemes'));
			return array(
				'result' => 'success',
				'redirect' => $response->redirecturl
			);
		} else {
			$message = __('Order payment failed. ' . $response->responseMessage, 'woocommerce-gateway-finpay');
			$order->update_status('failed', $message);
			throw new Exception($message);
		}
	}

	/**
	 * Process subscription payment.
	 *
	 * @param  float     $amount
	 * @param  WC_Order  $order
	 * @return void
	 */
	public function process_subscription_payment($amount, $order)
	{
		$payment_result = $this->get_option('result');

		if ('success' === $payment_result) {
			$order->payment_complete();
		} else {
			$order->update_status('failed', __('Subscription payment failed. To make a successful payment using Finpay Payments, please review the gateway settings.', 'woocommerce-gateway-finpay'));
		}
	}

	public function webhook()
	{
		$this->init_settings();

		$logger = new WC_Logger;
		if ($_SERVER['REQUEST_METHOD'] == 'GET') {
			// $logger->info('Masuk ke get');
			if ($_GET['status'] == 'sukses') {
				// echo 'masuk sini';exit();
				$this->checkAndRedirectUserToFinishUrl();
			} elseif ($_GET['status'] == 'gagal') {
				wp_redirect(get_permalink(wc_get_page_id('shop')));
			}
			die('This endpoint is for finpay notification URL (HTTP POST). This message will be shown if opened using browser (HTTP GET).');
		}


		$input_source = "php://input";
		$raw_notification = json_decode(file_get_contents($input_source), true);
		$logger->info('RECEIVED NOTIFICATION: ' . json_encode($raw_notification));

		// Get WooCommerce order
		$wcorder = wc_get_order($raw_notification['order']['id']);
		// exit if the order id doesn't exist in WooCommerce dashboard
		if (!$wcorder) {
			$logger->info('Can\'t find order id' . $raw_notification['order']['id'] . ' on WooCommerce dashboard');
			header('HTTP/1.1 404 Error');
			die('Not found!');
		}
		// Verify finpay notification
		// $finpay_notification = WC_Finpay_API::getStatusFromfinpayNotif( $plugin_id );
		$logger->info('Status: ' . $raw_notification['result']['payment']['status'] . ', SOF: ' . $raw_notification['sourceOfFunds']['type']);


		$plugin_options = $this->settings;
		$key = $plugin_options['select_finpay_environment'] == 'production' ? $plugin_options['password_production'] : $plugin_options['password_sandbox'];

		$incoming_signature = $raw_notification['signature'];
		unset($raw_notification['signature']);

		$signature = hash_hmac("sha512", json_encode($raw_notification), $key);

		$logger->info('signature: ' . $signature . ', incoming signature: ' . $incoming_signature);
		// 
		if ($signature == $incoming_signature) {
			if ($raw_notification['result']['payment']['status'] == 'PAID' || ($raw_notification['result']['payment']['status'] == 'CAPTURED' && $raw_notification['sourceOfFunds']['type'] == 'cc')) {
				$order = wc_get_order($raw_notification['order']['id']);
				// @TODO: relocate this check into the function itself, to prevent unnecessary double DB query load
				$order->payment_complete();
				header('HTTP/1.1 200 OK');
				die('00');
			} else {
				$order = wc_get_order($raw_notification['order']['id']);
				$order->update_status('failed', $raw_notification['result']['payment']['status']);
				$logger->error('Request payment failed: '.$raw_notification['result']['payment']['status']);
				header('HTTP/1.1 500 Error');
				die('-1');
			}
		} else {
			header('HTTP/1.1 401 Error');
			die('-1');
		}
	}

	public function checkAndRedirectUserToFinishUrl()
	{
		/** Wordpress menggunakan cookie daripada session */
		if (isset($_COOKIE['wc_finpay_last_order_finish_url'])) {
			wp_redirect($_COOKIE['wc_finpay_last_order_finish_url']);
		} else {
			wp_redirect(get_permalink(wc_get_page_id('shop')));
		}
	}

	public function set_finish_url_user_cookies($order)
	{
		$cookie_name = 'wc_finpay_last_order_finish_url';
		$order_finish_url = $this->get_return_url($order);
		setcookie($cookie_name, $order_finish_url, time() + 86400, "/");
	}
}
