<?php
/**
 * Optional Elementor document hooks; no internal editor APIs.
 *
 * @package WPDecisionLog
 */

namespace WPDecisionLog;

/**
 * Elementor Integration service.
 */
class Elementor_Integration {

	/**
	 * Register the service hooks.
	 */
	public function __construct() {
		add_action( 'elementor/document/after_save', array( $this, 'saved' ), 10, 2 );
		add_action( 'elementor/editor/footer', array( $this, 'editor' ) );
	}
	/**
	 * Detect the optional integration without requiring it.
	 *
	 * @return bool
	 */
	public static function active() {
		return did_action( 'elementor/loaded' ) || defined( 'ELEMENTOR_VERSION' );
	}
	/**
	 * Associate a public Elementor document-save event.
	 *
	 * @param object $document Elementor document exposing get_main_id.
	 *
	 * @return void
	 */
	public function saved( $document ) {
		global $wpdb;
		$id = $document->get_main_id();
		Change_Monitor::changed(
			'elementor-document',
			(string) $id,
			'elementor',
		);
		$widgets = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT identifier FROM %i WHERE kind='elementor-widget' AND identifier LIKE %s",
				Database::table( 'component_links' ),
				$wpdb->esc_like( $id . ':' ) . '%',
			),
		);
		foreach ( $widgets as $widget ) {
			Change_Monitor::changed(
				'elementor-widget',
				$widget,
				'elementor-document-change',
				'possible-dependency',
			);
		}
	}
	/**
	 * Render related decisions using the public editor footer hook.
	 *
	 * @return void
	 */
	public function editor() {
		if ( ! Permissions::allowed() ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin screen context; absint normalizes the ID and capabilities gate all displayed records.
		$id   = absint( $_GET['post'] ?? 0 ); // Read-only screen context, not a state change.
		$rows = Decision_Manager::linked( 'elementor-document', (string) $id );
		global $wpdb;
		$widgets = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT identifier FROM %i WHERE kind='elementor-widget' AND identifier LIKE %s",
				Database::table( 'component_links' ),
				$wpdb->esc_like( $id . ':' ) . '%',
			),
		);
		foreach ( $widgets as $widget ) {
			$rows = array_merge(
				$rows,
				Decision_Manager::linked( 'elementor-widget', $widget ),
			);
		}
		if ( $rows ) {
			echo '<aside style="position:fixed;right:20px;bottom:30px;z-index:99999;max-width:330px;padding:16px;background:#fff;border:2px solid #b45309;color:#111;max-height:50vh;overflow:auto" aria-label="' .
				esc_attr__( 'Related development decisions', 'wp-decisionlog' ) .
				'"><details open><summary>' .
				esc_html__( 'Related decisions (collapse)', 'wp-decisionlog' ) .
				'</summary>';
			foreach ( $rows as $row ) {
				Warning_Manager::content( $row );
			}
			echo '</details></aside>';
		}
	}
}
