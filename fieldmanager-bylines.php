<?php
/**
 * @package Bylines
 * @subpackage Plugin
 * @version 0.2
 */
/*
Plugin Name: Fieldmanager Bylines
Plugin URI: http://github.com/alleyinteractive/fieldmanager-bylines
Description: Allow creation of guest accounts to serve as Authors, Editors, Illustrators etc for a post.
Author: Will Gladstone, David Herrera, Matt Boynes
Version: 0.2
Author URI: http://www.alleyinteractive.com/
*/

require_once( dirname( __FILE__ ) . '/php/class-plugin-dependency.php' );

function fm_bylines_init() {
	require_once( dirname( __FILE__ ) . '/php/class-fm-bylines.php' );
	require_once( dirname( __FILE__ ) . '/php/class-fm-bylines-post.php' );
	require_once( dirname( __FILE__ ) . '/php/class-fm-bylines-author.php' );
	require_once( dirname( __FILE__ ) . '/functions.php' );

	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		require_once( dirname( __FILE__ ) . '/php/class-fm-bylines-cli.php' );
	}

	add_action( 'wp_enqueue_scripts', 'fm_bylines_enqueue_assets' );
}
add_action( 'plugins_loaded', 'fm_bylines_init' );

function fm_bylines_dependency() {

	$fm_bylines_dependency = new Plugin_Dependency( 'Fieldmanager Bylines', 'Fieldmanager', 'https://github.com/alleyinteractive/wordpress-fieldmanager' );
	if ( ! $fm_bylines_dependency->verify() ) {
		// Cease activation
		die( $fm_bylines_dependency->message() );
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
function fm_bylines_enqueue_assets() {
}
