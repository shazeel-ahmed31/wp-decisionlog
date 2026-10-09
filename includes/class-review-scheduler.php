<?php
/**
 * Deduplicated daily review reminders.
 *
 * @package WPDecisionLog
 */

namespace WPDecisionLog;

/**
 * Review Scheduler service.
 */
class Review_Scheduler {

	/**
	 * Register the service hooks.
	 */
	public function __construct() {
		add_action( 'wpdl_daily_reviews', array( self::class, 'run' ) );
	}
	/**
	 * Mark due decisions and deduplicate reminder delivery.
	 *
	 * @return void
	 */
	public static function run() {
		global $wpdb;
		if ( ! Plugin::settings()['reminders'] ) {
			return;
		}
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id,review_date,status FROM %i WHERE status IN ('active','needs-review') AND review_date<=%s",
				Database::table( 'decisions' ),
				current_time( 'Y-m-d' ),
			),
			ARRAY_A,
		);
		$count = 0;
		foreach ( $rows as $row ) {
			if ( 'active' === $row['status'] ) {
				Decision_Manager::save(
					array( 'status' => 'needs-review' ),
					$row['id'],
				);
			}
			$exists = $wpdb->get_var(
				$wpdb->prepare(
					'SELECT id FROM %i WHERE decision_id=%d AND review_date=%s',
					Database::table( 'notifications' ),
					$row['id'],
					$row['review_date'],
				),
			);
			if ( $exists ) {
				continue;
			}
			if (
				$wpdb->insert(
					Database::table( 'notifications' ),
					array(
						'decision_id' => $row['id'],
						'review_date' => $row['review_date'],
						'created_at'  => current_time( 'mysql', true ),
					)
				)
			) {
				++$count;
				Audit_Logger::log( 'review_due', $row['id'] );
			}
		}
		if ( $count && Plugin::settings()['email_reminders'] ) {
			$sent = wp_mail(
				get_option( 'admin_email' ),
				__( 'WP DecisionLog: decisions need review', 'wp-decisionlog' ),
				sprintf(
					/* translators: %d: Number of decisions requiring review. */
					__(
						'%d decisions need review. Open WP DecisionLog in your WordPress dashboard. No decision contents are included in this email.',
						'wp-decisionlog',
					),
					$count,
				),
			);
			Audit_Logger::log(
				'review_email',
				null,
				array(
					'outcome' => $sent ? 'sent' : 'failed',
				)
			);
		}
	}
	/**
	 * Complete a review and optionally schedule its next date.
	 *
	 * @param int    $id Decision identifier.
	 * @param string $date Optional next review date.
	 *
	 * @return array|\WP_Error
	 */
	public static function complete( $id, $date ) {
		// Null review date completes the current review without causing daily repeat reminders.
		$result = Decision_Manager::save(
			array(
				'status'      => 'active',
				'review_date' => $date,
			),
			$id,
		);
		if ( ! is_wp_error( $result ) ) {
			Audit_Logger::log( 'review_completed', $id );
		}
		return $result;
	}
}
