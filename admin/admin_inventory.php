<?php
session_start(); 
include '../connect.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') { header("Location: ../user/login.php"); exit; }

$categories = [
    1 => 'Ulam', 2 => 'Meryenda', 3 => 'Breakfast', 4 => 'Coffee', 
    5 => 'Drinks', 6 => 'Snacks', 7 => 'Candies', 8 => 'Noodles', 
    9 => 'Essentials', 10 => 'Accessories'
];

$message = ""; $msg_type = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action_type'] ?? '';
    if ($action === 'add' || $action === 'update') {
        $name = $con->real_escape_string(trim($_POST['productname']));
        $cat = (int)$_POST['categoryID']; 
        $unit = $con->real_escape_string(trim($_POST['unit']));
        $price = (float)$_POST['price']; 
        $stock = (int)$_POST['stock'];
        $image = $con->real_escape_string(trim($_POST['productImage']));

        if ($action === 'add') {
            $stmt = $con->prepare("INSERT INTO products (productname, categoryID, unit, price, stock, productImage) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sisdis", $name, $cat, $unit, $price, $stock, $image);
            if ($stmt->execute()) { $message = "Product added successfully!"; $msg_type = "success"; } 
            else { $message = "Error adding product."; $msg_type = "error"; }
        } else {
            $id = (int)$_POST['productID'];
            $stmt = $con->prepare("UPDATE products SET productname=?, categoryID=?, unit=?, price=?, stock=?, productImage=? WHERE productID=?");
            $stmt->bind_param("sisdisi", $name, $cat, $unit, $price, $stock, $image, $id);
            if ($stmt->execute()) { $message = "Product updated successfully!"; $msg_type = "success"; } 
            else { $message = "Error updating product."; $msg_type = "error"; }
        }
    } elseif ($action === 'delete') {
        $id = (int)$_POST['productID'];
        $con->query("DELETE FROM order_items WHERE productID = $id"); 
        $stmt = $con->prepare("DELETE FROM products WHERE productID = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) { $message = "Product deleted successfully!"; $msg_type = "success"; } 
        else { $message = "Error deleting product."; $msg_type = "error"; }
    }
}

$search = isset($_GET['search']) ? $con->real_escape_string(trim($_GET['search'])) : '';
$sql = $search !== '' ? "SELECT * FROM products WHERE productname LIKE '%$search%' ORDER BY productID DESC" : "SELECT * FROM products ORDER BY productID DESC";
$products = $con->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Inventory | Meryll Eatery Admin</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="admin_style.css">
</head>
<body>
<button class="mobile-toggle" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
<div class="sidebar-backdrop" onclick="toggleSidebar()"></div>

<aside class="sidebar d-flex flex-column" id="sidebar">
    <a href="admin.php" class="logo"><span class="logo-icon"><i class="fas fa-utensils"></i></span> Meryll Eatery</a>
    <nav class="nav flex-column flex-grow-1">
        <a href="admin.php" class="nav-link"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
        <a href="admin_orders.php" class="nav-link"><i class="fas fa-clipboard-list"></i> Manage Orders</a>
        <a href="get_order_details.php" class="nav-link"><i class="fas fa-receipt"></i> Order Details</a>
        <a href="admin_inventory.php" class="nav-link active"><i class="fas fa-boxes-stacked"></i> Inventory</a>
        <a href="manage_users.php" class="nav-link"><i class="fas fa-users"></i> Manage Users</a>
        <a href="admin_report.php" class="nav-link"><i class="fas fa-chart-line"></i> Monthly Report</a>
    </nav>
    <div class="sidebar-footer">
        <a href="admin.php" class="nav-link"><i class="fas fa-user-shield"></i> <?= htmlspecialchars($_SESSION['username']) ?></a>
        <a href="../user/logout.php" class="nav-link text-danger"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
</aside>

<main class="main-content">
    <div class="container-fluid p-0">
        <?php if ($message): ?>
            <div class="alert alert-<?= $msg_type ?> alert-dismissible fade show">
                <i class="fas fa-<?= $msg_type === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i> <?= $message ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <h1 class="mb-1">Inventory Management</h1>
        <p class="text-muted mb-4">Add, update, and track your canteen stock levels.</p>

        <div class="d-flex flex-wrap gap-3 align-items-center mb-4">
            <form method="GET" class="flex-grow-1" style="max-width: 350px;">
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                    <input type="text" name="search" class="form-control" placeholder="Search products..." value="<?= htmlspecialchars($search) ?>">
                </div>
            </form>
            <button class="btn btn-primary px-4" onclick="openModal('add')"><i class="fas fa-plus me-2"></i>Add Product</button>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <!-- ✅ NATIVE BOOTSTRAP SCROLLING -->
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>ID</th><th>Product Name</th><th>Category</th><th>Unit</th><th>Price</th><th>Stock</th><th>Status</th><th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($products && $products->num_rows > 0): ?>
                                <?php while($row = $products->fetch_assoc()): 
                                    $stock = $row['stock'];
                                    if ($stock <= 10) { $badge = '<span class="badge bg-danger">Low</span>'; }
                                    elseif ($stock <= 50) { $badge = '<span class="badge bg-warning text-dark">Medium</span>'; }
                                    else { $badge = '<span class="badge bg-success">High</span>'; }
                                ?>
                                    <tr>
                                        <td class="fw-bold">#<?= $row['productID'] ?></td>
                                        <td class="fw-semibold"><?= htmlspecialchars($row['productname']) ?></td>
                                        <td><?= $categories[$row['categoryID']] ?? 'Other' ?></td>
                                        <td class="text-muted small"><?= htmlspecialchars($row['unit']) ?></td>
                                        <td class="fw-bold">₱<?= number_format($row['price'], 2) ?></td>
                                        <td class="fw-bold"><?= $stock ?></td>
                                        <td><?= $badge ?></td>
                                        <td class="text-center">
                                            <button class="btn btn-sm btn-outline-primary me-1" onclick='openModal("edit", <?= json_encode($row) ?>)' title="Edit"><i class="fas fa-pen-to-square"></i></button>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Delete this product?')">
                                                <input type="hidden" name="action_type" value="delete">
                                                <input type="hidden" name="productID" value="<?= $row['productID'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete"><i class="fas fa-trash"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="8" class="text-center py-4 text-muted">No products found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Add/Edit Modal -->
<div class="modal fade" id="productModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-accent text-white">
                <h5 class="modal-title" id="modalTitle">Add New Product</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="POST">
                    <input type="hidden" name="action_type" id="actionType" value="add">
                    <input type="hidden" name="productID" id="editProductID" value="">
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-uppercase">Product Name</label>
                        <input type="text" name="productname" id="inputName" class="form-control" required>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-bold small text-uppercase">Category</label>
                            <select name="categoryID" id="inputCategory" class="form-select" required>
                                <?php foreach($categories as $id => $name): ?>
                                    <option value="<?= $id ?>"><?= $name ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-bold small text-uppercase">Unit</label>
                            <input type="text" name="unit" id="inputUnit" class="form-control" placeholder="e.g., 1 pc" required>
                        </div>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-bold small text-uppercase">Price (₱)</label>
                            <input type="number" step="0.01" name="price" id="inputPrice" class="form-control" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-bold small text-uppercase">Stock</label>
                            <input type="number" name="stock" id="inputStock" class="form-control" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-uppercase">Image Filename</label>
                        <input type="text" name="productImage" id="inputImage" class="form-control" placeholder="Leave blank if none">
                    </div>
                    <button type="submit" class="btn btn-primary w-100 py-2" id="submitBtn">Add Product</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('show');
    document.querySelector('.sidebar-backdrop').classList.toggle('show');
}

const productModal = new bootstrap.Modal(document.getElementById('productModal'));

function openModal(action, data = null) {
    if (action === 'add') {
        document.getElementById('modalTitle').textContent = 'Add New Product';
        document.getElementById('submitBtn').textContent = 'Add Product';
        document.getElementById('actionType').value = 'add';
        document.getElementById('editProductID').value = '';
        document.getElementById('inputName').value = '';
        document.getElementById('inputCategory').value = '1';
        document.getElementById('inputUnit').value = '';
        document.getElementById('inputPrice').value = '';
        document.getElementById('inputStock').value = '';
        document.getElementById('inputImage').value = '';
    } else if (action === 'edit' && data) {
        document.getElementById('modalTitle').textContent = 'Edit Product';
        document.getElementById('submitBtn').textContent = 'Save Changes';
        document.getElementById('actionType').value = 'update';
        document.getElementById('editProductID').value = data.productID;
        document.getElementById('inputName').value = data.productname;
        document.getElementById('inputCategory').value = data.categoryID;
        document.getElementById('inputUnit').value = data.unit;
        document.getElementById('inputPrice').value = data.price;
        document.getElementById('inputStock').value = data.stock;
        document.getElementById('inputImage').value = data.productImage || '';
    }
    productModal.show();
}
</script>
</body>
</html>