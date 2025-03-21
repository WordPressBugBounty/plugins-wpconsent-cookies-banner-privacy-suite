const WPConsentInputs = window.WPConsentInputs || (
	function ( document, window, $ ) {
		const app = {
			init() {
				$( app.ready );
			},
			ready() {
				app.initCheckbox();
				app.initShowHidden();
				app.itemToggle();
			},
			initCheckbox() {
				$( document ).on(
					'change',
					'.wpconsent-styled-checkbox input',
					function () {

						var $this = $( this );

						if ( $this.prop( 'checked' ) ) {
							$this.parent().addClass( 'checked' );

						} else {
							$this.parent().removeClass( 'checked' );
						}
					}
				);
			},
			initShowHidden() {
				$( document ).on( 'click', '.wpconsent-show-hidden', function ( e ) {
					e.preventDefault();
					const target = $( this ).data( 'target' );
					const hide_label = $( this ).data( 'hide-label' );
					// Let's find the target element in the parent element.
					$( this ).closest('.wpconsent-show-hidden-container').find( target ).toggleClass( 'wpconsent-visible' );
					if ( hide_label ) {
						// Let's keep track of the original label and toggle between them based on the visible class being present on the target element.
						const original_label = $( this ).text();
						const new_label = $( this ).data( 'hide-label' );
						$( this ).data( 'hide-label', original_label );
						$( this ).text( $( this ).text() === original_label ? new_label : original_label );
					}
				} );
			},
			itemToggle() {
				$( document ).on( 'click', '.wpconsent-onboarding-selectable-item', function ( e ) {
					// Only toggle if the target is not in the .wpconsent-onboarding-service-info element.
					if ( $( e.target ).closest( '.wpconsent-onboarding-service-info' ).length ) {
						return;
					}
					const checkbox = $( this ).find( 'input[type="checkbox"]' );
					checkbox.prop( 'checked', !checkbox.prop( 'checked' ) ).trigger( 'change' );
				} );
			},
		};
		return app;
	}( document, window, jQuery )
);

WPConsentInputs.init();
