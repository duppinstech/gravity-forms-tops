<?php
/**
 * Owner decisions of 2026-10-14: log retention, generic visitor errors, support contact, email log link.
 *
 * @package GF_Tops
 */

use PHPUnit\Framework\TestCase;

/**
 * Add-on that records the make key pre_render_populate passes on.
 */
class GF_Tops_Test_Populate_Addon extends GF_Tops_Test_Addon {
	public $seen_make = null;

	protected function is_form_enabled( $form_id ) {
		return true;
	}

	protected function populate_vehicle_choices( $form, array $settings, $make_key = '' ) {
		$this->seen_make = $make_key;
		return $form;
	}
}

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
		$GLOBALS['gf_tops_test_plugin_settings'] = array( 'request_log_retention_days' => '999999999999999999' );
		$this->assertSame( 36500, $this->addon->get_request_log_retention_days(), 'Capped at 100 years.' );

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

	public function test_posted_make_must_be_a_single_value() {
		$addon = new GF_Tops_Test_Populate_Addon();
		$form  = array(
			'id'               => 6,
			'fields'           => array(),
			'gravityformstops' => array(
				'enabled'              => '1',
				'tops_fields_make_key' => '5',
				'tops_fields_model_key' => '6',
			),
		);

		$_POST = array( 'gform_submit' => '6', 'input_5' => array( 'x', 'y' ) );
		$addon->pre_render_populate( $form );
		$this->assertSame( '', $addon->seen_make, 'An array is ignored (no warning, no lookup).' );

		$_POST = array( 'gform_submit' => '6', 'input_5' => ' 14 ' );
		$addon->pre_render_populate( $form );
		$this->assertSame( '14', $addon->seen_make );

		$_POST = array( 'gform_submit' => '7', 'input_5' => '14' );
		$addon->pre_render_populate( $form );
		$this->assertSame( '', $addon->seen_make, 'Only a submission of this form loads models.' );

		$_POST = array();
	}

	public function test_support_link_on_the_plugins_screen() {
		$info = new GF_Tops_Plugin_Info(
			array(
				'file'         => GF_TOPS_FILE,
				'settings_url' => 'admin.php?page=gf_settings&subview=gravityformstops',
				'help_url'     => '',
				'support_url'  => 'mailto:support@duppinstech.com',
			)
		);
		$GLOBALS['gf_tops_test_user_can'] = true;
		$meta                             = $info->row_meta(
			array( 'Version 1.5.0', '<a href="https://duppinstech.com">Duppins Technology</a>' ),
			plugin_basename( GF_TOPS_FILE )
		);
		$sponsor = $meta[2];
		$this->assertStringContainsString( 'https://github.com/sponsors/oduppinsjr', $sponsor );
		$this->assertStringContainsString( 'Sponsor', $sponsor );
		$this->assertStringContainsString( 'target="_blank"', $sponsor );
		$this->assertStringContainsString( 'mailto:support@duppinstech.com', end( $meta ) );
		$this->assertStringContainsString( 'plugin-information', implode( '', $meta ) );

		$already = $info->row_meta(
			array( '<a href="https://duppinstech.com">Duppins Technology</a>', '<a class="thickbox" href="plugin-install.php?tab=plugin-information">View details</a>' ),
			plugin_basename( GF_TOPS_FILE )
		);
		$this->assertSame( 1, substr_count( implode( '', $already ), 'plugin-information' ) );

		$this->assertSame( array( 'x' ), $info->row_meta( array( 'x' ), 'other/other.php' ) );

		$links = $info->action_links( array( 'Deactivate' ) );
		$this->assertSame( 'Settings', wp_strip_all_tags( $links[0] ) );
		$this->assertStringContainsString( 'page=gf_settings&subview=gravityformstops', $links[0] );
		$this->assertStringNotContainsString( 'Help', implode( '', $links ) );
		$this->assertSame( 'Deactivate', $links[1] );

		$GLOBALS['gf_tops_test_user_can'] = false;
		$this->assertSame( array( 'Deactivate' ), $info->action_links( array( 'Deactivate' ) ) );
		$hidden = $info->row_meta( array( '<a href="https://duppinstech.com">Author</a>' ), plugin_basename( GF_TOPS_FILE ) );
		$this->assertStringNotContainsString( 'mailto:', implode( '', $hidden ) );
	}

	public function test_view_details_reads_readme_and_wporg_images() {
		$info   = new GF_Tops_Plugin_Info( array( 'file' => GF_TOPS_FILE ) );
		$readme = $info->readme();
		$this->assertSame( '1.6.0', $readme['headers']['stable tag'] );
		$this->assertArrayHasKey( 'description', $readme['sections'] );
		$this->assertArrayHasKey( 'installation', $readme['sections'] );
		$this->assertArrayHasKey( 'frequently asked questions', $readme['sections'] );
		$this->assertArrayHasKey( 'changelog', $readme['sections'] );

		$info_obj = $info->filter_plugins_api( false, 'plugin_information', (object) array( 'slug' => 'gravity-forms-tops' ) );
		$this->assertIsObject( $info_obj );
		$this->assertArrayHasKey( 'description', $info_obj->sections );
		$this->assertArrayHasKey( 'installation', $info_obj->sections );
		$this->assertArrayHasKey( 'faq', $info_obj->sections );
		$this->assertArrayHasKey( 'changelog', $info_obj->sections );
		$this->assertStringContainsString( 'Setup checklist', $info_obj->sections['changelog'] );
		$this->assertStringContainsString( 'TowXchange', $info_obj->sections['faq'] );
		$this->assertStringContainsString( '90 days', $info_obj->sections['faq'] );
		$this->assertSame( false, $info->filter_plugins_api( false, 'plugin_information', (object) array( 'slug' => 'other-plugin' ) ) );

		$kept = $info->filter_plugins_api(
			(object) array( 'sections' => array( 'changelog' => '<p>Release notes</p>' ) ),
			'plugin_information',
			(object) array( 'slug' => 'gravity-forms-tops' )
		);
		$this->assertSame( '<p>Release notes</p>', $kept->sections['changelog'] );

		$this->assertStringContainsString( 'banner-772x250.jpg', $info_obj->banners['low'] );
		$this->assertStringContainsString( 'banner-1544x500.jpg', $info_obj->banners['high'] );
		$this->assertStringContainsString( 'icon-128x128.png', $info_obj->icons['1x'] );
		$this->assertStringContainsString( 'icon-256x256.png', $info_obj->icons['2x'] );
		$this->assertArrayHasKey( 'screenshots', $info_obj->sections );
		$this->assertStringContainsString( 'screenshot-1.jpg', $info_obj->sections['screenshots'] );
		$this->assertStringContainsString( 'screenshot-2.jpg', $info_obj->sections['screenshots'] );
		$this->assertStringContainsString( 'while QA is down', $info_obj->sections['screenshots'] );
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

	public function test_public_name_is_callpost_and_contributions_use_the_dco() {
		$root     = dirname( __DIR__ );
		$header   = file_get_contents( $root . '/gravity-forms-tops.php' );
		$readme   = file_get_contents( $root . '/readme.txt' );
		$contrib  = file_get_contents( $root . '/CONTRIBUTING.md' );
		$dco      = file_get_contents( $root . '/DCO' );
		$plan     = file_get_contents( $root . '/PLAN.md' );
		$standard = file_get_contents( $root . '/docs/github-updater-standard.md' );

		$this->assertStringContainsString( 'Plugin Name:       GF TowX Integration', $header );
		$this->assertStringContainsString( '=== GF TowX Integration ===', $readme );
		$this->assertStringContainsString( 'not affiliated with or endorsed by Rocketgenius', $readme );
		$this->assertStringContainsString( 'AS IS', $readme );
		$this->assertStringContainsString( 'Signed-off-by:', $contrib );
		$this->assertStringContainsString( "Developer's Certificate of Origin 1.1", $dco );
		$this->assertStringNotContainsString( 'Landing Page Manager', $plan );
		$this->assertStringNotContainsString( 'LPM_GitHub_Updater', $standard );
	}
}
