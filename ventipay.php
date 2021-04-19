<?php
/**
 * Plugin Name: VENTI Pay
 * Plugin URI: https://docs.ventipay.com/
 * Description: Acepta pagos y suscripciones con tarjetas.
 * Author: VENTI Pay
 * Author URI: https://www.ventipay.com/
 * Version: 0.0.1
 * Requires at least: 4.4
 * Tested up to: 5.6
 * WC requires at least: 3.0
 * WC tested up to: 5.0
 * Text Domain: ventipay
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
  exit;
}

if (!function_exists('write_log')) {
  function write_log($log) {
    if (is_array($log) || is_object($log)) {
      error_log(print_r($log, true));
    } else {
      error_log($log);
    }
  }
}

function ventipay_add_gateway_class($methods) {
  $methods[] = 'WC_VENTIPay_Gateway';
  return $methods;
}

add_filter('woocommerce_payment_gateways', 'ventipay_add_gateway_class');

add_action('plugins_loaded', 'ventipay_init_gateway_class');

function ventipay_init_gateway_class() {
  class WC_VENTIPay_Gateway extends WC_Payment_Gateway {
    public function __construct() {
      $this->id = 'ventipay';
      $this->icon = '';
      $this->has_fields = false;
      $this->method_title = __('VENTI Pay', 'ventipay');
      $this->method_description = __('Acepta pagos y suscripciones con tarjetas', 'ventipay');
      $this->supports = array(
        'products'
      );

      $this->init_form_fields();
      $this->init_settings();

      $this->title = $this->get_option('title');
      $this->description = $this->get_option('description');
      $this->enabled = $this->get_option('enabled');
      $this->testmode = 'yes' === $this->get_option('testmode');
      $this->api_key = $this->testmode ? $this->get_option('test_api_key') : $this->get_option('live_api_key');

      add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
      
      // We need custom JavaScript to obtain a token
      //add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );
      add_action('woocommerce_api_ventipay', array($this, 'ventipay_ipn_notification'));
    }

    public function init_form_fields() {
      $this->form_fields = array(
        'enabled' => array(
          'title' => __('Habilita/Deshabilita', 'ventipay'),
          'label' => __('Habilitar pagos con VENTI Pay', 'ventipay'),
          'type' => 'checkbox',
          'description' => '',
          'default' => 'no'
        ),
        'title' => array(
          'title' => __('Título', 'ventipay'),
          'type' => 'text',
          'description' => __('Título a mostrar a los clientes al elegir el método de pago.', 'ventipay'),
          'default' => __('Tarjeta de Crédito, Débito o Prepago', 'ventipay'),
          'desc_tip' => true
        ),
        'description' => array(
          'title' => __('Descripción', 'ventipay'),
          'type' => 'textarea',
          'description' => __('Descripción a mostrar a los clientes al elegir el método de pago.', 'ventipay'),
          'default' => __('Paga con cualquier tarjeta o billetera digital.', 'ventipay')
        ),
        'testmode' => array(
          'title' => __('Modo Pruebas', 'ventipay'),
          'label' => __('Habilita el Modo Pruebas', 'ventipay'),
          'type' => 'checkbox',
          'description' => __('Si habilitas esta opción podrás hacer pruebas de integración sin aceptar pagos reales.', 'ventipay'),
          'default' => 'yes',
          'desc_tip' => true
        ),
        'test_api_key' => array(
          'title' => __('API Key Modo Pruebas', 'ventipay'),
          'type' => 'password',
          'placeholder' => 'key_test_....'
        ),
        'live_api_key' => array(
          'title' =>__( 'API Key Modo Live', 'ventipay'),
          'type' => 'password',
          'placeholder' => 'key_live_....'
        )
      );
    }

    public function get_supported_currency() {
      return array(
        'CLP',
        'CLF'
      );
    }

    public function payment_scripts() {
    }

    public function process_payment($order_id) {
      global $woocommerce;

      // check (try/catch) if order exists
      $order = new WC_Order($order_id);
      // format currency
      $amount = (int) number_format($order->get_total(), 0, ',', '');
      $currency = $order->get_currency();
      $return_url = add_query_arg('order_id', $order_id, add_query_arg( 'wc-api', 'ventipay', home_url( '/' )));

      // Maybe an invoice is better?
      // Create an SDK/API helper
      $args = array(
        'headers' => array(
          'Authorization' => 'Basic ' . base64_encode($this->api_key . ':'),
        ),
        'body' => array(
          'amount' => $amount,
          'currency' => $currency,
          'capture' => false,
          'cancel_url' => $return_url,
          'success_url' => $return_url,
          // metadata is not being correctly sent
          'metadata' => array(
            'wp_order_id' => $order_id
          )
        )
      );

      $response = wp_remote_post('http://host.docker.internal:8081/v1/payments', $args);

      if (!is_wp_error($response)) {
        $body = json_decode($response['body'], true);

        write_log('-----> Response Body');

        write_log($body);
 
        if (isset($body) && ($body['object'] === 'payment') && ($body['status'] === 'requires_authorization') && isset($body['url'])) {
          $order->add_meta_data('ventipay_payment_id', $body['id'], true);
          // Validate if on-hold is the proper status
          $order->update_status('on-hold', __('Esperando a que el cliente autorize el pago', 'ventipay'));

          return array(
            'result' => 'success',
            'redirect' => esc_url_raw($body['url'])
          );
        }

        // redirection with fail status?
        wc_add_notice('Please try again.', 'error');
        return;
      } else {
        wc_add_notice('Connection error.', 'error');
        return;
      }
    }

    public function ventipay_ipn_notification() {
      $data = isset($_POST) ? $_POST : $_GET;

      write_log('_GET');
      write_log($_GET);

      write_log('_POST');
      write_log($_POST);

      // check if order exists
      // try/catch for other errors
      $order = new WC_Order($_GET['order_id']);

      if ($order->is_paid()) {
        return wp_redirect($order->get_checkout_order_received_url());
      }

      if ($order->needs_payment()) {
        // get payment ID from metadata and check values (amount and currency?)
        // attempt to capture payment
        // if successful, then complete payment and redirect
        // if not, cancel order (or reset)
        $order->payment_complete();
        return wp_redirect($order->get_checkout_order_received_url());
      }

      // send and error?
      return wp_redirect($order->get_checkout_order_received_url());
    }
  }
}
