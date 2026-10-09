<?php
/**
 * WordPress public hooks and explicit option allowlist.
 *
 * @package WPDecisionLog
 */

namespace WPDecisionLog;

/**
 * WordPress Integration service.
 */
class WordPress_Integration {

	const OPTIONS = array(
		'blogname',
		'blogdescription',
		'permalink_structure',
		'show_on_front',
		'page_on_front',
		'page_for_posts',
		'posts_per_page',
		'default_comment_status',
	);
	/**
	 * Register the service hooks.
	 */
	public function __construct() {
		add_action( 'updated_option', array( $this, 'option' ), 10, 3 );
		add_action( 'switch_theme', array( $this, 'theme' ), 10, 3 );
		add_action( 'activated_plugin', array( $this, 'plugin' ) );
		add_action( 'deactivated_plugin', array( $this, 'plugin' ) );
	}
	/**
	 * Handle only explicitly allowlisted option updates.
	 *
	 * @param string $name Table, setting or theme label.
	 *
	 * @return void
	 */
	public function option( $name ) {
		if ( in_array( $name, self::OPTIONS, true ) ) {
			Change_Monitor::changed( 'option', $name );
		}
	}
	/**
	 * Record a switch for both old and new theme identifiers.
	 *
	 * @param string         $name Table, setting or theme label.
	 * @param \WP_Theme      $new_theme Activated theme.
	 * @param \WP_Theme|null $old Previous theme.
	 *
	 * @return void
	 */
	public function theme( $name, $new_theme, $old ) {
		Change_Monitor::changed( 'theme', $new_theme->get_stylesheet() );
		if ( $old ) {
			Change_Monitor::changed( 'theme', $old->get_stylesheet() );
		}
	}
	/**
	 * Record supported plugin lifecycle changes.
	 *
	 * @param string $file Plugin basename.
	 *
	 * @return void
	 */
	public function plugin( $file ) {
		if ( plugin_basename( WPDL_FILE ) !== $file ) {
			Change_Monitor::changed( 'plugin', $file );
		}
	}
}
