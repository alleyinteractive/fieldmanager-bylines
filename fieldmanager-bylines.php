<?php
/**
 * Plugin Name: Fieldmanager Bylines
 * Plugin URI: http://github.com/alleyinteractive/fieldmanager-bylines
 * Description: Allow creation of guest accounts to serve as Authors, Editors, Illustrators etc for a post.
 * Author: Will Gladstone, David Herrera, Matt Boynes, Erick Hitter
 * Version: 0.3
 * Author URI: https://www.alleyinteractive.com/
 *
 * @package Fieldmanager_Bylines
 */

// Plugin Dependencies handler.
require_once __DIR__ . '/php/class-fm-bylines-plugin-dependencies.php';

/**
 * Load plugin
 */
function fm_bylines_init() {
	require_once __DIR__ . '/php/class-fm-bylines.php';
	require_once __DIR__ . '/php/class-fm-bylines-post.php';
	require_once __DIR__ . '/php/class-fm-bylines-author.php';
	require_once __DIR__ . '/functions.php';

	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		require_once __DIR__ . '/php/class-fm-bylines-cli.php';
	}

	add_action( 'wp_enqueue_scripts', 'fm_bylines_enqueue_assets' );
}
add_action( 'plugins_loaded', 'fm_bylines_init' );

/**
 * Load plugin dependencies
 */
function fm_bylines_dependency() {
	$fm_bylines_dependency = new FM_Bylines_Plugin_Dependencies( 'Fieldmanager Bylines', 'Fieldmanager', 'https://github.com/alleyinteractive/wordpress-fieldmanager' );
	if ( ! $fm_bylines_dependency->verify() ) {
		wp_die( wp_kses_post( $fm_bylines_dependency->message() ) );
	}
}
register_activation_hook( __FILE__, 'fm_bylines_dependency' );

/**
 * Get the base URL for this plugin.
 *
 * @return string URL pointing to Fieldmanager Plugin top directory.
 */
function fm_bylines_get_baseurl() {
	return plugin_dir_url( __FILE__ );
}

/**
 * Enqueue scripts and styles
 */
function fm_bylines_enqueue_assets() {}
