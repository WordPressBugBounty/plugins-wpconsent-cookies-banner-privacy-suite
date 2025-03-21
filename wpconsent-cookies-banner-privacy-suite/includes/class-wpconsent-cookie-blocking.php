<?php
/**
 * Class used to handle cookie blocking functionality.
 *
 * @package WPConsent
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPConsent_Cookie_Blocking.
 */
class WPConsent_Cookie_Blocking {

	/**
	 * Script blocker instance.
	 *
	 * @var WPConsent_Script_Blocker
	 */
	public $script_blocker;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->script_blocker = wpconsent()->script_blocker;
		$this->hooks();
	}

	/**
	 * Register hooks.
	 */
	public function hooks() {
		add_action( 'wp_loaded', array( $this, 'buffer_start' ) );
		add_action( 'shutdown', array( $this, 'buffer_end' ) );
		add_filter( 'wpconsent_skip_script_blocking', array( $this, 'maybe_skip_for_google_consent' ), 10, 5 );
	}

	/**
	 * Start output buffering.
	 */
	public function buffer_start() {
		ob_start( array( $this, 'process_output' ) );
	}

	/**
	 * End output buffering and flush.
	 */
	public function buffer_end() {
		if ( ob_get_length() ) {
			ob_end_flush();
		}
	}

	/**
	 * Process the page output and modify script tags.
	 *
	 * @param string $buffer The page output.
	 *
	 * @return string Modified page output.
	 */
	public function process_output( $buffer ) {
		if ( ! $this->should_process() ) {
			return $buffer;
		}

		$scripts_by_category = $this->find_scripts_by_category( $buffer );

		foreach ( $scripts_by_category['scripts'] as $category => $scripts ) {
			foreach ( $scripts as $script_data ) {
				$this->modify_script_tag( $script_data['script'], $script_data['name'], $category );
			}
		}
		if ( empty( $scripts_by_category['html'] ) ) {
			return $buffer;
		}

		return $scripts_by_category['html']->save();
	}

	/**
	 * Find scripts by category.
	 *
	 * @param string $html The HTML content.
	 *
	 * @return array
	 */
	public function find_scripts_by_category( $html ) {
		$html = wpconsent_get_simplehtmldom( $html );

		if ( ! $html ) {
			return array(
				'html'     => '',
				'scripts'  => array(),
				'services' => array(),
			);
		}
		$services_used       = array();
		$scripts             = $html->find( 'script' );
		$scripts_by_category = array();
		$all_known_scripts   = $this->script_blocker->get_all_scripts();

		foreach ( $scripts as $script ) {
			$src     = $script->src;
			$content = $script->innertext;

			foreach ( $all_known_scripts as $category => $services ) {
				foreach ( $services as $service_key => $service ) {
					foreach ( $service['scripts'] as $pattern ) {
						if (
							( ! empty( $src ) && strpos( $src, $pattern ) !== false ) ||
							( ! empty( $content ) && strpos( $content, $pattern ) !== false )
						) {
							$scripts_by_category[ $category ][] = array(
								'script' => $script,
								'name'   => $service_key,
							);

							$services_used[] = $service_key;

							break;
						}
					}
				}
			}
		}

		return array(
			'html'     => $html,
			'scripts'  => $scripts_by_category,
			'services' => $services_used,
		);
	}

	/**
	 * Check if we should process the output.
	 *
	 * @return bool
	 */
	private function should_process() {
		if ( is_admin() || is_feed() ) {
			return false;
		}
		if ( wp_doing_ajax() ) {
			return false;
		}
		// Don't load this for REST API requests.
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return false;
		}
		// Don't load if our debug parameter is set.
		if ( isset( $_GET['wpconsent_debug'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return false;
		}

		// Finally, don't load if the setting is disabled.
		return absint( wpconsent()->settings->get_option( 'enable_script_blocking', 0 ) ) === 1;
	}

	/**
	 * Get the Google Consent Mode.
	 *
	 * @return bool
	 */
	public function get_google_consent_mode() {
		return absint( wpconsent()->settings->get_option( 'google_consent_mode', true ) ) === 1;
	}

	/**
	 * Maybe skip script blocking for Google Analytics when using Google Consent Mode.
	 *
	 * @param bool   $skip Whether to skip the script blocking.
	 * @param string $src The script source.
	 * @param string $name The name of the known script.
	 * @param string $category The category of the known script.
	 * @param string $script The script element.
	 *
	 * @return bool
	 */
	public function maybe_skip_for_google_consent( $skip, $src, $name, $category, $script ) {
		$scripts_to_skip = array(
			'google-analytics',
			'google-tag-manager',
			'google-ads',
		);
		if ( in_array( $name, $scripts_to_skip, true ) && $this->get_google_consent_mode() ) {
			return true;
		}

		return $skip;
	}

	/**
	 * Modify the script tag for delayed execution.
	 *
	 * @param DOMElement $script The script element.
	 * @param string     $name The name of the known script.
	 * @param string     $category The category of the known script.
	 */
	private function modify_script_tag( $script, $name, $category ) {
		$src = $script->getAttribute( 'src' );
		if ( 'essential' === $category || apply_filters( 'wpconsent_skip_script_blocking', false, $src, $name, $category, $script ) ) {
			return;
		}
		$script->setAttribute( 'type', 'text/plain' );
		$script->setAttribute( 'data-wpconsent-src', $src );
		$script->setAttribute( 'data-wpconsent-name', $name );
		$script->setAttribute( 'data-wpconsent-category', $category );
		$script->removeAttribute( 'src' );
	}
}
