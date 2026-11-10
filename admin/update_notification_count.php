<?php
session_start();
include '../connect.php';

if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['count' => 0]);
    exit();
}

$admin_id = $_SESSION['admin_id'];

// Count unread notifications
$stmt = $con->prepare("SELECT COUNT(*) as count FROM notifications WHERE admin_id = ? AND is_read = 0");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();

echo json_encode(['count' => $result['count']]);
?>