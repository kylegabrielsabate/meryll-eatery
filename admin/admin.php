<?php
session_start();
include '../connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../user/login.php');
    exit();
}

$admin_name = $_SESSION['username'];
$admin_id = $_SESSION['user_id'];

// Fetch unread notifications count
$stmt = $con->prepare("SELECT COUNT(*) as count FROM notifications WHERE admin_id = ? AND is_read = 0");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$unread_count = $stmt->get_result()->fetch_assoc()['count'] ?? 0;

// Fetch recent notifications
$stmt = $con->prepare("SELECT * FROM notifications WHERE admin_id = ? ORDER BY created_at DESC LIMIT 10");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$recent_notifs = $stmt->get_result();

// ✅ FETCH REAL STATS FROM DATABASE
$stmt = $con->prepare("SELECT COUNT(*) as count FROM orders WHERE status = 'Pending'");
$stmt->execute();
$pending_count = $stmt->get_result()->fetch_assoc()['count'] ?? 0;

$stmt = $con->prepare("SELECT COUNT(*) as count FROM orders WHERE status = 'Completed' AND DATE(created_at) = CURDATE()");
$stmt->execute();
$completed_count = $stmt->get_result()->fetch_assoc()['count'] ?? 0;

$stmt = $con->prepare("SELECT SUM(total_amount) as total FROM orders WHERE status = 'Completed'");
$stmt->execute();
$total_revenue = $stmt->get_result()->fetch_assoc()['total'] ?? 0;

// Fetch ALL products for dashboard grid
$products = $con->query("SELECT * FROM products ORDER BY FIELD(categoryID, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10), productID DESC");

// Handle Quick Product Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['quick_update'])) {
    $product_id = (int)$_POST['product_id'];
    $product_name = $con->real_escape_string(trim($_POST['product_name']));
    $price = (float)$_POST['price'];
    $stock = (int)$_POST['stock'];
    
    $image_path = '';
    if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === 0) {
        $upload_dir = '../images/products/';
        if (!file_exists($upload_dir)) { mkdir($upload_dir, 0777, true); }
        $file_ext = strtolower(pathinfo($_FILES['product_image']['name'], PATHINFO_EXTENSION));
        $new_filename = uniqid() . '.' . $file_ext;
        $upload_path = $upload_dir . $new_filename;
        if (move_uploaded_file($_FILES['product_image']['tmp_name'], $upload_path)) { $image_path = $new_filename; }
    }
    
    if ($image_path) {
        $stmt = $con->prepare("UPDATE products SET productname=?, price=?, stock=?, productImage=? WHERE productID=?");
        $stmt->bind_param("sdisi", $product_name, $price, $stock, $image_path, $product_id);
    } else {
        $stmt = $con->prepare("UPDATE products SET productname=?, price=?, stock=? WHERE productID=?");
        $stmt->bind_param("sdii", $product_name, $price, $stock, $product_id);
    }
    if ($stmt->execute()) { $success_msg = "Product updated successfully!"; }
}

// Handle Toggle Visibility
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_visibility'])) {
    $product_id = (int)$_POST['product_id'];
    $new_visibility = (int)$_POST['is_visible'];
    $stmt = $con->prepare("UPDATE products SET is_visible=? WHERE productID=?");
    $stmt->bind_param("ii", $new_visibility, $product_id);
    if ($stmt->execute()) { $toggle_msg = "Product visibility updated!"; }
}

// Handle New Product from Camera
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_new_product'])) {
    $product_name = $con->real_escape_string(trim($_POST['product_name']));
    $category_id = (int)$_POST['category_id'];
    $price = (float)$_POST['price'];
    $stock = (int)$_POST['stock'];
    $unit = $con->real_escape_string(trim($_POST['unit']));
    
    $image_path = '';
    if (isset($_SESSION['camera_image_temp'])) {
        $upload_dir = '../images/products/';
        if (!file_exists($upload_dir)) { mkdir($upload_dir, 0777, true); }
        $file_ext = strtolower(pathinfo($_SESSION['camera_image_temp'], PATHINFO_EXTENSION));
        $new_filename = uniqid() . '.' . $file_ext;
        $upload_path = $upload_dir . $new_filename;
        if (rename($_SESSION['camera_image_temp'], $upload_path)) { $image_path = $new_filename; }
        unset($_SESSION['camera_image_temp']);
    }
    
    $stmt = $con->prepare("INSERT INTO products (productname, categoryID, unit, price, stock, productImage, is_available, is_visible) VALUES (?, ?, ?, ?, ?, ?, 1, 1)");
    $stmt->bind_param("sisdis", $product_name, $category_id, $unit, $price, $stock, $image_path);
    if ($stmt->execute()) { $success_msg = "Product added successfully!"; } 
    else { $error_msg = "Error adding product."; }
}

$categories = [
    1 => 'Ulam', 2 => 'Meryenda', 3 => 'Breakfast', 4 => 'Coffee', 
    5 => 'Drinks', 6 => 'Snacks', 7 => 'Candies', 8 => 'Noodles', 
    9 => 'Essentials', 10 => 'Accessories'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Dashboard - Meryll Eatery</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="admin_style.css">
</head>
<body>

<button class="mobile-menu-toggle" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>

<aside class="sidebar" id="sidebar">
    <button class="sidebar-close" onclick="toggleSidebar()"><i class="fas fa-arrow-left"></i></button>
    <a href="admin.php" class="logo">
        <span class="logo-icon"><i class="fas fa-utensils"></i></span> Meryll Eatery
    </a>
    <ul class="nav-menu">
        <li class="nav-item"><a href="admin.php" class="nav-link active"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
        <li class="nav-item"><a href="admin_orders.php" class="nav-link"><i class="fas fa-clipboard-list"></i> Manage Orders</a></li>
        <li class="nav-item"><a href="get_order_details.php" class="nav-link"><i class="fas fa-receipt"></i> Order Details</a></li>
        <li class="nav-item"><a href="admin_inventory.php" class="nav-link"><i class="fas fa-boxes-stacked"></i> Inventory</a></li>
        <li class="nav-item"><a href="admin_report.php" class="nav-link"><i class="fas fa-chart-line"></i> Monthly Report</a></li>
    </ul>
    <div class="sidebar-footer">
        <a href="admin.php" class="nav-link"><i class="fas fa-user-shield"></i> <?php echo htmlspecialchars($admin_name); ?></a>
        <a href="../user/logout.php" class="nav-link" style="color: #ef4444;"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
</aside>

<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

<main class="main-content">
    <div class="admin-container">
        <div class="admin-header">
            <div>
                <h1>Product Manager</h1>
                <p style="color: var(--admin-text-muted, #64748b);">Manage all products - Click to edit, toggle visibility for Ulams</p>
            </div>
        </div>

        <?php if (isset($success_msg)): ?><div class="alert-msg success"><i class="fas fa-check-circle"></i> <?php echo $success_msg; ?></div><?php endif; ?>
        <?php if (isset($toggle_msg)): ?><div class="alert-msg info"><i class="fas fa-info-circle"></i> <?php echo $toggle_msg; ?></div><?php endif; ?>

        <div class="camera-section">
            <h3><i class="fas fa-camera" style="color:#8B0000;"></i> Quick Add Product with Camera</h3>
            <div class="camera-upload-area" onclick="document.getElementById('cameraInput').click()">
                <i class="fas fa-camera"></i>
                <p><strong>Tap to take a photo</strong> or upload from gallery</p>
                <button class="camera-btn"><i class="fas fa-plus"></i> Add Product</button>
            </div>
            <input type="file" id="cameraInput" accept="image/*" capture="environment" onchange="handleCameraUpload(this)" style="display:none;">
        </div>

        <div class="filter-tabs">
            <button class="filter-tab active" onclick="filterProducts('all')">All Products</button>
            <button class="filter-tab" onclick="filterProducts('visible')">Visible</button>
            <button class="filter-tab" onclick="filterProducts('hidden')">Hidden</button>
            <button class="filter-tab" onclick="filterProducts('ulam')">Ulam Only</button>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon red"><i class="fas fa-receipt"></i></div>
                <div class="stat-info"><h3>Pending Orders</h3><p class="stat-number"><?= $pending_count ?></p></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon green"><i class="fas fa-check-circle"></i></div>
                <div class="stat-info"><h3>Completed Today</h3><p class="stat-number"><?= $completed_count ?></p></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon blue"><i class="fas fa-cash-register"></i></div>
                <div class="stat-info"><h3>Total Revenue</h3><p class="stat-number">₱<?= number_format($total_revenue, 2) ?></p></div>
            </div>
        </div>

        <div class="admin-card">
            <h3><i class="fas fa-th-large" style="color:#8B0000;"></i> All Products (<?php echo $products->num_rows; ?>)</h3>
            <p style="color:#64748b;margin-bottom:1rem;">Click any product to edit. Use the eye icon to show/hide Ulams from the menu.</p>
            
            <?php if ($products && $products->num_rows > 0): ?>
                <div class="products-grid" id="productsGrid">
                    <?php while($prod = $products->fetch_assoc()): 
                        $is_visible = $prod['is_visible'] ?? 1;
                        $category_name = $categories[$prod['categoryID']] ?? 'Other';
                        $is_ulam = ($prod['categoryID'] == 1);
                        
                        $image_src = '';
                        if (!empty($prod['productImage'])) {
                            $image_src = '../images/products/' . $prod['productImage'];
                            if (!file_exists(__DIR__ . '/../images/products/' . $prod['productImage'])) {
                                $image_src = 'https://via.placeholder.com/200x160/8B0000/ffffff?text=' . urlencode($prod['productname']);
                            }
                        } else {
                            $image_src = 'https://via.placeholder.com/200x160/8B0000/ffffff?text=No+Image';
                        }
                    ?>
                        <div class="product-card <?= $is_visible ? '' : 'hidden' ?>" 
                             data-category="<?= $prod['categoryID'] ?>" 
                             data-visible="<?= $is_visible ?>"
                             onclick="openQuickEdit(<?= htmlspecialchars(json_encode($prod)) ?>)">
                            
                            <?php if ($is_ulam): ?>
                                <form method="POST" style="position:absolute;top:10px;left:10px;z-index:20;" onclick="event.stopPropagation();">
                                    <input type="hidden" name="toggle_visibility" value="1">
                                    <input type="hidden" name="product_id" value="<?= $prod['productID'] ?>">
                                    <input type="hidden" name="is_visible" value="<?= $is_visible ? 0 : 1 ?>">
                                    <button type="submit" class="visibility-toggle <?= $is_visible ? 'active' : 'inactive' ?>">
                                        <i class="fas <?= $is_visible ? 'fa-eye' : 'fa-eye-slash' ?>"></i>
                                        <span><?= $is_visible ? 'Visible' : 'Hidden' ?></span>
                                    </button>
                                </form>
                            <?php endif; ?>
                            
                            <span class="category-badge"><?= $category_name ?></span>
                            <img src="<?= $image_src ?>" alt="<?= htmlspecialchars($prod['productname']) ?>" class="product-image" onerror="this.src='https://via.placeholder.com/200x160/8B0000/ffffff?text=No+Image'">
                            <div class="quick-edit-badge"><i class="fas fa-edit"></i> Edit</div>
                            <div class="product-info">
                                <div class="product-name"><?= htmlspecialchars($prod['productname']) ?></div>
                                <div class="product-price">₱<?= number_format($prod['price'], 2) ?></div>
                                <div class="product-stock">Stock: <?= $prod['stock'] ?> <?= $is_visible ? '<span style="color:#10b981">✓</span>' : '<span style="color:#ef4444">✗</span>' ?></div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <p style="text-align:center;padding:2rem;color:#64748b;">No products yet. Use the camera above to add your first product!</p>
            <?php endif; ?>
        </div>
    </div>
</main>

<!-- Quick Edit Modal -->
<div class="modal-overlay" id="quickEditModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-edit"></i> Quick Edit Product</h3>
            <button class="modal-close" onclick="closeQuickEdit()"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="quick_update" value="1">
                <input type="hidden" name="product_id" id="edit_product_id">
                <div class="form-group">
                    <label>Product Image</label>
                    <input type="file" name="product_image" accept="image/*" capture="environment" style="padding:0.5rem;">
                    <small style="color:#64748b;">Leave empty to keep current image</small>
                </div>
                <div class="form-group"><label>Product Name</label><input type="text" name="product_name" id="edit_product_name" required></div>
                <div class="form-row">
                    <div class="form-group"><label>Price (₱)</label><input type="number" step="0.01" name="price" id="edit_price" required></div>
                    <div class="form-group"><label>Stock</label><input type="number" name="stock" id="edit_stock" required></div>
                </div>
                <button type="submit" class="btn-primary" style="width:100%;margin-top:1rem;"><i class="fas fa-save"></i> Save Changes</button>
            </form>
        </div>
    </div>
</div>

<!-- New Product from Camera Modal -->
<div class="modal-overlay" id="cameraModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-camera"></i> Add New Product</h3>
            <button class="modal-close" onclick="closeCameraModal()"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <img id="cameraPreview" class="preview-image" alt="Captured image">
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="add_new_product" value="1">
                <div class="form-group"><label>Product Name</label><input type="text" name="product_name" required placeholder="e.g., Chicken Adobo"></div>
                <div class="form-group">
                    <label>Category</label>
                    <select name="category_id" required>
                        <?php foreach($categories as $id => $name): ?><option value="<?= $id ?>"><?= $name ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Price (₱)</label><input type="number" step="0.01" name="price" required placeholder="0.00"></div>
                    <div class="form-group"><label>Stock</label><input type="number" name="stock" required placeholder="0"></div>
                </div>
                <div class="form-group"><label>Unit</label><input type="text" name="unit" placeholder="e.g., 1 serving, 1 pc" value="1 serving"></div>
                <button type="submit" class="btn-primary" style="width:100%;margin-top:1rem;"><i class="fas fa-plus"></i> Add Product</button>
            </form>
        </div>
    </div>
</div>

<!-- ============================================
     REAL-TIME NOTIFICATION MODAL
     ============================================ -->
<div class="modal-overlay" id="orderAlertModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-bell"></i> New Order Received!</h3>
            <button class="modal-close" onclick="dismissAlert()"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body" style="text-align:center;">
            <i class="fas fa-receipt" style="font-size:4rem;color:var(--admin-accent);margin-bottom:1rem;"></i>
            <h2 style="margin-bottom:1rem;">Order #<span id="alertOrderNum">-</span></h2>
            <p style="font-size:1.1rem;color:#64748b;margin-bottom:0.5rem;">Customer: <strong id="alertCustomer">-</strong></p>
            <p style="font-size:1.5rem;font-weight:800;color:var(--admin-accent);margin-bottom:1rem;">Total: <span id="alertAmount">₱0.00</span></p>
            <p style="font-size:0.9rem;color:#94a3b8;margin-bottom:2rem;">Received at: <span id="alertTime">-</span></p>
            <div style="display:flex;gap:1rem;justify-content:center;">
                <button class="btn btn-primary" onclick="viewOrderFromAlert()"><i class="fas fa-eye"></i> View Order</button>
                <button class="btn btn-secondary" onclick="dismissAlert()"><i class="fas fa-times"></i> Dismiss</button>
            </div>
        </div>
    </div>
</div>

<script>
let currentOrderId = null;
let lastCheckedOrder = null;
let isTabActive = true;

// 1. Request Browser Notification Permission on load
if ("Notification" in window && Notification.permission === "default") {
    Notification.requestPermission();
}

// Track if the admin is looking at the tab
document.addEventListener("visibilitychange", () => {
    isTabActive = document.visibilityState === "visible";
    if (isTabActive) {
        document.title = "Admin Dashboard - Meryll Eatery"; // Reset title
    }
});

function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('active');
    document.getElementById('sidebarOverlay').classList.toggle('active');
    document.body.style.overflow = document.getElementById('sidebar').classList.contains('active') ? 'hidden' : '';
}

// 2. Play Sound Alert (Works even if tab is in background)
function playAlertSound() {
    try {
        const audioContext = new (window.AudioContext || window.webkitAudioContext)();
        const oscillator = audioContext.createOscillator();
        const gainNode = audioContext.createGain();
        oscillator.connect(gainNode);
        gainNode.connect(audioContext.destination);
        
        oscillator.frequency.value = 880; // A5 note (loud and clear)
        oscillator.type = 'sine';
        gainNode.gain.setValueAtTime(0.5, audioContext.currentTime);
        gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.8);
        
        oscillator.start(audioContext.currentTime);
        oscillator.stop(audioContext.currentTime + 0.8);
        
        // Double beep
        setTimeout(() => {
            const osc2 = audioContext.createOscillator();
            const gain2 = audioContext.createGain();
            osc2.connect(gain2);
            gain2.connect(audioContext.destination);
            osc2.frequency.value = 1100; 
            osc2.type = 'sine';
            gain2.gain.setValueAtTime(0.5, audioContext.currentTime);
            gain2.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.8);
            osc2.start(audioContext.currentTime);
            osc2.stop(audioContext.currentTime + 0.8);
        }, 300);
    } catch (e) {
        console.log("Audio play failed (user must interact with page first)");
    }
}

// 3. Trigger Browser Notification (Works even if tab is minimized)
function sendBrowserNotification(orderNum, amount) {
    if ("Notification" in window && Notification.permission === "granted") {
        new Notification("🔔 New Order Received!", {
            body: `Order #${orderNum} for ₱${amount}`,
            icon: "https://cdn-icons-png.flaticon.com/512/3081/3081840.png", 
            requireInteraction: true // Keeps notification on screen until clicked
        });
    }
}

// 4. Show On-Screen Popup & Blink Tab Title
function showLargeAlert(orderData) {
    currentOrderId = orderData.order_id;
    
    document.getElementById('alertOrderNum').textContent = orderData.order_number;
    document.getElementById('alertCustomer').textContent = orderData.customer_name || 'Online Customer';
    document.getElementById('alertAmount').textContent = '₱' + parseFloat(orderData.total_amount).toFixed(2);
    document.getElementById('alertTime').textContent = new Date().toLocaleString();
    
    const modal = document.getElementById('orderAlertModal');
    modal.classList.add('active'); // Use 'active' to match CSS
    
    playAlertSound();
    sendBrowserNotification(orderData.order_number, parseFloat(orderData.total_amount).toFixed(2));
    
    // Blink tab title if admin is on another tab
    if (!isTabActive) {
        let blinkCount = 0;
        const blinkInterval = setInterval(() => {
            document.title = blinkCount % 2 === 0 ? "🔔 NEW ORDER!" : "Admin Dashboard - Meryll Eatery";
            blinkCount++;
            if (blinkCount > 10) {
                clearInterval(blinkInterval);
                document.title = "Admin Dashboard - Meryll Eatery";
            }
        }, 1000);
    }
    
    // Auto-dismiss after 15 seconds
    setTimeout(() => {
        if (modal.classList.contains('active')) {
            dismissAlert();
        }
    }, 15000);
}

function viewOrderFromAlert() {
    if (currentOrderId) {
        window.location.href = 'get_order_details.php?id=' + currentOrderId;
    }
}

function dismissAlert() {
    document.getElementById('orderAlertModal').classList.remove('active');
    document.title = "Admin Dashboard - Meryll Eatery"; // Reset title
    currentOrderId = null;
}

// 5. Real-Time Polling (Checks every 5 seconds)
function checkNewOrders() {
    fetch('check_new_orders.php')
        .then(r => r.json())
        .then(data => {
            if (data.has_new_order) {
                if (data.order_id !== lastCheckedOrder) {
                    lastCheckedOrder = data.order_id;
                    showLargeAlert(data);
                    
                    // Update notification badge
                    fetch('update_notification_count.php')
                        .then(r => r.json())
                        .then(countData => {
                            const badge = document.querySelector('.notif-badge');
                            if (countData.count > 0 && badge) {
                                badge.textContent = countData.count;
                                badge.style.display = 'flex';
                            }
                        });
                }
            }
        })
        .catch(err => console.log('Polling error:', err));
}

// Start polling
setInterval(checkNewOrders, 5000);
checkNewOrders(); // Run immediately on load

// Camera Upload Logic
let cameraFile = null;
function handleCameraUpload(input) {
    if (input.files && input.files[0]) {
        cameraFile = input.files[0];
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('cameraPreview').src = e.target.result;
            document.getElementById('cameraModal').classList.add('active');
        };
        reader.readAsDataURL(cameraFile);
    }
}
function closeCameraModal() {
    document.getElementById('cameraModal').classList.remove('active');
    cameraFile = null;
}

// Quick Edit Logic
function openQuickEdit(product) {
    document.getElementById('edit_product_id').value = product.productID;
    document.getElementById('edit_product_name').value = product.productname;
    document.getElementById('edit_price').value = product.price;
    document.getElementById('edit_stock').value = product.stock;
    document.getElementById('quickEditModal').classList.add('active');
}
function closeQuickEdit() { document.getElementById('quickEditModal').classList.remove('active'); }

// Filter Logic
function filterProducts(filter) {
    document.querySelectorAll('.filter-tab').forEach(tab => tab.classList.remove('active'));
    event.target.classList.add('active');
    const cards = document.querySelectorAll('.product-card');
    cards.forEach(card => {
        const category = parseInt(card.dataset.category);
        const visible = parseInt(card.dataset.visible);
        if (filter === 'all') card.style.display = 'block';
        else if (filter === 'visible') card.style.display = visible === 1 ? 'block' : 'none';
        else if (filter === 'hidden') card.style.display = visible === 0 ? 'block' : 'none';
        else if (filter === 'ulam') card.style.display = category === 1 ? 'block' : 'none';
    });
}

// Close modals when clicking outside
document.getElementById('quickEditModal').addEventListener('click', function(e) { if (e.target === this) closeQuickEdit(); });
document.getElementById('cameraModal').addEventListener('click', function(e) { if (e.target === this) closeCameraModal(); });
document.getElementById('orderAlertModal').addEventListener('click', function(e) { if (e.target === this) dismissAlert(); });
</script>

</body>
</html>