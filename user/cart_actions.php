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

// ✅ RESEND API KEY (Replace with your actual Resend API Key)
// You can find this in your Resend dashboard: https://resend.com/api-keys
$RESEND_API_KEY = getenv('RESEND_API_KEY') ?: 're_PLACEHOLDER'; 
$RESEND_FROM_EMAIL = getenv('RESEND_FROM_EMAIL') ?: 'onboarding@resend.dev';

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

function cartTotalItems($cart) { $total = 0; foreach ($cart as $item) $total += $item['quantity']; return $total; }
function cartTotalPrice($cart) { $total = 0; foreach ($cart as $item) $total += ($item['price'] * $item['quantity']); return $total; }
function isValidKey($key) { return preg_match('/^[a-f0-9]{32}$/', $key); }

function generateOrderNumber($con) {
    $date = date('Ymd');
    $prefix = 'ME-' . $date . '-';
    $result = $con->query("SELECT COUNT(*) as cnt FROM orders WHERE created_at >= CURDATE()");
    $row = $result->fetch_assoc();
    $count = ($row['cnt'] ?? 0) + 1;
    return $prefix . str_pad($count, 3, '0', STR_PAD_LEFT);
}

// ✅ FUNCTION TO SEND EMAIL RECEIPT VIA RESEND
function sendReceiptEmail($con, $user_id, $order_number, $total_amount, $order_type, $pickup_time, $order_note, $cart_items, $api_key, $from_email) {
    // 1. Get User Email
    $stmt = $con->prepare("SELECT email, username FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    
    if (!$user || empty($user['email'])) return false;

    // 2. Build HTML Receipt
    $items_html = '';
    foreach ($cart_items as $item) {
        $items_html .= '<tr>
            <td style="padding: 12px; border-bottom: 1px solid #eee;">' . htmlspecialchars($item['name']) . '</td>
            <td style="padding: 12px; border-bottom: 1px solid #eee; text-align: center;">' . $item['quantity'] . '</td>
            <td style="padding: 12px; border-bottom: 1px solid #eee; text-align: right;">₱' . number_format($item['price'], 2) . '</td>
            <td style="padding: 12px; border-bottom: 1px solid #eee; text-align: right; font-weight: 600;">₱' . number_format($item['price'] * $item['quantity'], 2) . '</td>
        </tr>';
    }

    $html_body = "
    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; border: 2px solid #8B0000; border-radius: 10px; overflow: hidden;'>
        <div style='background: #8B0000; color: white; padding: 25px; text-align: center;'>
            <h1 style='margin: 0; font-size: 28px;'>🍽️ Meryll Eatery</h1>
            <p style='margin: 10px 0 0 0; font-size: 16px;'>Order Receipt</p>
        </div>
        <div style='padding: 25px;'>
            <p style='font-size: 16px;'>Hello <strong>" . htmlspecialchars($user['username']) . "</strong>,</p>
            <p style='font-size: 14px; color: #555;'>Thank you for your order! Please show this receipt when claiming your food.</p>
            
            <div style='background: #f9f9f9; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #8B0000;'>
                <p style='margin: 8px 0;'><strong>Order #:</strong> " . htmlspecialchars($order_number) . "</p>
                <p style='margin: 8px 0;'><strong>Date:</strong> " . date('F j, Y \a\t g:i A') . "</p>
                <p style='margin: 8px 0;'><strong>Order Type:</strong> " . htmlspecialchars($order_type) . "</p>
                <p style='margin: 8px 0;'><strong>Pickup Time:</strong> " . htmlspecialchars($pickup_time) . "</p>
                " . (!empty($order_note) ? "<p style='margin: 8px 0;'><strong>Note:</strong> " . htmlspecialchars($order_note) . "</p>" : "") . "
            </div>

            <table style='width: 100%; border-collapse: collapse; margin: 20px 0;'>
                <thead>
                    <tr style='background: #f4f4f4;'>
                        <th style='padding: 12px; text-align: left; border-bottom: 2px solid #ddd;'>Item</th>
                        <th style='padding: 12px; text-align: center; border-bottom: 2px solid #ddd;'>Qty</th>
                        <th style='padding: 12px; text-align: right; border-bottom: 2px solid #ddd;'>Price</th>
                        <th style='padding: 12px; text-align: right; border-bottom: 2px solid #ddd;'>Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    $items_html
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan='3' style='padding: 15px 10px; text-align: right; font-weight: bold; font-size: 18px; border-top: 2px solid #8B0000;'>Total:</td>
                        <td style='padding: 15px 10px; text-align: right; font-weight: bold; font-size: 18px; color: #8B0000; border-top: 2px solid #8B0000;'>₱" . number_format($total_amount, 2) . "</td>
                    </tr>
                </tfoot>
            </table>
            
            <div style='text-align: center; margin-top: 30px; padding: 20px; background: #fff0f0; border-radius: 8px;'>
                <p style='margin: 0; color: #8B0000; font-size: 16px; font-weight: 600;'>See you at Meryll Eatery! 🎉</p>
            </div>
        </div>
    </div>";

    // 3. Send via Resend API using cURL
    $url = 'https://api.resend.com/emails';
    $payload = json_encode([
        'from'    => 'Meryll Eatery <' . $from_email . '>',
        'to'      => [$user['email']],
        'subject' => 'Order Confirmation #' . $order_number . ' - Meryll Eatery',
        'html'    => $html_body
    ]);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $api_key,
        'Content-Type: application/json'
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // Return true if Resend accepted it (200 or 201)
    return ($http_code >= 200 && $http_code < 300);
}

switch ($action) {
    case 'add':
        $name  = trim($_POST['name'] ?? '');
        $price = floatval($_POST['price'] ?? 0);
        $qty   = intval($_POST['quantity'] ?? 1);
        $productID = intval($_POST['productID'] ?? 0);
        if ($qty < 1) $qty = 1;
        if (empty($name) || $price <= 0) { echo json_encode(['success' => false, 'message' => 'Invalid item.']); exit(); }

        $cartKey = md5($name . $price);
        if (!isset($_SESSION['cart'][$cartKey])) {
            $_SESSION['cart'][$cartKey] = ['productID' => $productID, 'name' => $name, 'price' => $price, 'quantity' => 0, 'is_available' => 1];
        }
        $_SESSION['cart'][$cartKey]['quantity'] += $qty;
        saveCartToDB($con, $user_id, $_SESSION['cart']);
        echo json_encode(['success' => true, 'message' => 'Added!', 'total_items' => cartTotalItems($_SESSION['cart'])]);
        break;

    case 'update':
        $cartKey = $_POST['cart_key'] ?? ''; $change = intval($_POST['change'] ?? 0);
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
        $_SESSION['cart'] = []; loadCartFromDB($con, $user_id);
        $unavailable = checkItemAvailability($con, $_SESSION['cart']);
        $items = [];
        foreach ($_SESSION['cart'] as $key => $item) {
            $items[] = ['cart_key' => $key, 'productID' => $item['productID'], 'name' => $item['name'], 'price' => $item['price'], 'quantity' => $item['quantity'], 'subtotal' => $item['price'] * $item['quantity'], 'is_available' => !isset($unavailable[$key])];
        }
        echo json_encode(['success' => true, 'items' => $items, 'total_items' => cartTotalItems($_SESSION['cart']), 'total_price' => cartTotalPrice($_SESSION['cart']), 'unavailable' => $unavailable]);
        break;

    case 'clear':
        $_SESSION['cart'] = []; saveCartToDB($con, $user_id, []);
        echo json_encode(['success' => true]);
        break;

    case 'deduct_fried_rice':
        if ($_SESSION['fried_rice_stock'] > 0) { $_SESSION['fried_rice_stock']--; echo json_encode(['success' => true]); }
        else { echo json_encode(['success' => false]); }
        break;

    case 'checkout':
        $_SESSION['cart'] = []; loadCartFromDB($con, $user_id);
        if (empty($_SESSION['cart'])) { echo json_encode(['success' => false, 'message' => 'Your cart is empty.']); exit(); }

        $unavailable = checkItemAvailability($con, $_SESSION['cart']);
        if (!empty($unavailable)) { echo json_encode(['success' => false, 'message' => 'Some items are no longer available: ' . implode(', ', $unavailable)]); exit(); }

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
            if ($row = $res->fetch_assoc()) $maxOrderId = $row['max_id'] ? $row['max_id'] : 0;
            $newOrderId = $maxOrderId + 1;

            $stmt = $con->prepare("INSERT INTO orders (order_id, user_id, order_number, order_type, pickup_time, order_note, total_amount, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            if (!$stmt) throw new Exception("Prepare failed: " . $con->error);
            $stmt->bind_param("iissssds", $newOrderId, $user_id, $order_number, $order_type, $pickup_time, $order_note, $total_amount, $status);
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

            // ✅ SEND EMAIL RECEIPT VIA RESEND
            $emailSent = sendReceiptEmail($con, $user_id, $order_number, $total_amount, $order_type, $pickup_time, $order_note, $_SESSION['cart'], $RESEND_API_KEY, $RESEND_FROM_EMAIL);

            $_SESSION['cart'] = [];
            saveCartToDB($con, $user_id, []);

            $message = 'Order placed successfully! Order #' . $order_number;
            if ($emailSent) {
                $message .= ' - Receipt sent to your email!';
            } else {
                $message .= ' - Please screenshot this page as your receipt.';
            }

            echo json_encode(['success' => true, 'message' => $message, 'order_id' => $newOrderId, 'order_number' => $order_number]);

        } catch (Exception $e) {
            if ($con) $con->rollback();
            echo json_encode(['success' => false, 'message' => 'Checkout failed: ' . $e->getMessage()]);
        }
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action.']);
}
?>