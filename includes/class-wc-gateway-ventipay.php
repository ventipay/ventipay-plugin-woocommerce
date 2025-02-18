<?php
if (!defined('ABSPATH')) {
  exit;
}

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
    $this->icon = 'https://ventipay.com/assets/apps/woocommerce/plugin-woocommerce-icon-checkout.png';
    $this->has_fields = false;
    $this->method_title = __('VentiPay', 'ventipay');
    $this->method_description = __(
      'Paga como quieras',
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
    $this->ipn_secret = $this->testmode ? $this->get_option('test_apn_secret') : $this->get_option('live_apn_secret');

    /**
     * Hooks
     */
    add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
    add_action('woocommerce_api_ventipay', [$this, 'ventipay_process_ipn']);
    add_action('woocommerce_thankyou_order_received_text', [$this, 'ventipay_thankyou_text'], 10, 2);
  }

  public function init_form_fields()
  {
    $this->form_fields = [
      'enabled' => [
        'title' => __('Habilitar/Deshabilitar', 'ventipay'),
        'label' => __('Aceptar pagos con VentiPay', 'ventipay'),
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
          'Paga en cuotas con débito y crédito, y transferencia bancaria.',
          'ventipay'
        ),
      ],
      'testmode' => [
        'title' => __('Habilitar Modo Pruebas', 'ventipay'),
        'label' => __('Atención: Si habilitas esta opción no se realizarán cargos reales a los clientes. Habilita este modo solo si necesitas realizar pruebas de integración.', 'ventipay'),
        'type' => 'checkbox',
        'default' => 'yes',
      ],
      'api_credentials_note' => [
        'title' => __('Credenciales API', 'ventipay'),
        'type' => 'title',
        'description' => __('Podrás encontrar tus API Keys en la sección Desarrolladores > API del Dashboard', 'ventipay'),
      ],
      'test_api_key' => [
        'title' => __('API Key (llave secreta) modo test', 'ventipay'),
        'type' => 'text',
        'placeholder' => 'key_test_...',
      ],
      'live_api_key' => [
        'title' => __('API Key (llave secreta) modo live', 'ventipay'),
        'type' => 'text',
        'placeholder' => 'key_live_...',
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
      $order = wc_get_order($order_id);
      $amount = (int) number_format($order->get_total(), 0, ',', '');
      $currency = $order->get_currency();
      $notification_url = add_query_arg(
        'order_id',
        $order_id,
        home_url('/wc-api/ventipay/')
      );
      $return_url = $this->get_return_url($order);
      $cancel_url = $order->get_cancel_order_url();

      /**
       * We attempt to create the payment
       */
      $create_payment = wp_remote_post(
        self::API_ENDPOINT . '/checkouts',
        [
          'headers' => [
            'Authorization' => 'Basic ' . base64_encode($this->api_key . ':'),
            'Content-Type' => 'application/json',
          ],
          'timeout' => 45,
          'data_format' => 'body',
          'body' => wp_json_encode([
            'authorize' => true,
            'currency' => $currency,
            'cancel_url' => $cancel_url,
            'cancel_url_method' => 'post',
            'items' => array(
              [
                'unit_price' => $amount,
                'quantity' => 1
              ],
            ),
            'notification_url' => $notification_url,
            'notification_events' => ['checkout.paid'],
            'success_url' => $return_url,
            'success_url_method' => 'post',
            'metadata' => [
              'wp_order_id' => $order_id,
            ],
          ])
        ]
      );

      if (!is_wp_error($create_payment)) {
        /**
         * Created payment data
         */
        $new_payment = json_decode(wp_remote_retrieve_body($create_payment));

        /**
         * Check if it's a valid payment: the right object type with the proper status
         */
        if (isset($new_payment)
          && !empty($new_payment->object)
          && 'checkout' === $new_payment->object
          && !empty($new_payment->id)
          && !empty($new_payment->status)
          && 'unpaid' === $new_payment->status
          && !empty($new_payment->url))
        {
          /**
           * We add the payment ID to the order metadata
           */
          $order->add_meta_data(
            'ventipay_checkout_id',
            $new_payment->id,
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
            'redirect' => esc_url_raw($new_payment->url),
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

  public function ventipay_process_ipn()
  {
    $order = wc_get_order($_GET['order_id']);

    /**
     * Check if it's a valid order
     */
    if (!isset($order) || !$order || !is_a($order, 'WC_Order') || !$order->get_id()) {
      header('HTTP/1.1 400 Bad Request (Order ID Not Found)');
      return;
    }

    /**
     * The order is already paid so we redirect the user to a success page
     */
    if ($order->is_paid()) {
      header('HTTP/1.1 200 OK (Order Is Paid)');
      return;
    }

    /**
     * The order is valid and it's ready to be paid
     */
    if ($order->needs_payment()) {
      /**
       * Stored payment ID
       */
      $meta_payment_id = $order->get_meta('ventipay_checkout_id');

      /**
       * Recieved payment ID
       */
      $posted_body = json_decode(file_get_contents('php://input'));
      $posted_payment_id = isset($posted_body) && !empty($posted_body->data) && !empty($posted_body->data->id) ? $posted_body->data->id : null;

      /**
       * We check if the stored ID looks like a valid one and both (stored and received) are equal
       * This is a simple check, however it should be enough to control MITM tampering.
       * Eventually we could check for a valid signature just like regular webhooks
       */
      if (!empty($meta_payment_id)
        && substr($meta_payment_id, 0, 4) === 'chk_'
        && !empty($posted_payment_id)
        && $meta_payment_id === $posted_payment_id)
      {
        /**
         * We check the checkout status.
         */
        $capture_payment = wp_remote_get(
          self::API_ENDPOINT . '/checkouts/' . $meta_payment_id,
          [
            'headers' => [
              'Authorization' => 'Basic ' . base64_encode($this->api_key . ':'),
              'Content-Type' => 'application/json',
            ],
            'timeout' => 120,
          ]
        );

        if (!is_wp_error($capture_payment)) {
          /**
           * Captured payment data
           */
          $captured_payment = json_decode(wp_remote_retrieve_body($capture_payment));

          /**
           * Check if the payment was properly captured/paid
           */
          if (isset($captured_payment)
            && !empty($captured_payment->status)
            && !$captured_payment->refunded
            && !$captured_payment->disputed
            && 'paid' === $captured_payment->status)
          {
            $order->payment_complete();
            header('HTTP/1.1 200 OK (Payment Completed)');
            return;
          }
        }
        header('HTTP/1.1 400 Bad Request (Unable To Capture Payment)');
        return;
      }
      header('HTTP/1.1 400 Bad Request (Posted Payment ID Mismatch)');
      return;
    }
    header('HTTP/1.1 400 Bad Request (Order Not Ready To Be Paid)');
    return;
  }

  public function ventipay_thankyou_text($var, $order_id)
  {
    $order = wc_get_order($order_id);
    if (isset($order) && $order && is_a($order, 'WC_Order') && ($order->get_id())) {
      $meta_payment_id = $order->get_meta('ventipay_checkout_id');
      if (!empty($meta_payment_id)) {
        if ('pending' === $order->get_status()) {
          ?>
            <script>
              setTimeout(() => {
                window.location.reload(true)
              }, 10000)
            </script>
          <?php

          $message = [
            '<div class="woocommerce-info">',
            '<span>',
            __(
              'Por favor espera mientras validamos tu pago...',
              'ventipay'
            ),
            '</span>',
            '</div>',
          ];
          return implode("\n", $message);
        }
        if ($order->is_paid()) {
          $message = [
            '<div class="woocommerce-message">',
            '<span>',
            __(
              '¡Tu pago está listo!',
              'ventipay'
            ),
            '</span>',
            '</div>',
          ];
          return implode("\n", $message);
        }
      }
    }
  }
}
?>