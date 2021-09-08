<?php
/**
 * Plugin Name: VentiPay
 * Plugin URI: https://docs.ventipay.com/
 * Description: Acepta pagos en cuotas sin intereses y pagos con tarjeta.
 * Author: VentiPay
 * Author URI: https://www.ventipay.com/
 * Version: 1.1.0
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

add_filter('woocommerce_payment_gateways', 'ventipay_add_gateway_class');

add_action('plugins_loaded', 'ventipay_init_gateway_class');

function ventipay_init_gateway_class()
{
  require_once dirname( __FILE__ ) . '/includes/class-wc-gateway-ventipay.php';
  require_once dirname( __FILE__ ) . '/includes/class-wc-gateway-ventipay-bnpl.php';
}
?>