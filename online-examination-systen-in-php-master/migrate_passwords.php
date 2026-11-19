<?php
// ============================================================
// migrate_passwords.php
// ONE-TIME script: hash existing ADMIN plain-text passwords
//                  with salted SHA-256 (salt:hash format).
// Run ONCE, then DELETE this file from the server.
// ============================================================
// NOTE: Existing MD5-hashed STUDENT accounts are NO LONGER
//       valid after the new login.php is deployed because
//       login.php now only accepts salt:hash format.
//       Students must RE-REGISTER with a new account, or you
//       can provide a password-reset flow.
// ============================================================
require_once __DIR__ . '/dbConnection.php';

// --- Protect: only run from browser on localhost or via CLI ---
$allowed_ips = ['127.0.0.1', '::1'];
if (php_sapi_name() !== 'cli' && !in_array($_SERVER['REMOTE_ADDR'] ?? '', $allowed_ips)) {
    http_response_code(403);
    die('Forbidden – run locally only.');
}

echo "<pre>";
echo "=== Admin Password Migration ===\n\n";

$result = $con->query("SELECT admin_id, email, password FROM admin");
$migrated = 0;
$skipped  = 0;

while ($row = $result->fetch_assoc()) {
    $plain = $row['password'];

    // Skip if already migrated (contains ':' separator)
    if (strpos($plain, ':') !== false) {
        echo "Admin #{$row['admin_id']} ({$row['email']}): already migrated – SKIPPED\n";
        $skipped++;
        continue;
    }

    // Generate 16-byte random salt (hex → 32 chars)
    $salt    = bin2hex(random_bytes(16));
    $hashed  = hash('sha256', $salt . $plain);
    $stored  = $salt . ':' . $hashed;

    $stmt = $con->prepare("UPDATE admin SET password = ? WHERE admin_id = ?");
    $stmt->bind_param('si', $stored, $row['admin_id']);
    $stmt->execute();
    $stmt->close();

    echo "Admin #{$row['admin_id']} ({$row['email']}): password migrated OK\n";
    $migrated++;
}

echo "\nDone. Migrated: $migrated  |  Skipped (already hashed): $skipped\n";
echo "\n*** DELETE this file from the server now! ***\n";
echo "</pre>";
