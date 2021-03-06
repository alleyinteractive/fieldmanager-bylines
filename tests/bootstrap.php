<?php
/**
 * PHPUnit bootstrap file
 *
 * @package Fieldmanager_Bylines
 */

$_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $_tests_dir ) {
	$_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
	echo "Could not find $_tests_dir/includes/functions.php, have you run bin/install-wp-tests.sh ?" . PHP_EOL; // phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped
	exit( 1 );
}

// Give access to tests_add_filter() function.
require_once $_tests_dir . '/includes/functions.php';

/**
 * Manually load the plugin being tested.
 */
function _manually_load_plugin() {
	$_fm_dir = getenv( 'FM_DIR' );
	if ( empty( $_fm_dir ) ) {
		$_fm_dir = dirname( __FILE__ ) . '/../../wordpress-fieldmanager';
	}
	require $_fm_dir . '/fieldmanager.php';

	require dirname( dirname( __FILE__ ) ) . '/fieldmanager-bylines.php';
}
tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

// Start up the WP testing environment.
require $_tests_dir . '/includes/bootstrap.php';
