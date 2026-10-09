<?php
/**
 * Nonblocking contextual notices on explicitly supported admin screens.
 *
 * @package WPDecisionLog
 */

namespace WPDecisionLog;

/**
 * Warning Manager service.
 */
class Warning_Manager {

	/**
	 * Resolve the site-specific per-user dismissal metadata key.
	 *
	 * @param int $id Decision identifier.
	 * @return string
	 */
	public static function dismiss_key( $id ) {
		return 'wpdl_dismiss_' . get_current_blog_id() . '_' . absint( $id );
	}
	/**
	 * Fingerprint canonical decision content and relations for dismissal expiry.
	 *
	 * @param array $row Decision record.
	 * @return string
	 */
	public static function fingerprint( $row ) {
		$record = Decision_Manager::get( $row['id'] );
		if ( is_wp_error( $record ) ) {
			return ''; }
		unset( $record['author_name'] );
		return hash( 'sha256', wp_json_encode( $record ) );
	}

	/**
	 * Register the service hooks.
	 */
	public function __construct() {
		add_action( 'admin_notices', array( $this, 'show' ) );
	}
	/**
	 * Render escaped decision context and its detail link.
	 *
	 * @param array $row Decision record.
	 *
	 * @return void
	 */
	public static function content( $row ) {
		$url =
			admin_url( 'admin.php?page=wp-decisionlog' ) .
			'#decision/' .
			(int) $row['id'];
		echo '<p><strong>' .
			esc_html__( 'Existing Development Decision', 'wp-decisionlog' ) .
			' · DL-' .
			(int) $row['id'] .
			'</strong> — ' .
			esc_html( $row['title'] ) .
			'</p><p><strong>' .
			esc_html__( 'Reason:', 'wp-decisionlog' ) .
			'</strong> ' .
			esc_html( $row['reason'] ) .
			'</p><p><strong>' .
			esc_html__( 'Risk if reversed:', 'wp-decisionlog' ) .
			'</strong> ' .
			esc_html( $row['reversal_risk'] ) .
			' (' .
			esc_html( $row['risk'] ) .
			') · ' .
			esc_html( $row['status'] ) .
			'</p><p><a href="' .
			esc_url( $url ) .
			'">' .
			esc_html__( 'View complete decision', 'wp-decisionlog' ) .
			'</a></p>';
	}
	/**
	 * Display nonblocking notices on explicitly supported screens.
	 *
	 * @return void
	 */
	public function show() {
		if ( ! Permissions::allowed() ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen ) {
			return;
		}
		$rows = array();
		$map  = array(
			'options-general'    => array( 'blogname', 'blogdescription' ),
			'options-reading'    => array(
				'show_on_front',
				'page_on_front',
				'page_for_posts',
				'posts_per_page',
			),
			'options-permalink'  => array( 'permalink_structure' ),
			'options-discussion' => array( 'default_comment_status' ),
		);
		foreach ( $map[ $screen->id ] ?? array() as $option ) {
			$rows = array_merge(
				$rows,
				Decision_Manager::linked( 'option', $option ),
			);
		}
		if ( 'themes' === $screen->id ) {
			$rows = array_merge(
				$rows,
				Decision_Manager::linked( 'theme', get_stylesheet() ),
			);
		}
		if ( 'plugins' === $screen->id ) {
			foreach ( get_plugins() as $file => $info ) {
				$rows = array_merge(
					$rows,
					Decision_Manager::linked( 'plugin', $file ),
				);
			}
		}
		if ( 'post' === $screen->base ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin screen context; absint normalizes the ID and capabilities gate all displayed records.
			$id   = absint( $_GET['post'] ?? 0 );
			$rows = array_merge(
				$rows,
				Decision_Manager::linked( 'post', (string) $id ),
				Decision_Manager::linked( 'elementor-document', (string) $id ),
			);
		}
		if (
			'woocommerce_page_wc-settings' === $screen->id &&
			WooCommerce_Integration::active()
		) {
			foreach ( WooCommerce_Integration::OPTIONS as $option ) {
				$rows = array_merge(
					$rows,
					Decision_Manager::linked( 'option', $option ),
				);
			}
			$rows = array_merge(
				$rows,
				Decision_Manager::linked( 'woocommerce-screen', 'wc-settings' ),
			);
		}
		$rows = array_merge(
			$rows,
			Decision_Manager::linked( 'screen', $screen->id ),
		);
		$seen = array();
		foreach ( $rows as $row ) {
			if (
				isset( $seen[ $row['id'] ] ) ||
				get_user_meta(
					get_current_user_id(),
					self::dismiss_key( $row['id'] ),
					true,
				) === self::fingerprint( $row )
			) {
				continue;
			}
			$seen[ $row['id'] ] = true;
			echo '<div class="notice notice-warning is-dismissible wpdl-warning" data-wpdl-id="' .
				(int) $row['id'] .
				'">';
			self::content( $row );
			echo '</div>';
		}
		if ( $rows ) {
			wp_enqueue_script(
				'wpdl-warnings',
				plugins_url( 'admin/assets/warnings.js', WPDL_FILE ),
				array(),
				WPDL_VERSION,
				true,
			);
			wp_localize_script(
				'wpdl-warnings',
				'wpdlWarnings',
				array(
					'root'  => esc_url_raw( rest_url( 'wp-decisionlog/v1/warnings/' ) ),
					'nonce' => wp_create_nonce( 'wp_rest' ),
				)
			);
		}
		if (
			in_array(
				$screen->id,
				array( 'dashboard', 'toplevel_page_wp-decisionlog' ),
				true,
			) &&
			Plugin::settings()['reminders']
		) {
			$total = Decision_Manager::listing(
				array(
					'review_queue' => true,
					'per_page'     => 1,
				)
			)['total'];
			if ( $total ) {
				echo '<div class="notice notice-info"><p>' .
					esc_html(
						sprintf(
							/* translators: %d: Number of decisions requiring review. */
							__(
								'%d development decisions need review.',
								'wp-decisionlog',
							),
							$total,
						),
					) .
					' <a href="' .
					esc_url(
						admin_url( 'admin.php?page=wp-decisionlog#reviews' ),
					) .
					'">' .
					esc_html__( 'Open review queue', 'wp-decisionlog' ) .
					'</a></p></div>';
			}
		}
	}
}
