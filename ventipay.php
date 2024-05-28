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
add_action('before_woocommerce_init', 'ventipay_declare_features_util_compatibility');
add_action('woocommerce_blocks_loaded', 'ventipay_blocks_loaded');

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

function ventipay_declare_features_util_compatibility()
{
  if (!class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
    return;
  }

  \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
  \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
}

function ventipay_blocks_loaded()
{
  if (!class_exists('Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
    return;
  }

  require_once dirname( __FILE__ ) . '/blocks/wc-gateway-ventipay.php';

  add_action(
    'woocommerce_blocks_payment_method_type_registration',
    function( Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry ) {
      $payment_method_registry->register(new WC_Block_Gateway_VentiPay());
    }
  );
}
?>