<?php
/**
 * Plugin Name: WP DecisionLog
 * Description: Record development decisions, monitor supported changes and protect important configuration context.
 * Version: 1.0.0
 * Requires at least: 6.4
 * Requires PHP: 8.1
 * Author: Shazeel Ahmed
 * License: GPL-2.0-or-later
 * Text Domain: wp-decisionlog
 *
 * @package WPDecisionLog
 */

namespace WPDecisionLog;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}
define( 'WPDL_VERSION', '1.0.0' );
define( 'WPDL_FILE', __FILE__ );
foreach (
	array(
		'database',
		'permissions',
		'audit-logger',
		'decision-manager',
		'export-manager',
		'review-scheduler',
		'change-monitor',
		'warning-manager',
		'rest-api',
		'activator',
		'deactivator',
		'plugin',
	)
	as $wpdl_class
) {
	require_once __DIR__ . '/includes/class-' . $wpdl_class . '.php';
}
require_once __DIR__ . '/admin/class-admin.php';
foreach ( array( 'wordpress', 'elementor', 'woocommerce' ) as $wpdl_integration ) {
	require_once __DIR__ .
		'/integrations/class-' .
		$wpdl_integration .
		'-integration.php';
}
register_activation_hook( __FILE__, array( Activator::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( Deactivator::class, 'deactivate' ) );
add_action( 'plugins_loaded', array( Plugin::class, 'init' ) );
