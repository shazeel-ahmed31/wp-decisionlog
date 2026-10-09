<?php
/**
 * Supported change events contain no setting values.
 *
 * @package WPDecisionLog
 */

namespace WPDecisionLog;

/**
 * Change Monitor service.
 */
class Change_Monitor {

	/**
	 * Record a supported change and flag matching active decisions.
	 *
	 * @param string $kind Component association kind.
	 * @param string $identifier Stable component identifier.
	 * @param string $source Public event source.
	 * @param string $outcome Detected change or inferred dependency.
	 *
	 * @return void
	 */
	public static function changed(
		$kind,
		$identifier,
		$source = 'WordPress',
		$outcome = 'detected',
	) {
		if ( ! Plugin::settings()['monitoring'] ) {
			return;
		}
		Audit_Logger::log(
			'configuration_changed',
			null,
			array(
				'kind'       => $kind,
				'identifier' => $identifier,
				'source'     => $source,
				'outcome'    => $outcome,
			)
		);
		foreach ( Decision_Manager::linked( $kind, $identifier ) as $row ) {
			if ( 'active' === $row['status'] ) {
				Decision_Manager::save(
					array( 'status' => 'needs-review' ),
					(int) $row['id'],
				);
			}
		}
	}
}
