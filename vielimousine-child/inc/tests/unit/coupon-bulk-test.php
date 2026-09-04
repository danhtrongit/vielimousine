<?php
declare(strict_types=1);

require __DIR__ . '/../../src/Service/Coupon/CouponCodeGenerator.php';
require __DIR__ . '/../../src/Service/Coupon/CouponBulkService.php';
require __DIR__ . '/../../src/Validation/Schemas/CouponBulkValidation.php';

use Vie\Service\Coupon\CouponBulkService;
use Vie\Service\Coupon\CouponCodeGenerator;
use Vie\Validation\Schemas\CouponBulkValidation;

$pass = 0; $fail = 0;
$assert = function (string $name, bool $cond, string $detail = '') use (&$pass, &$fail): void {
    if ($cond) { echo "  ✓ {$name}\n"; $pass++; }
    else { echo "  ✗ {$name}" . ($detail ? " — {$detail}" : '') . "\n"; $fail++; }
};

$fieldsOf = static fn(array $errors): array => array_column($errors, 'field');

// ── CouponCodeGenerator ──
$gen = new CouponCodeGenerator();

$codes = $gen->generate(200, 'VIE', '-26', 8);
$assert('sinh đúng số lượng', count($codes) === 200, 'got ' . count($codes));
$assert('mọi mã duy nhất', count(array_unique($codes)) === 200);
$assert(
    'mã đúng dạng prefix+random+suffix',
    count(array_filter($codes, fn($c) => (bool) preg_match('/^VIE[A-Z2-9]{8}-26$/', $c))) === 200
);
$assert(
    'bảng chữ không có ký tự dễ nhầm (O/0/I/1)',
    !preg_match('/[O0I1]/', implode('', array_map(fn($c) => substr($c, 3, 8), $codes)))
);

// Keyspace 1 ký tự = 32 mã; 30 mã đã bị chiếm → tối đa còn 2.
$alphabet = str_split(CouponCodeGenerator::ALPHABET);
$taken    = [];
foreach (array_slice($alphabet, 0, 30) as $ch) {
    $taken[$ch] = true;
}
$left = $gen->generate(10, '', '', 1, $taken);
$assert('không cấp lại mã đã bị chiếm', count($left) === 2, 'got ' . count($left));
$assert('mã còn lại nằm ngoài set taken', array_intersect($left, array_keys($taken)) === []);

$assert('count <= 0 trả về rỗng', $gen->generate(0, 'VIE', '', 8) === []);
$assert('normalizeAffix trim + in hoa', CouponCodeGenerator::normalizeAffix('  vie-26 ') === 'VIE-26');

// ── CouponBulkValidation::rules ──
$rules = CouponBulkValidation::rules();
$assert(
    'quantity chặn theo MAX_QUANTITY',
    $rules['quantity'] === 'required|int|min:1|max:' . CouponBulkService::MAX_QUANTITY,
    $rules['quantity']
);
$assert(
    'random_length chặn theo trần generator',
    $rules['random_length'] === 'nullable|int|min:' . CouponCodeGenerator::MIN_RANDOM_LENGTH
        . '|max:' . CouponCodeGenerator::MAX_RANDOM_LENGTH,
    $rules['random_length']
);

// ── CouponBulkValidation::crossValidate ──
$ok = [
    'quantity'      => 50,
    'prefix'        => 'VIE',
    'suffix'        => '',
    'random_length' => 8,
    'template'      => [
        'type'       => 'percentage',
        'value'      => 10,
        'valid_from' => '2026-01-01 00:00:00',
        'valid_to'   => '2026-12-31 23:59:59',
    ],
];
$assert('cấu hình hợp lệ không có lỗi', CouponBulkValidation::crossValidate($ok) === []);

$badAffix = $ok;
$badAffix['prefix'] = 'VIE HÈ!';
$assert(
    'tiền tố có ký tự lạ bị chặn',
    in_array('prefix', $fieldsOf(CouponBulkValidation::crossValidate($badAffix)), true)
);

$tooLong = $ok;
$tooLong['prefix']        = str_repeat('A', 20);
$tooLong['suffix']        = str_repeat('B', 20);
$tooLong['random_length'] = 16;
$assert(
    'tổng độ dài mã vượt 50 bị chặn',
    in_array('random_length', $fieldsOf(CouponBulkValidation::crossValidate($tooLong)), true)
);

$overPercent = $ok;
$overPercent['template']['value'] = 120;
$assert(
    'giảm % > 100 bị chặn',
    in_array('template.value', $fieldsOf(CouponBulkValidation::crossValidate($overPercent)), true)
);

$fixedOver = $ok;
$fixedOver['template']['type']  = 'fixed';
$fixedOver['template']['value'] = 500000;
$assert('giảm tiền cố định > 100 vẫn hợp lệ', CouponBulkValidation::crossValidate($fixedOver) === []);

$badRange = $ok;
$badRange['template']['valid_to'] = '2025-01-01 00:00:00';
$assert(
    'valid_to trước valid_from bị chặn',
    in_array('template.valid_to', $fieldsOf(CouponBulkValidation::crossValidate($badRange)), true)
);

echo "\n--- Coupon bulk: {$pass} passed, {$fail} failed ---\n";
exit($fail === 0 ? 0 : 1);
