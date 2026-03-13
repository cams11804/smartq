<?php
// Redirect if already logged in
session_start();
if (isset($_SESSION['student_number'])) {
    header('Location: dashboard.php');
    exit();
}
if (isset($_SESSION['admin_id'])) {
    header('Location: admin/dashboard.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>SMART Q — CICS Queuing System</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700;800&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <style>
    :root {
      --navy:       #0b1f3a;
      --navy-mid:   #112b52;
      --navy-light: #1a3d6e;
      --gold:       #c9a84c;
      --gold-light: #e8c97a;
      --white:      #f8f9fc;
      --off-white:  #eef1f7;
      --gray:       #8a9ab5;
      --border:     rgba(255,255,255,0.09);
    }

    *, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }

    html { scroll-behavior: smooth; }

    body {
      background: var(--navy);
      color: var(--white);
      font-family: 'DM Sans', sans-serif;
      min-height: 100vh;
      overflow-x: hidden;
    }

    /* ── BACKGROUND ─────────────────────────────── */
    .bg-layer {
      position: fixed; inset: 0; z-index: 0; pointer-events: none;
      background:
        radial-gradient(ellipse 80% 60% at 10% 0%,  rgba(26,61,110,0.55) 0%, transparent 70%),
        radial-gradient(ellipse 60% 50% at 90% 100%, rgba(201,168,76,0.08) 0%, transparent 65%),
        linear-gradient(175deg, #0b1f3a 0%, #071428 55%, #0a1c35 100%);
    }

    /* fine grid texture */
    .bg-layer::after {
      content: '';
      position: absolute; inset: 0;
      background-image:
        linear-gradient(rgba(255,255,255,0.025) 1px, transparent 1px),
        linear-gradient(90deg, rgba(255,255,255,0.025) 1px, transparent 1px);
      background-size: 40px 40px;
    }

    /* animated orb */
    .orb {
      position: fixed;
      width: 500px; height: 500px;
      border-radius: 50%;
      background: radial-gradient(circle, rgba(201,168,76,0.06) 0%, transparent 70%);
      top: -100px; right: -100px;
      animation: floatOrb 12s ease-in-out infinite alternate;
      pointer-events: none; z-index: 0;
    }

    @keyframes floatOrb {
      from { transform: translate(0,0) scale(1);   }
      to   { transform: translate(-40px,60px) scale(1.15); }
    }

    /* ── NAVBAR ─────────────────────────────────── */
    nav {
      position: relative; z-index: 10;
      display: flex; align-items: center; justify-content: space-between;
      padding: 22px 60px;
      border-bottom: 1px solid var(--border);
      backdrop-filter: blur(12px);
      background: rgba(11,31,58,0.6);
      animation: slideDown 0.6s ease both;
    }

    @keyframes slideDown {
      from { opacity:0; transform: translateY(-20px); }
      to   { opacity:1; transform: translateY(0); }
    }

    .nav-brand {
      display: flex; align-items: center; gap: 14px;
    }

    .nav-logo {
      width: 42px; height: 42px;
      background: linear-gradient(135deg, var(--gold), var(--gold-light));
      border-radius: 10px;
      display: flex; align-items: center; justify-content: center;
      font-family: 'Playfair Display', serif;
      font-weight: 800; font-size: 18px; color: var(--navy);
      box-shadow: 0 4px 20px rgba(201,168,76,0.35);
      flex-shrink: 0;
    }

    .nav-name {
      display: flex; flex-direction: column;
    }
    .nav-name span:first-child {
      font-family: 'Playfair Display', serif;
      font-size: 17px; font-weight: 700; letter-spacing: 0.5px;
      color: var(--white);
    }
    .nav-name span:last-child {
      font-size: 10px; font-weight: 400; color: var(--gray);
      letter-spacing: 0.5px;
    }

    .nav-links {
      display: flex; align-items: center; gap: 10px;
    }

    .btn-nav {
      padding: 9px 22px;
      border-radius: 8px;
      font-family: 'DM Sans', sans-serif;
      font-size: 13px; font-weight: 600;
      cursor: pointer; text-decoration: none;
      transition: all 0.2s;
      display: inline-flex; align-items: center; gap: 6px;
    }

    .btn-outline {
      border: 1.5px solid rgba(255,255,255,0.2);
      background: transparent; color: var(--white);
    }
    .btn-outline:hover {
      border-color: var(--gold); color: var(--gold);
      background: rgba(201,168,76,0.07);
    }

    .btn-gold {
      background: linear-gradient(135deg, var(--gold), var(--gold-light));
      border: none; color: var(--navy);
      box-shadow: 0 4px 18px rgba(201,168,76,0.3);
    }
    .btn-gold:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 28px rgba(201,168,76,0.45);
    }

    /* ── HERO ───────────────────────────────────── */
    .hero {
      position: relative; z-index: 5;
      display: flex; flex-direction: column; align-items: center;
      text-align: center;
      padding: 90px 24px 60px;
    }

    .hero-badge {
      display: inline-flex; align-items: center; gap: 8px;
      background: rgba(201,168,76,0.1);
      border: 1px solid rgba(201,168,76,0.3);
      color: var(--gold-light);
      padding: 6px 16px; border-radius: 99px;
      font-size: 12px; font-weight: 500; letter-spacing: 0.8px;
      text-transform: uppercase;
      margin-bottom: 32px;
      animation: fadeUp 0.7s 0.2s ease both;
    }

    .hero-badge::before {
      content: ''; width: 7px; height: 7px; border-radius: 50%;
      background: var(--gold);
      animation: pulse 2s infinite;
    }

    @keyframes pulse {
      0%, 100% { opacity:1; transform: scale(1); }
      50%       { opacity:0.5; transform: scale(0.7); }
    }

    .hero-title {
      font-family: 'Playfair Display', serif;
      font-size: clamp(42px, 7vw, 80px);
      font-weight: 800; line-height: 1.05;
      letter-spacing: -1px;
      animation: fadeUp 0.7s 0.35s ease both;
    }

    .hero-title .accent {
      background: linear-gradient(135deg, var(--gold), var(--gold-light));
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
    }

    .hero-sub {
      max-width: 540px;
      margin: 22px auto 0;
      font-size: 16px; font-weight: 300; line-height: 1.75;
      color: #a0b0c8;
      animation: fadeUp 0.7s 0.5s ease both;
    }

    @keyframes fadeUp {
      from { opacity:0; transform: translateY(28px); }
      to   { opacity:1; transform: translateY(0); }
    }

    /* ── CTA BUTTONS ─────────────────────────────── */
    .hero-cta {
      display: flex; align-items: center;
      flex-wrap: wrap; gap: 14px;
      justify-content: center;
      margin-top: 44px;
      animation: fadeUp 0.7s 0.65s ease both;
    }

    .btn-primary {
      display: inline-flex; align-items: center; gap: 10px;
      padding: 15px 34px;
      background: linear-gradient(135deg, #1a56db, #2563eb);
      color: white; border: none; border-radius: 12px;
      font-family: 'DM Sans', sans-serif;
      font-size: 15px; font-weight: 600;
      cursor: pointer; text-decoration: none;
      box-shadow: 0 6px 30px rgba(26,86,219,0.4);
      transition: all 0.25s;
    }
    .btn-primary:hover {
      transform: translateY(-3px);
      box-shadow: 0 12px 40px rgba(26,86,219,0.55);
    }

    .btn-secondary {
      display: inline-flex; align-items: center; gap: 10px;
      padding: 15px 34px;
      background: rgba(255,255,255,0.06);
      color: white; border: 1.5px solid rgba(255,255,255,0.15);
      border-radius: 12px;
      font-family: 'DM Sans', sans-serif;
      font-size: 15px; font-weight: 500;
      cursor: pointer; text-decoration: none;
      transition: all 0.25s;
    }
    .btn-secondary:hover {
      background: rgba(255,255,255,0.1);
      border-color: rgba(255,255,255,0.3);
      transform: translateY(-3px);
    }

    .btn-icon {
      width: 22px; height: 22px;
      display: flex; align-items: center; justify-content: center;
      font-size: 16px;
    }

    /* ── STATS BAR ───────────────────────────────── */
    .stats-bar {
      position: relative; z-index: 5;
      display: flex; justify-content: center;
      gap: 0;
      padding: 0 24px 70px;
      animation: fadeUp 0.7s 0.8s ease both;
    }

    .stat-item {
      text-align: center;
      padding: 28px 50px;
      border: 1px solid var(--border);
      background: rgba(255,255,255,0.03);
      backdrop-filter: blur(8px);
      position: relative;
    }
    .stat-item:first-child { border-radius: 16px 0 0 16px; }
    .stat-item:last-child  { border-radius: 0 16px 16px 0; }
    .stat-item + .stat-item { border-left: none; }

    .stat-num {
      font-family: 'Playfair Display', serif;
      font-size: 32px; font-weight: 700;
      color: var(--gold-light);
      line-height: 1;
    }
    .stat-label {
      font-size: 12px; font-weight: 400;
      color: var(--gray); margin-top: 6px;
      letter-spacing: 0.5px;
    }

    /* ── FEATURES SECTION ────────────────────────── */
    .section {
      position: relative; z-index: 5;
      padding: 70px 24px;
      max-width: 1100px;
      margin: 0 auto;
    }

    .section-label {
      display: flex; align-items: center; gap: 12px;
      margin-bottom: 16px;
    }
    .section-label span {
      font-size: 11px; font-weight: 600;
      text-transform: uppercase; letter-spacing: 2px;
      color: var(--gold);
    }
    .section-label::before {
      content: '';
      width: 32px; height: 2px;
      background: var(--gold);
      border-radius: 99px;
    }

    .section-title {
      font-family: 'Playfair Display', serif;
      font-size: clamp(28px, 4vw, 40px);
      font-weight: 700; line-height: 1.2;
      margin-bottom: 12px;
    }
    .section-sub {
      font-size: 15px; color: var(--gray); font-weight: 300;
      max-width: 480px; line-height: 1.7;
      margin-bottom: 50px;
    }

    .features-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
      gap: 20px;
    }

    .feature-card {
      background: rgba(255,255,255,0.03);
      border: 1px solid var(--border);
      border-radius: 16px;
      padding: 30px;
      transition: all 0.3s;
      position: relative; overflow: hidden;
    }
    .feature-card::before {
      content: '';
      position: absolute; top:0; left:0; right:0; height:2px;
      background: linear-gradient(90deg, transparent, var(--gold), transparent);
      opacity: 0; transition: opacity 0.3s;
    }
    .feature-card:hover { border-color: rgba(201,168,76,0.25); transform: translateY(-4px); }
    .feature-card:hover::before { opacity: 1; }

    .feature-icon {
      width: 48px; height: 48px;
      background: rgba(201,168,76,0.1);
      border: 1px solid rgba(201,168,76,0.2);
      border-radius: 12px;
      display: flex; align-items: center; justify-content: center;
      font-size: 22px;
      margin-bottom: 18px;
    }

    .feature-title {
      font-size: 16px; font-weight: 600; margin-bottom: 10px;
    }
    .feature-desc {
      font-size: 14px; color: var(--gray); line-height: 1.7; font-weight: 300;
    }

    /* ── HOW IT WORKS ────────────────────────────── */
    .divider {
      position: relative; z-index: 5;
      height: 1px;
      background: linear-gradient(90deg, transparent, var(--border), transparent);
      margin: 0 60px;
    }

    .steps-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
      gap: 10px;
      margin-top: 50px;
      position: relative;
    }

    .step-item {
      text-align: center;
      padding: 28px 20px;
      background: rgba(255,255,255,0.02);
      border: 1px solid var(--border);
      border-radius: 14px;
    }

    .step-circle {
      width: 52px; height: 52px;
      border-radius: 50%;
      background: linear-gradient(135deg, var(--navy-light), var(--navy-mid));
      border: 2px solid rgba(201,168,76,0.3);
      display: flex; align-items: center; justify-content: center;
      margin: 0 auto 16px;
      font-family: 'Playfair Display', serif;
      font-size: 18px; font-weight: 700;
      color: var(--gold-light);
    }
    .step-title { font-size: 14px; font-weight: 600; margin-bottom: 8px; }
    .step-desc  { font-size: 12px; color: var(--gray); line-height: 1.6; font-weight: 300; }

    /* ── ADMIN CARD ──────────────────────────────── */
    .admin-strip {
      position: relative; z-index: 5;
      margin: 0 auto 80px;
      max-width: 900px; padding: 0 24px;
    }

    .admin-card {
      background: linear-gradient(135deg, rgba(26,61,110,0.6), rgba(17,43,82,0.8));
      border: 1px solid rgba(201,168,76,0.2);
      border-radius: 20px;
      padding: 40px 50px;
      display: flex; align-items: center; justify-content: space-between;
      gap: 30px;
      flex-wrap: wrap;
      backdrop-filter: blur(10px);
      box-shadow: 0 20px 60px rgba(0,0,0,0.3);
    }

    .admin-card-text h3 {
      font-family: 'Playfair Display', serif;
      font-size: 22px; font-weight: 700;
      margin-bottom: 8px;
    }
    .admin-card-text p {
      font-size: 14px; color: var(--gray); font-weight: 300; line-height: 1.6;
    }

    /* ── FOOTER ─────────────────────────────────── */
    footer {
      position: relative; z-index: 5;
      border-top: 1px solid var(--border);
      padding: 30px 60px;
      display: flex; align-items: center; justify-content: space-between;
      flex-wrap: wrap; gap: 12px;
      background: rgba(0,0,0,0.2);
    }

    .footer-left {
      font-size: 13px; color: var(--gray); font-weight: 300;
    }
    .footer-left strong { color: var(--white); font-weight: 500; }

    .footer-right {
      display: flex; align-items: center; gap: 6px;
      font-size: 12px; color: var(--gray);
    }
    .status-dot {
      width: 8px; height: 8px; border-radius: 50%;
      background: #10b981;
      animation: pulse 2s infinite;
    }

    /* ── MOBILE ─────────────────────────────────── */
    @media (max-width: 768px) {
      nav { padding: 18px 24px; }
      .nav-links .btn-outline { display: none; }
      .stat-item { padding: 20px 26px; }
      .admin-card { padding: 30px 24px; flex-direction: column; text-align: center; }
      footer { padding: 24px; flex-direction: column; text-align: center; }
    }

    @media (max-width: 480px) {
      .stats-bar { flex-direction: column; align-items: center; }
      .stat-item { width: 100%; max-width: 300px; border-radius: 12px !important; border-left: 1px solid var(--border) !important; }
      .stat-item + .stat-item { margin-top: -1px; }
    }
  </style>
</head>
<body>

<div class="bg-layer"></div>
<div class="orb"></div>

<!-- ── NAVBAR ── -->
<nav>
  <div class="nav-brand">
    <div class="nav-logo">Q</div>
    <div class="nav-name">
      <span>SMART Q</span>
      <span>CICS — ZPPSU</span>
    </div>
  </div>
  <div class="nav-links">
    <a href="login.php"    class="btn-nav btn-outline">Student Login</a>
    <a href="register.php" class="btn-nav btn-gold">Register</a>
  </div>
</nav>

<!-- ── HERO ── -->
<section class="hero">
  <div class="hero-badge">Now Live — Digital Queue System</div>

  <h1 class="hero-title">
    Skip the Line,<br>
    <span class="accent">Not Your Class.</span>
  </h1>

  <p class="hero-sub">
    SMART Q is the official virtual queuing system of the College of Information
    and Computing Sciences. Get your queue number from anywhere — no hallway needed.
  </p>

  <div class="hero-cta">
    <a href="register.php" class="btn-primary">
      <div class="btn-icon">🎓</div>
      Get Queue Number
    </a>
    <a href="login.php" class="btn-secondary">
      <div class="btn-icon">→</div>
      I Already Have an Account
    </a>
  </div>
</section>

<!-- ── STATS ── -->
<div class="stats-bar">
  <div class="stat-item">
    <div class="stat-num">0</div>
    <div class="stat-label">Currently Waiting</div>
  </div>
  <div class="stat-item">
    <div class="stat-num">~0 min</div>
    <div class="stat-label">Avg. Wait Time</div>
  </div>
  <div class="stat-item">
    <div class="stat-num">Open</div>
    <div class="stat-label">System Status</div>
  </div>
  <div class="stat-item">
    <div class="stat-num">3</div>
    <div class="stat-label">Active Counters</div>
  </div>
</div>

<!-- ── FEATURES ── -->
<div class="divider"></div>
<section class="section">
  <div class="section-label"><span>Features</span></div>
  <h2 class="section-title">Everything You Need,<br>Right on Your Phone</h2>
  <p class="section-sub">Designed specifically for CICS students and faculty. Simple, fast, and fair.</p>

  <div class="features-grid">
    <div class="feature-card">
      <div class="feature-icon">🎫</div>
      <div class="feature-title">Virtual Queue Ticket</div>
      <div class="feature-desc">Select your transaction type and instantly receive a unique queue number — no physical line required.</div>
    </div>
    <div class="feature-card">
      <div class="feature-icon">📊</div>
      <div class="feature-title">Live Queue Dashboard</div>
      <div class="feature-desc">See your real-time position, estimated wait time, and who is currently being served — all in one screen.</div>
    </div>
    <div class="feature-card">
      <div class="feature-icon">🔔</div>
      <div class="feature-title">Instant Notifications</div>
      <div class="feature-desc">Get browser push alerts and Gmail notifications when your turn is approaching so you never miss your slot.</div>
    </div>
    <div class="feature-card">
      <div class="feature-icon">📁</div>
      <div class="feature-title">COR Upload</div>
      <div class="feature-desc">Upload your Certificate of Registration digitally. Admin verifies your documents before you even arrive.</div>
    </div>
    <div class="feature-card">
      <div class="feature-icon">🪟</div>
      <div class="feature-title">Multi-Counter Support</div>
      <div class="feature-desc">Multiple faculty counters operate simultaneously — the system intelligently routes and manages all queues.</div>
    </div>
    <div class="feature-card">
      <div class="feature-icon">📜</div>
      <div class="feature-title">Transaction History</div>
      <div class="feature-desc">Every completed transaction is archived. Students can reference past visits; admins get powerful analytics.</div>
    </div>
  </div>
</section>

<!-- ── HOW IT WORKS ── -->
<div class="divider"></div>
<section class="section">
  <div class="section-label"><span>How It Works</span></div>
  <h2 class="section-title">Four Simple Steps</h2>
  <p class="section-sub">From arrival to being served — the entire process is seamless.</p>

  <div class="steps-grid">
    <div class="step-item">
      <div class="step-circle">1</div>
      <div class="step-title">Register Once</div>
      <div class="step-desc">Create your account using your student number. Upload your COR for verification.</div>
    </div>
    <div class="step-item">
      <div class="step-circle">2</div>
      <div class="step-title">Join the Queue</div>
      <div class="step-desc">Select your transaction type and receive your virtual ticket number instantly.</div>
    </div>
    <div class="step-item">
      <div class="step-circle">3</div>
      <div class="step-title">Wait Anywhere</div>
      <div class="step-desc">Go to class or the canteen. Monitor your queue position from your phone in real time.</div>
    </div>
    <div class="step-item">
      <div class="step-circle">4</div>
      <div class="step-title">Get Notified</div>
      <div class="step-desc">Receive an alert when it's almost your turn. Walk in, get served, and you're done.</div>
    </div>
  </div>
</section>

<!-- ── ADMIN STRIP ── -->
<div class="admin-strip">
  <div class="admin-card">
    <div class="admin-card-text">
      <h3>Faculty & Staff Access</h3>
      <p>Manage queues, call students, view transaction history,<br>and monitor department operations from the admin panel.</p>
    </div>
    <a href="admin/login.php" class="btn-nav btn-gold" style="padding:13px 30px; font-size:14px; white-space:nowrap;">
      Admin Dashboard →
    </a>
  </div>
</div>

<!-- ── FOOTER ── -->
<footer>
  <div class="footer-left">
    <strong>SMART Q</strong> &nbsp;·&nbsp;
    College of Information and Computing Sciences &nbsp;·&nbsp;
    Zamboanga Peninsula Polytechnic State University
  </div>
  <div class="footer-right">
    <div class="status-dot"></div>
    System Online
  </div>
</footer>

</body>
</html>