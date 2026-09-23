<?php
require_once __DIR__ . '/../includes/inventory_helpers.php';

$host = 'localhost';
$user = 'itasset_user';
$pass = 'StrongPassword123!';
$db = 'it_asset_db';
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
  die('Database connection failed: ' . $conn->connect_error);
}
$conn->set_charset('utf8mb4');
function e($v)
{
  return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}
function ending_stock($row)
{
  return inventory_stock($row);
}
function update_item_status($conn, $id)
{
  $r = $conn->query("SELECT * FROM items WHERE id=" . (int)$id)->fetch_assoc();
  if (!$r) return;
  $status = inventory_status($r);
  $stmt = $conn->prepare('UPDATE items SET status=? WHERE id=?');
  $stmt->bind_param('si', $status, $id);
  $stmt->execute();
}
