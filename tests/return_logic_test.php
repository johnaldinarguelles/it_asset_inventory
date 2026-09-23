<?php
require_once __DIR__ . '/../includes/inventory_helpers.php';

if (inventory_outstanding_issued(['total_issued' => 9, 'total_returned' => 4]) !== 5) {
    throw new RuntimeException('Return Variance must equal issued minus returned.');
}
if (inventory_stock(['boh'=>0,'total_received'=>6,'total_returned'=>1,'total_issued'=>2,'total_disposed'=>3]) !== 2) {
    throw new RuntimeException('Return stock calculation must include disposal deduction.');
}
echo "return_logic_test: PASS\n";
