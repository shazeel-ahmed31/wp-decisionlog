<?php
/**
 * Decision storage and graph validation.
 *
 * @package WPDecisionLog
 */

namespace WPDecisionLog;

/**
 * Decision Manager service.
 */
class Decision_Manager {

	const TYPES    = array(
		'wordpress-core',
		'wordpress-settings',
		'theme',
		'plugin',
		'elementor-page',
		'elementor-widget',
		'woocommerce',
		'custom-css',
		'custom-javascript',
		'custom-php',
		'database',
		'other',
	);
	const STATUSES = array( 'active', 'needs-review', 'deprecated', 'archived' );
	const RISKS    = array( 'low', 'medium', 'high', 'critical' );
	const KINDS    = array(
		'option',
		'theme',
		'plugin',
		'post',
		'elementor-document',
		'elementor-widget',
		'woocommerce-screen',
		'screen',
	);
	/**
	 * Create a consistent request error.
	 *
	 * @param string $message Human-readable error message.
	 * @param int    $status HTTP error status.
	 *
	 * @return \WP_Error
	 */
	public static function error( $message, $status = 400 ) {
		return new \WP_Error( 'wpdl_error', $message, array( 'status' => $status ) );
	}
	/**
	 * Read a decision with links, dependencies and author.
	 *
	 * @param int $id Decision identifier.
	 *
	 * @return array|\WP_Error
	 */
	public static function get( $id ) {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE id=%d',
				Database::table( 'decisions' ),
				$id,
			),
			ARRAY_A,
		);
		if ( ! $row ) {
			return self::error(
				__( 'Decision not found.', 'wp-decisionlog' ),
				404,
			);
		}
		$row['id']           = (int) $row['id'];
		$row['display_id']   = 'DL-' . $row['id'];
		$row['links']        = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT kind,identifier FROM %i WHERE decision_id=%d',
				Database::table( 'component_links' ),
				$id,
			),
			ARRAY_A,
		);
		$row['dependencies'] = array_map(
			'intval',
			$wpdb->get_col(
				$wpdb->prepare(
					'SELECT depends_on FROM %i WHERE decision_id=%d',
					Database::table( 'dependencies' ),
					$id,
				),
			),
		);
		$user                = get_userdata( $row['author_id'] );
		$row['author_name']  = $user
			? $user->display_name
			: __( 'Deleted user', 'wp-decisionlog' );
		return $row;
	}
	/**
	 * Validate and normalize decision fields.
	 *
	 * @param mixed      $data Input decision fields.
	 * @param array|null $existing Existing fields for a partial update.
	 *
	 * @return array|\WP_Error
	 */
	public static function validate( $data, $existing = null ) {
		if ( ! is_array( $data ) ) {
			return self::error( 'Expected a JSON object.' );
		}
		$row = array();
		foreach (
			array(
				'title',
				'description',
				'reason',
				'reversal_risk',
				'component',
				'component_type',
				'risk',
				'status',
				'review_date',
				'code_snippet',
				'reference_url',
				'notes',
			)
			as $key
		) {
			$value = $data[ $key ] ?? ( $existing[ $key ] ?? '' );
			if ( ! is_scalar( $value ) && null !== $value ) {
				return self::error( "Invalid field: $key." );
			}
			$value = (string) $value;
			if ( strlen( $value ) > 100000 ) {
				return self::error( "Field too large: $key." );
			}
			$row[ $key ] =
				'code_snippet' === $key
					? $value
					: sanitize_textarea_field( $value );
		}
		foreach ( array( 'title', 'reason', 'component' ) as $required ) {
			if ( '' === trim( $row[ $required ] ) ) {
				return self::error( "Required field: $required." );
			}
		}
		foreach ( array( 'title', 'component' ) as $key ) {
			if ( mb_strlen( $row[ $key ] ) > 200 ) {
				return self::error( "$key must be at most 200 characters." );
			}
		}
		if (
			! in_array( $row['component_type'], self::TYPES, true ) ||
			! in_array( $row['risk'], self::RISKS, true ) ||
			! in_array( $row['status'], self::STATUSES, true )
		) {
			return self::error( 'Invalid component type, risk or status.' );
		}
		if ( $row['review_date'] ) {
			$date = \DateTimeImmutable::createFromFormat(
				'!Y-m-d',
				$row['review_date'],
			);
			if ( ! $date || $date->format( 'Y-m-d' ) !== $row['review_date'] ) {
				return self::error(
					'Review date must be a valid YYYY-MM-DD date.',
				);
			}
		} else {
			$row['review_date'] = null;
		}
		if ( $row['reference_url'] ) {
			if (
				! filter_var( $row['reference_url'], FILTER_VALIDATE_URL ) ||
				! in_array(
					strtolower(
						wp_parse_url( $row['reference_url'], PHP_URL_SCHEME ) ??
							'',
					),
					array( 'https', 'http' ),
					true,
				)
			) {
				return self::error( 'Reference must be an HTTP or HTTPS URL.' );
			}
			$row['reference_url'] = esc_url_raw( $row['reference_url'] );
		}
		return $row;
	}
	/**
	 * Validate typed stable component associations.
	 *
	 * @param array $links Typed component links.
	 *
	 * @return array|\WP_Error
	 */
	public static function validate_links( $links ) {
		if ( ! is_array( $links ) || count( $links ) > 50 ) {
			return self::error( 'Provide at most 50 component links.' );
		}
		$result = array();
		foreach ( $links as $link ) {
			if (
				! is_array( $link ) ||
				! isset( $link['kind'], $link['identifier'] ) ||
				! is_string( $link['identifier'] ) ||
				! in_array( $link['kind'], self::KINDS, true )
			) {
				return self::error( 'Invalid component link.' );
			}
			$identifier = sanitize_text_field( $link['identifier'] );
			if ( ! $identifier || mb_strlen( $identifier ) > 191 ) {
				return self::error(
					'Component identifier must contain 1–191 characters.',
				);
			}
			if (
				in_array( $link['kind'], array( 'post', 'elementor-document' ), true ) &&
				! ctype_digit( $identifier )
			) {
				return self::error(
					'Post and document identifiers must be numeric IDs.',
				);
			}
			if (
				'elementor-widget' === $link['kind'] &&
				! preg_match( '/^[1-9][0-9]*:[a-zA-Z0-9_-]+$/', $identifier )
			) {
				return self::error(
					'Widget identifiers must use documentID:widgetID.',
				);
			}
			$result[ $link['kind'] . ':' . $identifier ] = array(
				'kind'       => $link['kind'],
				'identifier' => $identifier,
			);
		}
		return array_values( $result );
	}
	/**
	 * Create or update a decision and its component associations.
	 *
	 * @param mixed $data Input decision fields.
	 * @param int   $id Decision identifier.
	 *
	 * @return array|\WP_Error
	 */
	public static function save( $data, $id = 0 ) {
		global $wpdb;
		$old = $id ? self::get( $id ) : null;
		if ( is_wp_error( $old ) ) {
			return $old;
		}
		$row = self::validate( $data, $old );
		if ( is_wp_error( $row ) ) {
			return $row;
		}
		$links = self::validate_links( $data['links'] ?? ( $old['links'] ?? array() ) );
		if ( is_wp_error( $links ) ) {
			return $links;
		}
		$wpdb->query( 'START TRANSACTION' );
		if ( $id ) {
			if ( ! $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i WHERE id=%d FOR UPDATE', Database::table( 'decisions' ), $id ) ) ) {
				$wpdb->query( 'ROLLBACK' );
				return self::error( 'Decision not found.', 404 );
			}
		}
		$row['updated_at'] = current_time( 'mysql', true );
		if ( ! $id ) {
			$row['uuid']       = wp_generate_uuid4();
			$row['author_id']  = get_current_user_id();
			$row['created_at'] = $row['updated_at'];
			$ok                = $wpdb->insert( Database::table( 'decisions' ), $row );
			$id                = (int) $wpdb->insert_id;
		} else {
			$ok = $wpdb->update(
				Database::table( 'decisions' ),
				$row,
				array(
					'id' => $id,
				)
			);
		}
		if ( false === $ok ) {
			$wpdb->query( 'ROLLBACK' );
			return self::error( 'Could not save decision.', 500 );
		}
		$ok = $wpdb->delete(
			Database::table( 'component_links' ),
			array(
				'decision_id' => $id,
			)
		);
		foreach ( $links as $link ) {
			if (
				false ===
				$wpdb->insert(
					Database::table( 'component_links' ),
					array_merge( array( 'decision_id' => $id ), $link ),
				)
			) {
				$ok = false;
				break;
			}
		}
		$changed = $old
			? array_keys(
				array_filter(
					$row,
					static function ( $value, $key ) use ( $old ) {
						return $value !== $old[ $key ];
					},
					ARRAY_FILTER_USE_BOTH,
				),
			)
			: array();
		$event   = $old
			? ( $old['status'] !== $row['status']
				? 'decision_status_changed'
				: 'decision_updated' )
			: 'decision_created';
		if (
			false === $ok ||
			false ===
				Audit_Logger::log( $event, $id, array( 'changed_fields' => $changed ) )
		) {
			$wpdb->query( 'ROLLBACK' );
			return self::error( 'Could not save decision history.', 500 );
		}
		$wpdb->query( 'COMMIT' );
		return self::get( $id );
	}
	/**
	 * Delete a decision and dependent relational rows.
	 *
	 * @param int $id Decision identifier.
	 *
	 * @return array|\WP_Error
	 */
	public static function delete( $id ) {
		global $wpdb;
		$lock = substr( $wpdb->prefix . 'wpdl_graph', 0, 64 );
		if ( '1' !== (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s,5)', $lock ) ) ) {
			return self::error( 'Dependency graph is busy; retry.', 409 ); }
		try {
			$row = self::get( $id );
			if ( is_wp_error( $row ) ) {
				return $row;
			}
			$wpdb->query( 'START TRANSACTION' );
			if ( ! $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i WHERE id=%d FOR UPDATE', Database::table( 'decisions' ), $id ) ) ) {
				$wpdb->query( 'ROLLBACK' );
				return self::error( 'Decision not found.', 404 );
			}

			$ok = true;
			foreach ( array( 'component_links', 'notifications', 'comments' ) as $table ) {
				$ok =
				false !==
					$wpdb->delete(
						Database::table( $table ),
						array(
							'decision_id' => $id,
						)
					) && $ok;
			}
			$ok =
			false !==
				$wpdb->query(
					$wpdb->prepare(
						'DELETE FROM %i WHERE decision_id=%d OR depends_on=%d',
						Database::table( 'dependencies' ),
						$id,
						$id,
					),
				) && $ok;
			$ok =
			false !==
				$wpdb->delete( Database::table( 'decisions' ), array( 'id' => $id ) ) &&
			$ok;
			$ok = false !== Audit_Logger::log( 'decision_deleted', $id ) && $ok;
			if ( $ok ) {
				$wpdb->query( 'COMMIT' );
			} else {
				$wpdb->query( 'ROLLBACK' );
			}
			return $ok ? array( 'deleted' => true ) : self::error( 'Deletion failed.', 500 );

		} finally {
			$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) ); }
	}
	/**
	 * Read a filtered and paginated collection.
	 *
	 * @param array $args Collection filters and pagination.
	 *
	 * @return array
	 */
	public static function listing( $args = array() ) {
		global $wpdb;
		$where  = '1=1';
		$values = array();
		foreach ( array( 'status', 'risk', 'component_type', 'component' ) as $key ) {
			if ( ! empty( $args[ $key ] ) ) {
				$where   .= " AND $key=%s";
				$values[] = sanitize_text_field( $args[ $key ] );
			}
		}
		if ( ! empty( $args['search'] ) ) {
			$where .=
				' AND (title LIKE %s OR reason LIKE %s OR component LIKE %s)';
			$search = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			array_push( $values, $search, $search, $search );
		}
		if ( ! empty( $args['review_queue'] ) ) {
			$where   .=
				" AND (status='needs-review' OR (review_date IS NOT NULL AND review_date<=%s AND status='active'))";
			$values[] = current_time( 'Y-m-d' );
		}
		$table = Database::table( 'decisions' );
		$size  = min( 100, max( 1, absint( $args['per_page'] ?? 20 ) ) );
		$page  = max( 1, absint( $args['page'] ?? 1 ) );
		$total = (int) $wpdb->get_var(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- The clause contains only fixed allowlisted SQL; every value and table identifier is prepared below.
				"SELECT COUNT(*) FROM %i WHERE $where",
				array_merge( array( $table ), $values ),
			),
		);
		$rows = $wpdb->get_results(
			// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- The merged array supplies identifier, filter values, limit and offset; PHPCS cannot count runtime arrays.
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- The clause contains only fixed allowlisted SQL; every value and table identifier is prepared below.
				"SELECT * FROM %i WHERE $where ORDER BY updated_at DESC,id DESC LIMIT %d OFFSET %d",
				array_merge( array( $table ), $values, array( $size, ( $page - 1 ) * $size ) ),
			),
			ARRAY_A,
		);
		return array(
			'items'    => $rows,
			'total'    => $total,
			'page'     => $page,
			'per_page' => $size,
		);
	}
	/**
	 * Replace graph edges after cycle validation.
	 *
	 * @param int   $id Decision identifier.
	 * @param array $targets Target decision identifiers.
	 *
	 * @return array|\WP_Error
	 */
	public static function dependencies( $id, $targets ) {
		global $wpdb;
		if ( is_wp_error( self::get( $id ) ) ) {
			return self::error( 'Decision not found.', 404 );
		}
		if ( ! is_array( $targets ) || count( $targets ) > 100 ) {
			return self::error(
				'Dependencies must be an array of at most 100 IDs.',
			);
		}
		foreach ( $targets as $target ) {
			if (
				! is_numeric( $target ) ||
				(int) $target < 1 ||
				(string) (int) $target !== (string) $target
			) {
				return self::error(
					'Dependencies must be positive integer IDs.',
				);
			}
		}
		$targets = array_values( array_unique( array_map( 'intval', $targets ) ) );
		// Serialize graph writers so concurrent valid requests cannot introduce a cycle.
		$lock = substr( $wpdb->prefix . 'wpdl_graph', 0, 64 );
		if (
			'1' !==
			(string) $wpdb->get_var(
				$wpdb->prepare( 'SELECT GET_LOCK(%s,5)', $lock ),
			)
		) {
			return self::error( 'Dependency graph is busy; retry.', 409 );
		}
		try {
			$wpdb->query( 'START TRANSACTION' );
			$edges = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT decision_id,depends_on FROM %i',
					Database::table( 'dependencies' ),
				),
				ARRAY_A,
			);
			$graph = array();
			foreach ( $edges as $edge ) {
				if ( (int) $edge['decision_id'] !== (int) $id ) {
					$graph[ $edge['decision_id'] ][] = (int) $edge['depends_on'];
				}
			}
			$graph[ $id ] = $targets;
			foreach ( $targets as $target ) {
				if ( is_wp_error( self::get( $target ) ) ) {
					$wpdb->query( 'ROLLBACK' );
					return self::error( 'Dependency target does not exist.' );
				}
				$stack = array( $target );
				$seen  = array();
				while ( $stack ) {
					$next = array_pop( $stack );
					if ( (int) $next === (int) $id ) {
						$wpdb->query( 'ROLLBACK' );
						return self::error(
							'Circular dependencies are not allowed.',
						);
					}
					if ( isset( $seen[ $next ] ) ) {
						continue;
					}
					$seen[ $next ] = true;
					foreach ( $graph[ $next ] ?? array() as $child ) {
						$stack[] = $child;
					}
				}
			}
			$ok =
				false !==
				$wpdb->delete(
					Database::table( 'dependencies' ),
					array(
						'decision_id' => $id,
					)
				);
			foreach ( $targets as $target ) {
				$ok =
					false !==
						$wpdb->insert(
							Database::table( 'dependencies' ),
							array(
								'decision_id' => $id,
								'depends_on'  => $target,
							)
						) && $ok;
			}
			$ok =
				false !==
					Audit_Logger::log(
						'dependencies_modified',
						$id,
						array(
							'depends_on' => $targets,
						)
					) && $ok;
			if ( $ok ) {
				$wpdb->query( 'COMMIT' );
			} else {
				$wpdb->query( 'ROLLBACK' );
			}
			return $ok
				? $targets
				: self::error( 'Dependency update failed.', 500 );
		} finally {
			$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) );
		}
	}
	/**
	 * Read a paginated decision discussion.
	 *
	 * @param int $id Decision identifier.
	 * @param int $page Requested comment page.
	 *
	 * @return array|\WP_Error
	 */
	public static function comments( $id, $page = 1 ) {
		global $wpdb;
		if ( is_wp_error( self::get( $id ) ) ) {
			return self::error( 'Decision not found.', 404 );
		}
		$page = max( 1, absint( $page ) );
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE decision_id=%d ORDER BY id DESC LIMIT 101 OFFSET %d',
				Database::table( 'comments' ),
				$id,
				( $page - 1 ) * 100,
			),
			ARRAY_A,
		);
		$more = count( $rows ) > 100;
		$rows = array_slice( $rows, 0, 100 );
		foreach ( $rows as &$row ) {
			$user               = get_userdata( $row['author_id'] );
			$row['author_name'] = $user ? $user->display_name : 'Deleted user';
		}
		return array(
			'items'    => $rows,
			'page'     => $page,
			'has_more' => $more,
		);
	}
	/**
	 * Append a validated plain-text comment.
	 *
	 * @param int    $id Decision identifier.
	 * @param string $body Plain-text comment content.
	 *
	 * @return array|\WP_Error
	 */
	public static function comment( $id, $body ) {
		global $wpdb;
		if ( is_wp_error( self::get( $id ) ) ) {
			return self::error( 'Decision not found.', 404 );
		}
		if ( ! is_string( $body ) || ! trim( $body ) || strlen( $body ) > 10000 ) {
			return self::error( 'Comment must contain 1–10000 bytes.' );
		}
		$body = sanitize_textarea_field( $body );
		if ( ! trim( $body ) ) {
			return self::error( 'Comment must contain plain text.' );
		}
		$wpdb->query( 'START TRANSACTION' );
		if ( ! $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i WHERE id=%d FOR UPDATE', Database::table( 'decisions' ), $id ) ) ) {
			$wpdb->query( 'ROLLBACK' );
			return self::error( 'Decision not found.', 404 );
		}

		$ok = $wpdb->insert(
			Database::table( 'comments' ),
			array(
				'decision_id' => $id,
				'author_id'   => get_current_user_id(),
				'created_at'  => current_time( 'mysql', true ),
				'body'        => sanitize_textarea_field( $body ),
			)
		);
		$ok =
			false !== Audit_Logger::log( 'comment_added', $id ) && false !== $ok;
		if ( $ok ) {
			$wpdb->query( 'COMMIT' );
		} else {
			$wpdb->query( 'ROLLBACK' );
		}
		return $ok
			? self::comments( $id )
			: self::error( 'Could not save comment.', 500 );
	}
	/**
	 * Find active decisions linked to a stable component.
	 *
	 * @param string $kind Component association kind.
	 * @param string $identifier Stable component identifier.
	 *
	 * @return array
	 */
	public static function linked( $kind, $identifier ) {
		global $wpdb;
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT d.* FROM %i d INNER JOIN %i l ON l.decision_id=d.id WHERE l.kind=%s AND l.identifier=%s AND d.status IN ('active','needs-review')",
				Database::table( 'decisions' ),
				Database::table( 'component_links' ),
				$kind,
				$identifier,
			),
			ARRAY_A,
		);
	}
}
