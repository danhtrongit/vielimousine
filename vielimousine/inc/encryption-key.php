<?php
/**
 * Nguồn khóa mã hóa — KHÔNG hard-code secret trong repo.
 *
 * Thứ tự ưu tiên:
 *   1. Hằng số trong wp-config.php:  define('VIE_ENCRYPTION_KEY', '...');  (khuyến nghị)
 *   2. Option tự sinh ngẫu nhiên, lưu trong DB (mỗi site một khóa, không nằm trong git).
 *
 * Lưu ý: trait Encryption sẽ ghi đè các biến này bằng hằng số wp-config nếu có.
 */

\defined( 'ABSPATH' ) || die;

$cipher_method = \defined( 'VIE_ENCRYPTION_CIPHER' ) ? VIE_ENCRYPTION_CIPHER : 'AES-128-CBC';

if ( \defined( 'VIE_ENCRYPTION_KEY' ) ) {
	$secret_key = VIE_ENCRYPTION_KEY;
} else {
	$secret_key = \get_option( 'vie_encryption_key' );
	if ( ! $secret_key ) {
		$secret_key = \bin2hex( \random_bytes( 32 ) );
		\add_option( 'vie_encryption_key', $secret_key, '', false ); // autoload=no
	}
}
