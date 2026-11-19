<?php
// ============================================================
// update.php  –  Central write handler
// Changes: role-based auth, prepared statements, AES-256
//          encryption on quiz creation, status-aware quiz flow
// ============================================================
require_once __DIR__ . '/dbConnection.php';
require_once __DIR__ . '/crypto.php';
require_once __DIR__ . '/release.php';

session_start();
check_session_timeout();   // 15-min inactivity guard (Part 1)

$email    = $_SESSION['email'] ?? '';
$role     = $_SESSION['role']  ?? '';
$is_admin = ($role === 'admin');

// ── HELPER: require admin role ────────────────────────────────────────────────
function require_admin(): void {
    global $is_admin;
    if (!$is_admin) {
        header('Location: index.php');
        exit;
    }
}

// =============================================================================
// ADMIN-ONLY OPERATIONS
// =============================================================================

// ── DELETE FEEDBACK ───────────────────────────────────────────────────────────
if (isset($_GET['fdid'])) {
    require_admin();
    $id = $_GET['fdid'];
    $stmt = $con->prepare("DELETE FROM feedback WHERE id = ?");
    $stmt->bind_param('s', $id);
    $stmt->execute();
    $stmt->close();
    header("location:dash.php?q=3");
    exit;
}

// ── DELETE USER ───────────────────────────────────────────────────────────────
if (isset($_GET['demail'])) {
    require_admin();
    $demail = $_GET['demail'];
    foreach (["DELETE FROM rank WHERE email=?",
              "DELETE FROM history WHERE email=?",
              "DELETE FROM user WHERE email=?"] as $sql) {
        $stmt = $con->prepare($sql);
        $stmt->bind_param('s', $demail);
        $stmt->execute();
        $stmt->close();
    }
    header("location:dash.php?q=1");
    exit;
}

// ── REMOVE QUIZ ───────────────────────────────────────────────────────────────
if (($_GET['q'] ?? '') === 'rmquiz') {
    require_admin();
    $eid = $_GET['eid'] ?? '';

    // Delete options & answers for each question
    $stmt = $con->prepare("SELECT qid FROM questions WHERE eid = ?");
    $stmt->bind_param('s', $eid);
    $stmt->execute();
    $rows = $stmt->get_result();
    $stmt->close();
    while ($r = $rows->fetch_assoc()) {
        $qid = $r['qid'];
        $s1 = $con->prepare("DELETE FROM options WHERE qid = ?");
        $s1->bind_param('s', $qid); $s1->execute(); $s1->close();
        $s2 = $con->prepare("DELETE FROM answer WHERE qid = ?");
        $s2->bind_param('s', $qid); $s2->execute(); $s2->close();
    }

    foreach (["DELETE FROM questions WHERE eid=?",
              "DELETE FROM quiz WHERE eid=?",
              "DELETE FROM history WHERE eid=?",
              "DELETE FROM quiz_secure WHERE eid=?"] as $sql) {
        $stmt = $con->prepare($sql);
        $stmt->bind_param('s', $eid);
        $stmt->execute();
        $stmt->close();
    }
    header("location:dash.php?q=5");
    exit;
}

// ── ADD QUIZ (step 1) ─────────────────────────────────────────────────────────
if (($_GET['q'] ?? '') === 'addquiz') {
    require_admin();

    $name       = ucwords(strtolower(trim($_POST['name'] ?? '')));
    $total      = (int)($_POST['total'] ?? 0);
    $sahi       = (int)($_POST['right'] ?? 0);
    $wrong      = (int)($_POST['wrong'] ?? 0);
    $time_limit = (int)($_POST['time']  ?? 0);
    $tag        = trim($_POST['tag']    ?? '');
    $desc       = trim($_POST['desc']   ?? '');

    // Convert datetime-local ("2024-01-15T14:00") to MySQL DATETIME
    $start_raw  = trim($_POST['start_time'] ?? '');
    $start_time = $start_raw ? date('Y-m-d H:i:s', strtotime($start_raw)) : null;

    $id     = uniqid();
    $status = 'locked';

    $stmt = $con->prepare(
        "INSERT INTO quiz (eid, title, sahi, wrong, total, time, intro, tag, date, start_time, status)
         VALUES (?,?,?,?,?,?,?,?,NOW(),?,?)"
    );
    $stmt->bind_param('ssiiiissss', $id, $name, $sahi, $wrong, $total, $time_limit, $desc, $tag, $start_time, $status);
    $stmt->execute();
    $stmt->close();

    header("location:dash.php?q=4&step=2&eid=$id&n=$total");
    exit;
}

// ── ADD QUESTIONS (step 2) + ENCRYPT ─────────────────────────────────────────
if (($_GET['q'] ?? '') === 'addqns') {
    require_admin();

    $n   = (int)($_GET['n']   ?? 0);
    $eid = $_GET['eid']       ?? '';
    $ch  = 4; // always 4 choices

    $payload = [];

    for ($i = 1; $i <= $n; $i++) {
        $qid = uniqid();
        $qns = $_POST['qns' . $i] ?? '';

        // Insert question into plain table
        $stmt = $con->prepare("INSERT INTO questions (eid, qid, qns, choice, sn) VALUES (?,?,?,?,?)");
        $stmt->bind_param('sssii', $eid, $qid, $qns, $ch, $i);
        $stmt->execute();
        $stmt->close();

        // Generate 4 option IDs
        $oaid = uniqid(); $obid = uniqid(); $ocid = uniqid(); $odid = uniqid();
        $a = $_POST[$i . '1'] ?? ''; $b = $_POST[$i . '2'] ?? '';
        $c = $_POST[$i . '3'] ?? ''; $d = $_POST[$i . '4'] ?? '';

        $options_data = [
            ['optionid' => $oaid, 'option' => $a],
            ['optionid' => $obid, 'option' => $b],
            ['optionid' => $ocid, 'option' => $c],
            ['optionid' => $odid, 'option' => $d],
        ];

        foreach ($options_data as $opt) {
            $stmt = $con->prepare("INSERT INTO options (qid, `option`, optionid) VALUES (?,?,?)");
            $stmt->bind_param('sss', $qid, $opt['option'], $opt['optionid']);
            $stmt->execute();
            $stmt->close();
        }

        // Determine correct answer optionid
        switch ($_POST['ans' . $i] ?? 'a') {
            case 'b': $ansid = $obid; break;
            case 'c': $ansid = $ocid; break;
            case 'd': $ansid = $odid; break;
            default:  $ansid = $oaid;
        }

        $stmt = $con->prepare("INSERT INTO answer (qid, ansid) VALUES (?,?)");
        $stmt->bind_param('ss', $qid, $ansid);
        $stmt->execute();
        $stmt->close();

        $payload[] = [
            'qid'     => $qid,
            'qns'     => $qns,
            'choice'  => $ch,
            'sn'      => $i,
            'ansid'   => $ansid,
            'options' => $options_data,
        ];
    }

    // ── Build JSON → SHA-256 hash → AES-256 encrypt ─────────────────────────
    $plain_json            = json_encode($payload);
    $hash                  = exam_hash($plain_json);          // SHA-256 digest
    [$cipher_b64, $iv_b64] = exam_encrypt($plain_json, $eid); // AES-256-CBC

    // Save cipher to quiz_secure
    $stmt = $con->prepare(
        "INSERT INTO quiz_secure (eid, cipher_b64, iv_b64, sha256)
         VALUES (?,?,?,?)
         ON DUPLICATE KEY UPDATE cipher_b64=VALUES(cipher_b64),
                                 iv_b64=VALUES(iv_b64),
                                 sha256=VALUES(sha256)"
    );
    $stmt->bind_param('ssss', $eid, $cipher_b64, $iv_b64, $hash);
    $stmt->execute();
    $stmt->close();

    // ── Delete plaintext rows ─────────────────────────────────────────────────
    $stmt = $con->prepare("SELECT qid FROM questions WHERE eid = ?");
    $stmt->bind_param('s', $eid);
    $stmt->execute();
    $rows = $stmt->get_result();
    $stmt->close();
    while ($r = $rows->fetch_assoc()) {
        $qid = $r['qid'];
        $s1 = $con->prepare("DELETE FROM options WHERE qid = ?");
        $s1->bind_param('s', $qid); $s1->execute(); $s1->close();
        $s2 = $con->prepare("DELETE FROM answer WHERE qid = ?");
        $s2->bind_param('s', $qid); $s2->execute(); $s2->close();
    }
    $stmt = $con->prepare("DELETE FROM questions WHERE eid = ?");
    $stmt->bind_param('s', $eid); $stmt->execute(); $stmt->close();

    // ── Set status = locked ───────────────────────────────────────────────────
    $stmt = $con->prepare("UPDATE quiz SET status = 'locked' WHERE eid = ?");
    $stmt->bind_param('s', $eid); $stmt->execute(); $stmt->close();

    log_activity($con, $email, 'QUIZ_ENCRYPTED', $eid);

    // ── If start_time is already in the past, release immediately ─────────────
    $stmt = $con->prepare("SELECT start_time FROM quiz WHERE eid = ?");
    $stmt->bind_param('s', $eid);
    $stmt->execute();
    $qrow = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($qrow && $qrow['start_time'] && strtotime($qrow['start_time']) <= time()) {
        release_exam($con, $eid);
    }

    header("location:dash.php?q=0");
    exit;
}

// =============================================================================
// SHARED: QUIZ ANSWER SUBMISSION
// =============================================================================
if (($_GET['q'] ?? '') === 'quiz' && ($_GET['step'] ?? '') == 2) {

    $eid   = $_GET['eid']  ?? '';
    $sn    = (int)($_GET['n'] ?? 1);
    $total = (int)($_GET['t'] ?? 1);
    $ans   = $_POST['ans'] ?? '';
    $qid   = $_GET['qid']  ?? '';

    // Block if quiz is not unlocked
    $stmt = $con->prepare("SELECT status FROM quiz WHERE eid = ?");
    $stmt->bind_param('s', $eid);
    $stmt->execute();
    $qstatus = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$qstatus || $qstatus['status'] !== 'unlocked') {
        log_activity($con, $email, 'EARLY_ACCESS_ATTEMPT', $eid);
        header("location:account.php?q=1");
        exit;
    }

    // Get correct answer
    $stmt = $con->prepare("SELECT ansid FROM answer WHERE qid = ?");
    $stmt->bind_param('s', $qid);
    $stmt->execute();
    $arow = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $ansid = $arow['ansid'] ?? '';

    if ($ans === $ansid) {
        // ── Correct answer ───────────────────────────────────────────────────
        $stmt = $con->prepare("SELECT sahi FROM quiz WHERE eid = ?");
        $stmt->bind_param('s', $eid);
        $stmt->execute();
        $qdata = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $sahi_pts = (int)($qdata['sahi'] ?? 0);

        if ($sn == 1) {
            $stmt = $con->prepare(
                "INSERT INTO history (email,eid,score,level,sahi,wrong,date) VALUES(?,?,0,0,0,0,NOW())"
            );
            $stmt->bind_param('ss', $email, $eid);
            $stmt->execute();
            $stmt->close();
        }

        $stmt = $con->prepare("SELECT score, sahi FROM history WHERE eid=? AND email=?");
        $stmt->bind_param('ss', $eid, $email);
        $stmt->execute();
        $hrow = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $new_score = (int)($hrow['score'] ?? 0) + $sahi_pts;
        $new_right = (int)($hrow['sahi']  ?? 0) + 1;

        $stmt = $con->prepare(
            "UPDATE history SET score=?,level=?,sahi=?,date=NOW() WHERE email=? AND eid=?"
        );
        $stmt->bind_param('iiiss', $new_score, $sn, $new_right, $email, $eid);
        $stmt->execute();
        $stmt->close();

    } else {
        // ── Wrong answer ─────────────────────────────────────────────────────
        $stmt = $con->prepare("SELECT wrong FROM quiz WHERE eid = ?");
        $stmt->bind_param('s', $eid);
        $stmt->execute();
        $qdata = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $wrong_pts = (int)($qdata['wrong'] ?? 0);

        if ($sn == 1) {
            $stmt = $con->prepare(
                "INSERT INTO history (email,eid,score,level,sahi,wrong,date) VALUES(?,?,0,0,0,0,NOW())"
            );
            $stmt->bind_param('ss', $email, $eid);
            $stmt->execute();
            $stmt->close();
        }

        $stmt = $con->prepare("SELECT score, wrong FROM history WHERE eid=? AND email=?");
        $stmt->bind_param('ss', $eid, $email);
        $stmt->execute();
        $hrow = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $new_score = (int)($hrow['score'] ?? 0) - $wrong_pts;
        $new_wrong = (int)($hrow['wrong']  ?? 0) + 1;

        $stmt = $con->prepare(
            "UPDATE history SET score=?,level=?,wrong=?,date=NOW() WHERE email=? AND eid=?"
        );
        $stmt->bind_param('iiiss', $new_score, $sn, $new_wrong, $email, $eid);
        $stmt->execute();
        $stmt->close();
    }

    if ($sn < $total) {
        $sn++;
        header("location:account.php?q=quiz&step=2&eid=$eid&n=$sn&t=$total");
    } else {
        // Last question: update rank (students only)
        if ($role !== 'admin') {
            $stmt = $con->prepare("SELECT score FROM history WHERE eid=? AND email=?");
            $stmt->bind_param('ss', $eid, $email);
            $stmt->execute();
            $hrow = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            $final_score = (int)($hrow['score'] ?? 0);

            $stmt = $con->prepare("SELECT score FROM rank WHERE email=?");
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $rrow = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$rrow) {
                $stmt = $con->prepare("INSERT INTO rank (email,score,time) VALUES(?,?,NOW())");
                $stmt->bind_param('si', $email, $final_score);
                $stmt->execute();
                $stmt->close();
            } else {
                $new_total = (int)$rrow['score'] + $final_score;
                $stmt = $con->prepare("UPDATE rank SET score=?,time=NOW() WHERE email=?");
                $stmt->bind_param('is', $new_total, $email);
                $stmt->execute();
                $stmt->close();
            }
        }
        header("location:account.php?q=result&eid=$eid");
    }
    exit;
}

// =============================================================================
// RESTART QUIZ
// =============================================================================
if (($_GET['q'] ?? '') === 'quizre' && ($_GET['step'] ?? '') == 25) {
    $eid = $_GET['eid'] ?? '';
    $t   = (int)($_GET['t'] ?? 1);

    $stmt = $con->prepare("SELECT score FROM history WHERE eid=? AND email=?");
    $stmt->bind_param('ss', $eid, $email);
    $stmt->execute();
    $hrow = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $old_score = (int)($hrow['score'] ?? 0);

    $stmt = $con->prepare("DELETE FROM history WHERE eid=? AND email=?");
    $stmt->bind_param('ss', $eid, $email);
    $stmt->execute();
    $stmt->close();

    $stmt = $con->prepare("SELECT score FROM rank WHERE email=?");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $rrow = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($rrow) {
        $new_rank = (int)$rrow['score'] - $old_score;
        $stmt = $con->prepare("UPDATE rank SET score=?,time=NOW() WHERE email=?");
        $stmt->bind_param('is', $new_rank, $email);
        $stmt->execute();
        $stmt->close();
    }

    header("location:account.php?q=quiz&step=2&eid=$eid&n=1&t=$t");
    exit;
}
