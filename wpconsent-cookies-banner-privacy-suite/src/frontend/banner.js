/**
 * Show the banner and handle position changes.
 *
 * @package WPConsent
 */

// Define global WPConsent namespace
window.WPConsent = {
	// Core functions that need to be globally accessible
	acceptAll: function() {
		this.savePreferences({essential: true, statistics: true, marketing: true});
		this.closePreferences();
	},

	savePreferences: function(preferences) {
		const existingPreferences = this.getCookie('wpconsent_preferences');
		// Save preferences to a cookie
		this.setCookie('wpconsent_preferences', JSON.stringify(preferences), wpconsent.consent_duration);

		// Hide the banner
		this.hideBanner();

		// Close preferences modal if open
		this.closePreferences();

		// Unlock scripts based on the new preferences
		this.unlockScripts(preferences);

		// Show the floating button
		const floatingButton = this.shadowRoot?.querySelector('#wpconsent-consent-floating');
		if (floatingButton) {
			floatingButton.style.display = 'block';
		}

		// Trigger events
		window.dispatchEvent(new CustomEvent('wpconsent_consent_saved', {detail: preferences}));

		if (existingPreferences) {
			window.dispatchEvent(new CustomEvent('wpconsent_consent_updated', {detail: preferences}));
		}
	},

	showPreferences: function() {
		const modal = this.shadowRoot?.querySelector('#wpconsent-preferences-modal');
		if (modal) {
			modal.style.display = 'flex';
			// Set up focus trap for the preferences modal
			this.setupFocusTrap(modal);
			// Focus the preferences title
			const modalTitle = this.shadowRoot?.querySelector('#wpconsent-preferences-title');
			if (modalTitle) {
				setTimeout(() => {
					modalTitle.focus({ preventScroll: true });
					// Set this as our tracked element
					this.lastFocusedElement = modalTitle;
				}, 100);
			}
		}
	},

	closePreferences: function() {
		const modal = this.shadowRoot?.querySelector('#wpconsent-preferences-modal');
		if (modal) {
			modal.style.display = 'none';
			// Remove focus trap when preferences modal is closed
			this.removeFocusTrap();
			// Return focus to the element that had focus before the modal was shown
			if (this.previouslyFocusedElement) {
				this.previouslyFocusedElement.focus({ preventScroll: true });
				this.previouslyFocusedElement = null;
			}
		}
	},

	showBanner: function() {
		const banner = this.shadowRoot?.querySelector('#wpconsent-banner-holder');
		if (banner) {
			banner.classList.add('wpconsent-banner-visible');
			// Set up focus trap for the banner
			this.setupFocusTrap(banner);
			// Focus the first focusable element in the banner
			this.setInitialFocus(banner);
		}
	},

	hideBanner: function() {
		const banner = this.shadowRoot?.querySelector('#wpconsent-banner-holder');
		if (banner) {
			banner.classList.remove('wpconsent-banner-visible');
			// Remove focus trap when banner is hidden
			this.removeFocusTrap();
			// Return focus to the element that had focus before the banner was shown
			if (this.previouslyFocusedElement) {
				this.previouslyFocusedElement.focus({ preventScroll: true });
				this.previouslyFocusedElement = null;
			}
		}
	},

	setCookie: function(name, value, days) {
		let expires = '';
		if (days > 0) {
			const date = new Date();
			date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
			expires = 'expires=' + date.toUTCString() + ';';
		}
		document.cookie = name + '=' + value + ';' + expires + 'path=/';
	},

	getCookie: function(name) {
		const value = `; ${document.cookie}`;
		const parts = value.split(`; ${name}=`);
		if (parts.length === 2) {
			return parts.pop().split(';').shift();
		}
	},

	hasConsent: function(category) {
		// Get current preferences from cookie
		const preferencesStr = this.getCookie('wpconsent_preferences');
		if (!preferencesStr) {
			return false;
		}

		try {
			const preferences = JSON.parse(preferencesStr);
			// Essential cookies are always allowed
			if (category === 'essential') {
				return true;
			}
			// Return the status for the requested category
			return preferences[category] === true;
		} catch (e) {
			console.error('Error parsing WPConsent preferences:', e);
			return false;
		}
	},

	unlockScripts: function(preferences) {
		const scripts = document.querySelectorAll('script[type="text/plain"]');
		scripts.forEach(script => {
			const category = script.getAttribute('data-wpconsent-category');
			if (preferences[category]) {
				const newScript = document.createElement('script');

				// Copy all attributes except 'type'
				script.getAttributeNames().forEach(attr => {
					if (attr !== 'type') {
						newScript.setAttribute(attr, script.getAttribute(attr));
					}
				});

				// Handle src attribute
				const src = script.getAttribute('data-wpconsent-src');
				if (src) {
					newScript.src = src;
				} else {
					newScript.text = script.text;
				}

				script.parentNode.replaceChild(newScript, script);
			}
		});

		// Send a custom event on the document when consent is processed.
		document.dispatchEvent(new CustomEvent('wpconsent_consent_processed', {detail: preferences}));
	},

	// Initialize the banner
	init: function() {
		const root = document.getElementById('wpconsent-root');
		const container = document.getElementById('wpconsent-container');
		const template = document.getElementById('wpconsent-template');

		// Get existing shadow root or create new one
		this.shadowRoot = container.shadowRoot;
		if (!this.shadowRoot) {
			this.shadowRoot = container.attachShadow({mode: 'open'});
			const content = template.content.cloneNode(true);
			this.shadowRoot.appendChild(content);
			template.remove();

			this.loadExternalCSS();
			this.initializeEventListeners();
			this.initializeKeyboardHandlers();
		}

		// Check for existing preferences
		const existingPreferences = this.getCookie('wpconsent_preferences');
		if (existingPreferences) {
			const preferences = JSON.parse(existingPreferences);
			this.unlockScripts(preferences);
			const floatingButton = this.shadowRoot.querySelector('#wpconsent-consent-floating');
			if (floatingButton) {
				floatingButton.style.display = 'block';
			}
		} else {
			this.showBanner();
		}
	},

	// Load external CSS
	loadExternalCSS: async function() {
		try {
			const cssUrl = `${wpconsent.css_url}?ver=${wpconsent.css_version}`;
			const response = await fetch(cssUrl);
			const css = await response.text();
			const style = document.createElement('style');
			style.textContent = css;
			this.shadowRoot.appendChild(style);
		} catch (error) {
			console.error('Failed to load WPConsent styles:', error);
		}
	},

	// Initialize event listeners
	initializeEventListeners: function() {
		// Accept all button
		this.shadowRoot.querySelectorAll('.wpconsent-accept-all').forEach(button => button.addEventListener('click', () => this.acceptAll()));

		// Cancel all button
		this.shadowRoot.querySelector('#wpconsent-cancel-all')?.addEventListener('click', () => {
			this.savePreferences({essential: true, statistics: false, marketing: false});
		});

		// Close button
		this.shadowRoot.querySelector('#wpconsent-banner-close')?.addEventListener('click', () => this.hideBanner());

		// Preferences button
		this.shadowRoot.querySelector('#wpconsent-preferences-all')?.addEventListener('click', () => this.showPreferences());

		// Floating button
		const floatingButton = this.shadowRoot.querySelector('#wpconsent-consent-floating');
		if (floatingButton) {
			floatingButton.addEventListener('click', () => this.showPreferences());
		}

		// Preferences modal buttons
		this.shadowRoot.querySelector('.wpconsent-save-preferences')?.addEventListener('click', () => {
			const checkboxes = this.shadowRoot.querySelectorAll('#wpconsent-preferences-modal input[type="checkbox"]');
			const selectedCookies = Array.from(checkboxes)
				.filter(checkbox => checkbox.checked)
				.map(checkbox => checkbox.value);

			this.savePreferences({
				essential: true,
				statistics: selectedCookies.includes('statistics'),
				marketing: selectedCookies.includes('marketing')
			});
		});
		this.shadowRoot.querySelector('.wpconsent-close-preferences')?.addEventListener('click', () => this.closePreferences());
	},

	// Initialize keyboard handlers for accessibility
	initializeKeyboardHandlers: function() {
		// Add event listener for tab key to manage focus
		document.addEventListener('keydown', (e) => {
			if (e.key === 'Tab') {
				this.handleTabKey(e);
			} else if (e.key === 'Escape') {
				this.handleEscapeKey(e);
			}
		});
	},

	// Handle escape key press
	handleEscapeKey: function(e) {
		const preferencesModal = this.shadowRoot?.querySelector('#wpconsent-preferences-modal');
		const bannerHolder = this.shadowRoot?.querySelector('#wpconsent-banner-holder');

		// If preferences modal is open, close it
		if (preferencesModal && preferencesModal.style.display === 'flex') {
			this.closePreferences();
		}
		// Otherwise, if banner is visible, close it
		else if (bannerHolder && bannerHolder.classList.contains('wpconsent-banner-visible')) {
			this.hideBanner();
		}
	},

	// Handle tab key press to implement focus trap
	handleTabKey: function(e) {
		// Check if banner or preferences modal is visible
		const bannerHolder = this.shadowRoot?.querySelector('#wpconsent-banner-holder');
		const preferencesModal = this.shadowRoot?.querySelector('#wpconsent-preferences-modal');

		const bannerVisible = bannerHolder && bannerHolder.classList.contains('wpconsent-banner-visible');
		const preferencesVisible = preferencesModal && preferencesModal.style.display === 'flex';

		// If neither is visible, do nothing
		if (!bannerVisible && !preferencesVisible) {
			return;
		}

		// Determine which container is active
		const container = preferencesVisible ? preferencesModal : bannerHolder;

		// Get all focusable elements in the container
		const focusableElements = this.getFocusableElements(container);

		if (focusableElements.length === 0) {
			return;
		}

		// Prevent default tab behavior
		e.preventDefault();

		// Set up variables for the first and last focusable elements
		const firstElement = focusableElements[0];
		const lastElement = focusableElements[focusableElements.length - 1];

		// Track current element index
		let currentElement;

		// If we already have a tracked element, use it
		if (this.lastFocusedElement && focusableElements.includes(this.lastFocusedElement)) {
			currentElement = this.lastFocusedElement;
		} else {
			// Otherwise, start with the first element
			currentElement = firstElement;
			this.lastFocusedElement = currentElement;
		}

		// Find the index of the current element
		const currentIndex = focusableElements.indexOf(currentElement);

		// Determine the next element to focus
		let nextElement;

		if (e.shiftKey) {
			// Shift+Tab moves backwards
			if (currentIndex <= 0) {
				nextElement = lastElement; // Wrap to last element
			} else {
				nextElement = focusableElements[currentIndex - 1];
			}
		} else {
			// Tab moves forward
			if (currentIndex >= focusableElements.length - 1) {
				nextElement = firstElement; // Wrap to first element
			} else {
				nextElement = focusableElements[currentIndex + 1];
			}
		}

		// Focus the next element and update our tracking
		nextElement.focus({ preventScroll: true });
		this.lastFocusedElement = nextElement;
	},

	// Set up focus trap for a container
	setupFocusTrap: function(container) {
		// Store the element that had focus before opening the container
		this.previouslyFocusedElement = document.activeElement;
		// Reset the tracked focused element
		this.lastFocusedElement = null;
	},

	// Remove focus trap
	removeFocusTrap: function() {
		// Clear the tracked focused element
		this.lastFocusedElement = null;
	},

	// Set initial focus to the first focusable element
	setInitialFocus: function(container) {
		const focusableElements = this.getFocusableElements(container);
		if (focusableElements.length > 0) {
			// Focus on the first button for better accessibility
			setTimeout(() => {
				focusableElements[0].focus({ preventScroll: true });
				// Set this as our tracked element
				this.lastFocusedElement = focusableElements[0];
			}, 100);
		}
	},

	// Get all focusable elements within a container
	getFocusableElements: function(container) {
		// Selectors for focusable elements
		const focusableSelectors = 'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])';

		// Query all focusable elements within the container
		const elements = Array.from(container.querySelectorAll(focusableSelectors))
			// Filter out hidden elements
			.filter(el => {
				// Check if element is visible
				const style = window.getComputedStyle(el);
				return style.display !== 'none' && style.visibility !== 'hidden' && !el.disabled;
			});

		return elements;
	},

	// Check if an element is contained within a container
	isElementInContainer: function(element, container) {
		if (!element || !container) {
			return false;
		}

		// Check if the element is within the shadow DOM container
		if (container.shadowRoot) {
			return container.shadowRoot.contains(element);
		}

		return container.contains(element);
	}
};

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => WPConsent.init());
