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

// ── Filters ────────────────────────────────────────────────────────
$filter_date    = $_GET['date']    ?? date('Y-m-d');
$filter_service = (int)($_GET['service'] ?? 0);
$filter_status  = $_GET['status']  ?? '';
$filter_search  = trim($_GET['search'] ?? '');
$page           = max(1, (int)($_GET['page'] ?? 1));
$per_page       = 15;
$offset         = ($page - 1) * $per_page;

// ── Build query ────────────────────────────────────────────────────
$where  = ['1=1'];
$params = [];

if ($filter_date) {
    $where[]  = 'DATE(h.logged_at) = ?';
    $params[] = $filter_date;
}
if ($filter_service) {
    $where[]  = 'h.service_id = ?';
    $params[] = $filter_service;
}
if ($filter_status) {
    $where[]  = 'h.transaction_status = ?';
    $params[] = $filter_status;
}
if ($filter_search) {
    $where[]  = '(h.student_number LIKE ? OR st.fullname LIKE ?)';
    $params[] = "%{$filter_search}%";
    $params[] = "%{$filter_search}%";
}

$where_sql = implode(' AND ', $where);

// Total count
$count_sql = "
    SELECT COUNT(*) FROM history h
    LEFT JOIN students st      ON h.student_number = st.student_number
    WHERE {$where_sql}
";
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total_records = (int)$count_stmt->fetchColumn();
$total_pages   = max(1, ceil($total_records / $per_page));

// Records
$sql = "
    SELECT h.*,
           st.fullname, st.course, st.gmail,
           sv.service_name,
           a.fullname  AS admin_fullname,
           c.counter_name
    FROM history h
    LEFT JOIN students     st ON h.student_number = st.student_number
    LEFT JOIN service_types sv ON h.service_id    = sv.service_id
    LEFT JOIN admins        a  ON h.admin_id       = a.admin_id
    LEFT JOIN counters      c  ON h.counter_id     = c.counter_id
    WHERE {$where_sql}
    ORDER BY h.logged_at DESC
    LIMIT {$per_page} OFFSET {$offset}
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$records = $stmt->fetchAll();

// ── Services for filter dropdown ──────────────────────────────────
$services = $pdo->query('SELECT * FROM service_types ORDER BY service_id')->fetchAll();

// ── Summary stats for selected date ───────────────────────────────
$summary_params = [$filter_date];
$summary = $pdo->prepare("
    SELECT
        COUNT(*)                                                    AS total,
        SUM(transaction_status = 'Completed')                       AS completed,
        SUM(transaction_status = 'Canceled')                        AS canceled,
        ROUND(AVG(wait_time_minutes),1)                             AS avg_wait,
        ROUND(AVG(service_duration_minutes),1)                      AS avg_service,
        MAX(wait_time_minutes)                                      AS max_wait
    FROM history h
    WHERE DATE(h.logged_at) = ?
");
$summary->execute($summary_params);
$stats = $summary->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Transaction History — SMART Q Admin</title>
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
      background:linear-gradient(160deg,#071428 0%,#0b1f3a 60%,#071020 100%);
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
      text-decoration:none; transition:all 0.2s;
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
      border:1px solid var(--border); margin-bottom:10px;
    }
    .sb-avatar {
      width:32px; height:32px; border-radius:8px;
      background:linear-gradient(135deg,var(--gold),var(--gold-lt));
      display:flex; align-items:center; justify-content:center;
      font-size:13px; font-weight:700; color:var(--navy);
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
      cursor:pointer; text-decoration:none; transition:all 0.2s;
    }
    .btn-logout-sb:hover { background:rgba(248,113,113,0.15); }

    /* ── CONTENT ─────────────────────────── */
    .content {
      margin-left:var(--sidebar);
      flex:1; position:relative; z-index:5;
      padding:28px 30px 60px;
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

    /* export btn */
    .btn-export {
      display:flex; align-items:center; gap:7px;
      padding:9px 18px; border-radius:9px;
      background:rgba(201,168,76,0.1);
      border:1px solid rgba(201,168,76,0.25);
      color:var(--gold-lt); font-family:'DM Sans',sans-serif;
      font-size:12px; font-weight:600; cursor:pointer;
      text-decoration:none; transition:all 0.2s;
    }
    .btn-export:hover { background:rgba(201,168,76,0.18); }

    /* ── SUMMARY STATS ───────────────────── */
    .summary-grid {
      display:grid;
      grid-template-columns:repeat(6,1fr);
      gap:12px; margin-bottom:22px;
    }
    .sum-card {
      background:rgba(15,36,68,0.8);
      border:1px solid var(--border);
      border-radius:12px; padding:16px;
      text-align:center;
    }
    .sum-num {
      font-family:'Playfair Display',serif;
      font-size:24px; font-weight:700; line-height:1;
      margin-bottom:5px;
    }
    .sum-label { font-size:10px; color:var(--gray); text-transform:uppercase; letter-spacing:0.5px; }
    .sum-card.blue  .sum-num { color:#93c5fd; }
    .sum-card.green .sum-num { color:var(--green); }
    .sum-card.red   .sum-num { color:var(--red); }
    .sum-card.gold  .sum-num { color:var(--gold-lt); }

    /* ── FILTER BAR ──────────────────────── */
    .filter-bar {
      background:rgba(15,36,68,0.8);
      border:1px solid var(--border);
      border-radius:14px; padding:18px 22px;
      margin-bottom:20px;
      display:flex; align-items:flex-end;
      gap:12px; flex-wrap:wrap;
    }
    .filter-field { display:flex; flex-direction:column; gap:6px; }
    .filter-field label {
      font-size:10px; font-weight:700; text-transform:uppercase;
      letter-spacing:0.7px; color:var(--gray);
    }
    .filter-field input,
    .filter-field select {
      background:rgba(255,255,255,0.04);
      border:1px solid var(--border); border-radius:9px;
      padding:9px 13px; color:var(--white);
      font-family:'DM Sans',sans-serif; font-size:13px;
      outline:none; transition:border-color 0.2s;
    }
    .filter-field input:focus,
    .filter-field select:focus { border-color:var(--gold); }
    .filter-field select option { background:#0f2444; }
    .filter-field input[type=text] { min-width:200px; }
    .filter-field input[type=date] { min-width:150px; }

    .btn-filter {
      padding:9px 20px; border:none; border-radius:9px;
      background:linear-gradient(135deg,var(--blue),#2563eb);
      font-family:'DM Sans',sans-serif;
      font-size:13px; font-weight:600; color:white;
      cursor:pointer; transition:all 0.2s;
      box-shadow:0 4px 16px rgba(26,86,219,0.3);
    }
    .btn-filter:hover { transform:translateY(-1px); }

    .btn-reset {
      padding:9px 16px; border-radius:9px;
      background:rgba(255,255,255,0.04);
      border:1px solid var(--border);
      color:var(--gray); font-family:'DM Sans',sans-serif;
      font-size:13px; font-weight:500; cursor:pointer;
      text-decoration:none; transition:all 0.2s;
    }
    .btn-reset:hover { color:var(--white); border-color:rgba(255,255,255,0.2); }

    /* ── TABLE CARD ──────────────────────── */
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

    /* ── HISTORY TABLE ───────────────────── */
    .hist-table { width:100%; border-collapse:collapse; }
    .hist-table th {
      font-size:10px; font-weight:700; text-transform:uppercase;
      letter-spacing:0.8px; color:var(--gray);
      padding:12px 16px; text-align:left;
      border-bottom:1px solid var(--border);
      white-space:nowrap;
    }
    .hist-table th.sortable { cursor:pointer; user-select:none; }
    .hist-table th.sortable:hover { color:var(--white); }

    .hist-table td {
      padding:13px 16px; font-size:13px;
      border-bottom:1px solid rgba(255,255,255,0.04);
      vertical-align:middle;
    }
    .hist-table tr:last-child td { border-bottom:none; }
    .hist-table tr:hover td { background:rgba(255,255,255,0.02); }

    /* badges */
    .badge {
      display:inline-flex; align-items:center; gap:5px;
      padding:3px 10px; border-radius:99px;
      font-size:11px; font-weight:600; white-space:nowrap;
    }
    .badge.completed { background:rgba(52,211,153,0.12); color:var(--green); }
    .badge.canceled  { background:rgba(248,113,113,0.12); color:var(--red); }
    .badge.pending   { background:rgba(251,191,36,0.12);  color:var(--yellow); }

    .queue-num {
      display:inline-flex; align-items:center; justify-content:center;
      min-width:42px; height:28px; border-radius:7px;
      background:rgba(26,86,219,0.12);
      border:1px solid rgba(26,86,219,0.2);
      font-family:'Playfair Display',serif;
      font-size:12px; font-weight:700; color:#93c5fd;
      padding:0 8px;
    }

    .stu-cell { display:flex; align-items:center; gap:10px; }
    .stu-av {
      width:30px; height:30px; border-radius:7px;
      background:linear-gradient(135deg,rgba(26,86,219,0.4),rgba(59,130,246,0.2));
      display:flex; align-items:center; justify-content:center;
      font-size:12px; font-weight:700; flex-shrink:0;
    }
    .stu-name { font-size:13px; font-weight:500; line-height:1.3; }
    .stu-id   { font-size:11px; color:var(--gray); }

    .time-cell { font-size:12px; color:var(--gray); white-space:nowrap; }

    .dur-pill {
      display:inline-flex; align-items:center;
      background:rgba(255,255,255,0.05);
      border:1px solid var(--border);
      border-radius:99px; padding:2px 9px;
      font-size:11px; color:var(--gray);
    }

    /* empty */
    .empty-state {
      text-align:center; padding:50px 20px;
      color:var(--gray); font-size:13px;
    }
    .empty-icon { font-size:40px; margin-bottom:12px; }

    /* ── PAGINATION ──────────────────────── */
    .pagination {
      display:flex; align-items:center; justify-content:space-between;
      padding:16px 22px;
      border-top:1px solid var(--border);
      flex-wrap:wrap; gap:10px;
    }
    .pagination-info { font-size:12px; color:var(--gray); }
    .pagination-btns { display:flex; gap:6px; }
    .pg-btn {
      display:inline-flex; align-items:center; justify-content:center;
      min-width:32px; height:32px; border-radius:8px;
      background:rgba(255,255,255,0.04);
      border:1px solid var(--border);
      font-size:12px; font-weight:600; color:var(--gray);
      text-decoration:none; transition:all 0.2s; padding:0 10px;
    }
    .pg-btn:hover  { background:rgba(255,255,255,0.08); color:var(--white); }
    .pg-btn.active { background:rgba(26,86,219,0.2); border-color:rgba(26,86,219,0.4); color:#93c5fd; }
    .pg-btn.disabled { opacity:0.3; pointer-events:none; }

    /* ── DETAIL MODAL ────────────────────── */
    .modal-overlay {
      display:none; position:fixed; inset:0;
      background:rgba(0,0,0,0.75); z-index:200;
      align-items:center; justify-content:center;
      backdrop-filter:blur(4px);
    }
    .modal-overlay.open { display:flex; }
    .modal-box {
      background:#0f2444;
      border:1px solid var(--border);
      border-radius:18px; padding:28px;
      max-width:480px; width:90%;
      position:relative;
      animation:popIn 0.25s ease both;
    }
    @keyframes popIn {
      from{opacity:0;transform:scale(0.95)}
      to  {opacity:1;transform:scale(1)}
    }
    .modal-close {
      position:absolute; top:14px; right:14px;
      background:rgba(255,255,255,0.08); border:none;
      width:30px; height:30px; border-radius:50%;
      color:var(--white); font-size:14px; cursor:pointer;
      transition:background 0.2s;
    }
    .modal-close:hover { background:rgba(255,255,255,0.15); }
    .modal-title {
      font-family:'Playfair Display',serif;
      font-size:18px; font-weight:700; margin-bottom:20px;
      padding-bottom:14px; border-bottom:1px solid var(--border);
    }
    .detail-row {
      display:flex; justify-content:space-between;
      padding:10px 0; border-bottom:1px solid rgba(255,255,255,0.04);
      font-size:13px;
    }
    .detail-row:last-child { border-bottom:none; }
    .detail-row .dl { color:var(--gray); }
    .detail-row .dv { font-weight:500; text-align:right; max-width:250px; }

    /* ── MOBILE ─────────────────────────── */
    @media(max-width:1000px) {
      .summary-grid { grid-template-columns:repeat(3,1fr); }
    }
    @media(max-width:700px) {
      .sidebar { display:none; }
      .content { margin-left:0; padding:20px 16px; }
      .summary-grid { grid-template-columns:repeat(2,1fr); }
      .filter-bar { flex-direction:column; align-items:stretch; }
      .filter-field input[type=text] { min-width:unset; }
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
  <a class="sb-link" href="dashboard.php">
    <span class="icon">📊</span> Dashboard
  </a>
  <a class="sb-link active" href="history.php">
    <span class="icon">📜</span> Transaction History
  </a>
  <div class="sb-section">System</div>
  <a class="sb-link" href="../index.php" target="_blank">
    <span class="icon">🌐</span> Student Portal
  </a>
  <div class="sb-bottom">
    <div class="sb-admin-info">
      <div class="sb-avatar"><?= strtoupper(substr($admin_name,0,1)) ?></div>
      <div>
        <div class="sb-admin-name"><?= htmlspecialchars($admin_name) ?></div>
        <div class="sb-admin-role"><?= htmlspecialchars($admin_role) ?></div>
      </div>
    </div>
    <a href="logout.php" class="btn-logout-sb">⬡ &nbsp;Log Out</a>
  </div>
</aside>

<!-- ── CONTENT ── -->
<div class="content">

  <!-- TOP ROW -->
  <div class="top-row">
    <div>
      <div class="page-title">Transaction History</div>
      <div class="page-sub">Complete log of all student queue transactions</div>
    </div>
    <a href="?<?= http_build_query(array_merge($_GET, ['export'=>'csv'])) ?>" class="btn-export">
      ⬇ Export CSV
    </a>
  </div>

  <!-- SUMMARY STATS -->
  <div class="summary-grid">
    <div class="sum-card blue">
      <div class="sum-num"><?= $stats['total'] ?? 0 ?></div>
      <div class="sum-label">Total</div>
    </div>
    <div class="sum-card green">
      <div class="sum-num"><?= $stats['completed'] ?? 0 ?></div>
      <div class="sum-label">Completed</div>
    </div>
    <div class="sum-card red">
      <div class="sum-num"><?= $stats['canceled'] ?? 0 ?></div>
      <div class="sum-label">Cancelled</div>
    </div>
    <div class="sum-card gold">
      <div class="sum-num"><?= $stats['avg_wait'] ?? 0 ?><span style="font-size:14px;">m</span></div>
      <div class="sum-label">Avg Wait</div>
    </div>
    <div class="sum-card gold">
      <div class="sum-num"><?= $stats['avg_service'] ?? 0 ?><span style="font-size:14px;">m</span></div>
      <div class="sum-label">Avg Service</div>
    </div>
    <div class="sum-card blue">
      <div class="sum-num"><?= $stats['max_wait'] ?? 0 ?><span style="font-size:14px;">m</span></div>
      <div class="sum-label">Max Wait</div>
    </div>
  </div>

  <!-- FILTER BAR -->
  <form method="GET" class="filter-bar">
    <div class="filter-field">
      <label>Search Student</label>
      <input type="text" name="search" placeholder="Name or student number..."
        value="<?= htmlspecialchars($filter_search) ?>"/>
    </div>
    <div class="filter-field">
      <label>Date</label>
      <input type="date" name="date" value="<?= htmlspecialchars($filter_date) ?>"/>
    </div>
    <div class="filter-field">
      <label>Service</label>
      <select name="service">
        <option value="0">All Services</option>
        <?php foreach($services as $s): ?>
        <option value="<?= $s['service_id'] ?>" <?= $filter_service == $s['service_id'] ? 'selected' : '' ?>>
          <?= htmlspecialchars($s['service_name']) ?>
        </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="filter-field">
      <label>Status</label>
      <select name="status">
        <option value="">All Status</option>
        <option value="Completed" <?= $filter_status === 'Completed' ? 'selected' : '' ?>>Completed</option>
        <option value="Canceled"  <?= $filter_status === 'Canceled'  ? 'selected' : '' ?>>Cancelled</option>
        <option value="Pending"   <?= $filter_status === 'Pending'   ? 'selected' : '' ?>>Pending</option>
      </select>
    </div>
    <button type="submit" class="btn-filter">🔍 &nbsp;Filter</button>
    <a href="history.php" class="btn-reset">✕ Reset</a>
  </form>

  <!-- TABLE -->
  <div class="card">
    <div class="card-head">
      <h3>📜 &nbsp;Transaction Log</h3>
      <span style="font-size:12px;color:var(--gray);">
        <?= $total_records ?> record<?= $total_records !== 1 ? 's' : '' ?> found
      </span>
    </div>

    <?php if (empty($records)): ?>
    <div class="empty-state">
      <div class="empty-icon">📭</div>
      No transaction records found for the selected filters.<br>
      Try changing the date or clearing the filters.
    </div>
    <?php else: ?>
    <div style="overflow-x:auto;">
    <table class="hist-table">
      <thead>
        <tr>
          <th>#</th>
          <th>Queue No.</th>
          <th>Student</th>
          <th>Service</th>
          <th>Counter</th>
          <th>Served By</th>
          <th>Wait</th>
          <th>Duration</th>
          <th>Time</th>
          <th>Status</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($records as $i => $r):
          $row_num = $offset + $i + 1;
        ?>
        <tr>
          <td style="color:var(--gray);font-size:12px;"><?= $row_num ?></td>
          <td><span class="queue-num"><?= str_pad($r['queue_num'],4,'0',STR_PAD_LEFT) ?></span></td>
          <td>
            <div class="stu-cell">
              <div class="stu-av"><?= strtoupper(substr($r['fullname'] ?? '?', 0, 1)) ?></div>
              <div>
                <div class="stu-name"><?= htmlspecialchars($r['fullname'] ?? 'Unknown') ?></div>
                <div class="stu-id"><?= htmlspecialchars($r['student_number']) ?></div>
              </div>
            </div>
          </td>
          <td style="font-size:12px;color:var(--gray);white-space:nowrap;">
            <?= htmlspecialchars($r['service_name'] ?? '—') ?>
          </td>
          <td style="font-size:12px;color:var(--gray);">
            <?= htmlspecialchars($r['counter_name'] ?? '—') ?>
          </td>
          <td style="font-size:12px;color:var(--gray);">
            <?= htmlspecialchars($r['admin_fullname'] ?? '—') ?>
          </td>
          <td>
            <?php if ($r['wait_time_minutes'] !== null): ?>
            <span class="dur-pill">⏱ <?= $r['wait_time_minutes'] ?>m</span>
            <?php else: ?><span style="color:var(--gray);">—</span><?php endif; ?>
          </td>
          <td>
            <?php if ($r['service_duration_minutes'] !== null): ?>
            <span class="dur-pill">🔧 <?= $r['service_duration_minutes'] ?>m</span>
            <?php else: ?><span style="color:var(--gray);">—</span><?php endif; ?>
          </td>
          <td class="time-cell">
            <?= date('h:i A', strtotime($r['logged_at'])) ?><br>
            <span style="font-size:10px;"><?= date('M d, Y', strtotime($r['logged_at'])) ?></span>
          </td>
          <td>
            <span class="badge <?= strtolower($r['transaction_status'] ?? 'pending') ?>">
              <?= htmlspecialchars($r['transaction_status'] ?? 'Pending') ?>
            </span>
          </td>
          <td>
            <button class="pg-btn" style="font-size:11px;"
              onclick='showDetail(<?= json_encode($r) ?>)'>
              View
            </button>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    </div>

    <!-- PAGINATION -->
    <div class="pagination">
      <div class="pagination-info">
        Showing <?= $offset + 1 ?>–<?= min($offset + $per_page, $total_records) ?> of <?= $total_records ?> records
      </div>
      <div class="pagination-btns">
        <?php
        $base = '?' . http_build_query(array_merge($_GET, ['page' => 1]));
        $prev = '?' . http_build_query(array_merge($_GET, ['page' => $page - 1]));
        $next = '?' . http_build_query(array_merge($_GET, ['page' => $page + 1]));
        $last = '?' . http_build_query(array_merge($_GET, ['page' => $total_pages]));
        ?>
        <a href="<?= $base ?>" class="pg-btn <?= $page <= 1 ? 'disabled' : '' ?>">«</a>
        <a href="<?= $prev ?>" class="pg-btn <?= $page <= 1 ? 'disabled' : '' ?>">‹</a>
        <?php for($p = max(1,$page-2); $p <= min($total_pages,$page+2); $p++): ?>
        <a href="?<?= http_build_query(array_merge($_GET,['page'=>$p])) ?>"
           class="pg-btn <?= $p === $page ? 'active' : '' ?>"><?= $p ?></a>
        <?php endfor; ?>
        <a href="<?= $next ?>" class="pg-btn <?= $page >= $total_pages ? 'disabled' : '' ?>">›</a>
        <a href="<?= $last ?>" class="pg-btn <?= $page >= $total_pages ? 'disabled' : '' ?>">»</a>
      </div>
    </div>
    <?php endif; ?>
  </div>

</div><!-- /content -->

<!-- DETAIL MODAL -->
<div class="modal-overlay" id="detailModal">
  <div class="modal-box">
    <button class="modal-close" onclick="closeDetail()">✕</button>
    <div class="modal-title">Transaction Detail</div>
    <div id="detailContent"></div>
  </div>
</div>

<script>
  function showDetail(r) {
    const fmt = v => v ?? '—';
    const rows = [
      ['History ID',    r.history_id],
      ['Queue Number',  String(r.queue_num).padStart(4,'0')],
      ['Student',       fmt(r.fullname)],
      ['Student No.',   r.student_number],
      ['Course',        fmt(r.course)],
      ['Service',       fmt(r.service_name)],
      ['Counter',       fmt(r.counter_name)],
      ['Served By',     fmt(r.admin_fullname)],
      ['Joined Queue',  fmt(r.time_joined)],
      ['Service Start', fmt(r.time_start)],
      ['Service End',   fmt(r.time_end)],
      ['Wait Time',     r.wait_time_minutes != null ? r.wait_time_minutes + ' min' : '—'],
      ['Service Time',  r.service_duration_minutes != null ? r.service_duration_minutes + ' min' : '—'],
      ['Status',        fmt(r.transaction_status)],
      ['Logged At',     fmt(r.logged_at)],
    ];
    document.getElementById('detailContent').innerHTML =
      rows.map(([l,v]) =>
        `<div class="detail-row"><span class="dl">${l}</span><span class="dv">${v}</span></div>`
      ).join('');
    document.getElementById('detailModal').classList.add('open');
  }

  function closeDetail() {
    document.getElementById('detailModal').classList.remove('open');
  }

  document.getElementById('detailModal').addEventListener('click', function(e) {
    if (e.target === this) closeDetail();
  });
</script>
</body>
</html>