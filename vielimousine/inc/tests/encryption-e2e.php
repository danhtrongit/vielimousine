<?php
declare(strict_types=1);
/**
 * Parent-theme Encryption trait hardening (C2).
 * Run: docker exec vie_cli wp eval '$pass=0;$fail=0; require ".../vielimousine/inc/tests/encryption-e2e.php"; ...'
 */
if (!defined('ABSPATH')) { throw new RuntimeException('Run inside WP context'); }
if (!defined('INC_PATH')) { throw new RuntimeException('Parent theme functions.php not loaded (INC_PATH missing)'); }

$pass = $pass ?? 0;
$fail = $fail ?? 0;
$assert = function (string $name, bool $cond, string $detail = '') use (&$pass, &$fail): void {
    if ($cond) { echo "  ✓ {$name}\n"; $pass++; }
    else { echo "  ✗ {$name}" . ($detail !== '' ? " — {$detail}" : '') . "\n"; $fail++; }
};

echo "Parent Encryption (C2): no hardcoded secret + working round-trip\n";

$LEAKED = 'd24eebeca3db6407c18d4de572fff114';

$traitSrc = (string) @file_get_contents(INC_PATH . 'classes/Utilities/Traits/Encryption.php');
$assert('trait no longer hardcodes the leaked key', strpos($traitSrc, $LEAKED) === false);
$assert('trait has no 32-hex secret literal at all', !preg_match('/[0-9a-f]{32}/i', $traitSrc));

$keySrc = (string) @file_get_contents(INC_PATH . 'encryption-key.php');
$assert('key file no longer hardcodes the leaked key', strpos($keySrc, $LEAKED) === false);

// Round-trip must still work through a class using the trait (regression guard).
$box = new class {
    use \HD\Utilities\Traits\Encryption;
    public function enc(?string $s): ?string { return self::encode($s); }
    public function dec(?string $s): ?string { return self::decode($s); }
};
$plain = 'hello-vié-123 ;#';
$ct = $box->enc($plain);
$assert('encode returns ciphertext (not plaintext)', is_string($ct) && $ct !== '' && $ct !== $plain);
$assert('decode(encode(x)) === x', $box->dec($ct) === $plain);
$assert('decode(null) === null', $box->dec(null) === null);
