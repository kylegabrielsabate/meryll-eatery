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
            $_SESSION['cart'][$cartKey] = [
                'name'     => $name,
                'price'    => $price,
                'quantity' => 0
            ];
        }
        $_SESSION['cart'][$cartKey]['quantity'] += $qty;

        echo json_encode([
            'success'     => true,
            'message'     => 'Added to cart!',
            'total_items' => cartTotalItems($_SESSION['cart']),
            'total_price' => cartTotalPrice($_SESSION['cart'])
        ]);
        break;

    case 'update':
        $cartKey = $_POST['cart_key'] ?? '';
        $change  = intval($_POST['change'] ?? 0);

        if (!isValidKey($cartKey)) {
            echo json_encode(['success' => false, 'message' => 'Invalid cart key.']);
            exit();
        }

        if (isset($_SESSION['cart'][$cartKey])) {
            $_SESSION['cart'][$cartKey]['quantity'] += $change;
            if ($_SESSION['cart'][$cartKey]['quantity'] <= 0) {
                unset($_SESSION['cart'][$cartKey]);
            }
        }

        echo json_encode([
            'success'     => true,
            'total_items' => cartTotalItems($_SESSION['cart']),
            'total_price' => cartTotalPrice($_SESSION['cart'])
        ]);
        break;

    case 'remove':
        $cartKey = $_POST['cart_key'] ?? '';

        if (isValidKey($cartKey) && isset($_SESSION['cart'][$cartKey])) {
            unset($_SESSION['cart'][$cartKey]);
        }

        echo json_encode([
            'success'     => true,
            'total_items' => cartTotalItems($_SESSION['cart']),
            'total_price' => cartTotalPrice($_SESSION['cart'])
        ]);
        break;

    case 'remove_by_name':
        $name  = trim($_POST['name'] ?? '');
        $price = floatval($_POST['price'] ?? 0);
        $cartKey = md5($name . $price);

        if (isset($_SESSION['cart'][$cartKey])) {
            $_SESSION['cart'][$cartKey]['quantity']--;
            if ($_SESSION['cart'][$cartKey]['quantity'] <= 0) {
                unset($_SESSION['cart'][$cartKey]);
            }
        }

        echo json_encode([
            'success'     => true,
            'total_items' => cartTotalItems($_SESSION['cart']),
            'total_price' => cartTotalPrice($_SESSION['cart'])
        ]);
        break;

    case 'get':
        $items = [];
        foreach ($_SESSION['cart'] as $key => $item) {
            $items[] = [
                'cart_key' => $key,
                'name'     => $item['name'],
                'price'    => $item['price'],
                'quantity' => $item['quantity'],
                'subtotal' => $item['price'] * $item['quantity']
            ];
        }

        echo json_encode([
            'success'     => true,
            'items'       => $items,
            'total_items' => cartTotalItems($_SESSION['cart']),
            'total_price' => cartTotalPrice($_SESSION['cart'])
        ]);
        break;

    case 'clear':
        $_SESSION['cart'] = [];
        echo json_encode(['success' => true, 'total_items' => 0, 'total_price' => 0]);
        break;

    case 'deduct_fried_rice':
        if ($_SESSION['fried_rice_stock'] > 0) {
            $_SESSION['fried_rice_stock']--;
            echo json_encode([
                'success'   => true,
                'remaining' => $_SESSION['fried_rice_stock']
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Fried rice is sold out!']);
        }
        break;

    case 'get_fried_rice_stock':
        echo json_encode([
            'success'   => true,
            'remaining' => $_SESSION['fried_rice_stock']
        ]);
        break;

    // ✅ CHECKOUT WITH PICKUP TIME
    case 'checkout':
        if (empty($_SESSION['cart'])) {
            echo json_encode(['success' => false, 'message' => 'Your cart is empty.']);
            exit();
        }

        $user_id = $_SESSION['user_id'];
        $total_amount = cartTotalPrice($_SESSION['cart']);
        $status = 'Pending';
        $pickup_time = $_POST['pickup_time'] ?? 'ASAP';

        $con->begin_transaction();
        try {
            $stmt = $con->prepare("INSERT INTO orders (user_id, total_amount, status, pickup_time, created_at) VALUES (?, ?, ?, ?, NOW())");
            $stmt->bind_param("idss", $user_id, $total_amount, $status, $pickup_time);
            $stmt->execute();
            $order_id = $con->insert_id;

            $stmt_item = $con->prepare("INSERT INTO order_items (order_id, item_name, price, quantity, subtotal) VALUES (?, ?, ?, ?, ?)");
            
            foreach ($_SESSION['cart'] as $item) {
                $subtotal = $item['price'] * $item['quantity'];
                $stmt_item->bind_param("isddd", $order_id, $item['name'], $item['price'], $item['quantity'], $subtotal);
                $stmt_item->execute();
            }

            $con->commit();
            $_SESSION['cart'] = [];

            echo json_encode([
                'success'  => true,
                'message'  => 'Order placed successfully! Pickup at: ' . $pickup_time,
                'order_id' => $order_id
            ]);

        } catch (Exception $e) {
            $con->rollback();
            echo json_encode(['success' => false, 'message' => 'Checkout failed. Please try again.']);
        }
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action.']);
}
?>