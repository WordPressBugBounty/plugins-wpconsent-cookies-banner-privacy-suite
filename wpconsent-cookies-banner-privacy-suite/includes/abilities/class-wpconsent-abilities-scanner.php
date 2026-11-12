<?php
/**
 * Abilities for the website scanner.
 *
 * @package WPConsent
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WPConsent_Abilities_Scanner class.
 */
class WPConsent_Abilities_Scanner extends WPConsent_Abilities_Group {

	/**
	 * Option that holds the time the next scan through an ability is allowed.
	 *
	 * It is written with a single INSERT so calls that arrive together cannot all take it,
	 * and read straight from the table so the options cache cannot hide it.
	 *
	 * @var string
	 */
	const SCAN_LOCK = 'wpconsent_abilities_scan_lock';

	/**
	 * Minimum time between two scans started through an ability, in seconds.
	 *
	 * @var int
	 */
	const SCAN_INTERVAL = 600;

	/**
	 * Stop starting new pages after this many seconds, so one call stays within request time limits.
	 *
	 * @var int
	 */
	const TIME_LIMIT = 40;

	/**
	 * Seconds to wait before another scan after one that failed.
	 *
	 * @var int
	 */
	const ERROR_RETRY = 60;

	/**
	 * Register the scanner abilities.
	 *
	 * @return void
	 */
	public function register() {
		$this->api->add(
			'get-scan-results',
			'read',
			array(
				'label'            => __( 'Get the last scan results', 'wpconsent-cookies-banner-privacy-suite' ),
				'description'      => __( 'Returns the results of the last website scan: the scripts found per category and the services that still need to be configured. The date is null when the site was never scanned.', 'wpconsent-cookies-banner-privacy-suite' ),
				'category'         => 'wpconsent-scanner',
				'output_schema'    => $this->scan_results_schema(),
				'execute_callback' => array( $this, 'get_scan_results' ),
			)
		);

		$this->api->add(
			'run-scan',
			'create',
			array(
				'label'            => __( 'Scan the website', 'wpconsent-cookies-banner-privacy-suite' ),
				'description'      => __( 'Scans the homepage and the pages selected for scanning, the same as the Scan button, and saves the results. Takes a while per page; a call is limited to about 40 seconds. When a page fails or there is not enough time for every page, complete is false and nothing is saved (ask the user to scan from the admin instead). Limited to one scan every 10 minutes (1 minute after a scan that failed); use get-scan-results to read the last results.', 'wpconsent-cookies-banner-privacy-suite' ),
				'category'         => 'wpconsent-scanner',
				'output_schema'    => array(
					'type'       => 'object',
					'properties' => array(
						'error'           => array( 'type' => 'boolean' ),
						'message'         => array( 'type' => 'string' ),
						'scripts'         => array( 'type' => array( 'object', 'array' ) ),
						'services_needed' => array( 'type' => 'array' ),
						'pages_scanned'   => array( 'type' => 'integer' ),
						'pages_total'     => array( 'type' => 'integer' ),
						'complete'        => array( 'type' => 'boolean' ),
					),
				),
				'execute_callback' => array( $this, 'run_scan' ),
			)
		);

		$this->api->add(
			'configure-detected-services',
			'update',
			array(
				'label'            => __( 'Configure the services found by the scan', 'wpconsent-cookies-banner-privacy-suite' ),
				'description'      => __( 'Adds the services found by the last scan, with their cookies, the same as the auto-configure button on the scanner page. Configures every service the scan found when "services" is not sent. Turning off script blocking removes consent protection: confirm with the user first, then send "confirm": true.', 'wpconsent-cookies-banner-privacy-suite' ),
				'category'         => 'wpconsent-scanner',
				'input_schema'     => array(
					'type'                 => 'object',
					'properties'           => array(
						'services'               => array(
							'type'        => 'array',
							'items'       => array( 'type' => 'string' ),
							'description' => __( 'Service slugs from services_needed in the scan results.', 'wpconsent-cookies-banner-privacy-suite' ),
						),
						'enable_script_blocking' => array( 'type' => 'boolean' ),
						'confirm'                => array( 'type' => 'boolean' ),
					),
					'additionalProperties' => false,
					'default'              => array(),
				),
				'output_schema'    => $this->scan_results_schema(),
				'execute_callback' => array( $this, 'configure_detected_services' ),
			)
		);
	}

	/**
	 * Execute callback for wpconsent/get-scan-results.
	 *
	 * @return array
	 */
	public function get_scan_results() {
		$scan = get_option( 'wpconsent_scanner_data', array() );
		$data = isset( $scan['data'] ) && is_array( $scan['data'] ) ? $scan['data'] : array();

		return array(
			'date'            => ! empty( $scan['date'] ) ? $scan['date'] : null,
			'configured'      => ! empty( $scan['configured'] ),
			'configured_time' => ! empty( $scan['configured_time'] ) ? $scan['configured_time'] : null,
			'scripts'         => isset( $data['scripts'] ) ? $data['scripts'] : array(),
			'services_needed' => isset( $data['services_needed'] ) ? array_values( (array) $data['services_needed'] ) : array(),
		);
	}

	/**
	 * Execute callback for wpconsent/run-scan.
	 *
	 * @return array|WP_Error
	 */
	public function run_scan() {
		// Take the lock before anything else, so of several calls that arrive together only one scans.
		if ( ! $this->lock_scan( time() + self::SCAN_INTERVAL ) ) {
			$retry_after = max( 1, $this->next_scan_time() - time() );

			return new WP_Error(
				'wpconsent_scan_rate_limited',
				sprintf(
					/* translators: %d: seconds until the next scan is allowed. */
					__( 'A scan ran recently. Try again in %d seconds, or read the last results with get-scan-results.', 'wpconsent-cookies-banner-privacy-suite' ),
					$retry_after
				),
				array( 'retry_after' => $retry_after )
			);
		}

		// The scanner matches scripts against the services list, so both must be loaded.
		$error = $this->api->load_components( array( 'services', 'scanner' ) );
		if ( $error ) {
			$this->unlock_scan();

			return $error;
		}
		$scanner = $this->api->component( 'scanner' );

		$results = $scanner->scan_site( self::TIME_LIMIT );
		if ( ! empty( $results['error'] ) ) {
			// Allow a retry after a short wait instead of the full interval, but never straight away,
			// so an agent cannot keep the scanner busy by calling again in a loop.
			$this->set_next_scan_time( time() + self::ERROR_RETRY );

			return new WP_Error(
				'wpconsent_scan_failed',
				! empty( $results['message'] ) ? $results['message'] : __( 'The website could not be scanned. Please try again.', 'wpconsent-cookies-banner-privacy-suite' )
			);
		}

		unset( $results['categories'] );

		// The lock is kept: running the scan again straight away will not get further.
		if ( empty( $results['complete'] ) ) {
			$results['message'] = sprintf(
				/* translators: 1: pages scanned, 2: pages to scan. */
				__( 'Only %1$d of %2$d pages could be scanned, so the results were not saved. Ask the user to run the scan from the Scan button in the WPConsent admin, which has no time limit.', 'wpconsent-cookies-banner-privacy-suite' ),
				$results['pages_scanned'],
				$results['pages_total']
			);
		}

		return $results;
	}

	/**
	 * Take the scan lock until the given time.
	 *
	 * An expired lock is removed first. The lock row is then inserted with a single query, so when
	 * several calls try at once the unique option name lets exactly one of them succeed.
	 *
	 * @param int $until Unix time until which no other scan may start.
	 *
	 * @return bool Whether this call took the lock.
	 */
	private function lock_scan( $until ) {
		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery -- The lock must bypass the options cache and be taken atomically.
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value < %d", self::SCAN_LOCK, time() ) );

		$inserted = $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %d, 'off')", self::SCAN_LOCK, $until ) );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery

		return 1 === $inserted;
	}

	/**
	 * The time the next scan is allowed, 0 when there is no lock.
	 *
	 * @return int
	 */
	private function next_scan_time() {
		global $wpdb;

		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", self::SCAN_LOCK ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Read from the table, not the options cache.
	}

	/**
	 * Change the time the next scan is allowed on the lock this call holds.
	 *
	 * @param int $until Unix time until which no other scan may start.
	 *
	 * @return void
	 */
	private function set_next_scan_time( $until ) {
		global $wpdb;

		$wpdb->update( $wpdb->options, array( 'option_value' => $until ), array( 'option_name' => self::SCAN_LOCK ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Written to the table, not the options cache.
	}

	/**
	 * Release the scan lock this call holds, when no scan was started.
	 *
	 * @return void
	 */
	private function unlock_scan() {
		global $wpdb;

		$wpdb->delete( $wpdb->options, array( 'option_name' => self::SCAN_LOCK ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Deleted from the table, not the options cache.
	}

	/**
	 * Execute callback for wpconsent/configure-detected-services.
	 *
	 * @param array $input Ability input.
	 *
	 * @return array|WP_Error
	 */
	public function configure_detected_services( $input ) {
		$confirmation = $this->api->require_confirmation( $input, array( 'enable_script_blocking' ) );
		if ( $confirmation ) {
			return $confirmation;
		}

		$error = $this->api->banner_required_error( ! empty( $input['enable_script_blocking'] ) );
		if ( ! $error ) {
			$error = $this->api->load_components( array( 'services', 'scanner', 'admin_ajax' ) );
		}
		if ( $error ) {
			return $error;
		}

		if ( isset( $input['services'] ) ) {
			$services = array_map( 'sanitize_text_field', $input['services'] );
		} else {
			$last     = $this->get_scan_results();
			$services = $last['services_needed'];
		}

		wpconsent_configure_scanned_services(
			$services,
			isset( $input['enable_script_blocking'] ) ? (int) $input['enable_script_blocking'] : null
		);

		return $this->get_scan_results();
	}

	/**
	 * Output schema for scan results.
	 *
	 * @return array
	 */
	protected function scan_results_schema() {
		return array(
			'type'       => 'object',
			'properties' => array(
				'date'            => array( 'type' => array( 'string', 'null' ) ),
				'configured'      => array( 'type' => 'boolean' ),
				'configured_time' => array( 'type' => array( 'string', 'null' ) ),
				'scripts'         => array( 'type' => array( 'object', 'array' ) ),
				'services_needed' => array(
					'type'  => 'array',
					'items' => array( 'type' => 'string' ),
				),
			),
		);
	}
}
