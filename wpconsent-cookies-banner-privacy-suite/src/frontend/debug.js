/**
 * Consent Mode debug mode.
 *
 * Adds WPConsent.debug(), which prints a plain-language report to the browser console about
 * whether the Google Consent Mode default (and the IAB TCF stub on Pro) ran before any Google
 * tag fired on the page. The same report runs automatically when the page URL contains
 * ?wpconsent_consent_debug=1 (wpconsent_debug is taken: it disables script blocking for the scanner).
 * Output is console only; there is no UI.
 *
 * Inputs:
 * - window.WPConsentDebugMarkers, written by the inline head scripts in includes/frontend-scripts.php
 *   (Consent Mode default) and includes/pro/frontend-scripts.php (TCF stub).
 * - window.dataLayer, where gtag() and GTM push their commands.
 * - wpconsent.debug, the localized strings, documentation links and settings summary from PHP.
 *
 * All user-facing text comes from wpconsent.debug.strings; nothing printed here is hardcoded.
 */

const WPCONSENT_DEVELOPER_ID = 'developer_id.dMmRkYz';
const GOOGLE_TAG_ID_PATTERN = /^(G|GT|AW|DC|UA|MC)-[A-Z0-9-]+$/i;
const MAX_TABLE_ROWS = 50;

/**
 * Get the localized debug settings, or an empty shell when the frontend data is unavailable.
 *
 * @return {Object}
 */
function debugData() {
	const data = ( window.wpconsent && window.wpconsent.debug ) || {};

	return {
		consent_mode_enabled: !! data.consent_mode_enabled,
		default_owner: data.default_owner || 'wpconsent',
		docs: data.docs || {},
		strings: data.strings || {},
	};
}

/**
 * Look up a localized string by key. Falls back to the key so a missing translation is visible, never silent.
 *
 * @param {string} key
 * @param {...*}   args Values for %s, %1$s, %2$s placeholders.
 * @return {string}
 */
function str( key, ...args ) {
	const template = debugData().strings[ key ] || key;
	let position = 0;

	return template.replace( /%(\d+\$)?s/g, ( match, index ) => {
		const value = index ? args[ parseInt( index, 10 ) - 1 ] : args[ position++ ];

		return typeof value === 'undefined' ? match : String( value );
	} );
}

/**
 * Normalize a dataLayer entry to a plain array when it is array-like (gtag pushes `arguments` objects).
 *
 * @param {*} entry
 * @return {Array|null} The arguments as an array, or null for plain objects and primitives.
 */
function toArgs( entry ) {
	if ( Array.isArray( entry ) ) {
		return entry;
	}

	if ( entry && typeof entry === 'object' && typeof entry.length === 'number' ) {
		return Array.prototype.slice.call( entry );
	}

	return null;
}

/**
 * Whether the entry is a gtag('consent', 'default', ...) command.
 *
 * @param {*} entry
 * @return {boolean}
 */
function isConsentDefault( entry ) {
	const args = toArgs( entry );

	return !! args && 'consent' === args[ 0 ] && 'default' === args[ 1 ];
}

/**
 * Whether the entry is a gtag('consent', 'update', ...) command.
 *
 * @param {*} entry
 * @return {boolean}
 */
function isConsentUpdate( entry ) {
	const args = toArgs( entry );

	return !! args && 'consent' === args[ 0 ] && 'update' === args[ 1 ];
}

/**
 * Whether the entry sets the WPConsent developer ID.
 *
 * @param {*} entry
 * @return {boolean}
 */
function isWPConsentDeveloperId( entry ) {
	const args = toArgs( entry );

	if ( args ) {
		return 'set' === args[ 0 ] && WPCONSENT_DEVELOPER_ID === args[ 1 ];
	}

	return !! entry && typeof entry === 'object' && Object.prototype.hasOwnProperty.call( entry, WPCONSENT_DEVELOPER_ID );
}

/**
 * Whether the entry shows a Google tag (gtag.js, GTM or Site Kit) doing something on the page.
 *
 * Recognized: gtag('js', Date), gtag('config', 'G-XXXX'), gtag('event', ...), another developer ID
 * such as Site Kit's developer_id.dZTNiMT, and the GTM container start event {event: 'gtm.js'}.
 *
 * @param {*} entry
 * @return {boolean}
 */
function isGoogleTagEntry( entry ) {
	const args = toArgs( entry );

	if ( args ) {
		if ( 'js' === args[ 0 ] && args[ 1 ] instanceof Date ) {
			return true;
		}

		if ( 'config' === args[ 0 ] && typeof args[ 1 ] === 'string' && GOOGLE_TAG_ID_PATTERN.test( args[ 1 ] ) ) {
			return true;
		}

		if ( 'event' === args[ 0 ] ) {
			return true;
		}

		if ( 'set' === args[ 0 ] && typeof args[ 1 ] === 'string' && 0 === args[ 1 ].indexOf( 'developer_id.' ) && WPCONSENT_DEVELOPER_ID !== args[ 1 ] ) {
			return true;
		}

		return false;
	}

	if ( entry && typeof entry === 'object' ) {
		if ( 'gtm.js' === entry.event || Object.prototype.hasOwnProperty.call( entry, 'gtm.start' ) ) {
			return true;
		}

		return Object.keys( entry ).some( ( key ) => 0 === key.indexOf( 'developer_id.' ) && WPCONSENT_DEVELOPER_ID !== key );
	}

	return false;
}

/**
 * Short, readable description of a dataLayer entry for the console table.
 *
 * @param {*} entry
 * @return {string}
 */
function describeEntry( entry ) {
	const args = toArgs( entry );

	if ( args ) {
		return args.map( ( arg ) => {
			if ( arg instanceof Date ) {
				return isNaN( arg.getTime() ) ? String( arg ) : arg.toISOString();
			}

			if ( arg && typeof arg === 'object' ) {
				try {
					return JSON.stringify( arg );
				} catch ( e ) {
					return '[object]';
				}
			}

			return String( arg );
		} ).join( ' ' );
	}

	if ( entry && typeof entry === 'object' ) {
		try {
			return JSON.stringify( entry );
		} catch ( e ) {
			return '[object]';
		}
	}

	return String( entry );
}

/**
 * Classify an entry for the console table type column.
 *
 * @param {*}      entry
 * @param {number} index
 * @param {number} wpconsentDefaultIndex Index of the default pushed by WPConsent, or -1.
 * @return {string} A string key from wpconsent.debug.strings.
 */
function entryTypeKey( entry, index, wpconsentDefaultIndex ) {
	if ( isConsentDefault( entry ) ) {
		return index === wpconsentDefaultIndex ? 'type_wpconsent_default' : 'type_other_default';
	}

	if ( isConsentUpdate( entry ) ) {
		return 'type_consent_update';
	}

	if ( isWPConsentDeveloperId( entry ) ) {
		return 'type_wpconsent_developer';
	}

	if ( isGoogleTagEntry( entry ) ) {
		return 'type_google_tag';
	}

	return 'type_other';
}

/**
 * Current list of Google tag script tags in the document.
 *
 * @return {string[]}
 */
function googleScriptsNow() {
	const scripts = document.querySelectorAll( 'script[src*="googletagmanager.com/"]' );

	return Array.prototype.map.call( scripts, ( script ) => script.src );
}

/**
 * Ask __tcfapi for its status without waiting; both the stub and the real CMP API answer ping synchronously.
 *
 * @return {string|null} The cmpStatus value, or null when __tcfapi is missing or did not answer.
 */
function tcfStatus() {
	if ( typeof window.__tcfapi !== 'function' ) {
		return null;
	}

	let status = null;

	try {
		window.__tcfapi( 'ping', 2, ( ping ) => {
			status = ping && ping.cmpStatus ? ping.cmpStatus : 'unknown';
		} );
	} catch ( e ) {
		status = 'error';
	}

	return status;
}

/**
 * Collect everything the report needs into one plain object.
 *
 * @return {Object}
 */
function buildReport() {
	const data = debugData();
	const markers = window.WPConsentDebugMarkers || {};
	const dataLayer = Array.isArray( window.dataLayer ) ? window.dataLayer : null;
	const entries = dataLayer || [];

	const headScriptRan = typeof markers.dataLayerLengthBefore === 'number';

	// WPConsent's own default: trust the recorded index when it still points at a consent default.
	let defaultIndex = -1;
	let defaultIsWPConsent = false;

	// Prefer matching the exact consent object the head script pushed; fall back to the recorded index.
	const byIdentity = markers.consentDefaultObject ? entries.findIndex( ( entry ) => {
		const args = toArgs( entry );

		return !! args && isConsentDefault( entry ) && args[ 2 ] === markers.consentDefaultObject;
	} ) : -1;

	if ( -1 !== byIdentity ) {
		defaultIndex = byIdentity;
		defaultIsWPConsent = true;
	} else if ( headScriptRan && isConsentDefault( entries[ markers.consentDefaultIndex ] ) ) {
		defaultIndex = markers.consentDefaultIndex;
		defaultIsWPConsent = true;
	} else {
		defaultIndex = entries.findIndex( isConsentDefault );
	}

	let developerIdIndex = -1;

	if ( headScriptRan && isWPConsentDeveloperId( entries[ markers.developerIdIndex ] ) ) {
		developerIdIndex = markers.developerIdIndex;
	} else {
		developerIdIndex = entries.findIndex( isWPConsentDeveloperId );
	}

	const firstTagIndex = entries.findIndex( isGoogleTagEntry );
	const firstTag = -1 === firstTagIndex ? null : {
		index: firstTagIndex,
		description: describeEntry( entries[ firstTagIndex ] ),
	};

	const scriptsBefore = Array.isArray( markers.googleScriptsBefore ) ? markers.googleScriptsBefore : [];
	const scriptsNow = googleScriptsNow();
	const tagsPresent = null !== firstTag || scriptsNow.length > 0;

	// Verdict.
	let verdict;

	if ( ! data.consent_mode_enabled ) {
		verdict = 'disabled';
	} else if ( ! tagsPresent ) {
		verdict = 'no_tags';
	} else if ( -1 === defaultIndex ) {
		verdict = 'missing';
	} else if ( null !== firstTag && firstTag.index < defaultIndex ) {
		// Only the dataLayer order decides: a script tag placed above ours may still execute later (async).
		verdict = 'late';
	} else {
		verdict = 'ok';
	}

	// TCF (Pro). The stub marker is only written when IAB TCF is enabled.
	const tcfEnabled = !! ( window.wpconsent && window.wpconsent.iab_tcf_enabled );
	let stubBeforeTags = null;

	if ( tcfEnabled && markers.tcfStub && typeof markers.tcfStub.dataLayerLength === 'number' && null !== firstTag ) {
		stubBeforeTags = markers.tcfStub.dataLayerLength <= firstTag.index;
	}

	return {
		consentModeEnabled: data.consent_mode_enabled,
		defaultOwner: data.default_owner,
		dataLayerExists: null !== dataLayer,
		headScriptRan,
		dataLayerLengthBefore: headScriptRan ? markers.dataLayerLengthBefore : null,
		defaultIndex,
		defaultIsWPConsent,
		developerIdIndex,
		firstTag,
		googleScriptsBefore: scriptsBefore,
		googleScriptsNow: scriptsNow,
		verdict,
		tcf: {
			enabled: tcfEnabled,
			apiStatus: tcfEnabled ? tcfStatus() : null,
			stubRecorded: !! ( markers.tcfStub && typeof markers.tcfStub.dataLayerLength === 'number' ),
			stubBeforeTags,
		},
		entries: entries.map( ( entry, index ) => ( {
			index,
			type: str( entryTypeKey( entry, index, defaultIsWPConsent ? defaultIndex : -1 ) ),
			entry: describeEntry( entry ),
		} ) ),
	};
}

/**
 * Print the report to the console in a form a site owner can read.
 *
 * @param {Object} report The object returned by buildReport().
 */
function printReport( report ) {
	const data = debugData();
	const ok = ( message ) => console.log( '%c%s', 'color: #1a7f37', '\u2713 ' + message );
	const warn = ( message ) => console.warn( message );
	const info = ( message ) => console.log( message );

	console.group( str( 'title' ) );

	// 1. Settings and the default consent command.
	if ( report.consentModeEnabled ) {
		ok( str( 'consent_mode_enabled' ) );
	} else {
		warn( str( 'consent_mode_disabled' ) );
	}

	if ( report.consentModeEnabled ) {
		if ( 'sitekit' === report.defaultOwner ) {
			info( str( 'owner_sitekit' ) );
		} else if ( 'none' === report.defaultOwner ) {
			warn( str( 'owner_none' ) );
		} else {
			info( str( 'owner_wpconsent' ) );
		}
	}

	if ( ! report.dataLayerExists ) {
		info( str( 'datalayer_missing' ) );
	}

	if ( report.consentModeEnabled && 'wpconsent' === report.defaultOwner && ! report.headScriptRan ) {
		warn( str( 'head_script_not_run' ) );
	}

	if ( -1 !== report.defaultIndex ) {
		ok( str( report.defaultIsWPConsent ? 'default_found' : 'default_found_other', report.defaultIndex ) );
	} else if ( report.consentModeEnabled ) {
		warn( str( 'default_missing' ) );
	}

	if ( -1 !== report.developerIdIndex ) {
		info( str( 'developer_id_found', report.developerIdIndex ) );
	} else if ( report.consentModeEnabled ) {
		info( str( 'developer_id_missing' ) );
	}

	// 2. First Google tag activity.
	if ( report.firstTag ) {
		info( str( 'first_tag_found', report.firstTag.index, report.firstTag.description ) );
	} else {
		info( str( 'first_tag_none' ) );
	}

	if ( report.googleScriptsBefore.length > 0 ) {
		warn( str( 'scripts_before_found' ) );
		report.googleScriptsBefore.forEach( ( src ) => warn( '  ' + src ) );
	} else if ( report.headScriptRan ) {
		ok( str( 'scripts_before_none' ) );
	}

	if ( report.googleScriptsNow.length > 0 ) {
		info( str( 'scripts_now' ) );
		report.googleScriptsNow.forEach( ( src ) => info( '  ' + src ) );
	} else {
		info( str( 'scripts_now_none' ) );
	}

	// 3. Verdict and guidance.
	switch ( report.verdict ) {
		case 'ok':
			ok( str( 'verdict_ok' ) );
			break;
		case 'late':
			console.error( str( 'verdict_late' ) );
			warn( str( 'late_explanation' ) );
			warn( str( 'gtg_check', data.docs.gtg || '' ) );
			info( str( 'consent_mode_docs', data.docs.consent_mode || '' ) );
			break;
		case 'missing':
			console.error( str( 'verdict_missing' ) );
			warn( str( 'missing_explanation' ) );
			warn( str( 'gtg_check', data.docs.gtg || '' ) );
			info( str( 'consent_mode_docs', data.docs.consent_mode || '' ) );
			break;
		case 'no_tags':
			info( str( 'verdict_no_tags' ) );
			break;
		default:
			info( str( 'verdict_disabled' ) );
			info( str( 'consent_mode_docs', data.docs.consent_mode || '' ) );
	}

	// 4. TCF.
	console.group( str( 'tcf_title' ) );

	if ( ! report.tcf.enabled ) {
		info( str( 'tcf_disabled' ) );
	} else {
		if ( null === report.tcf.apiStatus ) {
			warn( str( 'tcf_api_missing' ) );
		} else {
			ok( str( 'tcf_api_found', report.tcf.apiStatus ) );
		}

		if ( ! report.tcf.stubRecorded ) {
			warn( str( 'tcf_stub_not_recorded' ) );
		} else if ( null === report.tcf.stubBeforeTags ) {
			info( str( 'tcf_stub_no_tags' ) );
		} else if ( report.tcf.stubBeforeTags ) {
			ok( str( 'tcf_stub_before' ) );
		} else {
			// A late TCF stub is a late consent signal too, so it gets the same GTG check as a late default.
			console.error( str( 'tcf_stub_after' ) );
			warn( str( 'gtg_check', data.docs.gtg || '' ) );
		}
	}

	console.groupEnd();

	// 5. The dataLayer itself.
	if ( report.entries.length > 0 ) {
		const rows = report.entries.slice( 0, MAX_TABLE_ROWS ).map( ( row ) => {
			const out = {};
			out[ str( 'column_position' ) ] = row.index;
			out[ str( 'column_type' ) ] = row.type;
			out[ str( 'column_entry' ) ] = row.entry;

			return out;
		} );

		console.groupCollapsed( str( 'datalayer_table' ) );
		console.table( rows );
		console.groupEnd();
	}

	info( str( 'how_to_run' ) );
	console.groupEnd();
}

/**
 * Build and print the Consent Mode report. Returns the report object for programmatic use.
 *
 * @return {Object}
 */
function debug() {
	const report = buildReport();

	printReport( report );

	return report;
}

window.WPConsent = window.WPConsent || {};
window.WPConsent.debug = debug;

// Auto-run once when the page is loaded with ?wpconsent_debug=1, after the banner has initialized so
// the TCF API and any consent update are already in place. The load fallback covers pages where the
// banner markup is suppressed and the initialized event never fires.
( function () {
	let search = '';

	try {
		search = window.location.search || '';
	} catch ( e ) {
		return;
	}

	if ( ! /[?&]wpconsent_consent_debug=1(&|$)/.test( search ) ) {
		return;
	}

	let ranAfterInit = false;

	// Let the TCF module react to the initialized event first, then print once with the final state.
	window.addEventListener( 'wpconsent_banner_initialized', () => {
		ranAfterInit = true;
		setTimeout( debug, 0 );
	} );

	// Banner markup suppressed or init still waiting on a slow settings hook: print what is known so far.
	// If init completes later the listener above prints the report again with the final state.
	window.addEventListener( 'load', () => setTimeout( () => {
		if ( ! ranAfterInit ) {
			debug();
		}
	}, 3000 ) );
} )();
