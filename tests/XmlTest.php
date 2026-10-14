<?php
/**
 * Create Call XML generation, redaction, POST handling and response parsing.
 *
 * @package GF_Tops
 */

use PHPUnit\Framework\TestCase;

class XmlTest extends TestCase {

	protected function tearDown(): void {
		$GLOBALS['gf_tops_test_http'] = null;
	}

	private function auth() {
		return array(
			'user_id'            => 'fake-user',
			'password'           => 'fake-password',
			'session_id'         => 'fake-session',
			'authentication_key' => 'fake-auth-key',
		);
	}

	private function data( array $overrides = array() ) {
		return array_merge(
			array(
				'location'       => '1 Test St, Testville, TS 00000',
				'caller_name'    => 'Test Caller',
				'caller_phone'   => '555-0100',
				'destination'    => '2 Test Ave, Testville, TS 00000',
				'vehicle_info'   => '2020 TestMake TestModel Blue',
				'tag_number'     => 'TEST123',
				'tag_state'      => 'TS',
				'dispatch_notes' => 'Notes',
				'year'           => '2020',
				'make_key'       => '10',
				'model_key'      => '20',
				'color_key'      => '30',
			),
			$overrides
		);
	}

	public function test_build_produces_well_formed_xml_with_mapped_values() {
		$xml = GF_Tops_Xml::build_create_call_xml( $this->auth(), $this->data() );
		$doc = simplexml_load_string( $xml );

		$this->assertNotFalse( $doc );
		$this->assertSame( 'TOPSLink', (string) $doc->Operation->Product );
		$this->assertSame( 'Create', (string) $doc->Operation->Verb );
		$this->assertSame( 'fake-session', (string) $doc->Authentication->SessionID );
		$this->assertSame( 'Test Caller', (string) $doc->Data->CallerName );
		$this->assertSame( '2020 TestMake TestModel Blue', (string) $doc->Data->VehicleInfo );
		$this->assertSame( '20', (string) $doc->Data->ModelKey );
	}

	public function test_markup_and_control_characters_in_input_cannot_break_the_document() {
		$xml = GF_Tops_Xml::build_create_call_xml(
			$this->auth(),
			$this->data(
				array(
					'caller_name'    => 'A & B <Caller> "Q" \'S\'',
					'dispatch_notes' => "bell\x07 vtab\x0B </DispatchNotes><Injected>1</Injected>",
				)
			)
		);
		$doc = simplexml_load_string( $xml );

		$this->assertNotFalse( $doc, 'Escaped XML must parse.' );
		$this->assertSame( 'A & B <Caller> "Q" \'S\'', (string) $doc->Data->CallerName );
		$this->assertSame( 'bell vtab </DispatchNotes><Injected>1</Injected>', (string) $doc->Data->DispatchNotes );
		$this->assertFalse( isset( $doc->Data->Injected ) );
	}

	public function test_redaction_removes_every_credential_occurrence() {
		$xml      = GF_Tops_Xml::build_create_call_xml( $this->auth(), $this->data() );
		$redacted = GF_Tops_Xml::redact_for_log( $xml );

		$this->assertStringNotContainsString( 'fake-password', $redacted );
		$this->assertStringNotContainsString( 'fake-auth-key', $redacted );
		$this->assertStringNotContainsString( 'fake-session', $redacted );
		$this->assertSame( 2, substr_count( $redacted, '<Password>***</Password>' ) );
		$this->assertStringContainsString( 'Test Caller', $redacted );
		$this->assertSame( $redacted, GF_Tops_Xml::redact_for_log( $redacted ), 'Redaction is idempotent.' );
	}

	public function test_normalize_notes_collapses_whitespace() {
		$this->assertSame( 'a b c', GF_Tops_Xml::normalize_notes( "a\r\nb\n\n  c " ) );
		$this->assertSame( '', GF_Tops_Xml::normalize_notes( null ) );
	}

	public function test_parse_response_success_error_and_invalid() {
		$ok = GF_Tops_Xml::parse_response( '<towXResponse><Data><CallKey>CALL-1</CallKey></Data></towXResponse>' );
		$this->assertSame( 'CALL-1', $ok['call_key'] );
		$this->assertNull( $ok['error_message'] );

		$err = GF_Tops_Xml::parse_response( '<towXResponse><Errors><Error><Message>Bad key</Message><Context>Auth</Context></Error></Errors></towXResponse>' );
		$this->assertNull( $err['call_key'] );
		$this->assertSame( 'Bad key', $err['error_message'] );
		$this->assertSame( 'Auth', $err['error_context'] );

		$none = GF_Tops_Xml::parse_response( '<towXResponse><Data/></towXResponse>' );
		$this->assertNull( $none['call_key'] );
		$this->assertNull( $none['error_message'] );

		$bad = GF_Tops_Xml::parse_response( 'not xml <' );
		$this->assertSame( 'Invalid XML response from TowX.', $bad['error_message'] );
		$this->assertSame( array(), libxml_get_errors(), 'libxml error buffer is cleared.' );
	}

	public function test_post_returns_body_on_2xx_and_sends_tls_defaults() {
		$GLOBALS['gf_tops_test_http'] = array(
			'response' => array( 'code' => 200 ),
			'body'     => '<towXResponse/>',
		);

		$out = GF_Tops_Xml::post( 'https://apiqa.towxchange.net/v1/', '<x/>' );

		$this->assertSame( array( 'code' => 200, 'body' => '<towXResponse/>' ), $out );
		$args = $GLOBALS['gf_tops_test_http_last']['args'];
		$this->assertTrue( $args['sslverify'] );
		$this->assertSame( 'text/xml; charset=WINDOWS-1252', $args['headers']['Content-Type'] );
	}

	public function test_post_maps_non_2xx_and_transport_errors_to_wp_error() {
		$GLOBALS['gf_tops_test_http'] = array(
			'response' => array( 'code' => 503 ),
			'body'     => 'down',
		);
		$http = GF_Tops_Xml::post( 'https://apiqa.towxchange.net/v1/', '<x/>' );
		$this->assertInstanceOf( WP_Error::class, $http );
		$this->assertSame( 'gf_tops_http_error', $http->get_error_code() );
		$this->assertSame( array( 'body' => 'down', 'code' => 503 ), $http->get_error_data() );

		$GLOBALS['gf_tops_test_http'] = new WP_Error( 'http_request_failed', 'timeout' );
		$transport = GF_Tops_Xml::post( 'https://apiqa.towxchange.net/v1/', '<x/>' );
		$this->assertSame( 'http_request_failed', $transport->get_error_code() );
	}

	public function test_towx_host_detection_for_tls_workarounds() {
		$this->assertTrue( GF_Tops_Http::is_towx_url( 'https://api.towxchange.net/v1/' ) );
		$this->assertTrue( GF_Tops_Http::is_towx_url( 'https://APIQA.towxchange.net/v1/' ) );
		$this->assertFalse( GF_Tops_Http::is_towx_url( 'https://towxchange.net.example.com/' ) );
		$this->assertFalse( GF_Tops_Http::is_towx_url( 'https://evil-towxchange.net/' ) );
		$this->assertFalse( GF_Tops_Http::is_towx_url( 'not a url' ) );
	}
}
