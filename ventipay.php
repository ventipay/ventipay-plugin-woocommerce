<?php
/**
 * Plugin Name: VentiPay
 * Plugin URI: https://docs.ventipay.com/
 * Description: Acepta pagos y suscripciones con tarjetas.
 * Author: VentiPay
 * Author URI: https://www.ventipay.com/
 * Version: 0.1.0
 * Requires at least: 4.4
 * Tested up to: 5.6
 * WC requires at least: 3.0
 * WC tested up to: 5.0
 * Text Domain: ventipay
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
  exit();
}

if (!function_exists('write_log')) {
  function write_log($log)
  {
    if (is_array($log) || is_object($log)) {
      error_log(print_r($log, true));
    } else {
      error_log($log);
    }
  }
}

function ventipay_add_gateway_class($methods)
{
  $methods[] = 'WC_Gateway_VentiPay';
  return $methods;
}

add_filter('woocommerce_payment_gateways', 'ventipay_add_gateway_class');

add_action('plugins_loaded', 'ventipay_init_gateway_class');

function ventipay_init_gateway_class()
{
  class WC_Gateway_VentiPay extends WC_Payment_Gateway
  {
    /**
     * API Settings
     */
    const API_ENDPOINT = 'https://api.ventipay.com/v1';

    /**
     * Constructor
     */
    public function __construct()
    {
      $this->id = 'ventipay';
      $this->icon = '';
      $this->has_fields = false;
      $this->method_title = __('VentiPay', 'ventipay');
      $this->method_description = __(
        'Acepta pagos y suscripciones con tarjetas',
        'ventipay'
      );
      $this->supports = ['products'];

      $this->init_form_fields();
      $this->init_settings();

      $this->title = $this->get_option('title');
      $this->description = $this->get_option('description');
      $this->enabled = $this->get_option('enabled');
      $this->testmode = 'yes' === $this->get_option('testmode');
      $this->api_key = $this->testmode ? $this->get_option('test_api_key') : $this->get_option('live_api_key');

      add_action(
        'woocommerce_update_options_payment_gateways_' . $this->id,
        [$this, 'process_admin_options']
      );

      add_action(
        'woocommerce_api_ventipay',
        [
          $this,
          'ventipay_callback_notification',
        ]
      );
    }

    public function init_form_fields()
    {
      $this->form_fields = [
        'enabled' => [
          'title' => __('Habilita/Deshabilita', 'ventipay'),
          'label' => __('Habilitar pagos con VentiPay', 'ventipay'),
          'type' => 'checkbox',
          'description' => '',
          'default' => 'no',
        ],
        'title' => [
          'title' => __('Título', 'ventipay'),
          'type' => 'text',
          'description' => __(
            'Título a mostrar a los clientes al elegir el método de pago.',
            'ventipay'
          ),
          'default' => __(
            'VentiPay',
            'ventipay'
          ),
          'desc_tip' => true,
        ],
        'description' => [
          'title' => __('Descripción', 'ventipay'),
          'type' => 'textarea',
          'description' => __(
            'Descripción a mostrar a los clientes al elegir el método de pago.',
            'ventipay'
          ),
          'default' => __(
            'Paga con tu tarjeta de Crédito, Débito o Prepago.',
            'ventipay'
          ),
        ],
        'testmode' => [
          'title' => __('Modo Pruebas', 'ventipay'),
          'label' => __('Habilita el Modo Pruebas', 'ventipay'),
          'type' => 'checkbox',
          'description' => __(
            'Si habilitas esta opción podrás hacer pruebas de integración sin aceptar pagos reales.',
            'ventipay'
          ),
          'default' => 'yes',
          'desc_tip' => true,
        ],
        'test_api_key' => [
          'title' => __('API Key Modo Pruebas', 'ventipay'),
          'type' => 'password',
          'placeholder' => 'key_test_....',
        ],
        'live_api_key' => [
          'title' => __('API Key Modo Live', 'ventipay'),
          'type' => 'password',
          'placeholder' => 'key_live_....',
        ],
      ];
    }

    public function get_supported_currency()
    {
      return ['CLP'];
    }

    public function payment_scripts() {}

    public function process_payment($order_id)
    {
      global $woocommerce;

      try {
        /**
         * Order data
         */
        $order = new WC_Order($order_id);
        $amount = (int)number_format($order->get_total(), 0, ',', '');
        $currency = $order->get_currency();
        $return_url = add_query_arg(
          'order_id',
          $order_id,
          add_query_arg('wc-api', 'ventipay', home_url('/'))
        );

        /**
         * We attempt to create the payment
         */
        $createPayment = wp_remote_post(
          self::API_ENDPOINT . '/payments',
          [
            'headers' => [
              'Authorization' => 'Basic ' . base64_encode($this->api_key . ':'),
            ],
            'body' => [
              'amount' => $amount,
              'currency' => $currency,
              'capture' => false,
              'cancel_url' => $return_url,
              'cancel_url_method' => 'post',
              'success_url' => $return_url,
              'success_url_method' => 'post',
              'metadata' => [
                'wp_order_id' => $order_id,
              ]
            ],
          ]
        );

        if (!is_wp_error($createPayment)) {
          /**
           * Created payment data
           */
          $newPayment = json_decode(wp_remote_retrieve_body($createPayment));

          /**
           * Check if it's a valid payment: the right object type with the proper status
           */
          if (isset($newPayment)
            && !empty($newPayment->object)
            && 'payment' === $newPayment->object
            && !empty($newPayment->id)
            && !empty($newPayment->status)
            && 'requires_authorization' === $newPayment->status
            && !empty($newPayment->url))
          {
            /**
             * We add the payment ID to the order metadata
             */
            $order->add_meta_data(
              'ventipay_payment_id',
              $newPayment->id,
              true
            );

            /**
             * We change the status to pending while we wait for the customer to comeback
             */
            $order->update_status(
              'pending',
              __(
                'Esperando a que el cliente autorize el pago',
                'ventipay'
              )
            );

            /**
             * Redirect the customer to VentiPay's form
             */
            return [
              'result' => 'success',
              'redirect' => esc_url_raw($newPayment->url),
            ];
          }
        }

        /**
         * Something failed, we show the user an error message
         * This means either the payment wasn't properly created or it was but it's invalid
         */
        wc_add_notice(
          __(
            'Ocurrió un error al procesar tu pago. Por favor intenta nuevamente. Si el error persiste, por favor contáctanos.',
            'ventipay'
          ),
          'error'
        );
        return;
      } catch (Exception $e) {
        /**
         * Something failed, we show the user an error message
         */
        wc_add_notice(
          __(
            'Ocurrió un error al procesar tu pago. Por favor intenta nuevamente. Si el error persiste, por favor contáctanos.',
            'ventipay'
          ),
          'error'
        );
        return ['result' => 'fail', 'redirect' => ''];
      }
    }

    public function ventipay_callback_notification()
    {
      $order = new WC_Order($_GET['order_id']);

      /**
       * Check if it's a valid order
       */
      if (!$order->get_id()) {
        wc_add_notice(
          __(
            'Ocurrió un error al procesar tu pago. Por favor intenta nuevamente. Si el error persiste, por favor contáctanos.',
            'ventipay'
          ),
          'error'
        );
        return;
      }

      /**
       * The order is already paid so we redirect the user to a success page
       */
      if ($order->is_paid()) {
        return wp_redirect($order->get_checkout_order_received_url());
      }

      /**
       * The order is valid and it's ready to be paid
       */
      if ($order->needs_payment()) {
        /**
         * Stored payment ID
         */
        $meta_payment_id = $order->get_meta('ventipay_payment_id');

        /**
         * Recieved payment ID
         */
        $posted_payment_id = isset($_POST) && !empty($_POST['id']) ? $_POST['id'] : null;

        /**
         * We check if the stored ID looks like a valid one and both (stored and received) are equal
         * This is a simple check, however it should be enough to control MITM tampering.
         * Eventually we could check for a valid signature just like regular webhooks
         */
        if (!empty($meta_payment_id)
          && substr($meta_payment_id, 0, 4) === 'pay_'
          && !empty($posted_payment_id)
          && $meta_payment_id === $posted_payment_id)
        {
          /**
           * We attempt to capture the payment.
           * If it wasn't authorized or it's already captured, the API will sent an error.
           */
          $capturePayment = wp_remote_post(
            self::API_ENDPOINT . '/payments/' . $meta_payment_id . '/capture',
            [
              'headers' => [
                'Authorization' => 'Basic ' . base64_encode($this->api_key . ':'),
              ],
            ]
          );

          if (!is_wp_error($capturePayment)) {
            /**
             * Captured payment data
             */
            $capturedPayment = json_decode(wp_remote_retrieve_body($capturePayment));

            /**
             * Check if the payment was properly captured
             */
            if (isset($capturedPayment)
              && !empty($capturedPayment->status)
              && !$capturedPayment->refunded
              && !$capturedPayment->disputed
              && 'succeeded' === $capturedPayment->status)
            {
              $order->payment_complete();
              return wp_redirect(
                $order->get_checkout_order_received_url()
              );
            }
          }
          wc_add_notice(
            __(
              'Ocurrió un error al procesar tu pago. Por favor intenta nuevamente. Si el error persiste, por favor contáctanos.',
              'ventipay'
            ),
            'error'
          );
          return;
        }
        $order->update_status('failed');
        wc_add_notice(
          __(
            'Ocurrió un error al procesar tu pago. Por favor intenta nuevamente. Si el error persiste, por favor contáctanos.',
            'ventipay'
          ),
          'error'
        );
        return;
      }
      wc_add_notice(
        __(
          'Ocurrió un error al procesar tu pago. Por favor intenta nuevamente. Si el error persiste, por favor contáctanos.',
          'ventipay'
        ),
        'error'
      );
      return;
    }
  }
}
?>