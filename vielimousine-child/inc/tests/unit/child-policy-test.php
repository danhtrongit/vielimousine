<?php
declare(strict_types=1);

require __DIR__ . '/../../src/DTO/ChildAssessment.php';
require __DIR__ . '/../../src/Service/Pricing/ChildPolicy.php';

use Vie\Service\Pricing\ChildPolicy;

$pass = 0; $fail = 0;
$assert = function (string $name, bool $cond, string $detail = '') use (&$pass, &$fail): void {
    if ($cond) { echo "  ✓ {$name}\n"; $pass++; }
    else { echo "  ✗ {$name}" . ($detail ? " — {$detail}" : '') . "\n"; $fail++; }
};

// 2 children age 5, free quota 1 (under-6 policy). Only ONE is free —
// a spare adult slot must NOT free the second child.
$p = new ChildPolicy([5, 5], 1, 5);
$assert('exactly 1 free child', $p->freeChildrenCount() === 1, 'got ' . $p->freeChildrenCount());
$assert('policy free count 1', $p->policyFreeCount() === 1);
$charged = array_values(array_filter($p->assessments(), fn($a) => !$a->isFree));
$assert('1 charged child', count($charged) === 1);
$assert('charged child index 1', ($charged[0]->childIndex ?? 0) === 1);

// No spareAdultSlots concept anymore — constructor takes 3 args only.
$ref = new ReflectionMethod(ChildPolicy::class, '__construct');
$assert('constructor has 3 params', $ref->getNumberOfParameters() === 3, 'got ' . $ref->getNumberOfParameters());

echo "\n--- ChildPolicy: {$pass} passed, {$fail} failed ---\n";
exit($fail === 0 ? 0 : 1);
