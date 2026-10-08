<?php
/**
 * WPConsent compatibility with Thrive Architect and Thrive Theme Builder.
 *
 * @package WPConsent
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter( 'wpconsent_banner_output', 'wpconsent_thrive_builder_output' );
add_filter( 'wpconsent_should_block_scripts', 'wpconsent_thrive_builder_output' );
add_action( 'tcb_landing_page_template_redirect', 'wpconsent_thrive_landing_page_buffer_start' );
add_action( 'wp_enqueue_scripts', 'wpconsent_thrive_frontend_script', 20 );

/**
 * Prevent the banner and script blocking in the Thrive editor frames.
 *
 * Thrive saves the editor frame's markup, so placeholders added there would be saved into the page content.
 *
 * @param bool $value The current value.
 *
 * @return bool
 */
function wpconsent_thrive_builder_output( $value ) {
	if ( ! defined( 'TVE_EDITOR_FLAG' ) && ! defined( 'THRIVE_THEME_FLAG' ) ) {
		return $value;
	}

	$editor_flag = defined( 'TVE_EDITOR_FLAG' ) ? TVE_EDITOR_FLAG : 'tve';
	$theme_flag  = defined( 'THRIVE_THEME_FLAG' ) ? THRIVE_THEME_FLAG : 'tvet';

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( ( isset( $_GET[ $editor_flag ] ) || isset( $_GET[ $theme_flag ] ) ) && current_user_can( 'edit_posts' ) ) {
		return false;
	}

	return $value;
}

/**
 * Start script blocking on Thrive Landing Pages.
 *
 * Thrive renders Landing Pages on template_redirect priority 9 and exits, before our buffer starts.
 *
 * @return void
 */
function wpconsent_thrive_landing_page_buffer_start() {
	if ( ! empty( wpconsent()->cookie_blocking ) ) {
		wpconsent()->cookie_blocking->maybe_buffer_start();
	}
}

/**
 * Keep clicks on the placeholder away from Thrive's video element.
 *
 * Thrive listens for clicks anywhere on its video element to start the player, which loads YouTube's player API
 * before consent when a visitor clicks the placeholder itself, so only the accept button reaches the page.
 *
 * @return void
 */
function wpconsent_thrive_frontend_script() {
	if ( ! wp_script_is( 'wpconsent-frontend-js', 'enqueued' ) ) {
		return;
	}

	if ( 1 !== absint( wpconsent()->settings->get_option( 'enable_content_blocking' ) ) ) {
		return;
	}

	$script = "document.addEventListener('click',function(e){var p=e.target.closest&&e.target.closest('.tve_responsive_video_container .wpconsent-iframe-placeholder');if(p&&!e.target.closest('.wpconsent-iframe-accept-button')){e.stopPropagation();}},true);";

	wp_add_inline_script( 'wpconsent-frontend-js', $script, 'before' );
}
