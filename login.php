<?php
session_start();
$conn = new mysqli("localhost", "root", "", "coding");
if ($conn->connect_error)
  die("Connection failed: " . $conn->connect_error);

// If already logged in → redirect
if (isset($_SESSION['user_id'])) {
  header("Location: home2.php");
  exit();
}

// Handle login form submission
$login_error = '';
if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $email = trim($_POST['email']);
  $password = $_POST['password'];

  $stmt = $conn->prepare("SELECT id, name, password_hash FROM users WHERE email = ?");
  $stmt->bind_param("s", $email);
  $stmt->execute();
  $stmt->store_result();
  $stmt->bind_result($id, $name, $password_hash);
  $stmt->fetch();

  if ($stmt->num_rows > 0 && password_verify($password, $password_hash)) {
    $_SESSION['user_id'] = $id;
    $_SESSION['user_name'] = $name;
    header("Location: home2.php");
    exit();
  } else {
    $login_error = "Invalid email or password.";
  }
}
?>
<!doctype html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>Login | >_Oops!</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <link
    href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Space+Grotesk:wght@600;700&display=swap"
    rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">

  <!-- HEADER CSS -->
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
      background: linear-gradient(rgba(255, 255, 255, .9), rgba(255, 255, 255, .9)), url('pat3.png') center/cover no-repeat fixed;
      color: #0f172a;
      transition: .3s;
    }

    .dark-theme {
      background: linear-gradient(rgba(11, 11, 11, .95), rgba(11, 11, 11, .95)), url('pat3.png') center/cover no-repeat fixed;
      color: #eee;
    }

    /* LOGIN WRAPPER */
    .login-wrapper {
      flex: 1;
      /* Pushes the footer to the bottom */
      display: flex;
      justify-content: center;
      margin-top: 30px;
      /* Tight gap between header and card */
      margin-bottom: 80px;
      /* Space below the card to make the page feel longer */
    }

    /* LOGIN CARD - Kept compact and original size */
    .login-card {
      background: #fff;
      width: 360px;
      padding: 32px;
      /* Back to original compact padding */
      height: fit-content;
      /* Ensures it doesn't stretch tall */
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
      font-weight: 600;
      font-family: "Space Grotesk", sans-serif;
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

    .input-group input {
      width: 100%;
      padding: 12px;
      border-radius: 8px;
      border: 1px solid #d1d5db;
      outline: none;
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
      font-size: 15px;
      cursor: pointer;
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
      cursor: pointer;
      text-decoration: none;
      color: #000;
      transition: .2s;
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
      color: #475569;
    }

    /* FOOTER - This is what makes the page feel longer/complete */
    footer {
      border-top: 1px solid rgba(0, 0, 0, 0.05);
      padding: 40px;
      text-align: center;
      font-size: 13px;
      color: #94a3b8;
      background: transparent;
    }
  </style>
</head>

<body>

  <?php include 'header/header.php'; ?>

  <div class="login-wrapper">
    <div class="login-card">
      <h2>Welcome Back</h2>

      <?php if ($login_error): ?>
        <div class="error-msg"><?= htmlspecialchars($login_error) ?></div>
      <?php endif; ?>

      <form method="POST">
        <div class="input-group">
          <label>Email</label>
          <input type="email" name="email" placeholder="Email address" required />
        </div>

        <div class="input-group">
          <label>Password</label>
          <input type="password" name="password" placeholder="Your password" required />
        </div>

        <button class="login-btn" type="submit">Sign In</button>
      </form>

      <div class="divider">or sign in with</div>

      <div class="social-login">
        <a class="social-btn" href="google_oauth.php">
          <i class="fa-brands fa-google"></i> Google
        </a>

        <a class="social-btn" href="github_oauth.php">
          <i class="fa-brands fa-github"></i> GitHub
        </a>
      </div>

      <div class="signup-text">
        Don't have an account? <a href="signup.php">Sign Up</a>
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
      toggle.checked = true;
    }

    toggle.addEventListener('change', () => {
      if (toggle.checked) {
        document.body.classList.add('dark-theme');
        localStorage.setItem('theme', 'dark');
      } else {
        document.body.classList.remove('dark-theme');
        localStorage.setItem('theme', 'light');
      }
    });
  </script>

</body>

</html>