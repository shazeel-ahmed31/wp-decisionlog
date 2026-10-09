<?php
/**
 * Authorization.
 *
 * @package WPDecisionLog
 */

namespace WPDecisionLog;

/**
 * Permissions service.
 */
class Permissions {

	/**
	 * Check the current administrator capabilities.
	 *
	 * @return bool
	 */
	public static function allowed() {
		return current_user_can( 'manage_decisionlog' ) &&
			current_user_can( 'manage_options' );
	}
	/**
	 * Authorize a private REST request.
	 *
	 * @return true|\WP_Error
	 */
	public static function check() {
		return self::allowed()
			? true
			: new \WP_Error(
				'rest_forbidden',
				__( 'Administrator access is required.', 'wp-decisionlog' ),
				array( 'status' => is_user_logged_in() ? 403 : 401 ),
			);
	}
	/**
	 * Grant the administrator role the plugin capability.
	 *
	 * @return void
	 */
	public static function grant() {
		$role = get_role( 'administrator' );
		if ( $role ) {
			$role->add_cap( 'manage_decisionlog' );
		}
	}
}
