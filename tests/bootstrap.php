<?php
/**
 * PHPUnit bootstrap: minimal WordPress / Gravity Forms doubles so the plugin's standalone classes
 * (secrets, XML, entry mapping) can be tested without a WordPress install.
 *
 * Everything here is fake test data. Never add real TowX credentials or customer data.
 *
 * @package GF_Tops
 */

define( 'ABSPATH', __DIR__ . '/' );

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

/**
 * Test filter registry: tests set $GLOBALS['gf_tops_test_filters'][ $hook ] = callable.
 */
$GLOBALS['gf_tops_test_filters'] = array();
$GLOBALS['gf_tops_test_http']    = null;

function apply_filters( $hook, $value, ...$args ) {
	if ( isset( $GLOBALS['gf_tops_test_filters'][ $hook ] ) ) {
		return call_user_func( $GLOBALS['gf_tops_test_filters'][ $hook ], $value, ...$args );
	}
	return $value;
}

function __( $text, $domain = 'default' ) {
	return $text;
}

function esc_html__( $text, $domain = 'default' ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
}

function _n( $single, $plural, $number, $domain = 'default' ) {
	return 1 === (int) $number ? $single : $plural;
}

function absint( $value ) {
	return abs( (int) $value );
}

function rgar( $arr, $key, $default_value = '' ) {
	if ( ! is_array( $arr ) && ! ( is_object( $arr ) && $arr instanceof ArrayAccess ) ) {
		return $default_value;
	}
	return isset( $arr[ $key ] ) ? $arr[ $key ] : $default_value;
}

class WP_Error {
	private $code;
	private $message;
	private $data;

	public function __construct( $code = '', $message = '', $data = '' ) {
		$this->code    = $code;
		$this->message = $message;
		$this->data    = $data;
	}

	public function get_error_code() {
		return $this->code;
	}

	public function get_error_message() {
		return $this->message;
	}

	public function get_error_data() {
		return $this->data;
	}
}

function is_wp_error( $thing ) {
	return $thing instanceof WP_Error;
}

/**
 * HTTP double: tests set $GLOBALS['gf_tops_test_http'] to a response array or WP_Error.
 * The last request is recorded in $GLOBALS['gf_tops_test_http_last'].
 */
function wp_remote_post( $url, $args = array() ) {
	$GLOBALS['gf_tops_test_http_last'] = array(
		'url'  => $url,
		'args' => $args,
	);
	return $GLOBALS['gf_tops_test_http'];
}

function wp_remote_retrieve_response_code( $response ) {
	return isset( $response['response']['code'] ) ? $response['response']['code'] : '';
}

function wp_remote_retrieve_body( $response ) {
	return isset( $response['body'] ) ? $response['body'] : '';
}

function wp_parse_url( $url, $component = -1 ) {
	return parse_url( $url, $component ); // phpcs:ignore
}

/**
 * Stand-in for Gravity Forms' GFCommon crypto: AES-256-CTR with an HMAC-SHA-512 tag, base64 encoded.
 */
class GFCommon {
	const TEST_KEY = 'gf-tops-unit-test-key-not-a-secret';

	public static function openssl_encrypt( $text ) {
		$iv     = random_bytes( 16 );
		$cipher = openssl_encrypt( $text, 'aes-256-ctr', self::TEST_KEY, OPENSSL_RAW_DATA, $iv );
		$mac    = hash_hmac( 'sha512', $iv . $cipher, self::TEST_KEY, true );
		return base64_encode( $iv . $mac . $cipher );
	}

	public static function openssl_decrypt( $payload ) {
		$raw = base64_decode( (string) $payload, true );
		if ( false === $raw || strlen( $raw ) < 80 ) {
			return false;
		}
		$iv     = substr( $raw, 0, 16 );
		$mac    = substr( $raw, 16, 64 );
		$cipher = substr( $raw, 80 );
		if ( ! hash_equals( hash_hmac( 'sha512', $iv . $cipher, self::TEST_KEY, true ), $mac ) ) {
			return false;
		}
		return openssl_decrypt( $cipher, 'aes-256-ctr', self::TEST_KEY, OPENSSL_RAW_DATA, $iv );
	}
}

/**
 * Minimal GF field object.
 */
class GF_Tops_Test_Field {
	public $id;
	public $type;
	public $choices;

	public function __construct( $id, $type, $choices = array() ) {
		$this->id      = $id;
		$this->type    = $type;
		$this->choices = $choices;
	}
}

class GFFormsModel {
	public static function get_field( $form, $field_id ) {
		foreach ( (array) rgar( $form, 'fields', array() ) as $field ) {
			if ( (string) $field->id === (string) $field_id ) {
				return $field;
			}
		}
		return null;
	}
}

// ---- Doubles used by the GitHub updater tests. -------------------------------------------------

define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );

$GLOBALS['gf_tops_test_site_options'] = array();
$GLOBALS['gf_tops_test_get_queue']    = array();
$GLOBALS['gf_tops_test_get_requests'] = array();
$GLOBALS['gf_tops_test_hooks']        = array();

function get_site_option( $name, $default_value = false ) {
	return array_key_exists( $name, $GLOBALS['gf_tops_test_site_options'] ) ? $GLOBALS['gf_tops_test_site_options'][ $name ] : $default_value;
}

function update_site_option( $name, $value ) {
	$GLOBALS['gf_tops_test_site_options'][ $name ] = $value;
	return true;
}

function delete_site_option( $name ) {
	unset( $GLOBALS['gf_tops_test_site_options'][ $name ] );
	return true;
}

/**
 * GET double: shifts the next queued response (array or WP_Error) and records the request.
 */
function wp_remote_get( $url, $args = array() ) {
	$GLOBALS['gf_tops_test_get_requests'][] = array(
		'url'  => $url,
		'args' => $args,
	);
	if ( empty( $GLOBALS['gf_tops_test_get_queue'] ) ) {
		return new WP_Error( 'no_response_queued', 'No test response queued.' );
	}
	return array_shift( $GLOBALS['gf_tops_test_get_queue'] );
}

function wp_remote_retrieve_header( $response, $header ) {
	if ( ! is_array( $response ) || empty( $response['headers'] ) ) {
		return '';
	}
	$headers = array_change_key_case( $response['headers'], CASE_LOWER );
	return isset( $headers[ strtolower( $header ) ] ) ? $headers[ strtolower( $header ) ] : '';
}

function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	$GLOBALS['gf_tops_test_hooks'][] = array( $hook, $callback );
	return true;
}

function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	return add_filter( $hook, $callback, $priority, $accepted_args );
}

function plugin_basename( $file ) {
	return basename( dirname( $file ) ) . '/' . basename( $file );
}

/**
 * Same header matching as WordPress core's get_file_data().
 */
function get_file_data( $file, $headers ) {
	$data = (string) file_get_contents( $file, false, null, 0, 8192 ); // phpcs:ignore
	$out  = array();
	foreach ( $headers as $key => $name ) {
		$out[ $key ] = preg_match( '/^(?:[ \t]*<\?php)?[ \t\/*#@]*' . preg_quote( $name, '/' ) . ':(.*)$/mi', $data, $m ) ? trim( $m[1] ) : '';
	}
	return $out;
}

function esc_html( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
}

function esc_url( $url ) {
	return (string) $url;
}

function wpautop( $text ) {
	return '<p>' . $text . '</p>';
}

function trailingslashit( $value ) {
	return rtrim( (string) $value, '/\\' ) . '/';
}

function untrailingslashit( $value ) {
	return rtrim( (string) $value, '/\\' );
}

$GLOBALS['gf_tops_test_user_can'] = false;

function current_user_can( $capability ) {
	return (bool) $GLOBALS['gf_tops_test_user_can'];
}

/**
 * Filesystem double recording moves.
 */
class GF_Tops_Test_Filesystem {
	public $moves  = array();
	public $result = true;

	public function move( $from, $to, $overwrite = false ) {
		$this->moves[] = array( $from, $to, $overwrite );
		return $this->result;
	}
}

function wp_kses_post( $html ) {
	return (string) preg_replace( '#<script\b[^>]*>.*?</script>#is', '', (string) $html );
}

// ---- Doubles used by the add-on and privacy tests. -----------------------------------------------

$GLOBALS['gf_tops_test_options']    = array();
$GLOBALS['gf_tops_test_transients'] = array();

function get_option( $name, $default_value = false ) {
	return array_key_exists( $name, $GLOBALS['gf_tops_test_options'] ) ? $GLOBALS['gf_tops_test_options'][ $name ] : $default_value;
}

function update_option( $name, $value, $autoload = null ) {
	$GLOBALS['gf_tops_test_options'][ $name ] = $value;
	return true;
}

function delete_option( $name ) {
	unset( $GLOBALS['gf_tops_test_options'][ $name ] );
	return true;
}

function get_transient( $name ) {
	return array_key_exists( $name, $GLOBALS['gf_tops_test_transients'] ) ? $GLOBALS['gf_tops_test_transients'][ $name ] : false;
}

function set_transient( $name, $value, $expiration = 0 ) {
	$GLOBALS['gf_tops_test_transients'][ $name ] = $value;
	return true;
}

function wp_salt( $scheme = 'auth' ) {
	return 'gf-tops-unit-test-salt';
}

function esc_url_raw( $url ) {
	return (string) $url;
}

function set_url_scheme( $url, $scheme = null ) {
	return (string) preg_replace( '#^\w+://#', $scheme . '://', (string) $url );
}

function wp_strip_all_tags( $text ) {
	return trim( strip_tags( (string) $text ) ); // phpcs:ignore
}

function wp_json_encode( $data, $options = 0 ) {
	return json_encode( $data, $options ); // phpcs:ignore
}

function is_email( $email ) {
	return false !== filter_var( $email, FILTER_VALIDATE_EMAIL ) ? $email : false;
}

function has_blocks( $content ) {
	return false !== strpos( (string) $content, '<!-- wp:' );
}

/**
 * Minimal parse_blocks(): enough for "<!-- wp:name {json} /-->" self-closing blocks.
 */
function parse_blocks( $content ) {
	$blocks = array();
	if ( preg_match_all( '#<!-- wp:([a-z0-9/-]+) (\{.*?\}) /-->#', (string) $content, $m, PREG_SET_ORDER ) ) {
		foreach ( $m as $match ) {
			$blocks[] = array(
				'blockName'   => $match[1],
				'attrs'       => json_decode( $match[2], true ),
				'innerBlocks' => array(),
			);
		}
	}
	return $blocks;
}

if ( ! defined( 'GF_TOPS_VERSION' ) ) {
	define( 'GF_TOPS_VERSION', '0.0.0-test' );
	define( 'GF_TOPS_FILE', dirname( __DIR__ ) . '/gravity-forms-tops.php' );
	define( 'GF_TOPS_PATH', dirname( __DIR__ ) . '/' );
	define( 'GF_TOPS_URL', 'https://example.test/wp-content/plugins/gravity-forms-tops/' );
}

/**
 * Minimal Gravity Forms add-on base class.
 */
class GFAddOn {
	protected $_slug;

	public function __construct() {}

	public function get_slug() {
		return $this->_slug;
	}

	public function get_plugin_settings() {
		return isset( $GLOBALS['gf_tops_test_plugin_settings'] ) ? $GLOBALS['gf_tops_test_plugin_settings'] : array();
	}

	public function get_form_settings( $form ) {
		return is_array( $form ) && isset( $form[ $this->_slug ] ) && is_array( $form[ $this->_slug ] ) ? $form[ $this->_slug ] : array();
	}

	public function save_form_settings( $form, $settings ) {
		$GLOBALS['gf_tops_test_saved_settings'] = $settings;
		return true;
	}
}

/**
 * GFAPI double: entries come from $GLOBALS['gf_tops_test_entries']; the last search is recorded.
 */
class GFAPI {
	public static function get_entries( $form_ids, $search = array(), $sorting = null, $paging = null ) {
		$GLOBALS['gf_tops_test_entry_search'] = $search;
		$offset                               = isset( $paging['offset'] ) ? (int) $paging['offset'] : 0;
		$size                                 = isset( $paging['page_size'] ) ? (int) $paging['page_size'] : 20;
		$all                                  = isset( $GLOBALS['gf_tops_test_entries'] ) ? $GLOBALS['gf_tops_test_entries'] : array();
		return array_slice( $all, $offset, $size );
	}
}

/**
 * $wpdb double: records prepared SQL and returns queued results.
 */
class GF_Tops_Test_Wpdb {
	public $prefix  = 'wp_';
	public $queries = array();
	public $results = array();
	public $affected = 0;

	public function esc_like( $text ) {
		return addcslashes( (string) $text, '_%\\' );
	}

	public function prepare( $query, ...$args ) {
		if ( 1 === count( $args ) && is_array( $args[0] ) ) {
			$args = $args[0];
		}
		$i = 0;
		return (string) preg_replace_callback(
			'/%[ids]/',
			static function ( $m ) use ( &$i, $args ) {
				$value = $args[ $i++ ];
				if ( '%d' === $m[0] ) {
					return (string) (int) $value;
				}
				if ( '%i' === $m[0] ) {
					return '`' . $value . '`';
				}
				return "'" . addslashes( (string) $value ) . "'";
			},
			$query
		);
	}

	public function get_results( $sql ) {
		$this->queries[] = $sql;
		return $this->results;
	}

	public function query( $sql ) {
		$this->queries[] = $sql;
		return $this->affected;
	}
}

$GLOBALS['wpdb'] = new GF_Tops_Test_Wpdb();

require_once dirname( __DIR__ ) . '/includes/class-gf-tops-github-updater.php';
require_once dirname( __DIR__ ) . '/includes/class-gf-tops-secrets.php';
require_once dirname( __DIR__ ) . '/includes/class-gf-tops-xml.php';
require_once dirname( __DIR__ ) . '/includes/class-gf-tops-entry.php';
require_once dirname( __DIR__ ) . '/includes/class-gf-tops-http.php';
require_once dirname( __DIR__ ) . '/includes/class-gf-tops-request-log.php';
require_once dirname( __DIR__ ) . '/includes/class-gf-tops-privacy.php';
require_once dirname( __DIR__ ) . '/includes/class-gf-tops-addon.php';
