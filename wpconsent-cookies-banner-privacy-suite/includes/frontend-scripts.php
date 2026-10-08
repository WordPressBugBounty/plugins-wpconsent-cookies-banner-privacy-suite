<?php
/**
 * Load scripts for the frontend.
 *
 * @package WPConsent
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'wp_enqueue_scripts', 'wpconsent_frontend_scripts' );
add_action( 'wp_head', 'wpconsent_google_consent_script', 5 );

/**
 * Load frontend scripts here.
 *
 * @return void
 */
function wpconsent_frontend_scripts() {

	$frontend_asset_file = WPCONSENT_PLUGIN_PATH . 'build/frontend.asset.php';

	if ( ! file_exists( $frontend_asset_file ) ) {
		return;
	}

	$asset = require $frontend_asset_file;

	// Let's not load anything on the frontend if the banner is disabled.
	if ( ! wpconsent()->banner->is_enabled() ) {
		return;
	}

	// Respect the same filter that gates banner HTML output, so compat shims
	// (EDD receipt page, Avada/Bricks/Divi builder previews) suppress the script too.
	if ( ! apply_filters( 'wpconsent_banner_output', true ) ) {
		return;
	}

	wp_enqueue_script( 'wpconsent-frontend-js', WPCONSENT_PLUGIN_URL . 'build/frontend.js', $asset['dependencies'], $asset['version'], true );

	// Determine the correct CSS file based on RTL.
	$css_file = is_rtl() ? 'frontend-rtl.css' : 'frontend.css';

	wp_localize_script(
		'wpconsent-frontend-js',
		'wpconsent',
		apply_filters( 'wpconsent_frontend_js_data', wpconsent_frontend_js_data( $css_file, $asset['version'] ) )
	);
}

/**
 * Data localized to the frontend bundle as the `wpconsent` object.
 *
 * Shared by the Lite and Pro enqueue callbacks so new keys only have to be added once;
 * Pro merges its own keys on top of this array.
 *
 * @param string $css_file    The frontend stylesheet file name inside build/.
 * @param string $css_version The stylesheet version string.
 *
 * @return array
 */
function wpconsent_frontend_js_data( $css_file, $css_version ) {
	$default_allow          = boolval( wpconsent()->settings->get_option( 'default_allow', 0 ) );
	$manual_toggle_services = boolval( wpconsent()->settings->get_option( 'manual_toggle_services', 0 ) );
	$slugs                  = $manual_toggle_services ? wpconsent()->cookies->get_preference_slugs() : array(
		'essential',
		'statistics',
		'marketing',
	);

	return array(
		'consent_duration'           => wpconsent()->settings->get_option( 'consent_duration', 30 ),
		'css_url'                    => WPCONSENT_PLUGIN_URL . 'build/' . $css_file,
		'css_version'                => $css_version,
		'default_allow'              => $default_allow,
		'consent_type'               => $default_allow ? 'optout' : 'optin',
		'manual_toggle_services'     => $manual_toggle_services,
		'slugs'                      => $slugs,
		'enable_consent_banner'      => wpconsent()->settings->get_option( 'enable_consent_banner', 1 ),
		'enable_script_blocking'     => wpconsent()->settings->get_option( 'enable_script_blocking', 1 ),
		'enable_content_blocking'    => wpconsent()->settings->get_option( 'enable_content_blocking', 0 ),
		'enable_consent_floating'    => boolval( wpconsent()->settings->get_option( 'enable_consent_floating', 0 ) ),
		'enable_shared_consent'      => boolval( wpconsent()->settings->get_option( 'enable_shared_consent', 0 ) ),
		'cookie_domain'              => wpconsent()->settings->get_option( 'cookie_domain', '' ),
		'accept_button_enabled'      => boolval( wpconsent()->settings->get_option( 'accept_button_enabled', 1 ) ),
		'cancel_button_enabled'      => boolval( wpconsent()->settings->get_option( 'cancel_button_enabled', 1 ) ),
		'preferences_button_enabled' => boolval( wpconsent()->settings->get_option( 'preferences_button_enabled', 1 ) ),
		'respect_gpc'                => boolval( wpconsent()->settings->get_option( 'respect_gpc', 0 ) ),
		'gpc_toast_message'          => wpconsent()->settings->get_option( 'gpc_toast_message', wpconsent()->strings->get_string( 'gpc_toast_message' ) ),
		'gpc_toast_duration'         => absint( apply_filters( 'wpconsent_gpc_toast_duration', 10000 ) ),
	);
}

/**
 * Outputs the Google consent script for managing user preferences on Google-related services.
 *
 * This function does not execute if the banner display is disabled or if no Google services are enabled
 * in the cookie data. It ensures the Google consent script is loaded early enough for it to take effect correctly.
 *
 * @return void
 */
function wpconsent_google_consent_script() {
	// If the banner display is disabled don't load this.
	if ( ! wpconsent()->banner->is_enabled() ) {
		return;
	}

	// Let's load this only if they are using one of the Google services in the cookie data.
	if ( ! wpconsent()->cookies->needs_google_consent() ) {
		return;
	}

	// On Pro this is already forced to 0 when any geolocation group is opt-in (WPConsent_Geolocation::maybe_force_default_allow()).
	$default_allow      = intval( wpconsent()->settings->get_option( 'default_allow', 0 ) );
	$url_passthrough    = (bool) wpconsent()->settings->get_option( 'gcm_url_passthrough', 0 );
	$ads_data_redaction = (bool) wpconsent()->settings->get_option( 'gcm_ads_data_redaction', 0 );

	// Consent Mode signals keyed by the WPConsent category that controls them (mirrors WPConsent.consentModeState() in banner.js).
	$signal_categories = array(
		'ad_storage'              => 'marketing',
		'analytics_storage'       => 'statistics',
		'ad_user_data'            => 'marketing',
		'ad_personalization'      => 'marketing',
		'personalization_storage' => 'marketing',
	);

	$always_granted = array(
		'security_storage'      => 'granted',
		'functionality_storage' => 'granted',
		'wait_for_update'       => 500,
	);

	$extra_gtag = '';
	if ( $url_passthrough ) {
		$extra_gtag .= "\n\t\t\tgtag('set', 'url_passthrough', true);\n";
	}
	if ( $ads_data_redaction ) {
		$extra_gtag .= "\n\t\t\tgtag('set', 'ads_data_redaction', true);\n";
	}

	if ( $default_allow ) {
		// Opt-out site: the default is granted, so fold in what is already known before gtag.js loads
		// and the first hit never relies on that default: a saved decision that denies a category, or a
		// GPC signal the frontend script has not recorded yet (checkGPC() in banner.js writes that opt-out
		// once the page is ready). Only denials are applied here; grants still come from the frontend script.
		$respect_gpc = wpconsent()->settings->get_option( 'respect_gpc', 0 ) ? 'true' : 'false';

		$consent_js = "var consent = " . wp_json_encode( $always_granted ) . ";
			var signals = " . wp_json_encode( $signal_categories ) . ";
			var saved = null;
			try {
				var parts = ('; ' + document.cookie).split('; wpconsent_preferences=');
				if (parts.length === 2) {
					saved = JSON.parse(parts.pop().split(';').shift());
				}
			} catch (e) {}
			if (typeof saved !== 'object') {
				saved = null;
			}
			var gpcPending = {$respect_gpc} && navigator.globalPrivacyControl === true && !(saved && 'respect_gpc' in saved);
			for (var signal in signals) {
				consent[signal] = (gpcPending || (saved && !saved[signals[signal]])) ? 'denied' : 'granted';
			}";
	} else {
		// Opt-in site: everything consent-controlled starts denied, nothing to compute client-side.
		$consent_js = 'var consent = ' . wp_json_encode( array_merge( array_fill_keys( array_keys( $signal_categories ), 'denied' ), $always_granted ) ) . ';';
	}

	// We need to load the Google consent script earlier than other tracking scripts for it to take effect correctly.
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo "<script data-cfasync=\"false\" data-wpfc-render=\"false\">
		(function () {
			window.dataLayer = window.dataLayer || [];function gtag(){dataLayer.push(arguments);}

			// Set the developer ID.
			gtag('set', 'developer_id.dMmRkYz', true);{$extra_gtag}
			// Set default consent state based on plugin settings.
			{$consent_js}
			gtag('consent', 'default', consent);
		})();
	</script>"; // phpcs:ignore
}
