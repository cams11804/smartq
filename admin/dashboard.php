<?php
session_start();

// Must be logged in as admin
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}

require_once '../config/db.php';

$admin_id   = $_SESSION['admin_id'];
$admin_name = $_SESSION['admin_name'];
$admin_role = $_SESSION['admin_role'];

// ── Fetch counters ─────────────────────────────────────────────────
$counters = $pdo->query('SELECT * FROM counters ORDER BY counter_id')->fetchAll();

// ── Fetch service types ────────────────────────────────────────────
$services = $pdo->query('SELECT * FROM service_types ORDER BY service_id')->fetchAll();

// ── Handle CALL NEXT ──────────────────────────────────────────────
$action_msg   = '';
$action_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action     = $_POST['action'] ?? '';
    $counter_id = (int)($_POST['counter_id'] ?? 1);

    // ── Call Next Student ──────────────────────────────────────────
    if ($action === 'call_next') {
        $service_filter = (int)($_POST['service_id'] ?? 0);

        // First mark any currently Called tickets at this counter as Completed
        $finish = $pdo->prepare("
            UPDATE queuing q
            JOIN transactions t ON t.queuing_id = q.queuing_id
            SET q.queue_status = 'Completed', t.time_end = NOW(),
                t.transaction_status = 'Completed'
            WHERE t.counter_id = ? AND t.admin_id = ? AND q.queue_status = 'Called'
        ");
        $finish->execute([$counter_id, $admin_id]);

        // Find the next Pending student
        $sql = "SELECT q.*, s.service_name, st.fullname, st.gmail, st.cor_image, st.course
                FROM queuing q
                JOIN service_types s  ON q.service_id = s.service_id
                JOIN students st      ON q.student_number = st.student_number
                WHERE q.queue_status = 'Pending'";
        if ($service_filter) $sql .= " AND q.service_id = {$service_filter}";
        $sql .= " ORDER BY q.time_joined ASC LIMIT 1";

        $next = $pdo->query($sql)->fetch();

        if ($next) {
            // Mark as Called
            $pdo->prepare("UPDATE queuing SET queue_status = 'Called' WHERE queuing_id = ?")
                ->execute([$next['queuing_id']]);

            // Create transaction record
            $ins = $pdo->prepare("
                INSERT INTO transactions (queuing_id, admin_id, counter_id, time_start, transaction_date, transaction_status)
                VALUES (?, ?, ?, NOW(), CURDATE(), 'In Progress')
            ");
            $ins->execute([$next['queuing_id'], $admin_id, $counter_id]);

            $action_msg = "Called Queue #{$next['queuing_num']} — {$next['fullname']}";
        } else {
            $action_error = 'No students currently waiting in the queue.';
        }
    }

    // ── Complete Transaction ───────────────────────────────────────
    if ($action === 'complete') {
        $queuing_id = (int)($_POST['queuing_id'] ?? 0);
        if ($queuing_id) {
            // Get queuing data
            $qdata = $pdo->prepare("SELECT * FROM queuing WHERE queuing_id = ?");
            $qdata->execute([$queuing_id]);
            $qrow = $qdata->fetch();

            if ($qrow) {
                // Update queuing status
                $pdo->prepare("UPDATE queuing SET queue_status = 'Completed' WHERE queuing_id = ?")
                    ->execute([$queuing_id]);

                // Update transaction
                $pdo->prepare("
                    UPDATE transactions SET time_end = NOW(), transaction_status = 'Completed'
                    WHERE queuing_id = ?
                ")->execute([$queuing_id]);

                // Get transaction details for history
                $tdata = $pdo->prepare("SELECT * FROM transactions WHERE queuing_id = ? ORDER BY transaction_id DESC LIMIT 1");
                $tdata->execute([$queuing_id]);
                $trow = $tdata->fetch();

                // Archive to history
                if ($trow) {
                    $wait  = round((strtotime($trow['time_start']) - strtotime($qrow['time_joined'])) / 60);
                    $dur   = round((strtotime($trow['time_end'])   - strtotime($trow['time_start']))  / 60);
                    $pdo->prepare("
                        INSERT INTO history
                        (transaction_id, student_number, service_id, admin_id, counter_id,
                         queue_num, time_joined, time_start, time_end,
                         wait_time_minutes, service_duration_minutes, transaction_status)
                        VALUES (?,?,?,?,?,?,?,?,?,?,?,'Completed')
                    ")->execute([
                        $trow['transaction_id'], $qrow['student_number'], $qrow['service_id'],
                        $admin_id, $counter_id, $qrow['queuing_num'],
                        $qrow['time_joined'], $trow['time_start'], $trow['time_end'],
                        $wait, $dur
                    ]);
                }
                $action_msg = 'Transaction marked as completed and archived.';
            }
        }
    }

    // ── Cancel Queue ──────────────────────────────────────────────
    if ($action === 'cancel_queue') {
        $queuing_id = (int)($_POST['queuing_id'] ?? 0);
        if ($queuing_id) {
            $pdo->prepare("UPDATE queuing SET queue_status = 'Canceled' WHERE queuing_id = ?")
                ->execute([$queuing_id]);
            $action_msg = 'Queue ticket has been cancelled.';
        }
    }

    // Redirect to avoid resubmit
    if ($action_msg || $action_error) {
        $_SESSION['action_msg']   = $action_msg;
        $_SESSION['action_error'] = $action_error;
        header('Location: dashboard.php');
        exit();
    }
}

// Pull flash messages
if (isset($_SESSION['action_msg']))   { $action_msg   = $_SESSION['action_msg'];   unset($_SESSION['action_msg']); }
if (isset($_SESSION['action_error'])) { $action_error = $_SESSION['action_error']; unset($_SESSION['action_error']); }

// ── Fetch waiting queue ────────────────────────────────────────────
$queue_list = $pdo->query("
    SELECT q.*, s.service_name, st.fullname, st.course, st.gmail, st.cor_image, st.contact_number
    FROM queuing q
    JOIN service_types s ON q.service_id = s.service_id
    JOIN students st     ON q.student_number = st.student_number
    WHERE q.queue_status IN ('Pending','Called')
    ORDER BY q.queue_status DESC, q.time_joined ASC
")->fetchAll();

// ── Currently being served ─────────────────────────────────────────
$serving_now = $pdo->query("
    SELECT q.*, s.service_name, st.fullname, st.course, st.cor_image
    FROM queuing q
    JOIN service_types s ON q.service_id = s.service_id
    JOIN students st     ON q.student_number = st.student_number
    WHERE q.queue_status = 'Called'
    ORDER BY q.time_joined DESC LIMIT 1
")->fetch();

// ── Stats ──────────────────────────────────────────────────────────
$total_today     = $pdo->query("SELECT COUNT(*) FROM queuing WHERE DATE(time_joined) = CURDATE()")->fetchColumn();
$total_completed = $pdo->query("SELECT COUNT(*) FROM queuing WHERE queue_status = 'Completed' AND DATE(time_joined) = CURDATE()")->fetchColumn();
$total_waiting   = $pdo->query("SELECT COUNT(*) FROM queuing WHERE queue_status = 'Pending'")->fetchColumn();
$total_canceled  = $pdo->query("SELECT COUNT(*) FROM queuing WHERE queue_status = 'Canceled' AND DATE(time_joined) = CURDATE()")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Admin Dashboard — SMART Q</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700;800&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <style>
    :root {
      --navy:     #0b1f3a;
      --navy-mid: #112b52;
      --navy-card:#0f2444;
      --gold:     #c9a84c;
      --gold-lt:  #e8c97a;
      --blue:     #1a56db;
      --white:    #f8f9fc;
      --gray:     #8a9ab5;
      --border:   rgba(255,255,255,0.08);
      --green:    #34d399;
      --red:      #f87171;
      --yellow:   #fbbf24;
      --sidebar:  220px;
    }
    *, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }

    body {
      min-height:100vh;
      background: linear-gradient(160deg,#071428 0%,#0b1f3a 60%,#071020 100%);
      font-family:'DM Sans',sans-serif;
      color:var(--white);
      display:flex;
    }

    body::before {
      content:''; position:fixed; inset:0; pointer-events:none; z-index:0;
      background-image:
        linear-gradient(rgba(255,255,255,0.018) 1px,transparent 1px),
        linear-gradient(90deg,rgba(255,255,255,0.018) 1px,transparent 1px);
      background-size:44px 44px;
    }

    /* ── SIDEBAR ─────────────────────────── */
    .sidebar {
      position:fixed; top:0; left:0; bottom:0;
      width:var(--sidebar);
      background:rgba(7,16,32,0.95);
      border-right:1px solid var(--border);
      backdrop-filter:blur(20px);
      z-index:50;
      display:flex; flex-direction:column;
      padding:0 0 20px;
    }

    .sb-brand {
      display:flex; align-items:center; gap:10px;
      padding:22px 20px 18px;
      border-bottom:1px solid var(--border);
    }
    .sb-logo {
      width:34px; height:34px; border-radius:8px;
      background:linear-gradient(135deg,var(--gold),var(--gold-lt));
      display:flex; align-items:center; justify-content:center;
      font-family:'Playfair Display',serif;
      font-weight:800; font-size:14px; color:var(--navy);
      flex-shrink:0;
    }
    .sb-title { font-family:'Playfair Display',serif; font-size:14px; font-weight:700; }
    .sb-sub   { font-size:9px; color:var(--gold); letter-spacing:1px; text-transform:uppercase; }

    .sb-section {
      font-size:9px; font-weight:700; text-transform:uppercase;
      letter-spacing:1.5px; color:var(--gray);
      padding:18px 20px 8px;
    }

    .sb-link {
      display:flex; align-items:center; gap:10px;
      padding:10px 20px; margin:1px 8px;
      border-radius:10px;
      font-size:13px; font-weight:500; color:var(--gray);
      text-decoration:none; cursor:pointer;
      transition:all 0.2s;
    }
    .sb-link:hover  { background:rgba(255,255,255,0.05); color:var(--white); }
    .sb-link.active { background:rgba(26,86,219,0.15); color:#93c5fd; border:1px solid rgba(26,86,219,0.2); }
    .sb-link .icon  { font-size:15px; width:20px; text-align:center; }

    .sb-bottom {
      margin-top:auto; padding:16px 12px 0;
      border-top:1px solid var(--border);
    }

    .sb-admin-info {
      display:flex; align-items:center; gap:10px;
      padding:12px; border-radius:10px;
      background:rgba(255,255,255,0.03);
      border:1px solid var(--border);
      margin-bottom:10px;
    }
    .sb-avatar {
      width:32px; height:32px; border-radius:8px;
      background:linear-gradient(135deg,var(--gold),var(--gold-lt));
      display:flex; align-items:center; justify-content:center;
      font-size:13px; font-weight:700; color:var(--navy);
      flex-shrink:0;
    }
    .sb-admin-name { font-size:12px; font-weight:600; line-height:1.3; }
    .sb-admin-role { font-size:10px; color:var(--gray); }

    .btn-logout-sb {
      display:flex; align-items:center; justify-content:center; gap:6px;
      width:100%; padding:9px;
      background:rgba(248,113,113,0.08);
      border:1px solid rgba(248,113,113,0.2);
      border-radius:9px; color:#fca5a5;
      font-family:'DM Sans',sans-serif;
      font-size:12px; font-weight:500;
      cursor:pointer; text-decoration:none;
      transition:all 0.2s;
    }
    .btn-logout-sb:hover { background:rgba(248,113,113,0.15); }

    /* ── MAIN CONTENT ────────────────────── */
    .content {
      margin-left:var(--sidebar);
      flex:1; position:relative; z-index:5;
      padding:28px 30px 60px;
      min-height:100vh;
    }

    /* ── TOP ROW ─────────────────────────── */
    .top-row {
      display:flex; align-items:center; justify-content:space-between;
      margin-bottom:24px; flex-wrap:wrap; gap:12px;
    }
    .page-title {
      font-family:'Playfair Display',serif;
      font-size:24px; font-weight:700;
    }
    .page-sub { font-size:12px; color:var(--gray); margin-top:2px; }

    .top-actions { display:flex; align-items:center; gap:10px; }
    .date-badge {
      display:flex; align-items:center; gap:6px;
      background:rgba(255,255,255,0.04);
      border:1px solid var(--border);
      border-radius:8px; padding:8px 14px;
      font-size:12px; color:var(--gray);
    }

    /* ── STATS GRID ──────────────────────── */
    .stats-grid {
      display:grid;
      grid-template-columns:repeat(4,1fr);
      gap:14px; margin-bottom:24px;
    }
    .stat-card {
      background:rgba(15,36,68,0.8);
      border:1px solid var(--border);
      border-radius:14px; padding:20px;
      display:flex; flex-direction:column;
      gap:8px; position:relative; overflow:hidden;
      transition:border-color 0.2s;
    }
    .stat-card:hover { border-color:rgba(201,168,76,0.2); }
    .stat-card::before {
      content:''; position:absolute;
      top:0; left:0; right:0; height:2px;
    }
    .stat-card.blue::before   { background:linear-gradient(90deg,#1a56db,#3b82f6); }
    .stat-card.gold::before   { background:linear-gradient(90deg,var(--gold),var(--gold-lt)); }
    .stat-card.green::before  { background:linear-gradient(90deg,#10b981,#34d399); }
    .stat-card.red::before    { background:linear-gradient(90deg,#ef4444,#f87171); }

    .stat-icon { font-size:22px; }
    .stat-num {
      font-family:'Playfair Display',serif;
      font-size:32px; font-weight:800; line-height:1;
    }
    .stat-label { font-size:11px; color:var(--gray); font-weight:400; }

    /* ── GRID LAYOUT ─────────────────────── */
    .grid-2 {
      display:grid;
      grid-template-columns:1fr 360px;
      gap:20px; align-items:start;
    }

    /* ── CARD ───────────────────────────── */
    .card {
      background:rgba(15,36,68,0.85);
      border:1px solid var(--border);
      border-radius:16px; overflow:hidden;
    }
    .card-head {
      padding:16px 22px;
      border-bottom:1px solid var(--border);
      display:flex; align-items:center; justify-content:space-between;
    }
    .card-head h3 {
      font-family:'Playfair Display',serif;
      font-size:16px; font-weight:700;
    }
    .card-body { padding:20px 22px; }

    /* ── ALERT ──────────────────────────── */
    .alert {
      display:flex; align-items:center; gap:10px;
      padding:12px 16px; border-radius:10px;
      font-size:13px; margin-bottom:18px;
    }
    .alert.success { background:rgba(52,211,153,0.1); border:1px solid rgba(52,211,153,0.3); color:#6ee7b7; }
    .alert.error   { background:rgba(248,113,113,0.1); border:1px solid rgba(248,113,113,0.3); color:#fca5a5; }

    /* ── CALL NEXT PANEL ─────────────────── */
    .call-panel {
      background:linear-gradient(135deg,rgba(26,61,110,0.5),rgba(17,43,82,0.7));
      border:1px solid rgba(201,168,76,0.2);
      border-radius:16px; padding:22px;
      margin-bottom:20px; position:relative; overflow:hidden;
    }
    .call-panel::before {
      content:''; position:absolute; top:0; left:0; right:0; height:2px;
      background:linear-gradient(90deg,transparent,var(--gold),transparent);
    }
    .call-panel h3 {
      font-family:'Playfair Display',serif;
      font-size:16px; font-weight:700; margin-bottom:16px;
    }

    .call-controls {
      display:flex; gap:10px; flex-wrap:wrap; align-items:flex-end;
    }
    .ctrl-field { display:flex; flex-direction:column; gap:6px; flex:1; min-width:140px; }
    .ctrl-field label { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.7px; color:var(--gray); }
    .ctrl-field select {
      background:rgba(255,255,255,0.05);
      border:1px solid var(--border); border-radius:9px;
      padding:10px 14px; color:var(--white);
      font-family:'DM Sans',sans-serif; font-size:13px;
      outline:none; transition:border-color 0.2s;
    }
    .ctrl-field select:focus { border-color:var(--gold); }
    .ctrl-field select option { background:#0f2444; }

    .btn-call {
      display:flex; align-items:center; gap:8px;
      padding:11px 24px; border:none; border-radius:10px;
      background:linear-gradient(135deg,var(--gold),var(--gold-lt));
      font-family:'DM Sans',sans-serif;
      font-size:13px; font-weight:700; color:var(--navy);
      cursor:pointer; white-space:nowrap;
      box-shadow:0 4px 18px rgba(201,168,76,0.3);
      transition:all 0.2s; flex-shrink:0;
    }
    .btn-call:hover { transform:translateY(-2px); box-shadow:0 8px 28px rgba(201,168,76,0.45); }

    /* ── CURRENTLY SERVING ───────────────── */
    .serving-box {
      background:rgba(52,211,153,0.06);
      border:1px solid rgba(52,211,153,0.2);
      border-radius:14px; padding:18px 22px;
      display:flex; align-items:center; gap:18px;
      margin-bottom:20px; flex-wrap:wrap;
    }
    .serving-num {
      font-family:'Playfair Display',serif;
      font-size:48px; font-weight:800; line-height:1;
      color:var(--green); flex-shrink:0;
    }
    .serving-info { flex:1; }
    .serving-label { font-size:10px; text-transform:uppercase; letter-spacing:1px; color:var(--gray); margin-bottom:4px; }
    .serving-name  { font-size:16px; font-weight:600; margin-bottom:3px; }
    .serving-meta  { font-size:12px; color:var(--gray); }

    .serving-actions { display:flex; gap:8px; flex-wrap:wrap; }

    .btn-complete {
      display:flex; align-items:center; gap:6px;
      padding:9px 18px; border:none; border-radius:9px;
      background:rgba(52,211,153,0.15);
      border:1px solid rgba(52,211,153,0.3);
      color:var(--green); font-family:'DM Sans',sans-serif;
      font-size:12px; font-weight:600; cursor:pointer;
      transition:all 0.2s;
    }
    .btn-complete:hover { background:rgba(52,211,153,0.25); }

    .btn-cancel-q {
      display:flex; align-items:center; gap:6px;
      padding:9px 18px; border-radius:9px;
      background:rgba(248,113,113,0.1);
      border:1px solid rgba(248,113,113,0.25);
      color:#fca5a5; font-family:'DM Sans',sans-serif;
      font-size:12px; font-weight:600; cursor:pointer;
      transition:all 0.2s;
    }
    .btn-cancel-q:hover { background:rgba(248,113,113,0.2); }

    /* ── QUEUE TABLE ─────────────────────── */
    .queue-table { width:100%; border-collapse:collapse; }
    .queue-table th {
      font-size:10px; font-weight:700; text-transform:uppercase;
      letter-spacing:0.8px; color:var(--gray);
      padding:0 14px 12px; text-align:left;
      border-bottom:1px solid var(--border);
    }
    .queue-table td {
      padding:13px 14px; font-size:13px;
      border-bottom:1px solid rgba(255,255,255,0.04);
      vertical-align:middle;
    }
    .queue-table tr:last-child td { border-bottom:none; }
    .queue-table tr:hover td { background:rgba(255,255,255,0.02); }

    /* badges */
    .badge {
      display:inline-flex; align-items:center; gap:5px;
      padding:3px 10px; border-radius:99px;
      font-size:11px; font-weight:600;
    }
    .badge.pending  { background:rgba(251,191,36,0.12); color:var(--yellow); }
    .badge.called   { background:rgba(52,211,153,0.12); color:var(--green); }
    .badge.dot { width:6px; height:6px; border-radius:50%; background:currentColor; }

    .queue-num-badge {
      display:inline-flex; align-items:center; justify-content:center;
      width:38px; height:38px; border-radius:10px;
      background:rgba(26,86,219,0.15);
      border:1px solid rgba(26,86,219,0.25);
      font-family:'Playfair Display',serif;
      font-size:14px; font-weight:700; color:#93c5fd;
    }

    /* student info cell */
    .stu-cell { display:flex; align-items:center; gap:10px; }
    .stu-avatar {
      width:32px; height:32px; border-radius:8px;
      background:linear-gradient(135deg,rgba(26,86,219,0.4),rgba(59,130,246,0.2));
      display:flex; align-items:center; justify-content:center;
      font-size:13px; font-weight:700; flex-shrink:0;
    }
    .stu-name  { font-size:13px; font-weight:500; }
    .stu-snum  { font-size:11px; color:var(--gray); }

    /* COR thumbnail */
    .cor-thumb {
      width:36px; height:36px; border-radius:6px;
      object-fit:cover; border:1px solid var(--border);
      cursor:pointer; transition:transform 0.2s;
    }
    .cor-thumb:hover { transform:scale(1.1); }

    /* table action btns */
    .tbl-btn {
      display:inline-flex; align-items:center; gap:4px;
      padding:5px 10px; border-radius:7px;
      font-family:'DM Sans',sans-serif;
      font-size:11px; font-weight:600; cursor:pointer;
      border:none; transition:all 0.2s;
    }
    .tbl-btn.green { background:rgba(52,211,153,0.12); color:var(--green); border:1px solid rgba(52,211,153,0.2); }
    .tbl-btn.red   { background:rgba(248,113,113,0.1); color:#fca5a5; border:1px solid rgba(248,113,113,0.2); }
    .tbl-btn.green:hover { background:rgba(52,211,153,0.22); }
    .tbl-btn.red:hover   { background:rgba(248,113,113,0.2); }

    .empty-state {
      text-align:center; padding:40px 20px;
      color:var(--gray); font-size:13px;
    }
    .empty-icon { font-size:36px; margin-bottom:12px; }

    /* ── RIGHT PANEL ─────────────────────── */
    .right-col { display:flex; flex-direction:column; gap:16px; }

    /* COR Modal */
    .modal-overlay {
      display:none; position:fixed; inset:0;
      background:rgba(0,0,0,0.8); z-index:200;
      align-items:center; justify-content:center;
      backdrop-filter:blur(4px);
    }
    .modal-overlay.open { display:flex; }
    .modal-box {
      background:var(--navy-card);
      border:1px solid var(--border);
      border-radius:16px; padding:20px;
      max-width:500px; width:90%;
      position:relative;
    }
    .modal-close {
      position:absolute; top:14px; right:14px;
      background:rgba(255,255,255,0.08); border:none;
      width:28px; height:28px; border-radius:50%;
      color:var(--white); font-size:14px; cursor:pointer;
    }
    .modal-box img { width:100%; border-radius:10px; }
    .modal-title {
      font-family:'Playfair Display',serif;
      font-size:15px; font-weight:700; margin-bottom:12px;
    }

    /* refresh */
    .refresh-note {
      text-align:center; font-size:11px; color:var(--gray);
      padding:10px; display:flex; align-items:center; justify-content:center; gap:6px;
    }
    .spin { animation:spin 2s linear infinite; display:inline-block; }
    @keyframes spin { to{transform:rotate(360deg)} }

    /* ── MOBILE ─────────────────────────── */
    @media(max-width:1000px) {
      .grid-2 { grid-template-columns:1fr; }
      .stats-grid { grid-template-columns:repeat(2,1fr); }
    }
    @media(max-width:700px) {
      .sidebar { display:none; }
      .content { margin-left:0; padding:20px 16px; }
      .stats-grid { grid-template-columns:1fr 1fr; }
      .call-controls { flex-direction:column; }
    }
  </style>
</head>
<body>

<!-- ── SIDEBAR ── -->
<aside class="sidebar">
  <div class="sb-brand">
    <div class="sb-logo">Q</div>
    <div>
      <div class="sb-title">SMART Q</div>
      <div class="sb-sub">Admin Panel</div>
    </div>
  </div>

  <div class="sb-section">Main</div>
  <a class="sb-link active" href="dashboard.php">
    <span class="icon">📊</span> Dashboard
  </a>
  <a class="sb-link" href="history.php">
    <span class="icon">📜</span> Transaction History
  </a>

  <div class="sb-section">System</div>
  <a class="sb-link" href="../index.php" target="_blank">
    <span class="icon">🌐</span> Student Portal
  </a>

  <div class="sb-bottom">
    <div class="sb-admin-info">
      <div class="sb-avatar"><?= strtoupper(substr($admin_name, 0, 1)) ?></div>
      <div>
        <div class="sb-admin-name"><?= htmlspecialchars($admin_name) ?></div>
        <div class="sb-admin-role"><?= htmlspecialchars($admin_role) ?></div>
      </div>
    </div>
    <a href="logout.php" class="btn-logout-sb">⬡ &nbsp;Log Out</a>
  </div>
</aside>

<!-- ── MAIN CONTENT ── -->
<div class="content">

  <!-- TOP ROW -->
  <div class="top-row">
    <div>
      <div class="page-title">Queue Dashboard</div>
      <div class="page-sub">Manage student queues and transactions</div>
    </div>
    <div class="top-actions">
      <div class="date-badge">
        📅 &nbsp;<span id="liveClock"></span>
      </div>
    </div>
  </div>

  <!-- ALERTS -->
  <?php if (!empty($action_msg)): ?>
  <div class="alert success">✅ &nbsp;<?= htmlspecialchars($action_msg) ?></div>
  <?php endif; ?>
  <?php if (!empty($action_error)): ?>
  <div class="alert error">⚠️ &nbsp;<?= htmlspecialchars($action_error) ?></div>
  <?php endif; ?>

  <!-- STATS -->
  <div class="stats-grid">
    <div class="stat-card blue">
      <div class="stat-icon">📋</div>
      <div class="stat-num"><?= $total_today ?></div>
      <div class="stat-label">Total Today</div>
    </div>
    <div class="stat-card yellow" style="--c:var(--yellow);">
      <div class="stat-icon">⏳</div>
      <div class="stat-num"><?= $total_waiting ?></div>
      <div class="stat-label">Currently Waiting</div>
    </div>
    <div class="stat-card green">
      <div class="stat-icon">✅</div>
      <div class="stat-num"><?= $total_completed ?></div>
      <div class="stat-label">Completed Today</div>
    </div>
    <div class="stat-card red">
      <div class="stat-icon">✕</div>
      <div class="stat-num"><?= $total_canceled ?></div>
      <div class="stat-label">Cancelled Today</div>
    </div>
  </div>

  <!-- MAIN GRID -->
  <div class="grid-2">

    <!-- LEFT: QUEUE MANAGEMENT -->
    <div>

      <!-- CALL NEXT PANEL -->
      <div class="call-panel">
        <h3>🔔 &nbsp;Call Next Student</h3>
        <form method="POST">
          <input type="hidden" name="action" value="call_next"/>
          <div class="call-controls">
            <div class="ctrl-field">
              <label>Counter</label>
              <select name="counter_id">
                <?php foreach($counters as $c): ?>
                <option value="<?= $c['counter_id'] ?>"><?= htmlspecialchars($c['counter_name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="ctrl-field">
              <label>Filter by Service</label>
              <select name="service_id">
                <option value="0">All Services</option>
                <?php foreach($services as $s): ?>
                <option value="<?= $s['service_id'] ?>"><?= htmlspecialchars($s['service_name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <button type="submit" class="btn-call">
              📣 &nbsp;Call Next
            </button>
          </div>
        </form>
      </div>

      <!-- CURRENTLY SERVING -->
      <?php if ($serving_now): ?>
      <div class="serving-box">
        <div class="serving-num"><?= str_pad($serving_now['queuing_num'],4,'0',STR_PAD_LEFT) ?></div>
        <div class="serving-info">
          <div class="serving-label">Now Serving</div>
          <div class="serving-name"><?= htmlspecialchars($serving_now['fullname']) ?></div>
          <div class="serving-meta"><?= htmlspecialchars($serving_now['course']) ?> &nbsp;·&nbsp; <?= htmlspecialchars($serving_now['service_name']) ?></div>
        </div>
        <div class="serving-actions">
          <form method="POST" style="display:inline;">
            <input type="hidden" name="action" value="complete"/>
            <input type="hidden" name="queuing_id" value="<?= $serving_now['queuing_id'] ?>"/>
            <button type="submit" class="btn-complete">✓ &nbsp;Mark Complete</button>
          </form>
          <form method="POST" style="display:inline;">
            <input type="hidden" name="action" value="cancel_queue"/>
            <input type="hidden" name="queuing_id" value="<?= $serving_now['queuing_id'] ?>"/>
            <button type="submit" class="btn-cancel-q" onclick="return confirm('Cancel this student?')">✕ &nbsp;Cancel</button>
          </form>
        </div>
      </div>
      <?php endif; ?>

      <!-- QUEUE TABLE -->
      <div class="card">
        <div class="card-head">
          <h3>Waiting Queue</h3>
          <span style="font-size:12px;color:var(--gray);"><?= count($queue_list) ?> in queue</span>
        </div>
        <div class="card-body" style="padding:0;">
          <?php if (empty($queue_list)): ?>
          <div class="empty-state">
            <div class="empty-icon">🎉</div>
            No students currently in queue. All clear!
          </div>
          <?php else: ?>
          <table class="queue-table">
            <thead>
              <tr>
                <th>#</th>
                <th>Student</th>
                <th>Service</th>
                <th>Waited</th>
                <th>COR</th>
                <th>Status</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach($queue_list as $q): 
                $waited = round((time() - strtotime($q['time_joined'])) / 60);
              ?>
              <tr>
                <td>
                  <div class="queue-num-badge"><?= str_pad($q['queuing_num'],4,'0',STR_PAD_LEFT) ?></div>
                </td>
                <td>
                  <div class="stu-cell">
                    <div class="stu-avatar"><?= strtoupper(substr($q['fullname'],0,1)) ?></div>
                    <div>
                      <div class="stu-name"><?= htmlspecialchars($q['fullname']) ?></div>
                      <div class="stu-snum"><?= htmlspecialchars($q['student_number']) ?></div>
                    </div>
                  </div>
                </td>
                <td style="color:var(--gray);font-size:12px;"><?= htmlspecialchars($q['service_name']) ?></td>
                <td style="color:var(--gray);font-size:12px;"><?= $waited ?>m</td>
                <td>
                  <?php if (!empty($q['cor_image'])): ?>
                  <img src="../assets/uploads/<?= htmlspecialchars($q['cor_image']) ?>"
                       class="cor-thumb" onclick="openCOR(this.src,'<?= htmlspecialchars($q['fullname']) ?>')" alt="COR"/>
                  <?php else: ?>
                  <span style="color:var(--gray);font-size:11px;">—</span>
                  <?php endif; ?>
                </td>
                <td>
                  <span class="badge <?= strtolower($q['queue_status']) ?>">
                    <span class="dot"></span>
                    <?= $q['queue_status'] ?>
                  </span>
                </td>
                <td>
                  <?php if ($q['queue_status'] === 'Called'): ?>
                  <form method="POST" style="display:inline;">
                    <input type="hidden" name="action" value="complete"/>
                    <input type="hidden" name="queuing_id" value="<?= $q['queuing_id'] ?>"/>
                    <button class="tbl-btn green" type="submit">✓ Done</button>
                  </form>
                  <?php endif; ?>
                  <form method="POST" style="display:inline;">
                    <input type="hidden" name="action" value="cancel_queue"/>
                    <input type="hidden" name="queuing_id" value="<?= $q['queuing_id'] ?>"/>
                    <button class="tbl-btn red" type="submit"
                      onclick="return confirm('Cancel this queue ticket?')">✕</button>
                  </form>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
          <?php endif; ?>
        </div>
      </div>

      <div class="refresh-note">
        <span class="spin">↻</span>
        <span id="refreshCountdown">Auto-refreshing in 20s</span>
      </div>

    </div><!-- /left -->

    <!-- RIGHT COLUMN -->
    <div class="right-col">

      <!-- SERVICE BREAKDOWN -->
      <div class="card">
        <div class="card-head">
          <h3>By Service</h3>
        </div>
        <div class="card-body">
          <?php foreach($services as $svc):
            $cnt = $pdo->prepare("SELECT COUNT(*) FROM queuing WHERE service_id=? AND queue_status='Pending'");
            $cnt->execute([$svc['service_id']]);
            $n = (int)$cnt->fetchColumn();
            $pct = $total_waiting > 0 ? round(($n / $total_waiting) * 100) : 0;
          ?>
          <div style="margin-bottom:14px;">
            <div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:5px;">
              <span><?= htmlspecialchars($svc['service_name']) ?></span>
              <span style="color:var(--gold-lt);font-weight:600;"><?= $n ?></span>
            </div>
            <div style="height:5px;background:rgba(255,255,255,0.06);border-radius:99px;overflow:hidden;">
              <div style="height:100%;width:<?= $pct ?>%;background:linear-gradient(90deg,var(--blue),var(--gold));border-radius:99px;transition:width 0.5s;"></div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- COUNTERS STATUS -->
      <div class="card">
        <div class="card-head"><h3>Counters</h3></div>
        <div class="card-body">
          <?php foreach($counters as $c): ?>
          <div style="display:flex;align-items:center;justify-content:space-between;padding:10px 0;border-bottom:1px solid rgba(255,255,255,0.04);">
            <div>
              <div style="font-size:13px;font-weight:600;"><?= htmlspecialchars($c['counter_name']) ?></div>
              <div style="font-size:11px;color:var(--gray);"><?= htmlspecialchars($c['description']) ?></div>
            </div>
            <span class="badge called"><span class="dot"></span> Open</span>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- QUICK STATS TODAY -->
      <div class="card">
        <div class="card-head"><h3>Today at a Glance</h3></div>
        <div class="card-body">
          <?php
          $avg = $pdo->query("SELECT AVG(wait_time_minutes) FROM history WHERE DATE(logged_at)=CURDATE()")->fetchColumn();
          $avg = $avg ? round($avg) : 0;
          ?>
          <div style="display:flex;flex-direction:column;gap:10px;">
            <div style="display:flex;justify-content:space-between;font-size:13px;padding:8px 0;border-bottom:1px solid rgba(255,255,255,0.04);">
              <span style="color:var(--gray);">Avg. Wait Time</span>
              <span style="font-weight:600;color:var(--gold-lt);">~<?= $avg ?> min</span>
            </div>
            <div style="display:flex;justify-content:space-between;font-size:13px;padding:8px 0;border-bottom:1px solid rgba(255,255,255,0.04);">
              <span style="color:var(--gray);">Completion Rate</span>
              <span style="font-weight:600;color:var(--green);">
                <?= $total_today > 0 ? round(($total_completed/$total_today)*100) : 0 ?>%
              </span>
            </div>
            <div style="display:flex;justify-content:space-between;font-size:13px;padding:8px 0;">
              <span style="color:var(--gray);">Logged In As</span>
              <span style="font-weight:600;"><?= htmlspecialchars($admin_name) ?></span>
            </div>
          </div>
        </div>
      </div>

    </div><!-- /right -->
  </div><!-- /grid-2 -->
</div><!-- /content -->

<!-- COR MODAL -->
<div class="modal-overlay" id="corModal">
  <div class="modal-box">
    <button class="modal-close" onclick="closeCOR()">✕</button>
    <div class="modal-title" id="corModalTitle">Certificate of Registration</div>
    <img id="corModalImg" src="" alt="COR"/>
  </div>
</div>

<script>
  // Live clock
  function updateClock() {
    const now = new Date();
    document.getElementById('liveClock').textContent =
      now.toLocaleDateString('en-PH', {weekday:'short',year:'numeric',month:'short',day:'numeric'}) +
      ' · ' + now.toLocaleTimeString('en-PH', {hour:'2-digit',minute:'2-digit',second:'2-digit'});
  }
  updateClock(); setInterval(updateClock, 1000);

  // Auto-refresh countdown
  let secs = 20;
  const cd = document.getElementById('refreshCountdown');
  setInterval(() => {
    secs--;
    if (secs <= 0) location.reload();
    if (cd) cd.textContent = `Auto-refreshing in ${secs}s`;
  }, 1000);

  // COR modal
  function openCOR(src, name) {
    document.getElementById('corModalImg').src = src;
    document.getElementById('corModalTitle').textContent = name + ' — Certificate of Registration';
    document.getElementById('corModal').classList.add('open');
  }
  function closeCOR() {
    document.getElementById('corModal').classList.remove('open');
  }
  document.getElementById('corModal').addEventListener('click', function(e) {
    if (e.target === this) closeCOR();
  });
</script>
</body>
</html>