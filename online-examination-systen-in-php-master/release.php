<?php
// ============================================================
// release.php  –  Time-lock and release logic (server-side only)
// Capstone: Secure Online Examination Paper Distribution System
// ============================================================
require_once __DIR__ . '/crypto.php';

/**
 * Checks whether a quiz should be unlocked (now >= start_time).
 * If yes: decrypts the cipher, verifies SHA-256 integrity,
 *         re-inserts questions/options/answers, and marks unlocked.
 * If hash mismatch: marks 'tampered', does NOT restore data.
 * All time comparisons use the SERVER clock only.
 *
 * @param mysqli $con
 * @param string $eid  Quiz ID
 */
function release_exam(mysqli $con, string $eid): void {

    // ── 1. Fetch quiz status and start_time ────────────────────────────────
    $stmt = $con->prepare("SELECT status, start_time FROM quiz WHERE eid = ?");
    $stmt->bind_param('s', $eid);
    $stmt->execute();
    $quiz = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$quiz || $quiz['status'] !== 'locked') return;   // nothing to do
    if (!$quiz['start_time']) return;                      // no schedule set

    // ── 2. Server-side time gate (NEVER trust browser) ─────────────────────
    if (strtotime($quiz['start_time']) > time()) return;  // not yet

    // ── 3. Fetch ciphertext from quiz_secure ───────────────────────────────
    $stmt = $con->prepare("SELECT cipher_b64, iv_b64, sha256 FROM quiz_secure WHERE eid = ?");
    $stmt->bind_param('s', $eid);
    $stmt->execute();
    $secure = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$secure) return;   // no cipher data

    // ── 4. Decrypt ────────────────────────────────────────────────────────
    $plain = exam_decrypt($secure['cipher_b64'], $secure['iv_b64'], $eid);

    if ($plain === false || $plain === '') {
        // Decryption failed – treat as tampered
        _mark_tampered($con, $eid);
        return;
    }

    // ── 5. SHA-256 integrity check ─────────────────────────────────────────
    $computed = exam_hash($plain);
    if (!hash_equals($secure['sha256'], $computed)) {
        _mark_tampered($con, $eid);
        return;
    }

    // ── 6. Re-insert questions, options, answers from JSON ─────────────────
    $data = json_decode($plain, true);
    if (!is_array($data)) {
        _mark_tampered($con, $eid);
        return;
    }

    foreach ($data as $qdata) {
        $qid    = $qdata['qid'];
        $qns    = $qdata['qns'];
        $choice = (int)$qdata['choice'];
        $sn     = (int)$qdata['sn'];
        $ansid  = $qdata['ansid'];

        // Insert question (IGNORE avoids duplicates on double-call)
        $stmt = $con->prepare(
            "INSERT IGNORE INTO questions (eid, qid, qns, choice, sn) VALUES (?,?,?,?,?)"
        );
        $stmt->bind_param('sssii', $eid, $qid, $qns, $choice, $sn);
        $stmt->execute();
        $stmt->close();

        // Insert options
        foreach ($qdata['options'] as $opt) {
            $oid  = $opt['optionid'];
            $otxt = $opt['option'];
            $stmt = $con->prepare(
                "INSERT IGNORE INTO options (qid, `option`, optionid) VALUES (?,?,?)"
            );
            $stmt->bind_param('sss', $qid, $otxt, $oid);
            $stmt->execute();
            $stmt->close();
        }

        // Insert correct answer
        $stmt = $con->prepare(
            "INSERT IGNORE INTO answer (qid, ansid) VALUES (?,?)"
        );
        $stmt->bind_param('ss', $qid, $ansid);
        $stmt->execute();
        $stmt->close();
    }

    // ── 7. Mark quiz as unlocked ───────────────────────────────────────────
    $stmt = $con->prepare("UPDATE quiz SET status = 'unlocked' WHERE eid = ?");
    $stmt->bind_param('s', $eid);
    $stmt->execute();
    $stmt->close();

    log_activity($con, 'SYSTEM', 'QUIZ_UNLOCKED', $eid);
}

/** Helper: set status = 'tampered' and log the event. */
function _mark_tampered(mysqli $con, string $eid): void {
    $stmt = $con->prepare("UPDATE quiz SET status = 'tampered' WHERE eid = ?");
    $stmt->bind_param('s', $eid);
    $stmt->execute();
    $stmt->close();
    log_activity($con, 'SYSTEM', 'TAMPER_DETECTED', $eid);
}
