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
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="admin_style.css">
<style>
/* Dashboard specific overrides */
.stat-card { transition: transform 0.2s; }
.stat-card:hover { transform: translateY(-4px); }
.product-card { cursor: pointer; transition: all 0.3s; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
.product-card:hover { transform: translateY(-4px); box-shadow: 0 8px 20px rgba(0,0,0,0.12); }
.product-image { width: 100%; height: 160px; object-fit: cover; background: #f1f5f9; }
.quick-edit-badge { position: absolute; top: 10px; right: 10px; opacity: 0; transition: opacity 0.3s; z-index: 10; }
.product-card:hover .quick-edit-badge { opacity: 1; }
.category-badge { position: absolute; top: 10px; left: 10px; z-index: 10; }
.visibility-toggle { position: absolute; bottom: 10px; left: 10px; z-index: 10; font-size: 0.7rem; padding: 4px 8px; }
.preview-image { max-width: 100%; max-height: 200px; border-radius: 8px; margin-bottom: 1rem; }
.filter-btn.active { background-color: var(--admin-accent); color: #fff; border-color: var(--admin-accent); }
</style>
</head>
<body>

<button class="mobile-toggle" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
<div class="sidebar-backdrop" onclick="toggleSidebar()"></div>

<aside class="sidebar d-flex flex-column" id="sidebar">
    <a href="admin.php" class="logo"><span class="logo-icon"><i class="fas fa-utensils"></i></span> Meryll Eatery</a>
    <nav class="nav flex-column flex-grow-1">
        <a href="admin.php" class="nav-link active"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
        <a href="admin_orders.php" class="nav-link"><i class="fas fa-clipboard-list"></i> Manage Orders</a>
        <a href="get_order_details.php" class="nav-link"><i class="fas fa-receipt"></i> Order Details</a>
        <a href="admin_inventory.php" class="nav-link"><i class="fas fa-boxes-stacked"></i> Inventory</a>
        <a href="manage_users.php" class="nav-link"><i class="fas fa-users"></i> Manage Users</a>
        <a href="admin_report.php" class="nav-link"><i class="fas fa-chart-line"></i> Monthly Report</a>
    </nav>
    <div class="sidebar-footer">
        <a href="admin.php" class="nav-link"><i class="fas fa-user-shield"></i> <?= htmlspecialchars($admin_name) ?></a>
        <a href="../user/logout.php" class="nav-link text-danger"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
</aside>

<main class="main-content">
    <div class="container-fluid p-0">
        <?php if (isset($success_msg)): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle me-2"></i><?= $success_msg ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        <?php if (isset($toggle_msg)): ?>
            <div class="alert alert-info alert-dismissible fade show">
                <i class="fas fa-info-circle me-2"></i><?= $toggle_msg ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <h1 class="mb-1">Product Manager</h1>
        <p class="text-muted mb-4">Manage all products - Click to edit, toggle visibility for Ulams</p>

        <!-- Camera Upload Section -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body text-center py-4">
                <h5 class="card-title mb-3"><i class="fas fa-camera text-accent me-2"></i>Quick Add Product with Camera</h5>
                <p class="text-muted small mb-3">Tap to take a photo or upload from gallery</p>
                <button class="btn btn-primary px-4" onclick="document.getElementById('cameraInput').click()">
                    <i class="fas fa-plus me-2"></i>Add Product
                </button>
                <input type="file" id="cameraInput" accept="image/*" capture="environment" onchange="handleCameraUpload(this)" style="display:none;">
            </div>
        </div>

        <!-- Filter Buttons -->
        <div class="d-flex flex-wrap gap-2 mb-4">
            <button class="btn btn-outline-secondary filter-btn active" onclick="filterProducts('all', this)">All Products</button>
            <button class="btn btn-outline-secondary filter-btn" onclick="filterProducts('visible', this)">Visible</button>
            <button class="btn btn-outline-secondary filter-btn" onclick="filterProducts('hidden', this)">Hidden</button>
            <button class="btn btn-outline-secondary filter-btn" onclick="filterProducts('ulam', this)">Ulam Only</button>
        </div>

        <!-- Stats Grid -->
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="card border-0 shadow-sm stat-card h-100">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="bg-danger bg-opacity-10 text-danger rounded-circle p-3 d-flex align-items-center justify-content-center" style="width:50px;height:50px;">
                            <i class="fas fa-receipt fa-lg"></i>
                        </div>
                        <div>
                            <h6 class="text-muted mb-0 small fw-bold text-uppercase">Pending Orders</h6>
                            <h3 class="mb-0 fw-bold"><?= $pending_count ?></h3>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm stat-card h-100">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="bg-success bg-opacity-10 text-success rounded-circle p-3 d-flex align-items-center justify-content-center" style="width:50px;height:50px;">
                            <i class="fas fa-check-circle fa-lg"></i>
                        </div>
                        <div>
                            <h6 class="text-muted mb-0 small fw-bold text-uppercase">Completed Today</h6>
                            <h3 class="mb-0 fw-bold"><?= $completed_count ?></h3>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm stat-card h-100">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="bg-primary bg-opacity-10 text-primary rounded-circle p-3 d-flex align-items-center justify-content-center" style="width:50px;height:50px;">
                            <i class="fas fa-cash-register fa-lg"></i>
                        </div>
                        <div>
                            <h6 class="text-muted mb-0 small fw-bold text-uppercase">Total Revenue</h6>
                            <h3 class="mb-0 fw-bold">₱<?= number_format($total_revenue, 2) ?></h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Products Grid -->
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h5 class="card-title mb-1"><i class="fas fa-th-large text-accent me-2"></i>All Products (<?= $products->num_rows ?>)</h5>
                <p class="text-muted small mb-4">Click any product to edit. Use the eye icon to show/hide Ulams from the menu.</p>
                
                <?php if ($products && $products->num_rows > 0): ?>
                    <div class="row g-3" id="productsGrid">
                        <?php while($prod = $products->fetch_assoc()): 
                            $is_visible = $prod['is_visible'] ?? 1;
                            $category_name = $categories[$prod['categoryID']] ?? 'Other';
                            $is_ulam = ($prod['categoryID'] == 1);
                            
                            $image_src = '';
                            if (!empty($prod['productImage'])) {
                                $image_src = '../images/products/' . $prod['productImage'];
                                if (!file_exists(__DIR__ . '/../images/products/' . $prod['productImage'])) {
                                    $image_src = 'https://via.placeholder.com/300x200/8B0000/ffffff?text=' . urlencode($prod['productname']);
                                }
                            } else {
                                $image_src = 'https://via.placeholder.com/300x200/8B0000/ffffff?text=No+Image';
                            }
                        ?>
                            <div class="col-6 col-md-4 col-lg-3 product-item" 
                                 data-category="<?= $prod['categoryID'] ?>" 
                                 data-visible="<?= $is_visible ?>">
                                <div class="product-card position-relative h-100" onclick="openQuickEdit(<?= htmlspecialchars(json_encode($prod)) ?>)">
                                    <span class="badge bg-accent category-badge"><?= $category_name ?></span>
                                    
                                    <?php if ($is_ulam): ?>
                                        <form method="POST" onclick="event.stopPropagation();" class="position-absolute bottom-0 start-0 m-2 z-3">
                                            <input type="hidden" name="toggle_visibility" value="1">
                                            <input type="hidden" name="product_id" value="<?= $prod['productID'] ?>">
                                            <input type="hidden" name="is_visible" value="<?= $is_visible ? 0 : 1 ?>">
                                            <button type="submit" class="btn btn-sm <?= $is_visible ? 'btn-success' : 'btn-danger' ?> visibility-toggle">
                                                <i class="fas <?= $is_visible ? 'fa-eye' : 'fa-eye-slash' ?>"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    
                                    <img src="<?= $image_src ?>" alt="<?= htmlspecialchars($prod['productname']) ?>" class="product-image">
                                    
                                    <span class="badge bg-primary quick-edit-badge"><i class="fas fa-edit me-1"></i>Edit</span>
                                    
                                    <div class="p-3 bg-white">
                                        <h6 class="fw-bold mb-1 text-truncate"><?= htmlspecialchars($prod['productname']) ?></h6>
                                        <p class="text-accent fw-bold mb-1">₱<?= number_format($prod['price'], 2) ?></p>
                                        <small class="text-muted">Stock: <?= $prod['stock'] ?> 
                                            <?= $is_visible ? '<i class="fas fa-check text-success ms-1"></i>' : '<i class="fas fa-times text-danger ms-1"></i>' ?>
                                        </small>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <p class="text-muted text-center py-4">No products yet. Use the camera above to add your first product!</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<!-- Quick Edit Modal -->
<div class="modal fade" id="quickEditModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-accent text-white">
                <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Quick Edit Product</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="quick_update" value="1">
                    <input type="hidden" name="product_id" id="edit_product_id">
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-uppercase">Product Image</label>
                        <input type="file" name="product_image" accept="image/*" capture="environment" class="form-control">
                        <small class="text-muted">Leave empty to keep current image</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-uppercase">Product Name</label>
                        <input type="text" name="product_name" id="edit_product_name" class="form-control" required>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-bold small text-uppercase">Price (₱)</label>
                            <input type="number" step="0.01" name="price" id="edit_price" class="form-control" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-bold small text-uppercase">Stock</label>
                            <input type="number" name="stock" id="edit_stock" class="form-control" required>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 py-2">
                        <i class="fas fa-save me-2"></i>Save Changes
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- New Product from Camera Modal -->
<div class="modal fade" id="cameraModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-accent text-white">
                <h5 class="modal-title"><i class="fas fa-camera me-2"></i>Add New Product</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <img id="cameraPreview" class="preview-image d-block mx-auto" alt="Captured image">
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="add_new_product" value="1">
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-uppercase">Product Name</label>
                        <input type="text" name="product_name" class="form-control" required placeholder="e.g., Chicken Adobo">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-uppercase">Category</label>
                        <select name="category_id" class="form-select" required>
                            <?php foreach($categories as $id => $name): ?>
                                <option value="<?= $id ?>"><?= $name ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-bold small text-uppercase">Price (₱)</label>
                            <input type="number" step="0.01" name="price" class="form-control" required placeholder="0.00">
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-bold small text-uppercase">Stock</label>
                            <input type="number" name="stock" class="form-control" required placeholder="0">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-uppercase">Unit</label>
                        <input type="text" name="unit" class="form-control" placeholder="e.g., 1 serving" value="1 serving">
                    </div>
                    <button type="submit" class="btn btn-primary w-100 py-2">
                        <i class="fas fa-plus me-2"></i>Add Product
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
let cameraFile = null;
const quickEditModal = new bootstrap.Modal(document.getElementById('quickEditModal'));
const cameraModal = new bootstrap.Modal(document.getElementById('cameraModal'));

function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('show');
    document.querySelector('.sidebar-backdrop').classList.toggle('show');
}

function handleCameraUpload(input) {
    if (input.files && input.files[0]) {
        cameraFile = input.files[0];
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('cameraPreview').src = e.target.result;
            cameraModal.show();
        };
        reader.readAsDataURL(cameraFile);
    }
}

function openQuickEdit(product) {
    document.getElementById('edit_product_id').value = product.productID;
    document.getElementById('edit_product_name').value = product.productname;
    document.getElementById('edit_price').value = product.price;
    document.getElementById('edit_stock').value = product.stock;
    quickEditModal.show();
}

function filterProducts(filter, btn) {
    // Update active button
    document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    
    const items = document.querySelectorAll('.product-item');
    items.forEach(item => {
        const category = parseInt(item.dataset.category);
        const visible = parseInt(item.dataset.visible);
        
        if (filter === 'all') item.style.display = '';
        else if (filter === 'visible') item.style.display = visible === 1 ? '' : 'none';
        else if (filter === 'hidden') item.style.display = visible === 0 ? '' : 'none';
        else if (filter === 'ulam') item.style.display = category === 1 ? '' : 'none';
    });
}
</script>
</body>
</html>