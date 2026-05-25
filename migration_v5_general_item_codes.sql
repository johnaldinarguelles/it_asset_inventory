USE it_asset_db;

-- V5 update notes:
-- Non-unique consumable items should use ONE general barcode/item code as the inventory master.
-- Example: Mouse = 5718185. All receiving/issuance/return transactions using 5718185 update the same stock.
-- Do not create multiple inventory master rows for the same general code.

-- If your old database has duplicate rows for the same serial_number/code,
-- manually merge their totals first, then keep only one row per code for accurate stock reporting.

ALTER TABLE items
  MODIFY serial_number VARCHAR(120) NULL COMMENT 'Unique asset serial OR general barcode/item code for grouped consumables.';
