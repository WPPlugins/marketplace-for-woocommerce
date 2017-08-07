<?php
/*
Plugin Name: Marketplace for WooCommerce
Description: Let users sell on your store
Version: 1.0.0
Author: Algoritmika Ltd
Author URI: http://algoritmika.com
Copyright: © 2017 Algoritmika Ltd.
License: GNU General Public License v3.0
License URI: http://www.gnu.org/licenses/gpl-3.0.html
Text Domain: marketplace-for-woocommerce
Domain Path: /languages
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

if ( ! function_exists( 'alg_marketplace_for_wc' ) ) {
	/**
	 * Returns the main instance of Alg_MP_WC_Core to prevent the need to use globals.
	 *
	 * @version 1.0.0
	 * @since   1.0.0
	 * @return  Alg_MPWC_Core
	 */
	function alg_marketplace_for_wc() {
		$marketplace = Alg_MPWC_Core::get_instance();
		$marketplace->set_args( array(
			'plugin_file_path' => __FILE__,
			'action_links'     => array(
				array(
					'url'  => admin_url( 'admin.php?page=wc-settings&tab=alg_mpwc' ),
					'text' => __( 'Settings', 'woocommerce' ),
				),
			),
			'translation'      => array(
				'text_domain' => 'marketplace-for-woocommerce',
			),
		) );

		return $marketplace;
	}
}

// Starts the plugin
add_action( 'plugins_loaded', 'alg_mpwc_start_plugin' );
if ( ! function_exists( 'alg_mpwc_start_plugin' ) ) {
	/**
	 * Starts the plugin
	 *
	 * @version 1.0.0
	 * @since   1.0.0
	 */
	function alg_mpwc_start_plugin() {

		// Includes composer dependencies and autoloads classes
		require __DIR__ . '/vendor/autoload.php';

		// Initializes the plugin
		$marketplace = alg_marketplace_for_wc();
		$marketplace->init();
	}
}

if ( ! function_exists( 'alg_mpwc_register_hooks' ) ) {
	/**
	 * Handles activation, installation and uninstall hooks
	 *
	 * @version 1.0.0
	 * @since   1.0.0
	 */
	function alg_mpwc_register_hooks() {

		// Includes composer dependencies and autoloads classes
		require __DIR__ . '/vendor/autoload.php';

		// When plugin is enabled
		register_activation_hook( __FILE__, array( 'Alg_MPWC_Core', 'on_plugin_activation' ) );
	}
}

// Handles activation, installation and uninstall hooks
alg_mpwc_register_hooks();