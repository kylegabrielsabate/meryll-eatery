<?php
session_start();
include '../connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please login first.']);
    exit();
}

if (!isset($_SESSION['cart'])) { $_SESSION['cart'] = []; }
if (!isset($_SESSION['fried_rice_stock'])) { $_SESSION['fried_rice_stock'] = 20; }

$action = $_POST['action'] ?? $_GET['action'] ?? '';

function cartTotalItems($cart) {
    $total = 0;
    foreach ($cart as $item) { $total += $item['quantity']; }
    return $total;
}

function cartTotalPrice($cart) {
    $total = 0;
    foreach ($cart as $item) { $total += ($item['price'] * $item['quantity']); }
    return $total;
}

function isValidKey($key) {
    return preg_match('/^[a-f0-9]{32}$/', $key);
}

// Generate unique order number like "ME-20261108-001"
function generateOrderNumber($con) {
    $date = date('Ymd');
    $prefix = 'ME-' . $date . '-';
    $result = $con->query("SELECT COUNT(*) as cnt FROM orders WHERE created_at >= CURDATE()");
    $row = $result->fetch_assoc();
    $count = ($row['cnt'] ?? 0) + 1;
    return $prefix . str_pad($count, 3, '0', STR_PAD_LEFT);
}

switch ($action) {
    case 'add':
        $name  = trim($_POST['name'] ?? '');
        $price = floatval($_POST['price'] ?? 0);
        $qty   = intval($_POST['quantity'] ?? 1);
        if ($qty < 1) $qty = 1;

        if (empty($name) || $price <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid item.']);
            exit();
        }

        $cartKey = md5($name . $price);
        if (!isset($_SESSION['cart'][$cartKey])) {
            $_SESSION['cart'][$cartKey] = ['name' => $name, 'price' => $price, 'quantity' => 0];
        }
        $_SESSION['cart'][$cartKey]['quantity'] += $qty;

        echo json_encode(['success' => true, 'message' => 'Added!', 'total_items' => cartTotalItems($_SESSION['cart'])]);
        break;

    case 'update':
        $cartKey = $_POST['cart_key'] ?? '';
        $change  = intval($_POST['change'] ?? 0);
        if (isValidKey($cartKey) && isset($_SESSION['cart'][$cartKey])) {
            $_SESSION['cart'][$cartKey]['quantity'] += $change;
            if ($_SESSION['cart'][$cartKey]['quantity'] <= 0) unset($_SESSION['cart'][$cartKey]);
        }
        echo json_encode(['success' => true, 'total_items' => cartTotalItems($_SESSION['cart'])]);
        break;

    case 'remove':
        $cartKey = $_POST['cart_key'] ?? '';
        if (isValidKey($cartKey) && isset($_SESSION['cart'][$cartKey])) unset($_SESSION['cart'][$cartKey]);
        echo json_encode(['success' => true, 'total_items' => cartTotalItems($_SESSION['cart'])]);
        break;

    case 'get':
        $items = [];
        foreach ($_SESSION['cart'] as $key => $item) {
            $items[] = ['cart_key' => $key, 'name' => $item['name'], 'price' => $item['price'], 'quantity' => $item['quantity'], 'subtotal' => $item['price'] * $item['quantity']];
        }
        echo json_encode(['success' => true, 'items' => $items, 'total_items' => cartTotalItems($_SESSION['cart']), 'total_price' => cartTotalPrice($_SESSION['cart'])]);
        break;

    case 'clear':
        $_SESSION['cart'] = [];
        echo json_encode(['success' => true]);
        break;

    case 'deduct_fried_rice':
        if ($_SESSION['fried_rice_stock'] > 0) { $_SESSION['fried_rice_stock']--; echo json_encode(['success' => true]); }
        else { echo json_encode(['success' => false]); }
        break;

    // ✅ CHECKOUT - MANUAL ID GENERATION (Fixes TiDB Auto Increment Issue)
    case 'checkout':
        if (empty($_SESSION['cart'])) {
            echo json_encode(['success' => false, 'message' => 'Your cart is empty.']);
            exit();
        }

        $user_id = $_SESSION['user_id'];
        $total_amount = cartTotalPrice($_SESSION['cart']);
        $status = 'Pending';
        
        // Map "Delivery" to "Delivered" to match your database enum exactly
        $order_type_raw = $_POST['order_type'] ?? 'Pick-up';
        $order_type = ($order_type_raw === 'Delivery') ? 'Delivered' : $order_type_raw;
        
        $pickup_time = $_POST['pickup_time'] ?? '';
        $order_note = $_POST['order_note'] ?? '';
        $order_number = generateOrderNumber($con);

        try {
            $con->begin_transaction();
            
            // 1. MANUALLY FIND THE NEXT ORDER ID
            $maxOrderId = 0;
            $res = $con->query("SELECT MAX(order_id) as max_id FROM orders");
            if ($row = $res->fetch_assoc()) {
                $maxOrderId = $row['max_id'] ? $row['max_id'] : 0;
            }
            $newOrderId = $maxOrderId + 1;

            // 2. INSERT ORDER WITH SPECIFIC ID
            $stmt = $con->prepare("INSERT INTO orders (order_id, user_id, order_number, order_type, pickup_time, order_note, total_amount, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            if (!$stmt) throw new Exception("Prepare failed: " . $con->error);
            
            // Note: 'd' is for double/decimal (total_amount), 's' for string, 'i' for int
            $stmt->bind_param("iissssds", $newOrderId, $user_id, $order_number, $order_type, $pickup_time, $order_note, $total_amount, $status);
            $stmt->execute();
            
            // 3. MANUALLY FIND THE NEXT ORDER ITEM ID
            $maxItemId = 0;
            $res2 = $con->query("SELECT MAX(order_item_id) as max_id FROM order_items");
            if ($row2 = $res2->fetch_assoc()) {
                $maxItemId = $row2['max_id'] ? $row2['max_id'] : 0;
            }
            $newItemId = $maxItemId + 1;

            // 4. INSERT ITEMS WITH SPECIFIC IDs
            $stmt_item = $con->prepare("INSERT INTO order_items (order_item_id, order_id, item_name, quantity, price) VALUES (?, ?, ?, ?, ?)");
            if (!$stmt_item) throw new Exception("Prepare items failed: " . $con->error);
            
            foreach ($_SESSION['cart'] as $item) {
                $stmt_item->bind_param("iisdd", $newItemId, $newOrderId, $item['name'], $item['quantity'], $item['price']);
                $stmt_item->execute();
                $newItemId++; // Increment for the next item in the cart
            }
            
            $con->commit();
            $_SESSION['cart'] = [];

            echo json_encode([
                'success' => true, 
                'message' => 'Order placed successfully! Order #' . $order_number, 
                'order_id' => $newOrderId
            ]);

        } catch (Exception $e) {
            if ($con) $con->rollback();
            echo json_encode(['success' => false, 'message' => 'Checkout failed: ' . $e->getMessage()]);
        }
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action.']);
}
?>