<?php
/**
 * Entitlement checks for the Legal Documents feature.
 *
 * Shared by Lite and Pro. Lite never has a license component, so every
 * check resolves to the locked state there.
 *
 * @package WPConsent
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPConsent_Legal_Documents_Entitlement.
 */
class WPConsent_Legal_Documents_Entitlement {

	/**
	 * Addon SKU that licensing may attach to a key to unlock the feature.
	 * Groundwork only: licensing does not send this SKU yet, so today the
	 * Plus-or-higher check is what unlocks Legal Documents.
	 *
	 * @var string
	 */
	const SKU = 'wpconsent-legal-documents';

	/**
	 * Upsell slugs on the locked page, keyed by action. Clicks on these are
	 * the only Legal Documents clicks the upsell recorder counts.
	 *
	 * @var string[]
	 */
	const UPSELL_SLUGS = array(
		'upgrade'  => 'legal-documents-main',
		'renew'    => 'legal-documents-renew',
		'activate' => 'legal-documents-activate',
	);

	/**
	 * Whether the current license unlocks Legal Documents.
	 *
	 * Addon-style entitlement first (is_addon_allowed semantics), with Plus or
	 * higher as the fallback until licensing emits the SKU. The documents
	 * service enforces the real gate; this only drives the admin UI.
	 *
	 * @param bool|null $multisite Whether to check the network license. Defaults to the
	 *                             current context (network admin on multisite).
	 *
	 * @return bool
	 */
	public static function allowed( $multisite = null ) {
		$allowed = false;

		if ( isset( wpconsent()->license ) && is_object( wpconsent()->license ) ) {
			$multisite = self::resolve_multisite( $multisite );
			$license   = wpconsent()->license;
			$allowed   = $license->is_active( $multisite ) && ( $license->is_addon_allowed( self::SKU, $multisite ) || $license->license_can( 'plus', $multisite ) );
		}

		/**
		 * Filter whether Legal Documents is unlocked for this site.
		 *
		 * @param bool $allowed Whether the feature is unlocked.
		 */
		return (bool) apply_filters( 'wpconsent_legal_documents_allowed', $allowed );
	}

	/**
	 * The entitlement state used to pick the admin view.
	 *
	 * @param bool|null $multisite Whether to check the network license. Defaults to the
	 *                             current context (network admin on multisite).
	 *
	 * @return string One of lite | inactive | expired | basic | active.
	 */
	public static function state( $multisite = null ) {
		$multisite = self::resolve_multisite( $multisite );

		// allowed() runs the wpconsent_legal_documents_allowed filter, so a site that forces it on is active whatever the key.
		if ( self::allowed( $multisite ) ) {
			return 'active';
		}

		if ( ! isset( wpconsent()->license ) || ! is_object( wpconsent()->license ) ) {
			return 'lite';
		}

		$license = wpconsent()->license;

		if ( ! $license->get( $multisite ) ) {
			return 'inactive';
		}

		// The licence keeps its type when it expires; renewing Basic does not unlock the feature, so it gets the upgrade.
		if ( $license->is_expired( $multisite ) ) {
			return 'basic' === $license->type( $multisite ) ? 'basic' : 'expired';
		}

		if ( ! $license->is_active( $multisite ) ) {
			return 'inactive';
		}

		return 'basic';
	}

	/**
	 * Resolve the multisite flag from the current context when not given.
	 *
	 * @param bool|null $multisite The explicit flag, or null to detect.
	 *
	 * @return bool
	 */
	private static function resolve_multisite( $multisite ) {
		if ( null === $multisite ) {
			return is_multisite() && is_network_admin();
		}

		return (bool) $multisite;
	}
}
