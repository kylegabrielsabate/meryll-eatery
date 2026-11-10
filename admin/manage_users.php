<?php
session_start();
include '../connect.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') { header('Location: ../user/login.php'); exit(); }

// Handle deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    $stmt = $con->prepare("DELETE FROM users WHERE user_id = ? AND role != 'admin'");
    $stmt->bind_param("i", $_POST['user_id']);
    $stmt->execute();
    $msg = "User deleted!";
}

$sort = $_GET['sort'] ?? 'all';
$search = $_GET['search'] ?? '';

$sql = "SELECT * FROM users WHERE role != 'admin'";
if ($sort === 'student') $sql .= " AND role = 'user'";
elseif ($sort === 'instructor') $sql .= " AND role = 'instructor'";
if ($search) $sql .= " AND (username LIKE '%$search%' OR email LIKE '%$search%')";
$sql .= " ORDER BY created_at DESC";
$users = $con->query($sql);

$total_students = $con->query("SELECT COUNT(*) c FROM users WHERE role='user'")->fetch_assoc()['c'];
$total_instructors = $con->query("SELECT COUNT(*) c FROM users WHERE role='instructor'")->fetch_assoc()['c'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Users</title>
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
        <a href="admin_inventory.php" class="nav-link"><i class="fas fa-boxes-stacked"></i> Inventory</a>
        <a href="manage_users.php" class="nav-link active"><i class="fas fa-users"></i> Manage Users</a>
        <a href="admin_report.php" class="nav-link"><i class="fas fa-chart-line"></i> Monthly Report</a>
    </nav>
    <div class="sidebar-footer">
        <a href="admin.php" class="nav-link"><i class="fas fa-user-shield"></i> <?= htmlspecialchars($_SESSION['username']) ?></a>
        <a href="../user/logout.php" class="nav-link text-danger"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
</aside>

<main class="main-content">
    <div class="container-fluid p-0">
        <?php if(isset($msg)): ?><div class="alert alert-success"><?= $msg ?></div><?php endif; ?>
        
        <h1 class="mb-1">Manage Users</h1>
        <p class="text-muted mb-4">View and manage registered students and instructors.</p>

        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="bg-primary bg-opacity-10 text-primary rounded-circle p-3"><i class="fas fa-user-graduate fa-lg"></i></div>
                        <div><h6 class="text-muted mb-0">STUDENTS</h6><h3 class="mb-0 fw-bold"><?= $total_students ?></h3></div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="bg-warning bg-opacity-10 text-warning rounded-circle p-3"><i class="fas fa-chalkboard-teacher fa-lg"></i></div>
                        <div><h6 class="text-muted mb-0">INSTRUCTORS</h6><h3 class="mb-0 fw-bold"><?= $total_instructors ?></h3></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <div class="btn-group" role="group">
                        <a href="?sort=all" class="btn btn-outline-secondary <?= $sort==='all'?'active':'' ?>">All</a>
                        <a href="?sort=student" class="btn btn-outline-secondary <?= $sort==='student'?'active':'' ?>">Students</a>
                        <a href="?sort=instructor" class="btn btn-outline-secondary <?= $sort==='instructor'?'active':'' ?>">Instructors</a>
                    </div>
                    <form method="GET" class="ms-auto">
                        <input type="hidden" name="sort" value="<?= $sort ?>">
                        <div class="input-group" style="max-width:250px">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                            <input type="text" name="search" class="form-control" placeholder="Search..." value="<?= htmlspecialchars($search) ?>">
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- ✅ BOOTSTRAP TABLE RESPONSIVE WRAPPER -->
        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr><th>ID</th><th>Name</th><th>Email</th><th>ID Number</th><th>Type</th><th>Registered</th><th>Action</th></tr>
                        </thead>
                        <tbody>
                            <?php while($u = $users->fetch_assoc()): 
                                $isStudent = ($u['role'] === 'user');
                            ?>
                            <tr>
                                <td class="fw-bold">#<?= $u['user_id'] ?></td>
                                <td class="fw-semibold"><?= htmlspecialchars($u['username']) ?></td>
                                <td><?= htmlspecialchars($u['email']) ?></td>
                                <td><?= htmlspecialchars($u['student_id'] ?? 'N/A') ?></td>
                                <td><span class="badge <?= $isStudent ? 'user-type-student' : 'user-type-instructor' ?>"><?= $isStudent ? 'Student' : 'Instructor' ?></span></td>
                                <td><?= date('M j, Y', strtotime($u['created_at'])) ?></td>
                                <td>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Delete this user?')">
                                        <input type="hidden" name="delete_user" value="1">
                                        <input type="hidden" name="user_id" value="<?= $u['user_id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                            <?php if($users->num_rows === 0): ?><tr><td colspan="7" class="text-center py-4 text-muted">No users found.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('show');
    document.querySelector('.sidebar-backdrop').classList.toggle('show');
}
</script>
</body>
</html>