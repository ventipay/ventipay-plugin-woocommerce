<?php
use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

final class WC_Block_Gateway_VentiPay extends AbstractPaymentMethodType {
  private $gateway;
  protected $name = 'ventipay';

  public function initialize() {
    $this->settings = get_option('woocommerce_ventipay_settings', []);
    $this->gateway = new WC_Gateway_VentiPay();
  }
  public function is_active() {
    return $this->gateway->enabled === 'yes';
  }
  public function get_payment_method_script_handles() {
    wp_register_script(
      'ventipay-blocks-integration',
      plugin_dir_url(__FILE__) . '../assets/js/ventipay_checkout.js',
      [
          'wc-blocks-registry',
          'wc-settings',
          'wp-element',
          'wp-html-entities'
      ],
      null,
      true
    );

    return ['ventipay-blocks-integration'];
  }
  public function get_payment_method_data() {
    return [
      'title' => $this->gateway->title,
      'description' => $this->gateway->description,
      'icon' => $this->gateway->icon
    ];
  }
}
