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

// Fetch ALL products for dashboard grid (including hidden ones)
$products = $con->query("SELECT * FROM products ORDER BY FIELD(categoryID, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10), productID DESC");

// Handle Quick Product Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['quick_update'])) {
    $product_id = (int)$_POST['product_id'];
    $product_name = $con->real_escape_string(trim($_POST['product_name']));
    $price = (float)$_POST['price'];
    $stock = (int)$_POST['stock'];
    
    // Handle image upload
    $image_path = '';
    if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === 0) {
        $upload_dir = '../images/products/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_ext = strtolower(pathinfo($_FILES['product_image']['name'], PATHINFO_EXTENSION));
        $new_filename = uniqid() . '.' . $file_ext;
        $upload_path = $upload_dir . $new_filename;
        
        if (move_uploaded_file($_FILES['product_image']['tmp_name'], $upload_path)) {
            $image_path = $new_filename;
        }
    }
    
    if ($image_path) {
        $stmt = $con->prepare("UPDATE products SET productname=?, price=?, stock=?, productImage=? WHERE productID=?");
        $stmt->bind_param("sdisi", $product_name, $price, $stock, $image_path, $product_id);
    } else {
        $stmt = $con->prepare("UPDATE products SET productname=?, price=?, stock=? WHERE productID=?");
        $stmt->bind_param("sdii", $product_name, $price, $stock, $product_id);
    }
    
    if ($stmt->execute()) {
        $success_msg = "Product updated successfully!";
    }
}

// Handle Toggle Visibility
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_visibility'])) {
    $product_id = (int)$_POST['product_id'];
    $new_visibility = (int)$_POST['is_visible'];
    
    $stmt = $con->prepare("UPDATE products SET is_visible=? WHERE productID=?");
    $stmt->bind_param("ii", $new_visibility, $product_id);
    
    if ($stmt->execute()) {
        $toggle_msg = "Product visibility updated!";
    }
}

// Category names
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
<style>
/* Additional styles for visibility toggle */
.visibility-toggle {
    position: absolute;
    top: 10px;
    left: 10px;
    background: rgba(0,0,0,0.7);
    color: #fff;
    border: none;
    padding: 5px 10px;
    border-radius: 20px;
    font-size: 0.75rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 5px;
    z-index: 10;
    transition: all 0.3s;
}

.visibility-toggle:hover {
    background: rgba(0,0,0,0.9);
}

.visibility-toggle.active {
    background: #10b981;
}

.visibility-toggle.inactive {
    background: #ef4444;
}

.product-card.hidden {
    opacity: 0.6;
    filter: grayscale(0.8);
}

.category-badge {
    position: absolute;
    top: 10px;
    right: 60px;
    background: var(--admin-accent);
    color: #fff;
    padding: 3px 8px;
    border-radius: 12px;
    font-size: 0.65rem;
    font-weight: 700;
}

.filter-tabs {
    display: flex;
    gap: 0.5rem;
    margin-bottom: 1.5rem;
    flex-wrap: wrap;
}

.filter-tab {
    padding: 0.5rem 1rem;
    border: 2px solid var(--admin-border);
    background: #fff;
    border-radius: 20px;
    cursor: pointer;
    font-weight: 600;
    font-size: 0.85rem;
    transition: all 0.2s;
}

.filter-tab.active {
    background: var(--admin-accent);
    color: #fff;
    border-color: var(--admin-accent);
}

.filter-tab:hover {
    border-color: var(--admin-accent);
}
</style>
</head>
<body>

<!-- Mobile Menu Toggle -->
<button class="mobile-menu-toggle" onclick="toggleSidebar()">
    <i class="fas fa-bars"></i>
</button>

<!-- ✅ IDENTICAL SIDEBAR START -->
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
<!-- ✅ IDENTICAL SIDEBAR END -->

<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

<main class="main-content">
    <div class="admin-container">
        <div class="admin-header">
            <div>
                <h1>Product Manager</h1>
                <p style="color: var(--admin-text-muted, #64748b);">Manage all products - Click to edit, toggle visibility for Ulams</p>
            </div>
        </div>

        <?php if (isset($success_msg)): ?>
            <div class="alert-msg success"><i class="fas fa-check-circle"></i> <?php echo $success_msg; ?></div>
        <?php endif; ?>
        
        <?php if (isset($toggle_msg)): ?>
            <div class="alert-msg info"><i class="fas fa-info-circle"></i> <?php echo $toggle_msg; ?></div>
        <?php endif; ?>

        <!-- Filter Tabs -->
        <div class="filter-tabs">
            <button class="filter-tab active" onclick="filterProducts('all')">All Products</button>
            <button class="filter-tab" onclick="filterProducts('visible')">Visible</button>
            <button class="filter-tab" onclick="filterProducts('hidden')">Hidden</button>
            <button class="filter-tab" onclick="filterProducts('ulam')">Ulam Only</button>
        </div>

        <!-- Products Grid - ALL PRODUCTS -->
        <div class="admin-card">
            <h3><i class="fas fa-th-large" style="color:#8B0000;"></i> All Products (<?php echo $products->num_rows; ?>)</h3>
            <p style="color:#64748b;margin-bottom:1rem;">Click any product to edit. Use the eye icon to show/hide Ulams from the menu.</p>
            
            <?php if ($products && $products->num_rows > 0): ?>
                <div class="products-grid" id="productsGrid">
                    <?php while($prod = $products->fetch_assoc()): 
                        $is_visible = $prod['is_visible'] ?? 1;
                        $category_name = $categories[$prod['categoryID']] ?? 'Other';
                        $is_ulam = ($prod['categoryID'] == 1);
                    ?>
                        <div class="product-card <?= $is_visible ? '' : 'hidden' ?>" 
                             data-category="<?= $prod['categoryID'] ?>" 
                             data-visible="<?= $is_visible ?>"
                             onclick="openQuickEdit(<?= htmlspecialchars(json_encode($prod)) ?>)">
                            
                            <!-- Visibility Toggle (Only for Ulams) -->
                            <?php if ($is_ulam): ?>
                                <form method="POST" style="position:absolute;top:10px;left:10px;z-index:20;" onclick="event.stopPropagation();">
                                    <input type="hidden" name="toggle_visibility" value="1">
                                    <input type="hidden" name="product_id" value="<?= $prod['productID'] ?>">
                                    <input type="hidden" name="is_visible" value="<?= $is_visible ? 0 : 1 ?>">
                                    <button type="submit" class="visibility-toggle <?= $is_visible ? 'active' : 'inactive' ?>" title="<?= $is_visible ? 'Click to hide' : 'Click to show' ?>">
                                        <i class="fas <?= $is_visible ? 'fa-eye' : 'fa-eye-slash' ?>"></i>
                                        <span><?= $is_visible ? 'Visible' : 'Hidden' ?></span>
                                    </button>
                                </form>
                            <?php endif; ?>
                            
                            <!-- Category Badge -->
                            <span class="category-badge"><?= $category_name ?></span>
                            
                            <img src="../images/products/<?= htmlspecialchars($prod['productImage'] ?: 'placeholder.jpg') ?>" 
                                 alt="<?= htmlspecialchars($prod['productname']) ?>" 
                                 class="product-image"
                                 onerror="this.src='https://via.placeholder.com/200x160/8B0000/ffffff?text=No+Image'">
                            
                            <div class="quick-edit-badge"><i class="fas fa-edit"></i> Edit</div>
                            <div class="product-info">
                                <div class="product-name"><?= htmlspecialchars($prod['productname']) ?></div>
                                <div class="product-price"><?= number_format($prod['price'], 2) ?></div>
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
                
                <div class="form-group">
                    <label>Product Name</label>
                    <input type="text" name="product_name" id="edit_product_name" required>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Price (₱)</label>
                        <input type="number" step="0.01" name="price" id="edit_price" required>
                    </div>
                    <div class="form-group">
                        <label>Stock</label>
                        <input type="number" name="stock" id="edit_stock" required>
                    </div>
                </div>
                
                <button type="submit" class="btn-primary" style="width:100%;margin-top:1rem;">
                    <i class="fas fa-save"></i> Save Changes
                </button>
            </form>
        </div>
    </div>
</div>

<script>
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('active');
    document.getElementById('sidebarOverlay').classList.toggle('active');
    document.body.style.overflow = document.getElementById('sidebar').classList.contains('active') ? 'hidden' : '';
}

function openQuickEdit(product) {
    document.getElementById('edit_product_id').value = product.productID;
    document.getElementById('edit_product_name').value = product.productname;
    document.getElementById('edit_price').value = product.price;
    document.getElementById('edit_stock').value = product.stock;
    document.getElementById('quickEditModal').classList.add('active');
}

function closeQuickEdit() {
    document.getElementById('quickEditModal').classList.remove('active');
}

// Filter products
function filterProducts(filter) {
    // Update active tab
    document.querySelectorAll('.filter-tab').forEach(tab => tab.classList.remove('active'));
    event.target.classList.add('active');
    
    const cards = document.querySelectorAll('.product-card');
    
    cards.forEach(card => {
        const category = parseInt(card.dataset.category);
        const visible = parseInt(card.dataset.visible);
        
        if (filter === 'all') {
            card.style.display = 'block';
        } else if (filter === 'visible') {
            card.style.display = visible === 1 ? 'block' : 'none';
        } else if (filter === 'hidden') {
            card.style.display = visible === 0 ? 'block' : 'none';
        } else if (filter === 'ulam') {
            card.style.display = category === 1 ? 'block' : 'none';
        }
    });
}

// Close modal when clicking outside
document.getElementById('quickEditModal').addEventListener('click', function(e) {
    if (e.target === this) closeQuickEdit();
});
</script>

</body>
</html>