<?php
/**
 * Plugin Name: VentiPay
 * Plugin URI: https://docs.ventipay.com/
 * Description: Acepta pagos en cuotas sin intereses y pagos con tarjeta.
 * Author: VentiPay
 * Author URI: https://www.ventipay.com/
 * Version: 1.2.0
 * Requires at least: 5.7
 * Tested up to: 5.8
 * WC requires at least: 5.0
 * WC tested up to: 5.3
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
  $methods[] = WC_Gateway_VentiPay::class;
  $methods[] = WC_Gateway_VentiPay_BNPL::class;
  return $methods;
}

/**
 * Hooks
 */
add_filter('woocommerce_after_add_to_cart_button' , 'venti_show_bnpl_button_in_product_page', 5);
add_filter('woocommerce_payment_gateways', 'ventipay_add_gateway_class');
add_action('plugins_loaded', 'ventipay_init_gateway_class');
add_action('wp_enqueue_scripts', 'ventipay_setup_scripts');

/**
 * Add BNPL button in product page
 */
function venti_show_bnpl_button_in_product_page()
{
  global $product;
  $payment_gateways_obj = new WC_Payment_Gateways();
  $enabled_payment_gateways = $payment_gateways_obj->payment_gateways();
  if (isset($enabled_payment_gateways)
    && isset($enabled_payment_gateways['ventipay_bnpl'])
    && $enabled_payment_gateways['ventipay_bnpl']->enabled === 'yes'
  ) {
    $venti_min_installment_amount = ceil((int) number_format($product->get_price(), 0, ',', '') / 4);
    if ($venti_min_installment_amount > 0) {
      echo '<div class="ventipay-bnpl-product-button-container">';
      echo '<div class="ventipay-bnpl-product-button-image-container"><img src="https://pay.ventipay.com/assets/apps/woocommerce/plugin-woocommerce-icon-bnpl-button-product-page.svg" alt="Venti" border="0" /></div>';
      echo '<div class="ventipay-bnpl-product-button-text-container">Paga en cuotas con débito desde ' . wc_price($venti_min_installment_amount) . ' al mes</div>';
      echo '</div>';
    }
  }
}

/**
 * Load scripts, styles
 */
function ventipay_setup_scripts()
{
  wp_register_style('ventipay', plugin_dir_url(__FILE__) . 'assets/css/ventipay-style.css');
  wp_enqueue_style('ventipay');
}

/**
 * Load Gateway classes
 */
function ventipay_init_gateway_class()
{
  require_once dirname( __FILE__ ) . '/includes/class-wc-gateway-ventipay.php';
  require_once dirname( __FILE__ ) . '/includes/class-wc-gateway-ventipay-bnpl.php';
}
?>