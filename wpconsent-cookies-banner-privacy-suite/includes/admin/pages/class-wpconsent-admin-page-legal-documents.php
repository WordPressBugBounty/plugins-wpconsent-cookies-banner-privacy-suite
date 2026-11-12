<?php
/**
 * Legal Documents admin page.
 *
 * Lite and Basic see the documents list with the free Cookie Policy page as a
 * live row, and the Plus card below it with a single action: Upgrade to Plus,
 * or renew or activate the license when the key is expired or not active.
 * Entitled licences get the views the Pro subclass fills in: overview, wizard,
 * review, history, and the expired view when the licence lapses.
 *
 * @package WPConsent
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPConsent_Admin_Page_Legal_Documents
 */
class WPConsent_Admin_Page_Legal_Documents extends WPConsent_Admin_Page {

	/**
	 * Page slug.
	 *
	 * @var string
	 */
	public $page_slug = 'wpconsent-legal-documents';

	/**
	 * Per-site user option set once an admin's view of the locked page has been counted.
	 *
	 * @var string
	 */
	const LOCKED_SEEN_META = 'wpconsent_legal_locked_seen';

	/**
	 * Default view.
	 *
	 * @var string
	 */
	public $view = 'overview';

	/**
	 * Entitlement state for this request.
	 *
	 * @var string
	 */
	protected $entitlement_state = 'lite';

	/**
	 * Views that are not tabs but still addressable with ?view=.
	 *
	 * @var array
	 */
	protected $hidden_views = array( 'wizard', 'review', 'expired' );

	/**
	 * Set the translatable titles.
	 */
	public function __construct() {
		$this->page_title = __( 'Legal Documents', 'wpconsent-cookies-banner-privacy-suite' );
		$this->menu_title = __( 'Legal Documents', 'wpconsent-cookies-banner-privacy-suite' );

		$this->entitlement_state = WPConsent_Legal_Documents_Entitlement::state();

		parent::__construct();
	}

	/**
	 * Tabs: overview and history. The wizard, review and expired views are reached by link, not tab.
	 */
	protected function setup_views() {
		if ( 'active' !== $this->entitlement_state ) {
			return;
		}
		$this->views = array(
			'overview' => __( 'Documents', 'wpconsent-cookies-banner-privacy-suite' ),
			'history'  => __( 'History', 'wpconsent-cookies-banner-privacy-suite' ),
		);
	}

	/**
	 * Accept the hidden views as well as the tabs.
	 */
	protected function set_current_view() {
		parent::set_current_view();
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$view = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		if ( in_array( $view, $this->hidden_views, true ) ) {
			$this->view = $view;
		}
	}

	/**
	 * Output the page content for the current entitlement state and view.
	 *
	 * @return void
	 */
	public function output_content() {
		// With nothing published an expired key has nothing to show yet, so it gets the locked card with Renew.
		if ( $this->lapsed() ) {
			$this->output_view_expired();

			return;
		}
		if ( $this->shows_locked() ) {
			$this->output_documents();

			return;
		}
		$method = 'output_view_' . $this->view;
		if ( method_exists( $this, $method ) ) {
			$this->$method();

			return;
		}
		$this->output_view_overview();
	}

	/**
	 * A licence that no longer includes the feature (expired, downgraded to Basic, removed) on a site
	 * that has published documents: the pages stay, so the owner sees the lapse view with export, never the upsell.
	 *
	 * @return bool
	 */
	protected function lapsed() {
		return in_array( $this->entitlement_state, array( 'expired', 'basic', 'inactive' ), true ) && WPConsent_Legal_Documents_Store::has_published();
	}

	/**
	 * Whether this request shows the locked upsell card: any licence without the feature on a site
	 * with nothing published. It needs nothing from the service and counts as legal.locked_shown only.
	 *
	 * @return bool
	 */
	protected function shows_locked() {
		return 'active' !== $this->entitlement_state && ! $this->lapsed();
	}

	/**
	 * Overview: the documents and what needs attention. Pro fills it in.
	 */
	public function output_view_overview() {
		$this->output_coming_soon();
	}

	/**
	 * Wizard: gate, screener, scan and the six steps. Pro fills it in.
	 */
	public function output_view_wizard() {
		$this->output_coming_soon();
	}

	/**
	 * Review: previews and publishing choices. Pro fills it in.
	 */
	public function output_view_review() {
		$this->output_coming_soon();
	}

	/**
	 * History: versions, compare, restore, export. Pro fills it in.
	 */
	public function output_view_history() {
		$this->output_coming_soon();
	}

	/**
	 * Expired: what is out of date, renew, export. Pro fills it in.
	 */
	public function output_view_expired() {
		$this->output_coming_soon();
	}

	/**
	 * Lite and Basic: the documents list with the free Cookie Policy row, and
	 * the Plus card with one action below it.
	 *
	 * @return void
	 */
	protected function output_documents() {
		$this->count_locked_view();

		$action = $this->get_locked_action();
		?>
		<div class="wpconsent-legal-documents">
			<?php
			$this->box( __( 'Your Documents', 'wpconsent-cookies-banner-privacy-suite' ), $this->get_documents_list( $action ), array( 'flush' => true ) );

			echo WPConsent_Admin_Page::get_upsell_box( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				esc_html__( 'Legal Documents', 'wpconsent-cookies-banner-privacy-suite' ) . ' <span class="wpconsent-pro-pill">' . esc_html__( 'Plus', 'wpconsent-cookies-banner-privacy-suite' ) . '</span>',
				'<p>' . esc_html__( 'Generate a Privacy Policy and Cookie Policy from your answers and your site scan.', 'wpconsent-cookies-banner-privacy-suite' ) . '</p>',
				array(
					'text'       => esc_html( $action['text'] ),
					'url'        => esc_url( $action['url'] ),
					'class'      => 'wpconsent-button wpconsent-button-orange',
					'attributes' => $action['attributes'], // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in get_list_item_button().
				),
				array(),
				array(
					esc_html__( 'Privacy Policy and Cookie Policy from your answers and your scan', 'wpconsent-cookies-banner-privacy-suite' ),
					esc_html__( 'We tell you when a law change needs a new version', 'wpconsent-cookies-banner-privacy-suite' ),
					esc_html__( 'Every version kept, and you can export any of them', 'wpconsent-cookies-banner-privacy-suite' ),
				),
				esc_html( $action['note'] )
			);
			?>
		</div>
		<?php
	}

	/**
	 * The card's action for the current state. Lite and Basic upgrade; an
	 * expired key renews and a missing or rejected key is activated, since
	 * those sites may already pay for Plus.
	 *
	 * @return array{text: string, url: string, note: string, attributes: array<string,string>}
	 */
	protected function get_locked_action() {
		$licenses_url = 'https://wpconsent.com/my-account/licenses/';

		switch ( $this->entitlement_state ) {
			case 'expired':
				$key    = 'renew';
				$action = array(
					'text' => __( 'Renew Your License', 'wpconsent-cookies-banner-privacy-suite' ),
					'url'  => wpconsent_utm_url( $licenses_url, 'legal-documents', 'renew' ),
					'note' => __( 'Your license has expired. Renew it to use Legal Documents on Plus and above.', 'wpconsent-cookies-banner-privacy-suite' ),
				);
				break;

			case 'inactive':
				$key    = 'activate';
				$action = array(
					'text' => __( 'Activate Your License', 'wpconsent-cookies-banner-privacy-suite' ),
					'url'  => wpconsent()->license->get_settings_url( is_multisite() && is_network_admin() ),
					'note' => __( 'Enter a valid license key to use Legal Documents on Plus and above.', 'wpconsent-cookies-banner-privacy-suite' ),
				);
				break;

			default:
				$key    = 'upgrade';
				$lite   = 'lite' === $this->entitlement_state;
				$action = array(
					'text' => __( 'Upgrade to Plus', 'wpconsent-cookies-banner-privacy-suite' ),
					'url'  => wpconsent_utm_url( $lite ? 'https://wpconsent.com/lite/' : $licenses_url, 'legal-documents', 'main' ),
					'note' => $lite
						? __( 'Included in Plus and above.', 'wpconsent-cookies-banner-privacy-suite' )
						: __( 'Plus adds Legal Documents, Automatic Translations and Do Not Sell.', 'wpconsent-cookies-banner-privacy-suite' ),
				);
		}

		// Activation stays in this admin; the other actions open wpconsent.com in a new tab.
		$action['attributes'] = 'activate' === $key ? array() : array( 'target' => '_blank' );

		$action['attributes']['data-wpconsent-upsell'] = WPConsent_Legal_Documents_Entitlement::UPSELL_SLUGS[ $key ];

		return $action;
	}

	/**
	 * Count the page view once per admin, so `legal.locked_shown` is the
	 * number of admins who saw the Plus card and reloads cost no writes.
	 *
	 * @return void
	 */
	protected function count_locked_view() {
		$user_id = get_current_user_id();

		// A user option is stored per site, like the counter it guards, so a network admin counts on each site.
		if ( ! $user_id || get_user_option( self::LOCKED_SEEN_META, $user_id ) ) {
			return;
		}

		if ( false !== WPConsent_Usage_Tracking::increment( 'legal.locked_shown' ) ) {
			update_user_option( $user_id, self::LOCKED_SEEN_META, 1 );
		}
	}

	/**
	 * The "Your Documents" table: the same card, columns and plain-text
	 * status as the Plus overview, with the Plus documents locked.
	 *
	 * @param array $action The card's action from get_locked_action(), shared by the upsell rows.
	 *
	 * @return string
	 */
	protected function get_documents_list( $action ) {
		$privacy_policy = $this->get_upsell_row(
			$action,
			'privacy_policy',
			__( 'Privacy Policy', 'wpconsent-cookies-banner-privacy-suite' ),
			__( 'Written from your answers and your site scan.', 'wpconsent-cookies-banner-privacy-suite' )
		);

		return '<div class="wpconsent-legal-table-scroll"><table class="wpconsent-legal-table wpconsent-legal-documents-table wpconsent-legal-documents-locked-table">'
			. '<thead><tr>'
			. '<th class="wpconsent-legal-col-document">' . esc_html__( 'Document', 'wpconsent-cookies-banner-privacy-suite' ) . '</th>'
			. '<th>' . esc_html__( 'Status', 'wpconsent-cookies-banner-privacy-suite' ) . '</th>'
			. '<th>' . esc_html__( 'Page', 'wpconsent-cookies-banner-privacy-suite' ) . '</th>'
			. '<th class="wpconsent-legal-col-actions">' . esc_html__( 'Actions', 'wpconsent-cookies-banner-privacy-suite' ) . '</th>'
			. '</tr></thead><tbody>'
			. $this->get_document_row( $this->get_cookie_policy_row() )
			. $this->get_document_row( $privacy_policy )
			. '</tbody></table></div>';
	}

	/**
	 * A document that Plus and above get: locked, with the card's action as
	 * a text link. Markup only, no feature code behind it.
	 *
	 * @param array  $action      The card's action from get_locked_action(), so row and card always agree.
	 * @param string $document    The document key.
	 * @param string $name        The document name.
	 * @param string $description One line under the name.
	 *
	 * @return array
	 */
	protected function get_upsell_row( $action, $document, $name, $description ) {
		return array(
			'document'    => $document,
			'name'        => $name,
			'description' => $description,
			'status'      => __( 'Included in Plus', 'wpconsent-cookies-banner-privacy-suite' ),
			'status_key'  => 'locked',
			'actions'     => array(
				$this->get_row_action(
					array(
						'text'       => esc_html( $action['text'] ),
						'url'        => $action['url'],
						'attributes' => $action['attributes'],
					)
				),
			),
		);
	}

	/**
	 * The free Cookie Policy page as a row: the chosen page with its status,
	 * title and icons, or not created with the generator.
	 *
	 * @return array
	 */
	protected function get_cookie_policy_row() {
		$page_id = (int) wpconsent()->settings->get_option( 'cookie_policy_page', 0 );
		$post    = $page_id > 0 ? get_post( $page_id ) : null;
		// A chosen page is the admin's choice whatever its status; only a missing or trashed page offers the generator.
		if ( ! $post instanceof WP_Post || 'trash' === $post->post_status ) {
			$post = null;
		}
		$page    = '';
		$actions = array();

		if ( $post ) {
			$page_url  = get_permalink( $post );
			// The raw title, as the Cookies page select shows it: get_the_title() would prefix "Private:" next to the Private badge.
			$page      = '<a href="' . esc_url( $page_url ) . '" target="_blank" rel="noopener">' . esc_html( $post->post_title ) . '</a>';
			// The Plus table icons: the pen edits the page (the free policy has no answers), the eye opens it.
			$edit_url = get_edit_post_link( $post->ID, 'raw' );
			if ( $edit_url ) {
				$actions[] = $this->icon_link( $edit_url, __( 'Edit page', 'wpconsent-cookies-banner-privacy-suite' ), wpconsent_get_icon( 'edit', 15, 16, '0 0 15 16' ) );
			}
			$actions[] = $this->icon_link( $page_url, __( 'View page', 'wpconsent-cookies-banner-privacy-suite' ), wpconsent_get_icon( 'visibility', 14, 10, '0 0 14 10' ), true );
		} else {
			// The free generator: the Cookies page's button and handler (src/admin/generate-cookie-policy.js), counted under its own source.
			$actions[] = $this->get_row_action(
				array(
					'tag'        => 'button',
					'text'       => wpconsent_get_icon( 'generate' ) . esc_html__( 'Generate Cookie Policy Page', 'wpconsent-cookies-banner-privacy-suite' ),
					'class'      => 'wpconsent-button wpconsent-button-secondary wpconsent-button-small wpconsent-button-icon wpconsent-generate-cookie-policy-page',
					'attributes' => array(
						'type'                  => 'button',
						'id'                    => 'wpconsent-legal-generate-cookie-policy',
						'data-source'           => 'legal_documents',
						// The button sits at the right edge of the table, so the spinner goes on its left.
						'data-spinner-position' => 'left',
					),
				)
			);
			// A site may already have a cookie policy page it never selected: point to it instead of generating a second one.
			$actions[] = $this->get_row_action(
				array(
					'text'  => esc_html__( 'Choose Existing Page', 'wpconsent-cookies-banner-privacy-suite' ),
					'class' => 'wpconsent-button wpconsent-button-secondary wpconsent-button-small',
					'url'   => $this->get_page_url( 'wpconsent-cookies' ) . '#cookie-policy-input',
				)
			);
		}

		return array(
			'document'    => 'cookie_policy',
			'name'        => __( 'Cookie Policy', 'wpconsent-cookies-banner-privacy-suite' ),
			'description' => __( 'Lists the cookies configured in WPConsent and stays in sync with them.', 'wpconsent-cookies-banner-privacy-suite' ),
			'page'        => $page,
			'actions'     => $actions,
		) + $this->get_page_status( $post );
	}

	/**
	 * One action of a row, built by the shared button helper so every action
	 * carries the same markup and escaping. A text link unless told otherwise.
	 *
	 * @param array $args Arguments for get_list_item_button(): text, url, and optionally tag, class, attributes.
	 *
	 * @return string
	 */
	protected function get_row_action( $args ) {
		return WPConsent_Admin_Page::get_list_item_button(
			$args + array(
				'tag'   => 'a',
				'class' => 'wpconsent-legal-link',
			),
			false
		);
	}

	/**
	 * The status for the chosen page: WordPress's own status name
	 * (Published, Draft, Pending, Private) so the admin sees why visitors
	 * cannot read it yet, with the Plus status key its colour follows.
	 *
	 * @param WP_Post|null $post The chosen page, or null when none is set.
	 *
	 * @return array{status: string, status_key: string}
	 */
	protected function get_page_status( $post ) {
		if ( ! $post ) {
			return array(
				'status'     => __( 'Not created', 'wpconsent-cookies-banner-privacy-suite' ),
				'status_key' => 'missing',
			);
		}

		$status = get_post_status_object( $post->post_status );

		return array(
			'status'     => $status && ! empty( $status->label ) ? $status->label : ucfirst( $post->post_status ),
			'status_key' => 'publish' === $post->post_status ? 'published' : 'draft',
		);
	}

	/**
	 * One row of the documents table, in the Plus overview's columns and
	 * plain-text status. A locked row carries a lock beside its name.
	 *
	 * @param array $row Row data: document, name, description, status, status_key, actions (html[]), and optionally page (html). A locked status puts a lock beside the name.
	 *
	 * @return string
	 */
	protected function get_document_row( $row ) {
		$row += array( 'page' => '' );

		$classes = 'wpconsent-legal-document wpconsent-legal-document--' . sanitize_html_class( $row['status_key'] );

		$html  = '<tr class="' . esc_attr( $classes ) . '" data-document="' . esc_attr( $row['document'] ) . '">';
		$html .= '<td class="wpconsent-legal-col-document">';
		$html .= '<span class="wpconsent-legal-document-name">' . esc_html( $row['name'] );
		if ( 'locked' === $row['status_key'] ) {
			$html .= wpconsent_get_icon( 'lock', 10, 13, '0 0 22 28' );
		}
		$html .= '</span>';
		if ( ! empty( $row['description'] ) ) {
			$html .= '<span class="wpconsent-legal-document-description">' . esc_html( $row['description'] ) . '</span>';
		}
		$html .= '</td>';
		$html .= '<td>' . $this->status_span( $row['status_key'], $row['status'] ) . '</td>';
		$html .= '<td class="wpconsent-legal-col-page' . ( empty( $row['page'] ) ? ' wpconsent-legal-muted' : '' ) . '">' . ( ! empty( $row['page'] ) ? wp_kses_post( $row['page'] ) : '&mdash;' ) . '</td>';
		$html .= '<td class="wpconsent-legal-col-actions"><div class="wpconsent-legal-table-actions">' . implode( '', $row['actions'] ) . '</div></td>'; // Actions are escaped where they are built.
		$html .= '</tr>';

		return $html;
	}

	/**
	 * An icon button link as the documents tables draw it, with its label as title and accessible name.
	 *
	 * @param string $url     Link target.
	 * @param string $label   Translated label.
	 * @param string $icon    Icon markup from wpconsent_get_icon().
	 * @param bool   $new_tab Open in a new tab.
	 *
	 * @return string
	 */
	protected function icon_link( $url, $label, $icon, $new_tab = false ) {
		return '<a class="wpconsent-legal-icon-button" href="' . esc_url( $url ) . '"' . ( $new_tab ? ' target="_blank" rel="noopener"' : '' ) . ' title="' . esc_attr( $label ) . '" aria-label="' . esc_attr( $label ) . '">' . $icon . '</a>';
	}

	/**
	 * Plain-text status, coloured by its key, as the plugin's own tables show it.
	 *
	 * @param string $key   Status key (underscores become dashes in the class).
	 * @param string $label Translated label.
	 *
	 * @return string
	 */
	protected function status_span( $key, $label ) {
		return '<span class="wpconsent-legal-status wpconsent-legal-status--' . esc_attr( str_replace( '_', '-', $key ) ) . '">' . esc_html( $label ) . '</span>';
	}

	/**
	 * A section card in the shape of the design's Metabox: title bar, body and
	 * an optional grey action footer. Same classes as metabox(), without the
	 * collapse toggle the design does without.
	 *
	 * @param string $title   Title.
	 * @param string $content Body markup.
	 * @param array  $args    id, class, actions (markup right of the title), footer (markup), flush (no body padding).
	 */
	protected function box( $title, $content, $args = array() ) {
		$args    = wp_parse_args(
			$args,
			array(
				'id'      => '',
				'class'   => '',
				'actions' => '',
				'footer'  => '',
				'flush'   => false,
			)
		);
		$classes = array( 'wpconsent-metabox', 'wpconsent-legal-box' );
		if ( $args['flush'] ) {
			$classes[] = 'wpconsent-legal-box--flush';
		}
		if ( $args['class'] ) {
			$classes[] = $args['class'];
		}
		?>
		<div class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>" <?php echo $args['id'] ? 'id="' . esc_attr( $args['id'] ) . '"' : ''; ?>>
			<?php if ( '' !== $title ) : ?>
				<div class="wpconsent-metabox-title">
					<div class="wpconsent-metabox-title-text"><?php echo wp_kses_post( $title ); ?></div>
					<?php if ( $args['actions'] ) : ?>
						<div class="wpconsent-metabox-title-actions"><?php echo $args['actions']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
					<?php endif; ?>
				</div>
			<?php endif; ?>
			<div class="wpconsent-metabox-content"><?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
			<?php if ( $args['footer'] ) : ?>
				<div class="wpconsent-legal-box-footer"><?php echo $args['footer']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Entitled licences before the engine ships: a short note, no feature yet.
	 *
	 * @return void
	 */
	protected function output_coming_soon() {
		$this->metabox(
			__( 'Legal Documents', 'wpconsent-cookies-banner-privacy-suite' ),
			'<p>' . esc_html__( 'Legal Documents is included in your plan and is on its way. Your Cookie Policy page settings stay under Cookies Configuration until then.', 'wpconsent-cookies-banner-privacy-suite' ) . '</p>'
		);
	}
}
