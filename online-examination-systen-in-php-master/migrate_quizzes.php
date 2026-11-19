<?php
// ============================================================
// migrate_quizzes.php  –  ADMIN-ONLY, run once
// Encrypts the existing sample quizzes using the same AES-256
// + SHA-256 pipeline as the normal quiz-creation flow.
// Sets start_time = NOW() so all sample quizzes release
// immediately and remain usable for testing.
// Run ONCE, then consider deleting this file.
// ============================================================
session_start();
require_once __DIR__ . '/dbConnection.php';
require_once __DIR__ . '/crypto.php';
require_once __DIR__ . '/release.php';

// Admin-only guard
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    die('Forbidden – admins only. Log in to <a href="index.php">the home page</a> first.');
}

echo "<pre>";
echo "=== Quiz Migration: Encrypting Existing Sample Quizzes ===\n\n";

// Fetch every quiz that is currently 'unlocked' (the sample data state after upgrade.sql)
$result = $con->query("SELECT eid, title FROM quiz WHERE status = 'unlocked'");
if (!$result || $result->num_rows === 0) {
    echo "No unlocked quizzes found to migrate.\n";
    echo "(Already migrated, or none exist.)\n</pre>";
    exit;
}

while ($quiz = $result->fetch_assoc()) {
    $eid   = $quiz['eid'];
    $title = $quiz['title'];
    echo "Processing: \"$title\" (eid=$eid)\n";

    // ── Collect questions, options, answers ──────────────────────────────
    $stmt = $con->prepare("SELECT qid, qns, choice, sn FROM questions WHERE eid = ? ORDER BY sn ASC");
    $stmt->bind_param('s', $eid);
    $stmt->execute();
    $qresult = $stmt->get_result();
    $stmt->close();

    if ($qresult->num_rows === 0) {
        echo "  → No plain questions found – may already be encrypted. SKIPPING.\n\n";
        continue;
    }

    $payload = [];
    while ($qrow = $qresult->fetch_assoc()) {
        $qid = $qrow['qid'];

        // Options
        $stmt2 = $con->prepare("SELECT optionid, `option` FROM options WHERE qid = ?");
        $stmt2->bind_param('s', $qid);
        $stmt2->execute();
        $opts_res = $stmt2->get_result();
        $stmt2->close();
        $opts = [];
        while ($opt = $opts_res->fetch_assoc()) {
            $opts[] = ['optionid' => $opt['optionid'], 'option' => $opt['option']];
        }

        // Correct answer
        $stmt3 = $con->prepare("SELECT ansid FROM answer WHERE qid = ?");
        $stmt3->bind_param('s', $qid);
        $stmt3->execute();
        $ans_res = $stmt3->get_result();
        $stmt3->close();
        $ans_row = $ans_res->fetch_assoc();
        $ansid   = $ans_row['ansid'] ?? '';

        $payload[] = [
            'qid'     => $qid,
            'qns'     => $qrow['qns'],
            'choice'  => (int)$qrow['choice'],
            'sn'      => (int)$qrow['sn'],
            'ansid'   => $ansid,
            'options' => $opts,
        ];
    }

    // ── Encrypt ─────────────────────────────────────────────────────────
    $plain_json           = json_encode($payload);
    $hash                 = exam_hash($plain_json);
    [$cipher_b64, $iv_b64] = exam_encrypt($plain_json, $eid);

    // ── Save to quiz_secure ──────────────────────────────────────────────
    $stmt = $con->prepare(
        "INSERT INTO quiz_secure (eid, cipher_b64, iv_b64, sha256)
         VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE cipher_b64=VALUES(cipher_b64),
                                 iv_b64=VALUES(iv_b64),
                                 sha256=VALUES(sha256)"
    );
    $stmt->bind_param('ssss', $eid, $cipher_b64, $iv_b64, $hash);
    $stmt->execute();
    $stmt->close();

    // ── Delete plain rows ─────────────────────────────────────────────────
    foreach ($payload as $q) {
        $qid = $q['qid'];
        $s1 = $con->prepare("DELETE FROM options WHERE qid = ?");
        $s1->bind_param('s', $qid); $s1->execute(); $s1->close();
        $s2 = $con->prepare("DELETE FROM answer WHERE qid = ?");
        $s2->bind_param('s', $qid); $s2->execute(); $s2->close();
    }
    $stmt = $con->prepare("DELETE FROM questions WHERE eid = ?");
    $stmt->bind_param('s', $eid); $stmt->execute(); $stmt->close();

    // ── Set start_time = now, status = locked, then immediately release ───
    $now = date('Y-m-d H:i:s');
    $stmt = $con->prepare("UPDATE quiz SET start_time = ?, status = 'locked' WHERE eid = ?");
    $stmt->bind_param('ss', $now, $eid);
    $stmt->execute();
    $stmt->close();

    log_activity($con, $_SESSION['email'], 'QUIZ_ENCRYPTED', $eid);

    // Immediately release (start_time = now, so condition met)
    release_exam($con, $eid);

    echo "  → Encrypted + Released immediately (start_time=$now). OK.\n\n";
}

echo "=== Migration complete. ===\n";
echo "Sample quizzes are now status='unlocked' and protected by AES-256.\n";
echo "Delete this file from the server when done.\n";
echo "</pre>";
