<?php
/**
 * Plugin Name: VentiPay
 * Plugin URI: https://docs.ventipay.com/
 * Description: Cobra como quieras
 * Author: VentiPay
 * Author URI: https://www.ventipay.com/
 * Version: 2.1.0
 * Requires at least: 6.5
 * Tested up to: 6.5.2
 * WC requires at least: 8.0.0
 * WC tested up to: 8.8.2
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
  return $methods;
}

/**
 * Hooks
 */
add_filter('woocommerce_payment_gateways', 'ventipay_add_gateway_class');
add_action('plugins_loaded', 'ventipay_init_gateway_class');
add_action('wp_enqueue_scripts', 'ventipay_setup_scripts');

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
}
?>