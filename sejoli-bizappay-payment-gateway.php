<?php
/**
 *
 * @link              https://ridwan-arifandi.com
 * @since             1.0.0
 * @package           Sejoli
 *
 * @wordpress-plugin
 * Plugin Name:       Sejoli - BIZAPPAY Payment Gateway
 * Plugin URI:        https://sejoli.co.id
 * Description:       Integrate Sejoli Premium WordPress Membership Plugin with BIZAPPAY Payment Gateway.
 * Version:           1.0.0
 * Requires PHP: 	  7.4.1
 * Author:            Sejoli
 * Author URI:        https://sejoli.co.id
 * Text Domain:       sejoli-bizappay
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {

	die;

}

// Register payment gateway
add_filter('sejoli/payment/available-libraries', function( array $libraries ){

    require_once ( plugin_dir_path( __FILE__ ) . '/class-bizappay-payment-gateway.php' );

    $libraries['bizappay'] = new \SejoliBizappay();

    return $libraries;

});

add_action( 'plugins_loaded', 'plugin_init' ); 
function plugin_init() {

    load_plugin_textdomain( 'sejoli-bizappay', false, dirname(plugin_basename(__FILE__)).'/languages/' );

}
