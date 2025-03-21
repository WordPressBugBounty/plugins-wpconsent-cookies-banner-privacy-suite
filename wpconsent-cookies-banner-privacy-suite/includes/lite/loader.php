<?php
/**
 * File used for importing lite-only files.
 *
 * @package WPConsent
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( is_admin() || defined( 'DOING_CRON' ) && DOING_CRON ) {
	// Connect to upgrade.
	require_once WPCONSENT_PLUGIN_PATH . 'includes/lite/admin/class-wpconsent-connect.php';

}
