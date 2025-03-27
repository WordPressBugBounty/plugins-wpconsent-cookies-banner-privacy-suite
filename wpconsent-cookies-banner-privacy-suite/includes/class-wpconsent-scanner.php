<?php
/**
 * This class is used to scan websites and
 * suggest a cookies configuration that fits their
 * needs. It leverages the same structure that we use
 * for our script blocking where we block certain scripts
 * automatically for marketing and statistics scripts.
 *
 * @package WPConsent
 */

/**
 * WPConsent Scanner, singleton pattern.
 */
class WPConsent_Scanner {

	/**
	 * The services.
	 *
	 * @var array
	 */
	protected $services;

	/**
	 * The instance of this class.
	 *
	 * @var WPConsent_Scanner
	 */
	private static $instance;

	/**
	 * The services needed for the scanner.
	 *
	 * @var array
	 */
	protected $services_needed = array();

	/**
	 * The scripts by category.
	 *
	 * @var array
	 */
	protected $scripts_by_category = array();

	/**
	 * The essential services.
	 *
	 * @var array
	 */
	protected $essential = array();

	/**
	 * The endpoint for the scan.
	 *
	 * @var string
	 */
	protected $scan_endpoint = 'https://cookies.wpconsent.com/api/v1/scanner';

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->hooks();
	}

	/**
	 * Gets the instance of the first called class.
	 * This specific class will always run either version exclusively.
	 *
	 * @return WPConsent_Scanner
	 */
	public static function get_instance() {
		if ( ! isset( static::$instance ) ) {
			static::$instance = new static();
		}

		return static::$instance;
	}

	/**
	 * Adds the hooks for this class.
	 *
	 * @return void
	 */
	protected function hooks() {
		add_action( 'wp_ajax_wpconsent_scan_website', array( $this, 'ajax_scan_website' ) );
	}

	/**
	 * Scans the website and suggests a cookie configuration.
	 *
	 * @return void
	 */
	public function ajax_scan_website() {

		check_ajax_referer( 'wpconsent_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error();
		}

		$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';

		$response = $this->perform_scan();

		// Check if we have an error from the scanner.
		if ( isset( $response['error'] ) && $response['error'] ) {
			// Use the error message directly if available.
			$response['message'] = $response['error_message'];
		} else {
			// Only save scan data if there were no errors.
			$response['message'] = $this->get_message( $response );

			$this->save_scan_data( $response );

			if ( ! empty( $email ) ) {
				$this->save_email( $email );
			}
		}

		wp_send_json_success( $response );
	}

	/**
	 * Scans the website and returns the results.
	 *
	 * @return array
	 */
	public function perform_scan() {
		$scan_result = $this->remote_scan();

		// If we got an error from the scanner, return early with the error.
		if ( $scan_result['error'] ) {
			return array(
				'error'           => true,
				'error_message'   => $scan_result['error_message'],
				'scripts'         => array(),
				'categories'      => wpconsent()->cookies->get_categories(),
				'services_needed' => array(),
			);
		}

		return array(
			'error'           => false,
			'scripts'         => $this->get_scripts_formatted(),
			'categories'      => wpconsent()->cookies->get_categories(),
			'services_needed' => $this->services_needed,
		);
	}

	/**
	 * Does a remote scan of the website.
	 *
	 * @return array An array containing error status and message if applicable
	 */
	protected function remote_scan() {
		$result = array(
			'error'         => false,
			'error_message' => '',
		);

		$request = wp_remote_post(
			$this->scan_endpoint,
			array(
				'body'    => wp_json_encode( $this->scan_request_body() ),
				'headers' => $this->scan_request_headers(),
				'timeout' => 30, // Increase timeout to handle slower responses.
			)
		);

		if ( is_wp_error( $request ) ) {
			$result['error']         = true;
			$result['error_message'] = sprintf(
			/* translators: %s: error message */
				esc_html__( 'The scanner endpoint could not be reached: %s', 'wpconsent-cookies-banner-privacy-suite' ),
				$request->get_error_message()
			);

			return $result;
		}

		$response_code = wp_remote_retrieve_response_code( $request );
		if ( 200 !== $response_code ) {
			$result['error']         = true;
			$result['error_message'] = sprintf(
			/* translators: %d: HTTP response code */
				esc_html__( 'The scanner endpoint returned an error: HTTP %d', 'wpconsent-cookies-banner-privacy-suite' ),
				$response_code
			);

			return $result;
		}

		$body = wp_remote_retrieve_body( $request );

		if ( empty( $body ) ) {
			$result['error']         = true;
			$result['error_message'] = __( 'The scanner endpoint returned an empty response.', 'wpconsent-cookies-banner-privacy-suite' );

			return $result;
		}

		$data = json_decode( $body, true );

		if ( empty( $data ) || empty( $data['services'] ) || empty( $data['scripts'] ) ) {
			$result['error']         = true;
			$result['error_message'] = __( 'The scanner endpoint returned an invalid response format.', 'wpconsent-cookies-banner-privacy-suite' );

			return $result;
		}

		$this->scripts_by_category = $data['scripts'];
		$this->services_needed     = $data['services'];

		return $result;
	}

	/**
	 * Headers used for the scanner request.
	 *
	 * @return array
	 */
	protected function scan_request_headers() {
		return array(
			'Content-Type'        => 'application/json',
			'X-WPConsent-Version' => WPCONSENT_VERSION,
		);
	}

	/**
	 * The body of the request to the scanner.
	 *
	 * @return array
	 */
	protected function scan_request_body() {
		return array(
			'html'              => $this->get_website_html(),
			'home_url'          => home_url( '/' ),
			'wp_content_url'    => content_url( '/' ),
			'comments'          => $this->has_comments(),
			'wp_version'        => get_bloginfo( 'version' ),
			'php_version'       => phpversion(),
			'plugin_type'       => 'lite',
			'wpconsent_version' => WPCONSENT_VERSION,
		);
	}

	/**
	 * Scan the website markup and find scripts.
	 *
	 * @return array
	 */
	protected function get_scripts_formatted() {

		$scripts_data = array();
		$services     = $this->get_services();

		// Some services add multiple scripts legitimately, but we don't need to show them on the scanner page like we do for multiple Google Analytics scripts, for example.
		$ignore_duplicates = array(
			'optinmonster',
		);
		$used              = array();

		foreach ( $this->scripts_by_category as $category => $scripts ) {
			foreach ( $scripts as $script ) {
				// Let's see if we have a service for this script.
				if ( ! isset( $services[ $script['name'] ] ) ) {
					continue;
				}
				$service = $services[ $script['name'] ];

				if ( in_array( $script['name'], $ignore_duplicates, true ) ) {
					if ( in_array( $script['name'], $used, true ) ) {
						continue;
					}
					$used[] = $script['name'];
				}

				$scripts_data[ $category ][] = array(
					'name'        => $script['name'],
					'service'     => $service['label'],
					'html'        => esc_html( $script['script'] ),
					'logo'        => $service['logo'],
					'url'         => $service['service_url'],
					'description' => $service['description'],
					'cookies'     => $service['cookies'],
				);
			}
		}

		return $scripts_data;
	}

	/**
	 * Gets the message for the scan using the compiled data.
	 *
	 * @param array $data The data from the scan.
	 *
	 * @return string
	 */
	public function get_message( $data ) {
		$please_review = false;
		$total_scripts = 0;
		$total_cookies = 0;
		// Let's add the count of scripts in each category.
		foreach ( $data['scripts'] as $category => $scripts ) {
			$total_scripts += count( $scripts );
			foreach ( $scripts as $script ) {
				$total_cookies += count( $script['cookies'] );
			}
		}

		if ( 0 === $total_scripts ) {
			$message = __( 'No known scripts were found on your website.', 'wpconsent-cookies-banner-privacy-suite' );
		} else {
			$message = sprintf(
			/* translators: %1$d: number of scripts, %2$d: number of cookies */
				__( 'We found %1$d services on your website that set %2$d cookies.', 'wpconsent-cookies-banner-privacy-suite' ),
				$total_scripts,
				$total_cookies
			);

			$please_review = true;
		}
		if ( $please_review ) {
			$message .= ' ' . __( 'Please review them in the Detailed Report and choose the ones you want to automatically configure cookies for.', 'wpconsent-cookies-banner-privacy-suite' );
		}

		return $message;
	}

	/**
	 * Gets the services from the services class.
	 *
	 * @return array
	 */
	protected function get_services() {
		if ( ! isset( $this->services ) ) {
			$this->services = wpconsent()->services->get_services( $this->services_needed );
		}

		return $this->services;
	}

	/**
	 * Does a request to the website and returns the HTML.
	 *
	 * @return string
	 */
	public function get_website_html() {
		$request = $this->self_request();

		if ( is_wp_error( $request ) ) {
			return '';
		}

		return wp_remote_retrieve_body( $request );
	}

	/**
	 * Does a request to the website and returns the request object.
	 *
	 * @return WP_Error|array
	 */
	protected function self_request() {
		$user_ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

		// Let's pass forward the basic auth header if present.
		$auth_header = isset( $_SERVER['HTTP_AUTHORIZATION'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_AUTHORIZATION'] ) ) : '';

		$headers = array(
			'X-Forwarded-For' => $user_ip,
		);

		if ( ! empty( $auth_header ) ) {
			$headers['Authorization'] = $auth_header;
		}

		return wp_remote_get(
			add_query_arg(
				array(
					'wpconsent_debug' => 'true',
				),
				home_url( '/' )
			),
			array(
				'sslverify' => false,
				'headers'   => $headers,
			)
		);
	}

	/**
	 * Saves the scan results to the DB.
	 *
	 * @param array $data The data to save.
	 *
	 * @return void
	 */
	public function save_scan_data( $data ) {
		// Don't save the $data['message'] if set.
		unset( $data['message'] );

		$scanner_data = array(
			'date' => current_time( 'mysql' ),
			'data' => $data,
		);

		update_option( 'wpconsent_scanner_data', $scanner_data );
	}

	/**
	 * Gets the scan data from the DB.
	 *
	 * @return array
	 */
	public function get_scan_data() {
		return get_option( 'wpconsent_scanner_data', array() );
	}

	/**
	 * Tries to determine if the current site has comments enabled.
	 *
	 * @return bool
	 */
	public function has_comments() {
		// Let's check if comments are enabled site-wide.
		if ( get_option( 'default_comment_status' ) === 'open' ) {
			return true;
		}

		return false;
	}

	/**
	 * Marks the scan as configured.
	 *
	 * @return void
	 */
	public function mark_scan_as_configured() {
		$scan_data                    = $this->get_scan_data();
		$scan_data['configured']      = true;
		$scan_data['configured_time'] = current_time( 'mysql' );

		update_option( 'wpconsent_scanner_data', $scan_data );
	}

	/**
	 * Adds services needed for the scan.
	 *
	 * @param array|string $services The services needed.
	 *
	 * @return void
	 */
	public function add_services_needed( $services ) {
		if ( is_string( $services ) ) {
			$services = array( $services );
		}
		$this->services_needed = array_merge( $this->services_needed, $services );
	}

	/**
	 * Subscribes the email for updates on our scan results.
	 *
	 * @param string $email The email to save.
	 *
	 * @return void
	 */
	protected function save_email( $email ) {
		$services_needed = $this->services_needed;

		$body = array(
			'email'    => base64_encode( $email ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
			'services' => $services_needed,
		);

		wp_remote_post(
			'https://connect.wpconsent.com/subscribe',
			array(
				'user-agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . get_bloginfo( 'url' ) . '; WPConsent/' . WPCONSENT_VERSION,
				'headers'    => array(
					'Content-Type' => 'application/json',
				),
				'body'       => wp_json_encode( $body ),
			)
		);
	}
}
