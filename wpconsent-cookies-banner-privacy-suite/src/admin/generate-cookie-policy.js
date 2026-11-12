/* The one-click Cookie Policy generator, shared by the Cookies page and the Legal Documents page.
   The button carries data-source for the usage counter. A page with the policy select updates it in
   place; any other page reloads once the dialog closes, so its list reads the new page. */
jQuery( function ( $ ) {
	$( document ).on( 'click', '.wpconsent-generate-cookie-policy-page', function ( e ) {
		e.preventDefault();
		var $this = $( this );
		// A button at the edge of its container (the Legal Documents actions cell) asks for the spinner on its left.
		WPConsentSpinner.show_button_spinner( $this, $this.data( 'spinner-position' ) || 'right' );

		var data = {
			action: 'wpconsent_generate_cookie_policy',
			nonce: wpconsent.nonce,
			source: $this.data( 'source' ) || 'cookies'
		};

		$.post( ajaxurl, data ).always( function () {
			WPConsentSpinner.hide_button_spinner( $this );
		} ).done( function ( data ) {
			var $select = $( '#cookie-policy-page' );
			var reload = data.success && ! $select.length;
			// A capability error sends only a message, so the dialog falls back to the generic strings.
			var dialog = data.data || {};

			if ( data.success && $select.length ) {
				try {
					wpconsent_choices['cookie-policy-page'].setValue( [
						{
							value: dialog.page_id,
							label: dialog.page_title,
							selected: true
						}
					] );
					// Let's trigger a change on the select to update the hidden input.
					$select.trigger( 'change' );
				} catch ( err ) {
					console.error( err );
				}
			}

			var afterClose = function () {
				if ( reload ) {
					window.location.reload();
				}
			};

			var buttons = {
				ok: {
					text: wpconsent.ok,
					btnClass: 'btn-blue',
					action: afterClose
				}
			};
			if ( dialog.link ) {
				buttons.view_page = {
					text: dialog.view_page,
					btnClass: 'btn-blue',
					action: function () {
						window.open( dialog.link, '_blank' );
						afterClose();
					}
				};
			}

			$.confirm( {
				title: dialog.title || ( data.success ? '' : wpconsent.error ),
				content: dialog.message || wpconsent.error,
				type: data.success ? 'blue' : 'red',
				buttons: buttons
			} );
		} ).fail( function () {
			// An expired nonce or a server error: say so instead of clearing the spinner silently.
			$.alert( {
				title: wpconsent.error,
				content: wpconsent.error,
				type: 'red',
				buttons: {
					ok: {
						text: wpconsent.ok,
						btnClass: 'btn-red'
					}
				}
			} );
		} );
	} );
} );
