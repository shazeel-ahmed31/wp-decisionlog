<?php
/**
 * Authenticated REST resources.
 *
 * @package WPDecisionLog
 */

namespace WPDecisionLog;

/**
 * REST API service.
 */
class REST_API {

	/**
	 * Register the service hooks.
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register' ) );
	}
	/**
	 * Register an authenticated and bounded REST resource.
	 *
	 * @param string   $path Resource path pattern.
	 * @param string   $methods Allowed HTTP methods.
	 * @param callable $callback Resource handler.
	 *
	 * @return void
	 */
	public function route( $path, $methods, $callback ) {
		$args = array();
		foreach ( array( 'id', 'page', 'per_page', 'decision_id' ) as $key ) {
			$args[ $key ] = array(
				'type'    => 'integer',
				'minimum' => 1,
			);
		}
		foreach ( array( 'search', 'component', 'event_type', 'format' ) as $key ) {
			$args[ $key ] = array(
				'type'      => 'string',
				'maxLength' => 200,
			);
		}
		foreach (
			array(
				'status'         => Decision_Manager::STATUSES,
				'risk'           => Decision_Manager::RISKS,
				'component_type' => Decision_Manager::TYPES,
			)
			as $key => $values
		) {
			$args[ $key ] = array(
				'type' => 'string',
				'enum' => array_merge( array( '' ), $values ),
			);
		}
		$bounded = static function ( $request ) use ( $callback ) {
			if ( strlen( $request->get_body() ) > 2097152 ) {
				return Decision_Manager::error( 'Request exceeds 2 MiB.', 413 );
			}
			return call_user_func( $callback, $request );
		};
		register_rest_route(
			'wp-decisionlog/v1',
			$path,
			array(
				'methods'             => $methods,
				'callback'            => $bounded,
				'permission_callback' => array( Permissions::class, 'check' ),
				'args'                => $args,
			)
		);
	}
	/**
	 * Register private plugin REST endpoints.
	 *
	 * @return void
	 */
	public function register() {
		$this->route(
			'/decisions',
			'GET',
			static function ( $r ) {
				return Decision_Manager::listing( $r->get_params() );
			}
		);
		$this->route(
			'/decisions',
			'POST',
			static function ( $r ) {
				$result = Decision_Manager::save( $r->get_json_params() );
				return is_wp_error( $result )
				? $result
				: new \WP_REST_Response( $result, 201 );
			}
		);
		$this->route(
			'/decisions/(?P<id>\d+)',
			'GET',
			static function ( $r ) {
				return Decision_Manager::get( $r['id'] );
			}
		);
		$this->route(
			'/decisions/(?P<id>\d+)',
			'PUT',
			static function ( $r ) {
				return Decision_Manager::save( $r->get_json_params(), $r['id'] );
			}
		);
		$this->route(
			'/decisions/(?P<id>\d+)',
			'DELETE',
			static function ( $r ) {
				return Decision_Manager::delete( $r['id'] );
			}
		);
		$this->route(
			'/decisions/(?P<id>\d+)/dependencies',
			'GET',
			static function ( $r ) {
				$row = Decision_Manager::get( $r['id'] );
				return is_wp_error( $row ) ? $row : $row['dependencies'];
			},
		);
		$this->route(
			'/decisions/(?P<id>\d+)/dependencies',
			'POST',
			static function ( $r ) {
				return Decision_Manager::dependencies(
					$r['id'],
					$r->get_json_params()['dependencies'] ?? null,
				);
			},
		);
		$this->route(
			'/decisions/(?P<id>\d+)/review',
			'POST',
			static function (
				$r,
			) {
				return Review_Scheduler::complete(
					$r['id'],
					$r->get_json_params()['review_date'] ?? '',
				);
			}
		);
		$this->route(
			'/decisions/(?P<id>\d+)/comments',
			'GET',
			static function (
				$r,
			) {
				return Decision_Manager::comments( $r['id'], $r['page'] ?? 1 );
			}
		);
		$this->route(
			'/decisions/(?P<id>\d+)/comments',
			'POST',
			static function ( $r ) {
				$result = Decision_Manager::comment(
					$r['id'],
					$r->get_json_params()['body'] ?? null,
				);
				return is_wp_error( $result )
					? $result
					: new \WP_REST_Response( $result, 201 );
			},
		);
		$this->route( '/dashboard/stats', 'GET', array( $this, 'stats' ) );
		$this->route( '/components', 'GET', array( $this, 'components' ) );
		$this->route(
			'/activity',
			'GET',
			static function ( $r ) {
				return Audit_Logger::listing( $r->get_params() );
			}
		);
		$this->route(
			'/reviews',
			'GET',
			static function ( $r ) {
				return Decision_Manager::listing(
					array_merge( $r->get_params(), array( 'review_queue' => true ) ),
				);
			}
		);
		$this->route(
			'/export',
			'GET',
			static function ( $r ) {
				return Export_Manager::export( $r['format'] ?? 'json' );
			}
		);
		$this->route(
			'/import',
			'POST',
			static function ( $r ) {
				if ( strlen( $r->get_body() ) > 2097152 ) {
					return Decision_Manager::error( 'Import exceeds 2 MiB.', 413 );
				}
				return Export_Manager::import( $r->get_json_params() );
			}
		);
		$this->route(
			'/settings',
			'GET',
			static function () {
				return array_merge(
					Plugin::settings(),
					array(
						'elementor_active'   => Elementor_Integration::active(),
						'woocommerce_active' => WooCommerce_Integration::active(),
					)
				);
			}
		);
		$this->route(
			'/settings',
			'PUT',
			static function ( $r ) {
				$data = $r->get_json_params();
				if ( ! is_array( $data ) ) {
					return Decision_Manager::error( 'Expected settings object.' );
				}
				$settings = Plugin::settings();
				foreach ( array_keys( $settings ) as $key ) {
					if ( isset( $data[ $key ] ) ) {
						if ( ! is_bool( $data[ $key ] ) ) {
							return Decision_Manager::error(
								'Settings must be booleans.',
							);
						}
						$settings[ $key ] = $data[ $key ];
					}
				}
				update_option( 'wpdl_settings', $settings );
				return $settings;
			}
		);
		$this->route(
			'/warnings/(?P<id>\d+)/dismiss',
			'POST',
			static function (
				$r,
			) {
				$row = Decision_Manager::get( $r['id'] );
				if ( is_wp_error( $row ) ) {
					return $row;
				}
				update_user_meta(
					get_current_user_id(),
					Warning_Manager::dismiss_key( $r['id'] ),
					Warning_Manager::fingerprint( $row ),
				);
				return array( 'dismissed' => true );
			}
		);
	}
	/**
	 * Calculate live decision metrics and distributions.
	 *
	 * @return array
	 */
	public function stats() {
		global $wpdb;
		$table = Database::table( 'decisions' );
		return array(
			'total'            => (int) $wpdb->get_var(
				$wpdb->prepare( 'SELECT COUNT(*) FROM %i', $table ),
			),
			'active'           => (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM %i WHERE status='active'",
					$table,
				),
			),
			'needs_review'     => Decision_Manager::listing(
				array(
					'review_queue' => true,
					'per_page'     => 1,
				)
			)['total'],
			'high_risk'        => (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM %i WHERE risk IN ('high','critical') AND status IN ('active','needs-review')",
					$table,
				),
			),
			'recently_updated' => (int) $wpdb->get_var(
				$wpdb->prepare(
					'SELECT COUNT(*) FROM %i WHERE updated_at>=%s',
					$table,
					gmdate( 'Y-m-d H:i:s', time() - 7 * DAY_IN_SECONDS ),
				),
			),
			'by_component'     => $wpdb->get_results(
				$wpdb->prepare(
					'SELECT component_type AS label,COUNT(*) AS count FROM %i GROUP BY component_type',
					$table,
				),
				ARRAY_A,
			),
			'by_risk'          => $wpdb->get_results(
				$wpdb->prepare(
					'SELECT risk AS label,COUNT(*) AS count FROM %i GROUP BY risk',
					$table,
				),
				ARRAY_A,
			),
			'upcoming_reviews' => $wpdb->get_results(
				$wpdb->prepare(
					"SELECT id,title,review_date FROM %i WHERE review_date>=%s AND status IN ('active','needs-review') ORDER BY review_date ASC LIMIT 10",
					$table,
					current_time( 'Y-m-d' ),
				),
				ARRAY_A,
			),
		);
	}
	/**
	 * Read paginated component group counts.
	 *
	 * @param \WP_REST_Request $request Authenticated REST request.
	 *
	 * @return array
	 */
	public function components( $request ) {
		global $wpdb;
		$table = Database::table( 'decisions' );
		$page  = max( 1, absint( $request['page'] ?? 1 ) );
		$size  = 50;
		return array(
			'items'    => $wpdb->get_results(
				$wpdb->prepare(
					'SELECT component,component_type,COUNT(*) AS count FROM %i GROUP BY component,component_type ORDER BY count DESC,component LIMIT %d OFFSET %d',
					$table,
					$size,
					( $page - 1 ) * $size,
				),
				ARRAY_A,
			),
			'total'    => (int) $wpdb->get_var(
				$wpdb->prepare(
					'SELECT COUNT(*) FROM (SELECT component,component_type FROM %i GROUP BY component,component_type) groups_table',
					$table,
				),
			),
			'page'     => $page,
			'per_page' => $size,
		);
	}
}
