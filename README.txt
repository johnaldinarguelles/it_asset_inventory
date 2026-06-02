IT ASSET MANAGEMENT SYSTEM V2
Stack: HTML, CSS, PHP, Bootstrap 5, MySQL, DataTables, Chart.js

INSTALLATION
1. Copy folder it_asset_system_v2 to htdocs or www.
2. Open phpMyAdmin and import database.sql.
3. Edit config/db.php if your MySQL password is not empty.
4. Browse: http://localhost/it_asset_system_v2/login.php

DEFAULT LOGINS
Admin: admin / admin123
Staff: staff / admin123
Viewer: viewer / admin123

FEATURES INCLUDED
- Modern responsive sidebar UI
- Barcode scanner support: put cursor in Scan Barcode field, scan, then Enter
- Receiving, issuance, and return workflows
- Real-time stock monitoring with low/out-of-stock highlight
- DataTables search, filtering, pagination, and sorting
- Excel-compatible export (.xls) for items, transactions, monthly reports
- CSV import from Excel for item master list
- Charts and analytics dashboard
- Role-based access: Admin can do all functions; Staff can issue and return only; Viewer is view-only
- Auto PDF reports using browser Print / Save as PDF
- AJAX modal CRUD for item add/edit/delete

CSV IMPORT TEMPLATE COLUMNS
item_description,serial_number,location,uom,boh,actual_stock


V5 GENERAL ITEM CODE LOGIC
- For serialized assets, use the real serial number.
- For non-unique items like mouse/office supplies, use one general barcode/item code, e.g. 5718185 for all mouse stock.
- Receiving the same code increases stock.
- Issuing the same code decreases stock.
- Inventory totals are dynamically computed from BOH + Received + Returned - Issued.

V6 UPDATE - Transaction Accuracy and Dynamic Reports
- Every receiving, issuance, and return entry is saved as a transaction record.
- Return page now supports multiple entries.
- Reports page is dynamic: displayed totals and tables change based on filters.
- Added Date When and What / Action columns for report clarity.
- Excel and PDF reports now follow the selected filters.
- Inventory summary shows Total Received, Total Usage / Issued, Total Returned, and Net Movement.

V7 UPDATE - No Serial Items Maintenance and Item Activity View
- Added No Serial Items Maintenance for consumables and office supplies with no unique serial number.
- Admin can add no-serial items only; editing is disabled to protect transaction/report accuracy.
- Preloaded no-serial items: AA Battery, Post it, A4 Bond paper, Alcohol 473ml, Dell Bag, Dell Wireless Mouse, Jabra Evolve 20 Headphone, tapes, folders, scissors, sticker paper, and others.
- Each no-serial item automatically gets a general item code, example NS-AA-BATTERY.
- The generated item code is used in Receiving, Issuance, and Return so all stock movement connects to the same inventory record.
- Added View action in Items/Stock to display all activities for the selected item with Date When, What/Action, Quantity, PIC, Location, and Week.
- Existing installations: import migration_v7_no_serial_maintenance.sql.


FINAL FIX NOTES
- database.sql now uses only the tables/columns used by the PHP files: items, transactions, users, no_serial_items.
- Fixed MySQL #1071 by reducing unique index column lengths in no_serial_items.
- Login is admin / admin123 and staff / admin123.
- If login fails, import RESET_ADMIN_PASSWORD.sql.


IMPORT FIX:
- database.sql was rebuilt for one-time import.
- no_serial_items uses VARCHAR(150)/VARCHAR(80) unique keys to avoid MySQL #1071 max key length errors.
- Default login: admin / admin123

UPDATE: Item Activity View is now displayed as a Bootstrap modal from the Items / Stock page instead of opening view_item.php as a separate page.


RESPONSIVE UI UPDATE
- Improved desktop, tablet, and mobile layout.
- Sidebar now becomes a slide-out menu on tablet/mobile.
- Tables now use horizontal scrolling to prevent broken layout on small screens.
- Modals are optimized for mobile width and touch-friendly buttons.
