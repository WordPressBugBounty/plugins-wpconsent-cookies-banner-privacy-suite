/**
 * One-click switch to the banner message Google recommends for Google Consent Mode.
 */
jQuery( function ( $ ) {
	/**
	 * Show a message in the banner preview, if the page has one.
	 *
	 * @param {string} message The banner message HTML.
	 */
	function updatePreview( message ) {
		const container = document.getElementById( 'wpconsent-container' );
		if ( ! container || ! container.shadowRoot ) {
			return;
		}
		const target = container.shadowRoot.querySelector( '.wpconsent-banner-message' );
		if ( target ) {
			target.innerHTML = message;
		}
	}

	/**
	 * Put the message in the Banner Content editor, if the page has it.
	 *
	 * @param {string} message The banner message HTML.
	 */
	function updateEditor( message ) {
		if ( 'undefined' !== typeof window.tinymce && window.tinymce.get( 'banner_message' ) ) {
			window.tinymce.get( 'banner_message' ).setContent( message );
		}
		$( '#banner_message' ).val( message );
	}

	window.WPConsentGoogleBannerMessage = { updatePreview };

	$( document ).on( 'click', '.wpconsent-apply-google-banner-message', function ( e ) {
		e.preventDefault();
		const $notice = $( this ).closest( '.wpconsent-google-banner-notice' );

		$.confirm( {
			title: wpconsent.google_banner_message_title,
			content: wpconsent.google_banner_message_content,
			boxWidth: '560px',
			theme: 'modern',
			type: 'blue',
			buttons: {
				apply: {
					text: wpconsent.google_banner_message_button,
					btnClass: 'btn-confirm',
					action() {
						$.post(
							ajaxurl,
							{
								action: 'wpconsent_apply_google_banner_message',
								nonce: wpconsent.nonce,
							},
							function ( response ) {
								if ( ! response.success ) {
									return;
								}
								updateEditor( response.data.banner_message );
								updatePreview( response.data.banner_message );
								$notice.find( '.wpconsent-tcf-notice > span:last-child' ).text( response.data.message );
							}
						);
					},
				},
				cancel: {
					text: wpconsent.cancel_button,
					btnClass: '',
				},
			},
		} );
	} );
} );
