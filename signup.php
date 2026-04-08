<?php
session_start();
require_once __DIR__ . "/config/database.php";

$signup_error = '';
if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $name = trim($_POST['name']);
  $email = trim($_POST['email']);
  $password = $_POST['password'];

  $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
  $stmt->bind_param("s", $email);
  $stmt->execute();
  $stmt->store_result();

  if ($stmt->num_rows > 0) {
    $signup_error = "Email already registered. Please login.";
  } else {
    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt2 = $conn->prepare("INSERT INTO users (name, email, password_hash) VALUES (?, ?, ?)");
    $stmt2->bind_param("sss", $name, $email, $password_hash);
    if ($stmt2->execute()) {
      $_SESSION['user_id'] = $stmt2->insert_id;
      $_SESSION['user_name'] = $name;
      header("Location: index.php");
      exit();
    } else {
      $signup_error = "Error: " . $stmt2->error;
    }
  }
}
?>
<!doctype html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <title>Sign Up | >_Oops!</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />

  <link
    href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Space+Grotesk:wght@600;700&display=swap"
    rel="stylesheet" />
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet" />

  <link rel="stylesheet" href="header/header.css">

  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
      font-family: "Inter", sans-serif;
    }

    body {
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      background: linear-gradient(rgba(255, 255, 255, .85), rgba(255, 255, 255, .85)), url('pat3.png') center/cover no-repeat fixed;
      color: #0f172a;
      transition: .3s;
    }

    .dark-theme {
      background: linear-gradient(rgba(11, 11, 11, .95), rgba(11, 11, 11, .95)), url('pat3.png') center/cover no-repeat fixed;
      color: #eee;
    }

    /* Signup Wrapper - Controls page length */
    .login-wrapper {
      flex: 1;
      display: flex;
      justify-content: center;
      width: 100%;
      margin-top: 30px;
      /* Tight gap with header */
      margin-bottom: 80px;
      /* Space at bottom to make page feel longer */
    }

    /* Signup Card - Compact & Original Size */
    .login-card {
      background: #fff;
      width: 360px;
      padding: 32px;
      height: fit-content;
      border-radius: 14px;
      border: 1px solid #e5e7eb;
      box-shadow: 0 10px 30px rgba(0, 0, 0, .08);
      transition: .3s;
    }

    .dark-theme .login-card {
      background: #111;
      border-color: #222;
      color: #fff;
    }

    .login-card h2 {
      text-align: center;
      margin-bottom: 24px;
      font-family: "Space Grotesk", sans-serif;
      font-weight: 700;
    }

    .input-group {
      margin-bottom: 16px;
    }

    .input-group label {
      font-size: 14px;
      margin-bottom: 6px;
      display: block;
      color: #475569;
    }

    .dark-theme .input-group label {
      color: #aaa;
    }

    .input-group input {
      width: 100%;
      padding: 12px;
      border-radius: 8px;
      border: 1px solid #d1d5db;
      outline: none;
      transition: 0.2s;
    }

    .input-group input:focus {
      border-color: #000;
    }

    .dark-theme .input-group input {
      background: #1a1a1a;
      color: #fff;
      border-color: #333;
    }

    .login-btn {
      width: 100%;
      padding: 12px;
      margin-top: 10px;
      background: #000;
      color: #fff;
      border: none;
      border-radius: 8px;
      font-weight: 600;
      cursor: pointer;
      transition: 0.2s;
    }

    .login-btn:hover {
      opacity: 0.85;
    }

    .dark-theme .login-btn {
      background: #fff;
      color: #000;
    }

    .divider {
      text-align: center;
      color: #6b7280;
      margin: 20px 0;
      font-size: 14px;
    }

    .social-login {
      display: flex;
      flex-direction: column;
      gap: 10px;
    }

    .social-btn {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 10px;
      padding: 10px;
      border-radius: 8px;
      background: #fff;
      border: 1px solid #d1d5db;
      text-decoration: none;
      color: #000;
      font-size: 14px;
      font-weight: 500;
      transition: 0.2s;
    }

    .social-btn:hover {
      background: #f8fafc;
      border-color: #000;
    }

    .dark-theme .social-btn {
      background: #111;
      color: #fff;
      border-color: #333;
    }

    .signup-text {
      text-align: center;
      margin-top: 20px;
      font-size: 14px;
      color: #64748b;
    }

    .signup-text a {
      color: #000;
      text-decoration: none;
      font-weight: 600;
    }

    .dark-theme .signup-text a {
      color: #fff;
    }

    .error-msg {
      color: #dc3545;
      background: #fff5f5;
      padding: 10px;
      border-radius: 8px;
      text-align: center;
      margin-bottom: 12px;
      font-size: 13px;
      border: 1px solid #feb2b2;
    }

    /* Footer to extend the page length */
    footer {
      border-top: 1px solid rgba(0, 0, 0, 0.05);
      padding: 40px;
      text-align: center;
      font-size: 13px;
      color: #94a3b8;
    }
  </style>
</head>

<body>

  <?php include 'header/header.php'; ?>

  <div class="login-wrapper">
    <div class="login-card">
      <h2>Create Account</h2>

      <?php if ($signup_error): ?>
        <div class="error-msg"><?= htmlspecialchars($signup_error) ?></div>
      <?php endif; ?>

      <form method="POST">
        <div class="input-group">
          <label>Name</label>
          <input type="text" name="name" placeholder="char name[];" required>
        </div>

        <div class="input-group">
          <label>Email</label>
          <input type="email" name="email" placeholder="email@example.com" required>
        </div>

        <div class="input-group">
          <label>Password</label>
          <input type="password" name="password" placeholder="Enter a strong password" required>
        </div>

        <button class="login-btn" type="submit">Sign Up</button>
      </form>

      <div class="divider">or sign up with</div>

      <div class="social-login">
        <a class="social-btn" href="google_oauth.php"><i class="fa-brands fa-google"></i> Google</a>
        <a class="social-btn" href="github_oauth.php"><i class="fa-brands fa-github"></i> GitHub</a>
      </div>

      <div class="signup-text">
        Already have an account? <a href="login.php">Login</a>
      </div>
    </div>
  </div>

  <footer>
    © 2026 >_Oops! — Learn the fundamentals properly.
  </footer>

  <script src="header/header.js"></script>

  <script>
    const toggle = document.getElementById('theme-toggle');

    if (localStorage.getItem('theme') === 'dark') {
      document.body.classList.add('dark-theme');
      if (toggle) toggle.checked = true;
    }

    if (toggle) {
      toggle.addEventListener('change', () => {
        if (toggle.checked) {
          document.body.classList.add('dark-theme');
          localStorage.setItem('theme', 'dark');
        } else {
          document.body.classList.remove('dark-theme');
          localStorage.setItem('theme', 'light');
        }
      });
    }
  </script>

</body>

</html>