<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit('Run via: wp eval-file inc/tests/run.php');
}

\Vie\Schema\SchemaManager::install();

$cases = require __DIR__ . '/cases.php';
$calc  = \Vie\Container::get(\Vie\Service\Pricing\PriceCalculator::class);

$pass = 0;
$fail = 0;

foreach ($cases as $case) {
    $req       = \Vie\DTO\QuoteRequest::fromArray($case['request']);
    $breakdown = $calc->quote($req);
    $actual    = $breakdown->toArray();

    $errors = [];

    foreach ($case['expected'] as $key => $value) {
        $got = $actual[$key] ?? null;
        if ($got !== $value) {
            $errors[] = "  - {$key}: expected " . var_export($value, true)
                      . ', got ' . var_export($got, true);
        }
    }

    if (!empty($case['assert_ticket_positive'])) {
        if (($actual['ticket_subtotal'] ?? 0) <= 0) {
            $errors[] = '  - ticket_subtotal: expected > 0 for combo, got ' . var_export($actual['ticket_subtotal'] ?? null, true);
        }
    }

    $check = $case['extra_check'] ?? null;
    if ($check !== null) {
        $assessments = $actual['child_assessments'] ?? [];
        switch ($check) {
            case 'first_child_free':
                if (($assessments[0]['is_free'] ?? false) !== true) {
                    $errors[] = '  - first_child_free: expected child[0].is_free === true';
                }
                break;
            case 'one_free_one_charged':
                $freeCount = 0;
                foreach ($assessments as $a) {
                    if (!($a['treated_as_adult'] ?? false) && ($a['is_free'] ?? false)) {
                        $freeCount++;
                    }
                }
                if ($freeCount !== 1) {
                    $errors[] = "  - one_free_one_charged: expected exactly 1 free child, got {$freeCount}";
                }
                break;
            case 'both_children_free':
                $freeCount = 0;
                foreach ($assessments as $a) {
                    if (!($a['treated_as_adult'] ?? false) && ($a['is_free'] ?? false)) {
                        $freeCount++;
                    }
                }
                if ($freeCount !== 2) {
                    $errors[] = "  - both_children_free: expected 2 free children, got {$freeCount}";
                }
                break;
            case 'child_treated_as_adult':
                $treated = false;
                foreach ($assessments as $a) {
                    if (($a['treated_as_adult'] ?? false) === true) {
                        $treated = true;
                        break;
                    }
                }
                if (!$treated) {
                    $errors[] = '  - child_treated_as_adult: expected at least 1 child with treated_as_adult === true';
                }
                break;
        }
    }

    if ($errors === []) {
        echo "✓ {$case['name']}\n";
        $pass++;
    } else {
        echo "✗ {$case['name']}\n" . implode("\n", $errors) . "\n";
        $fail++;
    }
}

echo "\n--- Pricing: {$pass} passed, {$fail} failed ---\n\n";

echo "=== Phase 4 — Order E2E ===\n\n";
require __DIR__ . '/order-e2e.php';

echo "\n=== Phase 5 — Payment E2E ===\n\n";
require __DIR__ . '/payment-e2e.php';

echo "\n=== Phase 6 — Auth E2E ===\n\n";
require __DIR__ . '/auth-e2e.php';

echo "\n=== Phase 12 — Smoke E2E ===\n\n";
require __DIR__ . '/smoke-e2e.php';

echo "\n=== Phase 12 — Security E2E ===\n\n";
require __DIR__ . '/security-e2e.php';

echo "\n=== TOTAL: {$pass} passed, {$fail} failed ===\n";
exit($fail === 0 ? 0 : 1);
