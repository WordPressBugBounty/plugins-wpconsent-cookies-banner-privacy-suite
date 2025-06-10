=== WPConsent - Cookie Consent Banner for Privacy Compliance (GDPR / CCPA) ===
Contributors: WPbeginner, smub, gripgrip, wpcodeteam
Tags: consent, cookie, cookie notice, cookie consent, gdpr
Requires at least: 5.6
Tested up to: 6.8
Requires PHP: 7.0
Stable tag: 1.0.8
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Improve WordPress privacy compliance. Custom cookie consent banner, full site cookie scanner, automatic script blocking, and easy cookie policy setup.

== Description ==

= Customizable Cookie Banner + Website Compliance Scanner =

WPConsent is the easiest way to add a cookie consent banner to your WordPress website. You can customize the banner to match your website's branding and configure how it looks.

Our easy-to-use website scanner will automatically detect and list popular services used on your website. Our built-in integration provides you with details and a list of cookies used by each service, so you can easily configure them.

With the automatic script blocking feature, WPConsent will detect and block common tracking scripts like Google Analytics, Facebook Pixel, and more until the user gives consent. This ensures that those services can't add 3rd party cookies without user consent.

All cookie consent data is self-hosted on your website like it should be, making WPConsent the most privacy conscious solution.

> <strong>WPConsent Pro</strong><br />
> This plugin is the Lite version of WPConsent Pro, which comes with geolocation options, modal layout, records of consent, multilanguage and more plugins automatically detected. [Click here to purchase the best premium WordPress consent plugin now!](https://wpconsent.com/?utm_source=wprepo&utm_medium=link&utm_campaign=liteplugin)

https://www.youtube.com/watch?v=7fcP9QO8bKQ&rel=0

= Fully Customizable Cookie Banner =

The cookie banner should match your website's branding and be easy to use. We give you many options in order to improve the consent rates on your website:

* Choose from multiple banner layouts and positions.
* Customize all colors and text to match your website's branding.
* Add your logo for a professional look.
* Multiple button styles to choose from.

= Automatic Script Blocking =

WPConsent will automatically prevent many popular tracking scripts from adding cookies on your website. There's no need to change the tracking solutions you use in order to use WPConsent. We are continuously adding new scripts to our block list, if you have a specific script you'd like us to block, please [reach out](https://wpconsent.com/contact/?utm_source=wprepo&utm_medium=link&utm_campaign=liteplugin).

Automatically detected scripts:

* Google Analytics
* Google Ads
* Facebook Pixel
* Microsoft Clarity
* Pinterest Tag
* and many more...

Google Consent Mode v2 is now supported, allowing you to use Google Analytics and Google Ads without cookies until the user gives consent. Our integration automatically works whether you add the scripts using [WPCode](https://wordpress.org/plugins/insert-headers-and-footers/), a plugin like [MonsterInsights](https://wordpress.org/plugins/google-analytics-for-wordpress/) or even if using Google Tag Manager.

= Website Compliance Scanner =

Our goal with WPConsent was to create a WordPress privacy compliance plugin that's both EASY and POWERFUL.

That's why we added a built-in website scanner that detects many popular services used on your website.

But that's not all, with our API integration you can get details about services and cookies used by each service automatically configured on your site.

These are the details included with each service:

* Service Name
* Service Description
* Service Category
* Service Privacy/Data URL (if 3rd party)
* List of Cookies with description and duration

= Smart Content Blocking =

Prevent 3rd party content from being loaded before consent is given.
WPConsent can automatically block content like YouTube, Vimeo, DailyMotion, Google Maps, reCAPTCHA and more until the user gives consent.

Dynamic placeholders are displayed for YouTube, Vimeo and DailyMotion for a better user experience. The placeholder images are loaded from your website for improved privacy.

You can easily customize which content is blocked from the admin area.

= Supports Key Global Privacy Regulations =

The WPConsent plugin offers a high degree of flexibility, making it a valuable tool for addressing a wide array of cookie law, data protection, and privacy regulations. This includes, but is not limited to:

* GDPR: The General Data Protection Regulation, ePrivacy Directive, ePrivacy Regulation (European Union)
* CCPA: The California Consumer Privacy Act (California, United States)
* LGPD: The Brazilian General Data Protection Law (Brazil)
* AAP: Australia’s Privacy Principles (Australia)
* PECR: The Privacy and Electronic Communications Regulations (UK)
* PIPEDA: The Personal Information Protection and Electronic Documents Act (Canada)
* and many other international standards and laws.

= Branding Guideline =

WPConsent™ is a trademark of WPConsent LLC. When writing about the WPConsent plugin, please make sure to uppercase the initial 3 letters.

WPConsent (correct)
WP Consent (incorrect)
wpconsent (incorrect)

= DISCLAIMER =

This plugin is not a guarantee of website compliance. It is your responsibility to ensure your website meets all applicable cookie law requirements.

== Installation ==

1. Install the WPConsent plugin by uploading the `wpconsent-cookies-banner-privacy-suite` directory to the `/wp-content/plugins/` directory. (See instructions on <a href="http://www.wpbeginner.com/beginners-guide/step-by-step-guide-to-install-a-wordpress-plugin-for-beginners/" rel="friend">how to install a WordPress plugin</a>.)
2. Activate the WPConsent plugin through the `Plugins` menu in WordPress.
3. Visit the WPConsent > Dashboard page to configure the plugin settings.

[youtube https://www.youtube.com/watch?v=QXbrdVjWaME]

== Screenshots ==

1. WPConsent Banner Customization
2. WPConsent Scanner
3. WPConsent Dashboard

== Frequently Asked Questions ==

= Is my website fully compliant by using WPConsent? =

This plugin is not a guarantee of website compliance. It is your responsibility to ensure your website meets all applicable cookie law requirements.

= How do I add a cookie banner to my website? =

After activating the WPConsent plugin, you can customize the cookie banner by visiting the WPConsent > Banner page in your WordPress admin area.

= How do I configure the automatic script blocking feature? =

You can always change the automatic script blocking setting by visiting the WPConsent > Settings page in your WordPress admin area.

= How do I scan my website for cookies? =

After activating the WPConsent plugin, you can scan your website for cookies by visiting the WPConsent > Scanner page in your WordPress admin area.


== Changelog ==

= 1.0.8 =
* New: You can now allow users to manage their consent per individual services in the preferences panel.
* New: Added an option to easily change the icon for the preferences panel button shown after consent is saved.
* Fix: Links were being stripped out from the banner message when performing an import.

= 1.0.7.1 =
* Fix: Fixed an issue with the import not updating categories correctly.
* Tweak: The import process was not allowing HTML tags in the banner message.

= 1.0.7 =
* New: We added an Export/Import feature on the Settings page so that you can move the WPConsent configuration to another site. You can choose what to include in the export: Settings, Banner Design or Cookie Data.
* New: Integration with the WP Consent API plugin.
* New: You can now disable the close (x) button on the banner.
* New: Cookie table headers in the preferences panel are now editable from the admin.
* Fix: We improved the way our frontend styles are loaded to avoid showing the banner unstyled in some instances.

= 1.0.6 =
* New: You can now choose which pages to scan for services that use cookies in the Scanner settings.
* New: We added the option to allow scripts to be loaded by default and only block them after cookies are rejected.
* New: We added a setting to remove all plugin data when the plugin is uninstalled.
* Tweak: We improved the way we Google Consent Mode is loaded for improved compatibility with other plugins.
* Fix: Categories with no cookies are no longer displayed in the preferences panel.

= 1.0.5 =
* New: Prevent content like YouTube, Vimeo, DailyMotion, Google Maps, reCAPTCHA from adding cookies before consent. Dynamic placeholders are displayed where available using local images for improved compliance. Automatic detection for more services coming soon.
* New: Improved preferences panel design with full cookies information for each category.
* New: Added easy CSS customization support using “part” syntax.
* Tweak: We adjusted the way our script blocker is loaded for improved compatibility with other plugins.

= 1.0.4.2 =
* Fix: Onboarding wizard scan results not loading correctly.

= 1.0.4.1 =
* Fix: Cache class not loaded correctly in some instances.

= 1.0.4 =
* New: Improved website scanner using our API.
* New: Improved script blocking with support for remote updates.
* New: Easily customize all text in the preferences panel from the admin.
* New: Automatic cookie clearing on consent change.
* Tweak: Improved script unblocking for blocked scripts.
* Tweak: Improve compatibility with WP Rocket lazy loading.

= 1.0.3.1 =
* Fix: The banner was scrolling the page to focus in some scenarios.

= 1.0.3 =
* New: Added support for Google Consent Mode V2.
* New: Added 1-click Cookie Policy page configuration with a basic template.
* New: Added automatic detection for Google Tag Manager, Stripe and Convert.com.
* New: Added filters to allow other plugins to define scripts that are detected and blocked.
* Tweak: Improved accessibility of frontend banner with support for keyboard navigation.

= 1.0.2.1 =
* Fix: Mobile styles for banner.
* Fix: Link color in preferences panel.

= 1.0.2 =
* New: Use Shadow DOM for cookie banner for improved compatibility.
* New: Add global methods to read consent status to be used in other plugins.

= 1.0.1 =
* Tweak: Prevent loading script blocking class on rest endpoints.

= 1.0.0 =
* Initial Release

