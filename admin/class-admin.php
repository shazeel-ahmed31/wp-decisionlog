<?php
/**
 * WordPress admin shell and Settings API.
 *
 * @package WPDecisionLog
 */

namespace WPDecisionLog;

/**
 * Admin service.
 */
class Admin {

	/**
	 * Register the service hooks.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'admin_init', array( $this, 'settings' ) );
	}
	/**
	 * Register the site administrator menu.
	 *
	 * @return void
	 */
	public function menu() {
		add_menu_page(
			'WP DecisionLog',
			'DecisionLog',
			'manage_options',
			'wp-decisionlog',
			array( $this, 'render' ),
			'dashicons-list-view',
			58,
		);
	}
	/**
	 * Read or register the plugin configuration.
	 *
	 * @return void
	 */
	public function settings() {
		register_setting(
			'wpdl',
			'wpdl_settings',
			array(
				'type'              => 'object',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'show_in_rest'      => false,
			)
		);
	}
	/**
	 * Normalize Settings API boolean options.
	 *
	 * @param array $input Settings API input.
	 *
	 * @return array
	 */
	public function sanitize( $input ) {
		$out = Plugin::settings();
		foreach ( array_keys( $out ) as $key ) {
			$out[ $key ] = ! empty( $input[ $key ] );
		}
		return $out;
	}
	/**
	 * Render the authorized React dashboard shell.
	 *
	 * @return void
	 */
	public function render() {
		if ( Permissions::allowed() ) {
			echo '<div class="wrap"><div id="wpdl-root"></div><noscript>' .
				esc_html__(
					'Enable JavaScript to use the DecisionLog dashboard.',
					'wp-decisionlog',
				) .
				'</noscript></div>';
		} else {
			echo '<div class="notice notice-error"><p>' .
				esc_html__(
					'DecisionLog administrator capability is required. Reactivate the plugin to grant it.',
					'wp-decisionlog',
				) .
				'</p></div>';
		}
	}
	/**
	 * Load the WordPress-native dashboard assets and REST nonce.
	 *
	 * @param string $hook Current admin page hook.
	 *
	 * @return void
	 */
	public function assets( $hook ) {
		if (
			'toplevel_page_wp-decisionlog' !== $hook ||
			! Permissions::allowed()
		) {
			return;
		}
		wp_enqueue_script(
			'wpdl-app',
			plugins_url( 'build/index.js', WPDL_FILE ),
			array( 'wp-element' ),
			WPDL_VERSION,
			true,
		);
		wp_enqueue_style(
			'wpdl-admin',
			plugins_url( 'build/index.css', WPDL_FILE ),
			array(),
			WPDL_VERSION,
		);
		wp_localize_script(
			'wpdl-app',
			'wpdlConfig',
			array(
				'root'  => esc_url_raw( rest_url( 'wp-decisionlog/v1/' ) ),
				'nonce' => wp_create_nonce( 'wp_rest' ),
				'today' => current_time( 'Y-m-d' ),
			)
		);
	}
}
