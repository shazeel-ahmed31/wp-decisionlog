<?php
/**
 * Deactivation preserves data.
 *
 * @package WPDecisionLog
 */

namespace WPDecisionLog;

/**
 * Deactivator service.
 */
class Deactivator {

	/**
	 * Clear scheduled work without deleting records.
	 *
	 * @return void
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( 'wpdl_daily_reviews' );
	}
}
