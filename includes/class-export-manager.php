<?php
/**
 * Portable exports and bounded imports.
 *
 * @package WPDecisionLog
 */

namespace WPDecisionLog;

/**
 * Export Manager service.
 */
class Export_Manager {

	/**
	 * Produce portable JSON documentation or formula-safe CSV.
	 *
	 * @param string $format Requested export format.
	 *
	 * @return array|\WP_Error
	 */
	public static function export( $format ) {
		global $wpdb;
		if ( ! in_array( $format, array( 'json', 'csv' ), true ) ) {
			return Decision_Manager::error( 'Format must be json or csv.' );
		}
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				'SELECT id FROM %i ORDER BY id',
				Database::table( 'decisions' ),
			),
		);
		if ( count( $ids ) > 5000 ) {
			return Decision_Manager::error(
				'Export limit is 5000 decisions; use a database backup for larger datasets.',
				413,
			);
		}
		$records = array();
		foreach ( $ids as $id ) {
			$record                     = Decision_Manager::get( $id );
			$record['comments']         = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT body,created_at,author_id FROM %i WHERE decision_id=%d ORDER BY id',
					Database::table( 'comments' ),
					$id,
				),
				ARRAY_A,
			);
			$record['dependency_uuids'] = array();
			foreach ( $record['dependencies'] as $target ) {
				$dep = Decision_Manager::get( $target );
				if ( ! is_wp_error( $dep ) ) {
					$record['dependency_uuids'][] = $dep['uuid'];
				}
			}
			$records[] = $record;
		}
		if ( 'json' === $format ) {
			return array(
				'schema_version' => 1,
				'exported_at'    => gmdate( 'c' ),
				'decisions'      => $records,
			);
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- php://temp is a memory stream; WP_Filesystem does not provide CSV stream handling.
		$stream  = fopen( 'php://temp', 'w+' );
		$columns = array(
			'uuid',
			'title',
			'description',
			'reason',
			'reversal_risk',
			'component',
			'component_type',
			'author_name',
			'created_at',
			'updated_at',
			'risk',
			'status',
			'review_date',
			'code_snippet',
			'reference_url',
			'notes',
		);
		fputcsv( $stream, $columns, ',', '"', '' );
		foreach ( $records as $record ) {
			$row = array();
			foreach ( $columns as $column ) {
				$value = (string) ( $record[ $column ] ?? '' );
				if ( preg_match( '/^[\x00-\x20]*[=+@-]/u', $value ) ) {
					$value = "'" . $value;
				}
				$row[] = $value;
			}
			fputcsv( $stream, $row, ',', '"', '' );
		}
		rewind( $stream );
		$csv = stream_get_contents( $stream );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Close the memory-only CSV stream; no filesystem credentials or paths are accessed.
		fclose( $stream );
		return array(
			'filename' => 'wp-decisionlog.csv',
			'content'  => $csv,
		);
	}
	/**
	 * Validate and import records without overwriting existing UUIDs.
	 *
	 * @param array $payload Portable import document.
	 *
	 * @return array|\WP_Error
	 */
	public static function import( $payload ) {
		global $wpdb;
		if (
			! is_array( $payload ) ||
			( $payload['schema_version'] ?? null ) !== 1 ||
			! isset( $payload['decisions'] ) ||
			! is_array( $payload['decisions'] ) ||
			count( $payload['decisions'] ) > 500
		) {
			return Decision_Manager::error(
				'Expected schema_version 1 and at most 500 decisions.',
			);
		}
		$validated = array();
		$seen      = array();
		foreach ( $payload['decisions'] as $index => $record ) {
			if (
				! is_array( $record ) ||
				! isset( $record['uuid'] ) ||
				! is_string( $record['uuid'] ) ||
				! wp_is_uuid( $record['uuid'] )
			) {
				return Decision_Manager::error(
					"Invalid UUID in record $index.",
				);
			}
			$uuid = strtolower( $record['uuid'] );
			if ( isset( $seen[ $uuid ] ) ) {
				return Decision_Manager::error(
					"Duplicate UUID within file at record $index.",
				);
			}
			$seen[ $uuid ] = true;
			$row           = Decision_Manager::validate( $record );
			if ( is_wp_error( $row ) ) {
				return Decision_Manager::error(
					"Record $index: " . $row->get_error_message(),
				);
			}
			$links = Decision_Manager::validate_links( $record['links'] ?? array() );
			if ( is_wp_error( $links ) ) {
				return $links;
			}
			$deps = $record['dependency_uuids'] ?? array();
			if ( ! is_array( $deps ) || count( $deps ) > 100 ) {
				return Decision_Manager::error(
					"Invalid dependencies in record $index.",
				);
			}
			foreach ( $deps as $dep ) {
				if ( ! is_string( $dep ) || ! wp_is_uuid( $dep ) ) {
					return Decision_Manager::error(
						"Invalid dependency UUID in record $index.",
					);
				}
			}
			$comments = $record['comments'] ?? array();
			if ( ! is_array( $comments ) || count( $comments ) > 1000 ) {
				return Decision_Manager::error(
					'Import supports at most 1000 comments per record.',
				);
			}
			foreach ( $comments as $comment ) {
				if (
					! is_array( $comment ) ||
					! isset( $comment['body'] ) ||
					! is_string( $comment['body'] ) ||
					! trim( $comment['body'] ) ||
					strlen( $comment['body'] ) > 10000
				) {
					return Decision_Manager::error( 'Invalid imported comment.' );
				}
			}
			$validated[ $uuid ] = array_merge(
				$row,
				array(
					'links'            => $links,
					'dependency_uuids' => array_map( 'strtolower', $deps ),
					'comments'         => $comments,
				)
			);
		}
		// Validate the portable graph before importing any records.
		foreach ( $validated as $uuid => $record ) {
			$stack   = $record['dependency_uuids'];
			$visited = array();
			while ( $stack ) {
				$next = array_pop( $stack );
				if ( $next === $uuid ) {
					return Decision_Manager::error(
						'Import contains a circular dependency.',
					);
				}
				if ( isset( $visited[ $next ] ) ) {
					continue;
				}
				$visited[ $next ] = true;
				if ( isset( $validated[ $next ] ) ) {
					$stack = array_merge(
						$stack,
						$validated[ $next ]['dependency_uuids'],
					);
				} elseif (
					! $wpdb->get_var(
						$wpdb->prepare(
							'SELECT id FROM %i WHERE uuid=%s',
							Database::table( 'decisions' ),
							$next,
						),
					)
				) {
					return Decision_Manager::error(
						'Import references an unknown dependency UUID.',
					);
				}
			}
		}
		$summary = array(
			'imported' => 0,
			'skipped'  => 0,
			'errors'   => array(),
		);
		$new     = array();
		foreach ( $validated as $uuid => $record ) {
			$exists = $wpdb->get_var(
				$wpdb->prepare(
					'SELECT id FROM %i WHERE uuid=%s',
					Database::table( 'decisions' ),
					$uuid,
				),
			);
			if ( $exists ) {
				++$summary['skipped'];
				continue;
			}
			$saved = Decision_Manager::save( $record );
			if ( is_wp_error( $saved ) ) {
				$summary['errors'][] = array(
					'uuid'    => $uuid,
					'message' => $saved->get_error_message(),
				);
				continue;
			}
			if (
				false ===
				$wpdb->update(
					Database::table( 'decisions' ),
					array( 'uuid' => $uuid ),
					array( 'id' => $saved['id'] ),
				)
			) {
				Decision_Manager::delete( $saved['id'] );
				$summary['errors'][] = array(
					'uuid'    => $uuid,
					'message' => 'UUID conflict; record not imported.',
				);
				continue;
			}
			$new[ $uuid ] = $saved['id'];
			++$summary['imported'];
			foreach ( $record['comments'] as $comment ) {
				$result = Decision_Manager::comment(
					$saved['id'],
					$comment['body'],
				);
				if ( is_wp_error( $result ) ) {
					$summary['errors'][] = array(
						'uuid'    => $uuid,
						'message' => $result->get_error_message(),
					);
				}
			}
		}
		foreach ( $new as $uuid => $id ) {
			$targets = array();
			foreach ( $validated[ $uuid ]['dependency_uuids'] as $dep ) {
				$target = $wpdb->get_var(
					$wpdb->prepare(
						'SELECT id FROM %i WHERE uuid=%s',
						Database::table( 'decisions' ),
						$dep,
					),
				);
				if ( $target ) {
					$targets[] = (int) $target;
				} else {
					$summary['errors'][] = array(
						'uuid'    => $uuid,
						'message' => 'Dependency was not imported.',
					);
				}
			}
			$result = Decision_Manager::dependencies( $id, $targets );
			if ( is_wp_error( $result ) ) {
				$summary['errors'][] = array(
					'uuid'    => $uuid,
					'message' => $result->get_error_message(),
				);
			}
		}
		return $summary;
	}
}
