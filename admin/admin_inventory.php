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
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="admin_style.css">
</head>
<body>
    <button class="mobile-menu-toggle" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

    <aside class="sidebar" id="sidebar">
        <button class="sidebar-close" onclick="toggleSidebar()"><i class="fas fa-arrow-left"></i></button>
        <a href="admin.php" class="logo"><span class="logo-icon"><i class="fas fa-utensils"></i></span> Meryll Eatery</a>
        <ul class="nav-menu">
            <li class="nav-item"><a href="admin.php" class="nav-link"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li class="nav-item"><a href="admin_orders.php" class="nav-link"><i class="fas fa-clipboard-list"></i> Manage Orders</a></li>
            <li class="nav-item"><a href="get_order_details.php" class="nav-link"><i class="fas fa-receipt"></i> Order Details</a></li>
            <li class="nav-item"><a href="admin_inventory.php" class="nav-link active"><i class="fas fa-boxes-stacked"></i> Inventory</a></li>
            <li class="nav-item"><a href="manage_users.php" class="nav-link"><i class="fas fa-users"></i> Manage Users</a></li>
            <li class="nav-item"><a href="admin_report.php" class="nav-link"><i class="fas fa-chart-line"></i> Monthly Report</a></li>
        </ul>
        <div class="sidebar-footer">
            <a href="admin.php" class="nav-link"><i class="fas fa-user-shield"></i> <?php echo htmlspecialchars($_SESSION['username']); ?></a>
            <a href="../user/logout.php" class="nav-link" style="color: #ef4444;"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </aside>

    <main class="main-content">
        <div class="admin-container" style="max-width: 1200px; margin: 0 auto;">
            <?php if ($message): ?><div class="alert-msg <?php echo $msg_type; ?>"><i class="fas fa-<?php echo $msg_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i> <?php echo $message; ?></div><?php endif; ?>
            
            <div class="inventory-header">
                <div><h1 style="font-size: 1.8rem; font-weight: 800; margin-bottom: 0.3rem;">Inventory Management</h1><p style="color: var(--admin-text-muted, #64748b);">Add, update, and track your canteen stock levels.</p></div>
                <div style="display: flex; gap: 1rem; align-items: center;">
                    <form method="GET" class="search-container" style="width: 250px;">
                        <i class="fas fa-search"></i><input type="text" name="search" placeholder="Search products..." value="<?php echo htmlspecialchars($search); ?>">
                    </form>
                    <button class="btn-add" onclick="openModal('add')"><i class="fas fa-plus"></i> Add Product</button>
                </div>
            </div>

            <div class="admin-card" style="padding: 0;">
                <!-- ✅ USE table-scroll-wrapper HERE -->
                <div class="table-scroll-wrapper">
                    <table class="data-table" style="width: 100%; border-collapse: collapse;">
                        <thead><tr><th>ID</th><th>Product Name</th><th>Category</th><th>Unit</th><th>Price</th><th>Stock</th><th>Status</th><th style="text-align: center;">Actions</th></tr></thead>
                        <tbody>
                            <?php if ($products && $products->num_rows > 0): ?>
                                <?php while($row = $products->fetch_assoc()): 
                                    $stock = $row['stock'];
                                    if ($stock <= 10) { $badge = '<span class="badge-stock badge-low">Low</span>'; }
                                    elseif ($stock <= 50) { $badge = '<span class="badge-stock badge-med">Medium</span>'; }
                                    else { $badge = '<span class="badge-stock badge-high">High</span>'; }
                                ?>
                                    <tr>
                                        <td style="font-weight: 700;">#<?php echo $row['productID']; ?></td>
                                        <td style="font-weight: 600;"><?php echo htmlspecialchars($row['productname']); ?></td>
                                        <td><?php echo $categories[$row['categoryID']] ?? 'Other'; ?></td>
                                        <td style="color: var(--admin-text-muted, #64748b); font-size: 0.85rem;"><?php echo htmlspecialchars($row['unit']); ?></td>
                                        <td style="font-weight: 700;">₱<?php echo number_format($row['price'], 2); ?></td>
                                        <td style="font-weight: 700;"><?php echo $stock; ?></td>
                                        <td><?php echo $badge; ?></td>
                                        <td style="text-align: center;">
                                            <button class="action-btn btn-edit" onclick='openModal("edit", <?php echo json_encode($row); ?>)' title="Edit"><i class="fas fa-pen-to-square"></i></button>
                                            <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this product?');">
                                                <input type="hidden" name="action_type" value="delete"><input type="hidden" name="productID" value="<?php echo $row['productID']; ?>">
                                                <button type="submit" class="action-btn btn-delete" title="Delete"><i class="fas fa-trash"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="8" style="text-align: center; padding: 3rem; color: var(--admin-text-muted, #64748b);">No products found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <div class="modal-overlay" id="productModal">
        <div class="modal-content">
            <div class="modal-header"><h3 id="modalTitle">Add New Product</h3><button class="modal-close" onclick="closeModal()"><i class="fas fa-times"></i></button></div>
            <div class="modal-body">
                <form method="POST">
                    <input type="hidden" name="action_type" id="actionType" value="add"><input type="hidden" name="productID" id="editProductID" value="">
                    <div class="form-group"><label>Product Name</label><input type="text" name="productname" id="inputName" required></div>
                    <div class="form-row">
                        <div class="form-group"><label>Category</label><select name="categoryID" id="inputCategory" required>
                            <?php foreach($categories as $id => $name): ?><option value="<?php echo $id; ?>"><?php echo $name; ?></option><?php endforeach; ?>
                        </select></div>
                        <div class="form-group"><label>Unit</label><input type="text" name="unit" id="inputUnit" placeholder="e.g., 1 pc, 1 serving" required></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label>Price (₱)</label><input type="number" step="0.01" name="price" id="inputPrice" required></div>
                        <div class="form-group"><label>Stock Quantity</label><input type="number" name="stock" id="inputStock" required></div>
                    </div>
                    <div class="form-group"><label>Image Filename (e.g., adobo.jpg)</label><input type="text" name="productImage" id="inputImage" placeholder="Leave blank if none"></div>
                    <button type="submit" class="btn-primary" id="submitBtn" style="width:100%; margin-top:1rem;">Add Product</button>
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
    function openModal(action, data = null) {
        const modal = document.getElementById('productModal'); modal.classList.add('active');
        if (action === 'add') {
            document.getElementById('modalTitle').textContent = 'Add New Product'; document.getElementById('submitBtn').textContent = 'Add Product';
            document.getElementById('actionType').value = 'add'; document.getElementById('editProductID').value = '';
            document.getElementById('inputName').value = ''; document.getElementById('inputCategory').value = '1';
            document.getElementById('inputUnit').value = ''; document.getElementById('inputPrice').value = ''; document.getElementById('inputStock').value = ''; document.getElementById('inputImage').value = '';
        } else if (action === 'edit' && data) {
            document.getElementById('modalTitle').textContent = 'Edit Product'; document.getElementById('submitBtn').textContent = 'Save Changes';
            document.getElementById('actionType').value = 'update'; document.getElementById('editProductID').value = data.productID;
            document.getElementById('inputName').value = data.productname; document.getElementById('inputCategory').value = data.categoryID;
            document.getElementById('inputUnit').value = data.unit; document.getElementById('inputPrice').value = data.price; document.getElementById('inputStock').value = data.stock; document.getElementById('inputImage').value = data.productImage || '';
        }
    }
    function closeModal() { document.getElementById('productModal').classList.remove('active'); }
    document.getElementById('productModal').addEventListener('click', function(e) { if (e.target === this) closeModal(); });
    </script>
</body>
</html>