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

	private function row( $id, $entry_id = 41, $xml = '<Password>***</Password>' ) {
		return (object) array(
			'id'                   => $id,
			'created_at'           => '2026-10-01 12:00:00',
			'entry_id'             => $entry_id,
			'status'               => 'success',
			'call_key'             => 'CALL-1',
			'request_xml_redacted' => $xml,
			'response_raw'         => '<towXResponse/>',
		);
	}

	public function test_entries_are_found_by_email_in_every_entry_status() {
		$ids = GF_Tops_Privacy::find_entry_ids( 'person@example.com' );

		$this->assertSame( array( 41, 42 ), $ids );
		$this->assertSame( array( array( 'value' => 'person@example.com' ) ), $GLOBALS['gf_tops_test_entry_search']['field_filters'] );
		$this->assertSame( 'trash', $GLOBALS['gf_tops_test_entry_search']['status'], 'Spam and trash entries are searched too.' );
		$this->assertSame( array(), GF_Tops_Privacy::find_entry_ids( 'not-an-email' ) );
	}

	public function test_exact_email_matching() {
		$this->assertTrue( GF_Tops_Request_Log::text_contains_email( '<DispatchNotes>Customer Email: bob@x.com Owner</DispatchNotes>', 'bob@x.com' ) );
		$this->assertTrue( GF_Tops_Request_Log::text_contains_email( 'Email: BOB@X.COM.', 'bob@x.com' ), 'Case-insensitive; trailing sentence dot is fine.' );
		$this->assertFalse( GF_Tops_Request_Log::text_contains_email( 'Customer Email: jimbob@x.com', 'bob@x.com' ) );
		$this->assertFalse( GF_Tops_Request_Log::text_contains_email( 'Customer Email: bob@x.com.au', 'bob@x.com' ) );
		$this->assertFalse( GF_Tops_Request_Log::text_contains_email( 'Customer Email: bob@x.co', 'bob@x.com' ) );
		$this->assertFalse( GF_Tops_Request_Log::text_contains_email( 'Customer Email: bob+tow@x.com', 'b+tow@x.com' ) );
	}

	public function test_export_returns_only_confirmed_rows() {
		$GLOBALS['wpdb']->results = array(
			$this->row( 7 ),
			$this->row( 8, 99, '<DispatchNotes>Customer Email: jimbob@example.com</DispatchNotes>' ),
			$this->row( 9, 99, '<DispatchNotes>Customer Email: bob@example.com</DispatchNotes>' ),
		);
		$GLOBALS['gf_tops_test_entries'] = array( array( 'id' => 41 ) );

		$out = GF_Tops_Privacy::export_personal_data( 'bob@example.com', 1 );

		$this->assertTrue( $out['done'] );
		$this->assertSame( array( 'gf-tops-log-7', 'gf-tops-log-9' ), array_column( $out['data'], 'item_id' ), 'jimbob@ row is not exported.' );

		$sql = $GLOBALS['wpdb']->queries[0];
		$this->assertStringContainsString( 'entry_id IN (41)', $sql );
		$this->assertStringContainsString( 'LIMIT 5000', $sql );
	}

	public function test_like_wildcards_in_the_email_are_escaped() {
		GF_Tops_Privacy::export_personal_data( 'per_son%@example.com', 1 );
		$this->assertStringContainsString( "LIKE '%per\\\\_son\\\\%@example.com%'", $GLOBALS['wpdb']->queries[0] );
	}

	public function test_export_paginates() {
		$GLOBALS['wpdb']->results = array_fill( 0, 120, $this->row( 1 ) );

		$page2 = GF_Tops_Privacy::export_personal_data( 'person@example.com', 2 );
		$this->assertCount( 50, $page2['data'] );
		$this->assertFalse( $page2['done'] );

		$page3 = GF_Tops_Privacy::export_personal_data( 'person@example.com', 3 );
		$this->assertCount( 20, $page3['data'] );
		$this->assertTrue( $page3['done'] );
	}

	public function test_erase_deletes_only_confirmed_row_ids() {
		$GLOBALS['wpdb']->results  = array(
			$this->row( 7 ),
			$this->row( 8, 99, 'Customer Email: jimbob@example.com' ),
		);
		$GLOBALS['wpdb']->affected = 1;

		$out = GF_Tops_Privacy::erase_personal_data( 'bob@example.com', 1 );

		$this->assertTrue( $out['items_removed'] );
		$this->assertTrue( $out['done'] );
		$this->assertNotEmpty( $out['messages'], 'Explains rows without the email cannot be matched.' );
		$this->assertSame( 'DELETE FROM `wp_gf_tops_request_log` WHERE id IN (7)', end( $GLOBALS['wpdb']->queries ) );
	}

	public function test_erase_with_no_matches_runs_no_delete() {
		$GLOBALS['wpdb']->results        = array( $this->row( 8, 99, 'Customer Email: jimbob@example.com' ) );
		$GLOBALS['gf_tops_test_entries'] = array();

		$out = GF_Tops_Privacy::erase_personal_data( 'bob@example.com', 1 );

		$this->assertFalse( $out['items_removed'] );
		$this->assertCount( 1, $GLOBALS['wpdb']->queries, 'Only the candidate SELECT ran.' );
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
		$this->assertStringContainsString( 'after about 90 days', $text );
		$this->assertStringNotContainsString( 'never included', $text );
	}
}
