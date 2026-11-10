<?php
session_start();
include '../connect.php';

// Security check
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['has_new_order' => false]);
    exit();
}

// Check for orders created in the last 60 seconds that are still Pending
$stmt = $con->prepare("SELECT order_number, total_amount FROM orders WHERE created_at > DATE_SUB(NOW(), INTERVAL 60 SECOND) AND status = 'Pending' ORDER BY created_at DESC LIMIT 1");
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $order = $result->fetch_assoc();
    echo json_encode([
        'has_new_order' => true,
        'message' => 'Order #' . $order['order_number'] . ' - ₱' . number_format($order['total_amount'], 2)
    ]);
} else {
    echo json_encode(['has_new_order' => false]);
}
?>