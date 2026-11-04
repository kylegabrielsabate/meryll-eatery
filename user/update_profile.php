<?php
session_start();
include '../connect.php';

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }

$userID = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);

    if (empty($username) || empty($email)) {
        header("Location: profile.php?error=missing_fields");
        exit;
    }

    // Update user details
    $stmt = $con->prepare("UPDATE users SET username = ?, email = ? WHERE user_id = ?");
    $stmt->bind_param("ssi", $username, $email, $userID);

    if ($stmt->execute()) {
        // Update session username so it reflects immediately in the sidebar
        $_SESSION['username'] = $username;
        header("Location: profile.php?success=updated");
    } else {
        header("Location: profile.php?error=db_error");
    }
    exit;
}

header("Location: profile.php");
exit;
?>