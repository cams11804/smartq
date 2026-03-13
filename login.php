<?php
session_start();

// Already logged in? Redirect
if (isset($_SESSION['student_number'])) {
    header('Location: dashboard.php');
    exit();
}

require_once 'config/db.php';

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_number = trim($_POST['student_number'] ?? '');
    $password       = $_POST['password'] ?? '';

    if (empty($student_number) || empty($password)) {
        $error = 'Please enter your student number and password.';
    } else {
        $stmt = $pdo->prepare('SELECT * FROM students WHERE student_number = ?');
        $stmt->execute([$student_number]);
        $student = $stmt->fetch();

        if ($student && password_verify($password, $student['password'])) {
            // ── Login success ──
            $_SESSION['student_number'] = $student['student_number'];
            $_SESSION['fullname']       = $student['fullname'];
            $_SESSION['course']         = $student['course'];
            $_SESSION['gmail']          = $student['gmail'];
            header('Location: dashboard.php');
            exit();
        } else {
            $error = 'Incorrect student number or password. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Student Login — SMART Q</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700;800&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <style>
    :root {
      --navy:      #0b1f3a;
      --navy-mid:  #112b52;
      --navy-card: #0f2444;
      --gold:      #c9a84c;
      --gold-lt:   #e8c97a;
      --blue:      #1a56db;
      --white:     #f8f9fc;
      --gray:      #8a9ab5;
      --border:    rgba(255,255,255,0.08);
      --red:       #f87171;
      --green:     #34d399;
    }
    *, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }

    body {
      min-height: 100vh;
      background: linear-gradient(160deg, #071428 0%, #0b1f3a 50%, #071020 100%);
      font-family: 'DM Sans', sans-serif;
      color: var(--white);
      display: flex;
      flex-direction: column;
      overflow-x: hidden;
    }

    /* BG grid texture */
    body::before {
      content: ''; position: fixed; inset: 0; pointer-events: none; z-index: 0;
      background-image:
        linear-gradient(rgba(255,255,255,0.02) 1px, transparent 1px),
        linear-gradient(90deg, rgba(255,255,255,0.02) 1px, transparent 1px);
      background-size: 44px 44px;
    }

    /* Floating orb */
    .orb {
      position: fixed; width: 420px; height: 420px; border-radius: 50%;
      background: radial-gradient(circle, rgba(26,86,219,0.08) 0%, transparent 70%);
      bottom: -80px; left: -80px;
      pointer-events: none; z-index: 0;
      animation: floatOrb 10s ease-in-out infinite alternate;
    }
    .orb2 {
      position: fixed; width: 300px; height: 300px; border-radius: 50%;
      background: radial-gradient(circle, rgba(201,168,76,0.06) 0%, transparent 70%);
      top: 40px; right: -60px;
      pointer-events: none; z-index: 0;
      animation: floatOrb 14s ease-in-out infinite alternate-reverse;
    }
    @keyframes floatOrb {
      from { transform: translate(0, 0) scale(1); }
      to   { transform: translate(30px, -40px) scale(1.1); }
    }

    /* ── NAV ─────────────────────────────── */
    nav {
      position: relative; z-index: 10;
      display: flex; align-items: center; justify-content: space-between;
      padding: 18px 50px;
      border-bottom: 1px solid var(--border);
      background: rgba(11,31,58,0.7);
      backdrop-filter: blur(14px);
    }
    .nav-brand {
      display: flex; align-items: center; gap: 12px;
      text-decoration: none;
    }
    .nav-logo {
      width: 38px; height: 38px; border-radius: 9px;
      background: linear-gradient(135deg, var(--gold), var(--gold-lt));
      display: flex; align-items: center; justify-content: center;
      font-family: 'Playfair Display', serif;
      font-weight: 800; font-size: 16px; color: var(--navy);
      box-shadow: 0 4px 16px rgba(201,168,76,0.3);
    }
    .nav-name {
      font-family: 'Playfair Display', serif;
      font-size: 16px; font-weight: 700; color: var(--white);
    }
    .nav-register {
      font-size: 13px; font-weight: 500; color: var(--gray);
      text-decoration: none; transition: color 0.2s;
    }
    .nav-register:hover { color: var(--gold-lt); }

    /* ── MAIN LAYOUT ─────────────────────── */
    .main {
      position: relative; z-index: 5;
      flex: 1;
      display: grid;
      grid-template-columns: 1fr 1fr;
      max-width: 1100px;
      margin: 0 auto;
      width: 100%;
      padding: 60px 30px;
      gap: 60px;
      align-items: center;
    }

    /* ── LEFT PANEL ─────────────────────── */
    .left-panel {
      animation: fadeLeft 0.7s ease both;
    }
    @keyframes fadeLeft {
      from { opacity: 0; transform: translateX(-30px); }
      to   { opacity: 1; transform: translateX(0); }
    }

    .left-badge {
      display: inline-flex; align-items: center; gap: 8px;
      background: rgba(201,168,76,0.1);
      border: 1px solid rgba(201,168,76,0.25);
      color: var(--gold-lt);
      padding: 5px 14px; border-radius: 99px;
      font-size: 11px; font-weight: 600;
      letter-spacing: 1px; text-transform: uppercase;
      margin-bottom: 26px;
    }

    .left-title {
      font-family: 'Playfair Display', serif;
      font-size: clamp(32px, 4vw, 50px);
      font-weight: 800; line-height: 1.1;
      margin-bottom: 18px;
    }
    .left-title .accent {
      background: linear-gradient(135deg, var(--gold), var(--gold-lt));
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
    }

    .left-desc {
      font-size: 14px; color: var(--gray);
      font-weight: 300; line-height: 1.8;
      margin-bottom: 36px; max-width: 380px;
    }

    /* feature bullets */
    .feature-list {
      display: flex; flex-direction: column; gap: 14px;
    }
    .feature-item {
      display: flex; align-items: center; gap: 14px;
    }
    .feature-item .fi-icon {
      width: 38px; height: 38px; border-radius: 10px;
      background: rgba(255,255,255,0.04);
      border: 1px solid var(--border);
      display: flex; align-items: center; justify-content: center;
      font-size: 17px; flex-shrink: 0;
    }
    .feature-item .fi-text strong {
      display: block; font-size: 13px; font-weight: 600; margin-bottom: 2px;
    }
    .feature-item .fi-text span {
      font-size: 12px; color: var(--gray); font-weight: 300;
    }

    /* ── RIGHT PANEL (CARD) ──────────────── */
    .card {
      background: rgba(15,36,68,0.88);
      border: 1px solid var(--border);
      border-radius: 22px;
      overflow: hidden;
      box-shadow: 0 30px 80px rgba(0,0,0,0.45);
      backdrop-filter: blur(18px);
      animation: fadeRight 0.7s 0.2s ease both;
    }
    @keyframes fadeRight {
      from { opacity: 0; transform: translateX(30px); }
      to   { opacity: 1; transform: translateX(0); }
    }

    .card-head {
      padding: 30px 36px 24px;
      border-bottom: 1px solid var(--border);
      background: linear-gradient(135deg, rgba(26,61,110,0.35), rgba(17,43,82,0.15));
    }
    .card-head h2 {
      font-family: 'Playfair Display', serif;
      font-size: 22px; font-weight: 700; margin-bottom: 5px;
    }
    .card-head p { font-size: 13px; color: var(--gray); font-weight: 300; }

    .card-body { padding: 32px 36px 36px; }

    /* ── ALERT ──────────────────────────── */
    .alert {
      display: flex; align-items: flex-start; gap: 10px;
      padding: 13px 16px; border-radius: 10px;
      font-size: 13px; line-height: 1.5;
      margin-bottom: 22px;
      animation: shake 0.4s ease;
    }
    .alert.error {
      background: rgba(248,113,113,0.1);
      border: 1px solid rgba(248,113,113,0.3);
      color: #fca5a5;
    }
    @keyframes shake {
      0%, 100% { transform: translateX(0); }
      20%       { transform: translateX(-6px); }
      40%       { transform: translateX(6px); }
      60%       { transform: translateX(-4px); }
      80%       { transform: translateX(4px); }
    }

    /* ── FORM ───────────────────────────── */
    .field { display: flex; flex-direction: column; gap: 7px; margin-bottom: 18px; }

    .field label {
      font-size: 11px; font-weight: 600;
      text-transform: uppercase; letter-spacing: 0.7px;
      color: var(--gray);
    }

    .field input {
      background: rgba(255,255,255,0.04);
      border: 1px solid var(--border);
      border-radius: 10px;
      padding: 13px 16px;
      font-family: 'DM Sans', sans-serif;
      font-size: 14px; color: var(--white);
      outline: none;
      transition: border-color 0.2s, background 0.2s;
      width: 100%;
    }
    .field input::placeholder { color: rgba(138,154,181,0.4); }
    .field input:focus {
      border-color: var(--blue);
      background: rgba(26,86,219,0.07);
    }

    /* password */
    .pw-wrap { position: relative; }
    .pw-wrap input { padding-right: 46px; }
    .pw-toggle {
      position: absolute; right: 14px; top: 50%; transform: translateY(-50%);
      background: none; border: none; cursor: pointer;
      color: var(--gray); font-size: 15px; padding: 4px;
      transition: color 0.2s;
    }
    .pw-toggle:hover { color: var(--white); }

    /* forgot */
    .forgot-row {
      display: flex; justify-content: flex-end;
      margin-top: -10px; margin-bottom: 22px;
    }
    .forgot-row a {
      font-size: 12px; color: var(--gray); text-decoration: none;
      transition: color 0.2s;
    }
    .forgot-row a:hover { color: var(--gold-lt); }

    /* submit */
    .btn-submit {
      width: 100%; padding: 14px;
      background: linear-gradient(135deg, #1a56db, #2563eb);
      border: none; border-radius: 12px;
      font-family: 'DM Sans', sans-serif;
      font-size: 15px; font-weight: 600; color: white;
      cursor: pointer;
      box-shadow: 0 6px 28px rgba(26,86,219,0.4);
      transition: all 0.25s;
      display: flex; align-items: center; justify-content: center; gap: 10px;
      position: relative; overflow: hidden;
    }
    .btn-submit::after {
      content: '';
      position: absolute; inset: 0;
      background: rgba(255,255,255,0);
      transition: background 0.2s;
    }
    .btn-submit:hover {
      transform: translateY(-2px);
      box-shadow: 0 10px 36px rgba(26,86,219,0.55);
    }
    .btn-submit:hover::after { background: rgba(255,255,255,0.05); }
    .btn-submit:active { transform: translateY(0); }

    /* loading state */
    .btn-submit.loading { pointer-events: none; opacity: 0.8; }
    .spinner {
      display: none;
      width: 18px; height: 18px; border-radius: 50%;
      border: 2px solid rgba(255,255,255,0.3);
      border-top-color: white;
      animation: spin 0.7s linear infinite;
    }
    @keyframes spin { to { transform: rotate(360deg); } }

    /* divider */
    .or-divider {
      display: flex; align-items: center; gap: 14px;
      margin: 22px 0;
      font-size: 12px; color: var(--gray);
    }
    .or-divider::before,
    .or-divider::after {
      content: ''; flex: 1; height: 1px; background: var(--border);
    }

    /* register link */
    .register-link {
      display: flex; align-items: center; justify-content: center; gap: 8px;
      padding: 13px;
      border: 1.5px solid var(--border);
      border-radius: 12px;
      font-size: 13px; font-weight: 500; color: var(--gray);
      text-decoration: none;
      transition: all 0.2s;
    }
    .register-link:hover {
      border-color: rgba(201,168,76,0.3);
      color: var(--gold-lt);
      background: rgba(201,168,76,0.05);
    }

    /* admin link */
    .admin-link {
      text-align: center; margin-top: 20px;
      font-size: 12px; color: var(--gray);
    }
    .admin-link a {
      color: var(--gray); text-decoration: none;
      transition: color 0.2s;
    }
    .admin-link a:hover { color: var(--white); }

    /* ── FOOTER ─────────────────────────── */
    footer {
      position: relative; z-index: 5;
      text-align: center; padding: 20px;
      border-top: 1px solid var(--border);
      font-size: 12px; color: var(--gray); font-weight: 300;
    }

    /* ── MOBILE ─────────────────────────── */
    @media (max-width: 800px) {
      nav { padding: 16px 20px; }
      .main {
        grid-template-columns: 1fr;
        padding: 30px 20px 50px;
        gap: 30px;
      }
      .left-panel { text-align: center; }
      .left-desc  { margin: 0 auto 30px; }
      .feature-list { display: none; }
      .card-head, .card-body { padding-left: 24px; padding-right: 24px; }
    }
  </style>
</head>
<body>

<div class="orb"></div>
<div class="orb2"></div>

<!-- NAV -->
<nav>
  <a href="index.php" class="nav-brand">
    <div class="nav-logo">Q</div>
    <span class="nav-name">SMART Q</span>
  </a>
  <a href="register.php" class="nav-register">No account yet? Register →</a>
</nav>

<!-- MAIN -->
<div class="main">

  <!-- LEFT -->
  <div class="left-panel">
    <div class="left-badge">🎓 Student Portal</div>
    <h1 class="left-title">
      Your Queue,<br>
      <span class="accent">Your Time.</span>
    </h1>
    <p class="left-desc">
      Log in to join the CICS digital queue, monitor your position
      in real time, and get notified when it's your turn —
      all without standing in line.
    </p>

    <div class="feature-list">
      <div class="feature-item">
        <div class="fi-icon">📊</div>
        <div class="fi-text">
          <strong>Live Queue Tracking</strong>
          <span>See your number and estimated wait time</span>
        </div>
      </div>
      <div class="feature-item">
        <div class="fi-icon">🔔</div>
        <div class="fi-text">
          <strong>Smart Notifications</strong>
          <span>Get alerted when your turn is near</span>
        </div>
      </div>
      <div class="feature-item">
        <div class="fi-icon">📁</div>
        <div class="fi-text">
          <strong>Document Verified</strong>
          <span>COR checked digitally before you arrive</span>
        </div>
      </div>
      <div class="feature-item">
        <div class="fi-icon">🕐</div>
        <div class="fi-text">
          <strong>Transaction History</strong>
          <span>View all your past visits and services</span>
        </div>
      </div>
    </div>
  </div>

  <!-- RIGHT: LOGIN CARD -->
  <div class="card">
    <div class="card-head">
      <h2>Welcome Back</h2>
      <p>Enter your student credentials to access your queue.</p>
    </div>

    <div class="card-body">

      <?php if (!empty($error)): ?>
      <div class="alert error">
        <span>⚠️</span>
        <span><?= htmlspecialchars($error) ?></span>
      </div>
      <?php endif; ?>

      <form method="POST" id="loginForm" onsubmit="handleSubmit(this)">

        <div class="field">
          <label>Student Number</label>
          <input
            type="text"
            name="student_number"
            placeholder="e.g. 2023-0001"
            value="<?= htmlspecialchars($_POST['student_number'] ?? '') ?>"
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
              placeholder="Enter your password"
              autocomplete="current-password"
              required/>
            <button type="button" class="pw-toggle" onclick="togglePw()">👁️</button>
          </div>
        </div>

        <div class="forgot-row">
          <a href="#">Forgot password?</a>
        </div>

        <button type="submit" class="btn-submit" id="submitBtn">
          <div class="spinner" id="spinner"></div>
          <span id="btnText">Log In to My Account</span>
        </button>

      </form>

      <div class="or-divider">or</div>

      <a href="register.php" class="register-link">
        🎓 Create a New Account
      </a>

      <div class="admin-link">
        Are you faculty or staff?
        <a href="admin/login.php">Admin Login →</a>
      </div>

    </div><!-- /card-body -->
  </div><!-- /card -->

</div><!-- /main -->

<footer>
  SMART Q &nbsp;·&nbsp; College of Information and Computing Sciences &nbsp;·&nbsp; ZPPSU
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
    txt.textContent = 'Logging in...';
  }
</script>
</body>
</html>