<?php
/**
 * Plugin Name: MORKVA Vchasno Kasa Integration
 * Plugin URI: https://kasa.vchasno.com.ua/
 * Description: Інтеграція WooCommerce з пРРО Вчасно.Каса
 * Version: 0.7.1
 * Tested up to: 6.3
 * Requires at least: 5.2
 * Requires PHP: 7.1
 * Author: MORKVA
 * Author URI: https://morkva.co.ua
 * Text Domain: mrkv-vchasno-kasa
 * Domain Path: /languages
 * WC requires at least: 5.4.0
 * WC tested up to: 7.1.0
 */

/**
 * 1. Activation code (Check first register)
 * 2. Setup all part of plugin
 * */

// -----------------------------------------------------------------------//
// -------------------------------1.ACTIVATION----------------------------//
// -----------------------------------------------------------------------//

# This prevents a public user from directly accessing your .php files
if (! defined('ABSPATH')) {
    exit;
}

# Include all needed classes for activate
require_once 'classes/mrkv-activate.php'; 

new MRKV_ACTIVATION(__FILE__);

// -----------------------------------------------------------------------//
// -------------------------------2.SETUP---------------------------------//
// -----------------------------------------------------------------------//

# Include all needed classes for setup
require_once 'classes/mrkv-setup.php'; 

# Setup Plugin
new MRKV_SETUP(__FILE__);

?>