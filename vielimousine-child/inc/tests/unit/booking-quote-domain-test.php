<?php
declare(strict_types=1);

if (!function_exists('wp_timezone')) {
    function wp_timezone(): DateTimeZone
    {
        return new DateTimeZone('Asia/Ho_Chi_Minh');
    }
}

if (!function_exists('home_url')) {
    function home_url(string $path = ''): string
    {
        return 'https://vielimousine.test/' . ltrim($path, '/');
    }
}

require __DIR__ . '/../../src/Service/BookingQuote/BookingQuoteException.php';
require __DIR__ . '/../../src/Service/BookingQuote/BookingQuotePolicy.php';
require __DIR__ . '/../../src/Support/QueryBuilder.php';
require __DIR__ . '/../../src/Repository/RepositoryException.php';
require __DIR__ . '/../../src/Repository/AbstractRepository.php';
require __DIR__ . '/../../src/Repository/BookingQuoteRepository.php';
require __DIR__ . '/../../src/Service/Settings/InvoiceSettings.php';
require __DIR__ . '/../../src/Service/BookingQuote/BookingQuoteService.php';
require __DIR__ . '/../../src/Validation/Schemas/BookingQuoteValidation.php';

use Vie\Repository\BookingQuoteRepository;
use Vie\Service\BookingQuote\BookingQuoteException;
use Vie\Service\BookingQuote\BookingQuotePolicy;
use Vie\Service\BookingQuote\BookingQuoteService;
use Vie\Service\Settings\InvoiceSettings;
use Vie\Validation\Schemas\BookingQuoteValidation;

$pass = 0;
$fail = 0;
$assert = static function (string $name, bool $condition, string $detail = '') use (&$pass, &$fail): void {
    if ($condition) {
        echo "  ✓ {$name}\n";
        $pass++;
        return;
    }

    echo "  ✗ {$name}" . ($detail !== '' ? " — {$detail}" : '') . "\n";
    $fail++;
};

$same = static function (string $name, mixed $expected, mixed $actual) use ($assert): void {
    $assert(
        $name,
        $actual === $expected,
        'expected ' . var_export($expected, true) . ', got ' . var_export($actual, true)
    );
};

$invalid = static function (string $name, string $field, callable $callback) use ($assert): void {
    try {
        $callback();
        $assert($name, false, 'expected BookingQuoteException');
    } catch (BookingQuoteException $exception) {
        $assert(
            $name,
            $exception->errorCode === 'validation_error'
                && $exception->httpStatus === 422
                && $exception->field === $field,
            'got code=' . $exception->errorCode
                . ', status=' . $exception->httpStatus
                . ', field=' . var_export($exception->field, true)
        );
    } catch (Throwable $exception) {
        $assert($name, false, 'unexpected ' . $exception::class . ': ' . $exception->getMessage());
    }
};

$policy = new BookingQuotePolicy();
$localExpiry = new DateTimeImmutable('2026-09-22 12:00:00', wp_timezone());
$expiryTimestamp = $localExpiry->getTimestamp();

$quote = static fn(array $overrides = []): array => array_merge([
    'status'         => 'published',
    'valid_until'    => '2026-09-22 12:00:00',
    'total'          => 10_000_000,
    'deposit_amount' => 3_000_000,
    'paid_amount'    => 0,
    'payment_review' => false,
], $overrides);

// Offer lifecycle. The exact boundary is expired, not one second later.
$same('draft remains draft', 'draft', $policy->effectiveStatus($quote(['status' => 'draft']), $expiryTimestamp));
$same('revoked remains revoked', 'revoked', $policy->effectiveStatus($quote(['status' => 'revoked']), $expiryTimestamp - 1));
$same('unknown stored status is not exposed', 'draft', $policy->effectiveStatus($quote(['status' => 'unexpected']), $expiryTimestamp - 1));
$same('published offer is active one second before expiry', 'published', $policy->effectiveStatus($quote(), $expiryTimestamp - 1));
$same('unpaid offer expires at the exact local deadline', 'expired', $policy->effectiveStatus($quote(), $expiryTimestamp));
$same('unpaid offer remains expired after deadline', 'expired', $policy->effectiveStatus($quote(), $expiryTimestamp + 1));
$same(
    'received deposit keeps quote payable after original expiry',
    'published',
    $policy->effectiveStatus($quote(['paid_amount' => 3_000_000]), $expiryTimestamp + 86_400)
);
$same('missing expiry does not invent a deadline', 'published', $policy->effectiveStatus($quote(['valid_until' => null]), $expiryTimestamp));
$same('invalid expiry does not depend on process timezone', 'published', $policy->effectiveStatus($quote(['valid_until' => 'not-a-date']), $expiryTimestamp));
$same('expiry is serialized with WordPress timezone', '2026-09-22T12:00:00+07:00', $policy->expiresAt($quote()));
$same('empty expiry serializes as null', null, $policy->expiresAt($quote(['valid_until' => ''])));

// Payment status is calculated from server amounts; a review flag always wins.
$same('zero payment is unpaid', 'unpaid', $policy->paymentStatus($quote()));
$same('payment below deposit remains unpaid', 'unpaid', $policy->paymentStatus($quote(['paid_amount' => 2_999_999])));
$same('deposit threshold is deposit paid', 'deposit_paid', $policy->paymentStatus($quote(['paid_amount' => 3_000_000])));
$same('payment between deposit and total is deposit paid', 'deposit_paid', $policy->paymentStatus($quote(['paid_amount' => 6_000_000])));
$same('full payment is paid', 'paid', $policy->paymentStatus($quote(['paid_amount' => 10_000_000])));
$same('overpayment is still paid', 'paid', $policy->paymentStatus($quote(['paid_amount' => 11_000_000])));
$same(
    'review flag takes precedence over a full payment',
    'review_required',
    $policy->paymentStatus($quote(['paid_amount' => 10_000_000, 'payment_review' => true]))
);

// Remaining and due amounts never become negative and switch from deposit to balance.
$same('remaining amount starts at total', 10_000_000, $policy->remainingAmount($quote()));
$same('remaining amount subtracts paid ledger total', 7_000_000, $policy->remainingAmount($quote(['paid_amount' => 3_000_000])));
$same('remaining amount is clamped after overpayment', 0, $policy->remainingAmount($quote(['paid_amount' => 12_000_000])));
$same('negative paid amount is ignored', 10_000_000, $policy->remainingAmount($quote(['paid_amount' => -1])));
$same('first due amount is the deposit', 3_000_000, $policy->dueAmount($quote()));
$same('deposit cannot exceed remaining total', 10_000_000, $policy->dueAmount($quote(['deposit_amount' => 15_000_000])));
$same('after deposit the whole balance is due', 7_000_000, $policy->dueAmount($quote(['paid_amount' => 3_000_000])));
$same('fully paid quote has no due amount', 0, $policy->dueAmount($quote(['paid_amount' => 10_000_000])));

// Checkout availability combines lifecycle, reconciliation and arithmetic.
$same('active unpaid published quote can checkout', true, $policy->canCheckout($quote(), $expiryTimestamp - 1));
$same('expired unpaid quote cannot checkout', false, $policy->canCheckout($quote(), $expiryTimestamp));
$same(
    'expired quote with deposit can pay balance',
    true,
    $policy->canCheckout($quote(['paid_amount' => 3_000_000]), $expiryTimestamp + 86_400)
);
$same('draft cannot checkout', false, $policy->canCheckout($quote(['status' => 'draft']), $expiryTimestamp - 1));
$same('revoked quote cannot checkout even after deposit', false, $policy->canCheckout($quote(['status' => 'revoked', 'paid_amount' => 3_000_000]), $expiryTimestamp - 1));
$same('review-required quote cannot checkout', false, $policy->canCheckout($quote(['payment_review' => true]), $expiryTimestamp - 1));
$same('fully paid quote cannot checkout', false, $policy->canCheckout($quote(['paid_amount' => 10_000_000]), $expiryTimestamp - 1));
$same('zero-deposit quote cannot checkout', false, $policy->canCheckout($quote(['deposit_amount' => 0]), $expiryTimestamp - 1));

// Editable payload normalization must ignore all client-owned derived and state fields.
$normalized = BookingQuoteValidation::normalize([
    'id'             => 999,
    'public_id'      => str_repeat('a', 32),
    'sales_user_id'  => 444,
    'status'         => 'published',
    'paid_amount'    => 9_999_999,
    'payment_review' => true,
    'subtotal'       => 1,
    'total'          => 1,
    'deposit_amount' => 1,
    'customer_name'  => "  <b>Nguyễn Văn A</b>\n  ",
    'customer_email' => 'sales@example.com',
    'title'          => 'Tour Hạ Long',
    'description'    => "Dòng 1\r\nDòng 2",
    'trip_start'     => '2026-10-01',
    'trip_end'       => '2026-10-03',
    'image_url'      => '/wp-content/uploads/quote.jpg',
    'lines'          => [[
        'label'      => 'Người lớn',
        'quantity'   => 2,
        'unit'       => 'khách',
        'unit_price' => 1_500_000,
        'line_total' => 1,
    ]],
    'discount'       => 200_000,
    'deposit_type'   => 'percent',
    'deposit_value'  => 30,
    'valid_until'    => '2026-10-01T17:30',
]);

$same('single-line text is plain and trimmed', 'Nguyễn Văn A', $normalized['customer_name'] ?? null);
$same('multiline text preserves normalized newlines', "Dòng 1\nDòng 2", $normalized['description'] ?? null);
$same('line total is calculated server-side', 3_000_000, $normalized['lines'][0]['line_total'] ?? null);
$same('local datetime input gains seconds', '2026-10-01 17:30:00', $normalized['valid_until'] ?? null);
$same('relative same-site image is accepted', '/wp-content/uploads/quote.jpg', $normalized['image_url'] ?? null);
$same('client id is ignored', false, array_key_exists('id', $normalized));
$same('client public token is ignored', false, array_key_exists('public_id', $normalized));
$same('client owner is ignored', false, array_key_exists('sales_user_id', $normalized));
$same('client status is ignored', false, array_key_exists('status', $normalized));
$same('client paid state is ignored', false, array_key_exists('paid_amount', $normalized));
$same('client review state is ignored', false, array_key_exists('payment_review', $normalized));
$same('client subtotal is ignored', false, array_key_exists('subtotal', $normalized));
$same('client total is ignored', false, array_key_exists('total', $normalized));
$same('client deposit amount is ignored', false, array_key_exists('deposit_amount', $normalized));

$same(
    'same-origin HTTPS image is accepted',
    'https://vielimousine.test/wp-content/uploads/quote.jpg',
    BookingQuoteValidation::normalize([
        'image_url' => 'https://vielimousine.test/wp-content/uploads/quote.jpg',
    ])['image_url'] ?? null
);
$invalid('external image host is rejected', 'image_url', static fn() => BookingQuoteValidation::normalize([
    'image_url' => 'https://tracker.example/quote.jpg',
]));
$invalid('same host over HTTP is rejected', 'image_url', static fn() => BookingQuoteValidation::normalize([
    'image_url' => 'http://vielimousine.test/quote.jpg',
]));
$invalid('protocol-relative image is rejected', 'image_url', static fn() => BookingQuoteValidation::normalize([
    'image_url' => '//vielimousine.test/quote.jpg',
]));
$invalid('embedded URL credentials are rejected', 'image_url', static fn() => BookingQuoteValidation::normalize([
    'image_url' => 'https://user:secret@vielimousine.test/quote.jpg',
]));

$invalid('numeric-string money is rejected', 'discount', static fn() => BookingQuoteValidation::normalize([
    'discount' => '100000',
]));
$invalid('negative money is rejected', 'deposit_value', static fn() => BookingQuoteValidation::normalize([
    'deposit_value' => -1,
]));
$invalid('money over the storage bound is rejected', 'discount', static fn() => BookingQuoteValidation::normalize([
    'discount' => BookingQuoteValidation::MAX_MONEY + 1,
]));
$invalid('non-list lines payload is rejected', 'lines', static fn() => BookingQuoteValidation::normalize([
    'lines' => ['first' => ['label' => 'A']],
]));
$invalid('more than 50 lines are rejected', 'lines', static fn() => BookingQuoteValidation::normalize([
    'lines' => array_fill(0, BookingQuoteValidation::MAX_LINES + 1, []),
]));
$invalid('zero line quantity is rejected', 'lines.0.quantity', static fn() => BookingQuoteValidation::normalize([
    'lines' => [['label' => 'A', 'unit' => 'khách', 'quantity' => 0, 'unit_price' => 1]],
]));
$invalid('line quantity over 1000 is rejected', 'lines.0.quantity', static fn() => BookingQuoteValidation::normalize([
    'lines' => [['label' => 'A', 'unit' => 'khách', 'quantity' => 1001, 'unit_price' => 1]],
]));
$invalid('line multiplication cannot exceed money bound', 'lines.0.unit_price', static fn() => BookingQuoteValidation::normalize([
    'lines' => [[
        'label' => 'A',
        'unit' => 'khách',
        'quantity' => 2,
        'unit_price' => intdiv(BookingQuoteValidation::MAX_MONEY, 2) + 1,
    ]],
]));
$invalid('trip end cannot precede start', 'trip_end', static fn() => BookingQuoteValidation::normalize([
    'trip_start' => '2026-10-03',
    'trip_end' => '2026-10-01',
]));
$invalid('invalid calendar date is rejected', 'trip_start', static fn() => BookingQuoteValidation::normalize([
    'trip_start' => '2026-02-30',
]));
$invalid('invalid local datetime is rejected', 'valid_until', static fn() => BookingQuoteValidation::normalize([
    'valid_until' => '2026-02-30T10:00',
]));
$invalid('Zalo contact must be a phone number', 'contact_zalo', static fn() => BookingQuoteValidation::normalize([
    'contact_zalo' => 'https://zalo.me/84901234567',
]));

// Publish validation acts on calculated persisted values, never client totals.
$publishable = [
    'customer_name'  => 'Nguyễn Văn A',
    'customer_phone' => '0901234567',
    'title'          => 'Tour Hạ Long',
    'lines'          => [[
        'label'      => 'Combo',
        'quantity'   => 2,
        'unit'       => 'khách',
        'unit_price' => 5_000_000,
        'line_total' => 10_000_000,
    ]],
    'total'          => 9_800_000,
    'deposit_amount' => 3_000_000,
    'valid_until'    => '2026-09-22 12:00:00',
];

try {
    BookingQuoteValidation::assertPublishable($publishable, $expiryTimestamp - 1);
    $assert('complete future-dated quote is publishable', true);
} catch (Throwable $exception) {
    $assert('complete future-dated quote is publishable', false, $exception->getMessage());
}

$invalid('customer name is required to publish', 'customer_name', static fn() => BookingQuoteValidation::assertPublishable(
    array_merge($publishable, ['customer_name' => '']),
    $expiryTimestamp - 1
));
$invalid('at least one positive line is required', 'lines', static fn() => BookingQuoteValidation::assertPublishable(
    array_merge($publishable, ['lines' => [[
        'label' => 'Miễn phí',
        'quantity' => 1,
        'unit' => 'gói',
        'unit_price' => 0,
        'line_total' => 0,
    ]]]),
    $expiryTimestamp - 1
));
$invalid('zero total cannot be published', 'total', static fn() => BookingQuoteValidation::assertPublishable(
    array_merge($publishable, ['total' => 0]),
    $expiryTimestamp - 1
));
$invalid('zero deposit cannot be published', 'deposit_value', static fn() => BookingQuoteValidation::assertPublishable(
    array_merge($publishable, ['deposit_amount' => 0]),
    $expiryTimestamp - 1
));
$invalid('deposit cannot exceed total', 'deposit_value', static fn() => BookingQuoteValidation::assertPublishable(
    array_merge($publishable, ['deposit_amount' => 9_800_001]),
    $expiryTimestamp - 1
));
$invalid('expiry boundary is not publishable', 'valid_until', static fn() => BookingQuoteValidation::assertPublishable(
    $publishable,
    $expiryTimestamp
));

// BookingQuoteService's arithmetic and public projection stay deterministic without a DB.
$service = new BookingQuoteService(new BookingQuoteRepository(), $policy, new InvoiceSettings());
$calculate = new ReflectionMethod(BookingQuoteService::class, 'calculate');
$calculated = $calculate->invoke($service, [
    'lines' => [['line_total' => 999]],
    'discount' => 0,
    'deposit_type' => 'percent',
    'deposit_value' => 10,
]);
$same('service computes subtotal from server line totals', 999, $calculated['subtotal'] ?? null);
$same('service computes total after discount', 999, $calculated['total'] ?? null);
$same('percent deposit rounds up fractional VND', 100, $calculated['deposit_amount'] ?? null);

$fixed = $calculate->invoke($service, [
    'lines' => [['line_total' => 1_000_000]],
    'discount' => 125_000,
    'deposit_type' => 'fixed',
    'deposit_value' => 300_000,
]);
$same('fixed deposit is retained exactly', 300_000, $fixed['deposit_amount'] ?? null);
$same('discount is applied before fixed deposit', 875_000, $fixed['total'] ?? null);
$invalid('discount above subtotal is rejected by service', 'discount', static function () use ($calculate, $service): void {
    $calculate->invoke($service, [
        'lines' => [['line_total' => 100]],
        'discount' => 101,
        'deposit_type' => 'percent',
        'deposit_value' => 10,
    ]);
});
$invalid('percent deposit above 100 is rejected by service', 'deposit_value', static function () use ($calculate, $service): void {
    $calculate->invoke($service, [
        'lines' => [['line_total' => 100]],
        'discount' => 0,
        'deposit_type' => 'percent',
        'deposit_value' => 101,
    ]);
});
$invalid('service subtotal addition cannot overflow money bound', 'lines', static function () use ($calculate, $service): void {
    $calculate->invoke($service, [
        'lines' => [
            ['line_total' => BookingQuoteValidation::MAX_MONEY],
            ['line_total' => 1],
        ],
        'discount' => 0,
        'deposit_type' => 'fixed',
        'deposit_value' => 1,
    ]);
});

$projectionSource = [
    'id' => 321,
    'public_id' => str_repeat('b', 32),
    'code' => 'BQ-260922-ABC123',
    'status' => 'published',
    'sales_user_id' => 88,
    'customer_name' => 'Nguyễn Văn A',
    'customer_phone' => '0901234567',
    'customer_email' => 'customer@example.com',
    'title' => 'Tour Hạ Long',
    'lines' => [['label' => 'Combo', 'quantity' => 1, 'unit' => 'gói', 'unit_price' => 1_000_000, 'line_total' => 1_000_000]],
    'discount' => 0,
    'deposit_type' => 'percent',
    'deposit_value' => 30,
    'subtotal' => 1_000_000,
    'total' => 1_000_000,
    'deposit_amount' => 300_000,
    'paid_amount' => 0,
    'payment_review' => true,
    'valid_until' => '2026-09-30 12:00:00',
    'brand' => ['company_name' => 'Vie Limousine'],
    'created_at' => '2026-09-22 10:00:00',
    'updated_at' => '2026-09-22 10:00:00',
    'published_at' => '2026-09-22 10:00:00',
    'payment_id' => 'secret-payment-id',
    'raw_payload' => ['secret' => 'must-not-leak'],
];
$public = $service->publicView($projectionSource);
$same('public projection keeps opaque public id', str_repeat('b', 32), $public['public_id'] ?? null);
$same('public projection builds booking URL', 'https://vielimousine.test/booking/' . str_repeat('b', 32), $public['public_url'] ?? null);
$same('public projection includes ISO expiry', '2026-09-30T12:00:00+07:00', $public['expires_at'] ?? null);
$same('public projection includes review payment status', 'review_required', $public['payment_status'] ?? null);
foreach ([
    'id', 'sales_user_id', 'customer_phone', 'customer_email',
    'created_at', 'updated_at', 'published_at', 'payment_id', 'raw_payload', 'payment_review',
] as $forbidden) {
    $same("public projection omits {$forbidden}", false, array_key_exists($forbidden, $public));
}

echo "\n--- Booking quote domain: {$pass} passed, {$fail} failed ---\n";
exit($fail === 0 ? 0 : 1);
