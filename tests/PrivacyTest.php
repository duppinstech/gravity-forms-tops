<?php
/**
 * Privacy exporter / eraser for the request log.
 *
 * @package GF_Tops
 */

use PHPUnit\Framework\TestCase;

class PrivacyTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['wpdb']                 = new GF_Tops_Test_Wpdb();
		$GLOBALS['gf_tops_test_entries'] = array(
			array( 'id' => 41 ),
			array( 'id' => 42 ),
		);
		$GLOBALS['gf_tops_test_entry_search'] = null;
	}

	private function row( $id ) {
		return (object) array(
			'id'                   => $id,
			'created_at'           => '2026-10-01 12:00:00',
			'entry_id'             => 41,
			'status'               => 'success',
			'call_key'             => 'CALL-1',
			'request_xml_redacted' => '<Password>***</Password>',
			'response_raw'         => '<towXResponse/>',
		);
	}

	public function test_entries_are_found_by_email_across_all_fields() {
		$this->assertSame( array( 41, 42 ), GF_Tops_Privacy::find_entry_ids( 'person@example.com' ) );
		$this->assertSame( array( array( 'value' => 'person@example.com' ) ), $GLOBALS['gf_tops_test_entry_search']['field_filters'] );
		$this->assertSame( array(), GF_Tops_Privacy::find_entry_ids( 'not-an-email' ) );
	}

	public function test_export_returns_rows_for_entries_or_email_with_prepared_values() {
		$GLOBALS['wpdb']->results = array( $this->row( 7 ) );

		$out = GF_Tops_Privacy::export_personal_data( 'per_son%@example.com', 1 );

		$this->assertTrue( $out['done'] );
		$this->assertSame( 'gf-tops-log-7', $out['data'][0]['item_id'] );
		$this->assertSame( 'CALL-1', $out['data'][0]['data'][3]['value'] );

		$sql = $GLOBALS['wpdb']->queries[0];
		$this->assertStringContainsString( 'entry_id IN (41,42)', $sql );
		$this->assertStringContainsString( "LIKE '%per\\\\_son\\\\%@example.com%'", $sql, 'LIKE wildcards in the email are escaped.' );
		$this->assertStringContainsString( 'LIMIT 50 OFFSET 0', $sql );
	}

	public function test_export_paginates() {
		$GLOBALS['wpdb']->results = array_fill( 0, GF_Tops_Privacy::ROWS_PER_PAGE, $this->row( 1 ) );
		$out                      = GF_Tops_Privacy::export_personal_data( 'person@example.com', 2 );

		$this->assertFalse( $out['done'] );
		$this->assertStringContainsString( 'OFFSET 50', $GLOBALS['wpdb']->queries[0] );
	}

	public function test_erase_deletes_matching_rows() {
		$GLOBALS['wpdb']->affected = 3;
		$out                       = GF_Tops_Privacy::erase_personal_data( 'person@example.com', 1 );

		$this->assertTrue( $out['items_removed'] );
		$this->assertTrue( $out['done'] );
		$this->assertStringStartsWith( 'DELETE FROM `wp_gf_tops_request_log` WHERE', $GLOBALS['wpdb']->queries[0] );

		$GLOBALS['wpdb']->affected = 0;
		$none                      = GF_Tops_Privacy::erase_personal_data( 'nobody@example.com', 1 );
		$this->assertFalse( $none['items_removed'] );
	}

	public function test_invalid_email_touches_nothing() {
		$this->assertSame( 0, GF_Tops_Request_Log::delete_for_person( array( 41 ), 'not-an-email' ) );
		$this->assertSame( array(), GF_Tops_Request_Log::find_for_person( array( 41 ), '' ) );
		$this->assertSame( array(), $GLOBALS['wpdb']->queries );
	}

	public function test_without_entries_only_the_email_match_is_used() {
		$GLOBALS['gf_tops_test_entries'] = array();
		GF_Tops_Privacy::export_personal_data( 'person@example.com', 1 );

		$this->assertStringNotContainsString( 'entry_id IN', $GLOBALS['wpdb']->queries[0] );
	}

	public function test_registration_and_policy_text() {
		$this->assertArrayHasKey( 'gravity-forms-tops', GF_Tops_Privacy::register_exporter( array() ) );
		$this->assertArrayHasKey( 'gravity-forms-tops', GF_Tops_Privacy::register_eraser( array() ) );
		$text = GF_Tops_Privacy::policy_text();
		$this->assertStringContainsString( 'TowXchange', $text );
		$this->assertStringContainsString( 'erase', $text );
	}
}
