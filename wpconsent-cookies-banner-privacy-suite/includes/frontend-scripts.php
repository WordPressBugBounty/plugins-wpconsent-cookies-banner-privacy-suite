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

	return array(
		'consent_duration'           => wpconsent()->settings->get_option( 'consent_duration', 30 ),
		'css_url'                    => WPCONSENT_PLUGIN_URL . 'build/' . $css_file,
		'css_version'                => $css_version,
		'default_allow'              => $default_allow,
		'consent_type'               => $default_allow ? 'optout' : 'optin',
		'manual_toggle_services'     => $manual_toggle_services,
		// All configured categories (plus services when visitors can toggle them), so custom categories save too.
		'slugs'                      => wpconsent()->cookies->get_preference_slugs(),
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
		'debug'                      => wpconsent_consent_mode_debug_data(),
	);
}

/**
 * Who sets the Google Consent Mode default state on this site.
 *
 * Mirrors the gates in wpconsent_google_consent_script() and WPConsent_Cookies::needs_google_consent()
 * so the debug report can explain why no WPConsent default was found instead of just flagging it.
 *
 * @return string One of 'disabled' (Consent Mode off), 'sitekit' (Site Kit consent mode owns the default),
 *                'wpconsent' (WPConsent outputs the default) or 'none' (Consent Mode on, but no Google service configured).
 */
function wpconsent_consent_mode_default_owner() {
	if ( ! wpconsent()->cookie_blocking->get_google_consent_mode() ) {
		return 'disabled';
	}

	// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hook defined by Google Site Kit plugin.
	if ( 'enabled' === apply_filters( 'googlesitekit_consent_mode_status', 'disabled' ) ) {
		return 'sitekit';
	}

	return wpconsent()->cookies->needs_google_consent() ? 'wpconsent' : 'none';
}

/**
 * Data for the Consent Mode debug report printed by WPConsent.debug() in the browser console.
 *
 * The report is also run automatically when the page is loaded with ?wpconsent_consent_debug=1 (the shorter
 * wpconsent_debug parameter is already used to disable script blocking for the scanner). All the strings are
 * defined here so they can be translated; the frontend script only formats them.
 *
 * @return array
 */
function wpconsent_consent_mode_debug_data() {
	// The strings are sent to every visitor, not only to administrators or requests with the query parameter:
	// WPConsent.debug() has to work as a one-off console command for anyone (Google reviewers are not logged in),
	// and full-page caches would otherwise serve the cached variant without strings to ?wpconsent_consent_debug=1.
	return array(
		'consent_mode_enabled' => wpconsent()->cookie_blocking->get_google_consent_mode(),
		'default_owner'        => wpconsent_consent_mode_default_owner(),
		'docs'                 => array(
			'consent_mode' => wpconsent_utm_url( 'https://wpconsent.com/docs/google-consent-mode-v2/', 'debug', 'consent-mode' ),
			'gtg'          => wpconsent_utm_url( 'https://wpconsent.com/docs/troubleshooting-google-tag-gateway-gtg-with-wpconsent/', 'debug', 'gtg' ),
		),
		'strings'              => wpconsent_consent_mode_debug_strings(),
	);
}

/**
 * Translated strings for the Consent Mode debug report. The frontend only replaces the %s placeholders.
 *
 * @return array
 */
function wpconsent_consent_mode_debug_strings() {
	return array(
		'title'                    => __( 'WPConsent Consent Mode debug report', 'wpconsent-cookies-banner-privacy-suite' ),
		'how_to_run'               => __( 'Run WPConsent.debug() in this console at any time, or load any page with ?wpconsent_consent_debug=1 to see this report again.', 'wpconsent-cookies-banner-privacy-suite' ),
		'consent_mode_enabled'     => __( 'Google Consent Mode is enabled in WPConsent.', 'wpconsent-cookies-banner-privacy-suite' ),
		'consent_mode_disabled'    => __( 'Google Consent Mode is disabled in WPConsent. Enable it under WPConsent > Settings in the Google Consent Mode section if you use Google tags.', 'wpconsent-cookies-banner-privacy-suite' ),
		'owner_sitekit'            => __( 'Site Kit by Google has its own Consent Mode enabled, so WPConsent leaves the default consent state to Site Kit.', 'wpconsent-cookies-banner-privacy-suite' ),
		'owner_none'               => __( 'No Google service is configured in WPConsent, so WPConsent did not output a default consent state. Add your Google services under WPConsent > Settings > Cookies, or run the scanner, so the default is set before your tags load.', 'wpconsent-cookies-banner-privacy-suite' ),
		'owner_wpconsent'          => __( 'WPConsent sets the default consent state in the page head.', 'wpconsent-cookies-banner-privacy-suite' ),
		'datalayer_missing'        => __( 'window.dataLayer does not exist on this page, so no Google tag or consent command has run yet.', 'wpconsent-cookies-banner-privacy-suite' ),
		/* translators: %s: position (index) of the entry in window.dataLayer. */
		'default_found'            => __( 'WPConsent default consent command found in dataLayer at position %s.', 'wpconsent-cookies-banner-privacy-suite' ),
		/* translators: %s: position (index) of the entry in window.dataLayer. */
		'default_found_other'      => __( 'A default consent command from another integration was found in dataLayer at position %s.', 'wpconsent-cookies-banner-privacy-suite' ),
		'default_missing'          => __( 'No default consent command was found in dataLayer.', 'wpconsent-cookies-banner-privacy-suite' ),
		'head_script_not_run'      => __( 'The WPConsent consent script did not run in the page head. Check that the theme calls wp_head() and that no optimization plugin delays or removes inline scripts.', 'wpconsent-cookies-banner-privacy-suite' ),
		/* translators: %s: position (index) of the entry in window.dataLayer. */
		'developer_id_found'       => __( 'WPConsent developer ID found in dataLayer at position %s.', 'wpconsent-cookies-banner-privacy-suite' ),
		'developer_id_missing'     => __( 'WPConsent developer ID was not found in dataLayer.', 'wpconsent-cookies-banner-privacy-suite' ),
		/* translators: 1: position (index) of the entry in window.dataLayer, 2: short description of the entry. */
		'first_tag_found'          => __( 'First Google tag activity in dataLayer at position %1$s: %2$s', 'wpconsent-cookies-banner-privacy-suite' ),
		'first_tag_none'           => __( 'No Google tag activity was found in dataLayer.', 'wpconsent-cookies-banner-privacy-suite' ),
		'scripts_before_none'      => __( 'No Google tag script was loaded before the WPConsent consent script.', 'wpconsent-cookies-banner-privacy-suite' ),
		'scripts_before_found'     => __( 'These Google tag scripts were already in the page before the WPConsent consent script ran. Make sure they do not fire before the default consent state is set (the dataLayer order below shows what actually happened):', 'wpconsent-cookies-banner-privacy-suite' ),
		'scripts_now'              => __( 'Google tag scripts found in the page now:', 'wpconsent-cookies-banner-privacy-suite' ),
		'scripts_now_none'         => __( 'No Google tag script (gtag.js or gtm.js) was found in the page.', 'wpconsent-cookies-banner-privacy-suite' ),
		'verdict_ok'               => __( 'OK: the default consent state was set before any Google tag fired.', 'wpconsent-cookies-banner-privacy-suite' ),
		'verdict_late'             => __( 'LATE: a Google tag fired before the default consent state was set.', 'wpconsent-cookies-banner-privacy-suite' ),
		'verdict_missing'          => __( 'MISSING: Google tags are running on this page but no default consent state was set.', 'wpconsent-cookies-banner-privacy-suite' ),
		'verdict_no_tags'          => __( 'No Google tag activity was detected on this page, so there is nothing to check yet. Load a page where your Google tags run and try again.', 'wpconsent-cookies-banner-privacy-suite' ),
		'verdict_disabled'         => __( 'Google Consent Mode is disabled, so WPConsent is not sending consent signals to Google tags.', 'wpconsent-cookies-banner-privacy-suite' ),
		'late_explanation'         => __( 'The first hit of a tag that fires before the default consent state is sent without the visitor\'s consent choice, which Google flags as a late consent implementation. Make sure the tag is loaded after the WPConsent consent script, for example by letting WPConsent block it or by moving the snippet below wp_head().', 'wpconsent-cookies-banner-privacy-suite' ),
		'missing_explanation'      => __( 'Without a default consent state, Google tags assume consent is granted. Make sure the WPConsent consent script is output in the page head: the theme must call wp_head() and no optimization plugin may delay or remove inline scripts.', 'wpconsent-cookies-banner-privacy-suite' ),
		/* translators: %s: URL of the Google tag gateway troubleshooting article. */
		'gtg_check'                => __( 'First check whether the tag is served through Google tag gateway, which changes how and from where the tag is loaded. See: %s', 'wpconsent-cookies-banner-privacy-suite' ),
		/* translators: %s: URL of the Google Consent Mode documentation article. */
		'consent_mode_docs'        => __( 'Learn how WPConsent implements Google Consent Mode: %s', 'wpconsent-cookies-banner-privacy-suite' ),
		'tcf_title'                => __( 'IAB TCF', 'wpconsent-cookies-banner-privacy-suite' ),
		'tcf_disabled'             => __( 'IAB TCF is not enabled on this site, so no TCF stub is expected.', 'wpconsent-cookies-banner-privacy-suite' ),
		/* translators: %s: the cmpStatus value reported by the __tcfapi ping command. */
		'tcf_api_found'            => __( 'window.__tcfapi is available (status: %s).', 'wpconsent-cookies-banner-privacy-suite' ),
		'tcf_api_missing'          => __( 'window.__tcfapi is not available. The TCF stub did not run in the page head.', 'wpconsent-cookies-banner-privacy-suite' ),
		'tcf_stub_before'          => __( 'The TCF stub was defined before the first Google tag activity.', 'wpconsent-cookies-banner-privacy-suite' ),
		'tcf_stub_after'           => __( 'The TCF stub was defined after the first Google tag activity, so that tag could not read the TCF signal when it fired.', 'wpconsent-cookies-banner-privacy-suite' ),
		'tcf_stub_not_recorded'    => __( 'The WPConsent TCF stub did not record when it ran, so its timing could not be checked.', 'wpconsent-cookies-banner-privacy-suite' ),
		'tcf_stub_no_tags'         => __( 'No Google tag activity was found, so there is nothing to compare the TCF stub timing against.', 'wpconsent-cookies-banner-privacy-suite' ),
		'datalayer_table'          => __( 'dataLayer entries (Google tag activity and consent commands are marked):', 'wpconsent-cookies-banner-privacy-suite' ),
		'column_position'          => __( 'Position', 'wpconsent-cookies-banner-privacy-suite' ),
		'column_type'              => __( 'Type', 'wpconsent-cookies-banner-privacy-suite' ),
		'column_entry'             => __( 'Entry', 'wpconsent-cookies-banner-privacy-suite' ),
		'type_wpconsent_default'   => __( 'WPConsent consent default', 'wpconsent-cookies-banner-privacy-suite' ),
		'type_other_default'       => __( 'Consent default (other)', 'wpconsent-cookies-banner-privacy-suite' ),
		'type_consent_update'      => __( 'Consent update', 'wpconsent-cookies-banner-privacy-suite' ),
		'type_wpconsent_developer' => __( 'WPConsent developer ID', 'wpconsent-cookies-banner-privacy-suite' ),
		'type_google_tag'          => __( 'Google tag', 'wpconsent-cookies-banner-privacy-suite' ),
		'type_other'               => __( 'Other', 'wpconsent-cookies-banner-privacy-suite' ),
	);
}

/**
 * Outputs the Google consent script: the WPConsent developer ID and, when WPConsent owns it, the Consent Mode default state.
 *
 * Nothing is output when the banner is disabled or Google Consent Mode is off. The developer ID is output as soon as
 * Consent Mode is on; the default consent state is skipped when Site Kit sets it or no Google service is configured.
 * The script runs early in the head so it takes effect before any Google tag.
 *
 * @return void
 */
function wpconsent_google_consent_script() {
	// If the banner display is disabled don't load this.
	if ( ! wpconsent()->banner->is_enabled() ) {
		return;
	}

	// Nothing to output when Google Consent Mode is turned off in the settings.
	if ( ! wpconsent()->cookie_blocking->get_google_consent_mode() ) {
		return;
	}

	// The developer ID is always set, as early as possible, so Google can attribute the Consent Mode
	// implementation to WPConsent. The default consent state below is only ours to set when no other
	// integration (Site Kit consent mode) handles it and a Google service is configured on the site.
	$default_state_js = '';
	if ( wpconsent()->cookies->needs_google_consent() ) {
		$default_state_js = wpconsent_google_consent_default_state_js();
	}

	$markers_before_js = wpconsent_consent_mode_debug_markers_js();

	// We need to load the Google consent script earlier than other tracking scripts for it to take effect correctly.
	// The markers object records when this script ran relative to the dataLayer and to Google tag script tags
	// already in the page, so WPConsent.debug() can tell whether a tag fired before the default was set.
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo "<script data-cfasync=\"false\" data-wpfc-render=\"false\">
		(function () {
			window.dataLayer = window.dataLayer || [];function gtag(){dataLayer.push(arguments);}
			{$markers_before_js}
			// Set the developer ID.
			gtag('set', 'developer_id.dMmRkYz', true);
			markers.developerIdIndex = dataLayer.length - 1;{$default_state_js}
		})();
	</script>"; // phpcs:ignore
}

/**
 * Build the JavaScript that sets the Google Consent Mode default state from the plugin settings.
 *
 * Returned without a wrapping script tag so it can be appended to the developer ID script above, which also
 * defines the `markers` variable the last two lines write to (see wpconsent_consent_mode_debug_markers_js()).
 *
 * @return string
 */
function wpconsent_google_consent_default_state_js() {
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

		$consent_js = 'var consent = ' . wp_json_encode( $always_granted ) . ';
			var signals = ' . wp_json_encode( $signal_categories ) . ";
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

	return "{$extra_gtag}
			// Set default consent state based on plugin settings.
			{$consent_js}
			gtag('consent', 'default', consent);
			markers.consentDefaultIndex = dataLayer.length - 1;
			markers.consentDefaultObject = consent;";
}

/**
 * JavaScript that records, before any consent command is pushed, the state the WPConsent head script found.
 *
 * Stored on window.WPConsentDebugMarkers and read by WPConsent.debug(): the dataLayer length (anything below it was
 * pushed by another script before WPConsent ran) and the Google tag script tags already present in the document.
 * Returned without a script tag so it can be inlined in the consent script above. Defines a local `markers` variable.
 *
 * @return string
 */
function wpconsent_consent_mode_debug_markers_js() {
	return "var markers = window.WPConsentDebugMarkers = window.WPConsentDebugMarkers || {};
			markers.headScriptTime = Date.now();
			markers.dataLayerLengthBefore = dataLayer.length;
			markers.googleScriptsBefore = [];
			try {
				var tagScripts = document.querySelectorAll('script[src*=\"googletagmanager.com/\"]');
				for (var i = 0; i < tagScripts.length; i++) {
					// Skip tags that optimization plugins made inert (Rocket Loader, delayed JS); only executable types count.
					var tagType = (tagScripts[i].type || 'text/javascript').toLowerCase();
					if ('text/javascript' === tagType || 'module' === tagType || 'application/javascript' === tagType) {
						markers.googleScriptsBefore.push(tagScripts[i].src);
					}
				}
			} catch (e) {}";
}
