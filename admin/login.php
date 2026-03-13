<?php
session_start();

// Already logged in as admin? Redirect
if (isset($_SESSION['admin_id'])) {
    header('Location: dashboard.php');
    exit();
}

require_once '../config/db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Please enter your username and password.';
    } else {
        $stmt = $pdo->prepare('SELECT * FROM admins WHERE username = ?');
        $stmt->execute([$username]);
        $admin = $stmt->fetch();

        if ($admin && password_verify($password, $admin['password'])) {
            // ── Login success ──
            $_SESSION['admin_id']    = $admin['admin_id'];
            $_SESSION['admin_name']  = $admin['fullname'];
            $_SESSION['admin_user']  = $admin['username'];
            $_SESSION['admin_role']  = $admin['faculty_role'];
            header('Location: dashboard.php');
            exit();
        } else {
            $error = 'Invalid username or password. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Admin Login — SMART Q</title>
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
      --red:      #f87171;
      --green:    #34d399;
    }
    *, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }

    body {
      min-height: 100vh;
      font-family: 'DM Sans', sans-serif;
      color: var(--white);
      display: flex; flex-direction: column;
      overflow-x: hidden;
      background: #071020;
    }

    /* ── BACKGROUND ──────────────────────── */
    .bg {
      position: fixed; inset: 0; z-index: 0;
      background:
        radial-gradient(ellipse 70% 60% at 100% 0%,   rgba(17,43,82,0.8)  0%, transparent 65%),
        radial-gradient(ellipse 50% 40% at 0%   100%,  rgba(201,168,76,0.06) 0%, transparent 60%),
        linear-gradient(170deg, #071020 0%, #0b1f3a 45%, #071428 100%);
    }
    .bg::after {
      content: ''; position: absolute; inset: 0;
      background-image:
        linear-gradient(rgba(255,255,255,0.02) 1px, transparent 1px),
        linear-gradient(90deg, rgba(255,255,255,0.02) 1px, transparent 1px);
      background-size: 44px 44px;
    }

    /* diagonal accent line */
    .bg-line {
      position: fixed; z-index: 0; pointer-events: none;
      width: 2px; height: 100vh;
      background: linear-gradient(to bottom, transparent, rgba(201,168,76,0.12), transparent);
      top: 0; left: 35%;
      transform: rotate(15deg) translateX(-50%);
    }

    /* ── TOP BAR ─────────────────────────── */
    nav {
      position: relative; z-index: 10;
      display: flex; align-items: center; justify-content: space-between;
      padding: 18px 50px;
      border-bottom: 1px solid var(--border);
      background: rgba(7,16,32,0.75);
      backdrop-filter: blur(16px);
    }
    .nav-brand { display:flex; align-items:center; gap:12px; text-decoration:none; }
    .nav-logo {
      width: 38px; height: 38px; border-radius: 9px;
      background: linear-gradient(135deg, var(--gold), var(--gold-lt));
      display: flex; align-items: center; justify-content: center;
      font-family: 'Playfair Display', serif;
      font-weight: 800; font-size: 16px; color: var(--navy);
      box-shadow: 0 4px 16px rgba(201,168,76,0.3);
    }
    .nav-text { display: flex; flex-direction: column; }
    .nav-text strong {
      font-family: 'Playfair Display', serif;
      font-size: 15px; font-weight: 700; color: var(--white);
    }
    .nav-text span { font-size: 10px; color: var(--gold); letter-spacing: 1px; text-transform: uppercase; }

    .nav-back {
      display: flex; align-items: center; gap: 6px;
      font-size: 12px; color: var(--gray); text-decoration: none;
      transition: color 0.2s;
    }
    .nav-back:hover { color: var(--white); }

    /* ── MAIN ────────────────────────────── */
    .main {
      position: relative; z-index: 5;
      flex: 1; display: flex;
      align-items: center; justify-content: center;
      padding: 50px 20px;
    }

    .wrapper {
      display: grid;
      grid-template-columns: 1fr 420px;
      gap: 60px; align-items: center;
      max-width: 950px; width: 100%;
    }

    /* ── LEFT INFO ───────────────────────── */
    .info-panel { animation: fadeLeft 0.7s ease both; }
    @keyframes fadeLeft {
      from { opacity:0; transform:translateX(-30px); }
      to   { opacity:1; transform:translateX(0); }
    }

    .admin-badge {
      display: inline-flex; align-items: center; gap: 8px;
      background: rgba(201,168,76,0.08);
      border: 1px solid rgba(201,168,76,0.22);
      color: var(--gold-lt);
      padding: 5px 14px; border-radius: 99px;
      font-size: 11px; font-weight: 600;
      letter-spacing: 1px; text-transform: uppercase;
      margin-bottom: 26px;
    }

    .info-title {
      font-family: 'Playfair Display', serif;
      font-size: clamp(30px, 4vw, 46px);
      font-weight: 800; line-height: 1.1;
      margin-bottom: 16px;
    }
    .info-title .gold {
      background: linear-gradient(135deg, var(--gold), var(--gold-lt));
      -webkit-background-clip: text; -webkit-text-fill-color: transparent;
      background-clip: text;
    }

    .info-desc {
      font-size: 14px; color: var(--gray);
      font-weight: 300; line-height: 1.8;
      margin-bottom: 36px; max-width: 360px;
    }

    /* access list */
    .access-list { display: flex; flex-direction: column; gap: 12px; }
    .access-item {
      display: flex; align-items: center; gap: 14px;
      padding: 14px 16px;
      background: rgba(255,255,255,0.02);
      border: 1px solid var(--border);
      border-radius: 12px;
    }
    .access-icon {
      width: 36px; height: 36px; border-radius: 9px;
      background: rgba(201,168,76,0.08);
      border: 1px solid rgba(201,168,76,0.15);
      display: flex; align-items: center; justify-content: center;
      font-size: 16px; flex-shrink: 0;
    }
    .access-text strong { display:block; font-size:13px; font-weight:600; margin-bottom:2px; }
    .access-text span   { font-size:11px; color:var(--gray); font-weight:300; }

    /* ── LOGIN CARD ──────────────────────── */
    .card {
      background: rgba(10,20,40,0.92);
      border: 1px solid var(--border);
      border-radius: 22px;
      overflow: hidden;
      box-shadow: 0 30px 80px rgba(0,0,0,0.5), 0 0 0 1px rgba(201,168,76,0.06);
      backdrop-filter: blur(20px);
      animation: fadeRight 0.7s 0.15s ease both;
    }
    @keyframes fadeRight {
      from { opacity:0; transform:translateX(30px); }
      to   { opacity:1; transform:translateX(0); }
    }

    /* gold top border */
    .card-top-border {
      height: 3px;
      background: linear-gradient(90deg, transparent, var(--gold), var(--gold-lt), var(--gold), transparent);
    }

    .card-head {
      padding: 28px 36px 22px;
      border-bottom: 1px solid var(--border);
    }
    .card-head-row {
      display: flex; align-items: center; gap: 14px; margin-bottom: 10px;
    }
    .admin-avatar {
      width: 46px; height: 46px; border-radius: 12px;
      background: linear-gradient(135deg, rgba(201,168,76,0.2), rgba(232,201,122,0.1));
      border: 1px solid rgba(201,168,76,0.3);
      display: flex; align-items: center; justify-content: center;
      font-size: 20px;
    }
    .card-head h2 {
      font-family: 'Playfair Display', serif;
      font-size: 20px; font-weight: 700;
    }
    .card-head p { font-size: 12px; color: var(--gray); font-weight: 300; line-height: 1.5; }

    .card-body { padding: 28px 36px 34px; }

    /* ── ALERT ──────────────────────────── */
    .alert {
      display: flex; align-items: center; gap: 10px;
      padding: 12px 16px; border-radius: 10px;
      font-size: 13px; margin-bottom: 22px;
      animation: shake 0.4s ease;
    }
    .alert.error {
      background: rgba(248,113,113,0.08);
      border: 1px solid rgba(248,113,113,0.25);
      color: #fca5a5;
    }
    @keyframes shake {
      0%,100%{transform:translateX(0)}
      20%{transform:translateX(-6px)}
      40%{transform:translateX(6px)}
      60%{transform:translateX(-4px)}
      80%{transform:translateX(4px)}
    }

    /* ── FIELDS ─────────────────────────── */
    .field { display:flex; flex-direction:column; gap:7px; margin-bottom:18px; }
    .field label {
      font-size:11px; font-weight:700;
      text-transform:uppercase; letter-spacing:0.8px;
      color:var(--gray);
    }
    .field input {
      background: rgba(255,255,255,0.035);
      border: 1px solid var(--border);
      border-radius: 10px;
      padding: 13px 16px;
      font-family:'DM Sans',sans-serif;
      font-size:14px; color:var(--white);
      outline:none; width:100%;
      transition: border-color 0.2s, background 0.2s;
    }
    .field input::placeholder { color:rgba(138,154,181,0.35); }
    .field input:focus {
      border-color: var(--gold);
      background: rgba(201,168,76,0.04);
    }

    .pw-wrap { position:relative; }
    .pw-wrap input { padding-right:46px; }
    .pw-toggle {
      position:absolute; right:14px; top:50%; transform:translateY(-50%);
      background:none; border:none; cursor:pointer;
      color:var(--gray); font-size:15px;
      transition:color 0.2s;
    }
    .pw-toggle:hover { color:var(--white); }

    /* ── SUBMIT ─────────────────────────── */
    .btn-submit {
      width:100%; padding:14px;
      background: linear-gradient(135deg, var(--gold) 0%, var(--gold-lt) 100%);
      border:none; border-radius:12px;
      font-family:'DM Sans',sans-serif;
      font-size:15px; font-weight:700; color:var(--navy);
      cursor:pointer; margin-top:8px;
      box-shadow: 0 6px 28px rgba(201,168,76,0.3);
      transition: all 0.25s;
      display:flex; align-items:center; justify-content:center; gap:10px;
    }
    .btn-submit:hover {
      transform:translateY(-2px);
      box-shadow: 0 10px 36px rgba(201,168,76,0.45);
    }
    .btn-submit:active { transform:translateY(0); }
    .btn-submit.loading { opacity:0.8; pointer-events:none; }

    .spinner {
      display:none; width:18px; height:18px; border-radius:50%;
      border:2px solid rgba(11,31,58,0.3);
      border-top-color:var(--navy);
      animation:spin 0.7s linear infinite;
    }
    @keyframes spin { to{transform:rotate(360deg)} }

    /* warning note */
    .sec-note {
      display:flex; align-items:flex-start; gap:8px;
      background:rgba(201,168,76,0.05);
      border:1px solid rgba(201,168,76,0.15);
      border-radius:10px; padding:12px 14px;
      font-size:12px; color:rgba(232,201,122,0.7);
      line-height:1.6; margin-top:20px;
    }

    /* student link */
    .student-link {
      text-align:center; margin-top:20px;
      font-size:12px; color:var(--gray);
    }
    .student-link a {
      color:var(--gray); text-decoration:none; transition:color 0.2s;
    }
    .student-link a:hover { color:var(--white); }

    /* ── FOOTER ─────────────────────────── */
    footer {
      position:relative; z-index:5;
      text-align:center; padding:18px;
      border-top:1px solid var(--border);
      font-size:11px; color:var(--gray); font-weight:300;
    }

    /* ── MOBILE ─────────────────────────── */
    @media(max-width:800px) {
      nav { padding:16px 20px; }
      .wrapper { grid-template-columns:1fr; gap:30px; }
      .info-panel { text-align:center; }
      .info-desc  { margin:0 auto 28px; }
      .access-list { display:none; }
      .card-head, .card-body { padding-left:24px; padding-right:24px; }
    }
  </style>
</head>
<body>

<div class="bg"></div>
<div class="bg-line"></div>

<!-- NAV -->
<nav>
  <a href="../index.php" class="nav-brand">
    <div class="nav-logo">Q</div>
    <div class="nav-text">
      <strong>SMART Q</strong>
      <span>Admin Portal</span>
    </div>
  </a>
  <a href="../login.php" class="nav-back">← Student Login</a>
</nav>

<!-- MAIN -->
<div class="main">
  <div class="wrapper">

    <!-- LEFT INFO PANEL -->
    <div class="info-panel">
      <div class="admin-badge">🔐 Restricted Access</div>
      <h1 class="info-title">
        Faculty &<br>
        <span class="gold">Staff Portal</span>
      </h1>
      <p class="info-desc">
        Manage the CICS queue, serve students, monitor counters,
        and access full transaction analytics — all from one dashboard.
      </p>

      <div class="access-list">
        <div class="access-item">
          <div class="access-icon">🪟</div>
          <div class="access-text">
            <strong>Counter Management</strong>
            <span>Call the next student and update queue status</span>
          </div>
        </div>
        <div class="access-item">
          <div class="access-icon">📊</div>
          <div class="access-text">
            <strong>Live Queue Monitor</strong>
            <span>See all waiting students in real time</span>
          </div>
        </div>
        <div class="access-item">
          <div class="access-icon">📜</div>
          <div class="access-text">
            <strong>Transaction History</strong>
            <span>Full logs of all completed transactions</span>
          </div>
        </div>
        <div class="access-item">
          <div class="access-icon">🔔</div>
          <div class="access-text">
            <strong>Notification System</strong>
            <span>Send alerts to students when their turn arrives</span>
          </div>
        </div>
      </div>
    </div>

    <!-- LOGIN CARD -->
    <div class="card">
      <div class="card-top-border"></div>

      <div class="card-head">
        <div class="card-head-row">
          <div class="admin-avatar">🛡️</div>
          <div>
            <h2>Admin Sign In</h2>
          </div>
        </div>
        <p>This portal is for authorized CICS faculty and staff only.<br>Students please use the student login.</p>
      </div>

      <div class="card-body">

        <?php if (!empty($error)): ?>
        <div class="alert error">
          <span>⚠️</span>
          <span><?= htmlspecialchars($error) ?></span>
        </div>
        <?php endif; ?>

        <form method="POST" onsubmit="handleSubmit(this)">

          <div class="field">
            <label>Username</label>
            <input
              type="text"
              name="username"
              placeholder="Enter admin username"
              value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
              autocomplete="username"
              autofocus
              required/>
          </div>

          <div class="field">
            <label>Password</label>
            <div class="pw-wrap">
              <input
                type="password"
                name="password"
                id="pw"
                placeholder="Enter admin password"
                autocomplete="current-password"
                required/>
              <button type="button" class="pw-toggle" onclick="togglePw()">👁️</button>
            </div>
          </div>

          <button type="submit" class="btn-submit" id="submitBtn">
            <div class="spinner" id="spinner"></div>
            <span id="btnText">🔐 &nbsp;Sign In to Admin Panel</span>
          </button>

        </form>

        <div class="sec-note">
          🔒 This is a restricted area. Unauthorized access attempts are logged.
          If you need access, contact your system administrator.
        </div>

        <div class="student-link">
          Are you a student? <a href="../login.php">Go to Student Login →</a>
        </div>

      </div><!-- /card-body -->
    </div><!-- /card -->

  </div><!-- /wrapper -->
</div><!-- /main -->

<footer>
  SMART Q Admin Portal &nbsp;·&nbsp; CICS — ZPPSU &nbsp;·&nbsp; Authorized Personnel Only
</footer>

<script>
  function togglePw() {
    const pw  = document.getElementById('pw');
    const btn = document.querySelector('.pw-toggle');
    pw.type   = pw.type === 'password' ? 'text' : 'password';
    btn.textContent = pw.type === 'password' ? '👁️' : '🙈';
  }

  function handleSubmit(form) {
    const btn     = document.getElementById('submitBtn');
    const spinner = document.getElementById('spinner');
    const txt     = document.getElementById('btnText');
    btn.classList.add('loading');
    spinner.style.display = 'block';
    txt.textContent = 'Signing in...';
  }
</script>
</body>
</html>