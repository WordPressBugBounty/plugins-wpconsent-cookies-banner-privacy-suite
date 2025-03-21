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

		$html     = $this->get_website_html();
		$response = $this->perform_scan( $html );

		$response['message'] = $this->get_message( $response );

		$this->save_scan_data( $response );

		if ( ! empty( $email ) ) {
			$this->save_email( $email );
		}

		wp_send_json_success( $response );
	}

	/**
	 * Scans the website and returns the results.
	 *
	 * @param string $html The HTML of the website.
	 *
	 * @return array
	 */
	public function perform_scan( $html ) {
		$this->check_services_used( $html );

		$scripts   = $this->get_scripts_formatted();
		$essential = $this->get_essential_formatted();

		// Let's check if any scripts are in the essential category and move them to the essential array.
		foreach ( $scripts as $category => $category_scripts ) {
			if ( 'essential' === $category ) {
				$essential = array_merge( $essential, $category_scripts );
				unset( $scripts[ $category ] );
			}
		}

		return array(
			'scripts'         => $scripts,
			'essential'       => $essential,
			'categories'      => wpconsent()->cookies->get_categories(),
			'services_needed' => $this->services_needed,
		);
	}

	/**
	 * Checks the HTML for services used and loads the needed data for loading just the needed services.
	 *
	 * @param string $html The HTML of the website.
	 *
	 * @return void
	 */
	protected function check_services_used( $html ) {
		$scripts_by_category       = wpconsent()->cookie_blocking->find_scripts_by_category( $html );
		$this->scripts_by_category = $scripts_by_category['scripts'];
		$this->add_services_needed( $scripts_by_category['services'] );

		$this->get_essential( $html );

		$this->add_services_needed( $this->essential );
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
					'html'        => esc_html( $script['script']->outertext() ),
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
		$total_scripts = count( $data['essential'] );
		$total_cookies = 0;
		// Let's add the count of scripts in each category.
		foreach ( $data['scripts'] as $category => $scripts ) {
			$total_scripts += count( $scripts );
			foreach ( $scripts as $script ) {
				$total_cookies += count( $script['cookies'] );
			}
		}

		// Let's add up all cookies in the essential.
		foreach ( $data['essential'] as $essential ) {
			$total_cookies += count( $essential['cookies'] );
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
		if ( ! empty( $response['essential'] ) ) {
			$message .= ' ' . __( 'We also found some essential cookies used on your website that we recommend you configure.', 'wpconsent-cookies-banner-privacy-suite' );

			$please_review = true;
		}
		if ( $please_review ) {
			$message .= ' ' . __( 'Please review them in the Detailed Report and choose the ones you want to automatically configure cookies for.', 'wpconsent-cookies-banner-privacy-suite' );
		}

		return $message;
	}

	/**
	 * Gets the essential cookies.
	 *
	 * @param string $html The HTML of the website.
	 */
	protected function get_essential( $html ) {
		if ( $this->has_login( $html ) ) {
			$this->essential[] = 'login';
		}

		if ( $this->has_comments() ) {
			$this->essential[] = 'comments';
		}
	}

	/**
	 * Gets the essential cookies services formatted.
	 *
	 * @return array
	 */
	protected function get_essential_formatted() {
		$services             = $this->get_services();
		$essentials_formatted = array();
		foreach ( $this->essential as $essential_service ) {
			if ( ! isset( $services[ $essential_service ] ) ) {
				continue;
			}

			$service            = $services[ $essential_service ];
			$service['name']    = $essential_service;
			$service['service'] = $service['label'];
			$service['url']     = $service['service_url'];

			$essentials_formatted[] = $service;
		}

		return $essentials_formatted;
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
	 * Checks the HTML for a login form or links to the site's login url.
	 *
	 * @param string $html The HTML of the website.
	 *
	 * @return bool
	 */
	public function has_login( $html ) {
		// Let's see if we can find a login form.
		$document = wpconsent_get_simplehtmldom( $html );

		if ( ! $document ) {
			return false;
		}

		// Let's find all forms on the page.
		$forms = $document->find( 'form' );
		// Let's see if any of the forms have a password field.
		foreach ( $forms as $form ) {
			$password_fields = $form->find( 'input[type="password"]' );
			if ( ! empty( $password_fields ) ) {
				return true;
			}
		}

		// Let's see if we have a login link.
		$login_links = $document->find( 'a[href*="login"]' );
		if ( ! empty( $login_links ) ) {
			return true;
		}

		return false;
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
		return wp_remote_get(
			add_query_arg(
				array(
					'wpconsent_debug' => 'true',
				),
				home_url( '/' )
			),
			array(
				'sslverify' => false,
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
			'email'    => base64_encode( $email ),
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
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
