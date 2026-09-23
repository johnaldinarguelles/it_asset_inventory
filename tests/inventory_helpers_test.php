<?php
require_once __DIR__ . '/../includes/inventory_helpers.php';

function assert_same($expected, $actual, string $message): void {
    if ($expected !== $actual) {
        throw new RuntimeException($message . "; expected " . var_export($expected, true) . " got " . var_export($actual, true));
    }
}

$row = [
    'boh' => 2,
    'total_received' => 10,
    'total_returned' => 3,
    'total_issued' => 4,
    'total_disposed' => 5,
    'reorder_level' => 2,
    'current_co' => '',
];
assert_same(6, inventory_stock($row), 'disposal must reduce usable stock');
assert_same(4, inventory_outstanding_issued(['total_issued' => 7, 'total_returned' => 3]), 'return variance must remain issued minus returned');
assert_same('Disposed', inventory_status(array_merge($row, ['total_disposed' => 11])), 'fully disposed stock must have Disposed status');
assert_same('Issued', inventory_status(array_merge($row, ['total_disposed' => 0, 'current_co' => 'Ms. User'])), 'current responsible person must preserve Issued status');
assert_same(true, inventory_valid_date('2026-02-28'), 'valid calendar date accepted');
assert_same(false, inventory_valid_date('2026-02-30'), 'invalid calendar date rejected');
assert_same(false, inventory_valid_date('2026/02/28'), 'wrong date format rejected');
assert_same(null, inventory_disposal_error(array_merge($row, ['total_disposed' => 0, 'current_co' => '', 'total_issued' => 0, 'total_returned' => 0]), 1, 'Fixed Asset', '2026-09-23'), 'valid fixed-asset disposal accepted');
assert_same('Fixed Assets must be disposed with quantity 1.', inventory_disposal_error(array_merge($row, ['total_disposed' => 0, 'current_co' => '', 'total_issued' => 0, 'total_returned' => 0]), 2, 'Fixed Asset', '2026-09-23'), 'fixed asset quantity greater than one rejected');
assert_same(['2026-08' => 2, '2026-09' => 1], inventory_monthly_quantities([
    ['m' => '2026-08', 'action_type' => 'Disposed', 'q' => 2],
    ['m' => '2026-09', 'action_type' => 'Disposed', 'q' => 1],
], 'Disposed'), 'disposed dashboard quantities grouped by month');
echo "inventory_helpers_test: PASS\n";
