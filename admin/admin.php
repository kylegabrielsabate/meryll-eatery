<?php
session_start(); 
include '../connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') { 
    header("Location: ../user/login.php"); exit; 
}
$adminName = $_SESSION['username'];

$message = ""; $msg_type = "";
$active_tab = $_GET['tab'] ?? 'ulam';

// ✅ FIXED: Correct folder mappings for your GitHub structure
$cat_map = [
    'ulam' => ['id' => 1, 'name' => 'Ulam', 'folder' => 'ulam', 'use_webcam' => true],
    'meryenda' => ['id' => 2, 'name' => 'Meryenda', 'folder' => 'meryenda', 'use_webcam' => false],
    'candies' => ['id' => 3, 'name' => 'Candies', 'folder' => 'candies', 'use_webcam' => false],
    'coffee' => ['id' => 4, 'name' => 'Coffee', 'folder' => 'drinks', 'use_webcam' => false],
    'drinks' => ['id' => 5, 'name' => 'Drinks', 'folder' => 'drinks', 'use_webcam' => false],
    'noodles' => ['id' => 6, 'name' => 'Noodles', 'folder' => 'noodles', 'use_webcam' => false],
    'essentials' => ['id' => 7, 'name' => 'Essentials', 'folder' => 'essentials', 'use_webcam' => false],
];

// ============ HANDLE ADD ITEM ============
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_item'])) {
    $tab = $_POST['tab'] ?? 'ulam';
    $cat = $cat_map[$tab] ?? null;
    if (!$cat) { $message = "error:Invalid category."; }
    else {
        $name = $con->real_escape_string(trim($_POST['item_name']));
        $price = (float)$_POST['item_price'];
        $target_dir = "../images/" . $cat['folder'] . "/";
        if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
        
        $image_name = ""; $upload_ok = 1;
        
        if ($cat['use_webcam']) {
            $image_data = $_POST['item_image_base64'] ?? '';
            if (empty($image_data) || !preg_match('/^data:image\/(jpeg|png);base64,/', $image_data)) {
                $message = "error:Please capture a picture."; $upload_ok = 0;
            } else {
                preg_match('/^data:image\/(jpeg|png);base64,/', $image_data, $matches);
                $ext = $matches[1] === 'jpeg' ? 'jpg' : 'png';
                $image_name = time() . "_" . preg_replace('/[^a-z0-9]+/', '_', strtolower($name)) . "." . $ext;
                $target_file = $target_dir . $image_name;
                $base64_string = str_replace('data:image/' . $matches[1] . ';base64,', '', $image_data);
                if (file_put_contents($target_file, base64_decode($base64_string)) === false) {
                    $message = "error:Error saving image."; $upload_ok = 0;
                }
            }
        } else {
            if (!isset($_FILES['item_image']) || $_FILES['item_image']['error'] !== UPLOAD_ERR_OK) {
                $message = "error:Please upload a picture."; $upload_ok = 0;
            } else {
                $ext = strtolower(pathinfo($_FILES['item_image']['name'], PATHINFO_EXTENSION));
                if (!in_array($ext, ['jpg','jpeg','png','webp'])) {
                    $message = "error:Only JPG/PNG/WEBP allowed."; $upload_ok = 0;
                } else {
                    $image_name = time() . "_" . preg_replace('/[^a-z0-9]+/', '_', strtolower($name)) . "." . $ext;
                    $target_file = $target_dir . $image_name;
                    if (!move_uploaded_file($_FILES['item_image']['tmp_name'], $target_file)) {
                        $message = "error:Error saving image."; $upload_ok = 0;
                    }
                }
            }
        }
        
        if ($upload_ok == 1) {
            $stmt = $con->prepare("INSERT INTO products (productname, categoryID, unit, price, stock, productImage, is_available) VALUES (?, ?, '1 pc', ?, 50, ?, 1)");
            $stmt->bind_param("sids", $name, $cat['id'], $price, $image_name);
            if ($stmt->execute()) {
                $message = "success:" . $name . " added to " . $cat['name'] . "!";
            } else {
                $message = "error:DB error: " . $con->error;
            }
        }
    }
}

// ============ HANDLE TOGGLE VISIBILITY ============
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_item'])) {
    $id = (int)$_POST['product_id'];
    $stmt = $con->prepare("UPDATE products SET is_available = 1 - is_available WHERE productID = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        $message = "success:Item visibility toggled!";
    } else {
        $message = "error:Failed to toggle.";
    }
}

// ============ HANDLE DELETE ITEM ============
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_item'])) {
    $id = (int)$_POST['product_id'];
    $tab = $_POST['tab'] ?? 'ulam';
    $cat = $cat_map[$tab] ?? null;
    
    if ($cat && $id > 0) {
        $stmt = $con->prepare("SELECT productImage FROM products WHERE productID = ? AND categoryID = ?");
        $stmt->bind_param("ii", $id, $cat['id']);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        
        if ($row && !empty($row['productImage'])) {
            $file = "../images/" . $cat['folder'] . "/" . $row['productImage'];
            if (file_exists($file)) @unlink($file);
        }
        
        $stmt = $con->prepare("DELETE FROM products WHERE productID = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $message = "success:Item permanently deleted!";
        } else {
            $message = "error:Failed to delete.";
        }
    }
}

// ============ FETCH ITEMS PER CATEGORY ============
$cat_items = [];
foreach ($cat_map as $key => $cat) {
    $res = $con->prepare("SELECT productID, productname, price, productImage, is_available FROM products WHERE categoryID = ? ORDER BY productID DESC");
    $res->bind_param("i", $cat['id']);
    $res->execute();
    $db_items = $res->get_result()->fetch_all(MYSQLI_ASSOC);
    $cat_items[$key] = $db_items;
}

// ============ STATS ============
$stats = ['total_orders' => 0, 'total_revenue' => 0, 'total_customers' => 0];
$r1 = $con->query("SELECT COUNT(*) as c FROM users WHERE role='customer'"); if($r1) $stats['total_customers'] = $r1->fetch_assoc()['c'];
$r2 = $con->query("SELECT COUNT(*) as c FROM orders WHERE DATE(created_at)=CURDATE()"); if($r2) $stats['total_orders'] = $r2->fetch_assoc()['c'];
$r3 = $con->query("SELECT SUM(total_amount) as r FROM orders WHERE DATE(created_at)=CURDATE()"); if($r3) $stats['total_revenue'] = $r3->fetch_assoc()['r'] ?? 0;

$top_ulam = $con->query("SELECT item_name, SUM(quantity) as total_sold, SUM(price*quantity) as total_revenue FROM order_items GROUP BY item_name ORDER BY total_sold DESC LIMIT 5");
$recent_orders = $con->query("SELECT o.*, u.username FROM orders o JOIN users u ON o.user_id=u.user_id ORDER BY o.created_at DESC LIMIT 5");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Command Center | Meryll Eatery</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="admin_style.css">
    <style>
        :root { --admin-accent: #991b1b; --admin-border: #e2e8f0; --admin-text: #0f172a; --admin-text-muted: #64748b; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: #f1f5f9; }
        .sidebar { position: fixed; left: 0; top: 0; width: 260px; height: 100vh; background: #1e293b; color: white; padding: 1.5rem; z-index: 1000; transition: transform 0.3s; }
        .sidebar-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 999; }
        .sidebar-overlay.active { display: block; }
        .sidebar-close { display: none; position: absolute; top: 1rem; right: 1rem; background: none; border: none; color: white; font-size: 1.5rem; cursor: pointer; }
        .logo { display: block; font-size: 1.3rem; font-weight: 700; color: white; margin-bottom: 2rem; text-decoration: none; }
        .nav-menu { list-style: none; }
        .nav-item { margin-bottom: 0.5rem; }
        .nav-link { display: flex; align-items: center; gap: 10px; padding: 12px 15px; color: #cbd5e1; text-decoration: none; border-radius: 8px; transition: all 0.2s; }
        .nav-link:hover, .nav-link.active { background: #334155; color: white; }
        .badge-admin { background: #991b1b; color: white; padding: 2px 8px; border-radius: 12px; font-size: 0.7rem; margin-left: auto; }
        .sidebar-footer { position: absolute; bottom: 0; left: 0; right: 0; padding: 1rem; border-top: 1px solid #334155; }
        .main-content { margin-left: 260px; padding: 2rem; }
        .admin-container { max-width: 1400px; margin: 0 auto; }
        .admin-header { margin-bottom: 2rem; }
        .admin-header h1 { font-size: 1.8rem; color: #0f172a; margin-bottom: 0.5rem; }
        .admin-header p { color: #64748b; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; }
        .stat-card { background: white; padding: 1.5rem; border-radius: 12px; display: flex; align-items: center; gap: 1rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .stat-icon { width: 50px; height: 50px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; }
        .stat-icon.red { background: #fee2e2; color: #ef4444; }
        .stat-icon.dark { background: #1e293b; color: white; }
        .stat-icon.coral { background: #ffedd5; color: #f97316; }
        .stat-info h3 { font-size: 0.85rem; color: #64748b; margin-bottom: 0.25rem; }
        .stat-info p { font-size: 1.5rem; font-weight: 700; color: #0f172a; }
        .admin-card { background: white; border-radius: 12px; padding: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .admin-card h3 { font-size: 1.2rem; margin-bottom: 1.5rem; color: #0f172a; }
        .tab-nav { display: flex; gap: 8px; margin-bottom: 1.5rem; flex-wrap: wrap; border-bottom: 2px solid var(--admin-border); padding-bottom: 0; }
        .tab-btn { padding: 10px 18px; background: none; border: none; border-bottom: 3px solid transparent; cursor: pointer; font-weight: 600; color: var(--admin-text-muted); font-family: inherit; font-size: 0.9rem; transition: all 0.2s; }
        .tab-btn:hover { color: var(--admin-text); }
        .tab-btn.active { color: var(--admin-accent); border-bottom-color: var(--admin-accent); }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        .dashboard-grid { display: grid; grid-template-columns: 1fr 2fr; gap: 2rem; }
        .upload-form { background: #f8fafc; padding: 1.5rem; border-radius: 10px; }
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-weight: 600; color: #334155; font-size: 0.9rem; }
        .form-group input[type="text"], .form-group input[type="number"] { width: 100%; padding: 10px; border: 1px solid #e2e8f0; border-radius: 8px; font-family: inherit; }
        .btn { padding: 10px 20px; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.2s; font-family: inherit; }
        .btn-primary { background: #991b1b; color: white; }
        .btn-primary:hover { background: #7f1d1d; }
        .btn-secondary { background: #64748b; color: white; }
        .btn-danger { background: #ef4444; color: white; }
        .item-list { display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 1rem; margin-top: 1rem; }
        .item-card { background: #fff; border: 1px solid var(--admin-border); border-radius: 10px; overflow: hidden; position: relative; transition: transform 0.2s; }
        .item-card:hover { transform: translateY(-3px); box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        .item-card.hidden-item { opacity: 0.6; filter: grayscale(80%); }
        .item-card img { width: 100%; height: 120px; object-fit: cover; }
        .item-card-body { padding: 8px; }
        .item-card-name { font-size: 0.85rem; font-weight: 600; margin-bottom: 4px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .item-card-price { font-size: 0.8rem; color: var(--admin-accent); font-weight: 700; }
        .card-actions { position: absolute; top: 6px; right: 6px; display: flex; gap: 5px; z-index: 10; }
        .action-btn-icon { width: 28px; height: 28px; border-radius: 50%; border: none; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 0.8rem; color: white; transition: transform 0.2s; }
        .action-btn-icon:hover { transform: scale(1.1); }
        .btn-toggle { background: rgba(0,0,0,0.6); }
        .btn-toggle:hover { background: var(--admin-accent); }
        .btn-delete { background: rgba(220, 53, 69, 0.9); }
        .btn-delete:hover { background: #dc3545; }
        .item-status-badge { position: absolute; top: 6px; left: 6px; padding: 4px 8px; border-radius: 12px; font-size: 0.7rem; font-weight: 700; color: white; }
        .badge-visible { background: #10b981; }
        .badge-hidden { background: #6c757d; }
        .upload-area { background: #000; border-radius: 10px; overflow: hidden; margin-bottom: 10px; aspect-ratio: 4/3; position: relative; }
        .upload-area video, .upload-area img { width: 100%; height: 100%; object-fit: cover; }
        .upload-area .placeholder { color: #fff; display: flex; align-items: center; justify-content: center; height: 100%; text-align: center; padding: 1rem; }
        .file-input-wrapper input[type=file] { width: 100%; padding: 10px; border: 2px dashed var(--admin-border); border-radius: 10px; background: #f8fafc; cursor: pointer; }
        .confirm-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 9998; justify-content: center; align-items: center; backdrop-filter: blur(4px); }
        .confirm-overlay.active { display: flex; }
        .confirm-box { background: white; padding: 2rem; border-radius: 16px; text-align: center; max-width: 400px; width: 90%; box-shadow: 0 20px 60px rgba(0,0,0,0.3); animation: popIn 0.3s ease; }
        .confirm-icon { font-size: 3rem; color: #ef4444; margin-bottom: 1rem; }
        .confirm-title { font-size: 1.2rem; font-weight: 700; margin-bottom: 0.5rem; color: #0f172a; }
        .confirm-msg { color: #64748b; margin-bottom: 1.5rem; font-size: 0.95rem; }
        .confirm-actions { display: flex; gap: 10px; justify-content: center; }
        .btn-confirm-yes { background: #ef4444; color: white; border: none; padding: 10px 24px; border-radius: 8px; font-weight: 600; cursor: pointer; transition: 0.2s; }
        .btn-confirm-yes:hover { background: #dc2626; }
        .btn-confirm-no { background: #e2e8f0; color: #334155; border: none; padding: 10px 24px; border-radius: 8px; font-weight: 600; cursor: pointer; transition: 0.2s; }
        .btn-confirm-no:hover { background: #cbd5e1; }
        @keyframes popIn { from { opacity: 0; transform: scale(0.8); } to { opacity: 1; transform: scale(1); } }
        .toast-container { position: fixed; top: 20px; right: 20px; z-index: 10000; display: flex; flex-direction: column; gap: 10px; }
        .toast { padding: 14px 20px; border-radius: 10px; color: white; font-weight: 600; font-size: 0.9rem; box-shadow: 0 5px 15px rgba(0,0,0,0.2); animation: slideIn 0.3s ease; display: flex; align-items: center; gap: 10px; min-width: 250px; }
        .toast.success { background: #10b981; }
        .toast.error { background: #ef4444; }
        @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
        @keyframes slideOut { from { transform: translateX(0); opacity: 1; } to { transform: translateX(100%); opacity: 0; } }
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.active { transform: translateX(0); }
            .sidebar-close { display: block; }
            .main-content { margin-left: 0; }
            .dashboard-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>
    <aside class="sidebar" id="sidebar">
        <button class="sidebar-close" onclick="closeSidebar()"><i class="fas fa-arrow-left"></i></button>
        <a href="admin.php" class="logo"><i class="fas fa-utensils"></i> <span>Meryll Eatery Admin</span></a>
        <ul class="nav-menu">
            <li class="nav-item"><a href="admin.php" class="nav-link active"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li class="nav-item"><a href="admin_orders.php" class="nav-link"><i class="fas fa-clipboard-list"></i> Manage Orders</a></li>
            <li class="nav-item"><a href="admin_inventory.php" class="nav-link"><i class="fas fa-boxes-stacked"></i> Inventory</a></li>
            <li class="nav-item"><a href="admin_report.php" class="nav-link"><i class="fas fa-chart-line"></i> Monthly Report</a></li>
        </ul>
        <div class="sidebar-footer">
            <a href="admin.php" class="nav-link"><i class="fas fa-user-shield"></i> <?=htmlspecialchars($adminName)?> <span class="badge-admin">ADMIN</span></a>
            <a href="../user/logout.php" class="nav-link" style="color:#f87171;"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </aside>

    <main class="main-content">
        <div class="admin-container">
            <div class="admin-header">
                <div>
                    <h1>Admin Command Center</h1>
                    <p>Welcome back, <?=htmlspecialchars($adminName)?>.</p>
                </div>
            </div>

            <div class="stats-grid">
                <div class="stat-card"><div class="stat-icon red"><i class="fas fa-shopping-bag"></i></div><div class="stat-info"><h3>Today's Orders</h3><p><?=$stats['total_orders']?></p></div></div>
                <div class="stat-card"><div class="stat-icon dark"><i class="fas fa-coins"></i></div><div class="stat-info"><h3>Today's Revenue</h3><p><?=number_format($stats['total_revenue'],2)?></p></div></div>
                <div class="stat-card"><div class="stat-icon coral"><i class="fas fa-users"></i></div><div class="stat-info"><h3>Total Customers</h3><p><?=$stats['total_customers']?></p></div></div>
            </div>

            <div class="admin-card">
                <h3><i class="fas fa-layer-group"></i> Menu Manager</h3>
                
                <div class="tab-nav">
                    <?php foreach ($cat_map as $key => $cat): ?>
                        <button class="tab-btn <?=$active_tab==$key?'active':''?>" onclick="switchTab('<?=$key?>')">
                            <?=$cat['name']?> <span style="background:var(--admin-accent);color:#fff;padding:2px 8px;border-radius:10px;font-size:0.7rem;margin-left:6px;"><?=count($cat_items[$key])?></span>
                        </button>
                    <?php endforeach; ?>
                </div>

                <?php foreach ($cat_map as $key => $cat): ?>
                <div class="tab-content <?=$active_tab==$key?'active':''?>" id="tab-<?=$key?>">
                    <div class="dashboard-grid">
                        <div>
                            <h4 style="margin-bottom:1rem;"><i class="fas fa-plus-circle" style="color:var(--admin-accent);"></i> Add to <?=$cat['name']?></h4>
                            <form method="POST" enctype="<?=$cat['use_webcam']?'':'multipart/form-data'?>" class="upload-form" id="form-<?=$key?>">
                                <input type="hidden" name="tab" value="<?=$key?>">
                                <div class="form-group">
                                    <label>Item Name</label>
                                    <input type="text" name="item_name" required>
                                </div>
                                <div class="form-group">
                                    <label>Price (₱)</label>
                                    <input type="number" step="0.01" name="item_price" required>
                                </div>
                                
                                <?php if ($cat['use_webcam']): ?>
                                <div class="form-group">
                                    <label>Capture Picture</label>
                                    <div class="upload-area">
                                        <video id="webcam-<?=$key?>" autoplay playsinline style="display:none;"></video>
                                        <img id="preview-<?=$key?>" style="display:none;" alt="Captured">
                                        <canvas id="canvas-<?=$key?>" style="display:none;"></canvas>
                                        <div class="placeholder" id="placeholder-<?=$key?>"><div><i class="fas fa-camera" style="font-size:2rem;margin-bottom:0.5rem;display:block;"></i>Click "Start Camera"</div></div>
                                    </div>
                                    <div style="display:flex;gap:10px;">
                                        <button type="button" class="btn btn-secondary" id="startCam-<?=$key?>" style="flex:1;"><i class="fas fa-video"></i> Start</button>
                                        <button type="button" class="btn btn-primary" id="capture-<?=$key?>" style="flex:1;display:none;"><i class="fas fa-camera"></i> Capture</button>
                                        <button type="button" class="btn btn-danger" id="retake-<?=$key?>" style="flex:1;display:none;"><i class="fas fa-redo"></i> Retake</button>
                                    </div>
                                    <input type="hidden" name="item_image_base64" id="base64-<?=$key?>">
                                </div>
                                <?php else: ?>
                                <div class="form-group">
                                    <label>Upload Picture</label>
                                    <div class="file-input-wrapper">
                                        <input type="file" name="item_image" accept="image/*" required>
                                    </div>
                                </div>
                                <?php endif; ?>

                                <button type="submit" name="add_item" class="btn btn-primary" style="width:100%;"><i class="fas fa-plus"></i> Add Item</button>
                            </form>
                        </div>

                        <div>
                            <h4 style="margin-bottom:1rem;"><i class="fas fa-list" style="color:var(--admin-accent);"></i> Current <?=$cat['name']?></h4>
                            <?php if (empty($cat_items[$key])): ?>
                                <p style="text-align:center;color:#94a3b8;padding:2rem;background:#f8fafc;border-radius:10px;">No items yet.</p>
                            <?php else: ?>
                                <div class="item-list">
                                    <?php foreach ($cat_items[$key] as $item): 
                                        $is_visible = (bool)$item['is_available'];
                                    ?>
                                    <div class="item-card <?=$is_visible?'':'hidden-item'?>">
                                        <div class="item-status-badge <?=$is_visible?'badge-visible':'badge-hidden'?>">
                                            <?=$is_visible?'Visible':'Hidden'?>
                                        </div>
                                        
                                        <div class="card-actions">
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="product_id" value="<?=$item['productID']?>">
                                                <input type="hidden" name="tab" value="<?=$key?>">
                                                <button type="submit" name="toggle_item" class="action-btn-icon btn-toggle" title="<?=$is_visible?'Hide Item':'Show Item'?>">
                                                    <i class="fas fa-<?=$is_visible?'eye':'eye-slash'?>"></i>
                                                </button>
                                            </form>
                                            
                                            <button type="button" class="action-btn-icon btn-delete" onclick="confirmDelete(<?=$item['productID']?>, '<?=$key?>', '<?=htmlspecialchars($item['productname'])?>')" title="Delete Permanently">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                            <form id="delete-form-<?=$item['productID']?>" method="POST" style="display:none;">
                                                <input type="hidden" name="product_id" value="<?=$item['productID']?>">
                                                <input type="hidden" name="tab" value="<?=$key?>">
                                                <input type="hidden" name="delete_item" value="1">
                                            </form>
                                        </div>

                                        <img src="../images/<?=$cat['folder']?>/<?=$item['productImage']?>" alt="<?=htmlspecialchars($item['productname'])?>" onerror="this.src='https://via.placeholder.com/160x120?text=No+Image'">
                                        <div class="item-card-body">
                                            <div class="item-card-name"><?=htmlspecialchars($item['productname'])?></div>
                                            <div class="item-card-price"><?=number_format($item['price'],2)?></div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </main>

    <div class="toast-container" id="toastContainer"></div>

    <div class="confirm-overlay" id="confirmOverlay">
        <div class="confirm-box">
            <div class="confirm-icon"><i class="fas fa-exclamation-triangle"></i></div>
            <div class="confirm-title">Are you sure?</div>
            <div class="confirm-msg" id="confirmMsg">This action cannot be undone.</div>
            <div class="confirm-actions">
                <button class="btn-confirm-no" onclick="closeConfirm()">Cancel</button>
                <button class="btn-confirm-yes" id="btnConfirmYes">Yes, Delete</button>
            </div>
        </div>
    </div>

    <script>
        function closeSidebar() { document.getElementById('sidebar').classList.remove('active'); document.getElementById('sidebarOverlay').classList.remove('active'); }
        function switchTab(tab) {
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            event.currentTarget.classList.add('active');
            document.getElementById('tab-' + tab).classList.add('active');
        }
        function showToast(type, message) {
            const container = document.getElementById('toastContainer');
            const toast = document.createElement('div');
            toast.className = 'toast ' + type;
            toast.innerHTML = '<i class="fas fa-' + (type==='success'?'check-circle':'exclamation-circle') + '"></i> ' + message;
            container.appendChild(toast);
            setTimeout(() => {
                toast.style.animation = 'slideOut 0.3s ease forwards';
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }
        <?php if ($message): ?>
            <?php 
            $type = strpos($message, 'success:') === 0 ? 'success' : 'error';
            $msg = str_replace(['success:', 'error:'], '', $message);
            ?>
            showToast('<?=$type?>', '<?=$msg?>');
        <?php endif; ?>
        let pendingDeleteFormId = null;
        function confirmDelete(id, tab, name) {
            pendingDeleteFormId = 'delete-form-' + id;
            document.getElementById('confirmMsg').innerText = 'Are you sure you want to permanently delete "' + name + '"? The picture will be removed from the menu.';
            document.getElementById('confirmOverlay').classList.add('active');
        }
        function closeConfirm() {
            document.getElementById('confirmOverlay').classList.remove('active');
            pendingDeleteFormId = null;
        }
        document.getElementById('btnConfirmYes').addEventListener('click', function() {
            if (pendingDeleteFormId) {
                document.getElementById(pendingDeleteFormId).submit();
            }
        });
        document.getElementById('confirmOverlay').addEventListener('click', function(e) {
            if (e.target === this) closeConfirm();
        });
        const video = document.getElementById('webcam-ulam');
        const canvas = document.getElementById('canvas-ulam');
        const preview = document.getElementById('preview-ulam');
        const placeholder = document.getElementById('placeholder-ulam');
        const startBtn = document.getElementById('startCam-ulam');
        const captureBtn = document.getElementById('capture-ulam');
        const retakeBtn = document.getElementById('retake-ulam');
        const hiddenInput = document.getElementById('base64-ulam');
        let stream = null;
        if (startBtn) {
            startBtn.addEventListener('click', async () => {
                try {
                    stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
                    video.srcObject = stream;
                    video.style.display = 'block'; placeholder.style.display = 'none';
                    startBtn.style.display = 'none'; captureBtn.style.display = 'block';
                } catch (err) { showToast('error', 'Camera access denied.'); }
            });
            captureBtn.addEventListener('click', () => {
                canvas.width = video.videoWidth; canvas.height = video.videoHeight;
                canvas.getContext('2d').drawImage(video, 0, 0);
                hiddenInput.value = canvas.toDataURL('image/jpeg', 0.85);
                preview.src = hiddenInput.value; preview.style.display = 'block';
                video.style.display = 'none'; captureBtn.style.display = 'none'; retakeBtn.style.display = 'block';
                if (stream) stream.getTracks().forEach(t => t.stop());
            });
            retakeBtn.addEventListener('click', async () => {
                hiddenInput.value = ''; preview.style.display = 'none'; retakeBtn.style.display = 'none';
                startBtn.style.display = 'block'; placeholder.style.display = 'flex';
                try {
                    stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
                    video.srcObject = stream; video.style.display = 'block'; placeholder.style.display = 'none';
                } catch(e) {}
            });
            document.getElementById('form-ulam').addEventListener('submit', function(e) {
                if (!hiddenInput.value) { e.preventDefault(); showToast('error', 'Please capture a picture first!'); }
            });
        }
    </script>
</body>
</html>