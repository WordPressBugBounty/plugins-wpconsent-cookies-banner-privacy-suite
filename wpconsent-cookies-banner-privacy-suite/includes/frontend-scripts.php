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

	wp_enqueue_script( 'wpconsent-frontend-js', WPCONSENT_PLUGIN_URL . 'build/frontend.js', $asset['dependencies'], $asset['version'], true );

	wp_localize_script(
		'wpconsent-frontend-js',
		'wpconsent',
		apply_filters(
			'wpconsent_frontend_js_data',
			array(
				'consent_duration' => wpconsent()->settings->get_option( 'consent_duration', 30 ),
				'css_url'          => WPCONSENT_PLUGIN_URL . 'build/frontend.css',
				'css_version'      => $asset['version'],
			)
		)
	);
}

/**
 * Load the Google consent script.
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

	$consent_asset_file = WPCONSENT_PLUGIN_PATH . 'build/google-consent.asset.php';

	if ( ! file_exists( $consent_asset_file ) ) {
		return;
	}

	$asset = require $consent_asset_file;
	$src   = add_query_arg(
		'v',
		$asset['version'],
		WPCONSENT_PLUGIN_URL . 'build/google-consent.js'
	);

	// We need to load the Google consent script earlier than other tracking scripts for it to take effect correctly.
	echo '<script src="' . esc_url( $src ) . '"></script>'; // phpcs:ignore
}
