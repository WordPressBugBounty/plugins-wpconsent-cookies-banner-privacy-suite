<?php
/**
 * Local storage for the Legal Documents feature: feature state in the plugin
 * settings, rendered documents in the file cache backed by non-autoloaded
 * options, pending updates, section overrides and a capped activity log.
 *
 * Shared between Lite and Pro because the shortcode and block render stored
 * documents on sites whose licence has lapsed or that went back to Lite.
 *
 * @package WPConsent
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPConsent_Legal_Documents_Store.
 */
class WPConsent_Legal_Documents_Store {

	const SETTINGS_KEY     = 'legal_documents';
	const PENDING_OPTION   = 'wpconsent_legal_pending';
	const OVERRIDES_OPTION = 'wpconsent_legal_overrides';
	const LOG_OPTION       = 'wpconsent_legal_log';
	const LOG_MAX          = 200;
	const DOC_OPTION       = 'wpconsent_legal_document_';
	const DOC_CACHE        = 'legal-document-';
	const SOURCE_LOCALE    = 'source';

	/**
	 * Keys a stored document payload must carry.
	 *
	 * @var array
	 */
	private static $payload_keys = array( 'html', 'section_map', 'versions', 'provenance', 'stale' );

	/**
	 * The feature's settings record.
	 *
	 * @return array
	 */
	public static function all() {
		$data = wpconsent()->settings->get_option( self::SETTINGS_KEY, array() );

		return is_array( $data ) ? $data : array();
	}

	/**
	 * One value from the feature's settings record.
	 *
	 * @param string $key     Key.
	 * @param mixed  $default Default.
	 *
	 * @return mixed
	 */
	public static function get( $key, $default = null ) {
		$data = self::all();

		return array_key_exists( $key, $data ) ? $data[ $key ] : $default;
	}

	/**
	 * A value that must be an array: anything else (a record flattened by a settings import, an old
	 * format) reads as empty instead of failing the request that touches it.
	 *
	 * @param string $key Key.
	 *
	 * @return array
	 */
	public static function get_array( $key ) {
		$value = self::get( $key, array() );

		return is_array( $value ) ? $value : array();
	}

	/**
	 * Store one value.
	 *
	 * @param string $key   Key.
	 * @param mixed  $value Value.
	 */
	public static function set( $key, $value ) {
		self::set_many( array( $key => $value ) );
	}

	/**
	 * Store several values at once.
	 *
	 * @param array $pairs Key => value.
	 */
	public static function set_many( $pairs ) {
		// Fresh from the database: the settings object may hold what this request loaded before another request saved.
		wpconsent()->settings->refresh();
		$data = self::all();
		foreach ( $pairs as $key => $value ) {
			if ( null === $value ) {
				unset( $data[ $key ] );
			} else {
				$data[ $key ] = $value;
			}
		}
		wpconsent()->settings->update_option( self::SETTINGS_KEY, $data );
	}

	/**
	 * The answer set a version may be built from: the one the owner finished, never a draft
	 * left in the wizard. The service refuses a version from an unconfirmed set, so publishing
	 * from the latest save would stop every update on a site that once left a draft.
	 *
	 * @return string|null
	 */
	public static function confirmed_answer_set_id() {
		$confirmed = self::get( 'confirmed_answer_set_id' );
		if ( $confirmed ) {
			return $confirmed;
		}
		// Recorded before the confirmed id was kept apart: the latest save, when it was confirmed.
		return self::get( 'answers_confirmed' ) ? self::get( 'answer_set_id' ) : null;
	}

	/**
	 * Which documents the answers call for, from the last evaluation the service sent (a save or a publish):
	 * document type => bool. Empty before the first save.
	 *
	 * @return array
	 */
	public static function documents_needed() {
		$evaluation = self::get( 'evaluation' );

		return is_array( $evaluation ) && isset( $evaluation['documents'] ) && is_array( $evaluation['documents'] ) ? $evaluation['documents'] : array();
	}

	/**
	 * Documents whose rule waits for an answer in the last evaluation: not called for yet, and not ruled out.
	 * A published one is never offered for unpublishing while its rule is undecided.
	 *
	 * @return string[] Document types.
	 */
	public static function documents_undecided() {
		$evaluation = self::get( 'evaluation' );

		return is_array( $evaluation ) && isset( $evaluation['documents_undecided'] ) && is_array( $evaluation['documents_undecided'] ) ? array_values( array_filter( $evaluation['documents_undecided'], 'is_string' ) ) : array();
	}

	/**
	 * Record that the gate text was accepted.
	 *
	 * @param string $version Gate text version from the questionnaire.
	 * @param int    $user_id Who accepted.
	 */
	public static function accept_gate( $version, $user_id ) {
		self::set(
			'gate',
			array(
				'accepted_at'      => time(),
				'accepted_version' => (string) $version,
				'user_id'          => (int) $user_id,
				'site_url'         => home_url(),
			)
		);
	}

	/**
	 * Whether the gate was accepted for this questionnaire version.
	 *
	 * @param string $version Gate text version.
	 *
	 * @return bool
	 */
	public static function gate_accepted( $version ) {
		$gate = self::get( 'gate' );

		return is_array( $gate ) && ! empty( $gate['accepted_at'] ) && (string) $version === (string) $gate['accepted_version'];
	}

	/**
	 * Per-document bookkeeping (page id, version id, hash, status, menu item).
	 *
	 * @param string $type Document type.
	 *
	 * @return array
	 */
	public static function document_meta( $type ) {
		$documents = self::get_array( 'documents' );

		return isset( $documents[ $type ] ) && is_array( $documents[ $type ] ) ? $documents[ $type ] : array();
	}

	/**
	 * Whether any document is published on this site.
	 *
	 * @return bool
	 */
	public static function has_published() {
		foreach ( self::get_array( 'documents' ) as $meta ) {
			if ( is_array( $meta ) && ! empty( $meta['published_at'] ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Merge into a document's bookkeeping.
	 *
	 * @param string $type Document type.
	 * @param array  $meta Values to merge; null removes a key.
	 */
	public static function set_document_meta( $type, $meta ) {
		$documents          = self::get_array( 'documents' );
		$current            = isset( $documents[ $type ] ) && is_array( $documents[ $type ] ) ? $documents[ $type ] : array();
		foreach ( $meta as $key => $value ) {
			if ( null === $value ) {
				unset( $current[ $key ] );
			} else {
				$current[ $key ] = $value;
			}
		}
		$documents[ $type ] = $current;
		self::set( 'documents', $documents );
	}

	/**
	 * Store a rendered document for a locale: file cache for speed, a
	 * non-autoloaded option so a purged cache directory cannot blank a page.
	 *
	 * @param string $type    Document type.
	 * @param string $locale  Locale, or '' for the source language.
	 * @param array  $payload html, section_map, versions, provenance (source|machine), stale.
	 *
	 * @return bool
	 */
	public static function save_document( $type, $locale, $payload ) {
		$type   = sanitize_key( $type );
		$locale = self::locale_key( $locale );
		foreach ( self::$payload_keys as $key ) {
			if ( ! array_key_exists( $key, $payload ) ) {
				return false;
			}
		}
		$payload['saved_at'] = time();
		$name                = self::DOC_OPTION . $type . '_' . $locale;
		if ( false === get_option( $name, false ) ) {
			add_option( $name, $payload, '', 'no' );
		} else {
			update_option( $name, $payload, 'no' );
		}
		wpconsent()->file_cache->set( self::DOC_CACHE . $type . '-' . $locale, $payload );
		self::touch_page( $type );

		$locales = self::get_array( 'document_locales' );
		if ( ! isset( $locales[ $type ] ) || ! is_array( $locales[ $type ] ) ) {
			$locales[ $type ] = array();
		}
		if ( ! in_array( $locale, $locales[ $type ], true ) ) {
			$locales[ $type ][] = $locale;
		}
		self::set( 'document_locales', $locales );

		return true;
	}

	/**
	 * A stored document. Cache first, option second (re-priming the cache),
	 * and the source language when the locale has no copy.
	 *
	 * @param string $type   Document type.
	 * @param string $locale Locale, or '' for the source language.
	 *
	 * @return array|null
	 */
	public static function get_document( $type, $locale = '' ) {
		$type   = sanitize_key( $type );
		$locale = self::locale_key( $locale );
		$found  = self::read_document( $type, $locale );
		if ( null === $found && self::SOURCE_LOCALE !== $locale ) {
			$found = self::read_document( $type, self::SOURCE_LOCALE );
		}

		return $found;
	}

	/**
	 * Forget a stored document for a locale.
	 *
	 * @param string $type   Document type.
	 * @param string $locale Locale, or '' for the source language.
	 */
	public static function delete_document( $type, $locale = '' ) {
		$type   = sanitize_key( $type );
		$locale = self::locale_key( $locale );
		delete_option( self::DOC_OPTION . $type . '_' . $locale );
		wpconsent()->file_cache->delete( self::DOC_CACHE . $type . '-' . $locale );
		$locales = self::get_array( 'document_locales' );
		if ( isset( $locales[ $type ] ) ) {
			$locales[ $type ] = array_values( array_diff( $locales[ $type ], array( $locale ) ) );
			self::set( 'document_locales', $locales );
		}
	}

	/**
	 * Locales with a stored copy of a document.
	 *
	 * @param string $type Document type.
	 *
	 * @return array
	 */
	public static function document_locales( $type ) {
		$locales = self::get_array( 'document_locales' );

		return isset( $locales[ $type ] ) && is_array( $locales[ $type ] ) ? $locales[ $type ] : array();
	}

	/**
	 * Mark every translated copy of a document stale (after the source is republished).
	 *
	 * @param string $type Document type.
	 */
	public static function mark_translations_stale( $type ) {
		foreach ( self::document_locales( $type ) as $locale ) {
			if ( self::SOURCE_LOCALE === $locale ) {
				continue;
			}
			$payload = self::read_document( $type, $locale );
			if ( $payload ) {
				$payload['stale'] = true;
				self::save_document( $type, $locale, $payload );
			}
		}
	}

	/**
	 * Updates waiting for the owner, by document type.
	 *
	 * @return array
	 */
	public static function pending() {
		$pending = get_option( self::PENDING_OPTION, array() );

		return is_array( $pending ) ? $pending : array();
	}

	/**
	 * Record a pending update for a document.
	 *
	 * @param string $type Document type.
	 * @param array  $data version_id, impact, since, diff_summary, items.
	 */
	public static function set_pending( $type, $data ) {
		$pending          = self::pending();
		$existing         = isset( $pending[ $type ] ) && is_array( $pending[ $type ] ) ? $pending[ $type ] : array();
		$data['since']    = isset( $existing['since'] ) ? $existing['since'] : ( isset( $data['since'] ) ? $data['since'] : time() );
		$pending[ $type ] = $data;
		update_option( self::PENDING_OPTION, $pending, false );
	}

	/**
	 * Clear a pending update.
	 *
	 * @param string $type Document type.
	 */
	public static function clear_pending( $type ) {
		$pending = self::pending();
		unset( $pending[ $type ] );
		update_option( self::PENDING_OPTION, $pending, false );

		/**
		 * A document no longer has an update waiting.
		 *
		 * @param string $type Document type.
		 */
		do_action( 'wpconsent_legal_pending_cleared', $type );
	}

	/**
	 * Owner overrides for a document, active and inactive (never deleted).
	 *
	 * @param string $type        Document type.
	 * @param bool   $active_only Only the active ones.
	 *
	 * @return array section_id => { html, override_id, base_version_id, active, updated_at }.
	 */
	public static function overrides( $type, $active_only = true ) {
		$all  = get_option( self::OVERRIDES_OPTION, array() );
		$list = isset( $all[ $type ] ) && is_array( $all[ $type ] ) ? $all[ $type ] : array();
		if ( $active_only ) {
			$list = array_filter(
				$list,
				function ( $o ) {
					return ! empty( $o['active'] );
				}
			);
		}

		return $list;
	}

	/**
	 * Store an override.
	 *
	 * @param string $type       Document type.
	 * @param string $section_id Section id.
	 * @param array  $data       html, override_id, base_version_id.
	 */
	public static function set_override( $type, $section_id, $data ) {
		$all                            = get_option( self::OVERRIDES_OPTION, array() );
		$all                            = is_array( $all ) ? $all : array();
		$data['active']                 = true;
		$data['updated_at']             = time();
		$all[ $type ][ $section_id ]    = $data;
		update_option( self::OVERRIDES_OPTION, $all, false );
	}

	/**
	 * Deactivate an override; the text is kept.
	 *
	 * @param string $type       Document type.
	 * @param string $section_id Section id.
	 */
	public static function remove_override( $type, $section_id ) {
		$all = get_option( self::OVERRIDES_OPTION, array() );
		if ( isset( $all[ $type ][ $section_id ] ) ) {
			$all[ $type ][ $section_id ]['active']     = false;
			$all[ $type ][ $section_id ]['updated_at'] = time();
			update_option( self::OVERRIDES_OPTION, $all, false );
		}
	}

	/**
	 * Append to the activity log (newest first, capped).
	 *
	 * @param string $event Event name.
	 * @param array  $data  Context.
	 */
	public static function log( $event, $data = array() ) {
		$log = get_option( self::LOG_OPTION, array() );
		$log = is_array( $log ) ? $log : array();
		array_unshift(
			$log,
			array(
				'event' => (string) $event,
				'data'  => $data,
				'time'  => time(),
				'user'  => get_current_user_id(),
			)
		);
		update_option( self::LOG_OPTION, array_slice( $log, 0, self::LOG_MAX ), false );
	}

	/**
	 * The activity log, newest first.
	 *
	 * @return array
	 */
	public static function get_log() {
		$log = get_option( self::LOG_OPTION, array() );

		return is_array( $log ) ? $log : array();
	}

	/**
	 * Everything this class stores, for uninstall and erasure.
	 *
	 * @return array Option names.
	 */
	public static function option_names() {
		global $wpdb;
		$names = array( self::PENDING_OPTION, self::OVERRIDES_OPTION, self::LOG_OPTION, 'wpconsent_legal_translation_status' );
		$like  = $wpdb->esc_like( self::DOC_OPTION ) . '%';
		$docs  = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $like ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		foreach ( (array) $docs as $name ) {
			$names[] = $name;
		}

		return $names;
	}

	/**
	 * Normalise a locale into a storage key.
	 *
	 * @param string $locale Locale.
	 *
	 * @return string
	 */
	public static function locale_key( $locale ) {
		$locale = strtolower( trim( (string) $locale ) );
		if ( '' === $locale || self::SOURCE_LOCALE === $locale ) {
			return self::SOURCE_LOCALE;
		}

		return preg_replace( '/[^a-z0-9_]/', '_', $locale );
	}

	/**
	 * The page holding a document has new text, although its content (the block or shortcode) did not
	 * change: re-save it so its modified date moves and the save_post hooks fire, which is what page
	 * caches, sitemaps and CDN plugins listen to. Then announce it for anything else.
	 *
	 * @param string $type Document type.
	 */
	public static function touch_page( $type ) {
		$meta    = self::document_meta( $type );
		$page_id = ! empty( $meta['page_id'] ) ? (int) $meta['page_id'] : 0;
		$page    = $page_id ? get_post( $page_id ) : null;
		if ( $page && 'trash' !== $page->post_status ) {
			clean_post_cache( $page_id );
			// The content is re-saved as it is. From WP-Cron (the daily check, a translation, a recovery) no user is logged
			// in, and without unfiltered_html WordPress would strip an adopted page's embeds, scripts and forms on save:
			// kses stays out of this save, then comes back as the current user needs it.
			kses_remove_filters();
			wp_update_post( array( 'ID' => $page_id ) );
			kses_init();
		}

		/**
		 * A document's stored copy changed (publish, update, restore, recovery, translation).
		 *
		 * @param string $type    Document type.
		 * @param int    $page_id The page that shows it, or 0.
		 */
		do_action( 'wpconsent_legal_document_saved', $type, $page_id );
	}


	/**
	 * Read one locale's copy without fallback.
	 *
	 * @param string $type   Document type.
	 * @param string $locale Locale key.
	 *
	 * @return array|null
	 */
	private static function read_document( $type, $locale ) {
		$cache_key = self::DOC_CACHE . $type . '-' . $locale;
		$cached    = wpconsent()->file_cache->get( $cache_key, 0 );
		if ( is_array( $cached ) && isset( $cached['html'] ) ) {
			return $cached;
		}
		$option = get_option( self::DOC_OPTION . $type . '_' . $locale, false );
		if ( is_array( $option ) && isset( $option['html'] ) ) {
			wpconsent()->file_cache->set( $cache_key, $option );

			return $option;
		}

		return null;
	}
	/**
	 * The live version of a document as a standalone HTML page, built from the stored copy the same way
	 * the service builds its HTML export. Export works without the service, so it works without a licence.
	 *
	 * @param string $type       Document type.
	 * @param string $version_id Version wanted, or '' for the live one. Only the live version is stored here.
	 *
	 * @return array|null body, content_type, filename; null when the copy or the version is not here.
	 */
	public static function export_html( $type, $version_id = '' ) {
		$document = self::get_document( $type );
		$meta     = self::document_meta( $type );
		$live     = isset( $meta['version_id'] ) ? $meta['version_id'] : ( isset( $document['versions']['version_id'] ) ? $document['versions']['version_id'] : '' );
		if ( ! $document || empty( $document['html'] ) || ( $version_id && $version_id !== $live ) ) {
			return null;
		}
		$types = function_exists( 'wpconsent_legal_document_types' ) ? wpconsent_legal_document_types() : array();
		$title = isset( $types[ $type ] ) ? $types[ $type ] : ucwords( str_replace( '_', ' ', $type ) );
		$lang  = strtolower( substr( get_locale(), 0, 2 ) );
		$head  = '<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>' . esc_html( $title ) . '</title>'
			. '<style>.ld-policy{line-height:1.6;max-width:48em;margin:0 auto;padding:0 1em}.ld-policy table{border-collapse:collapse;width:100%;margin:1em 0}.ld-policy th,.ld-policy td{border:1px solid;padding:.4em .6em;text-align:left;vertical-align:top}.ld-policy section{margin-top:2em}.ld-footer{margin-top:3em;padding-top:1em;border-top:1px solid;font-size:.9em}</style>';
		// The stored body leaves the date and the widgets to display time. The export is read away from the site,
		// so its widgets are words and links, as in the Markdown and PDF: no button or form that does nothing in a file.
		$html  = function_exists( 'wpconsent_legal_document_fill' ) ? wpconsent_legal_document_fill( $type, $document['html'], null, true ) : $document['html'];
		$body  = '<!doctype html><html lang="' . esc_attr( $lang ? $lang : 'en' ) . '"><head>' . $head . '</head><body><main class="ld-policy">' . $html . '</main></body></html>';

		return array(
			'body'         => $body,
			'content_type' => 'text/html; charset=utf-8',
			'filename'     => sanitize_title( $title ) . '.html',
		);
	}
}
