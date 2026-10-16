<?php
/**
 * Gravity Forms Add-On: TOPS / TowX.
 *
 * @package GF_Tops
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once GF_TOPS_PATH . 'includes/class-gf-tops-xml.php';
require_once GF_TOPS_PATH . 'includes/class-gf-tops-entry.php';
require_once GF_TOPS_PATH . 'includes/class-gf-tops-request-log.php';
require_once GF_TOPS_PATH . 'includes/class-gf-tops-secrets.php';
require_once GF_TOPS_PATH . 'includes/class-gf-tops-privacy.php';

/**
 * Class GF_Tops_Addon
 */
class GF_Tops_Addon extends GFAddOn {

	/**
	 * Addon version.
	 *
	 * @var string
	 */
	protected $_version = GF_TOPS_VERSION;

	/**
	 * Minimum GF version.
	 *
	 * @var string
	 */
	protected $_min_gravityforms_version = '2.5';

	/**
	 * @var string
	 */
	protected $_slug = 'gravityformstops';

	/**
	 * @var string
	 */
	protected $_path = '';

	/**
	 * @var string
	 */
	protected $_full_path = GF_TOPS_FILE;

	/**
	 * @var string
	 */
	protected $_title = 'Gravity Forms TOPS';

	/**
	 * @var string
	 */
	protected $_short_title = 'TOPS';

	/**
	 * Support contact shown on the Plugins screen and in documentation.
	 */
	const SUPPORT_EMAIL = 'support@duppinstech.com';

	/**
	 * Singleton.
	 *
	 * @var GF_Tops_Addon|null
	 */
	private static $_instance = null;

	/**
	 * Form IDs that already had TOPS front-end assets attached this request (avoid duplicate inline globals).
	 *
	 * @var array<int, bool>
	 */
	private static $tops_frontend_assets_attached = array();

	/**
	 * Form IDs seen in rendered HTML (`gform_wrapper_{id}`) after shortcodes/builders run.
	 *
	 * @var array<int, bool>
	 */
	private static $gform_wrapper_ids_seen = array();

	/**
	 * Whether Elementor `elementor/frontend/the_content` capture was registered (avoid duplicate filters).
	 *
	 * @var bool
	 */
	private static $elementor_frontend_capture_added = false;

	/**
	 * Get instance.
	 *
	 * @return GF_Tops_Addon
	 */
	public static function get_instance() {
		if ( null === self::$_instance ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->_path = plugin_basename( GF_TOPS_FILE );
		parent::__construct();
	}

	/**
	 * Register hooks.
	 */
	public function init() {
		parent::init();

		// TowX Create Call must run before confirmation is built (see gform_confirmation). after_submission fires too late.
		add_action( 'gform_pre_handle_confirmation', array( $this, 'pre_handle_confirmation_towx' ), 10, 2 );
		add_filter( 'gform_confirmation', array( $this, 'filter_confirmation' ), 20, 4 );

		add_filter( 'gform_pre_render', array( $this, 'pre_render_populate' ), 10, 1 );
		add_filter( 'gform_pre_validation', array( $this, 'pre_render_populate' ), 10, 1 );
		add_filter( 'gform_pre_submission_filter', array( $this, 'pre_render_populate' ), 10, 1 );
		add_filter( 'gform_admin_pre_render', array( $this, 'pre_render_populate' ), 10, 1 );

		add_action( 'gform_enqueue_scripts', array( $this, 'enqueue_frontend_scripts' ), 10, 2 );
		add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue_frontend_from_page_content' ), 99 );
		add_action( 'wp_footer', array( $this, 'maybe_enqueue_frontend_footer_fallback' ), 15 );
		add_action( 'wp_footer', array( $this, 'render_footer_diagnostic_comment' ), 9999 );

		add_filter( 'the_content', array( $this, 'capture_gform_wrapper_ids_from_html' ), 999 );
		add_filter( 'widget_text', array( $this, 'capture_gform_wrapper_ids_from_html' ), 999 );
		add_filter( 'widget_custom_html_content', array( $this, 'capture_gform_wrapper_ids_from_html' ), 999 );
		add_filter( 'render_block', array( $this, 'capture_gform_wrapper_ids_from_render_block' ), 999, 2 );

		add_action( 'plugins_loaded', array( $this, 'wire_elementor_capture_early' ), 30 );

		add_action( 'wp_ajax_gf_tops_models', array( $this, 'ajax_get_models' ) );
		add_action( 'wp_ajax_nopriv_gf_tops_models', array( $this, 'ajax_get_models' ) );

		add_action( 'wp_ajax_gf_tops_test_auth', array( $this, 'ajax_test_auth' ) );

		add_action( GF_Tops_Request_Log::PURGE_HOOK, array( $this, 'purge_request_log' ) );
		if ( function_exists( 'wp_next_scheduled' ) && ! wp_next_scheduled( GF_Tops_Request_Log::PURGE_HOOK ) ) {
			// First run a day after activation or upgrade, so admins can change the retention period before anything is deleted.
			wp_schedule_event( time() + DAY_IN_SECONDS, 'daily', GF_Tops_Request_Log::PURGE_HOOK );
		}

		GF_Tops_Privacy::register_hooks();

		if ( is_admin() ) {
			add_filter( 'plugin_action_links_' . plugin_basename( GF_TOPS_FILE ), array( $this, 'add_plugin_action_links' ) );
			add_filter( 'plugin_row_meta', array( $this, 'add_plugin_row_meta' ), 10, 2 );
			add_action( 'admin_menu', array( $this, 'register_request_log_menu' ), 60 );
			add_action( 'admin_post_gf_tops_resend_create_call', array( $this, 'handle_resend_create_call_post' ) );
		}
	}

	/**
	 * "Settings" link on the Plugins screen.
	 *
	 * @param array $links Action links.
	 * @return array
	 */
	public function add_plugin_action_links( $links ) {
		$url = admin_url( 'admin.php?page=gf_settings&subview=' . $this->get_slug() );
		array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'gravity-forms-tops' ) . '</a>' );
		return $links;
	}

	/**
	 * "Support" link under the plugin description on the Plugins screen.
	 *
	 * @param array  $links Row meta links.
	 * @param string $file  Plugin basename.
	 * @return array
	 */
	public function add_plugin_row_meta( $links, $file ) {
		if ( plugin_basename( GF_TOPS_FILE ) === $file ) {
			$links[] = '<a href="' . esc_url( 'mailto:' . self::SUPPORT_EMAIL ) . '">' . esc_html__( 'Support', 'gravity-forms-tops' ) . '</a>';
		}
		return $links;
	}

	/**
	 * Days to keep request-log rows. 0 keeps them forever.
	 *
	 * @return int
	 */
	public function get_request_log_retention_days() {
		$plugin = $this->get_plugin_settings();
		$value  = is_array( $plugin ) && isset( $plugin['request_log_retention_days'] ) ? trim( (string) $plugin['request_log_retention_days'] ) : '';
		// Only a plain whole number counts; anything else ("90 days", "-1") uses the default rather than
		// silently meaning "keep forever" (0) or "one day".
		$days = preg_match( '/^\d+$/', $value ) ? (int) $value : GF_Tops_Request_Log::DEFAULT_RETENTION_DAYS;

		/**
		 * Days to keep TOPS request-log rows before the daily cleanup deletes them. 0 keeps them forever.
		 *
		 * @param int $days Default from Forms → Settings → TOPS (90 when not set).
		 */
		// Capped at 100 years so the cutoff date can always be calculated.
		return min( 36500, max( 0, (int) apply_filters( 'gf_tops_request_log_retention_days', $days ) ) );
	}

	/**
	 * Daily WP-Cron task: delete request-log rows older than the retention period.
	 *
	 * @return int Rows deleted.
	 */
	public function purge_request_log() {
		$days = $this->get_request_log_retention_days();
		if ( $days <= 0 ) {
			return 0;
		}
		$deleted = GF_Tops_Request_Log::purge_older_than( $days );
		if ( $deleted > 0 ) {
			$this->log( sprintf( 'Request log cleanup removed %d row(s) older than %d day(s).', $deleted, $days ) );
		}
		return $deleted;
	}

	/**
	 * Admin + form settings assets.
	 *
	 * @return array
	 */
	public function scripts() {
		$scripts   = parent::scripts();
		$js_path   = GF_TOPS_PATH . 'assets/js/admin-form-settings.js';
		$scripts[] = array(
			'handle'   => 'gf-tops-admin-form-settings',
			'src'      => GF_TOPS_URL . 'assets/js/admin-form-settings.js',
			'version'  => $this->_version . '.' . (string) ( file_exists( $js_path ) ? filemtime( $js_path ) : 0 ),
			'deps'     => array( 'jquery' ),
			'enqueue'  => array(
				array(
					'admin_page' => array( 'form_settings' ),
					'tab'        => $this->get_slug(),
				),
			),
			'callback' => array( $this, 'localize_admin_form_settings_script' ),
		);

		return $scripts;
	}

	/**
	 * @return array
	 */
	public function styles() {
		$styles   = parent::styles();
		$css_path = GF_TOPS_PATH . 'assets/css/admin-form-settings.css';
		$styles[] = array(
			'handle'  => 'gf-tops-admin-form-settings',
			'src'     => GF_TOPS_URL . 'assets/css/admin-form-settings.css',
			'version' => $this->_version . '.' . (string) ( file_exists( $css_path ) ? filemtime( $css_path ) : 0 ),
			'enqueue' => array(
				array(
					'admin_page' => array( 'form_settings' ),
					'tab'        => $this->get_slug(),
				),
			),
		);

		return $styles;
	}

	/**
	 * Localize strings for form settings UI (auth test).
	 *
	 * @param array $form    Current form.
	 * @param bool  $is_ajax Whether loaded via AJAX.
	 */
	public function localize_admin_form_settings_script( $form, $is_ajax = false ) {
		wp_localize_script(
			'gf-tops-admin-form-settings',
			'gfTopsAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'gf_tops_test_auth' ),
				'formId'  => isset( $form['id'] ) ? (int) $form['id'] : 0,
				'i18n'    => array(
					'testing'     => __( 'Testing…', 'gravity-forms-tops' ),
					'testOk'      => __( 'Connected. TowX returned vehicle makes.', 'gravity-forms-tops' ),
					'testFail'    => __( 'Connection failed.', 'gravity-forms-tops' ),
					'saveNotFound' => __( 'Could not find the Save control. Scroll to the bottom of this screen and use the main Save settings button.', 'gravity-forms-tops' ),
					'noResponse'   => __( 'No response body (HTTP 0). Often a network drop, timeout, or admin-ajax blocked. Check the browser Network tab for admin-ajax.php.', 'gravity-forms-tops' ),
				),
			)
		);
	}

	/**
	 * Never echo saved secrets into settings inputs (password fields stay empty until replaced).
	 *
	 * @param string       $setting_name  Setting key.
	 * @param string|array $default_value Default.
	 * @param array|bool   $settings      Optional settings array.
	 * @return mixed
	 */
	public function get_setting( $setting_name, $default_value = '', $settings = false ) {
		$value = parent::get_setting( $setting_name, $default_value, $settings );
		if ( in_array( $setting_name, GF_Tops_Secrets::secret_field_names(), true ) && $value !== '' && $value !== null ) {
			return '';
		}
		return $value;
	}

	/**
	 * GFAddOn expects a form array; many TOPS helpers pass a numeric form ID. Resolve IDs so settings load correctly.
	 *
	 * Secrets are returned exactly as stored (encrypted). Gravity Forms also uses this method for the settings
	 * screen's initial values, so it must never return plaintext credentials. Use get_runtime_form_settings()
	 * when calling TowX.
	 *
	 * @param array|int|string $form Form array or form ID.
	 * @return array
	 */
	public function get_form_settings( $form ) {
		if ( is_numeric( $form ) ) {
			$form_id = absint( $form );
			if ( $form_id <= 0 ) {
				return array();
			}
			if ( ! class_exists( 'GFAPI' ) ) {
				return array();
			}
			$form_array = GFAPI::get_form( $form_id );
			if ( ! $form_array || is_wp_error( $form_array ) ) {
				return array();
			}
			$form = $form_array;
		}

		$settings = parent::get_form_settings( $form );
		if ( ! is_array( $settings ) ) {
			return array();
		}

		return $settings;
	}

	/**
	 * Form settings with TowX secrets decrypted, for building API requests only. Never render the result.
	 *
	 * @param array|int|string $form Form array or form ID.
	 * @return array
	 */
	protected function get_runtime_form_settings( $form ) {
		return GF_Tops_Secrets::decrypt_settings( $this->get_form_settings( $form ) );
	}

	/**
	 * Encrypt TowX secrets before persisting to form meta (lazy migration from plaintext on save).
	 *
	 * @param array $form     Form array.
	 * @param array $settings Settings passed to GFAPI/update_form_meta.
	 * @return bool
	 */
	public function save_form_settings( $form, $settings ) {
		if ( is_array( $settings ) ) {
			$this->maybe_forget_connection_test( $form, $settings );
			$settings = GF_Tops_Secrets::encrypt_settings( $settings );
		}

		return parent::save_form_settings( $form, $settings );
	}

	/**
	 * Clear the "connection test passed" record for a form when its TowX credentials change.
	 *
	 * @param array $form     Form array.
	 * @param array $settings Settings about to be saved (secrets may be plaintext or stored ciphertext).
	 */
	protected function maybe_forget_connection_test( $form, array $settings ) {
		$form_id = (int) rgar( $form, 'id' );
		$tests   = get_option( 'gf_tops_auth_test_ok', array() );
		if ( $form_id <= 0 || ! is_array( $tests ) || ! isset( $tests[ $form_id ] ) ) {
			return;
		}
		$old = GF_Tops_Secrets::decrypt_settings( (array) parent::get_form_settings( $form ) );
		$new = GF_Tops_Secrets::decrypt_settings( $settings );
		foreach ( array( 'tops_user_id', 'tops_password', 'tops_session_id', 'tops_auth_key' ) as $key ) {
			if ( (string) rgar( $old, $key ) !== (string) rgar( $new, $key ) ) {
				unset( $tests[ $form_id ] );
				update_option( 'gf_tops_auth_test_ok', $tests, false );
				return;
			}
		}
	}

	/**
	 * Runs when the stored add-on version changes: create the log table and encrypt any plaintext secrets
	 * left by releases before 1.3.5 so they do not wait for each form to be saved by hand.
	 *
	 * @param string $previous_version Previously installed version (empty on first install).
	 */
	public function upgrade( $previous_version ) {
		parent::upgrade( $previous_version );

		GF_Tops_Request_Log::maybe_install();
		$this->migrate_plaintext_secrets();
	}

	/**
	 * Encrypt plaintext TowX secrets in every form's TOPS settings.
	 *
	 * @return int Number of forms updated.
	 */
	protected function migrate_plaintext_secrets() {
		if ( ! class_exists( 'GFAPI' ) || ! GF_Tops_Secrets::encryption_enabled() ) {
			return 0;
		}

		$updated = 0;
		// Active, inactive, and trashed forms (a restored form must not bring plaintext secrets back).
		$forms = array();
		foreach ( array( true, false ) as $is_active ) {
			foreach ( array( false, true ) as $in_trash ) {
				$batch = GFAPI::get_forms( $is_active, $in_trash );
				if ( is_array( $batch ) ) {
					$forms = array_merge( $forms, $batch );
				}
			}
		}
		foreach ( $forms as $form ) {
			$stored = $this->get_form_settings( $form );
			if ( empty( $stored ) || ! GF_Tops_Secrets::settings_need_migration( $stored ) ) {
				continue;
			}
			if ( $this->save_form_settings( $form, $stored ) ) {
				++$updated;
			}
		}

		if ( $updated > 0 ) {
			$this->log( sprintf( 'Encrypted stored TowX secrets for %d form(s).', $updated ) );
		}

		return $updated;
	}

	/**
	 * Preserve stored secrets when the password fields are left blank on save.
	 *
	 * @param array  $field Field definition.
	 * @param string $value Posted value.
	 * @return string
	 */
	public function save_secret_merge_callback( $field, $value ) {
		if ( null !== $value && '' !== $value ) {
			return $value;
		}
		$form = $this->get_current_form();
		if ( ! is_array( $form ) ) {
			return (string) $value;
		}
		$saved = rgar( $form, $this->_slug );
		// GF 2.5+ passes a settings field object; older renderers pass an array.
		$name = is_object( $field ) && isset( $field->name ) ? (string) $field->name : (string) rgar( $field, 'name' );
		if ( is_array( $saved ) && ! empty( $saved[ $name ] ) ) {
			return $saved[ $name ];
		}
		return (string) $value;
	}

	/**
	 * Count choices on a mapped Gravity Forms select (after pre_render filters).
	 *
	 * @param array      $form     Form.
	 * @param string|int $field_id Field ID.
	 * @return int|null Choice count, 0 if select but empty, null if field missing / not a select.
	 */
	protected function count_select_field_choices( $form, $field_id ) {
		if ( $field_id === '' || $field_id === null || ! isset( $form['fields'] ) || ! is_array( $form['fields'] ) ) {
			return null;
		}
		$target = (string) $field_id;
		foreach ( $form['fields'] as $field ) {
			if ( ! is_object( $field ) ) {
				continue;
			}
			if ( (string) $field->id !== $target ) {
				continue;
			}
			if ( 'select' !== $field->type ) {
				return 0;
			}
			if ( empty( $field->choices ) || ! is_array( $field->choices ) ) {
				return 0;
			}
			return count( $field->choices );
		}
		return null;
	}

	/**
	 * TowX field map: GF 2.5+ stores field_map rows as flat keys (tops_fields_make_key, …), not nested tops_fields[].
	 *
	 * @param array $settings Add-on form settings (from get_form_settings).
	 * @return array<string, mixed> Logical keys (make_key, location, …) → mapped GF field id or value.
	 */
	protected function get_tops_field_map( $settings ) {
		if ( ! is_array( $settings ) ) {
			return array();
		}

		$prefix = 'tops_fields_';
		$flat   = array();
		foreach ( $settings as $key => $value ) {
			if ( ! is_string( $key ) || strpos( $key, $prefix ) !== 0 ) {
				continue;
			}
			$name = substr( $key, strlen( $prefix ) );
			if ( $name !== '' ) {
				$flat[ $name ] = $value;
			}
		}
		if ( ! empty( $flat ) ) {
			return $flat;
		}

		$nested = isset( $settings['tops_fields'] ) ? $settings['tops_fields'] : null;
		if ( ! is_array( $nested ) ) {
			return array();
		}

		// Older Settings API shape: a list of rows, each with a "key" (logical name) and "value" (GF field ID).
		if ( isset( $nested[0] ) && is_array( $nested[0] ) && isset( $nested[0]['key'] ) ) {
			$out = array();
			foreach ( $nested as $row ) {
				if ( is_array( $row ) && ! empty( $row['key'] ) ) {
					$out[ (string) $row['key'] ] = isset( $row['value'] ) ? $row['value'] : '';
				}
			}
			return $out;
		}

		return $nested;
	}

	/**
	 * Human-readable JSON API environment for front-end status.
	 *
	 * @return string
	 */
	protected function get_api_environment_label() {
		$plugin = $this->get_plugin_settings();
		$env    = rgar( $plugin, 'api_environment', 'production' );

		if ( 'qa' === $env ) {
			return esc_html__( 'QA (apiqa.towxchange.net)', 'gravity-forms-tops' );
		}
		if ( 'custom' === $env ) {
			$url = (string) rgar( $plugin, 'custom_api_base' );
			return $url !== '' ? esc_html( $url ) : esc_html__( 'Custom base URL', 'gravity-forms-tops' );
		}

		return esc_html__( 'Production (api.towxchange.net)', 'gravity-forms-tops' );
	}

	/**
	 * Front-end browser console logging: Forms → Settings → TOPS (global), and/or Form Settings → TOPS cascade diagnostics.
	 *
	 * @param array|null $form_settings Optional Gravity Forms add-on settings for the current form.
	 * @return bool
	 */
	protected function is_browser_console_logging_enabled( $form_settings = null ) {
		$plugin = $this->get_plugin_settings();
		if ( ! empty( $plugin['browser_console_logging'] ) ) {
			return true;
		}
		if ( is_array( $form_settings ) && ! empty( $form_settings['gf_tops_cascade_debug'] ) ) {
			return true;
		}
		return false;
	}

	/**
	 * Make, Model, and Color are all mapped in the field map (admin checklist).
	 *
	 * @param array $settings Add-on form settings.
	 * @return bool
	 */
	protected function field_map_has_vehicle_mmc( $settings ) {
		$map = $this->get_tops_field_map( $settings );
		if ( empty( $map ) ) {
			return false;
		}
		foreach ( array( 'make_key', 'model_key', 'color_key' ) as $key ) {
			$v = rgar( $map, $key );
			if ( $v === '' || $v === null ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Load models via AJAX when Make changes (requires mapped Make + Model selects).
	 *
	 * @param array $map Normalized tops_fields map.
	 * @return bool
	 */
	protected function should_enable_model_cascade( array $map ) {
		$make  = rgar( $map, 'make_key' );
		$model = rgar( $map, 'model_key' );
		return $make !== '' && $make !== null && $model !== '' && $model !== null;
	}

	/**
	 * Admin-only copy for the form settings tab (never shown on the front end).
	 *
	 * @return string
	 */
	protected function get_tops_form_settings_intro_html() {
		return '<div class="gf-tops-settings-explainer" style="max-width:720px;"><p class="description" style="margin-top:0;">'
			. esc_html__(
				'When this integration is enabled, the plugin talks to the TowX (TOPSLink) API using your credentials below. TowX is the system of record for vehicle make, model, and color lists.',
				'gravity-forms-tops'
			)
			. '</p><ul class="ul-disc" style="margin-left:1.25em;"><li>'
			. esc_html__(
				'Makes and colors are loaded into your mapped dropdown selects from TowX (live lists — choices update when TowX data changes).',
				'gravity-forms-tops'
			)
			. '</li><li>'
			. esc_html__(
				'When both Make and Model are mapped to select fields, choosing a make triggers GetModelsForMake so the model dropdown stays in sync.',
				'gravity-forms-tops'
			)
			. '</li><li>'
			. esc_html__(
				'Map Make, Model, and Color in the field map to visible TowX dropdowns; option values must be TowX keys (the plugin populates them for you).',
				'gravity-forms-tops'
			)
			. '</li></ul><p class="description">'
			. esc_html__(
				'Nothing in this section appears on the public form — it is only for editors configuring the integration.',
				'gravity-forms-tops'
			)
			. '</p></div>';
	}

	/**
	 * Field-map keys that must be mapped before Create Call can send a complete request.
	 *
	 * @return string[]
	 */
	protected function get_required_field_map_keys() {
		$keys = array();
		foreach ( $this->get_field_map_definition() as $row ) {
			if ( ! empty( $row['required'] ) ) {
				$keys[] = (string) $row['name'];
			}
		}
		return $keys;
	}

	/**
	 * Setup checklist items for a form.
	 *
	 * @param array $form Form array.
	 * @return array<int, array{ok: bool, warn: bool, text: string}>
	 */
	protected function get_setup_checklist( $form ) {
		$settings = $this->get_form_settings( $form );
		$form_id  = (int) rgar( $form, 'id' );
		$map      = $this->get_tops_field_map( $settings );
		$items    = array();

		$has_credentials = true;
		foreach ( array( 'tops_user_id', 'tops_password', 'tops_session_id', 'tops_auth_key' ) as $key ) {
			if ( '' === (string) rgar( $settings, $key ) ) {
				$has_credentials = false;
			}
		}
		$items[] = array(
			'ok'   => $has_credentials,
			'warn' => false,
			'text' => $has_credentials
				? __( 'TowX credentials are saved.', 'gravity-forms-tops' )
				: __( 'Enter and save all four TowX credentials (User ID, Password, Session ID, Authentication key).', 'gravity-forms-tops' ),
		);

		$tests  = get_option( 'gf_tops_auth_test_ok', array() );
		$tested = is_array( $tests ) && ! empty( $tests[ $form_id ] ) ? (int) $tests[ $form_id ] : 0;
		$items[] = array(
			'ok'   => $tested > 0,
			'warn' => false,
			'text' => $tested > 0
				? sprintf(
					/* translators: %s: date and time of the last successful connection test */
					__( 'Connection test passed (%s).', 'gravity-forms-tops' ),
					wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $tested )
				)
				: __( 'Run "Test authentication" below after saving credentials.', 'gravity-forms-tops' ),
		);

		$missing = array();
		foreach ( $this->get_required_field_map_keys() as $key ) {
			$value = rgar( $map, $key );
			if ( '' === $value || null === $value ) {
				$missing[] = $key;
			}
		}
		$items[] = array(
			'ok'   => empty( $missing ),
			'warn' => false,
			'text' => empty( $missing )
				? __( 'All required TowX fields are mapped (Make, Model and Color dropdowns are filled from TowX).', 'gravity-forms-tops' )
				: sprintf(
					/* translators: %d: number of required field-map rows still empty */
					_n( '%d required field-map row is empty. Map it under "Field map" below.', '%d required field-map rows are empty. Map them under "Field map" below.', count( $missing ), 'gravity-forms-tops' ),
					count( $missing )
				),
		);

		$has_contact = '' !== $this->get_form_confirmation_contact_html( $settings );
		$items[]     = array(
			'ok'   => $has_contact,
			'warn' => ! $has_contact,
			'text' => $has_contact
				? __( 'A contact line is set for visitors when TowX cannot take the request.', 'gravity-forms-tops' )
				: __( 'No contact line is set. If TowX fails, visitors are asked to contact you but are not shown a phone number or link. Add one under "TowX confirmation text" below or in Forms → Settings → TOPS.', 'gravity-forms-tops' ),
		);

		$plugin = $this->get_plugin_settings();
		$env    = rgar( $plugin, 'api_environment', 'production' );
		$items[] = array(
			'ok'   => true,
			'warn' => false,
			'text' => sprintf(
				/* translators: %s: API environment label, e.g. "Production (api.towxchange.net)" */
				__( 'API environment: %s. In Production, every test submission creates a real tow call.', 'gravity-forms-tops' ),
				wp_strip_all_tags( $this->get_api_environment_label() )
			),
		);

		$failure = $has_credentials ? $this->get_last_lookup_failure( $this->get_runtime_form_settings( $form ) ) : null;
		if ( is_array( $failure ) ) {
			$items[] = array(
				'ok'   => false,
				'warn' => true,
				'text' => sprintf(
					/* translators: 1: TowX request name, 2: date and time, 3: error detail */
					__( 'Last TowX lookup failed: %1$s at %2$s — %3$s. Dropdowns fall back to the choices saved in the form until TowX answers again.', 'gravity-forms-tops' ),
					(string) $failure['verb'],
					wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $failure['time'] ),
					(string) $failure['message']
				),
			);
		}

		return $items;
	}

	/**
	 * Setup checklist shown on the form's TOPS settings tab once the integration is enabled.
	 *
	 * @param array $form Form array.
	 * @return string
	 */
	protected function get_tops_mmc_mapping_notice_html( $form ) {
		$settings = $this->get_form_settings( $form );
		if ( empty( $settings['enabled'] ) ) {
			return '<p class="description" style="max-width:720px;">' . esc_html__( 'Tick "Enable integration" and save to see the setup checklist for this form.', 'gravity-forms-tops' ) . '</p>';
		}

		$html = '<div class="gf-tops-setup-checklist" style="max-width:720px;margin:0.75em 0 0;"><p style="margin:0 0 0.25em;"><strong>'
			. esc_html__( 'Setup checklist', 'gravity-forms-tops' ) . '</strong></p><ul style="margin:0;list-style:none;">';
		foreach ( $this->get_setup_checklist( $form ) as $item ) {
			if ( $item['warn'] ) {
				$mark  = '!';
				$label = __( 'Attention:', 'gravity-forms-tops' );
				$color = '#996800';
			} elseif ( $item['ok'] ) {
				$mark  = '✓';
				$label = __( 'Done:', 'gravity-forms-tops' );
				$color = '#00a32a';
			} else {
				$mark  = '✗';
				$label = __( 'To do:', 'gravity-forms-tops' );
				$color = '#d63638';
			}
			$html .= '<li style="margin:0.2em 0;"><span aria-hidden="true" style="display:inline-block;width:1.2em;font-weight:700;color:' . esc_attr( $color ) . ';">' . esc_html( $mark ) . '</span>'
				. '<span class="screen-reader-text">' . esc_html( $label ) . ' </span>' . esc_html( $item['text'] ) . '</li>';
		}
		return $html . '</ul></div>';
	}

	/**
	 * Fallback: many themes/builders still output the [gravityforms] shortcode but never trigger
	 * `gform_enqueue_scripts` on the front end. Discover form IDs from post content and enqueue TOPS assets early.
	 *
	 * Uses GFAPI::get_form without running filters again (avoids duplicate TowX GetMakes/GetColors); the
	 * status banner recounts makes/colors from the DOM after render.
	 *
	 * @return void
	 */
	public function maybe_enqueue_frontend_from_page_content() {
		if ( is_admin() || ! class_exists( 'GFAPI' ) ) {
			return;
		}

		$this->enqueue_tops_assets_for_discovered_forms();
	}

	/**
	 * Late enqueue: global $post is sometimes unset during `wp_enqueue_scripts`; Elementor stores the form only in `_elementor_data`.
	 *
	 * @return void
	 */
	public function maybe_enqueue_frontend_footer_fallback() {
		if ( is_admin() || ! class_exists( 'GFAPI' ) ) {
			return;
		}
		if ( wp_script_is( 'gf-tops-frontend', 'enqueued' ) ) {
			return;
		}
		$this->enqueue_tops_assets_for_discovered_forms();
	}

	/**
	 * Load GF forms found on this singular URL and attach TOPS assets (deduped per request).
	 *
	 * @return void
	 */
	protected function enqueue_tops_assets_for_discovered_forms() {
		foreach ( $this->get_merged_discovered_form_ids() as $form_id ) {
			$form = GFAPI::get_form( $form_id );
			if ( ! $form || is_wp_error( $form ) ) {
				continue;
			}
			$this->attach_frontend_assets_for_form( $form );
		}
	}

	/**
	 * Collect every Gravity Forms ID we can infer (post meta, Elementor JSON, rendered `gform_wrapper_*`).
	 *
	 * @return int[]
	 */
	protected function get_merged_discovered_form_ids() {
		$from_meta = $this->discover_gravity_form_ids_on_singular();
		$from_html = array_keys( self::$gform_wrapper_ids_seen );
		$ids       = array_unique( array_merge( $from_meta, array_map( 'absint', $from_html ) ) );
		return array_values( array_filter( $ids ) );
	}

	/**
	 * Remember form IDs from rendered markup (Elementor, widgets, etc. often omit shortcodes from raw post_content).
	 *
	 * @param string $html Content HTML.
	 * @return string Unchanged.
	 */
	public function capture_gform_wrapper_ids_from_html( $html ) {
		if ( ! is_string( $html ) || $html === '' ) {
			return $html;
		}
		if ( preg_match_all( '/\bgform_wrapper_(\d+)\b/', $html, $m ) ) {
			foreach ( $m[1] as $id ) {
				self::$gform_wrapper_ids_seen[ (int) $id ] = true;
			}
		}
		return $html;
	}

	/**
	 * @param string $block_content Rendered block HTML.
	 * @param array  $block         Block array.
	 * @return string
	 */
	public function capture_gform_wrapper_ids_from_render_block( $block_content, $block ) {
		return $this->capture_gform_wrapper_ids_from_html( $block_content );
	}

	/**
	 * Register Elementor capture as soon as Elementor is loaded (`wp` was too late on some stacks).
	 *
	 * @return void
	 */
	public function wire_elementor_capture_early() {
		if ( ! class_exists( '\Elementor\Plugin' ) ) {
			return;
		}
		if ( did_action( 'elementor/loaded' ) ) {
			$this->maybe_hook_elementor_content_capture();
		} else {
			add_action( 'elementor/loaded', array( $this, 'maybe_hook_elementor_content_capture' ) );
		}
	}

	/**
	 * Elementor filters built HTML separately from core `the_content`; capture `gform_wrapper_*` there.
	 *
	 * @return void
	 */
	public function maybe_hook_elementor_content_capture() {
		if ( self::$elementor_frontend_capture_added ) {
			return;
		}
		if ( ! class_exists( '\Elementor\Plugin' ) ) {
			return;
		}
		add_filter( 'elementor/frontend/the_content', array( $this, 'capture_gform_wrapper_ids_from_html' ), 999 );
		self::$elementor_frontend_capture_added = true;
	}

	/**
	 * HTML comment for debugging: proves plugin ran, lists discovered form IDs, integration on/off, script enqueued.
	 * View page source and search for "GF TOPS".
	 *
	 * @return void
	 */
	public function render_footer_diagnostic_comment() {
		if ( is_admin() ) {
			return;
		}
		// Public page source should not fingerprint the plugin version or form IDs unless diagnostics are on.
		$show = $this->is_browser_console_logging_enabled() || ( defined( 'WP_DEBUG' ) && WP_DEBUG );

		/**
		 * Whether to print the `<!-- GF TOPS … -->` diagnostic comment in the page footer.
		 *
		 * @param bool $show Default: on when browser console logging or WP_DEBUG is enabled.
		 */
		if ( ! apply_filters( 'gf_tops_show_footer_diagnostic_comment', $show ) ) {
			return;
		}
		$merged = $this->get_merged_discovered_form_ids();
		$parts  = array();
		foreach ( $merged as $fid ) {
			$parts[] = $fid . ':' . ( $this->is_form_enabled( $fid ) ? 'on' : 'off' );
		}
		$form_summary = ! empty( $parts ) ? implode( '|', $parts ) : 'none';
		$enq          = wp_script_is( 'gf-tops-frontend', 'enqueued' ) ? 'yes' : 'no';
		echo "\n<!-- GF TOPS " . esc_html( GF_TOPS_VERSION ) . ' forms=' . esc_html( $form_summary ) . ' gf-tops-frontend=' . esc_html( $enq ) . " -->\n";
	}

	/**
	 * Gravity Form IDs on the current singular page (post content, blocks, Elementor JSON).
	 *
	 * Uses `get_queried_object_id()` so discovery works when `global $post` is not set yet during `wp_enqueue_scripts`.
	 *
	 * @return int[]
	 */
	protected function discover_gravity_form_ids_on_singular() {
		if ( ! is_singular() ) {
			return array();
		}

		$post_id = (int) get_queried_object_id();
		if ( $post_id <= 0 ) {
			return array();
		}

		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			return array();
		}

		$ids = $this->extract_gravity_form_ids_from_content( $post->post_content );

		$elementor = get_post_meta( $post_id, '_elementor_data', true );
		if ( is_string( $elementor ) && $elementor !== '' ) {
			$ids = array_merge( $ids, $this->extract_gravity_form_ids_from_elementor_json( $elementor ) );
		}

		$ids = array_unique( array_filter( array_map( 'absint', $ids ) ) );
		$ids = array_values( $ids );

		/**
		 * Extra Gravity Forms form IDs on this page when the form is embedded outside post_content
		 * (e.g. page builder meta). Return integer IDs.
		 *
		 * @param int[] $ids     Discovered IDs from shortcodes/blocks/Elementor.
		 * @param int   $post_id Current post ID.
		 */
		return apply_filters( 'gf_tops_discover_form_ids', $ids, $post_id );
	}

	/**
	 * Find Gravity Form IDs inside Elementor’s `_elementor_data` JSON (shortcodes and widget settings).
	 *
	 * @param string $json Raw Elementor data.
	 * @return int[]
	 */
	protected function extract_gravity_form_ids_from_elementor_json( $json ) {
		$ids = array();
		if ( ! is_string( $json ) || $json === '' ) {
			return $ids;
		}

		$patterns = array(
			'/\[gravityforms?\s+[^\]]*\bid\s*=\s*[\'"]?(\d+)/i',
			'/"form_id"\s*:\s*"(\d+)"/',
			'/"form_id"\s*:\s*(\d+)/',
			'/"formId"\s*:\s*"?(\d+)/',
		);
		foreach ( $patterns as $pattern ) {
			if ( preg_match_all( $pattern, $json, $m ) ) {
				foreach ( $m[1] as $id ) {
					$ids[] = (int) $id;
				}
			}
		}

		$ids = array_unique( array_filter( array_map( 'absint', $ids ) ) );
		return array_values( $ids );
	}

	/**
	 * @param string $content Post content.
	 * @return int[]
	 */
	protected function extract_gravity_form_ids_from_content( $content ) {
		$ids = array();
		if ( ! is_string( $content ) || $content === '' ) {
			return $ids;
		}

		if ( function_exists( 'has_blocks' ) && has_blocks( $content ) ) {
			$parsed = parse_blocks( $content );
			if ( is_array( $parsed ) ) {
				$ids = array_merge( $ids, $this->extract_gravity_form_ids_from_blocks( $parsed ) );
			}
		}

		if ( preg_match_all( '/\[gravityforms?\s+[^\]]*\bid\s*=\s*[\'"]?(\d+)/i', $content, $matches ) ) {
			foreach ( $matches[1] as $id ) {
				$ids[] = (int) $id;
			}
		}

		$ids = array_unique( array_filter( array_map( 'absint', $ids ) ) );
		return array_values( $ids );
	}

	/**
	 * @param array $blocks Parsed blocks (recursive).
	 * @return int[]
	 */
	protected function extract_gravity_form_ids_from_blocks( array $blocks ) {
		$ids = array();
		foreach ( $blocks as $block ) {
			$name = isset( $block['blockName'] ) ? (string) $block['blockName'] : '';
			if ( $name !== '' && false !== strpos( $name, 'gravityforms' ) ) {
				if ( ! empty( $block['attrs']['formId'] ) ) {
					$ids[] = (int) $block['attrs']['formId'];
				}
			}
			if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
				$ids = array_merge( $ids, $this->extract_gravity_form_ids_from_blocks( $block['innerBlocks'] ) );
			}
		}
		return $ids;
	}

	/**
	 * Gravity Forms calls `gform_enqueue_scripts` when a form is rendered — attach TOPS there.
	 *
	 * @param array $form Form.
	 * @param bool  $ajax Whether AJAX context.
	 */
	public function enqueue_frontend_scripts( $form, $ajax = false ) {
		if ( ! is_array( $form ) || empty( $form['id'] ) ) {
			return;
		}
		$this->attach_frontend_assets_for_form( $form );
	}

	/**
	 * Register gf-tops-frontend + inline config once per form per request.
	 *
	 * @param array $form Form (should match GF render when coming from gform_enqueue_scripts).
	 * @return void
	 */
	protected function attach_frontend_assets_for_form( $form ) {
		if ( ! is_array( $form ) || empty( $form['id'] ) ) {
			return;
		}

		$form_id = (int) $form['id'];
		if ( isset( self::$tops_frontend_assets_attached[ $form_id ] ) ) {
			return;
		}

		if ( ! $this->is_form_enabled( $form_id ) ) {
			return;
		}

		self::$tops_frontend_assets_attached[ $form_id ] = true;

		$settings = $this->get_form_settings( $form );
		$map      = $this->get_tops_field_map( $settings );

		$make_field     = rgar( $map, 'make_key' );
		$model_field    = rgar( $map, 'model_key' );
		$color_field    = rgar( $map, 'color_key' );
		$makes_count    = $this->count_select_field_choices( $form, $make_field );
		$colors_count   = $this->count_select_field_choices( $form, $color_field );

		$cascade_on = $this->should_enable_model_cascade( $map );
		if ( $cascade_on ) {
			$this->limit_page_cache_ttl( (int) $form['id'] );
		}

		$fe_js  = GF_TOPS_PATH . 'assets/js/frontend.js';
		$fe_css = GF_TOPS_PATH . 'assets/css/frontend.css';
		wp_enqueue_style(
			'gf-tops-frontend-css',
			GF_TOPS_URL . 'assets/css/frontend.css',
			array(),
			$this->_version . '.' . (string) ( file_exists( $fe_css ) ? filemtime( $fe_css ) : 0 )
		);
		wp_enqueue_script(
			'gf-tops-frontend',
			GF_TOPS_URL . 'assets/js/frontend.js',
			array( 'jquery' ),
			$this->_version . '.' . (string) ( file_exists( $fe_js ) ? filemtime( $fe_js ) : 0 ),
			true
		);

		wp_localize_script(
			'gf-tops-frontend',
			'gf_tops_i18n',
			array(
				'selectModel'           => esc_html__( 'Select a model', 'gravity-forms-tops' ),
				'statusTitle'           => esc_html__( 'TOPS / TowX API', 'gravity-forms-tops' ),
				'statusEnabled'         => esc_html__( 'Integration active on this form.', 'gravity-forms-tops' ),
				'statusApi'             => esc_html__( 'JSON / XML endpoint', 'gravity-forms-tops' ),
				'statusMakes'           => esc_html__( 'Makes loaded into dropdown', 'gravity-forms-tops' ),
				'statusColors'          => esc_html__( 'Colors loaded into dropdown', 'gravity-forms-tops' ),
				'statusModels'          => esc_html__( 'Models (GetModelsForMake)', 'gravity-forms-tops' ),
				'statusModelsPending'   => esc_html__( 'Choose a make — models load after you select.', 'gravity-forms-tops' ),
				'statusModelsLoading'   => esc_html__( 'Loading models…', 'gravity-forms-tops' ),
				'statusModelsOkOne'     => esc_html__( '1 model loaded for the selected make.', 'gravity-forms-tops' ),
				/* translators: %d: number of vehicle models loaded from TowX for the selected make (more than one). */
				'statusModelsOkMany'    => esc_html__( '%d models loaded for the selected make.', 'gravity-forms-tops' ),
				/* translators: %s: error message text from TowX or transport layer. */
				'statusModelsErr'       => esc_html__( 'Could not load models: %s', 'gravity-forms-tops' ),
				'statusUnknownField'    => esc_html__( 'Field not found — check field map IDs.', 'gravity-forms-tops' ),
				'modelsLoading'         => esc_html__( 'Loading models…', 'gravity-forms-tops' ),
				'modelsUnavailable'     => esc_html__( 'Vehicle models could not be loaded. Choose the make again to retry.', 'gravity-forms-tops' ),
				'modelsNone'            => esc_html__( 'No models are listed for this make.', 'gravity-forms-tops' ),
				'modelsReload'          => esc_html__( 'This page is out of date. Reload the page, then choose the make again.', 'gravity-forms-tops' ),
				'markerLabel'           => esc_html__( 'Choices provided by the dispatch system', 'gravity-forms-tops' ),
			)
		);

		$status = array(
			'formId'                => (int) $form['id'],
			'apiEnvironmentLabel'   => $this->is_browser_console_logging_enabled( $settings ) ? $this->get_api_environment_label() : '',
			'populateVehicleLists'  => true,
			'cascadeEnabled'        => $cascade_on,
			'makesCount'            => $makes_count,
			'colorsCount'           => $colors_count,
			'makeFieldId'           => (string) $make_field,
			'modelFieldId'          => (string) $model_field,
			'colorFieldId'          => $color_field !== '' && $color_field !== null ? (string) $color_field : '',
			'debugConsole'          => $this->is_browser_console_logging_enabled( $settings ),
		);

		$inline = 'window.gfTopsStatus=window.gfTopsStatus||{};window.gfTopsStatus[' . (int) $form['id'] . ']=' . wp_json_encode( $status ) . ';';

		if ( $cascade_on ) {
			$config = wp_json_encode(
				array(
					'formId'         => (int) $form['id'],
					'makeFieldId'    => (string) $make_field,
					'modelFieldId'   => (string) $model_field,
					'colorFieldId'   => $color_field !== '' && $color_field !== null ? (string) $color_field : '',
					'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
					'nonce'          => wp_create_nonce( 'gf_tops_models_' . (int) $form['id'] ),
					'debug'          => $this->is_browser_console_logging_enabled( $settings ),
					'hiddenMakeId'   => (string) rgar( $settings, 'tops_sync_hidden_make' ),
					'hiddenModelId'  => (string) rgar( $settings, 'tops_sync_hidden_model' ),
					'hiddenColorId'  => (string) rgar( $settings, 'tops_sync_hidden_color' ),
				)
			);
			$inline .= 'window.gfTopsForms=window.gfTopsForms||{};window.gfTopsForms[' . (int) $form['id'] . ']=' . $config . ';';
		}

		wp_add_inline_script(
			'gf-tops-frontend',
			$inline,
			'before'
		);
	}

	/**
	 * Keep full-page caches from serving the model drop-down's AJAX nonce after it expires (12–24 hours).
	 * Applies to LiteSpeed Cache automatically; other caches can hook `gf_tops_limit_page_cache_ttl`.
	 *
	 * @param int $form_id Form ID on the page.
	 */
	protected function limit_page_cache_ttl( $form_id ) {
		if ( ! apply_filters( 'gf_tops_models_require_nonce', true, $form_id ) ) {
			return;
		}

		/**
		 * Longest time (seconds) a full-page cache may keep a page with a TOPS model drop-down.
		 * Default 10 hours, below the nonce lifetime. Return 0 to leave page caching alone.
		 *
		 * @param int $ttl     Seconds.
		 * @param int $form_id Form ID.
		 */
		$ttl = (int) apply_filters( 'gf_tops_page_cache_max_ttl', 10 * HOUR_IN_SECONDS, $form_id );
		if ( $ttl <= 0 ) {
			return;
		}

		// LiteSpeed Cache public API.
		do_action( 'litespeed_control_set_ttl', $ttl );

		/**
		 * Fires when a page with a TOPS model drop-down should be cached for at most $ttl seconds.
		 * Use it to set the TTL for page caches other than LiteSpeed.
		 *
		 * @param int $ttl     Seconds.
		 * @param int $form_id Form ID.
		 */
		do_action( 'gf_tops_limit_page_cache_ttl', $ttl, $form_id );
	}

	/**
	 * Plugin settings fields.
	 *
	 * @return array
	 */
	public function plugin_settings_fields() {
		return array(
			array(
				'title'       => esc_html__( 'TowX API', 'gravity-forms-tops' ),
				'description' => esc_html__( 'Choose Production or QA. This controls both JSON requests (GetMakes, GetColors, models) and the Create Call XML POST—they always use the same TowX host for that environment.', 'gravity-forms-tops' ),
				'fields'      => array(
					array(
						'name'    => 'api_environment',
						'label'   => esc_html__( 'API environment', 'gravity-forms-tops' ),
						'type'    => 'select',
						'choices' => array(
							array(
								'label' => esc_html__( 'Production (api.towxchange.net)', 'gravity-forms-tops' ),
								'value' => 'production',
							),
							array(
								'label' => esc_html__( 'QA (apiqa.towxchange.net)', 'gravity-forms-tops' ),
								'value' => 'qa',
							),
							array(
								'label' => esc_html__( 'Custom base URL', 'gravity-forms-tops' ),
								'value' => 'custom',
							),
						),
					),
					array(
						'name'  => 'custom_api_base',
						'label' => esc_html__( 'Custom base URL', 'gravity-forms-tops' ),
						'type'  => 'text',
						'class' => 'medium',
						'tooltip' => esc_html__( 'Example: https://apiqa.towxchange.net/v1/ — include trailing slash if your API expects it. Always called over HTTPS.', 'gravity-forms-tops' ),
					),
					array(
						'name' => 'custom_api_base_notice',
						'type' => 'html',
						'html' => $this->get_custom_api_base_notice_html(),
					),
					array(
						'name'  => 'notification_email',
						'label' => esc_html__( 'Error notification email', 'gravity-forms-tops' ),
						'type'  => 'text',
						'class' => 'medium',
						'tooltip' => esc_html__( 'Optional. Must be a valid email address. Receives diagnostic emails when TowX Create Call fails (transport error, API error, or unexpected response). Leave blank to skip email.', 'gravity-forms-tops' ),
					),
					array(
						'name'  => 'confirmation_error_contact_html',
						'label' => esc_html__( 'Confirmation: contact line when TowX fails', 'gravity-forms-tops' ),
						'type'  => 'textarea',
						'class' => 'large',
						'tooltip' => esc_html__( 'Optional HTML shown after an API error when this form does not override it under Form Settings → TOPS → TowX confirmation text. Message confirmations only, not redirects.', 'gravity-forms-tops' ),
					),
					array(
						'name'          => 'request_log_retention_days',
						'label'         => esc_html__( 'Keep request log for (days)', 'gravity-forms-tops' ),
						'type'          => 'text',
						'input_type'    => 'number',
						'class'         => 'small',
						'default_value' => (string) GF_Tops_Request_Log::DEFAULT_RETENTION_DAYS,
						'tooltip'       => esc_html__( 'Request-log rows (with customer details) older than this many days are deleted by a daily task. Gravity Forms entries are not affected. Enter a whole number; 0 keeps rows forever, and anything else uses the default of 90.', 'gravity-forms-tops' ),
					),
					array(
						'name'    => 'enable_logging',
						'label'   => esc_html__( 'Logging', 'gravity-forms-tops' ),
						'type'    => 'checkbox',
						'choices' => array(
							array(
								'label' => esc_html__( 'Write TowX events to the PHP error log', 'gravity-forms-tops' ),
								'name'  => 'enable_logging',
							),
						),
					),
					array(
						'name'    => 'browser_console_logging',
						'label'   => esc_html__( 'Browser console', 'gravity-forms-tops' ),
						'type'    => 'checkbox',
						'choices' => array(
							array(
								'label' => esc_html__( 'Write TowX diagnostics to the browser console on forms with TOPS enabled', 'gravity-forms-tops' ),
								'name'  => 'browser_console_logging',
							),
						),
						'tooltip' => esc_html__( 'Logs integration snapshots, mapped field changes, and model-cascade activity to the visitor’s developer console (F12). Use for staging or production verification.', 'gravity-forms-tops' ),
					),
				),
			),
		);
	}

	/**
	 * Warning under the custom base URL when it does not point at a TowX host. TowX credentials and the
	 * full Create Call (with customer details) are sent to this host.
	 *
	 * @return string
	 */
	protected function get_custom_api_base_notice_html() {
		$plugin = $this->get_plugin_settings();
		$custom = (string) rgar( $plugin, 'custom_api_base' );
		if ( 'custom' !== rgar( $plugin, 'api_environment' ) || '' === $custom ) {
			return '';
		}
		$notes = array();
		if ( 'https' !== strtolower( (string) wp_parse_url( $custom, PHP_URL_SCHEME ) ) ) {
			$notes[] = __( 'This address does not start with https://. The plugin calls it over HTTPS anyway.', 'gravity-forms-tops' );
		}
		if ( ! GF_Tops_Http::is_towx_url( $custom ) ) {
			$notes[] = __( 'This is not a towxchange.net host. TowX credentials and every Create Call (with customer details) are sent to it. Use it only for a TowX-provided or trusted endpoint.', 'gravity-forms-tops' );
		}
		if ( empty( $notes ) ) {
			return '';
		}
		$html = '<div class="notice notice-warning inline" style="max-width:720px;"><p>';
		$html .= implode( '</p><p>', array_map( 'esc_html', $notes ) );
		return $html . '</p></div>';
	}

	/**
	 * Form settings fields.
	 *
	 * @param array $form Form.
	 * @return array
	 */
	public function form_settings_fields( $form ) {
		$form_id = (int) rgar( $form, 'id' );
		$log_url = admin_url( 'admin.php?page=gf_tops_request_log&id=' . $form_id );

		return array(
			array(
				'title'       => esc_html__( 'TOPS / TowX', 'gravity-forms-tops' ),
				'description' => '',
				'fields'      => array(
					array(
						'name'    => 'enabled',
						'label'   => esc_html__( 'Enable integration', 'gravity-forms-tops' ),
						'type'    => 'checkbox',
						'choices' => array(
							array(
								'label' => esc_html__( 'Submit this form to TowX when an entry is saved', 'gravity-forms-tops' ),
								'name'  => 'enabled',
							),
						),
					),
					array(
						'name' => 'tops_settings_intro',
						'type' => 'html',
						'html' => $this->get_tops_form_settings_intro_html(),
					),
					array(
						'name' => 'tops_mmc_map_notice',
						'type' => 'html',
						'html' => $this->get_tops_mmc_mapping_notice_html( $form ),
					),
					array(
						'name'    => 'gf_tops_cascade_debug',
						'label'   => esc_html__( 'Per-form browser console', 'gravity-forms-tops' ),
						'type'    => 'checkbox',
						'choices' => array(
							array(
								'label' => esc_html__( 'Enable TowX browser console diagnostics for this form only (optional if already enabled under Forms → Settings → TOPS)', 'gravity-forms-tops' ),
								'name'  => 'gf_tops_cascade_debug',
							),
						),
						'tooltip' => esc_html__( 'Same console output as the global “Browser console” checkbox; use here to debug one form while leaving global logging off.', 'gravity-forms-tops' ),
					),
				),
			),
			array(
				'title'       => esc_html__( 'Request log', 'gravity-forms-tops' ),
				'description' => esc_html__( 'Review Create Call requests and responses, and resubmit after fixing credentials or outages.', 'gravity-forms-tops' ),
				'fields'      => array(
					array(
						'name' => 'tops_request_log_link_html',
						'type' => 'html',
						'html' => $this->user_can_view_tops_request_log()
							? '<p><a href="' . esc_url( $log_url ) . '" class="button button-secondary">' . esc_html__( 'Open request log for this form', 'gravity-forms-tops' ) . '</a></p>'
							: '<p class="description">' . esc_html__( 'Opening the request log requires permission to view Gravity Forms entries.', 'gravity-forms-tops' ) . '</p>',
					),
				),
			),
			array(
				'title'  => esc_html__( 'Authentication', 'gravity-forms-tops' ),
				'fields' => array(
					array(
						'name'  => 'tops_user_id',
						'label' => esc_html__( 'User ID', 'gravity-forms-tops' ),
						'type'  => 'text',
						'class' => 'medium',
					),
					array(
						'name'            => 'tops_password',
						'label'           => esc_html__( 'Password', 'gravity-forms-tops' ),
						'type'            => 'text',
						'input_type'      => 'password',
						'class'           => 'medium',
						'save_callback'   => array( $this, 'save_secret_merge_callback' ),
						'tooltip'         => esc_html__( 'Masked like a password. After saving, it is not shown again; leave blank to keep the saved value. Restrict who can edit forms.', 'gravity-forms-tops' ),
					),
					array(
						'name'  => 'tops_session_id',
						'label' => esc_html__( 'Session ID', 'gravity-forms-tops' ),
						'type'  => 'text',
						'class' => 'medium',
					),
					array(
						'name'            => 'tops_auth_key',
						'label'           => esc_html__( 'Authentication key', 'gravity-forms-tops' ),
						'type'            => 'text',
						'input_type'      => 'password',
						'class'           => 'large',
						'save_callback'   => array( $this, 'save_secret_merge_callback' ),
						'tooltip'         => esc_html__( 'Masked like a password. After saving, it is not shown again; leave blank to keep the saved value.', 'gravity-forms-tops' ),
					),
					array(
						'name' => 'tops_auth_test_html',
						'type' => 'html',
						'html' => '<div class="gf-tops-auth-actions"><p class="gf-tops-auth-buttons">'
							. '<button type="button" class="button button-primary gf-tops-section-save">' . esc_html__( 'Save settings', 'gravity-forms-tops' ) . '</button> '
							. '<button type="button" class="button gf-tops-test-auth">' . esc_html__( 'Test authentication', 'gravity-forms-tops' ) . '</button>'
							. '</p><div id="gf-tops-test-auth-result-' . esc_attr( (string) rgar( $form, 'id' ) ) . '" class="gf-tops-test-auth-result" aria-live="polite"></div></div>'
							. '<p class="description">' . esc_html__( 'Save after changing credentials. Test uses what you typed when those fields have text; otherwise it uses the last saved Session ID and Authentication key (GetMakes).', 'gravity-forms-tops' ) . '</p>',
					),
				),
			),
			array(
				'title'       => esc_html__( 'Field map', 'gravity-forms-tops' ),
				'description' => esc_html__( 'Point each row at the correct form field. TowX uses Year, Make, Model, and Color for VehicleInfo; Make/Model/Color must be select fields that this add-on can fill from the API. Optional hidden fields at the bottom only mirror keys for other workflows.', 'gravity-forms-tops' ),
				'fields'      => array(
					array(
						'name'      => 'tops_fields',
						'label'     => esc_html__( 'TowX fields', 'gravity-forms-tops' ),
						'type'      => 'field_map',
						'field_map' => $this->get_field_map_definition(),
					),
					array(
						'name' => 'tops_hidden_sync_intro',
						'type' => 'html',
						'html' => '<hr style="margin:1.25em 0;" /><p><strong>' . esc_html__( 'Optional: Hidden field sync', 'gravity-forms-tops' ) . '</strong></p><p class="description">' . esc_html__( 'Choose Hidden fields if you need the raw TowX keys copied from the visible Make, Model, or Color dropdowns (for notifications, feeds, or custom workflows). Leave blank to skip.', 'gravity-forms-tops' ) . '</p>',
					),
					array(
						'name'       => 'tops_sync_hidden_make',
						'label'      => esc_html__( 'Hidden field — selected make (TowX key)', 'gravity-forms-tops' ),
						'type'       => 'field_select',
						'args'       => array(
							'input_types' => array( 'hidden' ),
						),
						'no_choices' => esc_html__( 'Add at least one Hidden field to this form to use this option.', 'gravity-forms-tops' ),
						'tooltip'    => esc_html__( 'When set, the visible Make dropdown value is copied into this Hidden field whenever it changes.', 'gravity-forms-tops' ),
					),
					array(
						'name'       => 'tops_sync_hidden_model',
						'label'      => esc_html__( 'Hidden field — selected model (TowX key)', 'gravity-forms-tops' ),
						'type'       => 'field_select',
						'args'       => array(
							'input_types' => array( 'hidden' ),
						),
						'no_choices' => esc_html__( 'Add at least one Hidden field to this form to use this option.', 'gravity-forms-tops' ),
						'tooltip'    => esc_html__( 'When set, the visible Model dropdown value is copied into this Hidden field whenever it changes.', 'gravity-forms-tops' ),
					),
					array(
						'name'       => 'tops_sync_hidden_color',
						'label'      => esc_html__( 'Hidden field — selected color (TowX key)', 'gravity-forms-tops' ),
						'type'       => 'field_select',
						'args'       => array(
							'input_types' => array( 'hidden' ),
						),
						'no_choices' => esc_html__( 'Add at least one Hidden field to this form to use this option.', 'gravity-forms-tops' ),
						'tooltip'    => esc_html__( 'When set, the visible Color dropdown value is copied into this Hidden field whenever it changes.', 'gravity-forms-tops' ),
					),
					array(
						'name' => 'tops_fieldmap_save_html',
						'type' => 'html',
						'html' => '<p class="gf-tops-fieldmap-save-row">'
							. '<button type="button" class="button button-primary gf-tops-section-save">' . esc_html__( 'Save settings', 'gravity-forms-tops' ) . '</button></p>'
							. '<p class="description">' . esc_html__( 'Saves all TOPS settings on this tab (integration, authentication, field map, and confirmation text).', 'gravity-forms-tops' ) . '</p>',
					),
				),
			),
			array(
				'title'       => esc_html__( 'TowX confirmation text', 'gravity-forms-tops' ),
				'description' => esc_html__( 'Shown after submit when TowX returns a result (message confirmations only). The site name in confirmations comes from Settings → General → Site Title unless you override it per form below.', 'gravity-forms-tops' ),
				'fields'      => array(
					array(
						'name'    => 'tops_confirmation_organization',
						'label'   => esc_html__( 'Override organization name (optional)', 'gravity-forms-tops' ),
						'type'    => 'text',
						'class'   => 'large',
						'tooltip' => esc_html__( 'Leave blank to use the WordPress Site Title (Settings → General). Set only if this form should show a different name in default confirmation text and in the {organization} placeholder.', 'gravity-forms-tops' ),
					),
					array(
						'name'    => 'tops_confirmation_contact_html',
						'label'   => esc_html__( 'Contact HTML when TowX fails (optional)', 'gravity-forms-tops' ),
						'type'    => 'textarea',
						'class'   => 'large',
						'tooltip' => esc_html__( 'Overrides the global Forms → Settings → TOPS contact line for this form only (e.g. phone link). Allowed HTML is sanitized.', 'gravity-forms-tops' ),
					),
					array(
						'name'    => 'tops_confirmation_success_custom',
						'label'   => esc_html__( 'Custom message — TowX success (optional)', 'gravity-forms-tops' ),
						'type'    => 'textarea',
						'class'   => 'large',
						'tooltip' => esc_html__( 'If set, replaces the default success block. Placeholders: {call_id}, {organization}, {site_name}, {contact_html}', 'gravity-forms-tops' ),
					),
					array(
						'name'    => 'tops_confirmation_error_custom',
						'label'   => esc_html__( 'Custom message — TowX error (optional)', 'gravity-forms-tops' ),
						'type'    => 'textarea',
						'class'   => 'large',
						'tooltip' => esc_html__( 'If set, replaces the default error block. Placeholders: {error} (the raw TowX / transport error, shown to visitors), {organization}, {site_name}, {contact_html}. The default block shows visitors a generic message instead.', 'gravity-forms-tops' ),
					),
					array(
						'name'    => 'tops_confirmation_unknown_custom',
						'label'   => esc_html__( 'Custom message — unclear result (optional)', 'gravity-forms-tops' ),
						'type'    => 'textarea',
						'class'   => 'large',
						'tooltip' => esc_html__( 'If set, replaces the default message when the response had no Call ID and no clear error. Placeholders: {organization}, {site_name}, {contact_html}', 'gravity-forms-tops' ),
					),
				),
			),
		);
	}

	/**
	 * Field map rows for TowX Data + optional note prefixes.
	 *
	 * @return array
	 */
	protected function get_field_map_definition() {
		return array(
			array(
				'name'       => 'location',
				'label'      => esc_html__( 'Location (pickup)', 'gravity-forms-tops' ),
				'required'   => true,
				'field_type' => array( 'address', 'text', 'textarea', 'hidden' ),
			),
			array(
				'name'       => 'caller_name',
				'label'      => esc_html__( 'Caller name', 'gravity-forms-tops' ),
				'required'   => true,
				'field_type' => array( 'name', 'text', 'hidden' ),
			),
			array(
				'name'       => 'caller_phone',
				'label'      => esc_html__( 'Caller phone', 'gravity-forms-tops' ),
				'required'   => true,
				'field_type' => array( 'phone', 'text', 'hidden' ),
			),
			array(
				'name'       => 'destination',
				'label'      => esc_html__( 'Destination', 'gravity-forms-tops' ),
				'required'   => true,
				'field_type' => array( 'address', 'text', 'textarea', 'hidden' ),
			),
			array(
				'name'       => 'tag_number',
				'label'      => esc_html__( 'Tag number', 'gravity-forms-tops' ),
				'required'   => true,
				'field_type' => array( 'text', 'hidden' ),
			),
			array(
				'name'       => 'tag_state',
				'label'      => esc_html__( 'Tag state', 'gravity-forms-tops' ),
				'required'   => true,
				'field_type' => array( 'text', 'select', 'hidden' ),
			),
			array(
				'name'       => 'dispatch_notes',
				'label'      => esc_html__( 'Dispatch notes', 'gravity-forms-tops' ),
				'required'   => false,
				'field_type' => array( 'textarea', 'text', 'hidden' ),
			),
			array(
				'name'       => 'year',
				'label'      => esc_html__( 'Year', 'gravity-forms-tops' ),
				'required'   => true,
				'field_type' => array( 'text', 'number', 'select', 'hidden' ),
			),
			array(
				'name'       => 'make_key',
				'label'      => esc_html__( 'Make (drop-down filled from TowX)', 'gravity-forms-tops' ),
				'required'   => true,
				'field_type' => array( 'select', 'hidden' ),
			),
			array(
				'name'       => 'model_key',
				'label'      => esc_html__( 'Model (drop-down filled from TowX)', 'gravity-forms-tops' ),
				'required'   => true,
				'field_type' => array( 'select', 'hidden' ),
			),
			array(
				'name'       => 'color_key',
				'label'      => esc_html__( 'Color (drop-down filled from TowX)', 'gravity-forms-tops' ),
				'required'   => true,
				'field_type' => array( 'select', 'hidden' ),
			),
			array(
				'name'       => 'bill_to_account',
				'label'      => esc_html__( 'Optional: Bill-to account (prefixed in notes)', 'gravity-forms-tops' ),
				'required'   => false,
				'field_type' => array( 'text', 'hidden' ),
			),
			array(
				'name'       => 'customer_email',
				'label'      => esc_html__( 'Optional: Customer email (prefixed in notes)', 'gravity-forms-tops' ),
				'required'   => false,
				'field_type' => array( 'email', 'text', 'hidden' ),
			),
			array(
				'name'       => 'owner_name',
				'label'      => esc_html__( 'Optional: Owner name (prefixed in notes)', 'gravity-forms-tops' ),
				'required'   => false,
				'field_type' => array( 'name', 'text', 'hidden' ),
			),
			array(
				'name'       => 'key_location',
				'label'      => esc_html__( 'Optional: Key location (prefixed in notes)', 'gravity-forms-tops' ),
				'required'   => false,
				'field_type' => array( 'text', 'textarea', 'hidden' ),
			),
			array(
				'name'       => 'call_reason',
				'label'      => esc_html__( 'Optional: Call reason (prefixed in notes)', 'gravity-forms-tops' ),
				'required'   => false,
				'field_type' => array( 'text', 'textarea', 'select', 'hidden' ),
			),
		);
	}

	/**
	 * Whether TOPS is enabled for a form ID.
	 *
	 * @param int $form_id Form ID.
	 * @return bool
	 */
	protected function is_form_enabled( $form_id ) {
		$settings = $this->get_form_settings( $form_id );
		return ! empty( $settings['enabled'] );
	}

	/**
	 * Resolve API base for JSON GET requests.
	 *
	 * @return string Trailing slash.
	 */
	protected function get_json_api_base() {
		$plugin = $this->get_plugin_settings();
		$env    = rgar( $plugin, 'api_environment', 'production' );

		if ( 'custom' === $env ) {
			$custom = (string) rgar( $plugin, 'custom_api_base' );
			if ( $custom !== '' ) {
				// Credentials travel in the query string and XML body, so always start over HTTPS. (Redirects are still
				// followed, as for the built-in hosts; see plan.md before disabling them.)
				return trailingslashit( set_url_scheme( esc_url_raw( $custom ), 'https' ) );
			}
		}

		if ( 'qa' === $env ) {
			return 'https://apiqa.towxchange.net/v1/';
		}

		return 'https://api.towxchange.net/v1/';
	}

	/**
	 * Create Call XML endpoint: same TowX base as JSON (plugin → TOPS → API environment).
	 *
	 * @return string
	 */
	protected function get_create_call_url() {
		return esc_url_raw(
			add_query_arg(
				array( 'Product' => 'TOPSLink' ),
				$this->get_json_api_base()
			)
		);
	}

	/**
	 * Populate makes/colors on pre_render.
	 *
	 * @param array $form Form.
	 * @return array
	 */
	public function pre_render_populate( $form ) {
		if ( ! is_array( $form ) || empty( $form['id'] ) || ! $this->is_form_enabled( $form['id'] ) ) {
			return $form;
		}

		$settings = $this->get_runtime_form_settings( $form );
		$map      = $this->get_tops_field_map( $settings );
		if ( empty( $map ) ) {
			return $form;
		}

		// On submit (and on re-render after failed validation) load models for the posted make so the
		// selected model keeps its label: VehicleInfo uses choice text, and the visitor's choice is preserved.
		$make_key   = '';
		$make_field = (string) rgar( $map, 'make_key' );
		// Only for a real submission of this form, so arbitrary POSTs cannot trigger TowX calls.
		$is_this_form_posted = (int) rgpost( 'gform_submit' ) === (int) $form['id'];
		if ( $is_this_form_posted && $make_field !== '' && $this->should_enable_model_cascade( $map ) ) {
			$posted   = rgpost( 'input_' . str_replace( '.', '_', $make_field ) );
			$make_key = is_scalar( $posted ) ? sanitize_text_field( (string) $posted ) : '';
		}

		return $this->populate_vehicle_choices( $form, $settings, $make_key );
	}

	/**
	 * Fill mapped Make / Color selects (and Model when a make key is known) with TowX choices.
	 *
	 * @param array  $form     Form.
	 * @param array  $settings Runtime (decrypted) add-on form settings.
	 * @param string $make_key Selected TowX make key, or '' to leave the model choices alone.
	 * @return array Form.
	 */
	protected function populate_vehicle_choices( $form, array $settings, $make_key = '' ) {
		$map = $this->get_tops_field_map( $settings );
		if ( empty( $map ) || empty( $form['fields'] ) || ! is_array( $form['fields'] ) ) {
			return $form;
		}

		$makes  = $this->api_get_json_choices( $settings, 'GetMakes' );
		$colors = $this->api_get_json_choices( $settings, 'GetColors' );
		$models = $make_key !== '' ? $this->get_model_choices( $settings, $make_key ) : null;

		foreach ( $form['fields'] as &$field ) {
			if ( ! is_object( $field ) || 'select' !== $field->type ) {
				continue;
			}
			$fid = (string) $field->id;
			if ( $makes && (string) rgar( $map, 'make_key' ) === $fid ) {
				$field->placeholder = esc_html__( 'Select a make', 'gravity-forms-tops' );
				$field->choices     = $makes;
			}
			if ( $colors && (string) rgar( $map, 'color_key' ) === $fid ) {
				$field->placeholder = esc_html__( 'Select a color', 'gravity-forms-tops' );
				$field->choices     = $colors;
			}
			if ( $models && (string) rgar( $map, 'model_key' ) === $fid ) {
				$field->choices = $models;
			}
		}
		unset( $field );

		return $form;
	}

	/**
	 * Transient key for a cached TowX vehicle list. Includes the API base and a keyed hash of the
	 * credentials so changing environment or credentials never serves another account's list.
	 *
	 * @param array  $settings Runtime add-on form settings.
	 * @param string $verb     GetMakes|GetColors|GetModelsForMake.
	 * @param string $make_key Make key for GetModelsForMake.
	 * @return string
	 */
	protected function get_vehicle_list_cache_key( array $settings, $verb, $make_key = '' ) {
		$parts = array(
			$this->get_json_api_base(),
			(string) rgar( $settings, 'tops_session_id' ),
			(string) rgar( $settings, 'tops_auth_key' ),
			(string) $verb,
			(string) $make_key,
		);
		return 'gf_tops_list_' . substr( hash_hmac( 'sha256', implode( '|', $parts ), wp_salt( 'auth' ) ), 0, 32 );
	}

	/**
	 * How long successful TowX vehicle lists are cached.
	 *
	 * @param string $verb TowX verb.
	 * @return int Seconds; 0 disables caching.
	 */
	protected function get_vehicle_list_cache_ttl( $verb ) {
		/**
		 * Seconds to cache TowX GetMakes / GetColors / GetModelsForMake results. Return 0 to disable.
		 *
		 * @param int    $ttl  Default one hour.
		 * @param string $verb TowX verb.
		 */
		return max( 0, (int) apply_filters( 'gf_tops_vehicle_list_cache_ttl', HOUR_IN_SECONDS, $verb ) );
	}

	/**
	 * @param string $key Cache key.
	 * @return array|null Cached list or null.
	 */
	protected function get_cached_vehicle_list( $key ) {
		$cached = get_transient( $key );
		return is_array( $cached ) ? $cached : null;
	}

	/**
	 * @param string $key  Cache key.
	 * @param string $verb TowX verb.
	 * @param array  $items Non-empty list to cache.
	 */
	protected function set_cached_vehicle_list( $key, $verb, array $items ) {
		$ttl = $this->get_vehicle_list_cache_ttl( $verb );
		if ( $ttl > 0 && ! empty( $items ) ) {
			set_transient( $key, $items, $ttl );
		}
	}

	/**
	 * Model rows from a decoded GetModelsForMake response.
	 *
	 * @param array $data_node towXResponse → Data.
	 * @return array<int, array{name: string, key: string}>|null Null when the response has no model list.
	 */
	protected function extract_models_from_data( $data_node ) {
		if ( ! is_array( $data_node ) ) {
			return null;
		}
		$rows = null;
		if ( isset( $data_node['Models'] ) && is_array( $data_node['Models'] ) ) {
			$rows = $data_node['Models'];
		} elseif ( isset( $data_node['Model'] ) && is_array( $data_node['Model'] ) ) {
			$rows = $data_node['Model'];
		}
		if ( null === $rows ) {
			return null;
		}

		$models = array();
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) || ! isset( $row['Key'] ) ) {
				continue;
			}
			$models[] = array(
				'name' => isset( $row['Name'] ) ? (string) $row['Name'] : '',
				'key'  => (string) $row['Key'],
			);
		}
		return $models;
	}

	/**
	 * Models for a make as Gravity Forms choices (cached), for server-side population.
	 *
	 * @param array  $settings Runtime add-on form settings.
	 * @param string $make_key TowX make key.
	 * @return array|null Choices, or null on failure.
	 */
	protected function get_model_choices( array $settings, $make_key ) {
		$models = $this->fetch_models_for_make( $settings, $make_key );
		if ( ! is_array( $models ) || empty( $models ) ) {
			return null;
		}
		$choices = array();
		foreach ( $models as $m ) {
			$choices[] = array(
				'text'  => $m['name'] !== '' ? $m['name'] : $m['key'],
				'value' => $m['key'],
			);
		}
		return $choices;
	}

	/**
	 * GetModelsForMake with caching; failures are logged and return null.
	 *
	 * @param array  $settings Runtime add-on form settings.
	 * @param string $make_key TowX make key.
	 * @return array<int, array{name: string, key: string}>|null
	 */
	protected function fetch_models_for_make( array $settings, $make_key ) {
		$make_key  = (string) $make_key;
		$cache_key = $this->get_vehicle_list_cache_key( $settings, 'GetModelsForMake', $make_key );
		$cached    = $this->get_cached_vehicle_list( $cache_key );
		if ( null !== $cached ) {
			return $cached;
		}
		if ( $this->is_lookup_paused( $settings ) || false !== get_transient( $this->get_model_cooldown_key( $settings, $make_key ) )
			|| true !== $this->is_known_make_key( $settings, $make_key ) ) {
			return null;
		}

		$response = wp_remote_get( $this->get_models_url( $settings, $make_key ), $this->get_json_remote_args() );
		if ( is_wp_error( $response ) ) {
			$this->log( 'GetModelsForMake transport error: ' . $response->get_error_message() );
			$this->note_lookup_failure( $settings, 'GetModelsForMake', $response->get_error_message() );
			return null;
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( $code < 200 || $code >= 300 || ! is_array( $data ) || isset( $data['towXResponse']['Errors'] ) ) {
			$this->log( 'GetModelsForMake failed (HTTP ' . $code . ').' );
			/* translators: %d: HTTP status code */
			$this->note_model_failure( $settings, $make_key, $code, sprintf( __( 'TowX HTTP %d', 'gravity-forms-tops' ), $code ) );
			return null;
		}

		$models = $this->extract_models_from_data( isset( $data['towXResponse']['Data'] ) ? $data['towXResponse']['Data'] : null );
		if ( is_array( $models ) ) {
			$this->set_cached_vehicle_list( $cache_key, 'GetModelsForMake', $models );
			$this->note_lookup_success( $settings );
		}
		return $models;
	}

	/**
	 * Whether a make key is in TowX's (cached) GetMakes list. Stops visitors from spending the site's TowX
	 * credentials and quota on arbitrary keys.
	 *
	 * @param array  $settings Runtime add-on form settings.
	 * @param string $make_key Candidate make key.
	 * @return bool|null True if known, false if not, null when GetMakes is unavailable (no lookup is made then).
	 */
	protected function is_known_make_key( array $settings, $make_key ) {
		if ( (string) $make_key === '' ) {
			return false;
		}
		$makes = $this->api_get_json_choices( $settings, 'GetMakes' );
		if ( ! is_array( $makes ) || empty( $makes ) ) {
			return null;
		}
		foreach ( $makes as $choice ) {
			if ( (string) rgar( $choice, 'value' ) === (string) $make_key ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Transient that pauses TowX vehicle lookups for these credentials after a failure.
	 *
	 * @param array $settings Runtime add-on form settings.
	 * @return string
	 */
	protected function get_lookup_backoff_key( array $settings ) {
		return $this->get_vehicle_list_cache_key( $settings, 'backoff' );
	}

	/**
	 * Whether lookups are paused after a recent failure (stops a TowX outage from turning every page view
	 * into blocking 30-second requests).
	 *
	 * @param array $settings Runtime add-on form settings.
	 * @return bool
	 */
	protected function is_lookup_paused( array $settings ) {
		return false !== get_transient( $this->get_lookup_backoff_key( $settings ) );
	}

	/**
	 * Identifier for these credentials in the lookup status option (a salted hash, never the credentials).
	 *
	 * @param array $settings Runtime add-on form settings.
	 * @return string
	 */
	protected function get_lookup_status_id( array $settings ) {
		return substr( $this->get_vehicle_list_cache_key( $settings, 'status' ), strlen( 'gf_tops_list_' ) );
	}

	/**
	 * Record a failed TowX lookup: pause lookups briefly and remember the failure for the settings screen.
	 *
	 * @param array  $settings Runtime add-on form settings.
	 * @param string $verb     TowX verb.
	 * @param string $message  Error detail (shown to admins only).
	 * @param bool   $pause    Pause all lookups for these credentials (outages only).
	 */
	protected function note_lookup_failure( array $settings, $verb, $message, $pause = true ) {
		/**
		 * Seconds to pause TowX vehicle lookups after an outage (transport error, timeout, 5xx, 429). Return 0 to disable.
		 *
		 * @param int    $seconds Default 90.
		 * @param string $verb    TowX verb that failed.
		 */
		$seconds = $pause ? max( 0, (int) apply_filters( 'gf_tops_lookup_failure_backoff', 90, $verb ) ) : 0;
		if ( $seconds > 0 ) {
			set_transient( $this->get_lookup_backoff_key( $settings ), 1, $seconds );
		}

		$status = get_option( 'gf_tops_lookup_status', array() );
		if ( ! is_array( $status ) ) {
			$status = array();
		}
		$id = $this->get_lookup_status_id( $settings );
		unset( $status[ $id ] );
		$status[ $id ] = array(
			'time'    => time(),
			'verb'    => (string) $verb,
			'message' => substr( wp_strip_all_tags( (string) $message ), 0, 300 ),
		);
		if ( count( $status ) > 20 ) {
			$status = array_slice( $status, -20, null, true );
		}
		update_option( 'gf_tops_lookup_status', $status, false );
	}

	/**
	 * GetModelsForMake failure. Outages (no response, 429, 5xx) pause all lookups; any other TowX error only
	 * puts this make on a short cooldown, so a visitor choosing one problem make cannot pause lookups for
	 * everyone.
	 *
	 * @param array  $settings Runtime add-on form settings.
	 * @param string $make_key TowX make key.
	 * @param int    $code     HTTP status (0 when there was no response).
	 * @param string $message  Error detail (shown to admins only).
	 */
	protected function note_model_failure( array $settings, $make_key, $code, $message ) {
		$code   = (int) $code;
		$outage = 0 === $code || 429 === $code || $code >= 500;
		$this->note_lookup_failure( $settings, 'GetModelsForMake', $message, $outage );
		if ( ! $outage ) {
			set_transient( $this->get_model_cooldown_key( $settings, $make_key ), 1, 5 * MINUTE_IN_SECONDS );
		}
	}

	/**
	 * @param array  $settings Runtime add-on form settings.
	 * @param string $make_key TowX make key.
	 * @return string
	 */
	protected function get_model_cooldown_key( array $settings, $make_key ) {
		return $this->get_vehicle_list_cache_key( $settings, 'GetModelsForMake-failed', (string) $make_key );
	}

	/**
	 * Clear the recorded failure for these credentials after a successful lookup.
	 *
	 * @param array $settings Runtime add-on form settings.
	 */
	protected function note_lookup_success( array $settings ) {
		$status = get_option( 'gf_tops_lookup_status', array() );
		$id     = $this->get_lookup_status_id( $settings );
		if ( is_array( $status ) && isset( $status[ $id ] ) ) {
			unset( $status[ $id ] );
			update_option( 'gf_tops_lookup_status', $status, false );
		}
	}

	/**
	 * Most recent unresolved lookup failure for these credentials, if any.
	 *
	 * @param array $settings Runtime add-on form settings.
	 * @return array|null Keys: time, verb, message.
	 */
	protected function get_last_lookup_failure( array $settings ) {
		$status = get_option( 'gf_tops_lookup_status', array() );
		$id     = $this->get_lookup_status_id( $settings );
		return is_array( $status ) && isset( $status[ $id ] ) && is_array( $status[ $id ] ) ? $status[ $id ] : null;
	}

	/**
	 * GetModelsForMake URL.
	 *
	 * @param array  $settings Runtime add-on form settings.
	 * @param string $make_key TowX make key.
	 * @return string
	 */
	protected function get_models_url( array $settings, $make_key ) {
		return add_query_arg(
			array(
				'Product'           => 'TOPSLink',
				'Noun'              => 'Call',
				'Verb'              => 'GetModelsForMake',
				'SessionID'         => rawurlencode( (string) rgar( $settings, 'tops_session_id' ) ),
				'AuthenticationKey' => rawurlencode( (string) rgar( $settings, 'tops_auth_key' ) ),
				'MakeKey'           => rawurlencode( (string) $make_key ),
			),
			$this->get_json_api_base()
		);
	}

	/**
	 * wp_remote_get args for TowX JSON calls.
	 *
	 * @return array
	 */
	protected function get_json_remote_args() {
		return class_exists( 'GF_Tops_Http' )
			? array_merge( GF_Tops_Http::default_remote_args(), array( 'timeout' => 30 ) )
			: array(
				'timeout'     => 30,
				'sslverify'   => true,
				'httpversion' => '1.1',
			);
	}

	/**
	 * Fetch makes or colors as GF choice arrays.
	 *
	 * @param array  $form_settings Form settings (credentials).
	 * @param string $verb          GetMakes|GetColors.
	 * @return array|null
	 */
	protected function api_get_json_choices( $form_settings, $verb ) {
		$form_settings = (array) $form_settings;
		$cache_key     = $this->get_vehicle_list_cache_key( $form_settings, $verb );
		$cached        = $this->get_cached_vehicle_list( $cache_key );
		if ( null !== $cached ) {
			return $cached;
		}
		if ( $this->is_lookup_paused( $form_settings ) ) {
			return null;
		}

		$base = $this->get_json_api_base();
		$url  = add_query_arg(
			array(
				'Product'            => 'TOPSLink',
				'Noun'               => 'Call',
				'Verb'               => $verb,
				'SessionID'          => rawurlencode( (string) rgar( $form_settings, 'tops_session_id' ) ),
				'AuthenticationKey'  => rawurlencode( (string) rgar( $form_settings, 'tops_auth_key' ) ),
			),
			$base
		);

		$response = wp_remote_get( $url, $this->get_json_remote_args() );

		if ( is_wp_error( $response ) ) {
			$this->log( 'TowX ' . $verb . ' error: ' . $response->get_error_message() );
			$this->note_lookup_failure( $form_settings, $verb, $response->get_error_message() );
			return null;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! is_array( $data ) || ! isset( $data['towXResponse']['Data'] ) ) {
			$this->log( 'TowX ' . $verb . ' unexpected JSON.' );
			$detail = is_array( $data ) && isset( $data['towXResponse']['Errors'] )
				? $this->parse_towx_test_errors_for_display( $data )['summary']
				/* translators: %d: HTTP status code */
				: sprintf( __( 'TowX HTTP %d', 'gravity-forms-tops' ), (int) wp_remote_retrieve_response_code( $response ) );
			$this->note_lookup_failure( $form_settings, $verb, $detail );
			return null;
		}

		$key_name = ( 'GetMakes' === $verb ) ? 'Makes' : 'Colors';
		if ( ! isset( $data['towXResponse']['Data'][ $key_name ] ) || ! is_array( $data['towXResponse']['Data'][ $key_name ] ) ) {
			/* translators: %s: TowX list name, "Makes" or "Colors" */
			$this->note_lookup_failure( $form_settings, $verb, sprintf( __( 'TowX returned no %s list.', 'gravity-forms-tops' ), $key_name ) );
			return null;
		}

		$choices = array();
		foreach ( $data['towXResponse']['Data'][ $key_name ] as $row ) {
			if ( empty( $row['Name'] ) || ! isset( $row['Key'] ) ) {
				continue;
			}
			$choices[] = array(
				'text'  => (string) $row['Name'],
				'value' => (string) $row['Key'],
			);
		}

		$this->set_cached_vehicle_list( $cache_key, $verb, $choices );
		$this->note_lookup_success( $form_settings );

		return $choices;
	}

	/**
	 * AJAX: models for make.
	 */
	public function ajax_get_models() {
		$form_id = isset( $_POST['form_id'] ) ? absint( wp_unslash( $_POST['form_id'] ) ) : 0;

		/**
		 * Whether the public GetModelsForMake AJAX endpoint requires a valid nonce.
		 *
		 * Full-page caches can serve a nonce older than its 12–24 hour lifetime, which makes the model
		 * dropdown fail for visitors. Prefer excluding tow-form pages from the page cache; return false only
		 * if that is not possible. The endpoint is read-only and responses are cached server-side.
		 *
		 * @param bool $require Default true.
		 * @param int  $form_id Form ID.
		 */
		$require_nonce = (bool) apply_filters( 'gf_tops_models_require_nonce', true, $form_id );
		$nonce         = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! $form_id || ( $require_nonce && ! wp_verify_nonce( $nonce, 'gf_tops_models_' . $form_id ) ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request. Reload the page and try again.', 'gravity-forms-tops' ) ), 403 );
		}

		if ( ! $this->is_form_enabled( $form_id ) ) {
			wp_send_json_error( array( 'message' => __( 'TOPS is not enabled for this form.', 'gravity-forms-tops' ) ), 400 );
		}

		$form = GFAPI::get_form( $form_id );
		if ( ! $form || is_wp_error( $form ) ) {
			wp_send_json_error( array( 'message' => __( 'Form not found.', 'gravity-forms-tops' ) ), 404 );
		}

		$settings   = $this->get_runtime_form_settings( $form );
		$make_key   = isset( $_POST['make_key'] ) ? sanitize_text_field( wp_unslash( $_POST['make_key'] ) ) : '';
		$want_debug = isset( $_POST['debug'] ) && '1' === $_POST['debug'] && $this->is_browser_console_logging_enabled( $settings );
		$base       = $this->get_json_api_base();

		if ( $make_key === '' ) {
			wp_send_json_success( array( 'models' => array() ) );
		}

		$cache_key = $this->get_vehicle_list_cache_key( $settings, 'GetModelsForMake', $make_key );
		$cached    = $this->get_cached_vehicle_list( $cache_key );
		if ( null !== $cached ) {
			$payload = array( 'models' => $cached );
			if ( $want_debug ) {
				$payload['debug'] = array(
					'step'        => 'cache',
					'make_key'    => $make_key,
					'api_base'    => $base,
					'model_count' => count( $cached ),
				);
			}
			wp_send_json_success( $payload );
		}

		$unavailable = __( 'Vehicle models are temporarily unavailable. Please try again in a minute.', 'gravity-forms-tops' );
		$cooling     = false !== get_transient( $this->get_model_cooldown_key( $settings, $make_key ) );
		$known       = ( $cooling || $this->is_lookup_paused( $settings ) ) ? null : $this->is_known_make_key( $settings, $make_key );
		if ( true !== $known ) {
			wp_send_json_success(
				array(
					'models' => array(),
					'code'   => null === $known ? 'unavailable' : 'unknown_make',
					'error'  => null === $known ? $unavailable : __( 'Unknown vehicle make. Reload the page and try again.', 'gravity-forms-tops' ),
				)
			);
		}

		$response = wp_remote_get( $this->get_models_url( $settings, $make_key ), $this->get_json_remote_args() );

		if ( is_wp_error( $response ) ) {
			$this->log( 'GetModelsForMake transport error: ' . $response->get_error_message() );
			$this->note_lookup_failure( $settings, 'GetModelsForMake', $response->get_error_message() );
			$payload = array(
				'models' => array(),
				'error'  => $unavailable,
			);
			if ( $want_debug ) {
				$payload['debug'] = array(
					'step'       => 'transport',
					'make_key'   => $make_key,
					'api_base'   => $base,
					'error_code' => $response->get_error_code(),
				);
			}
			wp_send_json_success( $payload );
		}

		$http_code = (int) wp_remote_retrieve_response_code( $response );
		$body      = wp_remote_retrieve_body( $response );
		$data      = json_decode( $body, true );

		$debug_base = array(
			'make_key'   => $make_key,
			'http_code'  => $http_code,
			'api_base'   => $base,
			'body_chars' => strlen( $body ),
		);

		if ( $http_code < 200 || $http_code >= 300 ) {
			$this->log( 'GetModelsForMake HTTP ' . $http_code );
			/* translators: %d: HTTP status code */
			$this->note_model_failure( $settings, $make_key, $http_code, sprintf( __( 'TowX HTTP %d', 'gravity-forms-tops' ), $http_code ) );
			$payload = array(
				'models' => array(),
				'error'  => sprintf(
					/* translators: %d: HTTP status code */
					__( 'TowX HTTP %d', 'gravity-forms-tops' ),
					$http_code
				),
			);
			if ( $want_debug ) {
				$payload['debug'] = array_merge( $debug_base, array( 'step' => 'http', 'body_preview' => $this->truncate_test_response_text( $body, 800 ) ) );
			}
			wp_send_json_success( $payload );
		}

		if ( ! is_array( $data ) ) {
			$this->log( 'GetModelsForMake invalid JSON.' );
			$this->note_model_failure( $settings, $make_key, $http_code, __( 'TowX did not return valid JSON.', 'gravity-forms-tops' ) );
			$payload = array(
				'models' => array(),
				'error'  => __( 'TowX did not return valid JSON.', 'gravity-forms-tops' ),
			);
			if ( $want_debug ) {
				$payload['debug'] = array_merge( $debug_base, array( 'step' => 'json', 'body_preview' => $this->truncate_test_response_text( $body, 800 ) ) );
			}
			wp_send_json_success( $payload );
		}

		if ( isset( $data['towXResponse']['Errors'] ) ) {
			$parsed  = $this->parse_towx_test_errors_for_display( $data );
			$summary = $parsed['summary'];
			$this->log( 'GetModelsForMake TowX error: ' . $summary );
			$this->note_model_failure( $settings, $make_key, $http_code, $summary );
			$payload = array(
				'models' => array(),
				'error'  => $summary,
			);
			if ( $want_debug ) {
				$payload['debug'] = array_merge(
					$debug_base,
					array(
						'step'            => 'towx_errors',
						'errors_block'    => $parsed['block'],
						'body_preview'    => $this->truncate_test_response_text( $body, 1200 ),
					)
				);
			}
			wp_send_json_success( $payload );
		}

		$data_node = isset( $data['towXResponse']['Data'] ) && is_array( $data['towXResponse']['Data'] )
			? $data['towXResponse']['Data']
			: array();

		$models = $this->extract_models_from_data( $data_node );

		if ( null === $models ) {
			$this->log( 'GetModelsForMake: no Models array in response.' );
			$keys = array_keys( $data_node );
			$payload = array(
				'models' => array(),
				'error'  => __( 'TowX response had no Models list for this make.', 'gravity-forms-tops' ),
			);
			if ( $want_debug ) {
				$payload['debug'] = array_merge(
					$debug_base,
					array(
						'step'          => 'shape',
						'data_keys'     => $keys,
						'body_preview'  => $this->truncate_test_response_text( $body, 1200 ),
					)
				);
			}
			wp_send_json_success( $payload );
		}

		$this->set_cached_vehicle_list( $cache_key, 'GetModelsForMake', $models );
		$this->note_lookup_success( $settings );

		$payload = array( 'models' => $models );
		if ( $want_debug ) {
			$payload['debug'] = array_merge( $debug_base, array( 'step' => 'ok', 'model_count' => count( $models ) ) );
		}
		wp_send_json_success( $payload );
	}

	/**
	 * Truncate text for admin test output (avoid huge JSON in browser).
	 *
	 * @param string $text Raw text.
	 * @param int    $max  Max length.
	 * @return string
	 */
	protected function truncate_test_response_text( $text, $max = 6000 ) {
		$text = wp_strip_all_tags( (string) $text );
		if ( function_exists( 'mb_strlen' ) && function_exists( 'mb_substr' ) ) {
			if ( mb_strlen( $text, 'UTF-8' ) > $max ) {
				return mb_substr( $text, 0, $max, 'UTF-8' ) . "\n…";
			}
			return $text;
		}
		if ( strlen( $text ) > $max ) {
			return substr( $text, 0, $max ) . "\n…";
		}
		return $text;
	}

	/**
	 * Build a readable TowX error summary + optional JSON blob from decoded response.
	 *
	 * @param array $data Decoded towX JSON.
	 * @return array{ summary: string, block: string }
	 */
	protected function parse_towx_test_errors_for_display( $data ) {
		$summary = __( 'TowX returned an error.', 'gravity-forms-tops' );
		$block   = '';

		if ( ! is_array( $data ) || ! isset( $data['towXResponse']['Errors'] ) ) {
			return compact( 'summary', 'block' );
		}

		$errors = $data['towXResponse']['Errors'];

		if ( ! is_array( $errors ) ) {
			$block = $this->truncate_test_response_text(
				is_scalar( $errors ) ? (string) $errors : (string) wp_json_encode( $errors )
			);
			return compact( 'summary', 'block' );
		}

		$encoded = wp_json_encode( $errors, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		if ( false !== $encoded ) {
			$block = $this->truncate_test_response_text( $encoded );
		}

		$error_node = isset( $errors['Error'] ) ? $errors['Error'] : null;
		if ( is_array( $error_node ) && isset( $error_node['Message'] ) ) {
			$summary = (string) $error_node['Message'];
			if ( ! empty( $error_node['Context'] ) ) {
				$summary .= ' — ' . (string) $error_node['Context'];
			}
		} elseif ( is_array( $error_node ) ) {
			$parts = array();
			foreach ( $error_node as $row ) {
				if ( is_array( $row ) && ! empty( $row['Message'] ) ) {
					$parts[] = (string) $row['Message'];
				}
			}
			if ( ! empty( $parts ) ) {
				$summary = implode( '; ', $parts );
			}
		}

		return compact( 'summary', 'block' );
	}

	/**
	 * Whether the current user may run the TowX auth test (GF capability API + fallback).
	 *
	 * @return bool
	 */
	protected function user_can_run_tops_auth_test() {
		if ( ! is_user_logged_in() ) {
			return false;
		}

		// Prefer GF’s short-cap API (maps to the right primitive caps).
		if ( class_exists( 'GFCommon' ) && method_exists( 'GFCommon', 'current_user_can' ) ) {
			if ( GFCommon::current_user_can( 'edit_forms' ) ) {
				return true;
			}
		}

		// GFForms::current_user_can_any() expects an array; a string is foreach’d by character and always fails.
		if ( class_exists( 'GFForms' ) && method_exists( 'GFForms', 'current_user_can_any' ) ) {
			if ( GFForms::current_user_can_any( array( 'gravityforms_edit_forms' ) ) ) {
				return true;
			}
		}

		if ( current_user_can( 'gravityforms_edit_forms' ) ) {
			return true;
		}

		// Misconfigured roles: user reached GF form settings but lacks the GF cap name we checked.
		return current_user_can( 'manage_options' );
	}

	/**
	 * AJAX (admin): verify Session ID + Authentication key against TowX GetMakes.
	 */
	public function ajax_test_auth() {
		try {
			$this->execute_ajax_test_auth();
		} catch ( \Throwable $e ) {
			$details = $e->getMessage() . "\n" . $e->getFile() . ':' . $e->getLine();
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_DISPLAY' ) && WP_DEBUG_DISPLAY ) {
				$details .= "\n\n" . $e->getTraceAsString();
			}
			wp_send_json_error(
				array(
					'message' => __( 'The connection test hit a PHP error on the server (see details below).', 'gravity-forms-tops' ),
					'details' => $details,
				),
				200
			);
		}
	}

	/**
	 * Core logic for ajax_test_auth (wrapped for Throwable handling).
	 */
	protected function execute_ajax_test_auth() {
		// Use HTTP 200 for all JSON outcomes so admin-ajax bodies are not dropped by proxies/CDNs on 4xx/5xx.
		if ( ! check_ajax_referer( 'gf_tops_test_auth', 'nonce', false ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Invalid session. Reload the page and try again.', 'gravity-forms-tops' ) ),
				200
			);
		}

		if ( ! $this->user_can_run_tops_auth_test() ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to run this test.', 'gravity-forms-tops' ) ), 200 );
		}

		$form_id = isset( $_POST['form_id'] ) ? absint( wp_unslash( $_POST['form_id'] ) ) : 0;
		$session = isset( $_POST['session_id'] ) ? sanitize_text_field( wp_unslash( $_POST['session_id'] ) ) : '';
		$authkey = isset( $_POST['auth_key'] ) ? sanitize_text_field( wp_unslash( $_POST['auth_key'] ) ) : '';

		// Saved secrets are often not present in the DOM (masked password fields); merge from stored form settings.
		if ( $form_id > 0 ) {
			if ( ! class_exists( 'GFAPI' ) ) {
				wp_send_json_error(
					array( 'message' => __( 'Gravity Forms API is not available.', 'gravity-forms-tops' ) ),
					200
				);
			}
			$form = GFAPI::get_form( $form_id );
			if ( ! $form || is_wp_error( $form ) ) {
				wp_send_json_error( array( 'message' => __( 'Form not found.', 'gravity-forms-tops' ) ), 200 );
			}
			$stored = $this->get_runtime_form_settings( $form );
			$saved_session = (string) rgar( $stored, 'tops_session_id' );
			$saved_authkey = (string) rgar( $stored, 'tops_auth_key' );
			// If the settings screen echoed the stored ciphertext into the masked input, treat it as "use saved".
			if ( GF_Tops_Secrets::is_encrypted( $authkey ) ) {
				$authkey = '';
			}
			if ( $session === '' ) {
				$session = (string) rgar( $stored, 'tops_session_id' );
			}
			if ( $authkey === '' ) {
				$authkey = (string) rgar( $stored, 'tops_auth_key' );
			}
		}

		if ( $session === '' || $authkey === '' ) {
			wp_send_json_error(
				array(
					'message' => __(
						'Session ID and Authentication key are required. Save the form first if those fields are blank (saved keys are used when the inputs are empty).',
						'gravity-forms-tops'
					),
				),
				200
			);
		}

		$base = $this->get_json_api_base();
		$url  = add_query_arg(
			array(
				'Product'           => 'TOPSLink',
				'Noun'              => 'Call',
				'Verb'              => 'GetMakes',
				'SessionID'         => rawurlencode( $session ),
				'AuthenticationKey' => rawurlencode( $authkey ),
			),
			$base
		);

		$response = wp_remote_get( $url, $this->get_json_remote_args() );

		if ( is_wp_error( $response ) ) {
			$messages = $response->get_error_messages();
			$msg      = ! empty( $messages ) ? implode( "\n", $messages ) : __( 'Unknown transport error.', 'gravity-forms-tops' );
			wp_send_json_error(
				array(
					'message' => sprintf(
						/* translators: %s: transport error */
						__( 'Request failed: %s', 'gravity-forms-tops' ),
						$messages ? $messages[0] : $msg
					),
					'details' => sprintf(
						/* translators: 1: API base URL, 2: WordPress error code, 3: all error lines */
						__( "Target base: %1\$s\nError code: %2\$s\n\nFull message(s):\n%3\$s", 'gravity-forms-tops' ),
						$base,
						$response->get_error_code(),
						$msg
					),
				),
				200
			);
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		$body_excerpt = $this->truncate_test_response_text( $body );

		if ( $code < 200 || $code >= 300 ) {
			wp_send_json_error(
				array(
					'message' => sprintf(
						/* translators: %d: HTTP status */
						__( 'HTTP %d from TowX.', 'gravity-forms-tops' ),
						(int) $code
					),
					'details' => sprintf(
						/* translators: 1: URL base, 2: response body excerpt */
						__( "Request: GET GetMakes\nBase: %1\$s\n\nResponse body:\n%2\$s", 'gravity-forms-tops' ),
						$base,
						$body_excerpt
					),
				),
				200
			);
		}

		if ( ! is_array( $data ) ) {
			$json_err = function_exists( 'json_last_error_msg' ) ? json_last_error_msg() : __( 'Invalid JSON', 'gravity-forms-tops' );
			wp_send_json_error(
				array(
					'message' => sprintf(
						/* translators: %s: json_last_error message */
						__( 'TowX did not return valid JSON (%s).', 'gravity-forms-tops' ),
						$json_err
					),
					'details' => $body_excerpt,
				),
				200
			);
		}

		if ( isset( $data['towXResponse']['Errors'] ) ) {
			$parsed = $this->parse_towx_test_errors_for_display( $data );
			wp_send_json_error(
				array(
					'message' => $parsed['summary'],
					'details' => $parsed['block'] !== '' ? $parsed['block'] : $body_excerpt,
				),
				200
			);
		}

		if ( empty( $data['towXResponse']['Data']['Makes'] ) || ! is_array( $data['towXResponse']['Data']['Makes'] ) ) {
			$pretty = $this->truncate_test_response_text(
				wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE )
			);
			wp_send_json_error(
				array(
					'message' => __( 'Unexpected TowX response: no Makes array in Data.', 'gravity-forms-tops' ),
					'details' => $pretty,
				),
				200
			);
		}

		$count = count( $data['towXResponse']['Data']['Makes'] );
		// The checklist tick means "the saved credentials work", so only record tests of the saved values.
		if ( $form_id > 0 && isset( $saved_session, $saved_authkey ) && $session === $saved_session && $authkey === $saved_authkey ) {
			$tests = get_option( 'gf_tops_auth_test_ok', array() );
			$tests = is_array( $tests ) ? $tests : array();
			$tests[ $form_id ] = time();
			update_option( 'gf_tops_auth_test_ok', $tests, false );
		}
		wp_send_json_success(
			array(
				'message' => sprintf(
					/* translators: %d: number of makes */
					_n( 'Connected. Retrieved %d vehicle make.', 'Connected. Retrieved %d vehicle makes.', $count, 'gravity-forms-tops' ),
					$count
				),
				'details' => sprintf(
					/* translators: 1: API base, 2: HTTP status code, 3: count of makes */
					__( "GET GetMakes\nBase: %1\$s\nHTTP status: %2\$d\nParsed: towXResponse → Data → Makes (%3\$d items)", 'gravity-forms-tops' ),
					$base,
					(int) $code,
					$count
				),
			)
		);
	}

	/**
	 * Before Gravity Forms builds the confirmation: POST Create Call so `gform_confirmation` can read the result transient.
	 *
	 * @param array $entry Entry.
	 * @param array $form  Form.
	 */
	public function pre_handle_confirmation_towx( $entry, $form ) {
		if ( ! is_array( $entry ) || ! is_array( $form ) ) {
			return;
		}
		if ( 'spam' === rgar( $entry, 'status' ) ) {
			return;
		}
		if ( ! $this->is_form_enabled( $form['id'] ) ) {
			return;
		}

		$settings = $this->get_runtime_form_settings( $form );
		$this->process_towx_create_call_for_entry(
			$form,
			$entry,
			$settings,
			array(
				'source'          => 'submission',
				'notify_errors'   => true,
				'store_transient' => true,
			)
		);
	}

	/**
	 * Build auth + data, POST Create Call, log row, optional transient + email.
	 *
	 * @param array $form     Form.
	 * @param array $entry    Entry.
	 * @param array $settings Add-on form settings.
	 * @param array $meta     source, resend_of_id, resend_by_user_id, notify_errors, store_transient.
	 * @return string Outcome: success, api_error, http_error, transport_error, no_call_key, or no_map (nothing sent).
	 */
	protected function process_towx_create_call_for_entry( $form, $entry, $settings, array $meta = array() ) {
		$meta = array_merge(
			array(
				'source'            => 'submission',
				'resend_of_id'      => 0,
				'resend_by_user_id' => 0,
				'notify_errors'     => true,
				'store_transient'   => true,
			),
			$meta
		);

		$form_id  = (int) $form['id'];
		$entry_id = (int) rgar( $entry, 'id' );
		$map      = $this->get_tops_field_map( $settings );
		if ( empty( $map ) ) {
			return 'no_map';
		}

		$auth = array(
			'user_id'            => (string) rgar( $settings, 'tops_user_id' ),
			'password'           => (string) rgar( $settings, 'tops_password' ),
			'session_id'         => (string) rgar( $settings, 'tops_session_id' ),
			'authentication_key' => (string) rgar( $settings, 'tops_auth_key' ),
		);

		$optional_prefix = GF_Tops_Entry::build_custom_prefix(
			$form,
			$entry,
			$map,
			array(
				'bill_to_account' => __( 'Bill To Account', 'gravity-forms-tops' ),
				'customer_email'  => __( 'Customer Email', 'gravity-forms-tops' ),
				'owner_name'      => __( 'Owner Name', 'gravity-forms-tops' ),
				'key_location'    => __( 'Key Location', 'gravity-forms-tops' ),
				'call_reason'     => __( 'Call Reason', 'gravity-forms-tops' ),
			)
		);

		$notes = GF_Tops_Entry::get_value( $form, $entry, rgar( $map, 'dispatch_notes' ) );
		$notes = GF_Tops_Xml::normalize_notes( $optional_prefix . $notes );

		$data = array(
			'location'       => GF_Tops_Entry::get_value( $form, $entry, rgar( $map, 'location' ) ),
			'caller_name'    => GF_Tops_Entry::get_value( $form, $entry, rgar( $map, 'caller_name' ) ),
			'caller_phone'   => GF_Tops_Entry::get_value( $form, $entry, rgar( $map, 'caller_phone' ) ),
			'destination'    => GF_Tops_Entry::get_value( $form, $entry, rgar( $map, 'destination' ) ),
			'vehicle_info'   => GF_Tops_Entry::build_vehicle_info_from_mmc( $form, $entry, $map ),
			'tag_number'     => GF_Tops_Entry::get_value( $form, $entry, rgar( $map, 'tag_number' ) ),
			'tag_state'      => GF_Tops_Entry::get_value( $form, $entry, rgar( $map, 'tag_state' ) ),
			'dispatch_notes' => $notes,
			'year'           => GF_Tops_Entry::get_value( $form, $entry, rgar( $map, 'year' ) ),
			'make_key'       => GF_Tops_Entry::get_value( $form, $entry, rgar( $map, 'make_key' ) ),
			'model_key'      => GF_Tops_Entry::get_value( $form, $entry, rgar( $map, 'model_key' ) ),
			'color_key'      => GF_Tops_Entry::get_value( $form, $entry, rgar( $map, 'color_key' ) ),
		);

		$xml       = GF_Tops_Xml::build_create_call_xml( $auth, $data );
		$url       = $this->get_create_call_url();
		$redacted  = GF_Tops_Xml::redact_for_log( $xml );
		$post_out  = GF_Tops_Xml::post( $url, $xml );

		if ( is_wp_error( $post_out ) ) {
			$edata   = $post_out->get_error_data();
			$raw     = is_array( $edata ) && isset( $edata['body'] ) ? (string) $edata['body'] : '';
			$http_c  = is_array( $edata ) && isset( $edata['code'] ) ? (int) $edata['code'] : 0;
			$status  = ( 'gf_tops_http_error' === $post_out->get_error_code() ) ? 'http_error' : 'transport_error';
			$err_msg = $post_out->get_error_message();

			GF_Tops_Request_Log::insert(
				array(
					'form_id'              => $form_id,
					'entry_id'             => $entry_id,
					'endpoint_url'         => $url,
					'status'               => $status,
					'http_code'            => $http_c,
					'call_key'             => null,
					'error_message'        => $err_msg,
					'request_xml_redacted' => $redacted,
					'response_raw'         => $raw,
					'source'               => $meta['source'],
					'resend_of_id'         => (int) $meta['resend_of_id'],
					'resend_by_user_id'    => (int) $meta['resend_by_user_id'],
				)
			);

			if ( $meta['store_transient'] ) {
				$this->store_result( $entry_id, null, $err_msg, $redacted, $raw );
			}
			if ( $meta['notify_errors'] ) {
				$this->notify_error(
					__( 'TOPS API transport error', 'gravity-forms-tops' ),
					$err_msg,
					$redacted,
					$raw,
					$form_id
				);
			}
			return $status;
		}

		$http_code = (int) $post_out['code'];
		$out       = (string) $post_out['body'];
		$parsed    = GF_Tops_Xml::parse_response( $out );

		if ( $parsed['error_message'] ) {
			$detail = $parsed['error_message'] . ( $parsed['error_context'] ? ' — ' . $parsed['error_context'] : '' );
			GF_Tops_Request_Log::insert(
				array(
					'form_id'              => $form_id,
					'entry_id'             => $entry_id,
					'endpoint_url'         => $url,
					'status'               => 'api_error',
					'http_code'            => $http_code,
					'call_key'             => null,
					'error_message'        => $detail,
					'request_xml_redacted' => $redacted,
					'response_raw'         => $out,
					'source'               => $meta['source'],
					'resend_of_id'         => (int) $meta['resend_of_id'],
					'resend_by_user_id'    => (int) $meta['resend_by_user_id'],
				)
			);
			if ( $meta['store_transient'] ) {
				$this->store_result( $entry_id, null, $parsed['error_message'], $redacted, $out );
			}
			if ( $meta['notify_errors'] ) {
				$this->notify_error(
					__( 'TOPS API returned an error', 'gravity-forms-tops' ),
					$detail,
					$redacted,
					$out,
					$form_id
				);
			}
			return 'api_error';
		}

		if ( $parsed['call_key'] ) {
			GF_Tops_Request_Log::insert(
				array(
					'form_id'              => $form_id,
					'entry_id'             => $entry_id,
					'endpoint_url'         => $url,
					'status'               => 'success',
					'http_code'            => $http_code,
					'call_key'             => $parsed['call_key'],
					'error_message'        => null,
					'request_xml_redacted' => $redacted,
					'response_raw'         => $out,
					'source'               => $meta['source'],
					'resend_of_id'         => (int) $meta['resend_of_id'],
					'resend_by_user_id'    => (int) $meta['resend_by_user_id'],
				)
			);
			if ( $meta['store_transient'] ) {
				$this->store_result( $entry_id, $parsed['call_key'], null, $redacted, $out );
			}
			$this->log( 'TowX CallKey ' . $parsed['call_key'] . ' for entry ' . $entry_id );
			return 'success';
		}

		$no_key = __( 'No CallKey in response', 'gravity-forms-tops' );
		GF_Tops_Request_Log::insert(
			array(
				'form_id'              => $form_id,
				'entry_id'             => $entry_id,
				'endpoint_url'         => $url,
				'status'               => 'no_call_key',
				'http_code'            => $http_code,
				'call_key'             => null,
				'error_message'        => $no_key,
				'request_xml_redacted' => $redacted,
				'response_raw'         => $out,
				'source'               => $meta['source'],
				'resend_of_id'         => (int) $meta['resend_of_id'],
				'resend_by_user_id'    => (int) $meta['resend_by_user_id'],
			)
		);
		if ( $meta['store_transient'] ) {
			$this->store_result( $entry_id, null, $no_key, $redacted, $out );
		}
		if ( $meta['notify_errors'] ) {
			$this->notify_error(
				__( 'TOPS API unexpected response', 'gravity-forms-tops' ),
				__( 'CallKey missing.', 'gravity-forms-tops' ),
				$redacted,
				$out,
				$form_id
			);
		}
		return 'no_call_key';
	}

	/**
	 * Capability string for the request-log submenu (must match how Gravity Forms registers sibling menus).
	 *
	 * Full-access GF roles often only have `gform_full_access`, not `gravityforms_edit_forms`. Site admins may
	 * have `manage_options` when GF role caps were not mapped.
	 *
	 * @return string
	 */
	protected function get_request_log_menu_capability() {
		foreach ( array( 'gform_full_access', 'gravityforms_view_entries', 'manage_options' ) as $capability ) {
			if ( current_user_can( $capability ) ) {
				return $capability;
			}
		}
		return 'gravityforms_view_entries';
	}

	/**
	 * Whether the current user may view the TOPS request log. Log rows hold customer details, so this follows
	 * Gravity Forms' entry permission rather than form editing.
	 *
	 * @return bool
	 */
	protected function user_can_view_tops_request_log() {
		return current_user_can( 'gform_full_access' )
			|| current_user_can( 'gravityforms_view_entries' )
			|| current_user_can( 'manage_options' );
	}

	/**
	 * Whether the current user may resend a Create Call (requires Gravity Forms' entry editing permission).
	 *
	 * @return bool
	 */
	protected function user_can_resend_tops_request() {
		return current_user_can( 'gform_full_access' )
			|| current_user_can( 'gravityforms_edit_entries' )
			|| current_user_can( 'manage_options' );
	}

	/**
	 * Submenu under Forms: per-form TowX request history.
	 */
	public function register_request_log_menu() {
		if ( ! class_exists( 'GFForms' ) ) {
			return;
		}

		GF_Tops_Request_Log::maybe_install();

		add_submenu_page(
			'gf_edit_forms',
			__( 'TOPS request log', 'gravity-forms-tops' ),
			__( 'TOPS log', 'gravity-forms-tops' ),
			$this->get_request_log_menu_capability(),
			'gf_tops_request_log',
			array( $this, 'render_request_log_page' )
		);
	}

	/**
	 * Admin: list or single log row for a form.
	 */
	public function render_request_log_page() {
		if ( ! $this->user_can_view_tops_request_log() ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'gravity-forms-tops' ) );
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only admin navigation (list/detail/pagination/preview notice); capability enforced above.
		$form_id = isset( $_GET['id'] ) ? absint( wp_unslash( $_GET['id'] ) ) : 0;
		$log_id  = isset( $_GET['log_id'] ) ? absint( wp_unslash( $_GET['log_id'] ) ) : 0;
		$paged   = isset( $_GET['paged'] ) ? max( 1, absint( wp_unslash( $_GET['paged'] ) ) ) : 1;
		$notice  = isset( $_GET['gf_tops_notice'] ) ? sanitize_key( wp_unslash( $_GET['gf_tops_notice'] ) ) : '';
		$outcome = isset( $_GET['gf_tops_status'] ) ? sanitize_key( wp_unslash( $_GET['gf_tops_status'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		echo '<div class="wrap gf-tops-request-log-wrap">';
		echo '<h1 class="wp-heading-inline">' . esc_html__( 'TOPS / TowX request log', 'gravity-forms-tops' ) . '</h1>';
		echo '<hr class="wp-header-end" />';

		if ( 'resent_ok' === $notice ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Create Call was sent again. Check the new row below.', 'gravity-forms-tops' ) . '</p></div>';
		} elseif ( 'resent_fail' === $notice ) {
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Resend could not be completed.', 'gravity-forms-tops' ) . '</p></div>';
		} elseif ( 'resent_result' === $notice && 'no_map' === $outcome ) {
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Nothing was sent: this form has no TOPS field map. Map the fields under Form Settings → TOPS, then resend.', 'gravity-forms-tops' ) . '</p></div>';
		} elseif ( 'resent_result' === $notice ) {
			echo '<div class="notice notice-warning is-dismissible"><p>' . sprintf(
				/* translators: %s: request log status label, e.g. "API error" */
				esc_html__( 'Create Call was sent again, but TowX did not return a Call ID (%s). See the new row below.', 'gravity-forms-tops' ),
				esc_html( $this->format_log_status_label( $outcome ) )
			) . '</p></div>';
		}

		if ( ! $form_id ) {
			echo '<p>' . esc_html__( 'Open this screen from Forms → select a form → Settings → TOPS → Open request log for this form, or add ?id=FORM_ID to the URL.', 'gravity-forms-tops' ) . '</p>';
			echo '</div>';
			return;
		}

		if ( ! class_exists( 'GFAPI' ) ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Gravity Forms is not available.', 'gravity-forms-tops' ) . '</p></div></div>';
			return;
		}

		$form = GFAPI::get_form( $form_id );
		if ( ! $form || is_wp_error( $form ) ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Form not found.', 'gravity-forms-tops' ) . '</p></div></div>';
			return;
		}

		$list_url    = admin_url( 'admin.php?page=gf_tops_request_log&id=' . $form_id );
		$entries_url = admin_url( 'admin.php?page=gf_entries&view=entries&id=' . $form_id );

		echo '<p><strong>' . esc_html__( 'Form', 'gravity-forms-tops' ) . ':</strong> ' . esc_html( rgar( $form, 'title' ) ) . ' (#' . (int) $form_id . ')';
		echo ' &nbsp;|&nbsp; <a href="' . esc_url( $entries_url ) . '">' . esc_html__( 'View entries', 'gravity-forms-tops' ) . '</a>';
		echo ' &nbsp;|&nbsp; <a href="' . esc_url( admin_url( 'admin.php?page=gf_edit_forms&view=settings&subview=gravityformstops&id=' . $form_id ) ) . '">' . esc_html__( 'TOPS settings', 'gravity-forms-tops' ) . '</a></p>';

		if ( $log_id ) {
			$row = GF_Tops_Request_Log::get_row( $log_id, $form_id );
			if ( ! $row ) {
				echo '<div class="notice notice-warning"><p>' . esc_html__( 'Log row not found.', 'gravity-forms-tops' ) . '</p></div>';
			} else {
				$this->render_request_log_detail( $row, $form_id, $list_url );
			}
			echo '<p><a href="' . esc_url( $list_url ) . '" class="button">' . esc_html__( 'Back to list', 'gravity-forms-tops' ) . '</a></p>';
			echo '</div>';
			return;
		}

		$per_page = 20;
		$q        = GF_Tops_Request_Log::query_for_form( $form_id, $paged, $per_page );
		$rows     = $q['rows'];
		$total    = (int) $q['total'];
		$pages    = (int) ceil( $total / $per_page );

		echo '<p class="description">' . esc_html__( 'Success is recorded when TowX returns a CallKey in the XML response. Each row is one HTTP attempt; resend rebuilds the Create Call from the current entry data and saved TOPS credentials.', 'gravity-forms-tops' ) . '</p>';

		echo '<table class="wp-list-table widefat fixed striped">';
		echo '<thead><tr>';
		echo '<th scope="col">' . esc_html__( 'Date (UTC)', 'gravity-forms-tops' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'Entry', 'gravity-forms-tops' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'Status', 'gravity-forms-tops' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'HTTP', 'gravity-forms-tops' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'Call ID', 'gravity-forms-tops' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'Source', 'gravity-forms-tops' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'Actions', 'gravity-forms-tops' ) . '</th>';
		echo '</tr></thead><tbody>';

		if ( empty( $rows ) ) {
			echo '<tr><td colspan="7">' . esc_html__( 'No requests logged yet for this form.', 'gravity-forms-tops' ) . '</td></tr>';
		} else {
			foreach ( $rows as $row ) {
				$detail_url = add_query_arg(
					array(
						'page'    => 'gf_tops_request_log',
						'id'      => $form_id,
						'log_id'  => (int) $row->id,
					),
					admin_url( 'admin.php' )
				);
				$status_label = $this->format_log_status_label( $row->status );
				$entry_link   = admin_url( 'admin.php?page=gf_entries&view=entry&id=' . $form_id . '&lid=' . (int) $row->entry_id );

				echo '<tr>';
				echo '<td>' . esc_html( $row->created_at ) . '</td>';
				echo '<td><a href="' . esc_url( $entry_link ) . '">#' . (int) $row->entry_id . '</a></td>';
				echo '<td>' . esc_html( $status_label ) . '</td>';
				echo '<td>' . ( (int) $row->http_code > 0 ? (int) $row->http_code : '—' ) . '</td>';
				echo '<td>' . ( $row->call_key ? esc_html( $row->call_key ) : '—' ) . '</td>';
				echo '<td>' . esc_html( $this->format_log_source_label( $row->source ) ) . '</td>';
				echo '<td>';
				echo '<a href="' . esc_url( $detail_url ) . '" class="button button-small">' . esc_html__( 'View', 'gravity-forms-tops' ) . '</a> ';
				$this->render_resend_form( (int) $row->id, $form_id, 'success' === $row->status );
				echo '</td>';
				echo '</tr>';
			}
		}

		echo '</tbody></table>';

		if ( $pages > 1 ) {
			echo '<div class="tablenav"><div class="tablenav-pages">';
			for ( $i = 1; $i <= $pages; $i++ ) {
				$url = add_query_arg(
					array(
						'page'  => 'gf_tops_request_log',
						'id'    => $form_id,
						'paged' => $i,
					),
					admin_url( 'admin.php' )
				);
				$button_class = ( $i === $paged ) ? 'button button-primary' : 'button';
				echo '<a href="' . esc_url( $url ) . '" class="' . esc_attr( $button_class ) . '" style="margin-right:4px;">' . esc_html( (string) (int) $i ) . '</a>';
			}
			echo '</div></div>';
		}

		echo '</div>';
	}

	/**
	 * @param object $row      DB row.
	 * @param int    $form_id  Form ID.
	 * @param string $list_url List URL.
	 */
	protected function render_request_log_detail( $row, $form_id, $list_url ) {
		echo '<div class="gf-tops-log-detail" style="max-width:960px;">';
		echo '<p><strong>' . esc_html__( 'Status', 'gravity-forms-tops' ) . ':</strong> ' . esc_html( $this->format_log_status_label( $row->status ) );
		if ( ! empty( $row->error_message ) ) {
			echo ' — ' . esc_html( $row->error_message );
		}
		echo '</p>';
		if ( ! empty( $row->call_key ) ) {
			echo '<p><strong>' . esc_html__( 'Call ID', 'gravity-forms-tops' ) . ':</strong> ' . esc_html( $row->call_key ) . '</p>';
		}
		echo '<p><strong>' . esc_html__( 'Endpoint', 'gravity-forms-tops' ) . ':</strong> <code>' . esc_html( $row->endpoint_url ) . '</code></p>';

		echo '<h2>' . esc_html__( 'Request (redacted)', 'gravity-forms-tops' ) . '</h2>';
		echo '<pre style="white-space:pre-wrap;word-break:break-word;max-height:320px;overflow:auto;background:#f6f7f7;padding:12px;border:1px solid #c3c4c7;">';
		echo esc_html( $row->request_xml_redacted );
		echo '</pre>';

		echo '<h2>' . esc_html__( 'Response', 'gravity-forms-tops' ) . '</h2>';
		echo '<pre style="white-space:pre-wrap;word-break:break-word;max-height:320px;overflow:auto;background:#f6f7f7;padding:12px;border:1px solid #c3c4c7;">';
		echo esc_html( $row->response_raw );
		echo '</pre>';

		echo '<p>';
		$this->render_resend_form( (int) $row->id, $form_id, 'success' === $row->status );
		echo '</p>';
		echo '</div>';
	}

	/**
	 * @param int  $log_id            Log row ID.
	 * @param int  $form_id           Form ID.
	 * @param bool $already_succeeded Whether this attempt already returned a Call ID (resend would create a duplicate call).
	 */
	protected function render_resend_form( $log_id, $form_id, $already_succeeded = false ) {
		if ( ! $this->user_can_resend_tops_request() ) {
			return;
		}
		$url     = admin_url( 'admin-post.php' );
		$confirm = '';
		if ( $already_succeeded ) {
			$message = __( 'TowX already returned a Call ID for this attempt. Resending creates a second tow call. Continue?', 'gravity-forms-tops' );
			$confirm = ' onsubmit="return window.confirm(' . esc_attr( (string) wp_json_encode( $message ) ) . ');"';
		}
		echo '<form method="post" action="' . esc_url( $url ) . '"' . $confirm . ' style="display:inline-block;margin-left:4px;vertical-align:middle;">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $confirm is built from esc_attr() above.
		wp_nonce_field( 'gf_tops_resend_' . $form_id, '_wpnonce', false, true );
		echo '<input type="hidden" name="action" value="gf_tops_resend_create_call" />';
		echo '<input type="hidden" name="form_id" value="' . esc_attr( (string) $form_id ) . '" />';
		echo '<input type="hidden" name="log_id" value="' . esc_attr( (string) $log_id ) . '" />';
		echo '<button type="submit" class="button button-small">' . esc_html__( 'Resend Create Call', 'gravity-forms-tops' ) . '</button>';
		echo '</form>';
	}

	/**
	 * @param string $source Raw source (submission|resend).
	 * @return string
	 */
	protected function format_log_source_label( $source ) {
		$labels = array(
			'submission' => __( 'Form submission', 'gravity-forms-tops' ),
			'resend'     => __( 'Resend', 'gravity-forms-tops' ),
		);
		return isset( $labels[ $source ] ) ? $labels[ $source ] : (string) $source;
	}

	/**
	 * @param string $status Raw status.
	 * @return string
	 */
	protected function format_log_status_label( $status ) {
		$labels = array(
			'success'         => __( 'Success', 'gravity-forms-tops' ),
			'api_error'       => __( 'API error', 'gravity-forms-tops' ),
			'http_error'      => __( 'HTTP error', 'gravity-forms-tops' ),
			'transport_error' => __( 'Transport error', 'gravity-forms-tops' ),
			'no_call_key'     => __( 'No Call ID', 'gravity-forms-tops' ),
		);
		return isset( $labels[ $status ] ) ? $labels[ $status ] : $status;
	}

	/**
	 * POST handler: resend Create Call for log row’s entry.
	 */
	public function handle_resend_create_call_post() {
		$form_id = isset( $_POST['form_id'] ) ? absint( wp_unslash( $_POST['form_id'] ) ) : 0;
		$log_id  = isset( $_POST['log_id'] ) ? absint( wp_unslash( $_POST['log_id'] ) ) : 0;

		$redirect_base = admin_url( 'admin.php?page=gf_tops_request_log&id=' . $form_id );
		$fail          = add_query_arg( 'gf_tops_notice', 'resent_fail', $redirect_base );
		$ok            = add_query_arg( 'gf_tops_notice', 'resent_ok', $redirect_base );

		if ( ! $form_id || ! $log_id || ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'gf_tops_resend_' . $form_id ) ) {
			wp_safe_redirect( $fail );
			exit;
		}

		if ( ! $this->user_can_resend_tops_request() ) {
			wp_safe_redirect( $fail );
			exit;
		}

		if ( ! class_exists( 'GFAPI' ) ) {
			wp_safe_redirect( $fail );
			exit;
		}

		$row = GF_Tops_Request_Log::get_row( $log_id, $form_id );
		if ( ! $row ) {
			wp_safe_redirect( $fail );
			exit;
		}

		$form = GFAPI::get_form( $form_id );
		if ( ! $form || is_wp_error( $form ) ) {
			wp_safe_redirect( $fail );
			exit;
		}

		if ( ! $this->is_form_enabled( $form_id ) ) {
			wp_safe_redirect( $fail );
			exit;
		}

		$entry = GFAPI::get_entry( (int) $row->entry_id );
		if ( is_wp_error( $entry ) || empty( $entry ) ) {
			wp_safe_redirect( $fail );
			exit;
		}

		if ( (int) rgar( $entry, 'form_id' ) !== $form_id ) {
			wp_safe_redirect( $fail );
			exit;
		}

		$settings = $this->get_runtime_form_settings( $form );
		$user_id  = get_current_user_id();

		// GFAPI::get_form() has no TowX choices; load them so VehicleInfo uses make / model / color names, not keys.
		$map  = $this->get_tops_field_map( $settings );
		$form = $this->populate_vehicle_choices(
			$form,
			$settings,
			$this->should_enable_model_cascade( $map ) ? (string) rgar( $entry, (string) rgar( $map, 'make_key' ) ) : ''
		);

		$outcome = $this->process_towx_create_call_for_entry(
			$form,
			$entry,
			$settings,
			array(
				'source'            => 'resend',
				'resend_of_id'      => $log_id,
				'resend_by_user_id' => $user_id ? $user_id : 0,
				'notify_errors'     => false,
				'store_transient'   => false,
			)
		);

		if ( 'success' !== $outcome ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'gf_tops_notice' => 'resent_result',
						'gf_tops_status' => sanitize_key( (string) $outcome ),
					),
					$redirect_base
				)
			);
			exit;
		}

		wp_safe_redirect( $ok );
		exit;
	}

	/**
	 * Store result for confirmation display.
	 *
	 * @param int         $entry_id Entry ID.
	 * @param string|null $call_key Call key.
	 * @param string|null $error    Error message.
	 * @param string      $xml_sent Unused; kept for signature compatibility. Request XML is not stored here.
	 * @param string      $raw      Raw response.
	 */
	protected function store_result( $entry_id, $call_key, $error, $xml_sent, $raw ) {
		$data = array(
			'call_key' => $call_key,
			'error'    => $error,
			'raw'      => $raw,
		);
		set_transient( 'gf_tops_result_' . $entry_id, $data, 15 * MINUTE_IN_SECONDS );
	}

	/**
	 * Filter confirmation message with TowX Create Call outcome (only after pre_handle_confirmation_towx runs).
	 *
	 * @param mixed $confirmation Confirmation.
	 * @param array $form Form.
	 * @param array $entry Entry.
	 * @param bool  $ajax Ajax.
	 * @return mixed
	 */
	public function filter_confirmation( $confirmation, $form, $entry, $ajax ) {
		if ( ! $this->is_form_enabled( $form['id'] ) ) {
			return $confirmation;
		}

		$entry_id = (int) rgar( $entry, 'id' );
		$data     = get_transient( 'gf_tops_result_' . $entry_id );
		if ( false === $data || ! is_array( $data ) ) {
			return $confirmation;
		}

		delete_transient( 'gf_tops_result_' . $entry_id );

		$fragment = $this->build_tops_confirmation_fragment( $form, $entry, $data );
		if ( $fragment === '' ) {
			return $confirmation;
		}

		/**
		 * Replace the entire confirmation body with the TowX status fragment (default: append).
		 *
		 * @param bool  $replace Replace instead of append.
		 * @param array $form    Form.
		 * @param array $entry   Entry.
		 * @param array $data    Transient payload (call_key, error, …).
		 */
		$replace = apply_filters( 'gf_tops_replace_confirmation_with_status', false, $form, $entry, $data );

		if ( $replace ) {
			/**
			 * Full HTML for the confirmation when replacing. Default is the built-in TowX fragment.
			 *
			 * @param string $html    HTML (filtered).
			 * @param array  $form    Form.
			 * @param array  $entry   Entry.
			 * @param array  $data    Result transient.
			 * @param mixed  $confirmation Original confirmation (string or array).
			 */
			$fragment = apply_filters( 'gf_tops_confirmation_full_html', $fragment, $form, $entry, $data, $confirmation );
			if ( is_string( $confirmation ) ) {
				return $fragment;
			}
			if ( is_array( $confirmation ) && isset( $confirmation['message'] ) && is_string( $confirmation['message'] ) ) {
				$confirmation['message'] = $fragment;
				return $confirmation;
			}
			return $confirmation;
		}

		if ( is_string( $confirmation ) ) {
			return $confirmation . $fragment;
		}

		if ( is_array( $confirmation ) ) {
			if ( isset( $confirmation['redirect'] ) ) {
				return $confirmation;
			}
			if ( isset( $confirmation['message'] ) && is_string( $confirmation['message'] ) ) {
				$confirmation['message'] .= $fragment;
				return $confirmation;
			}
		}

		return $confirmation;
	}

	/**
	 * HTML block for confirmation: success (Call ID), API/transport error, or ambiguous state.
	 *
	 * Per-form text lives under Form Settings → TOPS → TowX confirmation text. The default “contacting …”
	 * name is the WordPress Site Title unless you set a per-form override or custom templates.
	 *
	 * @param array $form Form.
	 * @param array $entry Entry.
	 * @param array $data  gf_tops_result_* transient.
	 * @return string HTML fragment.
	 */
	protected function build_tops_confirmation_fragment( $form, $entry, array $data ) {
		$settings = $this->get_form_settings( $form );

		$call_key = isset( $data['call_key'] ) && $data['call_key'] !== '' && $data['call_key'] !== null
			? (string) $data['call_key']
			: '';
		$error    = isset( $data['error'] ) && $data['error'] !== '' && $data['error'] !== null
			? (string) $data['error']
			: '';

		$contact_html = $this->get_form_confirmation_contact_html( $settings );
		$pairs        = $this->get_tops_confirmation_placeholder_pairs( $call_key, $error, $settings, $contact_html );

		if ( $call_key !== '' ) {
			$custom = isset( $settings['tops_confirmation_success_custom'] ) ? trim( (string) $settings['tops_confirmation_success_custom'] ) : '';
			if ( $custom !== '' ) {
				$html = '<div class="gf-tops-confirmation gf-tops-confirmation--success">';
				$html .= wp_kses_post( strtr( $custom, $pairs ) );
				$html .= '</div>';
			} else {
				$html = $this->build_default_tops_confirmation_success( $settings, $call_key );
			}
		} elseif ( $error !== '' ) {
			$custom = isset( $settings['tops_confirmation_error_custom'] ) ? trim( (string) $settings['tops_confirmation_error_custom'] ) : '';
			if ( $custom !== '' ) {
				$html = '<div class="gf-tops-confirmation gf-tops-confirmation--error">';
				$html .= wp_kses_post( strtr( $custom, $pairs ) );
				$html .= '</div>';
			} else {
				$html = $this->build_default_tops_confirmation_error( $error, $contact_html, (int) rgar( $form, 'id' ) );
			}
		} else {
			$custom = isset( $settings['tops_confirmation_unknown_custom'] ) ? trim( (string) $settings['tops_confirmation_unknown_custom'] ) : '';
			if ( $custom !== '' ) {
				$html = '<div class="gf-tops-confirmation gf-tops-confirmation--unknown">';
				$html .= wp_kses_post( strtr( $custom, $pairs ) );
				$html .= '</div>';
			} else {
				$html = $this->build_default_tops_confirmation_unknown( $contact_html, (int) rgar( $form, 'id' ) );
			}
		}

		return apply_filters( 'gf_tops_confirmation_fragment_html', $html, $form, $entry, $data );
	}

	/**
	 * Contact snippet for error/unknown states: per-form HTML overrides global plugin HTML.
	 *
	 * @param array $settings Form add-on settings.
	 * @return string HTML (already passed through wp_kses_post).
	 */
	protected function get_form_confirmation_contact_html( array $settings ) {
		$raw = isset( $settings['tops_confirmation_contact_html'] ) ? trim( (string) $settings['tops_confirmation_contact_html'] ) : '';
		if ( $raw === '' ) {
			$plugin = $this->get_plugin_settings();
			$raw    = isset( $plugin['confirmation_error_contact_html'] ) ? trim( (string) $plugin['confirmation_error_contact_html'] ) : '';
		}
		return $raw !== '' ? wp_kses_post( $raw ) : '';
	}

	/**
	 * Placeholders for custom confirmation templates (escaped except allowed HTML in {contact_html}).
	 *
	 * @param string $call_key     Call ID.
	 * @param string $error        Error message.
	 * @param array  $settings     Form settings.
	 * @param string $contact_html Sanitized contact HTML.
	 * @return array<string, string>
	 */
	protected function get_tops_confirmation_placeholder_pairs( $call_key, $error, array $settings, $contact_html ) {
		$org = $this->get_confirmation_organization_label( $settings );

		return array(
			'{call_id}'        => esc_html( $call_key ),
			'{error}'          => esc_html( $error ),
			'{organization}'   => esc_html( $org ),
			'{site_name}'      => esc_html( get_bloginfo( 'name' ) ),
			'{contact_html}'   => $contact_html,
		);
	}

	/**
	 * Name shown as “organization” in confirmations: optional per-form override, else WordPress Site Title (option `blogname`).
	 *
	 * @param array $settings Form add-on settings.
	 * @return string
	 */
	protected function get_confirmation_organization_label( array $settings ) {
		$override = isset( $settings['tops_confirmation_organization'] ) ? trim( (string) $settings['tops_confirmation_organization'] ) : '';
		if ( $override !== '' ) {
			return $override;
		}

		return trim( (string) get_bloginfo( 'name', 'display' ) );
	}

	/**
	 * Default success copy — organization label defaults to Site Title from Settings → General.
	 *
	 * @param array  $settings Form add-on settings.
	 * @param string $call_key Call reference.
	 * @return string HTML fragment (wrapped).
	 */
	protected function build_default_tops_confirmation_success( array $settings, $call_key ) {
		$org = $this->get_confirmation_organization_label( $settings );

		$html = '<div class="gf-tops-confirmation gf-tops-confirmation--success">';
		if ( $org !== '' ) {
			$html .= '<p>' . sprintf(
				/* translators: %s: site / organization name (WordPress Site Title unless overridden per form) */
				esc_html__( 'Thank you for contacting %s.', 'gravity-forms-tops' ),
				esc_html( $org )
			) . '</p>';
		} else {
			$html .= '<p>' . esc_html__( 'Thank you for your submission.', 'gravity-forms-tops' ) . '</p>';
		}
		$html .= '<p>' . sprintf(
			/* translators: %s: TowX Call ID / reference */
			esc_html__( 'Your Call ID: %s — keep this number for any follow-up about this request.', 'gravity-forms-tops' ),
			esc_html( $call_key )
		) . '</p>';
		$html .= '<p>' . esc_html__( 'You should receive follow-up about next steps.', 'gravity-forms-tops' ) . '</p>';
		$html .= '</div>';

		return $html;
	}

	/**
	 * Default error block. Visitors get a generic message and the contact line; the TowX / transport detail is
	 * shown only to signed-in staff who can open the request log (for example while testing the form).
	 *
	 * @param string $error        Error text.
	 * @param string $contact_html Sanitized contact HTML.
	 * @param int    $form_id      Form ID (for the request-log link).
	 * @return string HTML fragment (wrapped).
	 */
	protected function build_default_tops_confirmation_error( $error, $contact_html, $form_id = 0 ) {
		$html = '<div class="gf-tops-confirmation gf-tops-confirmation--error">';
		$html .= '<p>' . esc_html__( 'Your request was received, but we could not send it to our dispatch system automatically.', 'gravity-forms-tops' ) . '</p>';
		if ( $contact_html !== '' ) {
			$html .= '<div class="gf-tops-confirmation__contact">' . $contact_html . '</div>';
		} else {
			$html .= '<p>' . esc_html__( 'Please contact us directly so we can make sure your request is handled.', 'gravity-forms-tops' ) . '</p>';
		}
		$html .= $this->get_staff_confirmation_note( $error, $form_id );
		$html .= '</div>';

		return $html;
	}

	/**
	 * @param string $contact_html Sanitized contact HTML.
	 * @param int    $form_id      Form ID (for the request-log link).
	 * @return string HTML fragment (wrapped).
	 */
	protected function build_default_tops_confirmation_unknown( $contact_html, $form_id = 0 ) {
		$html = '<div class="gf-tops-confirmation gf-tops-confirmation--unknown">';
		$html .= '<p>' . esc_html__( 'Your submission was received, but the dispatch reference could not be confirmed.', 'gravity-forms-tops' ) . '</p>';
		if ( $contact_html !== '' ) {
			$html .= '<div class="gf-tops-confirmation__contact">' . $contact_html . '</div>';
		} else {
			$html .= '<p>' . esc_html__( 'Please contact us directly if you need help with this request.', 'gravity-forms-tops' ) . '</p>';
		}
		$html .= $this->get_staff_confirmation_note( '', $form_id );
		$html .= '</div>';

		return $html;
	}

	/**
	 * Note for signed-in staff who can open the request log: the real error and a link to the log.
	 * Visitors never see it.
	 *
	 * @param string $error   Error detail ('' when unknown).
	 * @param int    $form_id Form ID.
	 * @return string
	 */
	protected function get_staff_confirmation_note( $error, $form_id ) {
		if ( ! function_exists( 'is_user_logged_in' ) || ! is_user_logged_in() || ! $this->user_can_view_tops_request_log() ) {
			return '';
		}
		$html = '<div class="gf-tops-confirmation__staff" style="margin-top:1em;padding:8px 12px;border-left:4px solid #996800;background:#fcf9e8;">';
		$html .= '<p style="margin:0;"><strong>' . esc_html__( 'Staff only (visitors do not see this):', 'gravity-forms-tops' ) . '</strong> ';
		$html .= '' !== (string) $error
			? sprintf(
				/* translators: %s: TowX or transport error detail */
				esc_html__( 'TowX reported: %s', 'gravity-forms-tops' ),
				esc_html( $error )
			)
			: esc_html__( 'TowX did not return a Call ID.', 'gravity-forms-tops' );
		if ( $form_id > 0 ) {
			$html .= ' <a href="' . esc_url( admin_url( 'admin.php?page=gf_tops_request_log&id=' . (int) $form_id ) ) . '">' . esc_html__( 'Open the request log', 'gravity-forms-tops' ) . '</a>';
		}
		return $html . '</p></div>';
	}

	/**
	 * Email + log helpers.
	 *
	 * @param string $subject Subject.
	 * @param string $message Error text.
	 * @param string $xml     Redacted request XML (GF_Tops_Xml::redact_for_log); never pass credentials.
	 * @param string $raw     Raw response.
	 * @param int    $form_id Form ID (links the email to its request log).
	 */
	protected function notify_error( $subject, $message, $xml, $raw, $form_id = 0 ) {
		$this->log( $subject . ': ' . $message );

		$plugin = $this->get_plugin_settings();
		$raw_to = rgar( $plugin, 'notification_email' );
		$to     = is_string( $raw_to ) ? sanitize_email( trim( $raw_to ) ) : '';
		if ( ! is_email( $to ) ) {
			$this->log( 'TowX error email skipped: invalid or empty Error notification email (Forms → Settings → TOPS).' );
			return;
		}

		$body = '<p>' . esc_html( $message ) . '</p>';
		if ( (int) $form_id > 0 ) {
			$body .= '<p><a href="' . esc_url( admin_url( 'admin.php?page=gf_tops_request_log&id=' . (int) $form_id ) ) . '">' . esc_html__( 'Open the request log for this form', 'gravity-forms-tops' ) . '</a> ' . esc_html__( '(resend from there after fixing the cause).', 'gravity-forms-tops' ) . '</p>';
		}
		$body .= '<h4>' . esc_html__( 'Request XML (credentials redacted)', 'gravity-forms-tops' ) . '</h4><pre>' . esc_html( GF_Tops_Xml::redact_for_log( $xml ) ) . '</pre>';
		if ( $raw !== '' ) {
			$body .= '<h4>' . esc_html__( 'Response', 'gravity-forms-tops' ) . '</h4><pre>' . esc_html( $raw ) . '</pre>';
		}

		wp_mail( $to, '[' . wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) . '] ' . $subject, $body, array( 'Content-Type: text/html; charset=UTF-8' ) );
	}

	/**
	 * Log if enabled.
	 *
	 * @param string $msg Message.
	 */
	protected function log( $msg ) {
		$plugin = $this->get_plugin_settings();
		if ( empty( $plugin['enable_logging'] ) ) {
			return;
		}
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( '[GF TOPS] ' . $msg );
	}
}
