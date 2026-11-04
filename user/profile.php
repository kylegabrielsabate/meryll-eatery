<?php
session_start(); 
include '../connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') { 
    header("Location: login.php"); exit; 
}

$userID = $_SESSION['user_id']; 
$username = $_SESSION['username'] ?? 'User';

// Fetch current user details
$stmt = $con->prepare("SELECT user_id, username, email FROM users WHERE user_id = ?");
$stmt->bind_param("i", $userID); 
$stmt->execute(); 
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    session_destroy();
    header("Location: login.php"); exit;
}

// Fetch Order History using the correct schema
$orderStmt = $con->prepare("
    SELECT o.order_number, o.created_at, o.total_amount, o.status,
           oi.item_name, oi.quantity, oi.price
    FROM orders o
    JOIN order_items oi ON o.order_id = oi.order_id
    WHERE o.user_id = ?
    ORDER BY o.created_at DESC
");
$orderStmt->bind_param("i", $userID); 
$orderStmt->execute(); 
$result = $orderStmt->get_result();

$orders = [];
while ($row = $result->fetch_assoc()) {
    $onum = $row['order_number'];
    if (!isset($orders[$onum])) {
        $orders[$onum] = [
            'date' => $row['created_at'], 
            'total' => $row['total_amount'], 
            'status' => $row['status'],
            'items' => []
        ];
    }
    $subtotal = $row['quantity'] * $row['price'];
    $orders[$onum]['items'][] = [
        'name' => $row['item_name'], 
        'qty' => $row['quantity'], 
        'subtotal' => $subtotal
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile | Meryll Eatery</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        /* === EXACT SAME VARIABLES AS USER DASHBOARD === */
        :root{
            --brand-red:#8B0000;
            --brand-light:#fff0f0;
            --bg-light:#f8f9fa;
            --text-dark:#333;
            --text-muted:#6c757d;
            --sidebar-width:260px;
            --border-color:#e9ecef;
        }
        *{box-sizing:border-box;margin:0;padding:0}
        body{font-family:'Inter',sans-serif;background:var(--bg-light);display:flex;min-height:100vh}
        
        /* === SIDEBAR (Same as index.php) === */
        .sidebar{width:var(--sidebar-width);background:#fff;border-right:1px solid var(--border-color);display:flex;flex-direction:column;height:100vh;position:fixed;left:0;top:0;z-index:100}
        .logo{font-size:1.4rem;font-weight:700;color:var(--brand-red);padding:1.2rem 1.5rem;border-bottom:1px solid var(--border-color);display:flex;align-items:center;gap:10px;text-decoration:none}
        .nav-section{padding:1rem 0;flex:1;overflow-y:auto}
        .nav-link{display:flex;align-items:center;gap:12px;padding:12px 1.5rem;color:var(--text-dark);text-decoration:none;font-weight:500;font-size:.95rem;transition:all .2s;border-left:3px solid transparent}
        .nav-link:hover{background:#f8f9fa}
        .nav-link.active{background:var(--brand-light);color:var(--brand-red);border-left:3px solid var(--brand-red);font-weight:600}
        .nav-link i{font-size:1.1rem;width:20px;text-align:center;color:var(--text-muted)}
        .nav-link.active i{color:var(--brand-red)}
        .user-section{padding:1rem 1.5rem;border-top:1px solid var(--border-color);font-size:.9rem;display:flex;align-items:center;gap:8px}
        .user-section a{color:var(--brand-red);text-decoration:none;font-weight:600;margin-left:auto}

        /* === MAIN CONTENT === */
        .main-content{flex:1;margin-left:var(--sidebar-width);display:flex;flex-direction:column}
        .top-header{background:#fff;padding:1rem 2rem;display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid var(--border-color);position:sticky;top:0;z-index:50}
        .content-area{flex:1;padding:2rem;overflow-y:auto;max-width:1000px;margin:0 auto;width:100%}
        
        .page-header{margin-bottom:2rem;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem}
        .page-header h1{font-size:1.8rem;font-weight:700;color:var(--text-dark)}
        .btn-back{background:var(--brand-red);color:#fff;text-decoration:none;padding:10px 20px;border-radius:8px;font-weight:600;font-size:.9rem;display:inline-flex;align-items:center;gap:8px;transition:background .2s}
        .btn-back:hover{background:#6d0000}

        /* === PROFILE CARDS === */
        .profile-card{background:#fff;border-radius:12px;padding:2rem;box-shadow:0 2px 8px rgba(0,0,0,.06);border:1px solid var(--border-color);margin-bottom:2rem}
        .profile-card h3{font-size:1.2rem;font-weight:700;color:var(--brand-red);margin-bottom:1.5rem;display:flex;align-items:center;gap:8px}
        
        .form-group{margin-bottom:1.2rem}
        .form-group label{display:block;font-weight:600;font-size:.85rem;margin-bottom:.4rem;color:var(--text-dark)}
        .form-group input{width:100%;padding:11px 14px;border:1px solid var(--border-color);border-radius:8px;font-size:.95rem;outline:none;transition:border .2s}
        .form-group input:focus{border-color:var(--brand-red)}
        .btn-save{background:var(--brand-red);color:#fff;border:none;padding:12px 24px;border-radius:8px;font-weight:600;cursor:pointer;font-size:1rem;transition:background .2s}
        .btn-save:hover{background:#6d0000}

        /* === ORDER HISTORY === */
        .order-block{background:#fff;border-radius:12px;padding:1.5rem;border:1px solid var(--border-color);margin-bottom:1rem;transition:transform .2s}
        .order-block:hover{transform:translateY(-2px);box-shadow:0 4px 12px rgba(0,0,0,.08)}
        .order-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;padding-bottom:1rem;border-bottom:1px solid var(--border-color);flex-wrap:wrap;gap:10px}
        .order-id{font-weight:700;color:var(--brand-red);font-size:1.05rem}
        .order-date{font-size:.85rem;color:var(--text-muted);display:flex;align-items:center;gap:5px}
        .order-status{font-size:.8rem;font-weight:700;padding:4px 12px;border-radius:20px;background:var(--brand-light);color:var(--brand-red)}
        
        .order-item-row{display:flex;justify-content:space-between;padding:8px 0;font-size:.95rem;border-bottom:1px dashed var(--border-color)}
        .order-item-row:last-child{border-bottom:none}
        .item-name{flex:1;font-weight:500;color:var(--text-dark)}
        .item-qty{width:60px;text-align:center;color:var(--text-muted)}
        .item-price{width:100px;text-align:right;font-weight:600;color:var(--text-dark)}
        
        .order-footer{display:flex;justify-content:space-between;margin-top:1rem;padding-top:1rem;border-top:2px solid var(--border-color);font-weight:700;font-size:1.1rem}
        .order-total-amount{color:var(--brand-red)}

        .alert{padding:12px 16px;border-radius:8px;margin-bottom:1.5rem;font-weight:600;display:flex;align-items:center;gap:8px;font-size:.95rem}
        .alert-success{background:#dcfce7;color:#166534;border:1px solid #bbf7d0}
        .alert-error{background:#fee2e2;color:#991b1b;border:1px solid #fecaca}
        
        .empty-state{text-align:center;padding:3rem;background:#fff;border-radius:12px;border:1px solid var(--border-color);color:var(--text-muted)}
        .empty-state i{font-size:3rem;color:#ddd;margin-bottom:1rem}
        .empty-state a{color:var(--brand-red);text-decoration:none;font-weight:600;margin-top:1rem;display:inline-block}

        @media(max-width:768px){
            .sidebar{transform:translateX(-100%);transition:transform .3s}
            .sidebar.open{transform:translateX(0)}
            .main-content{margin-left:0}
            .mobile-toggle{display:block!important}
        }
        .mobile-toggle{display:none;background:none;border:none;font-size:1.5rem;cursor:pointer;color:var(--text-dark)}
    </style>
</head>
<body>
    <aside class="sidebar" id="sidebar">
        <div class="logo"><i class="bi bi-building"></i> Meryll Eatery</div>
        <div class="nav-section">
            <a href="index.php" class="nav-link"><i class="bi bi-calendar-day"></i> Today's Menu</a>
            <a href="my_orders.php" class="nav-link"><i class="bi bi-receipt"></i> My Orders</a>
            <a href="profile.php" class="nav-link active"><i class="bi bi-person-circle"></i> My Profile</a>
        </div>
        <div class="user-section">
            <i class="bi bi-person-circle"></i> <span><?=htmlspecialchars($username)?></span>
            <a href="logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
        </div>
    </aside>

    <main class="main-content">
        <header class="top-header">
            <button class="mobile-toggle" onclick="document.getElementById('sidebar').classList.toggle('open')"><i class="bi bi-list"></i></button>
            <div style="font-weight:700;color:var(--brand-red);font-size:1.1rem">My Profile</div>
            <div style="width:42px"></div> <!-- Spacer for alignment -->
        </header>

        <div class="content-area">
            <div class="page-header">
                <div>
                    <h1>Account Settings</h1>
                    <p style="color:var(--text-muted);font-size:.95rem">Update your details and view your canteen order history.</p>
                </div>
                <a href="index.php" class="btn-back"><i class="bi bi-arrow-left"></i> Back to Menu</a>
            </div>

            <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success"><i class="bi bi-check-circle-fill"></i> Profile updated successfully!</div>
            <?php endif; ?>
            
            <?php if (isset($_GET['error'])): ?>
                <div class="alert alert-error"><i class="bi bi-exclamation-circle-fill"></i> Failed to update profile. Please try again.</div>
            <?php endif; ?>

            <!-- Account Details Card -->
            <div class="profile-card">
                <h3><i class="bi bi-id-card"></i> Account Details</h3>
                <form action="update_profile.php" method="POST">
                    <div class="form-group">
                        <label>Username</label>
                        <input type="text" name="username" value="<?=htmlspecialchars($user['username'])?>" required>
                    </div>
                    <div class="form-group">
                        <label>Email Address</label>
                        <input type="email" name="email" value="<?=htmlspecialchars($user['email'])?>" required>
                    </div>
                    <button type="submit" class="btn-save"><i class="bi bi-save"></i> Save Changes</button>
                </form>
            </div>

            <!-- Order History Card -->
            <div class="profile-card" style="padding:0;overflow:hidden;background:transparent;border:none;box-shadow:none">
                <h3 style="background:#fff;padding:1.5rem 2rem;border-radius:12px 12px 0 0;border:1px solid var(--border-color);border-bottom:none;margin-bottom:0">
                    <i class="bi bi-bag-check"></i> Order History
                </h3>
                
                <?php if (empty($orders)): ?>
                    <div class="empty-state" style="border-radius:0 0 12px 12px;border-top:none">
                        <i class="bi bi-receipt"></i>
                        <h3 style="color:var(--text-dark);margin-bottom:.5rem">No Orders Yet</h3>
                        <p>You haven't placed any orders yet.</p>
                        <a href="index.php"><i class="bi bi-shop"></i> Browse the menu now</a>
                    </div>
                <?php else: ?>
                    <?php foreach ($orders as $orderNum => $order): ?>
                        <div class="order-block" style="border-radius:0;border-top:none">
                            <div class="order-header">
                                <span class="order-id">#<?=htmlspecialchars($orderNum)?></span>
                                <span class="order-date"><i class="bi bi-calendar3"></i> <?=date('M d, Y h:i A', strtotime($order['date']))?></span>
                                <span class="order-status"><?=htmlspecialchars($order['status'])?></span>
                            </div>
                            <div class="order-items">
                                <?php foreach ($order['items'] as $item): ?>
                                    <div class="order-item-row">
                                        <span class="item-name"><?=htmlspecialchars($item['name'])?></span>
                                        <span class="item-qty">x<?=intval($item['qty'])?></span>
                                        <span class="item-price">₱<?=number_format($item['subtotal'], 2)?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="order-footer">
                                <span>Total Paid</span>
                                <span class="order-total-amount">₱<?=number_format($order['total'], 2)?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script>
        // Close sidebar on mobile when clicking outside
        document.addEventListener('click', function(e) {
            const sidebar = document.getElementById('sidebar');
            const toggle = document.querySelector('.mobile-toggle');
            if (window.innerWidth <= 768 && sidebar.classList.contains('open') && !sidebar.contains(e.target) && !toggle.contains(e.target)) {
                sidebar.classList.remove('open');
            }
        });
    </script>
</body>
</html>