<?php
/**
 * Admin paged used for the site scanner.
 *
 * @package WPConsent
 */

/**
 * Class WPConsent_Admin_Page_Cookies.
 */
class WPConsent_Admin_Page_Scanner extends WPConsent_Admin_Page {

	use WPConsent_Services_Upsell;
	use WPConsent_Scan_Pages;

	/**
	 * Page slug.
	 *
	 * @var string
	 */
	public $page_slug = 'wpconsent-scanner';

	/**
	 * Default view.
	 *
	 * @var string
	 */
	public $view = 'scanner';

	/**
	 * Available views.
	 *
	 * @var array
	 */
	public $views = array();

	/**
	 * Scan results.
	 *
	 * @var array
	 */
	protected $scan_results;

	/**
	 * Current action.
	 *
	 * @var string
	 */
	protected $action;

	/**
	 * Call this just to set the page title translatable.
	 */
	public function __construct() {
		$this->page_title = __( 'Website Scanner', 'wpconsent-cookies-banner-privacy-suite' );
		$this->menu_title = __( 'Scanner', 'wpconsent-cookies-banner-privacy-suite' );
		parent::__construct();
	}

	/**
	 * Page specific Hooks.
	 *
	 * @return void
	 */
	public function page_hooks() {
		$this->views = array(
			'scanner'  => __( 'Scanner', 'wpconsent-cookies-banner-privacy-suite' ),
			'history'  => __( 'History', 'wpconsent-cookies-banner-privacy-suite' ),
			'settings' => __( 'Auto Scanning', 'wpconsent-cookies-banner-privacy-suite' ),
		);
	}

	/**
	 * For this page we output a menu.
	 *
	 * @return void
	 */
	public function output_header_bottom() {
		?>
		<ul class="wpconsent-admin-tabs">
			<?php
			foreach ( $this->views as $slug => $label ) {
				$class = $this->view === $slug ? 'active' : '';
				?>
				<li>
					<a href="<?php echo esc_url( $this->get_view_link( $slug ) ); ?>" class="<?php echo esc_attr( $class ); ?>"><?php echo esc_html( $label ); ?></a>
				</li>
			<?php } ?>
		</ul>
		<?php
	}

	/**
	 * Override the output method to handle upsell for History and Settings views.
	 *
	 * @return void
	 */
	public function output() {
		// For history and settings views, show upsell modal with blurred content.
		if ( 'history' === $this->view || 'settings' === $this->view ) {
			$this->output_header();
			?>
			<div class="wpconsent-content">
				<div class="wpconsent-blur-area">
					<?php
					if ( 'history' === $this->view ) {
						$this->output_view_history();
					} else {
						$this->output_view_settings();
					}
					?>
				</div>
				<?php
				if ( 'history' === $this->view ) {
					echo WPConsent_Admin_page::get_upsell_box( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						esc_html__( 'Scanning History is a PRO feature', 'wpconsent-cookies-banner-privacy-suite' ),
						'<p>' . esc_html__( 'Upgrade to WPConsent PRO to track all website scans over time. View detected services, monitor changes, and get notified when new services are found on your website.', 'wpconsent-cookies-banner-privacy-suite' ) . '</p>',
						array(
							'text' => esc_html__( 'Upgrade to PRO and Unlock "Scanning History"', 'wpconsent-cookies-banner-privacy-suite' ),
							'url'  => esc_url( wpconsent_utm_url( 'https://wpconsent.com/lite/', 'scanner-history-page', 'main' ) ),
						),
						array(
							'text' => esc_html__( 'Learn more about all the features', 'wpconsent-cookies-banner-privacy-suite' ),
							'url'  => esc_url( wpconsent_utm_url( 'https://wpconsent.com/lite/', 'scanner-history-page', 'features' ) ),
						)
					);
				} else {
					echo WPConsent_Admin_page::get_upsell_box( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						esc_html__( 'Scheduled Automatic Scans is a PRO feature', 'wpconsent-cookies-banner-privacy-suite' ),
						'<p>' . esc_html__( 'Upgrade to WPConsent PRO to automatically update your cookie configuration when new services are detected. Get email notifications and control how long scan history is retained.', 'wpconsent-cookies-banner-privacy-suite' ) . '</p>',
						array(
							'text' => esc_html__( 'Upgrade to PRO and Unlock "Scanner Settings"', 'wpconsent-cookies-banner-privacy-suite' ),
							'url'  => esc_url( wpconsent_utm_url( 'https://wpconsent.com/lite/', 'scanner-settings-page', 'main' ) ),
						),
						array(
							'text' => esc_html__( 'Learn more about all the features', 'wpconsent-cookies-banner-privacy-suite' ),
							'url'  => esc_url( wpconsent_utm_url( 'https://wpconsent.com/lite/', 'scanner-settings-page', 'features' ) ),
						)
					);
				}
				?>
			</div>
			<?php
			return;
		}

		// Default behavior for scanner view.
		parent::output();
	}

	/**
	 * Get the scan results in one place.
	 *
	 * @return array
	 */
	protected function get_scan_results() {
		if ( ! isset( $this->scan_results ) ) {
			$this->scan_results = wpconsent()->scanner->get_scan_data();
		}

		return $this->scan_results;
	}

	/**
	 * Output the page content.
	 *
	 * @return void
	 */
	public function output_content() {
		$this->metabox(
			esc_html__( 'Scan Overview', 'wpconsent-cookies-banner-privacy-suite' ),
			$this->get_scan_overview()
		);

		$scan_data = $this->get_scan_results();

		if ( empty( $scan_data['data']['scripts'] ) ) {
			return;
		}

		$this->metabox(
			__( 'Detailed Report', 'wpconsent-cookies-banner-privacy-suite' ),
			$this->get_scanner_input()
		);
	}

	/**
	 * Get the markup for the scanner.
	 *
	 * @return string
	 */
	public function get_scanner_input() {
		$previous_scan = $this->get_scan_results();
		$categories    = wpconsent()->cookies->get_categories();
		if ( ! empty( $previous_scan ) ) {
			$previous_data = $previous_scan['data'];
		}
		ob_start();
		?>
		<form action="" id="wpconsent-scanner-form">
			<p>
				<?php esc_html_e( 'Below you can see a list of scripts and integrations detected on your website that use cookies and that WPConsent can automatically detect. We recommend adding cookie information for all of them. Once added, you can edit the details on the settings page.', 'wpconsent-cookies-banner-privacy-suite' ); ?>
			</p>
			<?php
			if ( ! empty( $previous_data['scripts'] ) ) {
				foreach ( $previous_data['scripts'] as $category => $services ) {
					?>
					<h3><?php echo esc_html( $categories[ $category ]['name'] ); ?></h3>
					<div class="wpconsent-onboarding-selectable-list">
						<?php
						foreach ( $services as $service ) {
							$this->get_scan_service_template( $service );
						}
						?>
					</div>
					<?php
				}
			}
			?>
			<div class="<?php echo empty( $previous_data ) ? 'wpconsent-hidden' : ''; ?>" id="wpconsent-after-scan">
				<?php $this->services_upsell_box( 'scanner' ); ?>
				<label class="wpconsent-inline-styled-checkbox">
						<span class="wpconsent-styled-checkbox <?php echo wpconsent()->settings->get_option( 'enable_script_blocking', 1 ) ? 'checked' : ''; ?>">
							<input type="checkbox" name="script_blocking" <?php checked( wpconsent()->settings->get_option( 'enable_script_blocking', 1 ) ); ?>/>
						</span>
					<?php
					printf(
					// translators: %1$s is an opening link tag, %2$s is a closing link tag.
						esc_html__( 'Prevent known scripts from adding cookies before consent is given. %1$sLearn More%2$s', 'wpconsent-cookies-banner-privacy-suite' ),
						'<a target="_blank" rel="noopener noreferrer" href="' . esc_url( wpconsent_utm_url( 'https://wpconsent.com/docs/automatic-script-blocking', 'onboarding', 'scripb-blocking' ) ) . '">',
						'</a>'
					);
					?>
				</label>
				<div id="wpconsent-scanner-actions" class="wpconsent-metabox-form-row">
					<button class="wpconsent-button wpconsent-button-primary" id="wpconsent-save-scanner"><?php esc_html_e( 'Automatically Configure Cookies', 'wpconsent-cookies-banner-privacy-suite' ); ?></button>
				</div>
			</div>
			<input type="hidden" name="action" value="wpconsent_auto_configure"/>
			<?php wp_nonce_field( 'wpconsent_scan_website', 'wpconsent_scan_website_nonce' ); ?>
		</form>
		<?php
		return ob_get_clean();
	}

	/**
	 * Get the markup for a scanner service.
	 *
	 * @param array $data Service data.
	 *
	 * @return void
	 */
	public function get_scan_service_template( $data ) {
		?>
		<div class="wpconsent-onboarding-selectable-item wpconsent-show-hidden-container">
			<div class="wpconsent-onboarding-service-logo">
				<img src="<?php echo esc_url( $data['logo'] ); ?>" alt="<?php echo esc_attr( $data['service'] ); ?>"/>
			</div>
			<div class="wpconsent-onboarding-service-info">
				<h3><?php echo esc_html( $data['service'] ); ?></h3>
				<p><?php echo esc_html( $data['description'] ); ?></p>
				<div class="wpconsent-service-info-buttons">
					<?php if ( ! empty( $data['url'] ) ) { ?>
						<a href="<?php echo esc_url( $data['url'] ); ?>" class="wpconsent-button wpconsent-button-text" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Service URL', 'wpconsent-cookies-banner-privacy-suite' ); ?></a>
					<?php } ?>
					<?php if ( ! empty( $data['cookies'] ) ) { ?>
						<button type="button" class="wpconsent-button wpconsent-button-text wpconsent-show-hidden" data-target=".wpconsent-scanner-service-cookies-list" data-hide-label="<?php esc_attr_e( 'Hide Cookies', 'wpconsent-cookies-banner-privacy-suite' ); ?>"><?php esc_html_e( 'View Cookies', 'wpconsent-cookies-banner-privacy-suite' ); ?></button>
					<?php } ?>
					<?php if ( ! empty( $data['html'] ) ) { ?>
						<button type="button" class="wpconsent-button wpconsent-button-text wpconsent-show-hidden" data-target=".wpconsent-script-preview" data-hide-label="<?php esc_attr_e( 'Hide Script', 'wpconsent-cookies-banner-privacy-suite' ); ?>"><?php esc_html_e( 'View Script', 'wpconsent-cookies-banner-privacy-suite' ); ?></button>
					<?php } ?>
				</div>
				<?php if ( ! empty( $data['cookies'] ) ) { ?>
					<ul class="wpconsent-scanner-service-cookies-list wpconsent-hidden-preview">
						<?php foreach ( $data['cookies'] as $cookie => $cookie_data ) { ?>
							<li><?php echo esc_html( $cookie ); ?></li>
						<?php } ?>
					</ul>
				<?php } ?>
				<?php if ( ! empty( $data['html'] ) ) { ?>
					<pre class="wpconsent-script-preview wpconsent-hidden-preview"><?php echo esc_html( $data['html'] ); ?></pre>
				<?php } ?>
			</div>
			<div class="wpconsent-onboarding-service-checkbox">
				<label>
					<span class="wpconsent-styled-checkbox checked">
						<input type="checkbox" name="scanner_service[]" value="<?php echo esc_attr( $data['name'] ); ?>" checked/>
					</span>
				</label>
			</div>
		</div>
		<?php
	}

	/**
	 * Returns the scan overview markup.
	 *
	 * @return string
	 */
	public function get_scan_overview() {
		$scan_results = $this->get_scan_results();

		if ( ! empty( $scan_results ) ) {
			$scripts            = $scan_results['data']['scripts'];
			$last_ran_timestamp = $scan_results['date'];

			$scripts_count = 0;
			$cookies_count = 0;
			foreach ( $scripts as $category => $services ) {
				$scripts_count += count( $services );
				foreach ( $services as $service ) {
					$cookies_count += count( $service['cookies'] );
				}
			}
			$next_scheduled_scan = esc_html__( 'Not Scheduled', 'wpconsent-cookies-banner-privacy-suite' );
			if ( wpconsent()->settings->get_option( 'auto_scanner', 0 ) ) {
				$interval            = wpconsent()->settings->get_option( 'auto_scanner_interval', 1 );
				$last_ran            = wpconsent()->settings->get_option( 'auto_scanner_last_ran', 0 );
				$next_scheduled_scan = date_i18n( get_option( 'date_format' ), strtotime( "+{$interval} days", $last_ran ) );
			}

			$stats_to_show = array(
				array(
					'label' => __( 'Services Detected', 'wpconsent-cookies-banner-privacy-suite' ),
					'value' => $scripts_count,
				),
				array(
					'label' => __( 'Cookies In Use', 'wpconsent-cookies-banner-privacy-suite' ),
					'value' => $cookies_count,
				),
				array(
					'label' => __( 'Last Successful Scan', 'wpconsent-cookies-banner-privacy-suite' ),
					'value' => date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $last_ran_timestamp ) ),
				),
				array(
					'label' => __( 'Cookies Configured', 'wpconsent-cookies-banner-privacy-suite' ),
					'value' => isset( $scan_results['configured'] ),
				),
				array(
					'label' => __( 'Next Scheduled Scan', 'wpconsent-cookies-banner-privacy-suite' ),
					'value' => $next_scheduled_scan,
				),
			);
		}
		ob_start();
		if ( ! empty( $stats_to_show ) ) {
			?>
			<div class="wpconsent-scan-overview">
				<?php foreach ( $stats_to_show as $stat ) { ?>
					<div class="wpconsent-scan-overview-stat">
						<h3><?php echo esc_html( $stat['label'] ); ?></h3>
						<p>
							<?php if ( is_bool( $stat['value'] ) ) { ?>
								<span class="wpconsent-faux-checkbox <?php echo $stat['value'] ? esc_attr( 'wpconsent-checked' ) : ''; ?>"></span>
							<?php } else { ?>
								<?php echo esc_html( $stat['value'] ); ?>
							<?php } ?></p>
					</div>
				<?php } ?>
			</div>
		<?php } ?>

		<?php
		$this->metabox_row_separator();
		echo $this->get_manual_scan_input();  // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		?>

		<div class="wpconsent-metabox-form-row">
			<button id="wpconsent-start-scanner" class="wpconsent-button wpconsent-button-primary" type="button" data-action="reload"><?php esc_html_e( 'Scan Your Website', 'wpconsent-cookies-banner-privacy-suite' ); ?></button>
		</div>
		<p class="wpconsent-disclaimer">
			<?php
			printf(
			// translators: %1$s is an opening link tag, %2$s is a closing link tag, %3$s is an opening link tag.
				esc_html__( 'Please Note: By continuing with the website scan, you agree to send website data to our API for processing. This data is utilized to improve scanning accuracy and provide updated service and cookie descriptions. For details, please review our %1$sPrivacy Policy%2$s and %3$sTerms of Service%2$s.', 'wpconsent-cookies-banner-privacy-suite' ),
				'<a href="' . esc_url( wpconsent_utm_url( 'https://wpconsent.com/privacy-policy/', 'onboarding', 'privacy-policy' ) ) . '" target="_blank" rel="noopener noreferrer">',
				'</a>',
				'<a href="' . esc_url( wpconsent_utm_url( 'https://wpconsent.com/terms/', 'onboarding', 'terms-of-service' ) ) . '" target="_blank" rel="noopener noreferrer">'
			);
			?>
		</p>
		<?php
		return ob_get_clean();
	}

	/**
	 * Get the markup for the manual scan input.
	 *
	 * @return string
	 */
	public function get_manual_scan_input() {
		// Auto-populate important pages.
		$this->auto_populate_important_pages();

		ob_start();
		$selected_content_ids = wpconsent()->settings->get_option( 'manual_scan_pages', array() );

		$pages_args = array(
			'number'  => 20,
			'orderby' => 'title',
			'order'   => 'ASC',
		);
		if ( ! empty( $selected_content_ids ) ) {
			$pages_args['exclude'] = $selected_content_ids;
		}
		// Let's pre-load 20 pages.
		$pages = get_pages( $pages_args );
		?>
		<div class="wpconsent-manual-scan-description">
			<h3><?php esc_html_e( 'Select content to scan:', 'wpconsent-cookies-banner-privacy-suite' ); ?></h3>
		</div>
		<div class="wpconsent-manual-scan-row">
			<div class="wpconsent-inline-select-group">
				<select id="manual-scanner-page" name="manual_scanner_page" class="wpconsent-choices wpconsent-page-search" data-placeholder="<?php esc_attr_e( 'Search for a post/page...', 'wpconsent-cookies-banner-privacy-suite' ); ?>" data-search="true" data-ajax-action="wpconsent_search_content" data-ajax="true">
					<option value="0"><?php esc_html_e( 'Choose Page', 'wpconsent-cookies-banner-privacy-suite' ); ?></option>
					<?php
					foreach ( $pages as $page ) {
						?>
						<option value="<?php echo esc_attr( $page->ID ); ?>" data-url="<?php echo esc_url( get_permalink( $page->ID ) ); ?>">
							<?php printf( '%1$s (#%2$d)', esc_html( $page->post_title ), esc_attr( $page->ID ) ); ?>
						</option>
						<?php
					}
					?>
				</select>
			</div>
			<div class="wpconsent-scanner-selected-items-container">
				<!-- Homepage always scanned -->
				<div class="wpconsent-scanner-selected-item homepage" id="scanner-item-home">
					<div class="wpconsent-scanner-selected-item-info">
						<h3><?php esc_html_e( 'Home Page', 'wpconsent-cookies-banner-privacy-suite' ); ?></h3>
						<p><?php esc_html_e( 'Always Scanned', 'wpconsent-cookies-banner-privacy-suite' ); ?></p>
					</div>
				</div>
				<?php
				// Load saved selections.
				if ( ! empty( $selected_content_ids ) ) {
					foreach ( $selected_content_ids as $page_id ) {
						$page = get_post( $page_id );
						if ( $page ) {
							?>
							<div class="wpconsent-scanner-selected-item" id="scanner-item-<?php echo esc_attr( $page->ID ); ?>">
								<div class="wpconsent-scanner-selected-item-info">
									<h3><?php printf( '%1$s (#%2$d)', esc_html( $page->post_title ), esc_attr( $page->ID ) ); ?></h3>
									<a href="<?php echo esc_url( get_permalink( $page->ID ) ); ?>" target="_blank" rel="noopener noreferrer">
										<?php echo esc_html( wp_parse_url( get_permalink( $page->ID ), PHP_URL_PATH ) ); ?>
									</a>
								</div>
								<button type="button" class="wpconsent-remove-item" data-id="<?php echo esc_attr( $page->ID ); ?>">
									<span class="dashicons dashicons-no-alt"></span>
								</button>
								<input type="hidden" name="scanner_items[]" value="<?php echo esc_attr( $page->ID ); ?>">
							</div>
							<?php
						}
					}
				}
				?>
			</div>
		</div>

		<?php
		return ob_get_clean();
	}

	/**
	 * Output the history view with dummy data.
	 *
	 * @return void
	 */
	protected function output_view_history() {
		$dummy_history = $this->get_dummy_scan_history();
		ob_start();
		?>
		<div class="wpconsent-scan-history-table">
		<p><?php esc_html_e( 'View the history of all website scans. Track new services detected over time and monitor changes to your website\'s cookie usage.', 'wpconsent-cookies-banner-privacy-suite' ); ?></p>
		<div class="tablenav top">
			<div class="actions alignleft">
				<button type="button" class="button"><?php esc_html_e( 'Export CSV', 'wpconsent-cookies-banner-privacy-suite' ); ?></button>
			</div>
			<div class="tablenav-pages">
				<span class="displaying-num"><?php echo esc_html( count( $dummy_history ) . ' ' . __( 'items', 'wpconsent-cookies-banner-privacy-suite' ) ); ?></span>
			</div>
		</div>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th scope="col" class="column-scan_date"><?php esc_html_e( 'Scan Date', 'wpconsent-cookies-banner-privacy-suite' ); ?></th>
					<th scope="col" class="column-services_detected"><?php esc_html_e( 'Services Detected', 'wpconsent-cookies-banner-privacy-suite' ); ?></th>
					<th scope="col" class="column-new_services_count"><?php esc_html_e( 'New Services', 'wpconsent-cookies-banner-privacy-suite' ); ?></th>
					<th scope="col" class="column-removed_services_count"><?php esc_html_e( 'Removed Services', 'wpconsent-cookies-banner-privacy-suite' ); ?></th>
					<th scope="col" class="column-status"><?php esc_html_e( 'Status', 'wpconsent-cookies-banner-privacy-suite' ); ?></th>
					<th scope="col" class="column-actions"><?php esc_html_e( 'Actions', 'wpconsent-cookies-banner-privacy-suite' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $dummy_history as $item ) : ?>
					<tr>
						<td class="column-scan_date"><?php echo esc_html( $item['scan_date'] ); ?></td>
						<td class="column-services_detected"><?php echo esc_html( $item['services_detected'] ); ?></td>
						<td class="column-new_services_count">
							<?php if ( $item['new_services'] > 0 ) : ?>
								<span class="wpconsent-badge wpconsent-badge-new"><?php echo esc_html( $item['new_services'] ); ?></span>
							<?php else : ?>
								<?php echo esc_html( $item['new_services'] ); ?>
							<?php endif; ?>
						</td>
						<td class="column-removed_services_count">
							<?php if ( $item['removed_services'] > 0 ) : ?>
								<span class="wpconsent-badge wpconsent-badge-removed"><?php echo esc_html( $item['removed_services'] ); ?></span>
							<?php else : ?>
								<?php echo esc_html( $item['removed_services'] ); ?>
							<?php endif; ?>
						</td>
						<td class="column-status">
							<?php foreach ( $item['status'] as $status ) : ?>
								<span class="wpconsent-badge wpconsent-badge-<?php echo esc_attr( $status['class'] ); ?>"><?php echo esc_html( $status['label'] ); ?></span>
							<?php endforeach; ?>
						</td>
						<td class="column-actions">
							<div class="wpconsent-scan-history-actions">
								<button type="button" class="wpconsent-button wpconsent-button-primary"><?php esc_html_e( 'View Details', 'wpconsent-cookies-banner-privacy-suite' ); ?></button>
								<button type="button" class="wpconsent-button wpconsent-button-secondary"><?php esc_html_e( 'Delete', 'wpconsent-cookies-banner-privacy-suite' ); ?></button>
							</div>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
			<tfoot>
				<tr>
					<th scope="col" class="column-scan_date"><?php esc_html_e( 'Scan Date', 'wpconsent-cookies-banner-privacy-suite' ); ?></th>
					<th scope="col" class="column-services_detected"><?php esc_html_e( 'Services Detected', 'wpconsent-cookies-banner-privacy-suite' ); ?></th>
					<th scope="col" class="column-new_services_count"><?php esc_html_e( 'New Services', 'wpconsent-cookies-banner-privacy-suite' ); ?></th>
					<th scope="col" class="column-removed_services_count"><?php esc_html_e( 'Removed Services', 'wpconsent-cookies-banner-privacy-suite' ); ?></th>
					<th scope="col" class="column-status"><?php esc_html_e( 'Status', 'wpconsent-cookies-banner-privacy-suite' ); ?></th>
					<th scope="col" class="column-actions"><?php esc_html_e( 'Actions', 'wpconsent-cookies-banner-privacy-suite' ); ?></th>
				</tr>
			</tfoot>
		</table>
		</div>
		<?php
		$content = ob_get_clean();

		$this->metabox(
			esc_html__( 'Scan History', 'wpconsent-cookies-banner-privacy-suite' ),
			$content
		);
	}

	/**
	 * Get dummy scan history data.
	 *
	 * @return array
	 */
	protected function get_dummy_scan_history() {
		return array(
			array(
				'scan_date'         => wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( '-1 day' ) ),
				'services_detected' => 8,
				'new_services'      => 2,
				'removed_services'  => 0,
				'status'            => array(
					array(
						'class' => 'auto-updated',
						'label' => __( 'Auto-Updated', 'wpconsent-cookies-banner-privacy-suite' ),
					),
					array(
						'class' => 'email-sent',
						'label' => __( 'Email Sent', 'wpconsent-cookies-banner-privacy-suite' ),
					),
				),
			),
			array(
				'scan_date'         => wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( '-8 days' ) ),
				'services_detected' => 6,
				'new_services'      => 1,
				'removed_services'  => 0,
				'status'            => array(
					array(
						'class' => 'auto-updated',
						'label' => __( 'Auto-Updated', 'wpconsent-cookies-banner-privacy-suite' ),
					),
				),
			),
			array(
				'scan_date'         => wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( '-15 days' ) ),
				'services_detected' => 5,
				'new_services'      => 0,
				'removed_services'  => 1,
				'status'            => array(
					array(
						'class' => 'neutral',
						'label' => __( 'Completed', 'wpconsent-cookies-banner-privacy-suite' ),
					),
				),
			),
			array(
				'scan_date'         => wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( '-22 days' ) ),
				'services_detected' => 6,
				'new_services'      => 0,
				'removed_services'  => 0,
				'status'            => array(
					array(
						'class' => 'neutral',
						'label' => __( 'Completed', 'wpconsent-cookies-banner-privacy-suite' ),
					),
				),
			),
			array(
				'scan_date'         => wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( '-29 days' ) ),
				'services_detected' => 6,
				'new_services'      => 1,
				'removed_services'  => 0,
				'status'            => array(
					array(
						'class' => 'auto-updated',
						'label' => __( 'Auto-Updated', 'wpconsent-cookies-banner-privacy-suite' ),
					),
					array(
						'class' => 'email-sent',
						'label' => __( 'Email Sent', 'wpconsent-cookies-banner-privacy-suite' ),
					),
				),
			),
			array(
				'scan_date'         => wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( '-36 days' ) ),
				'services_detected' => 5,
				'new_services'      => 0,
				'removed_services'  => 0,
				'status'            => array(
					array(
						'class' => 'neutral',
						'label' => __( 'Completed', 'wpconsent-cookies-banner-privacy-suite' ),
					),
				),
			),
			array(
				'scan_date'         => wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( '-43 days' ) ),
				'services_detected' => 5,
				'new_services'      => 2,
				'removed_services'  => 0,
				'status'            => array(
					array(
						'class' => 'auto-updated',
						'label' => __( 'Auto-Updated', 'wpconsent-cookies-banner-privacy-suite' ),
					),
				),
			),
			array(
				'scan_date'         => wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( '-50 days' ) ),
				'services_detected' => 3,
				'new_services'      => 0,
				'removed_services'  => 1,
				'status'            => array(
					array(
						'class' => 'email-sent',
						'label' => __( 'Email Sent', 'wpconsent-cookies-banner-privacy-suite' ),
					),
				),
			),
			array(
				'scan_date'         => wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( '-57 days' ) ),
				'services_detected' => 4,
				'new_services'      => 1,
				'removed_services'  => 0,
				'status'            => array(
					array(
						'class' => 'auto-updated',
						'label' => __( 'Auto-Updated', 'wpconsent-cookies-banner-privacy-suite' ),
					),
				),
			),
			array(
				'scan_date'         => wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( '-64 days' ) ),
				'services_detected' => 3,
				'new_services'      => 0,
				'removed_services'  => 0,
				'status'            => array(
					array(
						'class' => 'neutral',
						'label' => __( 'Completed', 'wpconsent-cookies-banner-privacy-suite' ),
					),
				),
			),
		);
	}

	/**
	 * Output the settings view with dummy data.
	 *
	 * @return void
	 */
	protected function output_view_settings() {
		ob_start();
		?>
		<form method="post" action="">
			<!-- Auto Scanning -->
			<div class="wpconsent-metabox-form-row">
				<div class="wpconsent-metabox-form-row-label">
					<label for="auto_scanner"><?php esc_html_e( 'Auto Scanning', 'wpconsent-cookies-banner-privacy-suite' ); ?></label>
				</div>
				<div class="wpconsent-metabox-form-row-input">
					<label class="wpconsent-checkbox-toggle">
						<input type="checkbox" name="auto_scanner" id="auto_scanner" value="1" checked disabled />
						<span class="wpconsent-checkbox-toggle-slider"></span>
					</label>
					<p class="description"><?php esc_html_e( 'Automatically scan your website in the background to detect services that may track your visitors.', 'wpconsent-cookies-banner-privacy-suite' ); ?></p>
				</div>
			</div>

			<?php $this->metabox_row_separator(); ?>

			<!-- Scan Interval -->
			<div class="wpconsent-metabox-form-row">
				<div class="wpconsent-metabox-form-row-label">
					<label for="auto_scanner_interval"><?php esc_html_e( 'Scan Interval', 'wpconsent-cookies-banner-privacy-suite' ); ?></label>
				</div>
				<div class="wpconsent-metabox-form-row-input">
					<select name="auto_scanner_interval" id="auto_scanner_interval" class="wpconsent-select" disabled>
						<option value="1" selected><?php esc_html_e( 'Daily', 'wpconsent-cookies-banner-privacy-suite' ); ?></option>
						<option value="7"><?php esc_html_e( 'Weekly', 'wpconsent-cookies-banner-privacy-suite' ); ?></option>
						<option value="30"><?php esc_html_e( 'Monthly', 'wpconsent-cookies-banner-privacy-suite' ); ?></option>
					</select>
					<p class="description"><?php esc_html_e( 'Choose how often to automatically scan your website for tracking services.', 'wpconsent-cookies-banner-privacy-suite' ); ?></p>
				</div>
			</div>

			<?php $this->metabox_row_separator(); ?>

			<!-- Auto-Update Services -->
			<div class="wpconsent-metabox-form-row">
				<div class="wpconsent-metabox-form-row-label">
					<label for="scanner_auto_update"><?php esc_html_e( 'Auto-Update Services', 'wpconsent-cookies-banner-privacy-suite' ); ?></label>
				</div>
				<div class="wpconsent-metabox-form-row-input">
					<label class="wpconsent-checkbox-toggle">
						<input type="checkbox" name="scanner_auto_update" id="scanner_auto_update" value="1" checked disabled />
						<span class="wpconsent-checkbox-toggle-slider"></span>
					</label>
					<p class="description"><?php esc_html_e( 'Automatically add newly detected services to your cookie configuration.', 'wpconsent-cookies-banner-privacy-suite' ); ?></p>
					<p><strong><?php esc_html_e( 'Note:', 'wpconsent-cookies-banner-privacy-suite' ); ?></strong> <?php esc_html_e( 'The scanner only adds new services, it never removes existing ones. This is by design since some services may only load on pages that are not scanned.', 'wpconsent-cookies-banner-privacy-suite' ); ?></p>
				</div>
			</div>

			<?php $this->metabox_row_separator(); ?>

			<!-- Email Notifications -->
			<div class="wpconsent-metabox-form-row">
				<div class="wpconsent-metabox-form-row-label">
					<label for="scanner_email_notifications"><?php esc_html_e( 'Email Notifications', 'wpconsent-cookies-banner-privacy-suite' ); ?></label>
				</div>
				<div class="wpconsent-metabox-form-row-input">
					<label class="wpconsent-checkbox-toggle">
						<input type="checkbox" name="scanner_email_notifications" id="scanner_email_notifications" value="1" checked disabled />
						<span class="wpconsent-checkbox-toggle-slider"></span>
					</label>
					<p class="description"><?php esc_html_e( 'Send email notifications when new services are detected on your website.', 'wpconsent-cookies-banner-privacy-suite' ); ?></p>
				</div>
			</div>

			<!-- Email Addresses -->
			<div class="wpconsent-metabox-form-row">
				<div class="wpconsent-metabox-form-row-label">
					<label for="scanner_email_addresses"><?php esc_html_e( 'Notification Email Addresses', 'wpconsent-cookies-banner-privacy-suite' ); ?></label>
				</div>
				<div class="wpconsent-metabox-form-row-input">
					<input type="text" name="scanner_email_addresses" id="scanner_email_addresses" value="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>" class="wpconsent-input-email" disabled />
					<p class="description"><?php esc_html_e( 'Comma-separated list of email addresses to receive notifications. Leave empty to use the admin email.', 'wpconsent-cookies-banner-privacy-suite' ); ?></p>
					<p>
						<button type="button" class="wpconsent-button wpconsent-button-secondary" disabled><?php esc_html_e( 'Send Test Email', 'wpconsent-cookies-banner-privacy-suite' ); ?></button>
					</p>
				</div>
			</div>

			<?php $this->metabox_row_separator(); ?>

			<!-- History Retention -->
			<div class="wpconsent-metabox-form-row">
				<div class="wpconsent-metabox-form-row-label">
					<label for="scanner_history_retention"><?php esc_html_e( 'History Retention (Days)', 'wpconsent-cookies-banner-privacy-suite' ); ?></label>
				</div>
				<div class="wpconsent-metabox-form-row-input">
					<input type="number" name="scanner_history_retention" id="scanner_history_retention" class="wpconsent-regular-text wpconsent-input-number" value="90" min="0" max="365" disabled />
					<p><?php esc_html_e( 'How long to keep scan history records. Set to 0 to keep forever.', 'wpconsent-cookies-banner-privacy-suite' ); ?></p>
				</div>
			</div>

			<div class="wpconsent-metabox-form-row">
				<button type="button" class="wpconsent-button wpconsent-button-primary" disabled><?php esc_html_e( 'Save Settings', 'wpconsent-cookies-banner-privacy-suite' ); ?></button>
			</div>
		</form>
		<?php
		$content = ob_get_clean();

		$this->metabox(
			esc_html__( 'Scanner Settings', 'wpconsent-cookies-banner-privacy-suite' ),
			$content
		);
	}
}
