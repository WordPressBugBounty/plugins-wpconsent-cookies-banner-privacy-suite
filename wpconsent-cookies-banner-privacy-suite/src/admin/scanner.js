window.WPConsentScanner = window.WPConsentScanner || (
	function ( document, window, $ ) {
		const app = {
			init: function () {
				if ( !app.should_init() ) {
					return;
				}
				app.find_elements();
				app.add_events();
			},
			should_init: function () {
				app.start_button = $( '#wpconsent-start-scanner' );
				return app.start_button.length > 0;
			},
			find_elements: function () {
				app.results = $( '#wpconsent-scanner-scripts' );
				app.service_template = $( '#wpconsent-scanner-service' ).html();
				app.message = $( '#wpconsent-scanner-message' );
				app.essential = $( '#wpconsent-scanner-essential' );
				app.form = $( '#wpconsent-scanner-form' );
				app.after_scan = $( '#wpconsent-after-scan' );
			},
			add_events: function () {
				app.start_button.on( 'click', app.start_scanner );
				app.form.on( 'submit', app.configure_cookies );
			},
			start_scanner: function ( e ) {
				e.preventDefault();
				app.start_button.prop( 'disabled', true );
				app.after_scan_action = app.start_button.data( 'action' );
				app.results.empty();
				// Ajax call to start the scanner.
				WPConsentConfirm.show_please_wait( wpconsent.scanning_title );
				const email = $( '#scanner-email' ).val();
				const data = {
					action: 'wpconsent_scan_website',
					nonce: wpconsent.nonce
				};
				// If email is empty we just move on without it.
				if ( email !== '' ) {
					data.email = email;
				}

				$.post(
					ajaxurl,
					data
				).always(
					function () {
						app.start_button.prop( 'disabled', false );
					}
				).done( app.handle_response );
			},
			handle_response: function ( response ) {
				WPConsentConfirm.close();
				if ( response.success ) {
					$.confirm(
						{
							title: wpconsent.scan_complete,
							content: response.data.message,
							type: 'blue',
							icon: 'fa fa-check-circle',
							animateFromElement: false,
							buttons: {
								confirm: {
									text: wpconsent.ok,
									btnClass: 'btn-confirm',
									keys: ['enter'],
								},
							},
							onAction: function ( action ) {
								if ( action === 'confirm' ) {
									app.do_after_scan_action( response );
								}
							}
						},
					);
				}
			},
			do_after_scan_action: function ( response ) {
				// Default action is reload so if the scan action is not set or empty we should reload.
				if ( app.after_scan_action === '' || 'reload' === app.after_scan_action ) {
					location.reload();
					return;
				}
				// Trigger a custom event we can hook into from another file and pass the response.
				$( document ).trigger( 'wpconsent_after_scan', response );
			},
			configure_cookies: function ( e ) {
				e.preventDefault();
				$.confirm( {
					title: wpconsent.configure_cookies_title,
					content: wpconsent.configure_cookies_content,
					type: 'blue',
					icon: 'fa fa-exclamation-circle',
					animateFromElement: false,
					buttons: {
						confirm: {
							text: wpconsent.yes,
							btnClass: 'btn-confirm',
							keys: ['enter'],
						},
						cancel: {
							text: wpconsent.no,
							btnClass: 'btn-cancel',
							keys: ['esc'],
						},
					},
					onAction: function ( action ) {
						if ( action === 'confirm' ) {
							const data = app.form.serialize();
							$.post(
								ajaxurl,
								data
							).done(
								function ( response ) {
									if ( response.success ) {
										// Display success message and reload after ok.
										$.alert( {
											title: '',
											content: response.data.message,
											onAction: function () {
												location.reload();
											}
										} );
									}
								}
							);
						}
					},
				} );
			}
		};
		return app;
	}( document, window, jQuery )
);

WPConsentScanner.init();