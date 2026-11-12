<?php
/**
 * Renders stored Legal Documents on the front end: the
 * [wpconsent_legal_document] shortcode and the wpconsent/legal-document block.
 *
 * Shared by Lite and Pro and never checks the licence: a published page keeps
 * rendering its stored copy after a lapse or a downgrade.
 *
 * @package WPConsent
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_shortcode( 'wpconsent_legal_document', 'wpconsent_legal_document_shortcode' );
add_action( 'init', 'wpconsent_legal_document_register_block' );
add_action( 'wp_enqueue_scripts', 'wpconsent_legal_document_maybe_enqueue_in_head' );

/**
 * The document types the service renders.
 *
 * @return array type => label.
 */
function wpconsent_legal_document_types() {
	return array(
		'privacy_policy'     => __( 'Privacy Policy', 'wpconsent-cookies-banner-privacy-suite' ),
		'cookie_policy'      => __( 'Cookie Policy', 'wpconsent-cookies-banner-privacy-suite' ),
		'do_not_sell'        => __( 'Do Not Sell or Share My Personal Information', 'wpconsent-cookies-banner-privacy-suite' ),
		'limit_sensitive_pi' => __( 'Limit the Use of My Sensitive Personal Information', 'wpconsent-cookies-banner-privacy-suite' ),
	);
}

/**
 * Shortcode: [wpconsent_legal_document document="privacy_policy" locale=""].
 *
 * @param array $atts Shortcode attributes.
 *
 * @return string
 */
function wpconsent_legal_document_shortcode( $atts = array() ) {
	$atts = shortcode_atts(
		array(
			'document' => 'privacy_policy',
			'locale'   => '',
		),
		$atts,
		'wpconsent_legal_document'
	);
	$type = sanitize_key( $atts['document'] );
	if ( ! array_key_exists( $type, wpconsent_legal_document_types() ) ) {
		return '';
	}
	$locale = '' !== $atts['locale'] ? sanitize_text_field( $atts['locale'] ) : determine_locale();

	return wpconsent_legal_document_render( $type, $locale );
}

/**
 * Render a stored document, or nothing. A copy that went missing is fetched back by the plugin
 * (daily, when the Legal Documents screen opens, after a reinstall); the page never links to a copy
 * on another host.
 *
 * @param string $type   Document type.
 * @param string $locale Locale to prefer; falls back to the source language.
 *
 * @return string
 */
function wpconsent_legal_document_render( $type, $locale = '' ) {
	wpconsent_cookie_policy_register_styles();
	wp_enqueue_style( 'wpconsent-cookie-policy' );

	$payload = WPConsent_Legal_Documents_Store::get_document( $type, $locale );
	$classes = 'wpconsent-legal-document wpconsent-legal-document--' . sanitize_html_class( str_replace( '_', '-', $type ) );

	if ( ! is_array( $payload ) || empty( $payload['html'] ) ) {
		return '';
	}

	$notice = '';
	if ( ! empty( $payload['stale'] ) ) {
		$notice = __( 'This translation is of an earlier version of the English original.', 'wpconsent-cookies-banner-privacy-suite' );
	}
	$provenance = '';
	if ( isset( $payload['provenance'] ) && 'machine' === $payload['provenance'] ) {
		$provenance = '<p class="wpconsent-legal-provenance">' . esc_html__( 'This translation was produced automatically from the original.', 'wpconsent-cookies-banner-privacy-suite' ) . '</p>';
	}

	$output  = '<div class="' . esc_attr( $classes ) . '">';
	// A translated copy carries the date of the English version it was made from.
	$output .= wpconsent_legal_document_fill( $type, wp_kses( $payload['html'], wpconsent_legal_document_allowed_html() ), ! empty( $payload['source_published_at'] ) ? (int) $payload['source_published_at'] : null );
	if ( $notice ) {
		$output .= '<p class="wpconsent-legal-provenance">' . esc_html( $notice ) . '</p>';
	}
	// The documents have no footer. A machine translation still says what it is.
	$output .= $provenance;
	$output .= '</div>';
	if ( false !== strpos( $output, 'class="ld-visitor-state"' ) ) {
		// Printed in the footer, not inside the content: the content filters would encode the script's ampersands.
		add_action( 'wp_footer', 'wpconsent_legal_document_print_visitor_state_script' );
	}

	return $output;
}

/**
 * Print the script that reveals the visitor-state block that applies, once per page, when a document
 * on it carries such blocks. The engine sends every state hidden (an opt-out already made on this
 * browser, none yet, the confirmation after the form is sent) and leaves the choice to the site: a
 * Global Privacy Control signal the site honours counts as an opt-out, the request form's success
 * message is the confirmation, and otherwise the "not yet" state shows.
 */
function wpconsent_legal_document_print_visitor_state_script() {
	remove_action( 'wp_footer', 'wpconsent_legal_document_print_visitor_state_script' );
	$js = <<<'JS'
(function () {
	var docs = document.querySelectorAll( '.wpconsent-legal-document' );
	function show( root, state ) {
		var blocks = root.querySelectorAll( '.ld-visitor-state' ), has = false, i;
		for ( i = 0; i < blocks.length; i++ ) { if ( blocks[ i ].getAttribute( 'data-state' ) === state ) { has = true; } }
		if ( ! has ) { return false; }
		for ( i = 0; i < blocks.length; i++ ) { blocks[ i ].hidden = blocks[ i ].getAttribute( 'data-state' ) !== state; }
		return true;
	}
	function apply() {
		var gpc = window.wpconsent && wpconsent.respect_gpc && navigator.globalPrivacyControl === true;
		for ( var i = 0; i < docs.length; i++ ) {
			var root = docs[ i ];
			if ( ! root.querySelector( '.ld-visitor-state' ) ) { continue; }
			if ( root.querySelector( '.wpconsent-dnt-form-success' ) ) { show( root, 'confirmation' ) || show( root, 'submitted' ); continue; }
			if ( gpc && show( root, 'already' ) ) { continue; }
			show( root, 'not_yet' ) || show( root, 'new' );
		}
	}
	apply();
	if ( window.MutationObserver ) {
		new MutationObserver( apply ).observe( document.body, { subtree: true, childList: true, attributes: true, attributeFilter: [ 'class' ] } );
	}
})();
JS;

	if ( function_exists( 'wp_print_inline_script_tag' ) ) {
		wp_print_inline_script_tag( $js, array( 'id' => 'wpconsent-legal-visitor-state' ) );
	} else {
		// WordPress 5.6: the helper arrived in 5.7.
		echo '<script id="wpconsent-legal-visitor-state">' . $js . '</script>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- A fixed script of this file's own.
	}
}

/**
 * The markup the engine emits plus owner overrides, nothing more.
 *
 * @return array
 */
function wpconsent_legal_document_allowed_html() {
	$allowed = wp_kses_allowed_html( 'post' );
	foreach ( array( 'section', 'footer', 'h2', 'h3', 'h4', 'table', 'thead', 'tbody', 'tr', 'th', 'td', 'p', 'ul', 'ol', 'li', 'a', 'strong', 'em', 'br' ) as $tag ) {
		if ( ! isset( $allowed[ $tag ] ) ) {
			$allowed[ $tag ] = array();
		}
		$allowed[ $tag ]['id']          = true;
		$allowed[ $tag ]['class']       = true;
		$allowed[ $tag ]['data-source'] = true;
	}
	$allowed['a']['href'] = true;
	$allowed['a']['rel']  = true;
	// The engine's placeholders, filled at display time: widgets and the publication date.
	// A widget placeholder is a span carrying data-ld-widget (data-widget in copies stored before the attribute);
	// a table says which one it is with data-ld-table.
	$allowed['span']['data-ld-widget'] = true;
	$allowed['span']['data-widget']    = true;
	$allowed['table']['data-ld-table'] = true;
	$allowed['time']                = array( 'class' => true, 'datetime' => true );
	// Visitor-state blocks arrive hidden; kses before WordPress 6.4 would strip the attribute and show every state at once.
	$allowed['div']['hidden']     = true;
	$allowed['div']['data-state'] = true;

	return apply_filters( 'wpconsent_legal_document_allowed_html', $allowed );
}

/**
 * Fill what the engine leaves to the site at display time: the publication date and the widgets
 * (library rules/widgets.json). A placeholder the site cannot fill takes its sentence with it, as a
 * missing value does in the engine, so no empty link or blank is published.
 *
 * @param string   $type         Document type.
 * @param string   $html         Sanitised document body.
 * @param int|null $published_at The date to show, or null for the document's own publication date.
 * @param bool     $as_words     Fill the widgets as words and links (wpconsent_legal_document_widget_texts())
 *                               instead of the controls the page has: for a file read away from the site, where
 *                               a preferences button or a request form does nothing.
 *
 * @return string
 */
function wpconsent_legal_document_fill( $type, $html, $published_at = null, $as_words = false ) {
	if ( null === $published_at ) {
		$meta         = WPConsent_Legal_Documents_Store::document_meta( $type );
		$published_at = ! empty( $meta['published_at'] ) ? (int) $meta['published_at'] : 0;
	}
	if ( $published_at ) {
		// The site's own calendar day, not UTC's: a publish at 09:00 in Auckland is that day there.
		$date = wp_date( 'Y-m-d', (int) $published_at );
		$html = str_replace( '<time class="ld-published-on"></time>', '<time class="ld-published-on" datetime="' . esc_attr( $date ) . '">' . esc_html( wp_date( 'j F Y', (int) $published_at ) ) . '</time>', $html );
	}
	$html = wpconsent_legal_document_drop_sentences( $html, '#<time class="ld-published-on"></time>#' );
	if ( 'cookie_policy' === $type ) {
		$html = wpconsent_legal_document_live_cookie_tables( $html );
	}

	if ( $as_words ) {
		$widgets = array();
		foreach ( wpconsent_legal_document_widget_texts() as $name => $entry ) {
			$widgets[ $name ] = empty( $entry['url'] ) ? esc_html( $entry['text'] ) : '<a href="' . esc_url( $entry['url'] ) . '">' . esc_html( $entry['text'] ) . '</a>';
		}
	} else {
		$widgets = wpconsent_legal_document_widgets( $html );
	}
	// A placeholder is found by its attribute, whatever else the span carries. One this site cannot fill shows
	// the fallback text the service put inside it; one with no fallback takes its sentence with it.
	$html = preg_replace_callback(
		WPCONSENT_LEGAL_WIDGET_PLACEHOLDER,
		function ( $m ) use ( $widgets ) {
			if ( isset( $widgets[ $m[1] ] ) && '' !== $widgets[ $m[1] ] ) {
				return $widgets[ $m[1] ];
			}
			$fallback = trim( $m[2] );

			return '' !== $fallback ? esc_html( html_entity_decode( $fallback, ENT_QUOTES, 'UTF-8' ) ) : '<span data-ld-widget="' . $m[1] . '"></span>';
		},
		$html
	);

	return wpconsent_legal_document_drop_sentences( $html, '#<span\b[^>]*\bdata-(?:ld-)?widget="[a-z0-9_-]+"[^>]*>\s*</span>#' );
}

/**
 * A widget placeholder in a document body: a span that carries data-ld-widget (data-widget in copies stored
 * before the attribute was introduced), with its name and the fallback text inside it.
 */
if ( ! defined( 'WPCONSENT_LEGAL_WIDGET_PLACEHOLDER' ) ) {
	define( 'WPCONSENT_LEGAL_WIDGET_PLACEHOLDER', '#<span\b[^>]*\bdata-(?:ld-)?widget="([a-z0-9_-]+)"[^>]*>([^<]*)</span>#' );
}

/**
 * Swap the cookie tables of a stored Cookie Policy for the site's cookie list as it is now, so a
 * cookie or service added, edited or removed on the Cookies page shows on the next page load with
 * no republish. The engine writes one table per category, each under a heading with the category
 * name, and may write one table of every cookie with no heading of its own; both are five columns
 * (name, provider, purpose, duration, storage type), which no other table in the document has.
 *
 * The column headings stay the stored ones, so a translated copy keeps its own. A value the cookie
 * list does not hold (the storage type, the wording for an unknown or session lifetime) stays what
 * the stored table said for that cookie.
 *
 * @param string $html Sanitised document body.
 *
 * @return string
 */
function wpconsent_legal_document_live_cookie_tables( $html ) {
	// A cookie table has five columns. The table may carry attributes (data-ld-table says which one it is, and
	// the sanitiser lets class and id through): none of them may stop it from being found.
	$table = '<table(?:\s[^>]*)?>\s*(<thead>\s*<tr>(?:\s*<th>[^<]*</th>){5}\s*</tr>\s*</thead>)\s*<tbody>(.*?)</tbody>\s*</table>';
	if ( ! preg_match_all( '#' . $table . '#s', $html, $stored ) ) {
		return $html;
	}
	// What the stored tables said about each cookie, by name.
	$known = array();
	foreach ( $stored[2] as $body ) {
		if ( preg_match_all( '#<tr>\s*<td>(.*?)</td>\s*<td>(.*?)</td>\s*<td>(.*?)</td>\s*<td>(.*?)</td>\s*<td>(.*?)</td>\s*</tr>#s', $body, $rows, PREG_SET_ORDER ) ) {
			foreach ( $rows as $row ) {
				$name = html_entity_decode( $row[1], ENT_QUOTES, 'UTF-8' );
				if ( ! isset( $known[ $name ] ) ) {
					$known[ $name ] = array_slice( $row, 1 );
				}
			}
		}
	}
	$groups   = wpconsent_legal_document_live_cookies( $known );
	$rendered = false;

	return preg_replace_callback(
		'#(?:<(h[2-6])((?:\s[^>]*)?)>[^<]*</\1>\s*)?' . $table . '#s',
		function ( $m ) use ( $groups, &$rendered ) {
			$open  = '<table>' . $m[3] . '<tbody>';
			$close = '</tbody></table>';
			if ( '' === $m[1] ) {
				// The table of every cookie.
				$all = array();
				foreach ( $groups as $group ) {
					$all = array_merge( $all, $group['rows'] );
				}
				ksort( $all, SORT_STRING );

				return $all ? $open . implode( '', $all ) . $close : '';
			}
			if ( $rendered ) {
				// The first category table already wrote every category there is now.
				return '';
			}
			$rendered = true;
			$output   = '';
			foreach ( $groups as $group ) {
				$output .= '<' . $m[1] . '>' . esc_html( $group['label'] ) . '</' . $m[1] . '>' . $open . implode( '', $group['rows'] ) . $close;
			}

			return $output;
		},
		$html
	);
}

/**
 * The site's cookies as table rows, one group per category that has any, in the order of the
 * Cookies page. A cookie listed twice for the same service and category is one row, and the
 * cookies of a service the owner marked as not in use stay out, as they do at publish.
 *
 * @param array $known What the stored tables said for each cookie: name => the five cells, as HTML.
 *
 * @return array Each: label (category name), rows (sort key => row HTML).
 */
function wpconsent_legal_document_live_cookies( $known = array() ) {
	$api     = wpconsent()->cookies;
	$answers = WPConsent_Legal_Documents_Store::get( 'confirmed_answers' );
	$answers = is_array( $answers ) ? $answers : (array) WPConsent_Legal_Documents_Store::get( 'answers', array() );
	$unused  = isset( $answers['services_not_in_use'] ) ? (array) $answers['services_not_in_use'] : array();
	$owner   = isset( $answers['business_name'] ) && is_string( $answers['business_name'] ) && '' !== trim( $answers['business_name'] ) ? trim( $answers['business_name'] ) : get_bloginfo( 'name' );
	// Every stored row has the same storage type today; a cookie added since gets it too.
	$storage = $known ? reset( $known )[4] : '';
	// $provider arrives as HTML: a stored cell, or an escaped name.
	$row     = function ( $cookie, $provider ) use ( $known, $storage ) {
		$name     = ! empty( $cookie['cookie_id'] ) ? $cookie['cookie_id'] : ( isset( $cookie['name'] ) ? $cookie['name'] : '' );
		$was      = isset( $known[ $name ] ) ? $known[ $name ] : null;
		$purpose  = isset( $cookie['description'] ) ? trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $cookie['description'] ) ) ) : '';
		$duration = isset( $cookie['duration'] ) ? trim( (string) $cookie['duration'] ) : '';
		// The engine words a lifetime nobody recorded and a session lifetime itself; keep its wording where it gave one.
		$worded = '' === $duration || '0' === $duration || false !== stripos( $duration, 'session' );

		return array(
			$name,
			'<tr><td>' . esc_html( $name ) . '</td><td>' . $provider . '</td><td>' . ( '' === $purpose && $was ? $was[2] : esc_html( $purpose ) ) . '</td><td>' . ( $worded && $was ? $was[3] : esc_html( '0' === $duration ? '' : $duration ) ) . '</td><td>' . ( $was ? $was[4] : $storage ) . '</td></tr>',
		);
	};
	$groups = array();
	$seen   = array();
	foreach ( (array) $api->get_categories() as $category ) {
		if ( empty( $category['id'] ) ) {
			continue;
		}
		$rows = array();
		$add  = function ( $cookie, $provider, $service ) use ( &$rows, $row ) {
			list( $name, $html ) = $row( $cookie, $provider );
			$key                 = $name . "\n" . $service;
			if ( '' !== $name && ! isset( $rows[ $key ] ) ) {
				$rows[ $key ] = $html;
			}
		};
		foreach ( (array) $api->get_services_by_category( $category['id'] ) as $service ) {
			$term = get_term( $service['id'] );
			$slug = $term && ! is_wp_error( $term ) ? $term->slug : '';
			$cookies = (array) $api->get_cookies_by_service( $service['id'] );
			// The document may name the provider differently from the Cookies page ("YouTube" for a service saved as
			// "Youtube"): the name most of the service's stored rows carry stays, for the cookies added since as well.
			$names = array();
			foreach ( $cookies as $cookie ) {
				$name = ! empty( $cookie['cookie_id'] ) ? $cookie['cookie_id'] : '';
				if ( isset( $known[ $name ] ) ) {
					$names[] = $known[ $name ][1];
				}
			}
			$counts   = array_count_values( $names );
			$provider = $counts ? array_search( max( $counts ), $counts, true ) : esc_html( $service['name'] );
			foreach ( $cookies as $cookie ) {
				$seen[ $cookie['id'] ] = true;
				if ( '' === $slug || ! in_array( $slug, $unused, true ) ) {
					$add( $cookie, $provider, $slug );
				}
			}
		}
		foreach ( (array) $api->get_cookies_by_category( $category['id'] ) as $cookie ) {
			if ( ! isset( $seen[ $cookie['id'] ] ) ) {
				$seen[ $cookie['id'] ] = true;
				$add( $cookie, esc_html( $owner ), '' );
			}
		}
		if ( $rows ) {
			ksort( $rows, SORT_STRING );
			$groups[] = array( 'label' => isset( $category['name'] ) ? $category['name'] : '', 'rows' => $rows );
		}
	}

	return $groups;
}

/**
 * The markup each widget renders as, or '' for one this site cannot provide.
 *
 * @return array widget name => html.
 */
function wpconsent_legal_document_widgets( $html = '' ) {
	// The request form shortcode is the addon's whole form: only a document that places it (the Do Not Sell page) runs it.
	$wants_form = '' === $html || 1 === preg_match( '/\bdata-(?:ld-)?widget="dnsmpi_form"/', $html );
	$types  = wpconsent_legal_document_types();
	$link   = function ( $type ) use ( $types ) {
		$meta = WPConsent_Legal_Documents_Store::document_meta( $type );
		if ( empty( $meta['page_id'] ) || 'publish' !== get_post_status( $meta['page_id'] ) ) {
			return '';
		}

		return '<a href="' . esc_url( get_permalink( $meta['page_id'] ) ) . '" class="wpconsent-legal-widget">' . esc_html( $types[ $type ] ) . '</a>';
	};
	// The revoke link and the preferences button only work through the banner's script, which is not loaded while the banner is off.
	$banner  = isset( wpconsent()->banner ) && method_exists( wpconsent()->banner, 'is_enabled' ) ? (bool) wpconsent()->banner->is_enabled() : true;
	$widgets = array(
		'consent_revoke_link'        => $banner ? '<a href="#" class="wpconsent-legal-widget wpconsent-open-preferences wpconsent-legal-revoke">' . esc_html__( 'Change your cookie choices', 'wpconsent-cookies-banner-privacy-suite' ) . '</a>' : '',
		'consent_preferences_button' => $banner && shortcode_exists( 'wpconsent_preferences_button' ) ? do_shortcode( '[wpconsent_preferences_button]' ) : '',
		'dnsmpi_link'                => $link( 'do_not_sell' ),
		'limit_spi_link'             => $link( 'limit_sensitive_pi' ),
		'dnsmpi_form'                => $wants_form && shortcode_exists( 'wpconsent_do_not_sell_form' ) ? do_shortcode( '[wpconsent_do_not_sell_form]' ) : '',
		'dnsmpi_form_optional_fields' => wpconsent_legal_document_form_optional_fields(),
	);

	/**
	 * The markup the document's widgets render as.
	 *
	 * @param array $widgets widget name => html ('' for one the site cannot provide).
	 */
	return apply_filters( 'wpconsent_legal_document_widgets', $widgets );
}

/**
 * The widgets as words, for the places that turn a document into text away from the site: the update
 * review's diff and the Markdown and PDF exports are made by the documents service, which only has the
 * placeholders. Each widget the site can fill is sent as its text, with its address when it is a link;
 * one the site cannot fill is left out, and the service then drops its sentence as the page does.
 *
 * @return array widget name => array( text, url (optional) ).
 */
function wpconsent_legal_document_widget_texts() {
	// The words go into a document written in the site's language, whoever asks for the file: an admin whose
	// profile is set to another language gets the site's words, as a visitor to the page does.
	$switched = function_exists( 'switch_to_locale' ) && switch_to_locale( get_locale() );
	$texts    = array();
	// Html without the form placeholder: the request form shortcode is not run for this.
	foreach ( wpconsent_legal_document_widgets( '<p></p>' ) as $name => $html ) {
		$text = trim( preg_replace( '/\s+/u', ' ', html_entity_decode( wp_strip_all_tags( (string) $html ), ENT_QUOTES, 'UTF-8' ) ) );
		if ( '' === $text ) {
			continue;
		}
		$texts[ $name ] = array( 'text' => $text );
		if ( preg_match( '#^\s*<a\s[^>]*href="(https?://[^"]+)"#i', (string) $html, $m ) ) {
			$texts[ $name ]['url'] = esc_url_raw( html_entity_decode( $m[1], ENT_QUOTES, 'UTF-8' ) );
		}
	}
	// The cookie-choice controls only work on the site: read in a file, their label alone points at a control
	// that is not there. They become a link to the published Cookie Policy page, which carries the control
	// whenever the banner is on; without that page they are left out, sentence and all.
	$cookie_page   = WPConsent_Legal_Documents_Store::document_meta( 'cookie_policy' );
	$controls_page = ! empty( $cookie_page['page_id'] ) && 'publish' === get_post_status( $cookie_page['page_id'] ) ? esc_url_raw( get_permalink( $cookie_page['page_id'] ) ) : '';
	foreach ( array( 'consent_revoke_link', 'consent_preferences_button' ) as $name ) {
		if ( ! isset( $texts[ $name ] ) ) {
			continue;
		}
		if ( '' === $controls_page ) {
			unset( $texts[ $name ] );
		} else {
			$texts[ $name ]['url'] = $controls_page;
		}
	}
	// The request form cannot be written out as words: where it is on a published page, the text points there.
	// Without the addon the page shows a line saying how to send the request instead; the file says the same.
	$meta = WPConsent_Legal_Documents_Store::document_meta( 'do_not_sell' );
	if ( ! wpconsent_legal_document_has_request_form() ) {
		$fallback = trim( preg_replace( '/\s+/u', ' ', html_entity_decode( wp_strip_all_tags( wpconsent_legal_document_form_fallback() ), ENT_QUOTES, 'UTF-8' ) ) );
		if ( '' !== $fallback ) {
			$texts['dnsmpi_form'] = array( 'text' => $fallback );
		}
	} elseif ( ! empty( $meta['page_id'] ) && 'publish' === get_post_status( $meta['page_id'] ) ) {
		$texts['dnsmpi_form'] = array(
			'text' => __( 'Open the request form', 'wpconsent-cookies-banner-privacy-suite' ),
			'url'  => esc_url_raw( get_permalink( $meta['page_id'] ) ),
		);
	}
	if ( $switched ) {
		restore_previous_locale();
	}

	/**
	 * The widgets as words, sent to the documents service with a render or an export.
	 *
	 * @param array $texts widget name => array( text, url (optional) ).
	 */
	$texts = apply_filters( 'wpconsent_legal_document_widget_texts', $texts );

	// What goes out fits what the service takes, whatever a filter added: an entry without text is dropped
	// here, and text is cut to the length the service accepts instead of losing its sentence there.
	$clean = array();
	foreach ( is_array( $texts ) ? $texts : array() as $name => $entry ) {
		if ( ! is_array( $entry ) || ! isset( $entry['text'] ) || ! is_string( $entry['text'] ) || '' === trim( $entry['text'] ) ) {
			continue;
		}
		$clean[ $name ] = array( 'text' => mb_substr( $entry['text'], 0, 500 ) );
		if ( ! empty( $entry['url'] ) && is_string( $entry['url'] ) ) {
			$clean[ $name ]['url'] = $entry['url'];
		}
	}

	return $clean;
}

/**
 * What the Do Not Sell page says the request form also asks for: the optional fields switched on in
 * the Do Not Sell addon's Form Fields settings, as a phrase ("your state and country"). Empty when
 * none is on or the addon's form is not there, and the sentence around it is then left out, so the
 * page never describes a field the form does not have.
 *
 * @return string
 */
function wpconsent_legal_document_form_optional_fields() {
	if ( ! wpconsent_legal_document_has_request_form() ) {
		return '';
	}
	// The addon's optional fields, in the order the form shows them. First name, last name and email are always there.
	$fields  = array(
		'address' => __( 'address', 'wpconsent-cookies-banner-privacy-suite' ),
		'zip'     => __( 'ZIP code', 'wpconsent-cookies-banner-privacy-suite' ),
		'city'    => __( 'city', 'wpconsent-cookies-banner-privacy-suite' ),
		'state'   => __( 'state', 'wpconsent-cookies-banner-privacy-suite' ),
		'country' => __( 'country', 'wpconsent-cookies-banner-privacy-suite' ),
		'phone'   => __( 'phone number', 'wpconsent-cookies-banner-privacy-suite' ),
	);
	$enabled = array();
	foreach ( $fields as $key => $name ) {
		if ( wpconsent()->settings->get_option( 'dnt_field_' . $key . '_enabled', 0 ) ) {
			$enabled[] = $name;
		}
	}
	if ( ! $enabled ) {
		return '';
	}

	/* translators: %s: the form's optional fields as a list, for example "state and country". The phrase follows "You can also tell us". */
	return esc_html( sprintf( __( 'your %s', 'wpconsent-cookies-banner-privacy-suite' ), wp_sprintf( '%l', $enabled ) ) );
}

/**
 * Remove every sentence that still holds a placeholder matching $pattern, and any block
 * (paragraph, list item, heading) left empty by it. A sentence ends at ". ", "! " or "? ".
 *
 * @param string $html    Document html.
 * @param string $pattern Regex for the placeholder.
 *
 * @return string
 */
function wpconsent_legal_document_drop_sentences( $html, $pattern ) {
	if ( ! preg_match( $pattern, $html ) ) {
		return $html;
	}

	return preg_replace_callback(
		'#<(p|li|h[1-6])(\s[^>]*)?>(.*?)</\1>#s',
		function ( $m ) use ( $pattern ) {
			if ( ! preg_match( $pattern, $m[3] ) ) {
				return $m[0];
			}
			$sentences = preg_split( '/(?<=[.!?])\s+/', $m[3] );
			$kept      = array();
			foreach ( $sentences as $sentence ) {
				if ( ! preg_match( $pattern, $sentence ) ) {
					$kept[] = $sentence;
				}
			}
			$text = trim( implode( ' ', $kept ) );
			if ( '' === wp_strip_all_tags( $text ) ) {
				return '';
			}

			return '<' . $m[1] . ( isset( $m[2] ) ? $m[2] : '' ) . '>' . $text . '</' . $m[1] . '>';
		},
		$html
	);
}

/**
 * Register the block from its build folder (the first block in the plugin).
 */
function wpconsent_legal_document_register_block() {
	if ( ! function_exists( 'register_block_type' ) ) {
		return;
	}
	$dir = WPCONSENT_PLUGIN_PATH . 'build/blocks/legal-document';
	if ( ! file_exists( $dir . '/block.json' ) || WP_Block_Type_Registry::get_instance()->is_registered( 'wpconsent/legal-document' ) ) {
		return;
	}
	// register_block_type() only takes a directory from WordPress 5.8; the metadata form has done so since 5.5.
	if ( function_exists( 'register_block_type_from_metadata' ) ) {
		$block = register_block_type_from_metadata( $dir, array( 'render_callback' => 'wpconsent_legal_document_block_render' ) );
	} else {
		$block = register_block_type( $dir, array( 'render_callback' => 'wpconsent_legal_document_block_render' ) );
	}
	// The editor's strings are translated here, so the block ships one script for Lite and Pro whatever text domain each build uses.
	$handle = $block && ! empty( $block->editor_script_handles ) ? $block->editor_script_handles[0] : ( $block && ! empty( $block->editor_script ) ? $block->editor_script : '' );
	if ( $handle ) {
		wp_localize_script(
			$handle,
			'wpconsentLegalDocumentBlock',
			array(
				'documents' => wpconsent_legal_document_types(),
				'panel'     => __( 'Document', 'wpconsent-cookies-banner-privacy-suite' ),
				'which'     => __( 'Which document', 'wpconsent-cookies-banner-privacy-suite' ),
				'empty'     => __( 'No published document yet. Generate and publish it from WPConsent → Legal Documents.', 'wpconsent-cookies-banner-privacy-suite' ),
			)
		);
	}
}

/**
 * Block render callback: the block is the shortcode with attributes.
 *
 * @param array $attributes Block attributes.
 *
 * @return string
 */
function wpconsent_legal_document_block_render( $attributes ) {
	return wpconsent_legal_document_shortcode(
		array(
			'document' => isset( $attributes['document'] ) ? $attributes['document'] : 'privacy_policy',
			'locale'   => isset( $attributes['locale'] ) ? $attributes['locale'] : '',
		)
	);
}

/**
 * Whether a post renders a legal document (shortcode or block), so the
 * stylesheet can go in the head.
 *
 * @param WP_Post|null $post Post.
 *
 * @return bool
 */
function wpconsent_legal_document_in_post( $post ) {
	if ( ! $post ) {
		return false;
	}
	if ( has_shortcode( $post->post_content, 'wpconsent_legal_document' ) ) {
		return true;
	}

	return function_exists( 'has_block' ) && has_block( 'wpconsent/legal-document', $post );
}

/**
 * Enqueue the stylesheet in <head> when a legal document is on the page.
 */
function wpconsent_legal_document_maybe_enqueue_in_head() {
	if ( ! is_singular() || ! wpconsent_legal_document_in_post( get_post() ) ) {
		return;
	}
	wpconsent_cookie_policy_register_styles();
	wp_enqueue_style( 'wpconsent-cookie-policy' );
}

add_action( 'wp_footer', 'wpconsent_legal_document_california_link', 20 );

/**
 * The California footer link (PRD sections 5 and 11): once the Do Not Sell or Share page is published,
 * every page links to it in the footer for visitors in California.
 *
 * The Do Not Sell addon has no footer link or geolocation of its own, so this uses WPConsent's
 * geolocation. The link is printed hidden and the rule is:
 * - a known location outside the US hides it;
 * - California, or the US with no state, shows it;
 * - no known location shows it (the CCPA-safe default). A stored answer without a country (one
 *   saved before the geolocation answer carried it when no group matched) is checked against the
 *   geolocation endpoint once; only when that gives no country either does the link show.
 * Without geolocation every visitor sees it. Never checks the licence: a published page keeps its
 * link after a lapse.
 */
function wpconsent_legal_document_california_link() {
	// wp_footer only runs on the front end; admin screens use admin_footer.
	if ( ! class_exists( 'WPConsent_Legal_Documents_Store' ) ) {
		return;
	}
	$meta = WPConsent_Legal_Documents_Store::document_meta( 'do_not_sell' );
	if ( empty( $meta['published_at'] ) || empty( $meta['page_id'] ) || 'publish' !== get_post_status( $meta['page_id'] ) ) {
		return;
	}
	$geolocation = isset( wpconsent()->geolocation ) && is_object( wpconsent()->geolocation ) && method_exists( wpconsent()->geolocation, 'enabled' ) && wpconsent()->geolocation->enabled();
	$link        = '<p class="wpconsent-legal-ca-link" style="margin:1em auto;text-align:center;"' . ( $geolocation ? ' hidden' : '' ) . '><a href="' . esc_url( get_permalink( $meta['page_id'] ) ) . '">' . esc_html( wpconsent_legal_document_types()['do_not_sell'] ) . '</a></p>';
	echo $link; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above.
	if ( ! $geolocation ) {
		return;
	}
	?>
	<script>
	( function () {
		var link = document.querySelector( '.wpconsent-legal-ca-link' );
		var tries = 0;
		function show( data ) {
			var country = data && data.country ? String( data.country ).toUpperCase() : '';
			var region = data && data.region ? String( data.region ).toUpperCase() : '';
			link.hidden = country ? ! ( 'US' === country && ( ! region || 'CA' === region ) ) : false;
		}
		function lookup() {
			var api = window.wpconsent && window.wpconsent.geolocation && window.wpconsent.geolocation.api_url;
			if ( ! api || ! window.fetch ) {
				show( null );
				return;
			}
			window.fetch( api ).then( function ( r ) {
				return r.json();
			} ).then( function ( data ) {
				if ( data && data.country && window.WPConsent && window.WPConsent.setCookie && window.wpconsent ) {
					window.WPConsent.setCookie( 'wpconsent_geolocation', JSON.stringify( data ), window.wpconsent.consent_duration );
				}
				show( data );
			} ).catch( function () {
				show( null );
			} );
		}
		function read() {
			var match = document.cookie.match( /(?:^|;\s*)wpconsent_geolocation=([^;]*)/ );
			if ( ! match ) {
				// The geolocation script stores the visitor's location shortly after load.
				if ( ++tries < 20 ) {
					window.setTimeout( read, 500 );
				} else {
					link.hidden = false;
				}
				return;
			}
			var data = {};
			try {
				data = JSON.parse( decodeURIComponent( match[1] ) );
			} catch ( e ) {}
			if ( data && data.use_default && ! data.country ) {
				lookup();
				return;
			}
			show( data );
		}
		read();
	}() );
	</script>
	<?php
}

add_action( 'init', 'wpconsent_legal_document_register_form_fallback', 20 );
/**
 * The Do Not Sell page carries the Do Not Sell addon's request form. Without the addon the shortcode
 * would print as text, so a stand-in renders a line telling visitors how to send their request.
 */
function wpconsent_legal_document_register_form_fallback() {
	if ( ! shortcode_exists( 'wpconsent_do_not_sell_form' ) ) {
		add_shortcode( 'wpconsent_do_not_sell_form', 'wpconsent_legal_document_form_fallback' );
	}
}

/**
 * The stand-in for the request form: an email link from the questionnaire, or a plain line.
 *
 * @return string
 */
function wpconsent_legal_document_form_fallback() {
	$answers = WPConsent_Legal_Documents_Store::get( 'confirmed_answers' );
	$answers = is_array( $answers ) ? $answers : (array) WPConsent_Legal_Documents_Store::get( 'answers', array() );
	$email   = isset( $answers['privacy_email'] ) ? sanitize_email( $answers['privacy_email'] ) : '';
	if ( $email ) {
		return '<p class="wpconsent-legal-request-fallback">' . sprintf(
			/* translators: %s: email link. */
			esc_html__( 'There is no request form on this page yet. Send your request by email to %s and we will treat it exactly as a request sent through the form.', 'wpconsent-cookies-banner-privacy-suite' ),
			'<a href="mailto:' . esc_attr( $email ) . '">' . esc_html( $email ) . '</a>'
		) . '</p>';
	}

	return '<p class="wpconsent-legal-request-fallback">' . esc_html__( 'There is no request form on this page yet. Send your request using the contact details in our privacy policy and we will treat it exactly as a request sent through the form.', 'wpconsent-cookies-banner-privacy-suite' ) . '</p>';
}

/**
 * Whether the Do Not Sell addon's request form is there, as opposed to the stand-in above.
 *
 * @return bool
 */
function wpconsent_legal_document_has_request_form() {
	global $shortcode_tags;

	return isset( $shortcode_tags['wpconsent_do_not_sell_form'] ) && 'wpconsent_legal_document_form_fallback' !== $shortcode_tags['wpconsent_do_not_sell_form'];
}
