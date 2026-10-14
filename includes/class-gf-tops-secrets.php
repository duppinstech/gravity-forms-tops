<?php
/**
 * Encrypt TowX credentials at rest in form meta (AES-256-CTR + HMAC via Gravity Forms).
 *
 * @package GF_Tops
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class GF_Tops_Secrets
 */
class GF_Tops_Secrets {

	const PREFIX = 'gf_tops_e1:';

	/**
	 * Form settings keys stored encrypted.
	 *
	 * @return array<int, string>
	 */
	public static function secret_field_names() {
		$names = array( 'tops_password', 'tops_auth_key' );

		/**
		 * Filter which form settings are encrypted before saving to form meta.
		 *
		 * @param array<int, string> $names Setting keys.
		 */
		return apply_filters( 'gf_tops_secret_field_names', $names );
	}

	/**
	 * Whether encryption is enabled (can disable for debugging).
	 *
	 * @return bool
	 */
	public static function encryption_enabled() {

		/**
		 * Disable encrypting TowX secrets at rest (not recommended).
		 *
		 * @param bool $enabled Default true when Gravity Forms crypto is available.
		 */
		return apply_filters( 'gf_tops_encrypt_secrets_at_rest', self::crypto_available() );
	}

	/**
	 * @return bool
	 */
	public static function crypto_available() {
		return class_exists( 'GFCommon' )
			&& method_exists( 'GFCommon', 'openssl_encrypt' )
			&& method_exists( 'GFCommon', 'openssl_decrypt' )
			&& function_exists( 'openssl_encrypt' );
	}

	/**
	 * Stored value uses our wrapper prefix + GFCommon ciphertext.
	 *
	 * @param string $value Stored value.
	 * @return bool
	 */
	public static function is_encrypted( $value ) {
		return is_string( $value ) && strncmp( $value, self::PREFIX, strlen( self::PREFIX ) ) === 0;
	}

	/**
	 * Encrypt plaintext for database storage.
	 *
	 * @param string $plain Plaintext secret.
	 * @return string Prefixed ciphertext or plaintext if crypto unavailable / empty.
	 */
	public static function encrypt_at_rest( $plain ) {
		if ( ! is_string( $plain ) || $plain === '' ) {
			return '';
		}
		if ( self::is_encrypted( $plain ) ) {
			return $plain;
		}
		if ( ! self::encryption_enabled() ) {
			return $plain;
		}

		$enc = GFCommon::openssl_encrypt( $plain );
		if ( false === $enc || ! is_string( $enc ) ) {
			return $plain;
		}

		return self::PREFIX . $enc;
	}

	/**
	 * Decrypt value read from form meta for runtime use (API, auth test).
	 *
	 * @param string $stored Value from database.
	 * @return string Plaintext or legacy plaintext; empty string on failure.
	 */
	public static function decrypt_at_rest( $stored ) {
		if ( ! is_string( $stored ) || $stored === '' ) {
			return '';
		}
		if ( ! self::is_encrypted( $stored ) ) {
			return $stored;
		}
		if ( ! self::crypto_available() ) {
			return '';
		}

		$payload = substr( $stored, strlen( self::PREFIX ) );
		$plain   = GFCommon::openssl_decrypt( $payload );

		return false !== $plain && is_string( $plain ) ? $plain : '';
	}

	/**
	 * Encrypt every non-empty secret field in a settings array (values already encrypted are kept).
	 *
	 * @param array $settings Add-on form settings.
	 * @return array
	 */
	public static function encrypt_settings( array $settings ) {
		foreach ( self::secret_field_names() as $key ) {
			if ( ! isset( $settings[ $key ] ) || '' === $settings[ $key ] ) {
				continue;
			}
			$settings[ $key ] = self::encrypt_at_rest( (string) $settings[ $key ] );
		}
		return $settings;
	}

	/**
	 * Decrypt every secret field in a settings array for runtime use (API calls).
	 *
	 * @param array $settings Add-on form settings as stored.
	 * @return array
	 */
	public static function decrypt_settings( array $settings ) {
		foreach ( self::secret_field_names() as $key ) {
			if ( ! isset( $settings[ $key ] ) || '' === $settings[ $key ] ) {
				continue;
			}
			$settings[ $key ] = self::decrypt_at_rest( (string) $settings[ $key ] );
		}
		return $settings;
	}

	/**
	 * Whether stored settings still hold a plaintext secret that encryption would change.
	 *
	 * @param array $settings Add-on form settings as stored.
	 * @return bool
	 */
	public static function settings_need_migration( array $settings ) {
		if ( ! self::encryption_enabled() ) {
			return false;
		}
		foreach ( self::secret_field_names() as $key ) {
			if ( isset( $settings[ $key ] ) && is_string( $settings[ $key ] ) && '' !== $settings[ $key ] && ! self::is_encrypted( $settings[ $key ] ) ) {
				return true;
			}
		}
		return false;
	}
}
