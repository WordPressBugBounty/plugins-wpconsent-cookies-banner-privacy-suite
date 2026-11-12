<?php
/**
 * Abilities for the WPConsent tools: cookie policy, import and export, cache, reset and plugin installs.
 *
 * @package WPConsent
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WPConsent_Abilities_Tools class.
 */
class WPConsent_Abilities_Tools extends WPConsent_Abilities_Group {

	/**
	 * Sections the settings export can include.
	 *
	 * @var string[]
	 */
	protected $export_sections = array( 'settings', 'banner_design', 'cookie_data', 'custom_scripts' );

	/**
	 * Register the tools abilities.
	 *
	 * @return void
	 */
	public function register() {
		$this->api->add(
			'generate-cookie-policy',
			'create',
			array(
				'label'            => __( 'Generate the cookie policy page', 'wpconsent-cookies-banner-privacy-suite' ),
				'description'      => __( 'Creates a published Cookie Policy page with default text and the list of declared cookies, and sets it as the cookie policy page.', 'wpconsent-cookies-banner-privacy-suite' ),
				'category'         => 'wpconsent-tools',
				'output_schema'    => array(
					'type'       => 'object',
					'properties' => array(
						'page_id' => array( 'type' => 'integer' ),
						'title'   => array( 'type' => 'string' ),
						'url'     => array( 'type' => 'string' ),
					),
				),
				'execute_callback' => array( $this, 'generate_cookie_policy' ),
			)
		);

		$this->api->add(
			'export-settings',
			'read',
			array(
				'label'            => __( 'Export WPConsent settings', 'wpconsent-cookies-banner-privacy-suite' ),
				'description'      => __( 'Returns the WPConsent configuration in the same format as the Tools export file. Keep a copy before importing or resetting; note that importing it back does not restore everything (see import-settings).', 'wpconsent-cookies-banner-privacy-suite' ),
				'category'         => 'wpconsent-tools',
				'input_schema'     => array(
					'type'                 => 'object',
					'properties'           => array(
						'sections' => array(
							'type'  => 'array',
							'items' => array(
								'type' => 'string',
								'enum' => $this->export_sections,
							),
						),
					),
					'additionalProperties' => false,
					'default'              => array(),
				),
				'output_schema'    => array( 'type' => 'object' ),
				'execute_callback' => array( $this, 'export_settings' ),
			)
		);

		$this->api->add(
			'import-settings',
			'overwrite',
			array(
				'label'            => __( 'Import WPConsent settings', 'wpconsent-cookies-banner-privacy-suite' ),
				'description'      => __( 'Imports a WPConsent configuration (the output of export-settings or a Tools export file), replacing the matching parts of the current configuration. It works like the Tools import, which has known limits: settings are stored as text, nested settings such as the IAB TCF publisher restrictions and declarations are emptied, HTML in translated texts is stripped, and a new Cookie Policy page is created. Export the current configuration first and confirm with the user, then send "confirm": true.', 'wpconsent-cookies-banner-privacy-suite' ),
				'category'         => 'wpconsent-tools',
				'input_schema'     => array(
					'type'                 => 'object',
					'properties'           => array(
						'data' => array( 'type' => 'object' ),
					),
					'required'             => array( 'data' ),
					'additionalProperties' => false,
				),
				'output_schema'    => array(
					'type'       => 'object',
					'properties' => array(
						'imported' => array(
							'type'  => 'array',
							'items' => array( 'type' => 'string' ),
						),
					),
				),
				'confirm'          => __( 'Importing replaces the current WPConsent configuration. Export it first.', 'wpconsent-cookies-banner-privacy-suite' ),
				'execute_callback' => array( $this, 'import_settings' ),
			)
		);

		$this->api->add(
			'clear-cache',
			'update',
			array(
				'label'            => __( 'Clear the WPConsent cache', 'wpconsent-cookies-banner-privacy-suite' ),
				'description'      => __( 'Clears the cached cookie and service data so the banner and cookie policy show the latest configuration.', 'wpconsent-cookies-banner-privacy-suite' ),
				'category'         => 'wpconsent-tools',
				'output_schema'    => array(
					'type'       => 'object',
					'properties' => array(
						'cleared' => array( 'type' => 'boolean' ),
					),
				),
				'execute_callback' => array( $this, 'clear_cache' ),
			)
		);

		$this->api->add(
			'reset-to-defaults',
			'delete',
			array(
				'label'            => __( 'Reset WPConsent to defaults', 'wpconsent-cookies-banner-privacy-suite' ),
				'description'      => __( 'Resets the banner texts, the names and descriptions of the Essential, Statistics and Marketing categories, and the consent preferences cookie to their defaults, like the Tools reset. Other categories, services and cookies are kept. Export the configuration first, confirm with the user, then send "confirm": true.', 'wpconsent-cookies-banner-privacy-suite' ),
				'category'         => 'wpconsent-tools',
				'output_schema'    => array(
					'type'       => 'object',
					'properties' => array(
						'reset' => array( 'type' => 'boolean' ),
					),
				),
				'confirm'          => __( 'Resetting replaces the banner texts and the default category texts. Export the configuration first.', 'wpconsent-cookies-banner-privacy-suite' ),
				'execute_callback' => array( $this, 'reset_to_defaults' ),
			)
		);

		$this->api->add(
			'list-addons',
			'read',
			array(
				'label'            => __( 'List addons and recommended plugins', 'wpconsent-cookies-banner-privacy-suite' ),
				'description'      => __( 'Lists the plugins WPConsent recommends, and its addons when available, with whether each one is installed and active.', 'wpconsent-cookies-banner-privacy-suite' ),
				'category'         => 'wpconsent-tools',
				'output_schema'    => array(
					'type'       => 'object',
					'properties' => array(
						'recommended' => array(
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'properties' => array(
									'slug'        => array( 'type' => 'string' ),
									'name'        => array( 'type' => 'string' ),
									'description' => array( 'type' => 'string' ),
									'installed'   => array( 'type' => 'boolean' ),
									'active'      => array( 'type' => 'boolean' ),
								),
							),
						),
					),
				),
				'execute_callback' => array( $this, 'list_addons' ),
			)
		);

		$this->api->add(
			'install-recommended-plugin',
			'create',
			array(
				'label'               => __( 'Install a recommended plugin', 'wpconsent-cookies-banner-privacy-suite' ),
				'description'         => __( 'Installs and activates a plugin from the list returned by list-addons (recommended). Only plugins on that list can be installed.', 'wpconsent-cookies-banner-privacy-suite' ),
				'category'            => 'wpconsent-tools',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'slug' => array( 'type' => 'string' ),
					),
					'required'             => array( 'slug' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'message' => array( 'type' => 'string' ),
					),
				),
				'permission_callback' => array( $this, 'can_install_plugins' ),
				'execute_callback'    => array( $this, 'install_recommended_plugin' ),
			)
		);
	}

	/**
	 * Execute callback for wpconsent/generate-cookie-policy.
	 *
	 * @return array|WP_Error
	 */
	public function generate_cookie_policy() {
		$this->api->component( 'admin_ajax' );

		$page_id = wpconsent_generate_cookie_policy_page();
		if ( is_wp_error( $page_id ) ) {
			return $page_id;
		}

		return array(
			'page_id' => (int) $page_id,
			'title'   => get_the_title( $page_id ),
			'url'     => (string) get_permalink( $page_id ),
		);
	}

	/**
	 * Execute callback for wpconsent/export-settings.
	 *
	 * @param array $input Ability input.
	 *
	 * @return array|WP_Error
	 */
	public function export_settings( $input ) {
		$tools = $this->api->component( 'tools_page' );
		if ( is_wp_error( $tools ) ) {
			return $tools;
		}

		$sections = ! empty( $input['sections'] ) ? $input['sections'] : $this->export_sections;

		return $tools->get_export_data( $sections );
	}

	/**
	 * Execute callback for wpconsent/import-settings.
	 *
	 * @param array $input Ability input.
	 *
	 * @return array|WP_Error
	 */
	public function import_settings( $input ) {
		if ( empty( $input['data'] ) || ! is_array( $input['data'] ) ) {
			return new WP_Error( 'wpconsent_invalid_import', __( 'Import data cannot be processed.', 'wpconsent-cookies-banner-privacy-suite' ) );
		}

		$tools = $this->api->component( 'tools_page' );
		if ( is_wp_error( $tools ) ) {
			return $tools;
		}

		$tools->import_data( $input['data'] );

		return array( 'imported' => array_keys( $input['data'] ) );
	}

	/**
	 * Execute callback for wpconsent/clear-cache.
	 *
	 * @return array
	 */
	public function clear_cache() {
		wpconsent()->cookies->clear_cookies_cache();

		return array( 'cleared' => true );
	}

	/**
	 * Execute callback for wpconsent/reset-to-defaults.
	 *
	 * @return array|WP_Error
	 */
	public function reset_to_defaults() {
		$this->api->component( 'admin_ajax' );

		wpconsent_reset_to_defaults();

		return array( 'reset' => true );
	}

	/**
	 * Execute callback for wpconsent/list-addons.
	 *
	 * @return array|WP_Error
	 */
	public function list_addons() {
		$recommended_plugins = $this->api->component( 'recommended_plugins' );
		if ( is_wp_error( $recommended_plugins ) ) {
			return $recommended_plugins;
		}

		$recommended = array();
		foreach ( $recommended_plugins->get_plugin_list() as $slug => $plugin ) {
			$recommended[] = array_merge( array( 'slug' => $slug ), $plugin );
		}

		/**
		 * Filter the list returned by the list-addons ability.
		 *
		 * @param array $list The list, with a recommended key.
		 */
		return apply_filters( 'wpconsent_abilities_addons_list', array( 'recommended' => $recommended ) );
	}

	/**
	 * Execute callback for wpconsent/install-recommended-plugin.
	 *
	 * @param array $input Ability input.
	 *
	 * @return array|WP_Error
	 */
	public function install_recommended_plugin( $input ) {
		$recommended_plugins = $this->api->component( 'recommended_plugins' );
		if ( is_wp_error( $recommended_plugins ) ) {
			return $recommended_plugins;
		}

		// Only what list-addons shows; the installer's own allowlist also has cross-promo plugins.
		$slug = sanitize_key( $input['slug'] );
		if ( ! array_key_exists( $slug, $recommended_plugins->get_plugin_list() ) ) {
			return new WP_Error( 'wpconsent_invalid_plugin', __( 'Only plugins returned by list-addons (recommended) can be installed.', 'wpconsent-cookies-banner-privacy-suite' ) );
		}

		return $recommended_plugins->install_plugin( $slug );
	}

	/**
	 * Installing plugins needs the WordPress install and activate capabilities on top of managing WPConsent.
	 *
	 * @return bool
	 */
	public function can_install_plugins() {
		return $this->api->check_permission() && current_user_can( 'install_plugins' ) && current_user_can( 'activate_plugins' );
	}
}
