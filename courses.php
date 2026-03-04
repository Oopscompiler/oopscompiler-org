<?php
session_start();
$conn = new mysqli("localhost", "root", "", "coding");
if ($conn->connect_error) {
  die("Database connection failed: " . $conn->connect_error);
}

/* Language logic */
$lang = 'C';
if (isset($_GET['lang'])) {
  $inputLang = strtoupper(trim($_GET['lang']));
  $lang = ($inputLang === 'CPP' || $inputLang === 'C++' || $inputLang === 'C ') ? 'CPP' : 'C';
}

$sql = "
SELECT t.topic_id, t.topic_name, t.description,
       COUNT(DISTINCT qv.variant_id) AS total_questions
FROM topics t
JOIN questions q ON t.topic_id = q.topic_id
JOIN question_variants qv ON q.question_id = qv.question_id
WHERE qv.language = ?
GROUP BY t.topic_id, t.topic_name, t.description
HAVING total_questions > 0
ORDER BY t.topic_name
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $lang);
$stmt->execute();
$result = $stmt->get_result();

$displayLang = ($lang === 'CPP') ? 'C++' : 'C';
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title><?= $displayLang ?> Courses | ptrmaster</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <link
    href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@600;700&family=IBM+Plex+Sans:wght@400;500&display=swap"
    rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet" />

  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: "IBM Plex Sans", sans-serif;
      background: #fff;
      color: #000;
      transition: .3s;
    }

    /* DARK THEME */
    .dark-theme {
      background: #0b0b0b;
      color: #eee;
    }

    .dark-theme header {
      background: #0b0b0b;
      border-bottom: 1px solid #222;
    }

    /* HEADER */
    header {
      position: sticky;
      top: 0;
      background: #fff;
      border-bottom: 1px solid rgba(0, 0, 0, .1);
      z-index: 1000;
    }

    .nav-container {
      max-width: 1200px;
      margin: auto;
      padding: 18px 32px;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    /* LOGO */
    .logo a {
      font-family: "Space Grotesk", sans-serif;
      font-size: 20px;
      font-weight: 700;
      text-decoration: none;
    }

    .prompt {
      color: #000;
    }

    .brand {
      color: #000;
    }

    .dark-theme .prompt,
    .dark-theme .brand {
      color: #fff;
    }

    nav.nav-links {
      display: flex;
      gap: 34px;
    }

    nav.nav-links a {
      font-size: 14px;
      color: #444;
      text-decoration: none;
      font-weight: 500;
    }

    .dark-theme nav.nav-links a {
      color: #fff;
    }

    .header-right {
      display: flex;
      align-items: center;
      gap: 20px;
    }

    /* LOGIN BUTTON */
    .login {
      font-size: 14px;
      padding: 7px 16px;
      border: 1px solid #000;
      text-decoration: none;
      color: #000;
    }

    .login:hover {
      background: #000;
      color: #fff;
    }

    .dark-theme .login {
      border-color: #fff;
      color: #fff;
    }

    .dark-theme .login:hover {
      background: #fff;
      color: #000;
    }

    /* PROFILE DROPDOWN */
    .profile-container {
      position: relative;
    }

    .profile-box {
      cursor: pointer;
      padding: 7px 16px;
      border: 1px solid #000;
      font-size: 14px;
      display: flex;
      align-items: center;
      gap: 6px;
    }

    .profile-box:hover {
      background: #000;
      color: #fff;
    }

    .dark-theme .profile-box {
      border-color: #fff;
      color: #fff;
    }

    .dark-theme .profile-box:hover {
      background: #fff;
      color: #000;
    }

    .profile-dropdown {
      position: absolute;
      top: 110%;
      right: 0;
      background: #fff;
      border: 1px solid #ddd;
      border-radius: 8px;
      min-width: 140px;
      box-shadow: 0 10px 25px rgba(0, 0, 0, .08);
      display: none;
      overflow: hidden;
    }

    .profile-dropdown a {
      display: block;
      padding: 10px 14px;
      text-decoration: none;
      color: #000;
      font-size: 14px;
    }

    .profile-dropdown a:hover {
      background: #000;
      color: #fff;
    }

    .dark-theme .profile-dropdown {
      background: #111;
      border-color: #333;
    }

    .dark-theme .profile-dropdown a {
      color: #fff;
    }

    .dark-theme .profile-dropdown a:hover {
      background: #fff;
      color: #000;
    }

    /* THEME SWITCH */
    .theme-switch {
      position: relative;
      display: inline-block;
      width: 44px;
      height: 22px;
    }

    .theme-switch input {
      opacity: 0;
      width: 0;
      height: 0;
    }

    .slider {
      position: absolute;
      cursor: pointer;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: #ccc;
      transition: .4s;
      border-radius: 34px;
    }

    .slider:before {
      position: absolute;
      content: "";
      height: 16px;
      width: 16px;
      left: 3px;
      bottom: 3px;
      background: white;
      transition: .4s;
      border-radius: 50%;
    }

    input:checked+.slider {
      background: #333;
    }

    input:checked+.slider:before {
      transform: translateX(22px);
    }

    /* COURSES SECTION */
    .courses {
      max-width: 1200px;
      margin: 64px auto 120px;
      padding: 0 32px;
    }

    .courses h1 {
      font-family: "Space Grotesk", sans-serif;
      font-size: 36px;
      margin-bottom: 40px;
    }

    .course-card {
      display: block;
      border: 1px solid #ccc;
      padding: 32px;
      margin-bottom: 28px;
      text-decoration: none;
      color: inherit;
      transition: .3s;
    }

    .course-card:hover {
      border-color: #000;
      transform: translateX(6px);
    }

    .course-title {
      font-family: "Space Grotesk", sans-serif;
      font-size: 24px;
      margin-bottom: 10px;
    }

    .course-desc {
      font-size: 15px;
      color: #666;
      max-width: 700px;
    }

    .course-meta {
      margin-top: 16px;
      font-size: 13px;
      color: #777;
    }

    .dark-theme .course-card {
      border-color: #333;
      background: #111;
    }

    .dark-theme .course-desc {
      color: #aaa;
    }
  </style>
</head>

<body>

  <header>
    <div class="nav-container">

      <div class="logo">
        <a href="home2.php">
          <span class="prompt">&gt;_0ops! </span><span class="brand"></span>
        </a>
      </div>

      <nav class="nav-links">
        <a href="mycourses.php">My Courses</a>
        <a href="#">Practice</a>
        <a href="#">About</a>
      </nav>

      <div class="header-right">

        <?php if (isset($_SESSION['user_id'])): ?>
          <div class="profile-container">
            <div class="profile-box" id="profileToggle">
              <i class="fa-solid fa-user"></i>
              <?= htmlspecialchars($_SESSION['user_name']); ?>
              <i class="fa-solid fa-chevron-down" style="font-size:12px;"></i>
            </div>
            <div class="profile-dropdown" id="profileDropdown">
              <a href="profile.php"><i class="fa-solid fa-id-card"></i> Profile</a>
              <a href="logout.php"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
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

  <section class="courses">
    <h1><?= $displayLang ?> Programming Courses</h1>

    <?php if ($result->num_rows === 0): ?>
      <p>No <?= $displayLang ?> courses available yet.</p>
    <?php else: ?>
      <?php while ($row = $result->fetch_assoc()): ?>
        <a class="course-card" href="questions.php?topic_id=<?= $row['topic_id'] ?>&lang=<?= $lang ?>">
          <div class="course-title"><?= htmlspecialchars($row['topic_name']) ?></div>
          <div class="course-desc"><?= htmlspecialchars($row['description']) ?></div>
          <div class="course-meta"><?= $row['total_questions'] ?> exercises</div>
        </a>
      <?php endwhile; ?>
    <?php endif; ?>
  </section>

  <script>
    /* THEME TOGGLE */
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

    /* PROFILE DROPDOWN */
    const profileToggle = document.getElementById('profileToggle');
    const profileDropdown = document.getElementById('profileDropdown');
    if (profileToggle) {
      profileToggle.addEventListener('click', function (e) {
        e.stopPropagation();
        profileDropdown.style.display =
          profileDropdown.style.display === 'block' ? 'none' : 'block';
      });
      document.addEventListener('click', function () {
        profileDropdown.style.display = 'none';
      });
    }
  </script>

</body>

</html>