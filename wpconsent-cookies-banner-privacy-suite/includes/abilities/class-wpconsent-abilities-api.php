<?php
/**
 * Registers WPConsent abilities with the WordPress Abilities API (WordPress 6.9+).
 *
 * @package WPConsent
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WPConsent_Abilities_API class.
 */
class WPConsent_Abilities_API {

	/**
	 * Annotations for each kind of ability. The run endpoint picks the HTTP method from these:
	 * readonly → GET, destructive + idempotent → DELETE, everything else → POST.
	 *
	 * @var array
	 */
	protected $annotation_presets = array(
		'read'      => array(
			'readonly'    => true,
			'destructive' => false,
			'idempotent'  => true,
		),
		'update'    => array(
			'readonly'    => false,
			'destructive' => false,
			'idempotent'  => true,
		),
		'create'    => array(
			'readonly'    => false,
			'destructive' => false,
			'idempotent'  => false,
		),
		'delete'    => array(
			'readonly'    => false,
			'destructive' => true,
			'idempotent'  => true,
		),
		'overwrite' => array(
			'readonly'    => false,
			'destructive' => true,
			'idempotent'  => false,
		),
	);

	/**
	 * Components loaded on demand by component().
	 *
	 * @var array
	 */
	protected $loaded = array();

	/**
	 * Hook into the Abilities API, when it is available.
	 */
	public function __construct() {
		if ( ! self::is_available() ) {
			return;
		}

		add_action( 'wp_abilities_api_categories_init', array( $this, 'register_categories' ) );
		add_action( 'wp_abilities_api_init', array( $this, 'register_abilities' ) );
	}

	/**
	 * Is the Abilities API available (WordPress 6.9+)?
	 *
	 * @return bool
	 */
	public static function is_available() {
		return function_exists( 'wp_register_ability' );
	}

	/**
	 * Register the ability categories.
	 *
	 * @return void
	 */
	public function register_categories() {
		$categories = array(
			'wpconsent-settings' => array(
				'label'       => __( 'WPConsent Settings', 'wpconsent-cookies-banner-privacy-suite' ),
				'description' => __( 'Read and update WPConsent settings and the consent banner.', 'wpconsent-cookies-banner-privacy-suite' ),
			),
			'wpconsent-cookies'  => array(
				'label'       => __( 'WPConsent Cookies', 'wpconsent-cookies-banner-privacy-suite' ),
				'description' => __( 'Manage cookie categories, services and cookies.', 'wpconsent-cookies-banner-privacy-suite' ),
			),
			'wpconsent-scanner'  => array(
				'label'       => __( 'WPConsent Scanner', 'wpconsent-cookies-banner-privacy-suite' ),
				'description' => __( 'Run website scans and configure the services they find.', 'wpconsent-cookies-banner-privacy-suite' ),
			),
			'wpconsent-tools'    => array(
				'label'       => __( 'WPConsent Tools', 'wpconsent-cookies-banner-privacy-suite' ),
				'description' => __( 'Cookie policy, import and export, cache, reset and plugin installs.', 'wpconsent-cookies-banner-privacy-suite' ),
			),
		);

		/**
		 * Filter the WPConsent ability categories.
		 *
		 * @param array $categories Category slug => array( label, description ).
		 */
		$categories = apply_filters( 'wpconsent_abilities_categories', $categories );

		foreach ( $categories as $slug => $args ) {
			wp_register_ability_category( $slug, $args );
		}
	}

	/**
	 * Register the abilities of every ability group.
	 *
	 * @return void
	 */
	public function register_abilities() {
		// The group classes are only needed while abilities register, so they are not loaded on every request.
		foreach ( array( 'group', 'settings', 'cookies', 'scanner', 'tools' ) as $file ) {
			require_once WPCONSENT_PLUGIN_PATH . 'includes/abilities/class-wpconsent-abilities-' . $file . '.php';
		}

		$groups = array(
			'WPConsent_Abilities_Settings',
			'WPConsent_Abilities_Cookies',
			'WPConsent_Abilities_Scanner',
			'WPConsent_Abilities_Tools',
		);

		/**
		 * Filter the classes that register WPConsent abilities.
		 *
		 * @param string[] $groups Class names extending WPConsent_Abilities_Group.
		 */
		$groups = apply_filters( 'wpconsent_abilities_groups', $groups );

		foreach ( $groups as $class ) {
			if ( class_exists( $class ) ) {
				$group = new $class( $this );
				$group->register();
			}
		}
	}

	/**
	 * Register a single WPConsent ability.
	 *
	 * @param string $slug Ability slug, without the wpconsent/ prefix.
	 * @param string $type One of read, update, create, delete, overwrite.
	 * @param array  $args Arguments for wp_register_ability(), plus an optional `confirm`: the reason the agent
	 *                     must confirm every call with the user. It adds a confirm input and refuses calls without it.
	 *
	 * @return void
	 */
	public function add( $slug, $type, $args ) {
		if ( empty( $args['permission_callback'] ) ) {
			$args['permission_callback'] = array( $this, 'check_permission' );
		}

		// Abilities without input still need a schema: without one, the Abilities API rejects the empty object
		// that MCP clients send for tools that take no arguments.
		if ( empty( $args['input_schema'] ) ) {
			$args['input_schema'] = array(
				'type'                 => 'object',
				'additionalProperties' => false,
				'default'              => array(),
			);
		}

		$confirm = isset( $args['confirm'] ) ? $args['confirm'] : null;
		unset( $args['confirm'] );
		if ( null !== $confirm ) {
			$args['input_schema']['properties']['confirm'] = array( 'type' => 'boolean' );
		}

		// Abilities are private by default: `public` lists them for MCP clients and AI agents as well as REST.
		// The MCP Adapter also reads `mcp.public`.
		$args['meta'] = array(
			'annotations'  => $this->annotation_presets[ $type ],
			'show_in_rest' => true,
			'public'       => true,
			'mcp'          => array( 'public' => true ),
		);

		$execute                  = $args['execute_callback'];
		$schema                   = $args['input_schema'];
		$args['execute_callback'] = function ( $input = null ) use ( $execute, $schema, $confirm ) {
			$input = $this->sanitize_input( $input, $schema );
			if ( null !== $confirm && ! $this->is_confirmed( $input ) ) {
				return $this->with_status( $this->confirm_required_error( $confirm ) );
			}

			$locale = is_array( $input ) && isset( $input['locale'] ) ? (string) $input['locale'] : null;

			return $this->with_status(
				$this->in_locale(
					$locale,
					function () use ( $execute, $input ) {
						return call_user_func( $execute, $input );
					}
				)
			);
		};

		wp_register_ability( 'wpconsent/' . $slug, $args );
	}

	/**
	 * Convert validated input to the types its schema declares, like the WordPress 7.1 REST run endpoint.
	 *
	 * The REST API on WordPress 6.9 and 7.0, and direct execute() calls such as the MCP Adapter's, pass
	 * input through as sent. The schema accepts "false" and "0" as booleans, so without this a "false"
	 * string reads as true in every confirm gate and toggle.
	 *
	 * @param mixed $input  The ability input, already validated against the schema.
	 * @param array $schema The ability input schema.
	 *
	 * @return mixed
	 */
	protected function sanitize_input( $input, $schema ) {
		if ( ! is_array( $input ) ) {
			return $input;
		}

		$sanitized = rest_sanitize_value_from_schema( $input, $schema, 'input' );

		return is_wp_error( $sanitized ) ? $input : $sanitized;
	}

	/**
	 * Whether the agent confirmed the change with the user. Only a boolean true counts.
	 *
	 * @param array $input The ability input.
	 *
	 * @return bool
	 */
	public function is_confirmed( $input ) {
		return is_array( $input ) && isset( $input['confirm'] ) && true === $input['confirm'];
	}

	/**
	 * Give an ability error the HTTP status the REST API should answer with.
	 *
	 * Without one, the REST API answers 500, which tells an agent the server failed when the request needs fixing.
	 *
	 * @param mixed $result The ability result.
	 *
	 * @return mixed The result, with a status on errors that have none.
	 */
	protected function with_status( $result ) {
		if ( ! is_wp_error( $result ) ) {
			return $result;
		}

		$data = $result->get_error_data();
		if ( is_array( $data ) && isset( $data['status'] ) ) {
			return $result;
		}

		$code = $result->get_error_code();
		if ( '_not_found' === substr( $code, -10 ) ) {
			$status = 404;
		} elseif ( 'wpconsent_scan_rate_limited' === $code ) {
			$status = 429;
		} elseif ( preg_match( '/_failed$|_unavailable$/', $code ) ) {
			$status = 500;
		} else {
			$status = 400;
		}

		$result->add_data( array_merge( is_array( $data ) ? $data : array(), array( 'status' => $status ) ) );

		return $result;
	}

	/**
	 * Abilities are available to users who can manage WPConsent in the admin.
	 *
	 * @return bool
	 */
	public function check_permission() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Get a WPConsent component that normally only loads in the admin, loading it when needed.
	 *
	 * Abilities run over the REST API, where admin-only components and files are not loaded.
	 *
	 * @param string $name Component name, e.g. scanner or services.
	 *
	 * @return object|WP_Error The component, or an error when it cannot be loaded.
	 */
	public function component( $name ) {
		if ( isset( $this->loaded[ $name ] ) ) {
			return $this->loaded[ $name ];
		}
		if ( isset( wpconsent()->$name ) ) {
			return wpconsent()->$name;
		}

		$components = array(
			'services'            => function () {
				require_once WPCONSENT_PLUGIN_PATH . 'includes/admin/class-wpconsent-services.php';

				return WPConsent_Services::get_instance();
			},
			'scanner'             => function () {
				require_once WPCONSENT_PLUGIN_PATH . 'includes/class-wpconsent-scanner.php';

				return WPConsent_Scanner::get_instance();
			},
			'recommended_plugins' => function () {
				require_once WPCONSENT_PLUGIN_PATH . 'includes/admin/class-wpconsent-recommended-plugins.php';

				return new WPConsent_Recommended_Plugins();
			},
			'admin_ajax'          => function () {
				require_once WPCONSENT_PLUGIN_PATH . 'includes/admin/admin-ajax.php';

				return null;
			},
			'tools_page'          => function () {
				require_once WPCONSENT_PLUGIN_PATH . 'includes/lite/admin/pages/trait-wpconsent-language-picker.php';
				require_once WPCONSENT_PLUGIN_PATH . 'includes/admin/pages/trait-wpconsent-input-select.php';
				require_once WPCONSENT_PLUGIN_PATH . 'includes/admin/pages/class-wpconsent-admin-page.php';
				require_once WPCONSENT_PLUGIN_PATH . 'includes/admin/pages/class-wpconsent-admin-page-tools.php';

				return new WPConsent_Admin_Page_Tools();
			},
		);

		/**
		 * Filter the admin-only components abilities can load on demand.
		 *
		 * @param callable[] $components Name => callable that requires the files and returns the component (or null for files only).
		 */
		$components = apply_filters( 'wpconsent_abilities_components', $components );

		if ( ! isset( $components[ $name ] ) ) {
			return new WP_Error( 'wpconsent_component_unavailable', __( 'This part of WPConsent is not available.', 'wpconsent-cookies-banner-privacy-suite' ) );
		}

		$component = call_user_func( $components[ $name ] );
		if ( null === $component ) {
			return $this;
		}

		$this->loaded[ $name ] = $component;

		// Share it with the rest of the plugin when the main class has a property for it.
		if ( property_exists( wpconsent(), $name ) ) {
			wpconsent()->$name = $component;
		}

		return $component;
	}

	/**
	 * Load several components in order.
	 *
	 * @param string[] $names Component names, dependencies first.
	 *
	 * @return WP_Error|null The first loading error, or null when all loaded.
	 */
	public function load_components( $names ) {
		foreach ( $names as $name ) {
			$component = $this->component( $name );
			if ( is_wp_error( $component ) ) {
				return $component;
			}
		}

		return null;
	}

	/**
	 * Script blocking only works while the consent banner is on, so refuse turning it on without the banner.
	 *
	 * @param bool $enabling Whether the change turns script blocking on.
	 *
	 * @return WP_Error|null
	 */
	public function banner_required_error( $enabling ) {
		if ( $enabling && ! wpconsent()->settings->get_base_option( 'enable_consent_banner' ) ) {
			return new WP_Error( 'wpconsent_banner_disabled', __( 'Script blocking only works while the consent banner is on. Turn the banner on first.', 'wpconsent-cookies-banner-privacy-suite' ) );
		}

		return null;
	}

	/**
	 * Refuse a change that weakens consent protection unless the agent confirmed it with the user.
	 *
	 * Compares against the configured values, not the ones geolocation or IAB TCF force at runtime.
	 *
	 * @param array    $input         The ability input.
	 * @param string[] $disable_keys  Option keys whose turning off weakens protection.
	 * @param string[] $enable_keys   Option keys whose turning on weakens protection (e.g. default_allow).
	 * @param string[] $reasons       Optional key => reason, for a setting whose risk is not weaker consent protection.
	 *
	 * @return WP_Error|null Error when confirmation is missing, null when the change can go ahead.
	 */
	public function require_confirmation( $input, $disable_keys, $enable_keys = array(), $reasons = array() ) {
		if ( $this->is_confirmed( $input ) ) {
			return null;
		}

		foreach ( $disable_keys as $key ) {
			if ( array_key_exists( $key, $input ) && ! $input[ $key ] && wpconsent()->settings->get_base_option( $key ) ) {
				return isset( $reasons[ $key ] ) ? $this->confirm_required_error( $reasons[ $key ] ) : $this->confirmation_error( $key );
			}
		}

		foreach ( $enable_keys as $key ) {
			if ( array_key_exists( $key, $input ) && $input[ $key ] && ! wpconsent()->settings->get_base_option( $key ) ) {
				return isset( $reasons[ $key ] ) ? $this->confirm_required_error( $reasons[ $key ] ) : $this->confirmation_error( $key );
			}
		}

		return null;
	}

	/**
	 * The error asking the agent to confirm a change that weakens consent protection.
	 *
	 * @param string $key The setting being changed.
	 *
	 * @return WP_Error
	 */
	public function confirmation_error( $key ) {
		return $this->confirm_required_error(
			sprintf(
				/* translators: %s: the setting name. */
				__( 'Changing "%s" this way weakens consent protection on the site.', 'wpconsent-cookies-banner-privacy-suite' ),
				$key
			)
		);
	}

	/**
	 * The error asking the agent to confirm a change with the user and send it again.
	 *
	 * @param string $reason Why the change needs confirmation.
	 *
	 * @return WP_Error
	 */
	public function confirm_required_error( $reason ) {
		return new WP_Error(
			'wpconsent_confirmation_required',
			$reason . ' ' . __( 'Confirm with the user, then send the request again with "confirm": true.', 'wpconsent-cookies-banner-privacy-suite' )
		);
	}

	/**
	 * Run an ability in one language: the requested locale, or the default language.
	 *
	 * With Pro multilanguage, admin reads and writes otherwise follow the user's last admin
	 * language while REST reads follow the frontend locale, so they could hit different languages.
	 *
	 * @param string|null $locale   Requested locale, or null for the default language.
	 * @param callable    $callback The ability callback.
	 *
	 * @return mixed
	 */
	protected function in_locale( $locale, $callback ) {
		if ( ! isset( wpconsent()->multilanguage ) ) {
			return call_user_func( $callback );
		}

		$multilanguage = wpconsent()->multilanguage;
		$default       = $multilanguage->get_default_locale();
		if ( null !== $locale && $locale !== $default && ! array_key_exists( $locale, $multilanguage->get_merged_languages() ) ) {
			return new WP_Error(
				'wpconsent_invalid_language',
				sprintf(
					/* translators: %s: locale code. */
					__( 'The banner is not set up for "%s". Use the default language or one returned by get-languages.', 'wpconsent-cookies-banner-privacy-suite' ),
					$locale
				)
			);
		}

		$previous = $multilanguage->get_locale_override();
		$multilanguage->set_locale_override( null === $locale ? $default : $locale );
		try {
			return call_user_func( $callback );
		} finally {
			$multilanguage->set_locale_override( $previous );
		}
	}

	/**
	 * Input schema property for the optional locale of abilities that read or write translated text.
	 *
	 * @return array
	 */
	public function locale_schema() {
		return array(
			'type'        => 'string',
			'description' => __( 'Language to read or write, e.g. de_DE (from get-languages). Defaults to the site language.', 'wpconsent-cookies-banner-privacy-suite' ),
		);
	}
}
