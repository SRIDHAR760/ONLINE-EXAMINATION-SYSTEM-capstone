<?php
// ============================================================
// login.php  –  Student authentication (Part 1: salted SHA-256)
// ============================================================
session_start();
if (isset($_SESSION['email'])) {
    session_unset();
    session_destroy();
    session_start();
}

require_once __DIR__ . '/dbConnection.php';

$ref      = $_GET['q']        ?? 'index.php';
$email    = trim($_POST['email']    ?? '');
$password = $_POST['password'] ?? '';

// ── Prepared statement (Part 1) ───────────────────────────────────────────────
$stmt = $con->prepare("SELECT name, password FROM user WHERE email = ?");
$stmt->bind_param('s', $email);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

$valid = false;
if ($row) {
    $parts = explode(':', $row['password'], 2);
    if (count($parts) === 2) {
        // Salted SHA-256 format: salt:hash
        [$salt, $stored_hash] = $parts;
        $computed = hash('sha256', $salt . $password);
        $valid    = hash_equals($stored_hash, $computed);   // timing-safe compare
    }
    // Note: legacy MD5 hashes (no ':') are rejected – students must re-register.
}

if ($valid) {
    $_SESSION['name']          = $row['name'];
    $_SESSION['email']         = $email;
    $_SESSION['role']          = 'student';   // role-based auth (Part 1)
    $_SESSION['last_activity'] = time();      // session timeout (Part 1)
    header("location:account.php?q=1");
} else {
    header("location:$ref?w=Wrong Username or Password");
}
exit;