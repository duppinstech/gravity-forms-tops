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

require_once dirname( __DIR__ ) . '/includes/class-gf-tops-secrets.php';
require_once dirname( __DIR__ ) . '/includes/class-gf-tops-xml.php';
require_once dirname( __DIR__ ) . '/includes/class-gf-tops-entry.php';
require_once dirname( __DIR__ ) . '/includes/class-gf-tops-http.php';
