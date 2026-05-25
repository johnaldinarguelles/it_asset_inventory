USE it_asset_db;

-- V6 does not require a destructive database change.
-- It improves reporting accuracy by saving every Received, Issued, and Returned action in the transactions table.
-- Optional indexes for faster filtered reports:
ALTER TABLE transactions ADD INDEX idx_item_description (item_description(100));
ALTER TABLE transactions ADD INDEX idx_created_at (created_at);
