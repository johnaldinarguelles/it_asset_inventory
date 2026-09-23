<?php

function inventory_locations(): array
{
    return [
        'Rack 1',
        'Rack 2',
        'Rack 3',
        'Rack 4',
        'Rack 5',
        'Rack 6',
        'Rack 7',
        'Rack 8',
        'Cabinet 1',
        'Cabinet 2',
        'Storage Room',
    ];
}

function inventory_asset_classifications(): array
{
    return ['Fixed Asset', 'Non Fixed-Asset'];
}

function inventory_stock(array $row): int
{
    return (int)$row['boh']
        + (int)$row['total_received']
        + (int)$row['total_returned']
        - (int)$row['total_issued']
        - (int)($row['total_disposed'] ?? 0);
}

function inventory_outstanding_issued(array $row): int
{
    return (int)$row['total_issued'] - (int)$row['total_returned'];
}

function inventory_status(array $row): string
{
    $stock = inventory_stock($row);

    if ($stock <= 0 && (int)($row['total_disposed'] ?? 0) > 0) {
        return 'Disposed';
    }
    if (!empty($row['current_co'])) {
        return 'Issued';
    }
    if ($stock <= 0) {
        return 'Out of Stock';
    }
    if ($stock <= (int)$row['reorder_level']) {
        return 'Low Stock';
    }

    return 'Available';
}

function inventory_valid_date(string $date): bool
{
    $parsed = DateTime::createFromFormat('!Y-m-d', $date);
    return $parsed instanceof DateTime && $parsed->format('Y-m-d') === $date;
}

function inventory_disposal_error(array $item, int $quantity, string $classification, string $date): ?string
{
    if (!in_array($classification, inventory_asset_classifications(), true)) {
        return 'Invalid asset classification.';
    }
    if (!inventory_valid_date($date)) {
        return 'Date of Disposal must be a valid date in YYYY-MM-DD format.';
    }
    if ($quantity < 1) {
        return 'Quantity must be at least 1.';
    }
    if (inventory_outstanding_issued($item) > 0) {
        return 'Return all outstanding issued quantity before disposal.';
    }
    if ($quantity > inventory_stock($item)) {
        return 'Disposal quantity is greater than available stock.';
    }
    if ($classification === 'Fixed Asset' && $quantity !== 1) {
        return 'Fixed Assets must be disposed with quantity 1.';
    }

    return null;
}

function inventory_monthly_quantities(array $rows, string $action): array
{
    $totals = [];

    foreach ($rows as $row) {
        if (($row['action_type'] ?? '') !== $action) {
            continue;
        }

        $month = (string)($row['m'] ?? '');
        if ($month === '') {
            continue;
        }

        $totals[$month] = ($totals[$month] ?? 0) + (int)($row['q'] ?? 0);
    }

    return $totals;
}
