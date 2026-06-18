<?php

namespace HD\Utilities\Traits;

\defined( 'ABSPATH' ) || die;

trait Encryption {

	private static ?string $method = null;
	private static ?string $secretKey = null;

	// -------------------------------------------------------------

	/**
	 * @return void
	 */
	private static function loadKeys(): void {
		if ( ! is_null( self::$method ) && ! is_null( self::$secretKey ) ) {
			return;
		}

		$cipher_method = null;
		$secret_key    = null;

		$keyFile = INC_PATH . 'encryption-key.php';
		if ( is_file( $keyFile ) ) {
			include $keyFile; // đặt $cipher_method, $secret_key (ưu tiên hằng số / option)
		}

		// Hằng số wp-config (nếu có) luôn được ưu tiên — không hard-code khóa trong repo.
		if ( \defined( 'VIE_ENCRYPTION_KEY' ) ) {
			$secret_key = VIE_ENCRYPTION_KEY;
		}
		if ( \defined( 'VIE_ENCRYPTION_CIPHER' ) ) {
			$cipher_method = VIE_ENCRYPTION_CIPHER;
		}

		if ( empty( $secret_key ) ) {
			throw new \RuntimeException( 'Encryption key chưa được cấu hình (define VIE_ENCRYPTION_KEY trong wp-config.php).' );
		}

		self::$method    = $cipher_method ?: 'AES-128-CBC';
		self::$secretKey = $secret_key;
	}

	// -------------------------------------------------------------

	/**
	 * Encode a string with encryption
	 *
	 * @param string|null $data
	 *
	 * @return string|null
	 * @throws \Random\RandomException
	 */
	public static function encode( ?string $data ): ?string {
		if ( is_null( $data ) ) {
			return null;
		}

		self::loadKeys();

		$iv        = random_bytes( openssl_cipher_iv_length( self::$method ) );
		$key       = substr( hash( 'sha256', self::$secretKey ), 0, 16 );
		$encrypted = openssl_encrypt( $data, self::$method, $key, 0, $iv );

		return base64_encode( $iv . $encrypted );
	}

	// -------------------------------------------------------------

	/**
	 * Decode an encrypted string
	 *
	 * @param string|null $encryptedData
	 *
	 * @return string|null
	 */
	public static function decode( ?string $encryptedData ): ?string {
		if ( is_null( $encryptedData ) ) {
			return null;
		}

		self::loadKeys();

		$data = base64_decode( $encryptedData );

		$ivLength  = openssl_cipher_iv_length( self::$method );
		$iv        = substr( $data, 0, $ivLength );
		$encrypted = substr( $data, $ivLength );

		$key       = substr( hash( 'sha256', self::$secretKey ), 0, 16 );
		$decrypted = openssl_decrypt( $encrypted, self::$method, $key, 0, $iv );

		return $decrypted !== false ? $decrypted : null;
	}
}
