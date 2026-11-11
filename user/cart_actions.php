<?php
session_start();
include '../connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please login first.']);
    exit();
}

$user_id = (int)$_SESSION['user_id'];

if (!isset($_SESSION['cart']) || empty($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
    loadCartFromDB($con, $user_id);
}
if (!isset($_SESSION['fried_rice_stock'])) { $_SESSION['fried_rice_stock'] = 20; }

$action = $_POST['action'] ?? $_GET['action'] ?? '';

function loadCartFromDB($con, $user_id) {
    $uid = (int)$user_id;
    $stmt = $con->prepare("SELECT cart_data FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $uid);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        if (!empty($row['cart_data'])) {
            $savedCart = json_decode($row['cart_data'], true);
            if (is_array($savedCart)) $_SESSION['cart'] = $savedCart;
        }
    }
}

function saveCartToDB($con, $user_id, $cart) {
    $uid = (int)$user_id;
    $cartJson = json_encode($cart);
    $stmt = $con->prepare("UPDATE users SET cart_data = ? WHERE user_id = ?");
    $stmt->bind_param("si", $cartJson, $uid);
    $stmt->execute();
}

function checkItemAvailability($con, $cart) {
    $unavailable = [];
    foreach ($cart as $key => $item) {
        if (isset($item['productID']) && $item['productID'] > 0) {
            $stmt = $con->prepare("SELECT is_available FROM products WHERE productID = ?");
            $stmt->bind_param("i", $item['productID']);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($row = $res->fetch_assoc()) {
                if ($row['is_available'] == 0) $unavailable[$key] = $item['name'];
            }
        }
    }
    return $unavailable;
}

function cartTotalItems($cart) { 
    $total = 0; 
    foreach ($cart as $item) $total += $item['quantity']; 
    return $total; 
}

function cartTotalPrice($cart) { 
    $total = 0; 
    foreach ($cart as $item) $total += ($item['price'] * $item['quantity']); 
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

// Send receipt email via Resend
function sendReceiptEmail($con, $user_id, $order_number, $total_amount, $order_type, $pickup_time, $order_note, $cart_items) {
    $stmt = $con->prepare("SELECT email, username FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    
    if (!$user || empty($user['email'])) return false;

    $items_html = '';
    foreach ($cart_items as $item) {
        $items_html .= '<tr><td style="padding:8px;border-bottom:1px solid #eee">'.htmlspecialchars($item['name']).'</td>';
        $items_html .= '<td style="padding:8px;border-bottom:1px solid #eee;text-align:center">'.$item['quantity'].'</td>';
        $items_html .= '<td style="padding:8px;border-bottom:1px solid #eee;text-align:right">₱'.number_format($item['price'], 2).'</td>';
        $items_html .= '<td style="padding:8px;border-bottom:1px solid #eee;text-align:right;font-weight:600">₱'.number_format($item['price'] * $item['quantity'], 2).'</td></tr>';
    }

    $html_body = "
    <div style='font-family:Arial,sans-serif;max-width:600px;margin:auto;border:2px solid #8B0000;border-radius:10px;overflow:hidden'>
        <div style='background:#8B0000;color:#fff;padding:20px;text-align:center'>
            <h1 style='margin:0'>🍽️ Meryll Eatery</h1>
            <p style='margin:5px 0 0 0'>Order Receipt</p>
        </div>
        <div style='padding:20px'>
            <p>Hello <strong>".htmlspecialchars($user['username'])."</strong>,</p>
            <p>Thank you for your order! Here are your order details:</p>
            
            <div style='background:#f9f9f9;padding:15px;border-radius:8px;margin:20px 0'>
                <p><strong>Order #:</strong> ".htmlspecialchars($order_number)."</p>
                <p><strong>Date:</strong> ".date('F j, Y \a\t g:i A')."</p>
                <p><strong>Type:</strong> ".htmlspecialchars($order_type)."</p>
                <p><strong>Pickup Time:</strong> ".htmlspecialchars($pickup_time)."</p>
                <p><strong>Payment:</strong> ".htmlspecialchars($_POST['payment_method'] ?? 'Cash')."</p>
            </div>

            <table style='width:100%;border-collapse:collapse;margin:20px 0'>
                <thead><tr style='background:#f4f4f4'>
                    <th style='padding:10px;text-align:left'>Item</th>
                    <th style='padding:10px;text-align:center'>Qty</th>
                    <th style='padding:10px;text-align:right'>Price</th>
                    <th style='padding:10px;text-align:right'>Subtotal</th>
                </tr></thead>
                <tbody>$items_html</tbody>
                <tfoot>
                    <tr><td colspan='3' style='padding:15px 10px;text-align:right;font-weight:bold;font-size:1.2em'>Total:</td>
                    <td style='padding:15px 10px;text-align:right;font-weight:bold;font-size:1.2em;color:#8B0000'>₱".number_format($total_amount, 2)."</td></tr>
                </tfoot>
            </table>
            
            <p style='text-align:center;margin-top:30px;color:#666'>See you at Meryll Eatery! 🎉</p>
        </div>
    </div>";

    $url = 'https://api.resend.com/emails';
    $payload = json_encode([
        'from' => 'Meryll Eatery <'.getenv('RESEND_FROM_EMAIL').'>',
        'to' => [$user['email']],
        'subject' => 'Order Confirmation #' . $order_number . ' - Meryll Eatery',
        'html' => $html_body
    ]);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . getenv('RESEND_API_KEY'),
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ($httpCode >= 200 && $httpCode < 300);
}

// Create admin notification
function createAdminNotification($con, $order_id, $order_number, $total_amount, $order_type) {
    $message = "New order #$order_number - $order_type - ₱".number_format($total_amount, 2);
    $stmt = $con->prepare("INSERT INTO notifications (admin_id, title, message, type) VALUES (1, 'New Order Received', ?, 'order')");
    $stmt->bind_param("s", $message);
    $stmt->execute();
}

switch ($action) {
    case 'add':
        $name  = trim($_POST['name'] ?? '');
        $price = floatval($_POST['price'] ?? 0);
        $qty   = intval($_POST['quantity'] ?? 1);
        $productID = intval($_POST['productID'] ?? 0);
        if ($qty < 1) $qty = 1;
        if (empty($name) || $price <= 0) { 
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
        saveCartToDB($con, $user_id, $_SESSION['cart']);
        echo json_encode(['success' => true, 'message' => 'Added!', 'total_items' => cartTotalItems($_SESSION['cart'])]);
        break;

    case 'update':
        $cartKey = $_POST['cart_key'] ?? ''; 
        $change = intval($_POST['change'] ?? 0);
        if (isValidKey($cartKey) && isset($_SESSION['cart'][$cartKey])) {
            $_SESSION['cart'][$cartKey]['quantity'] += $change;
            if ($_SESSION['cart'][$cartKey]['quantity'] <= 0) unset($_SESSION['cart'][$cartKey]);
        }
        saveCartToDB($con, $user_id, $_SESSION['cart']);
        echo json_encode(['success' => true, 'total_items' => cartTotalItems($_SESSION['cart'])]);
        break;

    case 'remove':
        $cartKey = $_POST['cart_key'] ?? '';
        if (isValidKey($cartKey) && isset($_SESSION['cart'][$cartKey])) unset($_SESSION['cart'][$cartKey]);
        saveCartToDB($con, $user_id, $_SESSION['cart']);
        echo json_encode(['success' => true, 'total_items' => cartTotalItems($_SESSION['cart'])]);
        break;

    case 'get':
        $_SESSION['cart'] = []; 
        loadCartFromDB($con, $user_id);
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
        $_SESSION['cart'] = []; 
        loadCartFromDB($con, $user_id);
        
        if (empty($_SESSION['cart'])) { 
            echo json_encode(['success' => false, 'message' => 'Your cart is empty.']); 
            exit(); 
        }

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
        $payment_method = $_POST['payment_method'] ?? 'Cash';
        $pickup_time = $_POST['pickup_time'] ?? '';
        $order_note = $_POST['order_note'] ?? '';
        $order_number = generateOrderNumber($con);

        // ✅ GCASH PROOF OF PAYMENT UPLOAD
        $gcash_proof = null;
        if ($payment_method === 'GCash') {
            if (!isset($_FILES['gcash_proof']) || $_FILES['gcash_proof']['error'] !== 0) {
                echo json_encode(['success' => false, 'message' => 'Please upload your GCash proof of payment.']);
                exit();
            }
            $dir = __DIR__ . '/../uploads/proofs/';
            if (!is_dir($dir)) mkdir($dir, 0777, true);
            
            $ext = strtolower(pathinfo($_FILES['gcash_proof']['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
                echo json_encode(['success' => false, 'message' => 'Invalid image type. Use JPG, PNG, or GIF.']);
                exit();
            }
            $fname = 'proof_' . $user_id . '_' . time() . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
            if (!move_uploaded_file($_FILES['gcash_proof']['tmp_name'], $dir . $fname)) {
                echo json_encode(['success' => false, 'message' => 'Failed to save proof image.']);
                exit();
            }
            $gcash_proof = $fname;
        }

        try {
            $con->begin_transaction();
            
            $maxOrderId = 0;
            $res = $con->query("SELECT MAX(order_id) as max_id FROM orders");
            if ($row = $res->fetch_assoc()) $maxOrderId = $row['max_id'] ? $row['max_id'] : 0;
            $newOrderId = $maxOrderId + 1;

            // ✅ INSERT now includes gcash_proof (and fixed bind types: order_note=s, total=d)
            $stmt = $con->prepare("INSERT INTO orders (order_id, user_id, order_number, order_type, payment_method, pickup_time, order_note, total_amount, status, gcash_proof, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            if (!$stmt) throw new Exception("Prepare failed: " . $con->error);
            $stmt->bind_param("iisssssdss", $newOrderId, $user_id, $order_number, $order_type, $payment_method, $pickup_time, $order_note, $total_amount, $status, $gcash_proof);
            $stmt->execute();

            // Add to order_status table
            $stmt = $con->prepare("INSERT INTO order_status (order_id, status) VALUES (?, 'Pending')");
            $stmt->bind_param("i", $newOrderId);
            $stmt->execute();

            $maxItemId = 0;
            $res2 = $con->query("SELECT MAX(order_item_id) as max_id FROM order_items");
            if ($row2 = $res2->fetch_assoc()) $maxItemId = $row2['max_id'] ? $row2['max_id'] : 0;
            $newItemId = $maxItemId + 1;

            $stmt_item = $con->prepare("INSERT INTO order_items (order_item_id, order_id, item_name, quantity, price) VALUES (?, ?, ?, ?, ?)");
            if (!$stmt_item) throw new Exception("Prepare items failed: " . $con->error);
            
            foreach ($_SESSION['cart'] as $item) {
                $stmt_item->bind_param("iisdd", $newItemId, $newOrderId, $item['name'], $item['quantity'], $item['price']);
                $stmt_item->execute();
                $newItemId++;
            }
            
            $con->commit();
            
            // Send email receipt
            sendReceiptEmail($con, $user_id, $order_number, $total_amount, $order_type, $pickup_time, $order_note, $_SESSION['cart']);
            
            // Create admin notification
            createAdminNotification($con, $newOrderId, $order_number, $total_amount, $order_type);

            $_SESSION['cart'] = []; 
            saveCartToDB($con, $user_id, []);

            echo json_encode([
                'success' => true, 
                'message' => 'Order placed successfully! Receipt sent to your email.', 
                'order_id' => $newOrderId,
                'order_number' => $order_number,
                'redirect' => 'receipt.php?order_id=' . $newOrderId
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