<?php
$host = 'localhost';
$user = 'yas3';
$pass = 'Y@53mysql';
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
  return (int)$row['boh'] + (int)$row['total_received'] + (int)$row['total_returned'] - (int)$row['total_issued'];
}
function update_item_status($conn, $id)
{
  $r = $conn->query("SELECT * FROM items WHERE id=" . (int)$id)->fetch_assoc();
  if (!$r) return;
  $stock = ending_stock($r);
  $status = 'Available';
  if (!empty($r['current_co'])) $status = 'Issued';
  if ($stock <= 0) $status = 'Out of Stock';
  elseif ($stock <= (int)$r['reorder_level']) $status = 'Low Stock';
  $stmt = $conn->prepare('UPDATE items SET status=? WHERE id=?');
  $stmt->bind_param('si', $status, $id);
  $stmt->execute();
}
