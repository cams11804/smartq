<?php
session_start();

// Must be logged in
if (!isset($_SESSION['student_number'])) {
    header('Location: login.php');
    exit();
}

require_once 'config/db.php';

$student_number = $_SESSION['student_number'];
$fullname       = $_SESSION['fullname'];
$course         = $_SESSION['course'];

// ── Fetch student info ─────────────────────────────────────────────
$stmt = $pdo->prepare('SELECT * FROM students WHERE student_number = ?');
$stmt->execute([$student_number]);
$student = $stmt->fetch();

// ── Fetch active queue ticket ──────────────────────────────────────
$qstmt = $pdo->prepare("
    SELECT q.*, s.service_name
    FROM queuing q
    JOIN service_types s ON q.service_id = s.service_id
    WHERE q.student_number = ?
    AND q.queue_status IN ('Pending','Called')
    ORDER BY q.time_joined DESC
    LIMIT 1
");
$qstmt->execute([$student_number]);
$active_queue = $qstmt->fetch();

// ── Fetch service types ────────────────────────────────────────────
$services = $pdo->query('SELECT * FROM service_types ORDER BY service_id')->fetchAll();

// ── Fetch current serving number ──────────────────────────────────
$serving = $pdo->query("
    SELECT q.queuing_num, s.service_name
    FROM queuing q
    JOIN service_types s ON q.service_id = s.service_id
    WHERE q.queue_status = 'Called'
    ORDER BY q.time_joined DESC
    LIMIT 1
")->fetch();

// ── Count people ahead ─────────────────────────────────────────────
$ahead = 0;
if ($active_queue) {
    $astmt = $pdo->prepare("
        SELECT COUNT(*) FROM queuing
        WHERE queue_status = 'Pending'
        AND time_joined < ?
    ");
    $astmt->execute([$active_queue['time_joined']]);
    $ahead = (int)$astmt->fetchColumn();
}

// ── Total in queue right now ───────────────────────────────────────
$total_waiting = $pdo->query("SELECT COUNT(*) FROM queuing WHERE queue_status = 'Pending'")->fetchColumn();

// ── Transaction history (last 5) ──────────────────────────────────
$hist = $pdo->prepare("
    SELECT h.*, s.service_name
    FROM history h
    LEFT JOIN service_types s ON h.service_id = s.service_id
    WHERE h.student_number = ?
    ORDER BY h.logged_at DESC
    LIMIT 5
");
$hist->execute([$student_number]);
$history = $hist->fetchAll();

// ── Handle JOIN queue ──────────────────────────────────────────────
$join_error   = '';
$join_success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'join') {
    if ($active_queue) {
        $join_error = 'You already have an active queue ticket.';
    } else {
        $service_id = (int)($_POST['service_id'] ?? 0);
        if (!$service_id) {
            $join_error = 'Please select a transaction type.';
        } else {
            // Generate next queue number for today
            $nstmt = $pdo->query("
                SELECT MAX(queuing_num) FROM queuing
                WHERE DATE(time_joined) = CURDATE()
            ");
            $last_num   = (int)$nstmt->fetchColumn();
            $new_num    = $last_num > 0 ? $last_num + 1 : 1001;
            $est_wait   = $ahead * 5; // 5 mins per person estimate

            $ins = $pdo->prepare("
                INSERT INTO queuing (student_number, service_id, queuing_num, estimated_wait_time, queue_status, notification_status)
                VALUES (?, ?, ?, ?, 'Pending', 'Pending')
            ");
            $ins->execute([$student_number, $service_id, $new_num, $est_wait]);
            $join_success = 'Queue ticket generated successfully!';

            // Refresh active queue
            $qstmt->execute([$student_number]);
            $active_queue = $qstmt->fetch();
        }
    }
}

// ── Handle CANCEL queue ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel') {
    if ($active_queue) {
        $cancel = $pdo->prepare("UPDATE queuing SET queue_status = 'Canceled' WHERE queuing_id = ?");
        $cancel->execute([$active_queue['queuing_id']]);
        header('Location: dashboard.php');
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Dashboard — SMART Q</title>
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
    }
    *, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }

    body {
      min-height: 100vh;
      background: linear-gradient(160deg, #071428 0%, #0b1f3a 60%, #071020 100%);
      font-family: 'DM Sans', sans-serif;
      color: var(--white);
    }

    body::before {
      content:''; position:fixed; inset:0; pointer-events:none; z-index:0;
      background-image:
        linear-gradient(rgba(255,255,255,0.018) 1px, transparent 1px),
        linear-gradient(90deg, rgba(255,255,255,0.018) 1px, transparent 1px);
      background-size: 44px 44px;
    }

    /* ── TOPBAR ─────────────────────────── */
    .topbar {
      position: sticky; top:0; z-index: 100;
      display: flex; align-items: center; justify-content: space-between;
      padding: 14px 40px;
      background: rgba(11,31,58,0.85);
      border-bottom: 1px solid var(--border);
      backdrop-filter: blur(16px);
    }

    .topbar-brand {
      display: flex; align-items: center; gap: 12px;
      text-decoration: none;
    }
    .tb-logo {
      width: 36px; height: 36px; border-radius: 8px;
      background: linear-gradient(135deg, var(--gold), var(--gold-lt));
      display: flex; align-items: center; justify-content: center;
      font-family: 'Playfair Display', serif;
      font-weight: 800; font-size: 15px; color: var(--navy);
    }
    .tb-name {
      font-family: 'Playfair Display', serif;
      font-size: 15px; font-weight: 700;
    }

    .topbar-right {
      display: flex; align-items: center; gap: 16px;
    }

    .user-pill {
      display: flex; align-items: center; gap: 10px;
      background: rgba(255,255,255,0.04);
      border: 1px solid var(--border);
      border-radius: 99px;
      padding: 6px 16px 6px 8px;
    }
    .user-avatar {
      width: 30px; height: 30px; border-radius: 50%;
      background: linear-gradient(135deg, var(--blue), #2563eb);
      display: flex; align-items: center; justify-content: center;
      font-size: 12px; font-weight: 700;
    }
    .user-info { display: flex; flex-direction: column; }
    .user-name { font-size: 12px; font-weight: 600; line-height: 1.2; }
    .user-course { font-size: 10px; color: var(--gray); }

    .btn-logout {
      display: flex; align-items: center; gap: 6px;
      padding: 8px 16px; border-radius: 8px;
      background: rgba(248,113,113,0.1);
      border: 1px solid rgba(248,113,113,0.2);
      color: #fca5a5; font-size: 12px; font-weight: 500;
      text-decoration: none; cursor: pointer;
      font-family: 'DM Sans', sans-serif;
      transition: all 0.2s;
    }
    .btn-logout:hover { background: rgba(248,113,113,0.2); }

    /* ── LAYOUT ─────────────────────────── */
    .page {
      position: relative; z-index: 5;
      max-width: 1100px; margin: 0 auto;
      padding: 36px 24px 60px;
      display: grid;
      grid-template-columns: 1fr 340px;
      gap: 24px;
    }

    /* ── SECTION LABEL ──────────────────── */
    .sec-label {
      font-size: 10px; font-weight: 700;
      text-transform: uppercase; letter-spacing: 1.5px;
      color: var(--gold); margin-bottom: 12px;
    }

    /* ── CARD ───────────────────────────── */
    .card {
      background: rgba(15,36,68,0.85);
      border: 1px solid var(--border);
      border-radius: 18px;
      backdrop-filter: blur(14px);
      overflow: hidden;
    }
    .card-head {
      padding: 20px 26px 16px;
      border-bottom: 1px solid var(--border);
      display: flex; align-items: center; justify-content: space-between;
    }
    .card-head h2 {
      font-family: 'Playfair Display', serif;
      font-size: 17px; font-weight: 700;
    }
    .card-body { padding: 24px 26px; }

    /* ── ALERT ──────────────────────────── */
    .alert {
      display: flex; align-items: flex-start; gap: 10px;
      padding: 12px 16px; border-radius: 10px;
      font-size: 13px; margin-bottom: 18px;
    }
    .alert.success { background:rgba(52,211,153,0.1); border:1px solid rgba(52,211,153,0.3); color:#6ee7b7; }
    .alert.error   { background:rgba(248,113,113,0.1); border:1px solid rgba(248,113,113,0.3); color:#fca5a5; }
    .alert.info    { background:rgba(26,86,219,0.1);   border:1px solid rgba(26,86,219,0.3);   color:#93c5fd; }

    /* ── QUEUE TICKET ───────────────────── */
    .ticket {
      background: linear-gradient(135deg, rgba(26,61,110,0.6), rgba(17,43,82,0.8));
      border: 1px solid rgba(201,168,76,0.25);
      border-radius: 18px;
      padding: 30px;
      text-align: center;
      position: relative; overflow: hidden;
      margin-bottom: 20px;
    }
    .ticket::before {
      content: '';
      position: absolute; top:0; left:0; right:0; height:3px;
      background: linear-gradient(90deg, var(--gold), var(--gold-lt), var(--gold));
    }
    .ticket-label {
      font-size: 10px; font-weight: 700; text-transform: uppercase;
      letter-spacing: 2px; color: var(--gold); margin-bottom: 10px;
    }
    .ticket-num {
      font-family: 'Playfair Display', serif;
      font-size: 72px; font-weight: 800; line-height: 1;
      background: linear-gradient(135deg, var(--gold), var(--gold-lt));
      -webkit-background-clip: text; -webkit-text-fill-color: transparent;
      background-clip: text;
      margin-bottom: 8px;
    }
    .ticket-service {
      font-size: 13px; color: var(--gray); margin-bottom: 20px;
    }

    /* status badge */
    .status-badge {
      display: inline-flex; align-items: center; gap: 7px;
      padding: 6px 16px; border-radius: 99px;
      font-size: 12px; font-weight: 600;
    }
    .status-badge .dot { width:8px; height:8px; border-radius:50%; }
    .status-badge.pending { background:rgba(251,191,36,0.12); border:1px solid rgba(251,191,36,0.3); color:var(--yellow); }
    .status-badge.pending .dot { background:var(--yellow); animation: blink 1.5s infinite; }
    .status-badge.called  { background:rgba(52,211,153,0.12); border:1px solid rgba(52,211,153,0.3); color:var(--green); }
    .status-badge.called  .dot { background:var(--green); animation: blink 1s infinite; }
    @keyframes blink { 0%,100%{opacity:1} 50%{opacity:0.3} }

    /* ticket stats */
    .ticket-stats {
      display: grid; grid-template-columns: 1fr 1fr 1fr;
      gap: 10px; margin-top: 20px;
    }
    .ts-item {
      background: rgba(255,255,255,0.04);
      border: 1px solid var(--border);
      border-radius: 10px; padding: 12px 8px; text-align: center;
    }
    .ts-num { font-size: 20px; font-weight: 700; color: var(--white); }
    .ts-label { font-size: 10px; color: var(--gray); margin-top: 3px; }

    /* cancel btn */
    .btn-cancel {
      width: 100%; padding: 11px;
      background: rgba(248,113,113,0.08);
      border: 1px solid rgba(248,113,113,0.25);
      border-radius: 10px; color: #fca5a5;
      font-family: 'DM Sans', sans-serif;
      font-size: 13px; font-weight: 500;
      cursor: pointer; margin-top: 16px;
      transition: all 0.2s;
    }
    .btn-cancel:hover { background: rgba(248,113,113,0.15); }

    /* ── NOW SERVING ────────────────────── */
    .now-serving {
      background: rgba(26,86,219,0.08);
      border: 1px solid rgba(26,86,219,0.2);
      border-radius: 14px; padding: 20px;
      display: flex; align-items: center; justify-content: space-between;
      margin-bottom: 20px;
    }
    .ns-label { font-size: 11px; color: var(--gray); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 4px; }
    .ns-num {
      font-family: 'Playfair Display', serif;
      font-size: 36px; font-weight: 800; color: #93c5fd;
    }
    .ns-right { text-align: right; }
    .ns-service { font-size: 12px; color: var(--gray); }
    .ns-waiting { font-size: 13px; font-weight: 600; color: var(--white); }

    /* ── JOIN FORM ───────────────────────── */
    .join-form { margin-bottom: 0; }

    .service-grid {
      display: grid; grid-template-columns: 1fr 1fr;
      gap: 10px; margin-bottom: 18px;
    }
    .service-opt { display: none; }
    .service-opt + label {
      display: flex; flex-direction: column; align-items: center;
      gap: 8px; padding: 16px 10px;
      background: rgba(255,255,255,0.03);
      border: 1.5px solid var(--border);
      border-radius: 12px; cursor: pointer;
      font-size: 12px; font-weight: 500; text-align: center;
      transition: all 0.2s;
    }
    .service-opt + label .svc-icon { font-size: 22px; }
    .service-opt:checked + label {
      border-color: var(--blue);
      background: rgba(26,86,219,0.1);
      color: #93c5fd;
    }
    .service-opt + label:hover {
      border-color: rgba(26,86,219,0.4);
      background: rgba(26,86,219,0.05);
    }

    .btn-join {
      width: 100%; padding: 14px;
      background: linear-gradient(135deg, #1a56db, #2563eb);
      border: none; border-radius: 12px;
      font-family: 'DM Sans', sans-serif;
      font-size: 14px; font-weight: 600; color: white;
      cursor: pointer;
      box-shadow: 0 6px 24px rgba(26,86,219,0.35);
      transition: all 0.25s;
      display: flex; align-items: center; justify-content: center; gap: 8px;
    }
    .btn-join:hover { transform: translateY(-2px); box-shadow: 0 10px 32px rgba(26,86,219,0.5); }

    /* ── HISTORY TABLE ──────────────────── */
    .hist-table { width: 100%; border-collapse: collapse; }
    .hist-table th {
      font-size: 10px; font-weight: 700; text-transform: uppercase;
      letter-spacing: 0.8px; color: var(--gray);
      padding: 0 12px 12px; text-align: left;
      border-bottom: 1px solid var(--border);
    }
    .hist-table td {
      padding: 13px 12px; font-size: 13px;
      border-bottom: 1px solid rgba(255,255,255,0.04);
      vertical-align: middle;
    }
    .hist-table tr:last-child td { border-bottom: none; }
    .hist-table tr:hover td { background: rgba(255,255,255,0.02); }

    .badge {
      display: inline-flex; align-items: center;
      padding: 3px 10px; border-radius: 99px;
      font-size: 11px; font-weight: 600;
    }
    .badge.done     { background:rgba(52,211,153,0.12); color:var(--green); }
    .badge.canceled { background:rgba(248,113,113,0.12); color:var(--red); }
    .badge.pending  { background:rgba(251,191,36,0.12);  color:var(--yellow); }

    .empty-state {
      text-align: center; padding: 30px 20px;
      color: var(--gray); font-size: 13px;
    }
    .empty-state .empty-icon { font-size: 32px; margin-bottom: 10px; }

    /* ── RIGHT SIDEBAR ──────────────────── */
    .sidebar { display: flex; flex-direction: column; gap: 20px; }

    /* profile card */
    .profile-card {
      background: rgba(15,36,68,0.85);
      border: 1px solid var(--border);
      border-radius: 18px; padding: 24px;
      text-align: center;
    }
    .profile-avatar {
      width: 64px; height: 64px; border-radius: 50%;
      background: linear-gradient(135deg, var(--blue), #3b82f6);
      display: flex; align-items: center; justify-content: center;
      font-family: 'Playfair Display', serif;
      font-size: 24px; font-weight: 700;
      margin: 0 auto 14px;
      box-shadow: 0 6px 24px rgba(26,86,219,0.35);
    }
    .profile-name {
      font-family: 'Playfair Display', serif;
      font-size: 16px; font-weight: 700; margin-bottom: 4px;
    }
    .profile-snum { font-size: 12px; color: var(--gold-lt); margin-bottom: 4px; }
    .profile-course { font-size: 11px; color: var(--gray); }

    .profile-divider { height:1px; background:var(--border); margin:16px 0; }

    .profile-detail {
      display: flex; justify-content: space-between; align-items: center;
      padding: 6px 0; font-size: 12px;
    }
    .profile-detail span:first-child { color: var(--gray); }
    .profile-detail span:last-child  { font-weight: 500; text-align: right; max-width: 160px; word-break: break-word; }

    /* info card */
    .info-card {
      background: rgba(15,36,68,0.85);
      border: 1px solid var(--border);
      border-radius: 18px; padding: 20px 24px;
    }
    .info-row {
      display: flex; justify-content: space-between; align-items: center;
      padding: 10px 0; border-bottom: 1px solid rgba(255,255,255,0.04);
      font-size: 13px;
    }
    .info-row:last-child { border-bottom: none; }
    .info-row .ir-label { color: var(--gray); font-size: 12px; }
    .info-row .ir-val   { font-weight: 600; }
    .ir-green { color: var(--green); }
    .ir-yellow{ color: var(--yellow); }

    /* ── REFRESH INDICATOR ──────────────── */
    .refresh-bar {
      display: flex; align-items: center; justify-content: center; gap: 8px;
      padding: 8px; font-size: 11px; color: var(--gray);
      border-top: 1px solid var(--border); margin-top: 16px;
    }
    .spin-icon { animation: spin 2s linear infinite; display: inline-block; }
    @keyframes spin { to { transform: rotate(360deg); } }

    /* ── MOBILE ─────────────────────────── */
    @media(max-width:900px) {
      .page { grid-template-columns:1fr; }
      .topbar { padding:14px 20px; }
      .user-info { display:none; }
    }
    @media(max-width:500px) {
      .ticket-num { font-size:56px; }
      .service-grid { grid-template-columns:1fr; }
    }
  </style>
</head>
<body>

<!-- TOPBAR -->
<div class="topbar">
  <a href="index.php" class="topbar-brand">
    <div class="tb-logo">Q</div>
    <span class="tb-name">SMART Q</span>
  </a>
  <div class="topbar-right">
    <div class="user-pill">
      <div class="user-avatar"><?= strtoupper(substr($fullname, 0, 1)) ?></div>
      <div class="user-info">
        <span class="user-name"><?= htmlspecialchars(explode(',', $fullname)[0]) ?></span>
        <span class="user-course"><?= htmlspecialchars($course) ?></span>
      </div>
    </div>
    <a href="logout.php" class="btn-logout">⬡ Log Out</a>
  </div>
</div>

<!-- PAGE -->
<div class="page">

  <!-- ══ LEFT MAIN COLUMN ══ -->
  <div class="main-col">

    <?php if (!empty($join_success)): ?>
    <div class="alert success">✅ &nbsp;<?= htmlspecialchars($join_success) ?></div>
    <?php endif; ?>
    <?php if (!empty($join_error)): ?>
    <div class="alert error">⚠️ &nbsp;<?= htmlspecialchars($join_error) ?></div>
    <?php endif; ?>

    <!-- NOW SERVING -->
    <div class="sec-label">Live Status</div>
    <div class="now-serving">
      <div>
        <div class="ns-label">Now Serving</div>
        <div class="ns-num"><?= $serving ? str_pad($serving['queuing_num'], 4, '0', STR_PAD_LEFT) : '----' ?></div>
        <div class="ns-service"><?= $serving ? htmlspecialchars($serving['service_name']) : 'No active service' ?></div>
      </div>
      <div class="ns-right">
        <div class="ns-label">In Queue</div>
        <div class="ns-waiting"><?= $total_waiting ?> waiting</div>
      </div>
    </div>

    <!-- ACTIVE TICKET -->
    <div class="sec-label" style="margin-top:24px;">Your Queue Ticket</div>

    <?php if ($active_queue): ?>
    <!-- HAS ACTIVE TICKET -->
    <div class="ticket">
      <div class="ticket-label">Queue Number</div>
      <div class="ticket-num"><?= str_pad($active_queue['queuing_num'], 4, '0', STR_PAD_LEFT) ?></div>
      <div class="ticket-service"><?= htmlspecialchars($active_queue['service_name']) ?></div>

      <?php if ($active_queue['queue_status'] === 'Called'): ?>
      <div class="alert info" style="margin:0 0 10px; text-align:left;">
        🔔 &nbsp;<strong>It's your turn!</strong> Please proceed to the counter now.
      </div>
      <?php endif; ?>

      <div class="status-badge <?= strtolower($active_queue['queue_status']) ?>">
        <div class="dot"></div>
        <?= $active_queue['queue_status'] === 'Called' ? '🔔 You are being called!' : '⏳ Waiting in Queue' ?>
      </div>

      <div class="ticket-stats">
        <div class="ts-item">
          <div class="ts-num"><?= $ahead ?></div>
          <div class="ts-label">Ahead of You</div>
        </div>
        <div class="ts-item">
          <div class="ts-num">~<?= max(1, $ahead * 5) ?></div>
          <div class="ts-label">Est. Wait (min)</div>
        </div>
        <div class="ts-item">
          <div class="ts-num"><?= date('h:i A', strtotime($active_queue['time_joined'])) ?></div>
          <div class="ts-label">Joined At</div>
        </div>
      </div>

      <?php if ($active_queue['queue_status'] === 'Pending'): ?>
      <form method="POST">
        <input type="hidden" name="action" value="cancel"/>
        <button type="submit" class="btn-cancel"
          onclick="return confirm('Are you sure you want to cancel your queue ticket?')">
          ✕ Cancel My Queue Ticket
        </button>
      </form>
      <?php endif; ?>
    </div>

    <div class="refresh-bar">
      <span class="spin-icon">↻</span>
      Page auto-refreshes every 30 seconds
    </div>

    <?php else: ?>
    <!-- NO ACTIVE TICKET — SHOW JOIN FORM -->
    <div class="card">
      <div class="card-head">
        <h2>Join the Queue</h2>
        <span style="font-size:12px; color:var(--gray);"><?= $total_waiting ?> currently waiting</span>
      </div>
      <div class="card-body">
        <p style="font-size:13px; color:var(--gray); margin-bottom:20px; line-height:1.6;">
          Select your transaction type below and get your virtual queue number instantly.
        </p>

        <form method="POST" class="join-form">
          <input type="hidden" name="action" value="join"/>

          <div class="service-grid">
            <?php
            $icons = ['📋','🎓','📄','🪪','📊','🔖'];
            foreach ($services as $i => $svc):
            ?>
            <input type="radio" name="service_id" id="svc<?= $svc['service_id'] ?>"
              value="<?= $svc['service_id'] ?>" class="service-opt" required/>
            <label for="svc<?= $svc['service_id'] ?>">
              <span class="svc-icon"><?= $icons[$i % count($icons)] ?></span>
              <?= htmlspecialchars($svc['service_name']) ?>
            </label>
            <?php endforeach; ?>
          </div>

          <button type="submit" class="btn-join">
            🎫 &nbsp;Get My Queue Number
          </button>
        </form>
      </div>
    </div>
    <?php endif; ?>

    <!-- HISTORY -->
    <div class="sec-label" style="margin-top:28px;">Transaction History</div>
    <div class="card">
      <div class="card-head">
        <h2>Recent Visits</h2>
        <span style="font-size:12px; color:var(--gray);">Last 5 transactions</span>
      </div>
      <div class="card-body" style="padding:0;">
        <?php if (empty($history)): ?>
        <div class="empty-state">
          <div class="empty-icon">📭</div>
          No transactions yet. Join a queue to get started!
        </div>
        <?php else: ?>
        <table class="hist-table">
          <thead>
            <tr>
              <th>Queue #</th>
              <th>Service</th>
              <th>Date</th>
              <th>Wait</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($history as $h): ?>
            <tr>
              <td><strong><?= str_pad($h['queue_num'], 4, '0', STR_PAD_LEFT) ?></strong></td>
              <td><?= htmlspecialchars($h['service_name'] ?? 'N/A') ?></td>
              <td style="color:var(--gray)"><?= date('M d, Y', strtotime($h['logged_at'])) ?></td>
              <td style="color:var(--gray)"><?= $h['wait_time_minutes'] ?? '—' ?> min</td>
              <td>
                <span class="badge <?= strtolower($h['transaction_status'] ?? 'pending') ?>">
                  <?= htmlspecialchars($h['transaction_status'] ?? 'Pending') ?>
                </span>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <?php endif; ?>
      </div>
    </div>

  </div><!-- /main-col -->

  <!-- ══ RIGHT SIDEBAR ══ -->
  <div class="sidebar">

    <!-- PROFILE CARD -->
    <div class="profile-card">
      <div class="profile-avatar"><?= strtoupper(substr($fullname, 0, 1)) ?></div>
      <div class="profile-name"><?= htmlspecialchars($fullname) ?></div>
      <div class="profile-snum"><?= htmlspecialchars($student_number) ?></div>
      <div class="profile-course"><?= htmlspecialchars($course) ?></div>

      <div class="profile-divider"></div>

      <div class="profile-detail">
        <span>Gmail</span>
        <span><?= htmlspecialchars($student['gmail'] ?? '—') ?></span>
      </div>
      <div class="profile-detail">
        <span>Contact</span>
        <span><?= htmlspecialchars($student['contact_number'] ?? '—') ?></span>
      </div>
      <div class="profile-detail">
        <span>Gender</span>
        <span><?= htmlspecialchars($student['gender'] ?? '—') ?></span>
      </div>
    </div>

    <!-- QUEUE INFO CARD -->
    <div class="info-card">
      <div class="sec-label">Queue Status</div>
      <div class="info-row">
        <span class="ir-label">System Status</span>
        <span class="ir-val ir-green">● Online</span>
      </div>
      <div class="info-row">
        <span class="ir-label">Total Waiting</span>
        <span class="ir-val"><?= $total_waiting ?> students</span>
      </div>
      <div class="info-row">
        <span class="ir-label">Now Serving</span>
        <span class="ir-val ir-yellow">
          <?= $serving ? '#' . str_pad($serving['queuing_num'], 4, '0', STR_PAD_LEFT) : 'None' ?>
        </span>
      </div>
      <div class="info-row">
        <span class="ir-label">Est. Per Person</span>
        <span class="ir-val">~5 minutes</span>
      </div>
      <div class="info-row">
        <span class="ir-label">Office Hours</span>
        <span class="ir-val">8:00 AM – 5:00 PM</span>
      </div>
    </div>

    <!-- COR PREVIEW -->
    <?php if (!empty($student['cor_image'])): ?>
    <div class="info-card">
      <div class="sec-label">Your COR</div>
      <img src="assets/uploads/<?= htmlspecialchars($student['cor_image']) ?>"
           alt="COR" style="width:100%; border-radius:10px; margin-top:8px;"/>
    </div>
    <?php endif; ?>

  </div><!-- /sidebar -->

</div><!-- /page -->

<!-- Auto-refresh every 30 seconds -->
<script>
  setTimeout(() => location.reload(), 30000);

  // Countdown timer
  let secs = 30;
  const bar = document.querySelector('.refresh-bar');
  if (bar) {
    setInterval(() => {
      secs--;
      if (secs <= 0) secs = 30;
      const span = bar.querySelector('span:last-child');
      if (span) span.textContent = `Page refreshes in ${secs}s`;
    }, 1000);
  }
</script>

</body>
</html>