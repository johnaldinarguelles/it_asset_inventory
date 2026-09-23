USE it_asset_db;

ALTER TABLE items
  ADD COLUMN asset_classification ENUM('Fixed Asset','Non Fixed-Asset') NOT NULL DEFAULT 'Non Fixed-Asset' AFTER uom,
  ADD COLUMN total_disposed INT NOT NULL DEFAULT 0 AFTER total_returned;

ALTER TABLE items
  MODIFY status ENUM('Available','Issued','Low Stock','Out of Stock','Disposed') NOT NULL DEFAULT 'Available';

ALTER TABLE transactions
  MODIFY action_type ENUM('Received','Issued','Returned','Disposed','Adjusted') NOT NULL;
