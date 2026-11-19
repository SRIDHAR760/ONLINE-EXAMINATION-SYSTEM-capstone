<?php
// ============================================================
// crypto.php  –  Encryption helpers (NEVER send to browser)
// Capstone: AES-256-CBC + SHA-256 + Base64
// ============================================================

// ── MASTER SECRET ────────────────────────────────────────────────────────────
// Change this to any long random string before going live.
// KEEP THIS FILE OUT OF PUBLIC VERSION CONTROL.
define('MASTER_SECRET',
    'C@p$t0n3#OnlineExam##AES256-SHA256##SecureKey##2024##KNIT##Integrity##7x9Qr!mPz');

// ── KEY DERIVATION ────────────────────────────────────────────────────────────
// Returns 32 raw bytes derived from the master secret + quiz ID.
// These 32 bytes become the AES-256 key (256 bits = 32 bytes).
function exam_key(string $eid): string {
    return hash('sha256', MASTER_SECRET . $eid, true);   // raw = true → binary
}

// ── INTEGRITY HASH ────────────────────────────────────────────────────────────
// Returns the hex SHA-256 digest of any string.
// Used to verify that the ciphertext has not been tampered with.
function exam_hash(string $plain): string {
    return hash('sha256', $plain);   // 64 hex chars
}

// ── ENCRYPT ───────────────────────────────────────────────────────────────────
// AES-256-CBC with a fresh random 16-byte IV each time.
// Returns [base64(ciphertext), base64(iv)] – safe to store in the DB.
// The KEY is NEVER stored; it is re-derived from MASTER_SECRET + eid on demand.
function exam_encrypt(string $plain, string $eid): array {
    $key    = exam_key($eid);
    $iv     = random_bytes(16);                          // secure random IV
    $cipher = openssl_encrypt($plain, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
    return [base64_encode($cipher), base64_encode($iv)];
}

// ── DECRYPT ───────────────────────────────────────────────────────────────────
// Reverses exam_encrypt().  Returns the plain string or false on error.
function exam_decrypt(string $cipher_b64, string $iv_b64, string $eid) {
    $key    = exam_key($eid);
    $cipher = base64_decode($cipher_b64);
    $iv     = base64_decode($iv_b64);
    return openssl_decrypt($cipher, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
}

// ── ACTIVITY LOGGER ───────────────────────────────────────────────────────────
// Writes a row to activity_log; $eid may be null for non-quiz events.
function log_activity(mysqli $con, string $email, string $action, ?string $eid = null): void {
    $ip   = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $stmt = $con->prepare(
        "INSERT INTO activity_log (email, action, eid, ip) VALUES (?, ?, ?, ?)"
    );
    $stmt->bind_param('ssss', $email, $action, $eid, $ip);
    $stmt->execute();
    $stmt->close();
}

// ── SESSION TIMEOUT GUARD ─────────────────────────────────────────────────────
// Call after session_start(). Destroys session and redirects if idle > 15 min.
function check_session_timeout(): void {
    $timeout = 900; // 15 minutes
    if (isset($_SESSION['last_activity'])) {
        if (time() - $_SESSION['last_activity'] > $timeout) {
            session_unset();
            session_destroy();
            header('Location: index.php?w=Session+expired.+Please+log+in+again.');
            exit;
        }
    }
    $_SESSION['last_activity'] = time();
}
