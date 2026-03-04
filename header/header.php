<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}
$user_id = $_SESSION['user_id'] ?? null;
?>

<link rel="stylesheet" href="header/header.css">

<header>
  <div class="nav-container">
    <div class="logo"><a href="home2.php">&gt;_Oops!</a></div>

    <nav class="nav">
      <a href="mycourses.php">My Courses</a>
      <a href="#">Practice</a>
      <a href="#">About</a>
    </nav>

    <div class="header-right">
      <?php if ($user_id): ?>
        <div class="profile-box" id="profileBox">
          <div class="profile">
            <?= htmlspecialchars($_SESSION['user_name'] ?? 'Profile') ?>
            <span class="arrow">▾</span>
          </div>

          <div class="dropdown" id="profileDropdown">
            <a href="profile.php">Profile</a>
            <a href="logout.php">Logout</a>
          </div>
        </div>
      <?php else: ?>
        <a class="login" href="login.php">Login</a>
      <?php endif; ?>

      <label class="theme-switch">
        <input type="checkbox" id="theme-toggle" />
        <span class="slider"></span>
      </label>
    </div>
  </div>
</header>