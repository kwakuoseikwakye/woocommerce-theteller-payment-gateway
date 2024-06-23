<?php

/**
 * Plugin Name: WooCommerce PaySwitch Theteller Payment Gateway
 * Plugin URI: https://theteller.net
 * Description: PaySwitch Theteller Payment gateway for WooCommerce
 * Version: 2.0.2
 * Author: Kwaku Osei Kwakye
 * Author URI: https://github.com/kwakuoseikwakye
 * Requires at least: 6.0
 * Tested up to: 6.5
 * WC requires at least: 8.0
 * WC tested up to: 9.0.1
 */

if (!defined('ABSPATH')) {
      exit;
}

if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
      exit("Woocommerce is not defined or active. Kindly active or install Woocommerce.");
}

add_action('plugins_loaded', 'woocommerce_theteller_init', 0);

function woocommerce_theteller_init()
{
      if (!class_exists('WC_Payment_Gateway')) {
            return;
      }

      require_once __DIR__ . '/includes/class-wc-theteller-checkout.php';

      add_filter('woocommerce_payment_gateways', 'woocommerce_add_theteller_gateway', 0);
      add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'woocommerce_add_theteller_settings_link');
}

function woocommerce_add_theteller_gateway($methods)
{
      $methods[] = 'WC_Theteller';
      return $methods;
}

function woocommerce_add_theteller_settings_link($links)
{
      $settings_link = array(
            'settings' => '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout&section=theteller') . '" title="' . __('View PaySwitch Theteller WooCommerce Settings', '') . '"theteller>' . __('Settings', 'theteller') . '</a>',
      );

      return array_merge($settings_link, $links);
}

add_action('before_woocommerce_init', function () {
      if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
      }
});
