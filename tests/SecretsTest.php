<?php
/**
 * Credential encryption at rest and plaintext migration.
 *
 * @package GF_Tops
 */

use PHPUnit\Framework\TestCase;

class SecretsTest extends TestCase {

	protected function tearDown(): void {
		$GLOBALS['gf_tops_test_filters'] = array();
	}

	public function test_encrypt_round_trip_uses_prefix_and_hides_plaintext() {
		$stored = GF_Tops_Secrets::encrypt_at_rest( 'fake-password-123' );

		$this->assertStringStartsWith( GF_Tops_Secrets::PREFIX, $stored );
		$this->assertStringNotContainsString( 'fake-password-123', $stored );
		$this->assertTrue( GF_Tops_Secrets::is_encrypted( $stored ) );
		$this->assertSame( 'fake-password-123', GF_Tops_Secrets::decrypt_at_rest( $stored ) );
	}

	public function test_encrypt_is_idempotent_for_already_encrypted_values() {
		$stored = GF_Tops_Secrets::encrypt_at_rest( 'fake-key' );
		$this->assertSame( $stored, GF_Tops_Secrets::encrypt_at_rest( $stored ) );
	}

	public function test_empty_and_non_string_values() {
		$this->assertSame( '', GF_Tops_Secrets::encrypt_at_rest( '' ) );
		$this->assertSame( '', GF_Tops_Secrets::encrypt_at_rest( null ) );
		$this->assertSame( '', GF_Tops_Secrets::decrypt_at_rest( '' ) );
		$this->assertSame( '', GF_Tops_Secrets::decrypt_at_rest( null ) );
	}

	public function test_legacy_plaintext_is_returned_unchanged_on_decrypt() {
		$this->assertSame( 'legacy-plaintext', GF_Tops_Secrets::decrypt_at_rest( 'legacy-plaintext' ) );
	}

	public function test_tampered_ciphertext_decrypts_to_empty_string() {
		$stored   = GF_Tops_Secrets::encrypt_at_rest( 'fake-password' );
		$tampered = GF_Tops_Secrets::PREFIX . base64_encode( str_repeat( 'x', 120 ) );

		$this->assertSame( '', GF_Tops_Secrets::decrypt_at_rest( $tampered ) );
		$this->assertSame( '', GF_Tops_Secrets::decrypt_at_rest( GF_Tops_Secrets::PREFIX . 'not-base64!!' ) );
		$this->assertNotSame( '', GF_Tops_Secrets::decrypt_at_rest( $stored ) );
	}

	public function test_encryption_can_be_disabled_by_filter() {
		$GLOBALS['gf_tops_test_filters']['gf_tops_encrypt_secrets_at_rest'] = '__return_false_for_test';
		$this->assertSame( 'plain', GF_Tops_Secrets::encrypt_at_rest( 'plain' ) );
		$this->assertFalse( GF_Tops_Secrets::settings_need_migration( array( 'tops_password' => 'plain' ) ) );
	}

	public function test_settings_helpers_only_touch_secret_fields() {
		$settings = array(
			'enabled'         => '1',
			'tops_user_id'    => 'fake-user',
			'tops_session_id' => 'fake-session',
			'tops_password'   => 'fake-password',
			'tops_auth_key'   => 'fake-auth-key',
		);

		$encrypted = GF_Tops_Secrets::encrypt_settings( $settings );

		$this->assertSame( 'fake-user', $encrypted['tops_user_id'] );
		$this->assertSame( 'fake-session', $encrypted['tops_session_id'] );
		$this->assertTrue( GF_Tops_Secrets::is_encrypted( $encrypted['tops_password'] ) );
		$this->assertTrue( GF_Tops_Secrets::is_encrypted( $encrypted['tops_auth_key'] ) );

		$this->assertSame( $settings, GF_Tops_Secrets::decrypt_settings( $encrypted ) );
	}

	public function test_migration_detects_plaintext_and_is_complete_after_encrypting() {
		$legacy = array(
			'tops_password' => 'fake-password',
			'tops_auth_key' => '',
		);
		$this->assertTrue( GF_Tops_Secrets::settings_need_migration( $legacy ) );

		$migrated = GF_Tops_Secrets::encrypt_settings( $legacy );
		$this->assertFalse( GF_Tops_Secrets::settings_need_migration( $migrated ) );
		$this->assertSame( '', $migrated['tops_auth_key'] );
		$this->assertFalse( GF_Tops_Secrets::settings_need_migration( array() ) );
	}

	public function test_secret_field_list_is_filterable() {
		$GLOBALS['gf_tops_test_filters']['gf_tops_secret_field_names'] = static function ( $names ) {
			$names[] = 'tops_session_id';
			return $names;
		};

		$out = GF_Tops_Secrets::encrypt_settings( array( 'tops_session_id' => 'fake-session' ) );
		$this->assertTrue( GF_Tops_Secrets::is_encrypted( $out['tops_session_id'] ) );
	}
}

/**
 * @return bool
 */
function __return_false_for_test() {
	return false;
}
