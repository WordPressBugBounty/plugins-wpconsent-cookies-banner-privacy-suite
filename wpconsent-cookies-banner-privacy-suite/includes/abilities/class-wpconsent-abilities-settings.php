<?php
/**
 * Abilities for the WPConsent settings and the consent banner.
 *
 * @package WPConsent
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WPConsent_Abilities_Settings class.
 */
class WPConsent_Abilities_Settings extends WPConsent_Abilities_Group {

	/**
	 * Register the settings abilities.
	 *
	 * @return void
	 */
	public function register() {
		$this->api->add(
			'get-settings',
			'read',
			array(
				'label'            => __( 'Get WPConsent settings', 'wpconsent-cookies-banner-privacy-suite' ),
				'description'      => __( 'Returns the configured WPConsent settings: script and content blocking, Google Consent Mode, Clarity, GPC, consent duration, shared consent and the cookie policy page. Geolocation rules and IAB TCF can override some of these for visitors; see get-geolocation-rules and get-iab-tcf-settings.', 'wpconsent-cookies-banner-privacy-suite' ),
				'category'         => 'wpconsent-settings',
				'output_schema'    => $this->get_settings_schema(),
				'execute_callback' => array( $this, 'get_settings' ),
			)
		);

		$this->api->add(
			'update-settings',
			'update',
			array(
				'label'            => __( 'Update WPConsent settings', 'wpconsent-cookies-banner-privacy-suite' ),
				'description'      => __( 'Updates only the WPConsent settings that are sent; everything else stays as it is. Turning off script blocking, Google Consent Mode or respect_gpc, or turning on default_allow (scripts load before consent), weakens consent protection, and turning on uninstall_data deletes all WPConsent data on uninstall: confirm these with the user first, then send "confirm": true.', 'wpconsent-cookies-banner-privacy-suite' ),
				'category'         => 'wpconsent-settings',
				'input_schema'     => $this->get_update_schema( self::get_fields() ),
				'output_schema'    => array(
					'type'       => 'object',
					'properties' => array(
						'updated'  => array(
							'type'  => 'array',
							'items' => array( 'type' => 'string' ),
						),
						'settings' => $this->get_settings_schema(),
					),
				),
				'execute_callback' => array( $this, 'update_settings' ),
			)
		);

		$banner_fields = self::get_banner_fields();

		$this->api->add(
			'get-banner',
			'read',
			array(
				'label'            => __( 'Get the consent banner', 'wpconsent-cookies-banner-privacy-suite' ),
				'description'      => __( 'Returns the configured consent banner content (texts, buttons, on/off) and design (layout, position, colors, sizes). Texts are in the site language unless "locale" is sent.', 'wpconsent-cookies-banner-privacy-suite' ),
				'category'         => 'wpconsent-settings',
				'input_schema'     => array(
					'type'                 => 'object',
					'properties'           => array( 'locale' => $this->api->locale_schema() ),
					'additionalProperties' => false,
					'default'              => array(),
				),
				'output_schema'    => $this->get_banner_schema( $banner_fields ),
				'execute_callback' => array( $this, 'get_banner' ),
			)
		);

		$this->api->add(
			'update-banner-content',
			'update',
			array(
				'label'            => __( 'Update the consent banner content', 'wpconsent-cookies-banner-privacy-suite' ),
				'description'      => __( 'Updates only the banner content fields that are sent: message, button texts, which buttons show and their order, logo, preferences panel and cookie policy texts (in cookie_policy_learn_more, %s is replaced by the service name). Texts are saved in the site language unless "locale" is sent. Turning the banner off removes consent protection: confirm with the user first, then send "confirm": true.', 'wpconsent-cookies-banner-privacy-suite' ),
				'category'         => 'wpconsent-settings',
				'input_schema'     => $this->get_update_schema( $banner_fields['content'], true ),
				'output_schema'    => $this->get_banner_schema( $banner_fields ),
				'execute_callback' => array( $this, 'update_banner_content' ),
			)
		);

		$this->api->add(
			'update-banner-design',
			'update',
			array(
				'label'            => __( 'Update the consent banner design', 'wpconsent-cookies-banner-privacy-suite' ),
				'description'      => __( 'Updates only the banner design fields that are sent: layout and position, colors, font size, button size, corners and style, and the floating icon. The position must fit the layout.', 'wpconsent-cookies-banner-privacy-suite' ),
				'category'         => 'wpconsent-settings',
				'input_schema'     => $this->get_update_schema( $banner_fields['design'] ),
				'output_schema'    => $this->get_banner_schema( $banner_fields ),
				'execute_callback' => array( $this, 'update_banner_design' ),
			)
		);
	}

	/**
	 * The banner fields exposed to abilities, grouped by the part of the banner editor they come from.
	 *
	 * @return array Group (content, design) => key => field definition.
	 */
	public static function get_banner_fields() {
		$strings = wpconsent()->strings->get_strings();
		$text    = array();
		$html    = array();

		$text_keys = array(
			'accept_button_text',
			'cancel_button_text',
			'preferences_button_text',
			'preferences_panel_title',
			'cookie_policy_title',
			'save_preferences_button_text',
			'close_button_text',
			'cookie_table_header_name',
			'cookie_table_header_description',
			'cookie_table_header_duration',
			'cookie_table_header_service_url',
			'gpc_toast_message',
			'banner_logo',
		);
		foreach ( $text_keys as $key ) {
			$text[ $key ] = array(
				'type'    => 'string',
				'default' => isset( $strings[ $key ] ) ? $strings[ $key ] : '',
			);
		}

		// Stored empty while untouched, so the default keeps following the active language.
		$text['cookie_policy_learn_more'] = array(
			'type'           => 'string',
			'default'        => isset( $strings['cookie_policy_learn_more'] ) ? $strings['cookie_policy_learn_more'] : '',
			'empty_defaults' => true,
		);

		foreach ( array( 'banner_message', 'preferences_panel_description', 'cookie_policy_text', 'gpc_override_message' ) as $key ) {
			$html[ $key ] = array(
				'type'     => 'string',
				'default'  => isset( $strings[ $key ] ) ? $strings[ $key ] : '',
				'sanitize' => 'wp_kses_post',
			);
		}

		$toggles = array();
		foreach ( array( 'accept_button_enabled', 'cancel_button_enabled', 'preferences_button_enabled', 'disable_close_button', 'hide_powered_by' ) as $key ) {
			$toggles[ $key ] = array(
				'type'    => 'boolean',
				'default' => false,
			);
		}

		$content = array_merge(
			array(
				'enable_consent_banner' => array(
					'type'     => 'boolean',
					'default'  => false,
					'disables' => true,
				),
				'button_order'          => array(
					'type'    => 'array',
					'default' => array( 'preferences', 'cancel', 'accept' ),
					'enum'    => array( 'accept', 'cancel', 'preferences' ),
				),
			),
			$html,
			$text,
			$toggles
		);

		$color = array(
			'type'    => 'string',
			'default' => '',
			'pattern' => '^#[0-9a-fA-F]{3,8}$',
		);

		$design = array(
			'banner_layout'         => array(
				'type'    => 'string',
				'default' => 'long',
				'enum'    => array_keys( self::get_layout_positions() ),
			),
			'banner_position'       => array(
				'type'    => 'string',
				'default' => 'bottom',
				'enum'    => array_values( array_unique( call_user_func_array( 'array_merge', array_values( self::get_layout_positions() ) ) ) ),
			),
			'banner_font_size'      => array(
				'type'    => 'string',
				'default' => '16px',
				'enum'    => array( '12px', '14px', '16px', '18px', '20px', '22px', '24px', '26px', '28px', '30px' ),
			),
			'banner_button_size'    => array(
				'type'    => 'string',
				'default' => 'small',
				'enum'    => array( 'small', 'regular', 'large' ),
			),
			'banner_button_corner'  => array(
				'type'    => 'string',
				'default' => 'slightly-rounded',
				'enum'    => array( 'square', 'slightly-rounded', 'rounded' ),
			),
			'banner_button_type'    => array(
				'type'    => 'string',
				'default' => 'filled',
				'enum'    => array( 'filled', 'outlined' ),
			),
			'consent_floating_icon' => array(
				'type'    => 'string',
				'default' => 'preferences',
			),
		);
		foreach ( array( 'banner_background_color', 'banner_text_color', 'banner_accept_bg', 'banner_accept_color', 'banner_cancel_bg', 'banner_cancel_color', 'banner_preferences_bg', 'banner_preferences_color' ) as $key ) {
			$design[ $key ] = $color;
		}

		/**
		 * Filter the banner fields that abilities can read and update.
		 *
		 * @param array $fields Group (content, design) => key => field definition.
		 */
		return apply_filters(
			'wpconsent_abilities_banner_fields',
			array(
				'content' => $content,
				'design'  => $design,
			)
		);
	}

	/**
	 * The banner layouts and the positions each one allows.
	 *
	 * @return array Layout => allowed positions (empty when the layout has no position).
	 */
	public static function get_layout_positions() {
		/**
		 * Filter the banner layouts abilities can set, with their allowed positions.
		 *
		 * @param array $layouts Layout => positions.
		 */
		return apply_filters(
			'wpconsent_abilities_banner_layouts',
			array(
				'long'     => array( 'top', 'bottom' ),
				'floating' => array( 'left-top', 'right-top', 'left-bottom', 'right-bottom' ),
			)
		);
	}

	/**
	 * Execute callback for wpconsent/get-banner.
	 *
	 * @return array
	 */
	public function get_banner() {
		$banner = array();
		foreach ( self::get_banner_fields() as $group => $fields ) {
			$banner[ $group ] = $this->get_values( $fields );
		}

		return $banner;
	}

	/**
	 * Execute callback for wpconsent/update-banner-content.
	 *
	 * @param array $input The content fields to change.
	 *
	 * @return array|WP_Error
	 */
	public function update_banner_content( $input ) {
		$fields = self::get_banner_fields();

		$confirmation = $this->api->require_confirmation( $input, array_keys( wp_list_filter( $fields['content'], array( 'disables' => true ) ) ) );
		if ( $confirmation ) {
			return $confirmation;
		}

		return $this->save_banner_fields( $input, $fields['content'] );
	}

	/**
	 * Execute callback for wpconsent/update-banner-design.
	 *
	 * @param array $input The design fields to change.
	 *
	 * @return array|WP_Error
	 */
	public function update_banner_design( $input ) {
		$fields = self::get_banner_fields();

		if ( isset( $input['banner_layout'] ) || isset( $input['banner_position'] ) ) {
			$layouts  = self::get_layout_positions();
			$layout   = isset( $input['banner_layout'] ) ? $input['banner_layout'] : wpconsent()->settings->get_base_option( 'banner_layout', 'long' );
			$position = isset( $input['banner_position'] ) ? $input['banner_position'] : wpconsent()->settings->get_base_option( 'banner_position', 'bottom' );

			if ( ! empty( $layouts[ $layout ] ) && ! in_array( $position, $layouts[ $layout ], true ) ) {
				return new WP_Error(
					'wpconsent_invalid_position',
					sprintf(
						/* translators: 1: banner layout, 2: allowed positions. */
						__( 'The "%1$s" layout needs one of these positions: %2$s.', 'wpconsent-cookies-banner-privacy-suite' ),
						$layout,
						implode( ', ', $layouts[ $layout ] )
					)
				);
			}
		}

		return $this->save_banner_fields( $input, $fields['design'] );
	}

	/**
	 * Save the sent banner fields and return the updated banner.
	 *
	 * @param array $input  The ability input.
	 * @param array $fields The fields this ability may change.
	 *
	 * @return array|WP_Error
	 */
	protected function save_banner_fields( $input, $fields ) {
		$changes = array_intersect_key( $input, $fields );
		if ( empty( $changes ) ) {
			return new WP_Error( 'wpconsent_nothing_to_update', __( 'Send at least one banner field to update.', 'wpconsent-cookies-banner-privacy-suite' ) );
		}

		$settings = array();
		foreach ( $changes as $key => $value ) {
			$settings[ $key ] = $this->sanitize_value( $value, $fields[ $key ] );

			// Like the banner editor: the untouched default is stored empty so it keeps following the active language.
			if ( ! empty( $fields[ $key ]['empty_defaults'] ) && $fields[ $key ]['default'] === $settings[ $key ] ) {
				$settings[ $key ] = '';
			}
		}

		wpconsent()->settings->bulk_update_options( $settings );

		wpconsent_after_banner_saved();

		return $this->get_banner();
	}

	/**
	 * Output schema for the banner abilities.
	 *
	 * @param array $banner_fields Group => fields.
	 *
	 * @return array
	 */
	protected function get_banner_schema( $banner_fields ) {
		$properties = array();
		foreach ( $banner_fields as $group => $fields ) {
			$group_properties = array();
			foreach ( $fields as $key => $field ) {
				$group_properties[ $key ] = $this->field_schema( $field );
			}
			$properties[ $group ] = array(
				'type'       => 'object',
				'properties' => $group_properties,
			);
		}

		return array(
			'type'       => 'object',
			'properties' => $properties,
		);
	}

	/**
	 * The settings exposed to abilities, with their type and default.
	 *
	 * Each field: type (boolean, integer, string, array), default, and optionally
	 * enum (allowed values), disables (turning it off needs confirmation), enables_risk
	 * (turning it on needs confirmation) and confirm_reason (why, when not weaker consent protection).
	 *
	 * @return array
	 */
	public static function get_fields() {
		$fields = array(
			'cookie_policy_page'                => array(
				'type'    => 'integer',
				'default' => 0,
			),
			'enable_script_blocking'            => array(
				'type'     => 'boolean',
				'default'  => false,
				'disables' => true,
			),
			'google_consent_mode'               => array(
				'type'     => 'boolean',
				'default'  => false,
				'disables' => true,
			),
			'gcm_url_passthrough'               => array(
				'type'    => 'boolean',
				'default' => false,
			),
			'gcm_ads_data_redaction'            => array(
				'type'    => 'boolean',
				'default' => false,
			),
			'enable_consent_floating'           => array(
				'type'    => 'boolean',
				'default' => false,
			),
			'default_allow'                     => array(
				'type'         => 'boolean',
				'default'      => false,
				'enables_risk' => true,
			),
			'manual_toggle_services'            => array(
				'type'    => 'boolean',
				'default' => false,
			),
			'consent_duration'                  => array(
				'type'    => 'integer',
				'default' => 30,
				'minimum' => 1,
			),
			'enable_content_blocking'           => array(
				'type'    => 'boolean',
				'default' => false,
			),
			'content_blocking_services'         => array(
				'type'    => 'array',
				'default' => array(),
			),
			'content_blocking_placeholder_text' => array(
				'type'    => 'string',
				'default' => 'Click here to accept {category} cookies and load this content',
			),
			'clarity_consent_mode'              => array(
				'type'    => 'boolean',
				'default' => false,
			),
			'uninstall_data'                    => array(
				'type'           => 'boolean',
				'default'        => false,
				'enables_risk'   => true,
				// Not a consent change, so it gives its own reason.
				'confirm_reason' => __( 'Turning on uninstall_data deletes all WPConsent settings, cookies and consent records when the plugin is uninstalled.', 'wpconsent-cookies-banner-privacy-suite' ),
			),
			'enable_shared_consent'             => array(
				'type'    => 'boolean',
				'default' => false,
			),
			'respect_gpc'                       => array(
				'type'     => 'boolean',
				'default'  => false,
				'disables' => true,
			),
			'usage_tracking'                    => array(
				'type'    => 'boolean',
				'default' => false,
			),
		);

		/**
		 * Filter the settings that abilities can read and update.
		 *
		 * @param array $fields Option key => field definition.
		 */
		return apply_filters( 'wpconsent_abilities_settings_fields', $fields );
	}

	/**
	 * Execute callback for wpconsent/get-settings.
	 *
	 * @return array
	 */
	public function get_settings() {
		$settings = $this->get_values( self::get_fields() );

		$settings['cookie_domain'] = (string) wpconsent()->settings->get_base_option( 'cookie_domain', '' );
		$settings['is_pro']        = class_exists( 'WPConsent_Premium' );
		$settings['version']       = WPCONSENT_VERSION;

		return $settings;
	}

	/**
	 * Execute callback for wpconsent/update-settings.
	 *
	 * @param array $input The settings to change.
	 *
	 * @return array|WP_Error
	 */
	public function update_settings( $input ) {
		$fields  = self::get_fields();
		$changes = array_intersect_key( $input, $fields );

		if ( empty( $changes ) ) {
			return new WP_Error( 'wpconsent_nothing_to_update', __( 'Send at least one setting to update.', 'wpconsent-cookies-banner-privacy-suite' ) );
		}

		// wp_list_pluck() warns on every field without the key, so pluck only the fields that set one.
		$reasons = array_filter(
			$fields,
			function ( $field ) {
				return isset( $field['confirm_reason'] );
			}
		);

		$confirmation = $this->api->require_confirmation(
			$input,
			array_keys( wp_list_filter( $fields, array( 'disables' => true ) ) ),
			array_keys( wp_list_filter( $fields, array( 'enables_risk' => true ) ) ),
			wp_list_pluck( $reasons, 'confirm_reason' )
		);
		if ( $confirmation ) {
			return $confirmation;
		}

		if ( ! $this->api->is_confirmed( $input ) ) {
			/**
			 * Filter the reason a settings change needs confirmation, for changes the field flags do not cover.
			 *
			 * @param string|null $reason  Why the change needs confirmation, null when it does not.
			 * @param array       $changes The settings being changed, option key => new value.
			 */
			$reason = apply_filters( 'wpconsent_abilities_settings_confirm_reason', null, $changes );
			if ( null !== $reason ) {
				return $this->api->confirm_required_error( $reason );
			}
		}

		$banner_error = $this->api->banner_required_error( ! empty( $changes['enable_script_blocking'] ) );
		if ( $banner_error ) {
			return $banner_error;
		}

		if ( ! empty( $changes['cookie_policy_page'] ) ) {
			$page = get_post( $changes['cookie_policy_page'] );
			if ( ! $page || 'page' !== $page->post_type || 'publish' !== $page->post_status ) {
				return new WP_Error( 'wpconsent_invalid_page', __( 'The cookie policy page must be a published page.', 'wpconsent-cookies-banner-privacy-suite' ) );
			}
		}

		$settings = array();
		foreach ( $changes as $key => $value ) {
			$settings[ $key ] = $this->sanitize_value( $value, $fields[ $key ] );
		}

		if ( isset( $settings['enable_shared_consent'] ) ) {
			$settings['cookie_domain'] = wpconsent_get_shared_cookie_domain( $settings['enable_shared_consent'] );
		}

		$previous_duration = absint( wpconsent()->settings->get_option( 'consent_duration', 30 ) );

		wpconsent()->settings->bulk_update_options( $settings );

		wpconsent_after_settings_saved( $settings, $previous_duration );

		/**
		 * Fires after settings are updated through an ability.
		 *
		 * @param array $settings The settings that were saved.
		 */
		do_action( 'wpconsent_abilities_settings_saved', $settings );

		return array(
			'updated'  => array_keys( $changes ),
			'settings' => $this->get_settings(),
		);
	}

	/**
	 * Sanitize an incoming value the way the admin pages store it.
	 *
	 * @param mixed $value The incoming value.
	 * @param array $field The field definition.
	 *
	 * @return mixed
	 */
	protected function sanitize_value( $value, $field ) {
		if ( isset( $field['sanitize'] ) ) {
			return call_user_func( $field['sanitize'], $value );
		}

		switch ( $field['type'] ) {
			case 'boolean':
				return $value ? 1 : 0;
			case 'integer':
				return intval( $value );
			case 'array':
				return array_values( array_map( 'sanitize_text_field', (array) $value ) );
			default:
				return sanitize_text_field( $value );
		}
	}

	/**
	 * Input schema for an update ability: any subset of the fields, plus confirm.
	 *
	 * @param array $fields      Field definitions.
	 * @param bool  $translated  Whether the fields are translatable texts that accept a locale.
	 *
	 * @return array
	 */
	protected function get_update_schema( $fields, $translated = false ) {
		$properties = array();
		foreach ( $fields as $key => $field ) {
			$properties[ $key ] = $this->field_schema( $field, true );
		}
		if ( $translated ) {
			$properties['locale'] = $this->api->locale_schema();
		}
		$properties['confirm'] = array(
			'type'        => 'boolean',
			'description' => __( 'Set to true after the user confirmed turning off consent protection.', 'wpconsent-cookies-banner-privacy-suite' ),
		);

		return array(
			'type'                 => 'object',
			'properties'           => $properties,
			'additionalProperties' => false,
			'minProperties'        => 1,
		);
	}

	/**
	 * Read the current value of each field, cast to its type.
	 *
	 * @param array $fields Field definitions.
	 *
	 * @return array
	 */
	protected function get_values( $fields ) {
		$values = array();
		foreach ( $fields as $key => $field ) {
			$value = wpconsent()->settings->get_base_option( $key, $field['default'] );
			if ( ! empty( $field['empty_defaults'] ) && '' === (string) $value ) {
				$value = $field['default'];
			}
			$values[ $key ] = $this->cast( $value, $field['type'], isset( $field['items'] ) ? $field['items'] : 'string' );
		}

		return $values;
	}

	/**
	 * Cast a stored option value to the field type.
	 *
	 * @param mixed  $value     The stored value.
	 * @param string $type      The field type.
	 * @param string $item_type The type of array items.
	 *
	 * @return mixed
	 */
	protected function cast( $value, $type, $item_type = 'string' ) {
		switch ( $type ) {
			case 'boolean':
				return (bool) $value;
			case 'integer':
				return (int) $value;
			case 'array':
				$value = array_values( (array) $value );

				return 'integer' === $item_type ? array_map( 'intval', $value ) : $value;
			default:
				return (string) $value;
		}
	}

	/**
	 * JSON Schema for a single field.
	 *
	 * Output schemas only carry the type: stored values from older versions may sit outside
	 * today's allowed values, and that must not make a read fail validation.
	 *
	 * @param array $field Field definition.
	 * @param bool  $input Whether the schema validates input (adds enum, pattern, minimum).
	 *
	 * @return array
	 */
	protected function field_schema( $field, $input = false ) {
		$schema = array( 'type' => $field['type'] );
		if ( 'array' === $field['type'] ) {
			$schema['items'] = array( 'type' => isset( $field['items'] ) ? $field['items'] : 'string' );
		}
		if ( ! $input ) {
			return $schema;
		}
		if ( 'array' === $field['type'] ) {
			if ( isset( $field['enum'] ) ) {
				$schema['items']['enum'] = $field['enum'];
			}
		} elseif ( isset( $field['enum'] ) ) {
			$schema['enum'] = $field['enum'];
		}
		if ( isset( $field['pattern'] ) ) {
			$schema['pattern'] = $field['pattern'];
		}
		if ( isset( $field['minimum'] ) ) {
			$schema['minimum'] = $field['minimum'];
		}

		return $schema;
	}

	/**
	 * Output schema for wpconsent/get-settings.
	 *
	 * @return array
	 */
	protected function get_settings_schema() {
		$properties = array();
		foreach ( self::get_fields() as $key => $field ) {
			$properties[ $key ] = $this->field_schema( $field );
		}
		$properties['cookie_domain'] = array( 'type' => 'string' );
		$properties['is_pro']        = array( 'type' => 'boolean' );
		$properties['version']       = array( 'type' => 'string' );

		return array(
			'type'       => 'object',
			'properties' => $properties,
		);
	}
}
