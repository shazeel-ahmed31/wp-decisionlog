<?php
/**
 * Metadata-only audit log.
 *
 * @package WPDecisionLog
 */

namespace WPDecisionLog;

/**
 * Audit Logger service.
 */
class Audit_Logger {

	/**
	 * Append metadata-only audit history.
	 *
	 * @param string $event Audit event type.
	 * @param int    $id Decision identifier.
	 * @param array  $details Allowlisted event metadata.
	 *
	 * @return int|false
	 */
	public static function log( $event, $id = null, $details = array() ) {
		global $wpdb;
		// Callers cannot accidentally persist option values, snippets, reasons or imported text.
		$safe = array_intersect_key(
			$details,
			array_flip(
				array(
					'kind',
					'identifier',
					'changed_fields',
					'depends_on',
					'source',
					'outcome',
				)
			),
		);
		return $wpdb->insert(
			Database::table( 'activity' ),
			array(
				'created_at'  => current_time( 'mysql', true ),
				'actor_id'    => get_current_user_id(),
				'event_type'  => sanitize_key( $event ),
				'decision_id' => $id,
				'details'     => wp_json_encode( $safe ),
			)
		);
	}
	/**
	 * Read a filtered and paginated collection.
	 *
	 * @param array $args Collection filters and pagination.
	 *
	 * @return array
	 */
	public static function listing( $args ) {
		global $wpdb;
		$where  = '1=1';
		$values = array();
		if ( ! empty( $args['event_type'] ) ) {
			$where   .= ' AND event_type=%s';
			$values[] = sanitize_key( $args['event_type'] );
		}
		if ( ! empty( $args['decision_id'] ) ) {
			$where   .= ' AND decision_id=%d';
			$values[] = absint( $args['decision_id'] );
		}
		$table = Database::table( 'activity' );
		$total = (int) $wpdb->get_var(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- The clause contains only fixed allowlisted SQL; every value and table identifier is prepared below.
				"SELECT COUNT(*) FROM %i WHERE $where",
				array_merge( array( $table ), $values ),
			),
		);
		$size = min( 100, max( 1, absint( $args['per_page'] ?? 20 ) ) );
		$page = max( 1, absint( $args['page'] ?? 1 ) );
		$rows = $wpdb->get_results(
			// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- The merged array supplies identifier, filter values, limit and offset; PHPCS cannot count runtime arrays.
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- The clause contains only fixed allowlisted SQL; every value and table identifier is prepared below.
				"SELECT * FROM %i WHERE $where ORDER BY id DESC LIMIT %d OFFSET %d",
				array_merge( array( $table ), $values, array( $size, ( $page - 1 ) * $size ) ),
			),
			ARRAY_A,
		);
		foreach ( $rows as &$row ) {
			$row['details'] = json_decode( $row['details'], true );
		}
		return array(
			'items'    => $rows,
			'total'    => $total,
			'page'     => $page,
			'per_page' => $size,
		);
	}
}
