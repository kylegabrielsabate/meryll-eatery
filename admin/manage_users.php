<?php
session_start();
include '../connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../user/login.php');
    exit();
}

// Handle user deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    $user_id = (int)$_POST['user_id'];
    $stmt = $con->prepare("DELETE FROM users WHERE user_id = ? AND role != 'admin'");
    $stmt->bind_param("i", $user_id);
    if ($stmt->execute()) {
        $success_msg = "User deleted successfully!";
    } else {
        $error_msg = "Error deleting user.";
    }
}

// Get sort parameter
$sort = $_GET['sort'] ?? 'all';
$search = isset($_GET['search']) ? $con->real_escape_string(trim($_GET['search'])) : '';

// Build query based on sort
$sql = "SELECT user_id, username, email, student_id, role, created_at FROM users WHERE role IN ('user', 'instructor')";

if ($sort === 'student') {
    $sql .= " AND student_id LIKE 'E%'";
} elseif ($sort === 'instructor') {
    $sql .= " AND student_id LIKE 'TAG-%'";
}

if (!empty($search)) {
    $sql .= " AND (username LIKE '%$search%' OR email LIKE '%$search%' OR student_id LIKE '%$search%')";
}

$sql .= " ORDER BY created_at DESC";
$users = $con->query($sql);

// Count totals
$total_students = $con->query("SELECT COUNT(*) as count FROM users WHERE role = 'user' AND student_id LIKE 'E%'")->fetch_assoc()['count'];
$total_instructors = $con->query("SELECT COUNT(*) as count FROM users WHERE role = 'instructor' OR student_id LIKE 'TAG-%'")->fetch_assoc()['count'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Users - Meryll Eatery Admin</title>
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
        <li class="nav-item"><a href="admin_inventory.php" class="nav-link"><i class="fas fa-boxes-stacked"></i> Inventory</a></li>
        <li class="nav-item"><a href="manage_users.php" class="nav-link active"><i class="fas fa-users"></i> Manage Users</a></li>
        <li class="nav-item"><a href="admin_report.php" class="nav-link"><i class="fas fa-chart-line"></i> Monthly Report</a></li>
    </ul>
    <div class="sidebar-footer">
        <a href="admin.php" class="nav-link"><i class="fas fa-user-shield"></i> <?php echo htmlspecialchars($_SESSION['username']); ?></a>
        <a href="../user/logout.php" class="nav-link" style="color: #ef4444;"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
</aside>

<main class="main-content">
    <div class="admin-container" style="max-width: 1200px; margin: 0 auto;">
        <?php if (isset($success_msg)): ?><div class="alert-msg success"><i class="fas fa-check-circle"></i> <?php echo $success_msg; ?></div><?php endif; ?>
        <?php if (isset($error_msg)): ?><div class="alert-msg error"><i class="fas fa-exclamation-circle"></i> <?php echo $error_msg; ?></div><?php endif; ?>

        <div class="admin-header">
            <div>
                <h1>Manage Users</h1>
                <p style="color: var(--admin-text-muted, #64748b);">View and manage registered students and instructors.</p>
            </div>
        </div>

        <!-- User Stats Cards -->
        <div class="stats-grid" style="margin-bottom: 1.5rem;">
            <div class="stat-card">
                <div class="stat-icon blue"><i class="fas fa-user-graduate"></i></div>
                <div class="stat-info"><h3>Students</h3><p class="stat-number"><?php echo $total_students; ?></p></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon coral"><i class="fas fa-chalkboard-teacher"></i></div>
                <div class="stat-info"><h3>Instructors</h3><p class="stat-number"><?php echo $total_instructors; ?></p></div>
            </div>
        </div>

        <!-- Sort & Search Bar -->
        <div class="admin-card" style="padding: 1rem;">
            <div style="display: flex; gap: 1rem; align-items: center; flex-wrap: wrap;">
                <div class="filter-tabs" style="margin-bottom: 0; flex: 1;">
                    <a href="?sort=all&search=<?php echo urlencode($search); ?>" class="filter-tab <?= $sort === 'all' ? 'active' : '' ?>">All Users</a>
                    <a href="?sort=student&search=<?php echo urlencode($search); ?>" class="filter-tab <?= $sort === 'student' ? 'active' : '' ?>">Students</a>
                    <a href="?sort=instructor&search=<?php echo urlencode($search); ?>" class="filter-tab <?= $sort === 'instructor' ? 'active' : '' ?>">Instructors</a>
                </div>
                <form method="GET" class="search-container" style="width: 250px;">
                    <i class="fas fa-search"></i>
                    <input type="text" name="search" placeholder="Search users..." value="<?php echo htmlspecialchars($search); ?>">
                    <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sort); ?>">
                </form>
            </div>
        </div>

        <!-- Users Table -->
        <div class="admin-card" style="padding: 0;">
            <div class="table-scroll-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>ID Number</th>
                            <th>Type</th>
                            <th>Registered</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($users && $users->num_rows > 0): ?>
                            <?php while($user = $users->fetch_assoc()): 
                                $is_student = (strpos($user['student_id'], 'E') === 0);
                                $type_class = $is_student ? 'user-type-student' : 'user-type-instructor';
                                $type_label = $is_student ? 'Student' : 'Instructor';
                            ?>
                                <tr>
                                    <td style="font-weight: 700;">#<?php echo $user['user_id']; ?></td>
                                    <td style="font-weight: 600;"><?php echo htmlspecialchars($user['username']); ?></td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td><?php echo htmlspecialchars($user['student_id']); ?></td>
                                    <td><span class="user-type-badge <?php echo $type_class; ?>"><?php echo $type_label; ?></span></td>
                                    <td><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                                    <td>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this user?');">
                                            <input type="hidden" name="delete_user" value="1">
                                            <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                            <button type="submit" class="action-btn btn-delete" title="Delete User"><i class="fas fa-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="7" style="text-align: center; padding: 3rem; color: var(--admin-text-muted);">No users found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<script>
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('active');
    document.getElementById('sidebarOverlay').classList.toggle('active');
    document.body.style.overflow = document.getElementById('sidebar').classList.contains('active') ? 'hidden' : '';
}
</script>
</body>
</html>