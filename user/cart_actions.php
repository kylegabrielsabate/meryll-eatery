<?php
session_start();
include '../connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please login first.']);
    exit();
}

$user_id = $_SESSION['user_id'];

// Initialize cart from database if session is empty
if (!isset($_SESSION['cart']) || empty($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
    loadCartFromDB($con, $user_id);
}

if (!isset($_SESSION['fried_rice_stock'])) { $_SESSION['fried_rice_stock'] = 20; }

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Load cart from database into session
function loadCartFromDB($con, $user_id) {
    $stmt = $con->prepare("
        SELECT c.cartID, c.productID, c.quantity, p.productname, p.price, p.is_available 
        FROM cart c 
        JOIN products p ON c.productID = p.productID 
        WHERE c.user_id = ?
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $cartKey = md5($row['productname'] . $row['price']);
        $_SESSION['cart'][$cartKey] = [
            'cartID' => $row['cartID'],
            'productID' => $row['productID'],
            'name' => $row['productname'],
            'price' => (float)$row['price'],
            'quantity' => (int)$row['quantity'],
            'is_available' => (int)$row['is_available']
        ];
    }
}

// Save entire cart to database
function saveCartToDB($con, $user_id, $cart) {
    // Clear existing cart for this user
    $stmt = $con->prepare("DELETE FROM cart WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    
    if (empty($cart)) return;
    
    $stmt = $con->prepare("INSERT INTO cart (user_id, productID, quantity) VALUES (?, ?, ?)");
    
    foreach ($cart as $item) {
        $stmt->bind_param("iii", $user_id, $item['productID'], $item['quantity']);
        $stmt->execute();
    }
}

// Check if items are still available
function checkItemAvailability($con, $cart) {
    $unavailable = [];
    
    foreach ($cart as $key => $item) {
        if (isset($item['is_available']) && $item['is_available'] == 0) {
            $unavailable[$key] = $item['name'];
        }
    }
    
    return $unavailable;
}

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
        $productID = intval($_POST['productID'] ?? 0);
        
        if ($qty < 1) $qty = 1;

        if (empty($name) || $price <= 0 || $productID <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid item.']);
            exit();
        }

        $cartKey = md5($name . $price);
        
        if (!isset($_SESSION['cart'][$cartKey])) {
            $_SESSION['cart'][$cartKey] = [
                'productID' => $productID,
                'name' => $name,
                'price' => $price,
                'quantity' => 0,
                'is_available' => 1
            ];
        }
        $_SESSION['cart'][$cartKey]['quantity'] += $qty;

        // Save to database
        saveCartToDB($con, $user_id, $_SESSION['cart']);

        echo json_encode(['success' => true, 'message' => 'Added!', 'total_items' => cartTotalItems($_SESSION['cart'])]);
        break;

    case 'update':
        $cartKey = $_POST['cart_key'] ?? '';
        $change  = intval($_POST['change'] ?? 0);
        if (isValidKey($cartKey) && isset($_SESSION['cart'][$cartKey])) {
            $_SESSION['cart'][$cartKey]['quantity'] += $change;
            if ($_SESSION['cart'][$cartKey]['quantity'] <= 0) unset($_SESSION['cart'][$cartKey]);
        }
        
        saveCartToDB($con, $user_id, $_SESSION['cart']);
        
        echo json_encode(['success' => true, 'total_items' => cartTotalItems($_SESSION['cart'])]);
        break;

    case 'remove':
        $cartKey = $_POST['cart_key'] ?? '';
        if (isValidKey($cartKey) && isset($_SESSION['cart'][$cartKey])) {
            unset($_SESSION['cart'][$cartKey]);
        }
        
        saveCartToDB($con, $user_id, $_SESSION['cart']);
        
        echo json_encode(['success' => true, 'total_items' => cartTotalItems($_SESSION['cart'])]);
        break;

    case 'get':
        // Reload from DB to get latest availability status
        $_SESSION['cart'] = [];
        loadCartFromDB($con, $user_id);
        
        // Check for unavailable items
        $unavailable = checkItemAvailability($con, $_SESSION['cart']);
        
        $items = [];
        foreach ($_SESSION['cart'] as $key => $item) {
            $items[] = [
                'cart_key' => $key,
                'productID' => $item['productID'],
                'name' => $item['name'],
                'price' => $item['price'],
                'quantity' => $item['quantity'],
                'subtotal' => $item['price'] * $item['quantity'],
                'is_available' => !isset($unavailable[$key])
            ];
        }
        
        echo json_encode([
            'success' => true,
            'items' => $items,
            'total_items' => cartTotalItems($_SESSION['cart']),
            'total_price' => cartTotalPrice($_SESSION['cart']),
            'unavailable' => $unavailable
        ]);
        break;

    case 'clear':
        $_SESSION['cart'] = [];
        saveCartToDB($con, $user_id, []);
        echo json_encode(['success' => true]);
        break;

    case 'deduct_fried_rice':
        if ($_SESSION['fried_rice_stock'] > 0) {
            $_SESSION['fried_rice_stock']--;
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false]);
        }
        break;

    case 'checkout':
        // Reload from DB to check latest availability
        $_SESSION['cart'] = [];
        loadCartFromDB($con, $user_id);
        
        if (empty($_SESSION['cart'])) {
            echo json_encode(['success' => false, 'message' => 'Your cart is empty.']);
            exit();
        }

        // Check availability before checkout
        $unavailable = checkItemAvailability($con, $_SESSION['cart']);
        if (!empty($unavailable)) {
            echo json_encode([
                'success' => false,
                'message' => 'Some items are no longer available: ' . implode(', ', $unavailable)
            ]);
            exit();
        }

        $total_amount = cartTotalPrice($_SESSION['cart']);
        $status = 'Pending';
        
        $order_type_raw = $_POST['order_type'] ?? 'Pick-up';
        $order_type = ($order_type_raw === 'Delivery') ? 'Delivered' : $order_type_raw;
        
        $pickup_time = $_POST['pickup_time'] ?? '';
        $order_note = $_POST['order_note'] ?? '';
        $order_number = generateOrderNumber($con);

        try {
            $con->begin_transaction();
            
            $maxOrderId = 0;
            $res = $con->query("SELECT MAX(order_id) as max_id FROM orders");
            if ($row = $res->fetch_assoc()) {
                $maxOrderId = $row['max_id'] ? $row['max_id'] : 0;
            }
            $newOrderId = $maxOrderId + 1;

            $stmt = $con->prepare("INSERT INTO orders (order_id, user_id, order_number, order_type, pickup_time, order_note, total_amount, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            if (!$stmt) throw new Exception("Prepare failed: " . $con->error);
            
            $stmt->bind_param("iissssds", $newOrderId, $user_id, $order_number, $order_type, $pickup_time, $order_note, $total_amount, $status);
            $stmt->execute();
            
            $maxItemId = 0;
            $res2 = $con->query("SELECT MAX(order_item_id) as max_id FROM order_items");
            if ($row2 = $res2->fetch_assoc()) {
                $maxItemId = $row2['max_id'] ? $row2['max_id'] : 0;
            }
            $newItemId = $maxItemId + 1;

            $stmt_item = $con->prepare("INSERT INTO order_items (order_item_id, order_id, item_name, quantity, price) VALUES (?, ?, ?, ?, ?)");
            if (!$stmt_item) throw new Exception("Prepare items failed: " . $con->error);
            
            foreach ($_SESSION['cart'] as $item) {
                $stmt_item->bind_param("iisdd", $newItemId, $newOrderId, $item['name'], $item['quantity'], $item['price']);
                $stmt_item->execute();
                $newItemId++;
            }
            
            $con->commit();
            
            // Clear cart from session AND database
            $_SESSION['cart'] = [];
            saveCartToDB($con, $user_id, []);

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