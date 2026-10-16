<?php
/**
 * Owner decisions of 2026-10-14: log retention, generic visitor errors, support contact, email log link.
 *
 * @package GF_Tops
 */

use PHPUnit\Framework\TestCase;

class OwnerDecisionsTest extends TestCase {

	/** @var GF_Tops_Test_Addon */
	private $addon;

	protected function setUp(): void {
		$GLOBALS['wpdb']                         = new GF_Tops_Test_Wpdb();
		$GLOBALS['gf_tops_test_options']         = array();
		$GLOBALS['gf_tops_test_transients']      = array();
		$GLOBALS['gf_tops_test_filters']         = array();
		$GLOBALS['gf_tops_test_plugin_settings'] = array();
		$GLOBALS['gf_tops_test_logged_in']       = false;
		$GLOBALS['gf_tops_test_user_can']        = false;
		$GLOBALS['gf_tops_test_mail']            = array();
		$this->addon                             = new GF_Tops_Test_Addon();
	}

	private function call( $method, ...$args ) {
		$ref = new ReflectionMethod( GF_Tops_Addon::class, $method );
		$ref->setAccessible( true );
		return $ref->invoke( $this->addon, ...$args );
	}

	public function test_retention_defaults_to_90_days_and_0_keeps_forever() {
		$this->assertSame( 90, $this->addon->get_request_log_retention_days() );

		$GLOBALS['gf_tops_test_plugin_settings'] = array( 'request_log_retention_days' => '30' );
		$this->assertSame( 30, $this->addon->get_request_log_retention_days() );

		$GLOBALS['gf_tops_test_plugin_settings'] = array( 'request_log_retention_days' => '0' );
		$this->assertSame( 0, $this->addon->get_request_log_retention_days() );
		$this->assertSame( 0, $this->addon->purge_request_log() );
		$this->assertSame( array(), $GLOBALS['wpdb']->queries, 'Nothing is deleted when retention is 0.' );

		foreach ( array( '-1', '90 days', 'abc', '3.5' ) as $invalid ) {
			$GLOBALS['gf_tops_test_plugin_settings'] = array( 'request_log_retention_days' => $invalid );
			$this->assertSame( 90, $this->addon->get_request_log_retention_days(), "'$invalid' falls back to the default." );
		}
		$GLOBALS['gf_tops_test_plugin_settings'] = array( 'request_log_retention_days' => ' 45 ' );
		$this->assertSame( 45, $this->addon->get_request_log_retention_days() );

		$GLOBALS['gf_tops_test_filters']['gf_tops_request_log_retention_days'] = static function () {
			return 7;
		};
		$this->assertSame( 7, $this->addon->get_request_log_retention_days() );
	}

	public function test_purge_deletes_rows_older_than_the_cutoff_in_batches() {
		$GLOBALS['wpdb']->affected = 1000;
		$deleted                   = GF_Tops_Request_Log::purge_older_than( 90, 1000, 3 );

		$this->assertSame( 3000, $deleted, 'Keeps deleting full batches up to the safety limit.' );
		$this->assertCount( 3, $GLOBALS['wpdb']->queries );
		$this->assertMatchesRegularExpression( "/^DELETE FROM `wp_gf_tops_request_log` WHERE created_at < '\\d{4}-\\d{2}-\\d{2} \\d{2}:\\d{2}:\\d{2}' LIMIT 1000$/", $GLOBALS['wpdb']->queries[0] );

		$cutoff = gmdate( 'Y-m-d', time() - 90 * DAY_IN_SECONDS );
		$this->assertStringContainsString( $cutoff, $GLOBALS['wpdb']->queries[0] );

		$GLOBALS['wpdb']          = new GF_Tops_Test_Wpdb();
		$GLOBALS['wpdb']->affected = 10;
		$this->assertSame( 10, GF_Tops_Request_Log::purge_older_than( 90 ) );
		$this->assertCount( 1, $GLOBALS['wpdb']->queries, 'Stops after a partial batch.' );

		$this->assertSame( 0, GF_Tops_Request_Log::purge_older_than( 0 ) );
	}

	public function test_visitors_get_a_generic_error_without_technical_detail() {
		$html = $this->call( 'build_default_tops_confirmation_error', 'cURL error 28: secret-host timed out', '', 3 );

		$this->assertStringContainsString( 'could not send it to our dispatch system', $html );
		$this->assertStringContainsString( 'Please contact us directly', $html, 'Fallback when no contact line is set.' );
		$this->assertStringNotContainsString( 'cURL', $html );
		$this->assertStringNotContainsString( 'request log', $html );

		$with_contact = $this->call( 'build_default_tops_confirmation_error', 'x', '<a href="tel:5550100">Call 555-0100</a>', 3 );
		$this->assertStringContainsString( 'Call 555-0100', $with_contact );
		$this->assertStringNotContainsString( 'Please contact us directly', $with_contact );
	}

	public function test_staff_who_can_open_the_log_see_the_error_and_a_link() {
		$GLOBALS['gf_tops_test_logged_in'] = true;
		$GLOBALS['gf_tops_test_user_can']  = true;

		$html = $this->call( 'build_default_tops_confirmation_error', 'Invalid <AuthenticationKey>', '', 3 );
		$this->assertStringContainsString( 'Staff only', $html );
		$this->assertStringContainsString( 'Invalid &lt;AuthenticationKey&gt;', $html, 'Escaped.' );
		$this->assertStringContainsString( 'admin.php?page=gf_tops_request_log&id=3', $html );

		$unknown = $this->call( 'build_default_tops_confirmation_unknown', '', 3 );
		$this->assertStringContainsString( 'did not return a Call ID', $unknown );

		$GLOBALS['gf_tops_test_user_can'] = false;
		$this->assertStringNotContainsString( 'Staff only', $this->call( 'build_default_tops_confirmation_error', 'x', '', 3 ), 'Logged in without log access: visitor view.' );
	}

	public function test_unknown_result_has_a_contact_fallback_for_visitors() {
		$html = $this->call( 'build_default_tops_confirmation_unknown', '', 3 );
		$this->assertStringContainsString( 'Please contact us directly', $html );
		$this->assertStringNotContainsString( 'Staff only', $html );

		$with_contact = $this->call( 'build_default_tops_confirmation_unknown', '<a href="tel:5550100">Call</a>', 3 );
		$this->assertStringNotContainsString( 'Please contact us directly', $with_contact );
	}

	public function test_privacy_text_follows_the_retention_setting() {
		$this->assertStringContainsString( 'after about 90 days', GF_Tops_Privacy::policy_text( 90 ) );
		$this->assertStringContainsString( 'after about 30 days', GF_Tops_Privacy::policy_text( 30 ) );
		$this->assertStringContainsString( 'after about 1 day,', GF_Tops_Privacy::policy_text( 1 ) );

		$forever = GF_Tops_Privacy::policy_text( 0 );
		$this->assertStringContainsString( 'kept until they are erased on request', $forever );
		$this->assertStringNotContainsString( 'deleted automatically', $forever );
	}

	public function test_existing_cleanup_schedule_is_not_moved() {
		$GLOBALS['gf_tops_test_cron'] = array( GF_Tops_Request_Log::PURGE_HOOK => 12345 );
		$this->addon->init();
		$this->assertSame( 12345, $GLOBALS['gf_tops_test_cron'][ GF_Tops_Request_Log::PURGE_HOOK ] );
	}

	public function test_pages_with_the_model_dropdown_cap_the_page_cache_ttl() {
		$GLOBALS['gf_tops_test_actions'] = array();
		$this->call( 'limit_page_cache_ttl', 6 );

		$this->assertSame( array( 'litespeed_control_set_ttl', array( 36000 ) ), $GLOBALS['gf_tops_test_actions'][0] );
		$this->assertSame( array( 'gf_tops_limit_page_cache_ttl', array( 36000, 6 ) ), $GLOBALS['gf_tops_test_actions'][1] );

		$GLOBALS['gf_tops_test_actions']                                 = array();
		$GLOBALS['gf_tops_test_filters']['gf_tops_models_require_nonce'] = static function () {
			return false;
		};
		$this->call( 'limit_page_cache_ttl', 6 );
		$this->assertSame( array(), $GLOBALS['gf_tops_test_actions'], 'No nonce, no limit needed.' );
	}

	public function test_support_link_on_the_plugins_screen() {
		$links = $this->addon->add_plugin_row_meta( array( 'Version 1.5.0' ), plugin_basename( GF_TOPS_FILE ) );
		$this->assertStringContainsString( 'mailto:support@duppinstech.com', end( $links ) );

		$this->assertSame( array( 'x' ), $this->addon->add_plugin_row_meta( array( 'x' ), 'other/other.php' ) );
	}

	public function test_failure_email_links_to_the_request_log_and_never_contains_credentials() {
		$GLOBALS['gf_tops_test_plugin_settings'] = array( 'notification_email' => 'ops@example.test' );
		$xml                                     = GF_Tops_Xml::build_create_call_xml(
			array(
				'user_id'            => 'fake-user',
				'password'           => 'fake-password',
				'session_id'         => 'fake-session',
				'authentication_key' => 'fake-auth-key',
			),
			array_fill_keys( array( 'location', 'caller_name', 'caller_phone', 'destination', 'vehicle_info', 'tag_number', 'tag_state', 'dispatch_notes', 'year', 'make_key', 'model_key', 'color_key' ), 'x' )
		);

		$this->call( 'notify_error', 'TOPS API returned an error', 'Bad key', $xml, '<towXResponse/>', 3 );

		$this->assertCount( 1, $GLOBALS['gf_tops_test_mail'] );
		$body = $GLOBALS['gf_tops_test_mail'][0]['message'];
		$this->assertStringContainsString( 'admin.php?page=gf_tops_request_log&id=3', $body );
		foreach ( array( 'fake-user', 'fake-password', 'fake-session', 'fake-auth-key' ) as $secret ) {
			$this->assertStringNotContainsString( $secret, $body );
		}
	}

	public function test_daily_cleanup_is_scheduled_a_day_out() {
		$GLOBALS['gf_tops_test_cron'] = array();
		$this->addon->init();

		$this->assertArrayHasKey( GF_Tops_Request_Log::PURGE_HOOK, $GLOBALS['gf_tops_test_cron'] );
		$this->assertGreaterThanOrEqual( time() + DAY_IN_SECONDS - 5, $GLOBALS['gf_tops_test_cron'][ GF_Tops_Request_Log::PURGE_HOOK ] );
	}
}
