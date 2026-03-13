<?php
session_start();
if (isset($_SESSION['student_number'])) {
    header('Location: dashboard.php');
    exit();
}

require_once 'config/db.php';

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_number = trim($_POST['student_number'] ?? '');
    $fullname       = trim($_POST['fullname'] ?? '');
    $date_of_birth  = trim($_POST['date_of_birth'] ?? '');
    $gmail          = trim($_POST['gmail'] ?? '');
    $contact_number = trim($_POST['contact_number'] ?? '');
    $gender         = trim($_POST['gender'] ?? '');
    $course         = trim($_POST['course'] ?? '');
    $password       = $_POST['password'] ?? '';
    $confirm        = $_POST['confirm_password'] ?? '';

    // ── Validation ──────────────────────────────
    if (empty($student_number))  $errors[] = 'Student number is required.';
    if (empty($fullname))        $errors[] = 'Full name is required.';
    if (empty($date_of_birth))   $errors[] = 'Date of birth is required.';
    if (empty($gmail))           $errors[] = 'Gmail address is required.';
    elseif (!filter_var($gmail, FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email address.';
    if (empty($gender))          $errors[] = 'Please select your gender.';
    if (empty($course))          $errors[] = 'Please select your course.';
    if (strlen($password) < 6)   $errors[] = 'Password must be at least 6 characters.';
    if ($password !== $confirm)  $errors[] = 'Passwords do not match.';

    // ── COR Upload ──────────────────────────────
    $cor_image = '';
    if (isset($_FILES['cor_image']) && $_FILES['cor_image']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['image/jpeg','image/png','image/jpg'];
        $ftype   = $_FILES['cor_image']['type'];
        $fsize   = $_FILES['cor_image']['size'];
        if (!in_array($ftype, $allowed))   $errors[] = 'COR must be a JPG or PNG image.';
        elseif ($fsize > 5 * 1024 * 1024) $errors[] = 'COR image must be under 5MB.';
        else {
            $ext      = pathinfo($_FILES['cor_image']['name'], PATHINFO_EXTENSION);
            $filename = 'cor_' . preg_replace('/[^a-zA-Z0-9]/', '', $student_number) . '_' . time() . '.' . $ext;
            $dest     = __DIR__ . '/assets/uploads/' . $filename;
            move_uploaded_file($_FILES['cor_image']['tmp_name'], $dest);
            $cor_image = $filename;
        }
    } else {
        $errors[] = 'Please upload your COR image.';
    }

    // ── Check duplicate ──────────────────────────
    if (empty($errors)) {
        $chk = $pdo->prepare('SELECT student_number FROM students WHERE student_number = ? OR gmail = ?');
        $chk->execute([$student_number, $gmail]);
        if ($chk->fetch()) $errors[] = 'Student number or Gmail already registered.';
    }

    // ── Insert ───────────────────────────────────
    if (empty($errors)) {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $ins  = $pdo->prepare('INSERT INTO students
            (student_number, password, fullname, date_of_birth, gmail, contact_number, gender, course, cor_image)
            VALUES (?,?,?,?,?,?,?,?,?)');
        $ins->execute([$student_number,$hash,$fullname,$date_of_birth,$gmail,$contact_number,$gender,$course,$cor_image]);
        $success = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Register — SMART Q</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
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
      display: flex; flex-direction: column;
    }

    /* BG grid */
    body::before {
      content:''; position:fixed; inset:0; pointer-events:none; z-index:0;
      background-image:
        linear-gradient(rgba(255,255,255,0.02) 1px, transparent 1px),
        linear-gradient(90deg, rgba(255,255,255,0.02) 1px, transparent 1px);
      background-size: 44px 44px;
    }

    /* ── NAV ── */
    nav {
      position: relative; z-index: 10;
      display: flex; align-items: center; justify-content: space-between;
      padding: 18px 50px;
      border-bottom: 1px solid var(--border);
      background: rgba(11,31,58,0.7);
      backdrop-filter: blur(14px);
    }
    .nav-brand { display:flex; align-items:center; gap:12px; text-decoration:none; }
    .nav-logo {
      width:38px; height:38px; border-radius:9px;
      background: linear-gradient(135deg, var(--gold), var(--gold-lt));
      display:flex; align-items:center; justify-content:center;
      font-family:'Playfair Display',serif; font-weight:800; font-size:16px; color:var(--navy);
      box-shadow:0 4px 16px rgba(201,168,76,0.3);
    }
    .nav-name { font-family:'Playfair Display',serif; font-size:16px; font-weight:700; color:var(--white); }
    .nav-login { font-size:13px; font-weight:500; color:var(--gray); text-decoration:none; transition:color 0.2s; }
    .nav-login:hover { color:var(--gold-lt); }

    /* ── WRAPPER ── */
    .wrapper {
      position: relative; z-index: 5;
      flex: 1;
      display: flex; align-items: flex-start; justify-content: center;
      padding: 50px 20px 60px;
    }

    /* ── CARD ── */
    .card {
      width: 100%; max-width: 680px;
      background: rgba(15,36,68,0.85);
      border: 1px solid var(--border);
      border-radius: 22px;
      overflow: hidden;
      box-shadow: 0 30px 80px rgba(0,0,0,0.4);
      backdrop-filter: blur(16px);
      animation: fadeUp 0.6s ease both;
    }
    @keyframes fadeUp {
      from { opacity:0; transform:translateY(30px); }
      to   { opacity:1; transform:translateY(0); }
    }

    /* card header */
    .card-head {
      padding: 34px 40px 28px;
      border-bottom: 1px solid var(--border);
      background: linear-gradient(135deg, rgba(26,61,110,0.4), rgba(17,43,82,0.2));
    }
    .card-head h1 {
      font-family:'Playfair Display',serif;
      font-size:26px; font-weight:700; margin-bottom:6px;
    }
    .card-head p { font-size:13px; color:var(--gray); font-weight:300; line-height:1.6; }

    /* steps indicator */
    .step-indicator {
      display:flex; align-items:center; gap:0;
      margin-top:22px;
    }
    .step-dot {
      display:flex; align-items:center; gap:8px;
      font-size:12px; font-weight:500; color:var(--gray);
    }
    .step-dot .dot {
      width:28px; height:28px; border-radius:50%;
      border:2px solid var(--border);
      display:flex; align-items:center; justify-content:center;
      font-size:11px; font-weight:700;
      transition:all 0.3s;
    }
    .step-dot.active .dot { border-color:var(--gold); color:var(--gold); background:rgba(201,168,76,0.1); }
    .step-dot.done   .dot { border-color:var(--green); color:var(--green); background:rgba(52,211,153,0.1); }
    .step-dot.active { color:var(--white); }
    .step-line { flex:1; height:1px; background:var(--border); margin:0 8px; }

    /* card body */
    .card-body { padding: 36px 40px; }

    /* ── ALERT ── */
    .alert {
      padding:14px 18px; border-radius:10px;
      font-size:13px; line-height:1.6;
      margin-bottom:22px;
      display:flex; gap:10px; align-items:flex-start;
    }
    .alert.error   { background:rgba(248,113,113,0.1); border:1px solid rgba(248,113,113,0.3); color:#fca5a5; }
    .alert.success { background:rgba(52,211,153,0.1);  border:1px solid rgba(52,211,153,0.3);  color:#6ee7b7; }
    .alert ul { margin:6px 0 0 16px; }
    .alert ul li { margin-bottom:3px; }

    /* ── FORM GRID ── */
    .form-grid {
      display:grid; grid-template-columns:1fr 1fr; gap:18px;
    }
    .form-grid .full { grid-column: 1 / -1; }

    /* ── FIELD ── */
    .field { display:flex; flex-direction:column; gap:7px; }
    .field label {
      font-size:12px; font-weight:600; letter-spacing:0.5px;
      text-transform:uppercase; color:var(--gray);
    }
    .field label span { color:var(--red); margin-left:2px; }

    .field input,
    .field select {
      background: rgba(255,255,255,0.04);
      border: 1px solid var(--border);
      border-radius: 10px;
      padding: 12px 16px;
      font-family:'DM Sans',sans-serif;
      font-size:14px; color:var(--white);
      outline:none; transition:border-color 0.2s, background 0.2s;
      width:100%;
    }
    .field input::placeholder { color:rgba(138,154,181,0.5); }
    .field select option { background:#0f2444; color:var(--white); }
    .field input:focus,
    .field select:focus {
      border-color: var(--blue);
      background: rgba(26,86,219,0.07);
    }

    /* password wrapper */
    .pw-wrap { position:relative; }
    .pw-wrap input { padding-right:46px; }
    .pw-toggle {
      position:absolute; right:14px; top:50%; transform:translateY(-50%);
      background:none; border:none; cursor:pointer;
      color:var(--gray); font-size:16px; padding:4px;
      transition:color 0.2s;
    }
    .pw-toggle:hover { color:var(--white); }

    /* COR upload */
    .upload-zone {
      border: 2px dashed rgba(201,168,76,0.25);
      border-radius:12px;
      padding:26px 20px;
      text-align:center;
      cursor:pointer;
      transition:all 0.25s;
      background:rgba(201,168,76,0.03);
      position:relative;
    }
    .upload-zone:hover,
    .upload-zone.dragover {
      border-color:var(--gold);
      background:rgba(201,168,76,0.07);
    }
    .upload-zone input[type=file] {
      position:absolute; inset:0; opacity:0; cursor:pointer; width:100%; height:100%;
    }
    .upload-icon { font-size:28px; margin-bottom:8px; }
    .upload-text { font-size:13px; color:var(--gray); line-height:1.6; }
    .upload-text strong { color:var(--gold-lt); }
    .upload-preview {
      display:none;
      margin-top:12px;
      border-radius:8px;
      overflow:hidden;
      max-height:160px;
    }
    .upload-preview img { width:100%; object-fit:cover; }

    /* section divider */
    .section-sep {
      grid-column:1/-1;
      display:flex; align-items:center; gap:12px;
      margin:6px 0 2px;
    }
    .section-sep span {
      font-size:11px; font-weight:600; text-transform:uppercase;
      letter-spacing:1.5px; color:var(--gold); white-space:nowrap;
    }
    .section-sep::before,
    .section-sep::after {
      content:''; flex:1; height:1px; background:var(--border);
    }

    /* submit */
    .btn-submit {
      width:100%; padding:15px;
      background:linear-gradient(135deg,#1a56db,#2563eb);
      border:none; border-radius:12px;
      font-family:'DM Sans',sans-serif;
      font-size:15px; font-weight:600; color:white;
      cursor:pointer; margin-top:28px;
      box-shadow:0 6px 28px rgba(26,86,219,0.4);
      transition:all 0.25s;
      display:flex; align-items:center; justify-content:center; gap:10px;
    }
    .btn-submit:hover { transform:translateY(-2px); box-shadow:0 10px 36px rgba(26,86,219,0.55); }
    .btn-submit:active { transform:translateY(0); }

    .login-link {
      text-align:center; margin-top:20px;
      font-size:13px; color:var(--gray);
    }
    .login-link a { color:var(--gold-lt); text-decoration:none; font-weight:500; }
    .login-link a:hover { text-decoration:underline; }

    /* success screen */
    .success-screen {
      text-align:center; padding:20px 0 10px;
    }
    .success-icon {
      font-size:60px; margin-bottom:20px;
      animation:bounceIn 0.6s ease both;
    }
    @keyframes bounceIn {
      0%   { transform:scale(0); opacity:0; }
      60%  { transform:scale(1.15); }
      100% { transform:scale(1); opacity:1; }
    }
    .success-screen h2 {
      font-family:'Playfair Display',serif;
      font-size:24px; font-weight:700; margin-bottom:10px;
    }
    .success-screen p { font-size:14px; color:var(--gray); line-height:1.7; margin-bottom:28px; }
    .btn-login {
      display:inline-flex; align-items:center; gap:8px;
      padding:13px 32px;
      background:linear-gradient(135deg,var(--gold),var(--gold-lt));
      border:none; border-radius:10px;
      font-family:'DM Sans',sans-serif;
      font-size:14px; font-weight:700; color:var(--navy);
      cursor:pointer; text-decoration:none;
      box-shadow:0 6px 24px rgba(201,168,76,0.35);
      transition:all 0.2s;
    }
    .btn-login:hover { transform:translateY(-2px); box-shadow:0 10px 32px rgba(201,168,76,0.5); }

    @media(max-width:600px){
      nav { padding:16px 20px; }
      .card-head, .card-body { padding-left:22px; padding-right:22px; }
      .form-grid { grid-template-columns:1fr; }
      .form-grid .full { grid-column:1; }
    }
  </style>
</head>
<body>

<!-- NAV -->
<nav>
  <a href="index.php" class="nav-brand">
    <div class="nav-logo">Q</div>
    <span class="nav-name">SMART Q</span>
  </a>
  <a href="login.php" class="nav-login">Already have an account? Log in →</a>
</nav>

<div class="wrapper">
  <div class="card">

    <!-- CARD HEAD -->
    <div class="card-head">
      <h1>Create Your Account</h1>
      <p>Register once using your student number to access the CICS digital queue system.</p>

      <div class="step-indicator">
        <div class="step-dot active">
          <div class="dot">1</div>
          <span>Register</span>
        </div>
        <div class="step-line"></div>
        <div class="step-dot">
          <div class="dot">2</div>
          <span>Log In</span>
        </div>
        <div class="step-line"></div>
        <div class="step-dot">
          <div class="dot">3</div>
          <span>Get Queue</span>
        </div>
      </div>
    </div>

    <!-- CARD BODY -->
    <div class="card-body">

      <?php if ($success): ?>
      <!-- SUCCESS -->
      <div class="success-screen">
        <div class="success-icon">🎓</div>
        <h2>Registration Complete!</h2>
        <p>Your account has been created successfully.<br>You can now log in and join the queue.</p>
        <a href="login.php" class="btn-login">Go to Login →</a>
      </div>

      <?php else: ?>

      <!-- ERRORS -->
      <?php if (!empty($errors)): ?>
      <div class="alert error">
        <span>⚠️</span>
        <div>
          <strong>Please fix the following:</strong>
          <ul>
            <?php foreach($errors as $e): ?>
              <li><?= htmlspecialchars($e) ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      </div>
      <?php endif; ?>

      <!-- FORM -->
      <form method="POST" enctype="multipart/form-data" id="regForm">
        <div class="form-grid">

          <!-- SECTION: Account Info -->
          <div class="section-sep full"><span>Account Information</span></div>

          <div class="field full">
            <label>Student Number <span>*</span></label>
            <input type="text" name="student_number"
              placeholder="e.g. 2023-0001"
              value="<?= htmlspecialchars($_POST['student_number'] ?? '') ?>"
              required/>
          </div>

          <div class="field">
            <label>Password <span>*</span></label>
            <div class="pw-wrap">
              <input type="password" name="password" id="pw1" placeholder="Min. 6 characters" required/>
              <button type="button" class="pw-toggle" onclick="togglePw('pw1',this)">👁️</button>
            </div>
          </div>

          <div class="field">
            <label>Confirm Password <span>*</span></label>
            <div class="pw-wrap">
              <input type="password" name="confirm_password" id="pw2" placeholder="Repeat password" required/>
              <button type="button" class="pw-toggle" onclick="togglePw('pw2',this)">👁️</button>
            </div>
          </div>

          <!-- SECTION: Personal Info -->
          <div class="section-sep full"><span>Personal Information</span></div>

          <div class="field full">
            <label>Full Name <span>*</span></label>
            <input type="text" name="fullname"
              placeholder="Last Name, First Name Middle Name"
              value="<?= htmlspecialchars($_POST['fullname'] ?? '') ?>"
              required/>
          </div>

          <div class="field">
            <label>Date of Birth <span>*</span></label>
            <input type="date" name="date_of_birth"
              value="<?= htmlspecialchars($_POST['date_of_birth'] ?? '') ?>"
              required/>
          </div>

          <div class="field">
            <label>Gender <span>*</span></label>
            <select name="gender" required>
              <option value="" disabled <?= empty($_POST['gender']) ? 'selected' : '' ?>>Select gender</option>
              <option value="Male"   <?= ($_POST['gender'] ?? '') === 'Male'   ? 'selected' : '' ?>>Male</option>
              <option value="Female" <?= ($_POST['gender'] ?? '') === 'Female' ? 'selected' : '' ?>>Female</option>
              <option value="Other"  <?= ($_POST['gender'] ?? '') === 'Other'  ? 'selected' : '' ?>>Other</option>
            </select>
          </div>

          <div class="field">
            <label>Gmail Address <span>*</span></label>
            <input type="email" name="gmail"
              placeholder="yourname@gmail.com"
              value="<?= htmlspecialchars($_POST['gmail'] ?? '') ?>"
              required/>
          </div>

          <div class="field">
            <label>Contact Number</label>
            <input type="text" name="contact_number"
              placeholder="09XXXXXXXXX"
              value="<?= htmlspecialchars($_POST['contact_number'] ?? '') ?>"/>
          </div>

          <div class="field full">
            <label>Course <span>*</span></label>
            <select name="course" required>
              <option value="" disabled <?= empty($_POST['course']) ? 'selected' : '' ?>>Select your course</option>
              <option value="BSIT"  <?= ($_POST['course'] ?? '') === 'BSIT'  ? 'selected' : '' ?>>BS Information Technology</option>
              <option value="BSCS"  <?= ($_POST['course'] ?? '') === 'BSCS'  ? 'selected' : '' ?>>BS Computer Science</option>
              <option value="BSIS"  <?= ($_POST['course'] ?? '') === 'BSIS'  ? 'selected' : '' ?>>BS Information Systems</option>
              <option value="ACT"   <?= ($_POST['course'] ?? '') === 'ACT'   ? 'selected' : '' ?>>Associate in Computer Technology</option>
              <option value="Other" <?= ($_POST['course'] ?? '') === 'Other' ? 'selected' : '' ?>>Other</option>
            </select>
          </div>

          <!-- SECTION: COR Upload -->
          <div class="section-sep full"><span>Certificate of Registration</span></div>

          <div class="field full">
            <label>Upload COR Image <span>*</span></label>
            <div class="upload-zone" id="uploadZone">
              <input type="file" name="cor_image" id="corFile" accept="image/png,image/jpeg,image/jpg"
                onchange="previewCOR(this)"/>
              <div class="upload-icon">📄</div>
              <div class="upload-text">
                <strong>Click to upload</strong> or drag and drop your COR<br>
                PNG or JPG — max 5MB
              </div>
              <div class="upload-preview" id="corPreview">
                <img id="corImg" src="" alt="COR Preview"/>
              </div>
            </div>
          </div>

        </div><!-- /form-grid -->

        <button type="submit" class="btn-submit">
          <span>🎓</span> Create My Account
        </button>

        <div class="login-link">
          Already registered? <a href="login.php">Log in here</a>
        </div>
      </form>

      <?php endif; ?>
    </div><!-- /card-body -->
  </div><!-- /card -->
</div><!-- /wrapper -->

<script>
  function togglePw(id, btn) {
    const inp = document.getElementById(id);
    inp.type = inp.type === 'password' ? 'text' : 'password';
    btn.textContent = inp.type === 'password' ? '👁️' : '🙈';
  }

  function previewCOR(input) {
    if (input.files && input.files[0]) {
      const reader = new FileReader();
      reader.onload = e => {
        document.getElementById('corImg').src = e.target.result;
        document.getElementById('corPreview').style.display = 'block';
        document.querySelector('#uploadZone .upload-icon').textContent = '✅';
        document.querySelector('#uploadZone .upload-text').innerHTML =
          '<strong>' + input.files[0].name + '</strong><br><span style="color:#8a9ab5;font-size:12px;">Click to change</span>';
      };
      reader.readAsDataURL(input.files[0]);
    }
  }

  // Drag and drop highlight
  const zone = document.getElementById('uploadZone');
  if (zone) {
    zone.addEventListener('dragover',  e => { e.preventDefault(); zone.classList.add('dragover'); });
    zone.addEventListener('dragleave', () => zone.classList.remove('dragover'));
    zone.addEventListener('drop',      e => { e.preventDefault(); zone.classList.remove('dragover'); });
  }
</script>
</body>
</html>