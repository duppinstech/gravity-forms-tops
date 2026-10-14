<?php
/**
 * GF_Tops_Addon logic that does not need a running WordPress / Gravity Forms site.
 *
 * @package GF_Tops
 */

use PHPUnit\Framework\TestCase;

/**
 * Add-on with controllable dependencies.
 */
class GF_Tops_Test_Addon extends GF_Tops_Addon {
	public $makes        = null;
	public $current_form = null;
	public $make_calls   = 0;

	protected function api_get_json_choices( $form_settings, $verb ) {
		if ( 'GetMakes' === $verb ) {
			++$this->make_calls;
			return $this->makes;
		}
		return null;
	}

	public function get_current_form() {
		return $this->current_form;
	}
}

class AddonTest extends TestCase {

	/** @var GF_Tops_Test_Addon */
	private $addon;

	protected function setUp(): void {
		$GLOBALS['gf_tops_test_options']         = array();
		$GLOBALS['gf_tops_test_transients']      = array();
		$GLOBALS['gf_tops_test_filters']         = array();
		$GLOBALS['gf_tops_test_plugin_settings'] = array();
		$this->addon                             = new GF_Tops_Test_Addon();
	}

	private function call( $method, ...$args ) {
		$ref = new ReflectionMethod( GF_Tops_Addon::class, $method );
		$ref->setAccessible( true );
		return $ref->invoke( $this->addon, ...$args );
	}

	private function settings( array $extra = array() ) {
		return array_merge(
			array(
				'tops_session_id' => 'fake-session',
				'tops_auth_key'   => 'fake-auth-key',
			),
			$extra
		);
	}

	public function test_field_map_reads_flat_nested_and_row_list_shapes() {
		$flat = array(
			'tops_fields_make_key' => '5',
			'tops_fields_location' => '1',
			'enabled'              => '1',
		);
		$this->assertSame( array( 'make_key' => '5', 'location' => '1' ), $this->call( 'get_tops_field_map', $flat ) );

		$rows = array(
			'tops_fields' => array(
				array( 'key' => 'make_key', 'value' => '5' ),
				array( 'key' => 'model_key', 'value' => '6' ),
			),
		);
		$this->assertSame( array( 'make_key' => '5', 'model_key' => '6' ), $this->call( 'get_tops_field_map', $rows ) );

		$nested = array( 'tops_fields' => array( 'make_key' => '5' ) );
		$this->assertSame( array( 'make_key' => '5' ), $this->call( 'get_tops_field_map', $nested ) );

		$this->assertSame( array(), $this->call( 'get_tops_field_map', 'not-an-array' ) );
	}

	public function test_models_are_extracted_from_either_response_shape() {
		$expected = array(
			array( 'name' => 'Camry', 'key' => '20' ),
		);
		$this->assertSame( $expected, $this->call( 'extract_models_from_data', array( 'Models' => array( array( 'Key' => 20, 'Name' => 'Camry' ) ) ) ) );
		$this->assertSame( $expected, $this->call( 'extract_models_from_data', array( 'Model' => array( array( 'Key' => '20', 'Name' => 'Camry' ), 'junk' ) ) ) );
		$this->assertNull( $this->call( 'extract_models_from_data', array( 'Other' => array() ) ) );
		$this->assertNull( $this->call( 'extract_models_from_data', null ) );
	}

	public function test_make_key_check_is_tri_state() {
		$this->addon->makes = array( array( 'text' => 'Toyota', 'value' => '10' ) );
		$this->assertTrue( $this->call( 'is_known_make_key', $this->settings(), '10' ) );
		$this->assertFalse( $this->call( 'is_known_make_key', $this->settings(), '999' ) );
		$this->assertFalse( $this->call( 'is_known_make_key', $this->settings(), '' ) );

		$this->addon->makes = null;
		$this->assertNull( $this->call( 'is_known_make_key', $this->settings(), '10' ), 'GetMakes unavailable: never treated as known.' );
	}

	public function test_cache_key_depends_on_credentials_and_make_without_containing_them() {
		$a = $this->call( 'get_vehicle_list_cache_key', $this->settings(), 'GetModelsForMake', '10' );
		$b = $this->call( 'get_vehicle_list_cache_key', $this->settings(), 'GetModelsForMake', '11' );
		$c = $this->call( 'get_vehicle_list_cache_key', $this->settings( array( 'tops_auth_key' => 'other-key' ) ), 'GetModelsForMake', '10' );

		$this->assertNotSame( $a, $b );
		$this->assertNotSame( $a, $c );
		$this->assertStringStartsWith( 'gf_tops_list_', $a );
		$this->assertStringNotContainsString( 'fake-auth-key', $a );
		$this->assertStringNotContainsString( 'fake-session', $a );
		$this->assertLessThanOrEqual( 172 - strlen( '_transient_timeout_' ), strlen( $a ), 'Fits the options table name limit.' );
	}

	public function test_lookup_failure_pauses_and_is_reported_until_success() {
		$settings = $this->settings();
		$this->assertFalse( $this->call( 'is_lookup_paused', $settings ) );

		$this->call( 'note_lookup_failure', $settings, 'GetMakes', '<b>cURL error 28</b>: timed out' );
		$this->assertTrue( $this->call( 'is_lookup_paused', $settings ) );
		$failure = $this->call( 'get_last_lookup_failure', $settings );
		$this->assertSame( 'GetMakes', $failure['verb'] );
		$this->assertSame( 'cURL error 28: timed out', $failure['message'], 'Stored without markup.' );

		$other = $this->settings( array( 'tops_auth_key' => 'other-key' ) );
		$this->assertFalse( $this->call( 'is_lookup_paused', $other ), 'Other credentials are not paused.' );
		$this->assertNull( $this->call( 'get_last_lookup_failure', $other ) );

		$stored = wp_json_encode( $GLOBALS['gf_tops_test_options'] );
		$this->assertStringNotContainsString( 'fake-auth-key', $stored );

		$this->call( 'note_lookup_success', $settings );
		$this->assertNull( $this->call( 'get_last_lookup_failure', $settings ) );
	}

	public function test_lookup_backoff_can_be_disabled() {
		$GLOBALS['gf_tops_test_filters']['gf_tops_lookup_failure_backoff'] = static function () {
			return 0;
		};
		$this->call( 'note_lookup_failure', $this->settings(), 'GetColors', 'HTTP 500' );
		$this->assertFalse( $this->call( 'is_lookup_paused', $this->settings() ) );
		$this->assertNotNull( $this->call( 'get_last_lookup_failure', $this->settings() ), 'Failure is still reported to admins.' );
	}

	public function test_fetch_models_makes_no_request_while_paused_or_for_unknown_makes() {
		$this->addon->makes = array( array( 'text' => 'Toyota', 'value' => '10' ) );
		$GLOBALS['gf_tops_test_get_requests'] = array();
		$GLOBALS['gf_tops_test_get_queue']    = array();

		$this->assertNull( $this->call( 'fetch_models_for_make', $this->settings(), '999' ) );
		$this->call( 'note_lookup_failure', $this->settings(), 'GetMakes', 'down' );
		$this->assertNull( $this->call( 'fetch_models_for_make', $this->settings(), '10' ) );

		$this->assertSame( array(), $GLOBALS['gf_tops_test_get_requests'] );
	}

	public function test_custom_api_base_is_always_https() {
		$GLOBALS['gf_tops_test_plugin_settings'] = array(
			'api_environment' => 'custom',
			'custom_api_base' => 'http://apiqa.towxchange.net/v1',
		);
		$this->assertSame( 'https://apiqa.towxchange.net/v1/', $this->call( 'get_json_api_base' ) );

		$GLOBALS['gf_tops_test_plugin_settings'] = array( 'api_environment' => 'qa' );
		$this->assertSame( 'https://apiqa.towxchange.net/v1/', $this->call( 'get_json_api_base' ) );

		$GLOBALS['gf_tops_test_plugin_settings'] = array();
		$this->assertSame( 'https://api.towxchange.net/v1/', $this->call( 'get_json_api_base' ) );
	}

	public function test_blank_secret_on_save_keeps_the_stored_value() {
		$this->addon->current_form = array(
			'id'               => 1,
			'gravityformstops' => array( 'tops_password' => 'gf_tops_e1:ciphertext' ),
		);
		$field_object       = new stdClass();
		$field_object->name = 'tops_password';

		$this->assertSame( 'gf_tops_e1:ciphertext', $this->addon->save_secret_merge_callback( $field_object, '' ) );
		$this->assertSame( 'gf_tops_e1:ciphertext', $this->addon->save_secret_merge_callback( array( 'name' => 'tops_password' ), null ) );
		$this->assertSame( 'new-secret', $this->addon->save_secret_merge_callback( $field_object, 'new-secret' ) );
	}

	public function test_towx_error_summary_for_single_and_multiple_errors() {
		$one = $this->call(
			'parse_towx_test_errors_for_display',
			array( 'towXResponse' => array( 'Errors' => array( 'Error' => array( 'Message' => 'Bad key', 'Context' => 'Auth' ) ) ) )
		);
		$this->assertSame( 'Bad key — Auth', $one['summary'] );

		$many = $this->call(
			'parse_towx_test_errors_for_display',
			array( 'towXResponse' => array( 'Errors' => array( 'Error' => array( array( 'Message' => 'A' ), array( 'Message' => 'B' ) ) ) ) )
		);
		$this->assertSame( 'A; B', $many['summary'] );

		$none = $this->call( 'parse_towx_test_errors_for_display', array() );
		$this->assertSame( 'TowX returned an error.', $none['summary'] );
	}

	public function test_response_text_is_stripped_and_truncated() {
		$this->assertSame( 'hello', $this->call( 'truncate_test_response_text', '<p>hello</p>', 10 ) );
		$this->assertSame( "abcde\n…", $this->call( 'truncate_test_response_text', 'abcdefghij', 5 ) );
	}

	public function test_form_ids_are_found_in_shortcodes_blocks_and_elementor_data() {
		$content = '[gravityform id="3" title="false"] <!-- wp:gravityforms/form {"formId":"7"} /-->';
		$this->assertEqualsCanonicalizing( array( 3, 7 ), $this->call( 'extract_gravity_form_ids_from_content', $content ) );

		$json = '[{"settings":{"form_id":"12"}},{"settings":{"formId":13}},{"settings":{"editor":"[gravityforms id=14]"}}]';
		$this->assertEqualsCanonicalizing( array( 12, 13, 14 ), $this->call( 'extract_gravity_form_ids_from_elementor_json', $json ) );

		$this->assertSame( array(), $this->call( 'extract_gravity_form_ids_from_content', 'no forms here' ) );
	}

	public function test_required_field_map_keys_match_the_definition() {
		$required = $this->call( 'get_required_field_map_keys' );
		foreach ( array( 'location', 'caller_name', 'caller_phone', 'destination', 'make_key', 'model_key', 'color_key' ) as $key ) {
			$this->assertContains( $key, $required );
		}
		$this->assertNotContains( 'dispatch_notes', $required );
		$this->assertNotContains( 'customer_email', $required );
	}

	public function test_log_labels_are_translated_and_unknown_values_pass_through() {
		$this->assertSame( 'Form submission', $this->call( 'format_log_source_label', 'submission' ) );
		$this->assertSame( 'Resend', $this->call( 'format_log_source_label', 'resend' ) );
		$this->assertSame( 'other', $this->call( 'format_log_source_label', 'other' ) );
		$this->assertSame( 'No Call ID', $this->call( 'format_log_status_label', 'no_call_key' ) );
	}
}
