<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get the current Unix timestamp shifted to the site's timezone.
 *
 * Matches values stored with current_time( 'mysql' ), so date math done on
 * those columns stays on site-local days. Written as a parse of the site-time
 * string because WPCS discourages current_time( 'timestamp' ).
 *
 * @return int
 */
function wpconsent_site_time() {
	return strtotime( current_time( 'mysql' ) );
}

/**
 * Get a URL with UTM parameters.
 *
 * @param string $url The URL to add the params to.
 * @param string $medium The marketing medium.
 * @param string $campaign The campaign.
 * @param string $ad_content The utm_content param.
 *
 * @return string
 */
function wpconsent_utm_url( $url, $medium = '', $campaign = '', $ad_content = '' ) {
	$args = array(
		'utm_source'   => class_exists( 'WPConsent_License' ) ? 'proplugin' : 'liteplugin',
		'utm_medium'   => sanitize_key( $medium ),
		'utm_campaign' => sanitize_key( $campaign ),
	);

	if ( ! empty( $ad_content ) ) {
		$args['utm_content'] = sanitize_key( $ad_content );
	}

	$utm_url = add_query_arg(
		$args,
		$url
	);

	return apply_filters( 'wpconsent_utm_url', $utm_url, $url, $medium, $campaign, $ad_content );
}


/**
 * Check WP version and include the compatible upgrader skin.
 */
function wpconsent_require_upgrader() {

	global $wp_version;

	require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
	require_once WPCONSENT_PLUGIN_PATH . 'includes/admin/class-wpconsent-skin.php';
}

/**
 * Get the HTML DOM object and load the simplehtmldom library if needed.
 *
 * @param string $html The HTML to parse.
 *
 * @return WPConsent_Simple_HTML_DOM|false
 */
function wpconsent_get_simplehtmldom( $html ) {
	if ( ! function_exists( 'wpconsent_str_get_html' ) ) {
		// Load the simple HTML DOM library only if we need it.
		require_once WPCONSENT_PLUGIN_PATH . 'lib/simplehtmldom/class-wpconsent-simple-html-dom.php';
	}

	return wpconsent_str_get_html( $html );
}

/**
 * Get the registrable domain from a hostname using the Public Suffix List.
 *
 * Lazy-loads the PSL library only when needed to avoid unnecessary memory usage.
 *
 * @param string $hostname The hostname to parse.
 *
 * @return string The registrable domain, or empty string on failure.
 */
function wpconsent_get_registrable_domain( $hostname ) {
	if ( ! class_exists( 'WPConsent_Public_Suffix_List' ) ) {
		// Load the Public Suffix List library only if we need it.
		require_once WPCONSENT_PLUGIN_PATH . 'lib/public-suffix-list/class-wpconsent-public-suffix-list.php';
	}

	return WPConsent_Public_Suffix_List::get_registrable_domain( $hostname );
}

/**
 * Get the cookie domain to store when shared consent is turned on or off.
 *
 * @param int $enable_shared_consent Whether shared consent is enabled (1 or 0).
 *
 * @return string The registrable domain (e.g. 'example.com'), or empty string when shared consent is off.
 */
function wpconsent_get_shared_cookie_domain( $enable_shared_consent ) {
	// Calculate cookie domain if shared consent is enabled.
	$cookie_domain = '';
	$hostname      = '';
	if ( $enable_shared_consent ) {
		$hostname = wp_parse_url( home_url(), PHP_URL_HOST );
		if ( $hostname ) {
			$cookie_domain = wpconsent_get_registrable_domain( $hostname );
		}
	}

	/**
	 * Filter the calculated cookie domain before saving.
	 *
	 * Allows developers to modify the cookie domain for edge cases or custom requirements.
	 *
	 * @param string $cookie_domain The calculated registrable domain (e.g., 'example.com').
	 * @param string $hostname The original hostname from home_url().
	 * @param int    $enable_shared_consent Whether shared consent is enabled (1 or 0).
	 *
	 * @since 1.1.0
	 */
	return apply_filters( 'wpconsent_cookie_domain', $cookie_domain, $hostname, $enable_shared_consent );
}

/**
 * Run the follow-up work needed after settings are saved from the admin or an ability.
 *
 * @param array $settings          The settings that were just saved.
 * @param int   $previous_duration The consent duration in days before the save.
 *
 * @return void
 */
function wpconsent_after_settings_saved( $settings, $previous_duration ) {
	if ( isset( $settings['consent_duration'] ) ) {
		/**
		 * Fires after the Consent Duration setting is saved so dependent cookie labels can re-sync.
		 * A label edited by hand is left alone.
		 *
		 * @since 1.1.7
		 * @since 1.2.1 Passes the new and previous duration in days.
		 *
		 * @param int $new_duration      The new consent duration in days.
		 * @param int $previous_duration The previous consent duration in days.
		 */
		do_action( 'wpconsent_consent_duration_updated', absint( $settings['consent_duration'] ), absint( $previous_duration ) );
	}
}

/**
 * Run the follow-up work needed after banner settings are saved from the admin or an ability.
 *
 * @return void
 */
function wpconsent_after_banner_saved() {
	// Clear translation strings cache when settings change.
	delete_transient( 'wpconsent_translation_strings' );
}
