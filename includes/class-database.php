<?php
/**
 * Database schema.
 *
 * @package WPDecisionLog
 */

namespace WPDecisionLog;

/**
 * Database service.
 */
class Database {

	/**
	 * Resolve a site-prefixed plugin table.
	 *
	 * @param string $name Table, setting or theme label.
	 *
	 * @return string
	 * @throws \InvalidArgumentException When a table name is not allowlisted.
	 */
	public static function table( $name ) {
		global $wpdb;
		if ( ! in_array( $name, array( 'decisions', 'dependencies', 'component_links', 'activity', 'comments', 'notifications' ), true ) ) {
			throw new \InvalidArgumentException( 'Unknown DecisionLog table.' );
		}
		return $wpdb->prefix . 'decisionlog_' . $name;
	}
	/**
	 * Create or upgrade the schema while preserving records.
	 *
	 * @return true|\WP_Error
	 */
	public static function migrate() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$collate = $wpdb->get_charset_collate();
		$tables  = array(
			'decisions'       => 'id bigint unsigned NOT NULL AUTO_INCREMENT,
    uuid char(36) NOT NULL,
    title varchar(200) NOT NULL,
    description longtext NOT NULL,
    reason longtext NOT NULL,
    reversal_risk longtext NOT NULL,
    component varchar(200) NOT NULL,
    component_type varchar(40) NOT NULL,
    author_id bigint unsigned NOT NULL,
    created_at datetime NOT NULL,
    updated_at datetime NOT NULL,
    risk varchar(16) NOT NULL,
    status varchar(24) NOT NULL,
    review_date date DEFAULT NULL,
    code_snippet longtext NOT NULL,
    reference_url text NOT NULL,
    notes longtext NOT NULL,
    PRIMARY KEY  (id),
    UNIQUE KEY uuid (uuid),
    KEY status_review (status,review_date),
    KEY component_type (component_type),
    KEY updated_at (updated_at)',
			'dependencies'    => 'id bigint unsigned NOT NULL AUTO_INCREMENT,
    decision_id bigint unsigned NOT NULL,
    depends_on bigint unsigned NOT NULL,
    PRIMARY KEY  (id),
    UNIQUE KEY edge (decision_id,depends_on),
    KEY target (depends_on)',
			'component_links' => 'id bigint unsigned NOT NULL AUTO_INCREMENT,
    decision_id bigint unsigned NOT NULL,
    kind varchar(40) NOT NULL,
    identifier varchar(191) NOT NULL,
    PRIMARY KEY  (id),
    UNIQUE KEY association (decision_id,kind,identifier),
    KEY component (kind,identifier)',
			'activity'        => 'id bigint unsigned NOT NULL AUTO_INCREMENT,
    created_at datetime NOT NULL,
    actor_id bigint unsigned NOT NULL,
    event_type varchar(64) NOT NULL,
    decision_id bigint unsigned DEFAULT NULL,
    details text NOT NULL,
    PRIMARY KEY  (id),
    KEY timeline (created_at,id),
    KEY decision (decision_id),
    KEY event_type (event_type)',
			'comments'        => 'id bigint unsigned NOT NULL AUTO_INCREMENT,
    decision_id bigint unsigned NOT NULL,
    author_id bigint unsigned NOT NULL,
    created_at datetime NOT NULL,
    body text NOT NULL,
    PRIMARY KEY  (id),
    KEY decision (decision_id,id)',
			'notifications'   => 'id bigint unsigned NOT NULL AUTO_INCREMENT,
    decision_id bigint unsigned NOT NULL,
    review_date date NOT NULL,
    created_at datetime NOT NULL,
    PRIMARY KEY  (id),
    UNIQUE KEY reminder (decision_id,review_date)',
		);
		foreach ( $tables as $name => $schema ) {
			dbDelta(
				'CREATE TABLE ' .
					self::table( $name ) .
					" ($schema) $collate ENGINE=InnoDB;",
			);
		}
		foreach ( array_keys( $tables ) as $name ) {
			$table = self::table( $name );
			if (
				$wpdb->get_var(
					$wpdb->prepare(
						'SHOW TABLES LIKE %s',
						$wpdb->esc_like( $table ),
					),
				) !== $table
			) {
				return new \WP_Error(
					'schema_failed',
					__(
						// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange -- Uninstall DDL is gated by the site administrator explicit data-deletion setting.
						'Unable to create DecisionLog tables.',
						'wp-decisionlog',
					),
				);
			}
		}
		update_option( 'wpdl_schema_version', WPDL_VERSION );
		return true;
	}
}
