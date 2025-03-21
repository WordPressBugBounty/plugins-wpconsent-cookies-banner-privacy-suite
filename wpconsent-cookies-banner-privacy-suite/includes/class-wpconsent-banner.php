<?php
/**
 * Class used to load and output the cookie banner.
 *
 * @package WPConsent
 */

/**
 * Class WPConsent_Banner.
 */
class WPConsent_Banner {

	/**
	 * Banner settings.
	 *
	 * @var array
	 */
	public $settings = array();

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->hooks();
	}

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function hooks() {
		add_action( 'wp_footer', array( $this, 'maybe_output_banner' ) );
	}

	/**
	 * Output the banner if enabled.
	 *
	 * @return void
	 */
	public function maybe_output_banner() {
		if ( ! $this->is_enabled() ) {
			return;
		}

		// Don't load in legacy widget preview in the block editor.
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return;
		}

		// Don't load in the customizer preview.
		if ( is_customize_preview() ) {
			return;
		}

		$this->output_banner();
	}

	/**
	 * Check if the banner is enabled.
	 *
	 * @return bool
	 */
	public function is_enabled() {
		return wpconsent()->settings->get_option( 'enable_consent_banner' );
	}

	/**
	 * Output the banner with proper escaping.
	 *
	 * @return void
	 */
	public function output_banner() {
		// Allowed tags we start with the ones from wp_kses_post.
		$allowed_tags = wp_kses_allowed_html( 'post' );

		// Add the SVG tags from our icons.
		$allowed_tags = array_merge( $allowed_tags, wpconsent_get_icon_allowed_tags() );
		// Let's allow tabindex attribute.
		$allowed_tags['div']['tabindex'] = true;

		// Get colors and create CSS variables.
		$colors   = $this->get_color_settings();
		$css_vars = $this->get_css_variables( $colors );

		// Create the Shadow DOM container with CSS variables.
		echo '<div id="wpconsent-root" style="' . esc_attr( $css_vars ) . '">';
		echo '<div id="wpconsent-container"></div>';

		// Create a template that contains both styles and HTML.
		echo '<template id="wpconsent-template">';

		// Add the banner HTML to the template.
		echo wp_kses( $this->get_banner(), $allowed_tags );

		// Add the preferences modal to the template.
		echo $this->get_preferences_modal(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		// Add the floating button to the template.
		$this->floating_consent_button();

		echo '</template>';
		echo '</div>';
	}

	/**
	 * Get CSS variables string from colors array.
	 *
	 * @param array $colors Color settings.
	 *
	 * @return string
	 */
	private function get_css_variables( $colors ) {
		$vars = array(
			'--wpconsent-z-index: 900000;',
			'--wpconsent-background: ' . $colors['background'] . ';',
			'--wpconsent-text: ' . $colors['text'] . ';',
			'--wpconsent-accept-bg: ' . $colors['accept_bg'] . ';',
			'--wpconsent-cancel-bg: ' . $colors['cancel_bg'] . ';',
			'--wpconsent-preferences-bg: ' . $colors['preferences_bg'] . ';',
			'--wpconsent-accept-color: ' . $colors['accept_color'] . ';',
			'--wpconsent-cancel-color: ' . $colors['cancel_color'] . ';',
			'--wpconsent-preferences-color: ' . $colors['preferences_color'] . ';',
			'--wpconsent-font-size: ' . $colors['font_size'] . ';',
		);

		return implode( ';', $vars );
	}

	/**
	 * Get the banner markup.
	 *
	 * @return string
	 */
	public function get_banner() {
		$font_size       = wpconsent()->settings->get_option( 'banner_font_size', '16px' );
		$close_text      = esc_attr__( 'Close', 'wpconsent-cookies-banner-privacy-suite' );
		$button_size     = wpconsent()->settings->get_option( 'banner_button_size', 'regular' );
		$button_corner   = wpconsent()->settings->get_option( 'banner_button_corner', 'slightly-rounded' );
		$button_type     = wpconsent()->settings->get_option( 'banner_button_type', 'filled' );
		$button_order    = wpconsent()->settings->get_option(
			'button_order',
			array(
				'accept',
				'cancel',
				'preferences',
			)
		);
		$banner_layout   = wpconsent()->settings->get_option( 'banner_layout', 'long' );
		$banner_position = wpconsent()->settings->get_option( 'banner_position', 'top' );
		$position_class  = ! empty( $banner_position ) ? 'wpconsent-banner-' . esc_attr( $banner_layout ) . '-' . esc_attr( $banner_position ) : '';
		$logo            = wpconsent()->settings->get_option( 'banner_logo', '' );

		$html = '<div class="wpconsent-banner-holder wpconsent-banner-' . esc_attr( $banner_layout ) . ' ' . $position_class . '" id="wpconsent-banner-holder" tabindex="-1" aria-labelledby="wpconsent-banner-title" role="dialog" aria-modal="true">';

		$html .= '<div class="wpconsent-banner">';
		$html .= '<button class="wpconsent-banner-close" id="wpconsent-banner-close" aria-label="' . esc_attr( $close_text ) . '">' . wpconsent_get_icon( 'close', 12, 12 ) . '</button>';
		if ( ! empty( $logo ) ) {
			$html .= '<div class="wpconsent-banner-header">';

			$site_name = get_bloginfo( 'name' );

			$html .= '<div class="wpconsent-banner-logo"><img height="30" src="' . esc_url( $logo ) . '" alt="' . esc_html( $site_name ) . '" /></div>';
			$html .= '</div>';
		}

		$text = wpconsent()->settings->get_option( 'banner_message', esc_html__( 'This website uses cookies to ensure you get the best experience on our website.', 'wpconsent-cookies-banner-privacy-suite' ) );

		$html .= '<div class="wpconsent-banner-body">';
		$html .= '<h2 id="wpconsent-banner-title" class="screen-reader-text">' . esc_html__( 'Cookie Consent', 'wpconsent-cookies-banner-privacy-suite' ) . '</h2>';
		$html .= '<div class="wpconsent-banner-message" tabindex="0">' . wp_kses_post( wpautop( $text ) ) . '</div>';
		$html .= '</div>';

		$html .= '<div class="wpconsent-banner-footer wpconsent-button-size-' . esc_attr( $button_size ) . ' wpconsent-button-corner-' . esc_attr( $button_corner ) . ' wpconsent-button-type-' . esc_attr( $button_type ) . '">';

		foreach ( $button_order as $button_id ) {
			$enabled = wpconsent()->settings->get_option( $button_id . '_button_enabled', true );
			if ( ! $enabled ) {
				continue;
			}
			$button_text = wpconsent()->settings->get_option( $button_id . '_button_text', '' );

			$html .= '<button type="button" id="wpconsent-' . esc_attr( $button_id ) . '-all" class="wpconsent-' . esc_attr( $button_id ) . '-cookies wpconsent-banner-button wpconsent-' . esc_attr( $button_id ) . '-all">' . esc_html( $button_text ) . '</button>';
		}

		$html .= '</div>'; // .wpconsent-banner-footer
		$html .= $this->powered_by();
		$html .= '</div>'; // .wpconsent-banner
		$html .= '</div>';// .wpconsent-banner-holder

		return $html;
	}

	/**
	 * Get the color settings.
	 *
	 * @return array
	 */
	public function get_color_settings() {
		return array(
			'background'        => wpconsent()->settings->get_option( 'banner_background_color', '#FFFFFF' ),
			'text'              => wpconsent()->settings->get_option( 'banner_text_color', '#000000' ),
			'button_text'       => wpconsent()->settings->get_option( 'banner_button_text_color', '#FFFFFF' ),
			'accept_bg'         => wpconsent()->settings->get_option( 'banner_accept_bg', '#0073AA' ),
			'cancel_bg'         => wpconsent()->settings->get_option( 'banner_cancel_bg', '#0073AA' ),
			'preferences_bg'    => wpconsent()->settings->get_option( 'banner_preferences_bg', '#0073AA' ),
			'accept_color'      => wpconsent()->settings->get_option( 'banner_accept_color', '#FFFFFF' ),
			'cancel_color'      => wpconsent()->settings->get_option( 'banner_cancel_color', '#FFFFFF' ),
			'preferences_color' => wpconsent()->settings->get_option( 'banner_preferences_color', '#FFFFFF' ),
			'font_size'         => wpconsent()->settings->get_option( 'banner_font_size', '16px' ),
		);
	}

	/**
	 * Get the preferences modal.
	 *
	 * @return string
	 */
	public function get_preferences_modal() {
		$categories         = wpconsent()->cookies->get_categories();
		$accept_button_text = wpconsent()->settings->get_option( 'accept_button_text', '' );
		$logo               = wpconsent()->settings->get_option( 'banner_logo', '' );

		$html = '<div id="wpconsent-preferences-modal" class="wpconsent-preferences-modal" style="display:none;" tabindex="-1" role="dialog" aria-labelledby="wpconsent-preferences-title" aria-modal="true">';

		$html .= '<div class="wpconsent-preferences-content">';

		// Preferences header div.
		$html .= '<div class="wpconsent-preferences-header">';
		$html .= '<h2 id="wpconsent-preferences-title" tabindex="0">' . esc_html__( 'Cookie Preferences', 'wpconsent-cookies-banner-privacy-suite' ) . '</h2>';
		if ( ! empty( $logo ) ) {
			$site_name = get_bloginfo( 'name' );

			$html .= '<div class="wpconsent-banner-logo"><img height="30" src="' . esc_url( $logo ) . '" alt="' . esc_html( $site_name ) . '" /></div>';
		}
		$html .= '</div>'; // .wpconsent-preferences-header
		$html .= '<p tabindex="0">' . esc_html__( 'Manage your cookie preferences below:', 'wpconsent-cookies-banner-privacy-suite' ) . '</p>';

		foreach ( $categories as $category_slug => $category ) {
			$html .= '<div class="wpconsent-cookie-category">';
			$html .= '<div class="wpconsent-cookie-category-text">';
			$html .= '<label for="cookie-category-' . esc_attr( $category_slug ) . '">' . esc_html( $category['name'] ) . '</label>';
			$html .= '<p tabindex="0">' . wp_kses_post( $category['description'] ) . '</p>';
			$html .= '</div>'; // .wpconsent-cookie-category-text
			$html .= '<div class="wpconsent-cookie-category-checkbox">';
			$html .= '<input type="checkbox" id="cookie-category-' . esc_attr( $category_slug ) . '" name="wpconsent_cookie[]" value="' . esc_attr( $category_slug ) . '" ' . ( $category['required'] ? 'checked disabled' : '' ) . '>';
			$html .= '</div>'; // .wpconsent-cookie-category-checkbox
			$html .= '</div>'; // .wpconsent-cookie-category
		}

		// Cookie policy section, if set.
		$cookie_policy_page_id = wpconsent()->settings->get_option( 'cookie_policy_page', 0 );
		if ( $cookie_policy_page_id ) {
			$cookie_policy_page_url = get_permalink( $cookie_policy_page_id );
			$privacy_policy         = get_privacy_policy_url();

			$html .= '<div class="wpconsent-cookie-category">';
			$html .= '<div class="wpconsent-cookie-category-text">';
			$html .= '<label>' . esc_html__( 'Cookie Policy', 'wpconsent-cookies-banner-privacy-suite' ) . '</label>';
			$html .= '<p tabindex="0">';

			if ( $privacy_policy ) {
				$html .= sprintf(
					/* translators: 1: Cookie policy URL, 2: Privacy policy URL */
					esc_html__( 'You can find more information about our %1$s and %2$s.', 'wpconsent-cookies-banner-privacy-suite' ),
					'<a href="' . esc_url( $cookie_policy_page_url ) . '">' . esc_html__( 'Cookie Policy', 'wpconsent-cookies-banner-privacy-suite' ) . '</a>',
					'<a href="' . esc_url( $privacy_policy ) . '">' . esc_html__( 'Privacy Policy', 'wpconsent-cookies-banner-privacy-suite' ) . '</a>'
				);
			} else {
				$html .= sprintf(
					/* translators: %s: Cookie policy URL */
					esc_html__( 'You can find more information in our %s.', 'wpconsent-cookies-banner-privacy-suite' ),
					'<a href="' . esc_url( $cookie_policy_page_url ) . '">' . esc_html__( 'Cookie Policy', 'wpconsent-cookies-banner-privacy-suite' ) . '</a>'
				);
			}
			$html .= '</p>';
			$html .= '</div>'; // .wpconsent-cookie-category-text
			$html .= '</div>'; // .wpconsent-cookie-category
		}

		$html .= '<div class="wpconsent-preferences-actions">';
		$html .= '<div class="wpconsent-preferences-buttons">';
		$html .= '<button class="wpconsent-accept-all wpconsent-banner-button">' . esc_html( $accept_button_text ) . '</button>';
		$html .= '<button class="wpconsent-save-preferences wpconsent-banner-button">' . esc_html__( 'Save Preferences', 'wpconsent-cookies-banner-privacy-suite' ) . '</button>';
		$html .= '<button class="wpconsent-close-preferences wpconsent-banner-button">' . esc_html__( 'Close', 'wpconsent-cookies-banner-privacy-suite' ) . '</button>';
		$html .= '</div>'; // .wpconsent-preferences-buttons
		$html .= '</div>'; // .wpconsent-preferences-actions
		$html .= '</div>'; // .wpconsent-preferences-content
		$html .= '</div>'; // #wpconsent-preferences-modal

		return $html;
	}

	/**
	 * Output the powered by WPConsent logo.
	 *
	 * @return string
	 */
	public function powered_by() {
		if ( wpconsent()->settings->get_option( 'hide_powered_by' ) ) {
			return '';
		}
		$url  = wpconsent_utm_url( 'https://wpconsent.com/powered-by/', 'poweredby' );
		$html = '<div class="wpconsent-powered-by">';

		$html .= '<a style="color: ' . esc_attr( $this->get_color_settings()['text'] ) . '" href="' . esc_url( $url ) . '" target="_blank" rel="nofollow noopener noreferrer">';
		$html .= sprintf(
		/* translators: %1$s and %2$s add a tag used for hiding the text on small screens and %3$s is the WPConsent logo svg */
			esc_html__( '%1$sPowered by%2$s %3$s', 'wpconsent-cookies-banner-privacy-suite' ),
			'<span class="wpconsent-powered-by-text">',
			'</span>',
			wpconsent_get_icon( 'logo-mono', 80, 12, '0 0 57 9', $this->get_color_settings()['text'] )
		);
		$html .= '</a>';
		$html .= '</div>'; // .wpconsent-powered-by

		return $html;
	}

	/**
	 * Output the floating consent button.
	 *
	 * @return void
	 */
	public function floating_consent_button() {
		if ( ! wpconsent()->settings->get_option( 'enable_consent_floating' ) || is_admin() ) {
			return;
		}
		$colors = $this->get_color_settings();
		$style  = 'background-color: ' . esc_attr( $colors['background'] ) . '; color: ' . esc_attr( $colors['text'] ) . ';';
		echo '<button id="wpconsent-consent-floating" class="wpconsent-consent-floating-button" style="' . esc_attr( $style ) . '" aria-label="' . esc_attr__( 'Cookie Preferences', 'wpconsent-cookies-banner-privacy-suite' ) . '">';
		wpconsent_icon( 'preferences', 24, 24, '0 -960 960 960', $colors['text'] );
		echo '</button>';
	}
}
