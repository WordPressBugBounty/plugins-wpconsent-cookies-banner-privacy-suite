<?php
/**
 * Uninstall WPConsent.
 *
 * Remove:
 * - Custom post types and taxonomies
 * - Plugin settings and options
 * - Custom database tables
 * - Uploaded files
 * - Scheduled events
 *
 * @package WPConsent
 */

// Exit if accessed directly.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// If the function already exists we shouldn't run the uninstall as another version of the plugin is active.
if ( function_exists( 'wpconsent' ) ) {
	return;
}

// Load the main plugin file to access the classes.
require_once 'wpconsent.php';

// Clear any scheduled events.
wp_clear_scheduled_hook( 'wpconsent_auto_scanner' );
wp_clear_scheduled_hook( 'wpconsent_cleanup_scan_history' );
wp_clear_scheduled_hook( 'wpconsent_usage_tracking_cron' );
wp_clear_scheduled_hook( 'wpconsent_consent_logs_cleanup' );
wp_clear_scheduled_hook( 'wpconsent_consent_logs_cleanup_continue' );
wp_clear_scheduled_hook( 'wpconsent_analytics_aggregate' );
// Legal Documents: the daily check, and a translation job still queued (WP cron, or Action Scheduler when another plugin loads it).
wp_clear_scheduled_hook( 'wpconsent_legal_documents_status_check' );
wp_unschedule_hook( 'wpconsent_process_document_translation' );
if ( function_exists( 'as_unschedule_all_actions' ) ) {
	as_unschedule_all_actions( 'wpconsent_process_document_translation', array(), 'wpconsent_translation' );
}

// Remove notifications.
if ( class_exists( 'WPConsent_Notifications' ) ) {
	WPConsent_Notifications::delete_notifications_data();
}

// Let's see if the uninstall_data option is set.
$wpconsent_settings = get_option( 'wpconsent_settings', array() );

if ( ! empty( $wpconsent_settings['uninstall_data'] ) ) {

	global $wpdb;

	delete_option( 'wpconsent_usage_counters' );
	// A per-site user option: only this site's flag goes.
	delete_metadata( 'user', 0, $wpdb->get_blog_prefix() . 'wpconsent_legal_locked_seen', '', true );

	// Remove custom post types and taxonomies.

	// Let's make sure our post type and taxonomy are registered.
	wpconsent()->load_components();
	wpconsent()->cookies->register_post_type();
	wpconsent()->cookies->register_taxonomy();

	// Legal Documents: the store reads its record through the settings component, which exists only now.
	// The service forgets this site too (answers, versions, audit) when the owner removes the plugin's data.
	if ( class_exists( 'WPConsent_Legal_Documents_Client' ) && class_exists( 'WPConsent_Legal_Documents_Store' ) && WPConsent_Legal_Documents_Store::has_published() ) {
		( new WPConsent_Legal_Documents_Client() )->erase_site();
	}
	// Legal Documents: the stored documents (the published pages are the owner's content and stay).
	if ( class_exists( 'WPConsent_Legal_Documents_Store' ) ) {
		foreach ( WPConsent_Legal_Documents_Store::option_names() as $wpconsent_legal_option ) {
			delete_option( $wpconsent_legal_option );
		}
	}

	// Delete all posts of our custom post type.
	$wpconsent_cookies = get_posts(
		array(
			'post_type'   => 'wpconsent_cookie',
			'post_status' => 'any',
			'numberposts' => - 1,
			'fields'      => 'ids',
		)
	);

	foreach ( $wpconsent_cookies as $wpconsent_cookie_id ) {
		wp_delete_post( $wpconsent_cookie_id, true );
	}

	// Delete all terms and taxonomies.
	$wpconsent_category_terms = get_terms(
		array(
			'taxonomy'   => 'wpconsent_category',
			'hide_empty' => false,
			'fields'     => 'ids',
		)
	);

	if ( ! is_wp_error( $wpconsent_category_terms ) ) {
		foreach ( $wpconsent_category_terms as $wpconsent_category_term_id ) {
			wp_delete_term( $wpconsent_category_term_id, 'wpconsent_category' );
		}
	}

	// Delete all plugin options.
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'wpconsent\_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

	// Delete all plugin user meta.
	$wpdb->query( "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'wpconsent\_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

	// Delete all plugin post meta.
	$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE 'wpconsent\_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '\_wpconsent\_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

	// Remove any transients we've left behind.
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_wpconsent\_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_site\_transient\_wpconsent\_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_timeout\_wpconsent\_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_site\_transient\_timeout\_wpconsent\_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

	// Remove uploaded files.
	$wpconsent_uploads_directory = wp_upload_dir();
	if ( empty( $wpconsent_uploads_directory['error'] ) ) {
		global $wp_filesystem;
		WP_Filesystem();

		// Remove the wpconsent directory from uploads.
		$wp_filesystem->rmdir( $wpconsent_uploads_directory['basedir'] . '/wpconsent/', true );
	}

	// Remove translation files.
	$wpconsent_languages_directory = defined( 'WP_LANG_DIR' ) ? trailingslashit( WP_LANG_DIR ) : trailingslashit( WP_CONTENT_DIR ) . 'languages/';
	$wpconsent_translations        = glob( wp_normalize_path( $wpconsent_languages_directory . 'plugins/wpconsent-*' ) );

	if ( ! empty( $wpconsent_translations ) ) {
		global $wp_filesystem;
		WP_Filesystem();

		foreach ( $wpconsent_translations as $wpconsent_file ) {
			$wp_filesystem->delete( $wpconsent_file );
		}
	}

	// Maybe drop the "records of consent" table.
	if ( class_exists( 'WPConsent_Consent_Log' ) ) {
		$wpconsent_records_table = esc_sql( $wpdb->prefix . 'wpconsent_consent_logs' );
		$wpdb->query( "DROP TABLE IF EXISTS `{$wpconsent_records_table}`" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	// Maybe drop the "Do Not Track" table.
	if ( class_exists( 'WPConsent_DNT_DB' ) ) {
		$wpconsent_dnt_table = esc_sql( $wpdb->prefix . 'wpconsent_dnt_requests' );
		$wpdb->query( "DROP TABLE IF EXISTS `{$wpconsent_dnt_table}`" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	// Drop the scan history table.
	$wpconsent_scan_history_table = esc_sql( $wpdb->prefix . 'wpconsent_scan_history' );
	$wpdb->query( "DROP TABLE IF EXISTS `{$wpconsent_scan_history_table}`" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

	// Drop the banner analytics tables.
	foreach ( array( 'wpconsent_analytics_events', 'wpconsent_analytics_daily' ) as $wpconsent_analytics_table ) {
		$wpconsent_analytics_table = esc_sql( $wpdb->prefix . $wpconsent_analytics_table );
		$wpdb->query( "DROP TABLE IF EXISTS `{$wpconsent_analytics_table}`" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}
}
