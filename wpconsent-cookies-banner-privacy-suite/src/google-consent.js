(function () {
	window.dataLayer = window.dataLayer || [];function gtag(){dataLayer.push(arguments);}
	gtag('consent', 'default', {
		'security_storage': 'granted',
		'functionality_storage': 'granted',
		'ad_storage': 'denied',
		'analytics_storage': 'denied',
		'ad_user_data': 'denied',
		'ad_personalization': 'denied',
	});

	document.addEventListener( 'wpconsent_consent_processed', function( event ) {
		// passed detail is preferences.
		const preferences = event.detail;
		gtag('consent', 'update', {
			'ad_storage': preferences.marketing ? 'granted' : 'denied',
			'analytics_storage': preferences.statistics ? 'granted' : 'denied',
			'ad_user_data': preferences.marketing ? 'granted' : 'denied',
			'ad_personalization': preferences.marketing ? 'granted' : 'denied',
			'security_storage': 'granted',
			'functionality_storage': 'granted'
		});
	});
})();
