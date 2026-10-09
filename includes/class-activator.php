<?php
/**
 * Activation.
 *
 * @package WPDecisionLog
 */

namespace WPDecisionLog;

/**
 * Activator service.
 */
class Activator {

	/**
	 * Prepare site-local schema, capabilities and scheduled reviews.
	 *
	 * @param bool $network_wide Whether network activation was requested.
	 *
	 * @return void
	 */
	public static function activate( $network_wide = false ) {
		if ( $network_wide ) {
			wp_die(
				esc_html__(
					'Activate WP DecisionLog individually on each site; network activation is not supported.',
					'wp-decisionlog',
				),
			);
		}
		$result = Database::migrate();
		if ( is_wp_error( $result ) ) {
			wp_die( esc_html( $result->get_error_message() ) );
		}
		Permissions::grant();
		add_option(
			'wpdl_settings',
			array(
				'monitoring'          => true,
				'reminders'           => true,
				'email_reminders'     => false,
				'delete_on_uninstall' => false,
			)
		);
		if ( ! wp_next_scheduled( 'wpdl_daily_reviews' ) ) {
			wp_schedule_event( time() + 60, 'daily', 'wpdl_daily_reviews' );
		}
	}
}
