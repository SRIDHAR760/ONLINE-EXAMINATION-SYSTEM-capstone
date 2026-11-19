<?php
// ============================================================
// admin.php  –  Admin authentication (Part 1: salted SHA-256)
//               Removes hardcoded session key; uses role='admin'
// ============================================================
require_once __DIR__ . '/dbConnection.php';

$ref      = $_GET['q']         ?? 'index.php';
$email    = trim($_POST['uname']    ?? '');
$password = $_POST['password'] ?? '';

// ── Prepared statement (Part 1) ───────────────────────────────────────────────
$stmt = $con->prepare("SELECT admin_id, password FROM admin WHERE email = ?");
$stmt->bind_param('s', $email);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

$valid = false;
if ($row) {
    $parts = explode(':', $row['password'], 2);
    if (count($parts) === 2) {
        // Salted SHA-256 format: salt:hash  (set by migrate_passwords.php)
        [$salt, $stored_hash] = $parts;
        $computed = hash('sha256', $salt . $password);
        $valid    = hash_equals($stored_hash, $computed);
    }
}

if ($valid) {
    session_start();
    if (isset($_SESSION['email'])) session_unset();
    $_SESSION['name']          = 'Admin';
    $_SESSION['role']          = 'admin';    // role-based auth – replaces hardcoded key
    $_SESSION['email']         = $email;
    $_SESSION['last_activity'] = time();     // session timeout (Part 1)
    header("location:dash.php?q=0");
} else {
    header("location:$ref?w=Warning : Access denied");
}
exit;