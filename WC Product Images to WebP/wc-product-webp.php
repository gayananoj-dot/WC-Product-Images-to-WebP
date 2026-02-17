<?php
/**
 * Plugin Name: WC Product Images to WebP
 * Description: Converts WooCommerce product featured/gallery images to WebP and updates attachments to use .webp files.
 * Version: 1.0.0
 * Author: Your Name
 * License: GPLv2 or later
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) exit;

define('WCPW_VERSION', '1.0.0');
define('WCPW_PLUGIN_FILE', __FILE__);
define('WCPW_PLUGIN_DIR', plugin_dir_path(__FILE__));

require_once WCPW_PLUGIN_DIR . 'includes/class-wcpw-converter.php';
require_once WCPW_PLUGIN_DIR . 'includes/class-wcpw-admin.php';

add_action('plugins_loaded', function () {
  if (!class_exists('WooCommerce')) {
    // Plugin can still run, but the product hooks/UI are Woo-centric.
  }

  \WCPW\Admin::init();
  \WCPW\Converter::init();

  if (defined('WP_CLI') && WP_CLI) {
    require_once WCPW_PLUGIN_DIR . 'includes/class-wcpw-cli.php';
    \WCPW\CLI::init();
  }
});
