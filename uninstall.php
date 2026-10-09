<?php
/**
 * Explicit opt-in uninstall cleanup.
 *
 * @package WPDecisionLog
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit();
}
/**
 * Delete site data only after explicit administrator opt-in.
 *
 * @return void
 */
function wpdl_uninstall_site() {
	global $wpdb;
	$settings = get_option( 'wpdl_settings', array() );
	if ( empty( $settings['delete_on_uninstall'] ) ) {
		return;
	}
	foreach (
		array(
			'dependencies',
			'component_links',
			'notifications',
			'comments',
			'activity',
			'decisions',
		)
		as $table
	) {
		$wpdb->query(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange -- Uninstall DDL is gated by the site administrator explicit data-deletion setting.
				'DROP TABLE IF EXISTS %i',
				$wpdb->prefix . 'decisionlog_' . $table,
			),
		);
	}
	delete_option( 'wpdl_settings' );
	delete_option( 'wpdl_schema_version' );
	$wpdb->query(
		$wpdb->prepare(
			'DELETE FROM %i WHERE meta_key LIKE %s',
			$wpdb->usermeta,
			$wpdb->esc_like( 'wpdl_dismiss_' . get_current_blog_id() . '_' ) . '%',
		),
	);
	$role = get_role( 'administrator' );
	if ( $role ) {
		$role->remove_cap( 'manage_decisionlog' );
	}
	wp_clear_scheduled_hook( 'wpdl_daily_reviews' );
}
// Single-site activation is supported; clean only sites explicitly opting in.
if ( is_multisite() ) {
	foreach (
		get_sites(
			array(
				'fields' => 'ids',
				'number' => 0,
			)
		)
		as $site_id
	) {
		switch_to_blog( $site_id );
		wpdl_uninstall_site();
		restore_current_blog();
	}
} else {
	wpdl_uninstall_site();
}
