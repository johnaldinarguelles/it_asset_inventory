<?php
require_once __DIR__ . '/../includes/inventory_helpers.php';
$item = ['boh'=>0,'total_received'=>5,'total_returned'=>0,'total_issued'=>0,'total_disposed'=>0,'reorder_level'=>1,'current_co'=>''];

function expect_error(?string $expected, ?string $actual, string $name): void {
    if ($expected !== $actual) throw new RuntimeException($name . ': expected ' . var_export($expected, true) . ' got ' . var_export($actual, true));
}
expect_error(null, inventory_disposal_error($item, 2, 'Non Fixed-Asset', '2026-09-23'), 'valid consumable disposal');
expect_error('Disposal quantity is greater than available stock.', inventory_disposal_error($item, 6, 'Non Fixed-Asset', '2026-09-23'), 'over-stock disposal');
expect_error('Return all outstanding issued quantity before disposal.', inventory_disposal_error(array_merge($item, ['total_issued'=>1]), 1, 'Non Fixed-Asset', '2026-09-23'), 'outstanding issue');
expect_error('Date of Disposal must be a valid date in YYYY-MM-DD format.', inventory_disposal_error($item, 1, 'Non Fixed-Asset', '2026-02-30'), 'invalid date');
expect_error('Invalid asset classification.', inventory_disposal_error($item, 1, 'Unknown', '2026-09-23'), 'invalid classification');
echo "disposal_validation_test: PASS\n";
