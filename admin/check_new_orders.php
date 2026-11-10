<?php
session_start();
include '../connect.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['has_new_order' => false]);
    exit();
}

// Get the last order ID the admin has seen (from session)
$last_seen = isset($_SESSION['last_seen_order']) ? $_SESSION['last_seen_order'] : 0;

// Check for NEW orders created in the last 10 seconds
$stmt = $con->prepare("
    SELECT o.order_id, o.order_number, o.total_amount, o.created_at, u.username as customer_name
    FROM orders o
    LEFT JOIN users u ON o.user_id = u.user_id
    WHERE o.order_id > ? 
    AND o.created_at > DATE_SUB(NOW(), INTERVAL 10 SECOND)
    AND o.status = 'Pending'
    ORDER BY o.order_id DESC
    LIMIT 1
");
$stmt->bind_param("i", $last_seen);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $order = $result->fetch_assoc();
    
    // Update session with latest order ID
    $_SESSION['last_seen_order'] = $order['order_id'];
    
    echo json_encode([
        'has_new_order' => true,
        'order_id' => $order['order_id'],
        'order_number' => $order['order_number'],
        'total_amount' => $order['total_amount'],
        'customer_name' => $order['customer_name'] ?? 'Online Customer',
        'created_at' => $order['created_at']
    ]);
} else {
    echo json_encode(['has_new_order' => false]);
}
?>