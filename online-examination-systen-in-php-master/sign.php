<?php
// ============================================================
// sign.php  –  New-user registration (Part 1: salted SHA-256)
// ============================================================
require_once __DIR__ . '/dbConnection.php';
ob_start();

$name    = ucwords(strtolower(trim($_POST['name']    ?? '')));
$gender  = $_POST['gender']   ?? '';
$email   = trim($_POST['email']   ?? '');
$college = trim($_POST['college'] ?? '');
$mob     = trim($_POST['mob']     ?? '');
$password= $_POST['password'] ?? '';

// ── Salted SHA-256 (Part 1) ───────────────────────────────────────────────────
// Generate a 32-char hex salt (16 random bytes → hex)
$salt   = bin2hex(random_bytes(16));
$hashed = hash('sha256', $salt . $password);
$stored = $salt . ':' . $hashed;   // stored as "salt:hash" – 97 chars total

// ── Prepared-statement INSERT (Part 1) ───────────────────────────────────────
$stmt = $con->prepare(
    "INSERT INTO user (name, gender, college, email, mob, password) VALUES (?,?,?,?,?,?)"
);
$stmt->bind_param('ssssss', $name, $gender, $college, $email, $mob, $stored);

if ($stmt->execute()) {
    session_start();
    $_SESSION['email']         = $email;
    $_SESSION['name']          = $name;
    $_SESSION['role']          = 'student';   // role-based auth (Part 1)
    $_SESSION['last_activity'] = time();      // session timeout (Part 1)
    $stmt->close();
    ob_end_clean();
    header("location:account.php?q=1");
} else {
    $stmt->close();
    ob_end_clean();
    header("location:index.php?q7=Email Already Registered!!!");
}