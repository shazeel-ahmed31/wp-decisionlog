<?php
/**
 * Service registration.
 *
 * @package WPDecisionLog
 */

namespace WPDecisionLog;

/**
 * Plugin service.
 */
class Plugin {

	/**
	 * Initialize independent plugin services.
	 *
	 * @return void
	 */
	public static function init() {
		if ( get_option( 'wpdl_schema_version' ) !== WPDL_VERSION ) {
			$result = Database::migrate();
			if ( is_wp_error( $result ) ) {
				return;
			}
		}
		new Admin();
		new REST_API();
		new Review_Scheduler();
		new Warning_Manager();
		new WordPress_Integration();
		new Elementor_Integration();
		new WooCommerce_Integration();
	}
	/**
	 * Read or register the plugin configuration.
	 *
	 * @return array
	 */
	public static function settings() {
		return wp_parse_args(
			get_option( 'wpdl_settings', array() ),
			array(
				'monitoring'          => true,
				'reminders'           => true,
				'email_reminders'     => false,
				'delete_on_uninstall' => false,
			)
		);
	}
}
