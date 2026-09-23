<?php
require_once __DIR__ . '/../includes/inventory_helpers.php';
$disposed = inventory_monthly_quantities([
    ['m'=>'2026-08','action_type'=>'Disposed','q'=>'2'],
    ['m'=>'2026-09','action_type'=>'Received','q'=>'3'],
    ['m'=>'2026-09','action_type'=>'Disposed','q'=>'1'],
], 'Disposed');
if (($disposed['2026-08'] ?? 0) !== 2 || ($disposed['2026-09'] ?? 0) !== 1) {
    throw new RuntimeException('Dashboard must aggregate Disposed quantities by month.');
}
echo "dashboard_disposal_test: PASS\n";
