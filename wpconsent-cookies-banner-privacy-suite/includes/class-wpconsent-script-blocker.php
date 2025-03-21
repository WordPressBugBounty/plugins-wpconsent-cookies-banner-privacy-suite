<?php
/**
 * Class used to manage script blocking based on cookie categories.
 *
 * @package WPConsent
 */

/**
 * Class WPConsent_Script_Blocker.
 */
class WPConsent_Script_Blocker {

	/**
	 * Known scripts categorized by cookie type.
	 *
	 * @var array
	 */
	protected $categorized_scripts = array();

	/**
	 * Get scripts for a specific category.
	 *
	 * @param string $category The category to get scripts for.
	 *
	 * @return array
	 */
	public function get_scripts_for_category( $category ) {
		return isset( $this->categorized_scripts[ $category ] ) ? $this->categorized_scripts[ $category ] : array();
	}

	/**
	 * Load the known scripts data.
	 */
	public function load_data() {
		$this->categorized_scripts = array(
			'essential'  => array(
				'google-tag-manager' => array(
					'label'   => 'Google Tag Manager',
					'scripts' => array(
						'googletagmanager.com/gtm.js',
					),
				),
				'stripe'             => array(
					'label'   => 'Stripe',
					'scripts' => array(
						'js.stripe.com/v3',
					),
				),
			),
			'statistics' => array(
				'google-analytics' => array(
					'label'   => 'Google Analytics',
					'scripts' => array(
						'google-analytics.com/analytics.js',
						'googletagmanager.com/gtag/js',
						'google-analytics.com/ga.js',
					),
				),
				'matomo'           => array(
					'label'   => 'Matomo',
					'scripts' => array(
						'matomo.php',
					),
				),
				'clarity'          => array(
					'label'   => 'Clarity',
					'scripts' => array(
						'clarity.ms',
					),
				),
				'clicky'           => array(
					'label'   => 'Clicky',
					'scripts' => array(
						'static.getclicky.com',
					),
				),
				'convert-insights' => array(
					'label'   => 'Convert Insights',
					'scripts' => array(
						'convertexperiments.com/v1/js',
					),
				),
			),
			'marketing'  => array(
				'facebook-pixel'   => array(
					'label'   => 'Facebook Pixel',
					'scripts' => array(
						'connect.facebook.net/en_US/fbevents.js',
					),
				),
				'google-ads'       => array(
					'label'   => 'Google Ads',
					'scripts' => array(
						'googleads.g.doubleclick.net',
					),
				),
				'linkedin-insight' => array(
					'label'   => 'LinkedIn Insight',
					'scripts' => array(
						'snap.licdn.com/li.lms-analytics/insight.min.js',
					),
				),
				'twitter-pixel'    => array(
					'label'   => 'X (formerly Twitter) Pixel',
					'scripts' => array(
						'static.ads-twitter.com/uwt.js',
						'platform.twitter.com/widgets.js',
						'analytics.twitter.com/i/adsct',
						'static.ads-x.com/uwt.js',
					),
				),
				'pinterest-tag'    => array(
					'label'   => 'Pinterest Tag',
					'scripts' => array(
						'assets.pinterest.com/js/pinit.js',
						's.pinimg.com/ct/core.js',
					),
				),
				'snapchat-pixel'   => array(
					'label'   => 'Snapchat Pixel',
					'scripts' => array(
						'sc-static.net/scevent.min.js',
					),
				),
				'tiktok-pixel'     => array(
					'label'   => 'TikTok Pixel',
					'scripts' => array(
						'analytics.tiktok.com/i18n/pixel/events.js',
					),
				),
				'optinmonster'     => array(
					'label'   => 'OptinMonster',
					'scripts' => array(
						'omappapi.com/app/js/api.min.js',
					),
				),
			),
		);
	}

	/**
	 * Get all known scripts.
	 *
	 * @return array
	 */
	public function get_all_scripts() {
		if ( empty( $this->categorized_scripts ) ) {
			$this->load_data();
		}

		return apply_filters( 'wpconsent_blocked_scripts', $this->categorized_scripts );
	}
}
