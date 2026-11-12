<?php
/**
 * Abilities for cookie categories, services and cookies.
 *
 * @package WPConsent
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WPConsent_Abilities_Cookies class.
 */
class WPConsent_Abilities_Cookies extends WPConsent_Abilities_Group {

	/**
	 * Default categories WPConsent needs for automatic script blocking.
	 *
	 * @var string[]
	 */
	protected $protected_categories = array( 'essential', 'statistics', 'marketing' );

	/**
	 * Register the cookie abilities.
	 *
	 * @return void
	 */
	public function register() {
		$id_input = array(
			'type'                 => 'object',
			'properties'           => array(
				'id' => array( 'type' => 'integer' ),
			),
			'required'             => array( 'id' ),
			'additionalProperties' => false,
		);
		$filter   = array(
			'type'                 => 'object',
			'properties'           => array(
				'category_id' => array(
					'type'        => 'integer',
					'description' => __( 'Only return items under this category (or service) ID.', 'wpconsent-cookies-banner-privacy-suite' ),
				),
				'locale'      => $this->api->locale_schema(),
			),
			'additionalProperties' => false,
			'default'              => array(),
		);
		$deleted  = array(
			'type'       => 'object',
			'properties' => array(
				'deleted' => array( 'type' => 'boolean' ),
			),
		);

		$this->add(
			'list-categories',
			'read',
			__( 'List cookie categories', 'wpconsent-cookies-banner-privacy-suite' ),
			__( 'Lists the cookie categories (Essential, Statistics, Marketing and custom ones) with their IDs.', 'wpconsent-cookies-banner-privacy-suite' ),
			'list_categories',
			array(
				'type'                 => 'object',
				'properties'           => array( 'locale' => $this->api->locale_schema() ),
				'additionalProperties' => false,
				'default'              => array(),
			),
			array(
				'type'  => 'array',
				'items' => $this->category_schema(),
			)
		);

		$this->add(
			'add-category',
			'create',
			__( 'Add a cookie category', 'wpconsent-cookies-banner-privacy-suite' ),
			__( 'Adds a custom cookie category.', 'wpconsent-cookies-banner-privacy-suite' ),
			'add_category',
			$this->object_schema(
				array(
					'name'        => array( 'type' => 'string' ),
					'description' => array( 'type' => 'string' ),
				),
				array( 'name' )
			),
			$this->category_schema()
		);

		$this->add(
			'update-category',
			'update',
			__( 'Update a cookie category', 'wpconsent-cookies-banner-privacy-suite' ),
			__( 'Updates the name and/or description of a cookie category, in the site language unless "locale" is sent. Fields that are not sent keep their current value.', 'wpconsent-cookies-banner-privacy-suite' ),
			'update_category',
			$this->object_schema(
				array(
					'id'          => array( 'type' => 'integer' ),
					'name'        => array( 'type' => 'string' ),
					'description' => array( 'type' => 'string' ),
					'locale'      => $this->api->locale_schema(),
				),
				array( 'id' )
			),
			$this->category_schema()
		);

		$this->add(
			'delete-category',
			'delete',
			__( 'Delete a cookie category', 'wpconsent-cookies-banner-privacy-suite' ),
			__( 'Deletes a custom cookie category and every cookie in it. The category must have no services left (delete or move them first). The Essential, Statistics and Marketing categories cannot be deleted. Confirm with the user first, then send "confirm": true.', 'wpconsent-cookies-banner-privacy-suite' ),
			'delete_category',
			$id_input,
			$deleted,
			__( 'Deleting a category permanently removes it and every cookie in it from the cookie policy.', 'wpconsent-cookies-banner-privacy-suite' )
		);

		$this->add(
			'list-services',
			'read',
			__( 'List services', 'wpconsent-cookies-banner-privacy-suite' ),
			__( 'Lists the configured services (for example Google Analytics) with the category each one belongs to.', 'wpconsent-cookies-banner-privacy-suite' ),
			'list_services',
			$filter,
			array(
				'type'  => 'array',
				'items' => $this->service_schema(),
			)
		);

		$this->add(
			'add-service',
			'create',
			__( 'Add a service', 'wpconsent-cookies-banner-privacy-suite' ),
			__( 'Adds a service under a cookie category.', 'wpconsent-cookies-banner-privacy-suite' ),
			'add_service',
			$this->object_schema(
				array(
					'name'        => array( 'type' => 'string' ),
					'category_id' => array( 'type' => 'integer' ),
					'description' => array( 'type' => 'string' ),
					'url'         => array(
						'type'        => 'string',
						'description' => __( 'Link to the service privacy policy.', 'wpconsent-cookies-banner-privacy-suite' ),
					),
				),
				array( 'name', 'category_id' )
			),
			$this->service_schema()
		);

		$this->add(
			'update-service',
			'update',
			__( 'Update a service', 'wpconsent-cookies-banner-privacy-suite' ),
			__( 'Updates a service, in the site language unless "locale" is sent. Fields that are not sent keep their current value; send category_id to move it to another category.', 'wpconsent-cookies-banner-privacy-suite' ),
			'update_service',
			$this->object_schema(
				array(
					'id'          => array( 'type' => 'integer' ),
					'name'        => array( 'type' => 'string' ),
					'category_id' => array( 'type' => 'integer' ),
					'description' => array( 'type' => 'string' ),
					'url'         => array( 'type' => 'string' ),
					'locale'      => $this->api->locale_schema(),
				),
				array( 'id' )
			),
			$this->service_schema()
		);

		$this->add(
			'delete-service',
			'delete',
			__( 'Delete a service', 'wpconsent-cookies-banner-privacy-suite' ),
			__( 'Deletes a service, the same as the delete button on the Cookies page. Confirm with the user first, then send "confirm": true.', 'wpconsent-cookies-banner-privacy-suite' ),
			'delete_service',
			$id_input,
			$deleted,
			__( 'Deleting a service permanently removes it from the cookie policy.', 'wpconsent-cookies-banner-privacy-suite' )
		);

		$this->add(
			'list-cookies',
			'read',
			__( 'List cookies', 'wpconsent-cookies-banner-privacy-suite' ),
			__( 'Lists the declared cookies with their description and duration. Pass a category or service ID to narrow the list.', 'wpconsent-cookies-banner-privacy-suite' ),
			'list_cookies',
			$filter,
			array(
				'type'  => 'array',
				'items' => $this->cookie_schema(),
			)
		);

		$this->add(
			'add-cookie',
			'create',
			__( 'Add a cookie', 'wpconsent-cookies-banner-privacy-suite' ),
			__( 'Declares a cookie under a category, or under a service when service_id is sent.', 'wpconsent-cookies-banner-privacy-suite' ),
			'add_cookie',
			$this->object_schema(
				array(
					'cookie_id'   => array(
						'type'        => 'string',
						'description' => __( 'The cookie name as set in the browser, e.g. _ga.', 'wpconsent-cookies-banner-privacy-suite' ),
					),
					'name'        => array( 'type' => 'string' ),
					'description' => array( 'type' => 'string' ),
					'category_id' => array( 'type' => 'integer' ),
					'service_id'  => array( 'type' => 'integer' ),
					'duration'    => array( 'type' => 'string' ),
				),
				array( 'cookie_id', 'name', 'category_id' )
			),
			$this->cookie_schema()
		);

		$this->add(
			'update-cookie',
			'update',
			__( 'Update a cookie', 'wpconsent-cookies-banner-privacy-suite' ),
			__( 'Updates a declared cookie. Fields that are not sent keep their current value. Send service_id to move it under a service, or another category_id to move it to that category directly.', 'wpconsent-cookies-banner-privacy-suite' ),
			'update_cookie',
			$this->object_schema(
				array(
					'id'          => array( 'type' => 'integer' ),
					'cookie_id'   => array( 'type' => 'string' ),
					'name'        => array( 'type' => 'string' ),
					'description' => array( 'type' => 'string' ),
					'category_id' => array( 'type' => 'integer' ),
					'service_id'  => array( 'type' => 'integer' ),
					'duration'    => array( 'type' => 'string' ),
				),
				array( 'id' )
			),
			$this->cookie_schema()
		);

		$this->add(
			'delete-cookie',
			'delete',
			__( 'Delete a cookie', 'wpconsent-cookies-banner-privacy-suite' ),
			__( 'Deletes a declared cookie. Confirm with the user first, then send "confirm": true.', 'wpconsent-cookies-banner-privacy-suite' ),
			'delete_cookie',
			$id_input,
			$deleted,
			__( 'Deleting a cookie permanently removes it from the cookie policy.', 'wpconsent-cookies-banner-privacy-suite' )
		);
	}

	/**
	 * Register one ability of this group.
	 *
	 * @param string     $slug        Ability slug.
	 * @param string     $type        Annotation preset.
	 * @param string     $label       Label.
	 * @param string     $description Description.
	 * @param string     $method      Execute method on this class.
	 * @param array|null $input       Input schema, null when the ability takes no input.
	 * @param array      $output      Output schema.
	 * @param string     $confirm     Why the ability needs "confirm": true; null when it runs without one.
	 *
	 * @return void
	 */
	protected function add( $slug, $type, $label, $description, $method, $input, $output, $confirm = null ) {
		$args = array(
			'label'            => $label,
			'description'      => $description,
			'category'         => 'wpconsent-cookies',
			'output_schema'    => $output,
			'execute_callback' => array( $this, $method ),
		);
		if ( null !== $input ) {
			$args['input_schema'] = $input;
		}
		if ( null !== $confirm ) {
			$args['confirm'] = $confirm;
		}

		$this->api->add( $slug, $type, $args );
	}

	/**
	 * Execute callback for wpconsent/list-categories.
	 *
	 * @return array
	 */
	public function list_categories() {
		$categories = array();
		foreach ( wpconsent()->cookies->get_categories() as $slug => $category ) {
			$categories[] = $this->format_category( $category['id'], $slug, $category['name'], $category['description'], $category['required'] );
		}

		return $categories;
	}

	/**
	 * Execute callback for wpconsent/add-category.
	 *
	 * @param array $input Ability input.
	 *
	 * @return array|WP_Error
	 */
	public function add_category( $input ) {
		$category_id = wpconsent()->cookies->add_category(
			sanitize_text_field( $input['name'] ),
			isset( $input['description'] ) ? sanitize_textarea_field( $input['description'] ) : ''
		);

		if ( ! $category_id ) {
			return new WP_Error( 'wpconsent_save_failed', __( 'The category could not be added. A category with this name may already exist.', 'wpconsent-cookies-banner-privacy-suite' ) );
		}

		return $this->get_category( $category_id );
	}

	/**
	 * Execute callback for wpconsent/update-category.
	 *
	 * @param array $input Ability input.
	 *
	 * @return array|WP_Error
	 */
	public function update_category( $input ) {
		if ( ! $this->is_category( $input['id'] ) ) {
			return $this->not_found( __( 'category', 'wpconsent-cookies-banner-privacy-suite' ) );
		}

		// Unsent fields keep their value in the language being edited.
		$current = $this->get_category( $input['id'] );

		$updated = wpconsent()->cookies->update_category(
			$input['id'],
			isset( $input['name'] ) ? sanitize_text_field( $input['name'] ) : $current['name'],
			isset( $input['description'] ) ? sanitize_textarea_field( $input['description'] ) : $current['description']
		);

		if ( ! $updated ) {
			return new WP_Error( 'wpconsent_save_failed', __( 'The category could not be updated.', 'wpconsent-cookies-banner-privacy-suite' ) );
		}

		return $this->get_category( $input['id'] );
	}

	/**
	 * Execute callback for wpconsent/delete-category.
	 *
	 * @param array $input Ability input.
	 *
	 * @return array|WP_Error
	 */
	public function delete_category( $input ) {
		$current = wpconsent()->cookies->get_category_by_id( $input['id'] );
		if ( ! $current || ! $this->is_category( $input['id'] ) ) {
			return $this->not_found( __( 'category', 'wpconsent-cookies-banner-privacy-suite' ) );
		}

		if ( in_array( $current['slug'], $this->protected_categories, true ) ) {
			return new WP_Error( 'wpconsent_protected_category', __( 'The Essential, Statistics and Marketing categories are needed for script blocking and cannot be deleted.', 'wpconsent-cookies-banner-privacy-suite' ) );
		}

		// Deleting the term would turn its services into top-level categories.
		$has_services = get_terms(
			array(
				'taxonomy'   => wpconsent()->cookies->taxonomy,
				'parent'     => (int) $input['id'],
				'number'     => 1,
				'fields'     => 'ids',
				'hide_empty' => false,
			)
		);
		if ( $has_services ) {
			return new WP_Error( 'wpconsent_category_has_services', __( 'This category still has services. Delete them or move them to another category first.', 'wpconsent-cookies-banner-privacy-suite' ) );
		}

		return array( 'deleted' => wpconsent()->cookies->delete_category( $input['id'] ) );
	}

	/**
	 * Execute callback for wpconsent/list-services.
	 *
	 * @param array $input Ability input.
	 *
	 * @return array
	 */
	public function list_services( $input ) {
		$category_ids = ! empty( $input['category_id'] ) ? array( (int) $input['category_id'] ) : wp_list_pluck( wpconsent()->cookies->get_categories(), 'id' );

		$services = array();
		foreach ( $category_ids as $category_id ) {
			foreach ( wpconsent()->cookies->get_services_by_category( $category_id ) as $service ) {
				$services[] = $this->format_service( $service['id'] );
			}
		}

		return $services;
	}

	/**
	 * Execute callback for wpconsent/add-service.
	 *
	 * @param array $input Ability input.
	 *
	 * @return array|WP_Error
	 */
	public function add_service( $input ) {
		if ( ! $this->is_category( $input['category_id'] ) ) {
			return $this->not_found( __( 'category', 'wpconsent-cookies-banner-privacy-suite' ) );
		}

		$service_id = wpconsent()->cookies->add_service(
			sanitize_text_field( $input['name'] ),
			(int) $input['category_id'],
			isset( $input['description'] ) ? sanitize_textarea_field( $input['description'] ) : '',
			isset( $input['url'] ) ? esc_url_raw( $input['url'] ) : ''
		);

		if ( ! $service_id ) {
			return new WP_Error( 'wpconsent_save_failed', __( 'The service could not be added. A service with this name may already exist.', 'wpconsent-cookies-banner-privacy-suite' ) );
		}

		return $this->format_service( $service_id );
	}

	/**
	 * Execute callback for wpconsent/update-service.
	 *
	 * @param array $input Ability input.
	 *
	 * @return array|WP_Error
	 */
	public function update_service( $input ) {
		if ( ! $this->is_service( $input['id'] ) ) {
			return $this->not_found( __( 'service', 'wpconsent-cookies-banner-privacy-suite' ) );
		}
		if ( isset( $input['category_id'] ) && ! $this->is_category( $input['category_id'] ) ) {
			return $this->not_found( __( 'category', 'wpconsent-cookies-banner-privacy-suite' ) );
		}

		// Unsent fields keep their value in the language being edited.
		$current = $this->format_service( $input['id'] );
		$updated = wpconsent()->cookies->update_service(
			$input['id'],
			isset( $input['name'] ) ? sanitize_text_field( $input['name'] ) : $current['name'],
			isset( $input['description'] ) ? sanitize_textarea_field( $input['description'] ) : $current['description'],
			isset( $input['url'] ) ? esc_url_raw( $input['url'] ) : $current['service_url'],
			isset( $input['category_id'] ) ? (int) $input['category_id'] : false
		);

		if ( ! $updated ) {
			return new WP_Error( 'wpconsent_save_failed', __( 'The service could not be updated.', 'wpconsent-cookies-banner-privacy-suite' ) );
		}

		// A translation save skips the term update, so move the service to its new category here.
		if ( isset( $input['category_id'] ) && $this->term_parent( $input['id'] ) !== (int) $input['category_id'] ) {
			wp_update_term( (int) $input['id'], wpconsent()->cookies->taxonomy, array( 'parent' => (int) $input['category_id'] ) );
			wpconsent()->cookies->clear_cookies_cache();
		}

		return $this->format_service( $input['id'] );
	}

	/**
	 * Execute callback for wpconsent/delete-service.
	 *
	 * @param array $input Ability input.
	 *
	 * @return array|WP_Error
	 */
	public function delete_service( $input ) {
		if ( ! $this->is_service( $input['id'] ) ) {
			return $this->not_found( __( 'service', 'wpconsent-cookies-banner-privacy-suite' ) );
		}

		return array( 'deleted' => wpconsent()->cookies->delete_service( $input['id'] ) );
	}

	/**
	 * Execute callback for wpconsent/list-cookies.
	 *
	 * @param array $input Ability input.
	 *
	 * @return array
	 */
	public function list_cookies( $input ) {
		$term_ids = ! empty( $input['category_id'] ) ? array( (int) $input['category_id'] ) : wp_list_pluck( wpconsent()->cookies->get_categories(), 'id' );

		$cookies = array();
		foreach ( $term_ids as $term_id ) {
			foreach ( wpconsent()->cookies->get_cookies_by_category( $term_id ) as $cookie ) {
				$cookies[ $cookie['id'] ] = $this->format_cookie( $cookie['id'] );
			}
		}

		return array_values( $cookies );
	}

	/**
	 * Execute callback for wpconsent/add-cookie.
	 *
	 * @param array $input Ability input.
	 *
	 * @return array|WP_Error
	 */
	public function add_cookie( $input ) {
		$term_id = $this->cookie_term( $input );
		if ( is_wp_error( $term_id ) ) {
			return $term_id;
		}

		$post_id = wpconsent()->cookies->add_cookie(
			sanitize_text_field( $input['cookie_id'] ),
			sanitize_text_field( $input['name'] ),
			isset( $input['description'] ) ? sanitize_textarea_field( $input['description'] ) : '',
			$term_id,
			isset( $input['duration'] ) ? sanitize_text_field( $input['duration'] ) : ''
		);

		if ( ! $post_id || is_wp_error( $post_id ) ) {
			return new WP_Error( 'wpconsent_save_failed', __( 'The cookie could not be added.', 'wpconsent-cookies-banner-privacy-suite' ) );
		}

		return $this->format_cookie( $post_id );
	}

	/**
	 * Execute callback for wpconsent/update-cookie.
	 *
	 * @param array $input Ability input.
	 *
	 * @return array|WP_Error
	 */
	public function update_cookie( $input ) {
		$current = $this->format_cookie( $input['id'] );
		if ( ! $current ) {
			return $this->not_found( __( 'cookie', 'wpconsent-cookies-banner-privacy-suite' ) );
		}

		// Sending the category the cookie already reports keeps it where it is, service included,
		// so the output of list-cookies can be sent back unchanged.
		$term_id = $current['service_id'] ? $current['service_id'] : $current['category_id'];
		if ( isset( $input['service_id'] ) || ( isset( $input['category_id'] ) && (int) $input['category_id'] !== $current['category_id'] ) ) {
			$term_id = $this->cookie_term( array_merge( array( 'category_id' => $current['category_id'] ), $input ) );
			if ( is_wp_error( $term_id ) ) {
				return $term_id;
			}
		}

		$post_id = wpconsent()->cookies->update_cookie(
			$input['id'],
			isset( $input['cookie_id'] ) ? sanitize_text_field( $input['cookie_id'] ) : $current['cookie_id'],
			isset( $input['name'] ) ? sanitize_text_field( $input['name'] ) : $current['name'],
			isset( $input['description'] ) ? sanitize_textarea_field( $input['description'] ) : $current['description'],
			$term_id,
			isset( $input['duration'] ) ? sanitize_text_field( $input['duration'] ) : $current['duration']
		);

		if ( ! $post_id || is_wp_error( $post_id ) ) {
			return new WP_Error( 'wpconsent_save_failed', __( 'The cookie could not be updated.', 'wpconsent-cookies-banner-privacy-suite' ) );
		}

		return $this->format_cookie( $input['id'] );
	}

	/**
	 * Execute callback for wpconsent/delete-cookie.
	 *
	 * @param array $input Ability input.
	 *
	 * @return array|WP_Error
	 */
	public function delete_cookie( $input ) {
		if ( ! $this->format_cookie( $input['id'] ) ) {
			return $this->not_found( __( 'cookie', 'wpconsent-cookies-banner-privacy-suite' ) );
		}

		return array( 'deleted' => (bool) wpconsent()->cookies->delete_cookie( $input['id'] ) );
	}

	/**
	 * The term a cookie is attached to: the service when one is sent, otherwise the category.
	 *
	 * @param array $input Ability input with category_id and optional service_id.
	 *
	 * @return int|WP_Error
	 */
	protected function cookie_term( $input ) {
		if ( ! empty( $input['service_id'] ) ) {
			return $this->is_service( $input['service_id'] ) ? (int) $input['service_id'] : $this->not_found( __( 'service', 'wpconsent-cookies-banner-privacy-suite' ) );
		}

		return $this->is_category( $input['category_id'] ) ? (int) $input['category_id'] : $this->not_found( __( 'category', 'wpconsent-cookies-banner-privacy-suite' ) );
	}

	/**
	 * Category data for output.
	 *
	 * @param int $category_id Term ID.
	 *
	 * @return array
	 */
	protected function get_category( $category_id ) {
		$category = wpconsent()->cookies->get_category_by_id( $category_id );

		// Pro multilanguage swaps in the requested language's texts here, as get_categories() does.
		$category = apply_filters( 'wpconsent_category_data', $category, $category['id'] );

		return $this->format_category( $category['id'], $category['slug'], $category['name'], $category['description'], $category['required'] );
	}

	/**
	 * Shape a category for output.
	 *
	 * @param int    $id          Term ID.
	 * @param string $slug        Slug.
	 * @param string $name        Name.
	 * @param string $description Description.
	 * @param mixed  $required    Whether the category is required.
	 *
	 * @return array
	 */
	protected function format_category( $id, $slug, $name, $description, $required ) {
		return array(
			'id'          => (int) $id,
			'slug'        => (string) $slug,
			'name'        => (string) $name,
			'description' => (string) $description,
			'required'    => (bool) $required,
		);
	}

	/**
	 * Service data for output.
	 *
	 * @param int $service_id Term ID.
	 *
	 * @return array
	 */
	protected function format_service( $service_id ) {
		$term = get_term( (int) $service_id, wpconsent()->cookies->taxonomy );

		// Pro multilanguage swaps in the requested language's texts here, as it does for the admin.
		$texts = apply_filters(
			'wpconsent_service_data',
			array(
				'name'        => $term->name,
				'description' => $term->description,
				'url'         => (string) get_term_meta( $term->term_id, 'wpconsent_service_url', true ),
			),
			$term->term_id
		);

		return array(
			'id'          => (int) $term->term_id,
			'name'        => $texts['name'],
			'description' => $texts['description'],
			'service_url' => (string) $texts['url'],
			'category_id' => (int) $term->parent,
			'auto_added'  => wpconsent()->cookies->is_service_auto_added( $term->term_id ),
		);
	}

	/**
	 * Cookie data for output.
	 *
	 * @param int $post_id Cookie post ID.
	 *
	 * @return array|false False when there is no cookie with this ID.
	 */
	protected function format_cookie( $post_id ) {
		$post = get_post( (int) $post_id );
		if ( ! $post || wpconsent()->cookies->post_type !== $post->post_type ) {
			return false;
		}

		$terms = wp_get_post_terms( $post->ID, wpconsent()->cookies->taxonomy, array( 'fields' => 'ids' ) );

		// Pro multilanguage swaps in the requested language's texts here, as it does for the admin.
		$texts = apply_filters(
			'wpconsent_cookie_data',
			array(
				'name'        => $post->post_title,
				'description' => $post->post_content,
				'duration'    => (string) get_post_meta( $post->ID, 'wpconsent_cookie_duration', true ),
			),
			$post->ID
		);

		// The cookie is attached to one term: a category, or a service under a category.
		$term_id = is_array( $terms ) && $terms ? (int) $terms[0] : 0;
		$parent  = $term_id ? $this->term_parent( $term_id ) : null;

		return array(
			'id'          => $post->ID,
			'cookie_id'   => (string) get_post_meta( $post->ID, 'wpconsent_cookie_id', true ),
			'name'        => $texts['name'],
			'description' => $texts['description'],
			'duration'    => (string) $texts['duration'],
			'category_id' => $parent ? $parent : $term_id,
			'service_id'  => $parent ? $term_id : 0,
			'auto_added'  => (bool) get_post_meta( $post->ID, '_wpconsent_auto_added', true ),
		);
	}

	/**
	 * Build an object input schema.
	 *
	 * @param array $properties Properties.
	 * @param array $required   Required property names.
	 *
	 * @return array
	 */
	protected function object_schema( $properties, $required ) {
		return array(
			'type'                 => 'object',
			'properties'           => $properties,
			'required'             => $required,
			'additionalProperties' => false,
		);
	}

	/**
	 * Output schema for a category.
	 *
	 * @return array
	 */
	protected function category_schema() {
		return array(
			'type'       => 'object',
			'properties' => array(
				'id'          => array( 'type' => 'integer' ),
				'slug'        => array( 'type' => 'string' ),
				'name'        => array( 'type' => 'string' ),
				'description' => array( 'type' => 'string' ),
				'required'    => array( 'type' => 'boolean' ),
			),
		);
	}

	/**
	 * Output schema for a service.
	 *
	 * @return array
	 */
	protected function service_schema() {
		return array(
			'type'       => 'object',
			'properties' => array(
				'id'          => array( 'type' => 'integer' ),
				'name'        => array( 'type' => 'string' ),
				'description' => array( 'type' => 'string' ),
				'service_url' => array( 'type' => 'string' ),
				'category_id' => array( 'type' => 'integer' ),
				'auto_added'  => array( 'type' => 'boolean' ),
			),
		);
	}

	/**
	 * Output schema for a cookie.
	 *
	 * @return array
	 */
	protected function cookie_schema() {
		return array(
			'type'       => 'object',
			'properties' => array(
				'id'          => array( 'type' => 'integer' ),
				'cookie_id'   => array( 'type' => 'string' ),
				'name'        => array( 'type' => 'string' ),
				'description' => array( 'type' => 'string' ),
				'duration'    => array( 'type' => 'string' ),
				'category_id' => array( 'type' => 'integer' ),
				'service_id'  => array(
					'type'        => 'integer',
					'description' => __( 'The service the cookie is declared under, 0 when it is declared under the category directly.', 'wpconsent-cookies-banner-privacy-suite' ),
				),
				'auto_added'  => array( 'type' => 'boolean' ),
			),
		);
	}
}
