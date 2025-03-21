<?php
/**
 * Admin paged used to Manage Geolocation.
 *
 * @package WPConsent
 */

/**
 * Class WPConsent_Admin_Page_Geolocation
 */
class WPConsent_Admin_Page_Geolocation extends WPConsent_Admin_Page {
	/**
	 * Page slug.
	 *
	 * @var string
	 */
	public $page_slug = 'wpconsent-geolocation';

	/**
	 * Call this just to set the page title translatable.
	 */
	public function __construct() {
		$this->page_title = __( 'Geolocation', 'wpconsent-cookies-banner-privacy-suite' );
		parent::__construct();
	}

	/**
	 * Override the output method so we can add our form markup for this page.
	 *
	 * @return void
	 */
	public function output() {
		$this->output_header();
		?>
		<div class="wpconsent-content">
			<div class="wpconsent-blur-area">
			<?php
			$this->output_content();
			do_action( "wpconsent_admin_page_content_{$this->page_slug}", $this );
			?>
			</div>
			<?php
			echo WPConsent_Admin_page::get_upsell_box( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				esc_html__( 'Geolocation is a PRO feature', 'wpconsent-cookies-banner-privacy-suite' ),
				'<p>' . esc_html__( 'Upgrade to WPConsent PRO today and personalize the display of your cookie banner to show only in the specific countries or regions you choose.', 'wpconsent-cookies-banner-privacy-suite' ) . '</p>',
				array(
					'text' => esc_html__( 'Upgrade to PRO and Unlock "Geolocation"', 'wpconsent-cookies-banner-privacy-suite' ),
					'url'  => esc_url( wpconsent_utm_url( 'https://wpconsent.com/lite/', 'geolocation-page', 'main' ) ),
				),
				array(
					'text' => esc_html__( 'Learn more about all the features', 'wpconsent-cookies-banner-privacy-suite' ),
					'url'  => esc_url( wpconsent_utm_url( 'https://wpconsent.com/lite/', 'geolocation-page', 'features' ) ),
				)
			);
			?>
		</div>
		<?php
	}

	/**
	 * Output the content of the page.
	 *
	 * @return void
	 */
	public function output_content() {
		$this->metabox(
			__( 'Geolocation', 'wpconsent-cookies-banner-privacy-suite' ),
			$this->get_location_input()
		);
		wp_nonce_field( 'wpconsent_save_geolocation', 'wpconsent_save_geolocation_nonce' );
		?>
		<div class="wpconsent-submit">
			<button type="submit" name="save_changes" class="wpconsent-button" value="save">
				<?php esc_html_e( 'Save Changes', 'wpconsent-cookies-banner-privacy-suite' ); ?>
			</button>
		</div>
		<?php
	}

	/**
	 * Get the location input.
	 *
	 * @return string
	 */
	public function get_location_input() {
		ob_start();
		$this->metabox_row(
			__( 'Enable Geolocation', 'wpconsent-cookies-banner-privacy-suite' ),
			$this->get_checkbox_toggle(
				wpconsent()->settings->get_option( 'geolocation_enabled' ),
				'geolocation_enabled',
				__( 'Display the cookie banner just for visitors from the locations selected below.', 'wpconsent-cookies-banner-privacy-suite' )
			)
		);

		$this->metabox_row(
			__( 'EU Visitors', 'wpconsent-cookies-banner-privacy-suite' ),
			$this->get_checkbox_toggle(
				wpconsent()->settings->get_option( 'geolocation_eu' ),
				'geolocation_eu',
				__( 'Display the cookie banner for visitors from Europe.', 'wpconsent-cookies-banner-privacy-suite' )
			),
			'geolocation_eu'
		);

		$this->metabox_row(
			__( 'Countries', 'wpconsent-cookies-banner-privacy-suite' ),
			$this->get_country_picker(),
			'',
			'',
			'',
			esc_html__( 'Select the countries you want to display the cookie banner for.', 'wpconsent-cookies-banner-privacy-suite' )
		);

		$this->metabox_row(
			'',
			'<p class="wpconsent-disclaimer">' . __( 'Please note: your site visitors IP address will be sent in an anonymized format to our location API to determine their location.', 'wpconsent-cookies-banner-privacy-suite' ) . '</p>'
		);

		return ob_get_clean();
	}

	/**
	 * Get the country picker.
	 *
	 * @return string
	 */
	public function get_country_picker() {
		ob_start();
		?>
		<select name="geolocation_countries[]" id="geolocation_countries" multiple="multiple" class="wpconsent-choices" style="width: 100%;" placeholder="<?php esc_attr_e( 'Select countries', 'wpconsent-cookies-banner-privacy-suite' ); ?>"></select>
		<?php
		return ob_get_clean();
	}
}
