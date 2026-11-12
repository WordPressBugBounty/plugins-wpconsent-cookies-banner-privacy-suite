<?php
/**
 * Base class for a group of WPConsent abilities.
 *
 * @package WPConsent
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Base class for a group of related abilities.
 */
abstract class WPConsent_Abilities_Group {

	/**
	 * The abilities registrar.
	 *
	 * @var WPConsent_Abilities_API
	 */
	protected $api;

	/**
	 * Constructor.
	 *
	 * @param WPConsent_Abilities_API $api The abilities registrar.
	 */
	public function __construct( $api ) {
		$this->api = $api;
	}

	/**
	 * Register this group's abilities through $this->api->add().
	 *
	 * @return void
	 */
	abstract public function register();

	/**
	 * Is this a top-level cookie category?
	 *
	 * @param int $term_id Term ID.
	 *
	 * @return bool
	 */
	protected function is_category( $term_id ) {
		return 0 === $this->term_parent( $term_id );
	}

	/**
	 * Is this a service (a term under a category)?
	 *
	 * @param int $term_id Term ID.
	 *
	 * @return bool
	 */
	protected function is_service( $term_id ) {
		$parent = $this->term_parent( $term_id );

		return null !== $parent && 0 !== $parent;
	}

	/**
	 * The parent of a cookie category or service term.
	 *
	 * @param int $term_id Term ID.
	 *
	 * @return int|null Parent term ID (0 for a category), or null when there is no such term.
	 */
	protected function term_parent( $term_id ) {
		$term = get_term( (int) $term_id, wpconsent()->cookies->taxonomy );

		return $term && ! is_wp_error( $term ) ? (int) $term->parent : null;
	}

	/**
	 * A not-found error for the given item type.
	 *
	 * @param string $type Item type, already translated.
	 *
	 * @return WP_Error
	 */
	protected function not_found( $type ) {
		return new WP_Error(
			'wpconsent_not_found',
			/* translators: %s: item type, e.g. category, service or cookie. */
			sprintf( __( 'No %s found with this ID.', 'wpconsent-cookies-banner-privacy-suite' ), $type )
		);
	}
}
