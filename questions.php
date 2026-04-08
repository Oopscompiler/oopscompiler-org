<?php
session_start();
$user_id = $_SESSION['user_id'] ?? 0;
$is_logged_in = $user_id > 0;

require_once __DIR__ . "/config/database.php";

$topic_id = intval($_GET['topic_id'] ?? 0);

$lang = 'C';
if (isset($_GET['lang'])) {
    $inputLang = strtoupper(trim($_GET['lang']));
    // Normalize language string
    if ($inputLang === 'CPP' || $inputLang === 'C++' || $inputLang === 'C ') $lang = 'CPP';
}

/* Fetch Topic Name */
$stmtTopic = $conn->prepare("SELECT topic_name FROM topics WHERE topic_id = ?");
$stmtTopic->bind_param("i", $topic_id);
$stmtTopic->execute();
$topicRes = $stmtTopic->get_result();
$topicName = ($topicRes && $topicRes->num_rows > 0) ? $topicRes->fetch_assoc()['topic_name'] : "Topic";

/* SQL for Questions (Incomplete) */
$sql_incomplete = "
SELECT q.question_id, q.title, q.description, q.difficulty
FROM questions q
JOIN question_variants qv ON q.question_id = qv.question_id
WHERE q.topic_id = ? AND qv.language = ?
AND q.question_id NOT IN (
    SELECT question_id FROM user_completed_questions WHERE user_id = ?
)
ORDER BY FIELD(q.difficulty, 'Easy', 'Medium', 'Hard') ASC
";
$stmt = $conn->prepare($sql_incomplete);
$stmt->bind_param("isi", $topic_id, $lang, $user_id);
$stmt->execute();
$incomplete = $stmt->get_result();

/* SQL for Questions (Completed) */
$sql_completed = "
SELECT q.question_id, q.title, q.description, q.difficulty
FROM questions q
JOIN question_variants qv ON q.question_id = qv.question_id
WHERE q.topic_id = ? AND qv.language = ?
AND q.question_id IN (
    SELECT question_id FROM user_completed_questions WHERE user_id = ?
)
ORDER BY FIELD(q.difficulty, 'Easy', 'Medium', 'Hard') ASC
";
$stmt2 = $conn->prepare($sql_completed);
$stmt2->bind_param("isi", $topic_id, $lang, $user_id);
$stmt2->execute();
$completed = $stmt2->get_result();

$displayLang = ($lang === 'CPP') ? 'C++' : 'C';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($topicName) ?> | >_Oops!</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@600;700&family=IBM+Plex+Sans:wght@400;500&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet" />

<style>
/* =====================
    RESET & LAYOUT
   ===================== */
* { margin:0; padding:0; box-sizing:border-box; }
body { 
    font-family: "IBM Plex Sans", sans-serif; 
    background: #fff; color: #000; 
    transition: background 0.3s ease, color 0.3s ease;
}

/* =====================
    HEADER STYLES
   ===================== */
header { position: sticky; top: 0; background: #fff; border-bottom: 1px solid rgba(0,0,0,0.1); z-index: 1000; }
.nav-container { max-width: 1200px; margin: auto; padding: 18px 32px; display: flex; justify-content: space-between; align-items: center; }
.logo a { font-family: "Space Grotesk", sans-serif; font-size: 22px; font-weight: 700; text-decoration: none; color: #000; }
nav.nav-links { display: flex; gap: 34px; }
nav.nav-links a { font-size: 14px; color: #444; text-decoration: none; font-weight: 500; }
.header-right { display: flex; align-items: center; gap: 20px; }
.login, .profile { font-size: 14px; padding: 7px 16px; border: 1px solid #000; text-decoration: none; color: #000; }
.login:hover, .profile:hover { background: #000; color: #fff; }

/* Theme Toggle */
.theme-switch { position: relative; display: inline-block; width: 44px; height: 22px; }
.theme-switch input { opacity: 0; width: 0; height: 0; }
.slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #ccc; transition: 0.4s; border-radius: 34px; }
.slider:before { position: absolute; content: ""; height: 16px; width: 16px; left: 3px; bottom: 3px; background-color: white; transition: 0.4s; border-radius: 50%; }
input:checked + .slider { background-color: #333; }
input:checked + .slider:before { transform: translateX(22px); }

/* =====================
    QUESTION LIST
   ===================== */
.questions { max-width: 1200px; margin: 64px auto 120px; padding: 0 32px; }
.questions h1 { font-family: "Space Grotesk", sans-serif; font-size: 32px; margin-bottom: 8px; }
.topic-meta { color: #666; margin-bottom: 48px; font-size: 14px; }

.question-card {
    border: 1px solid #ccc; padding: 24px; margin-bottom: 20px; 
    transition: transform 0.25s ease, border 0.25s ease; cursor: pointer;
    background: #fff; display: block; text-decoration: none; color: inherit;
}
.question-card:hover { border-color: #000; transform: translateX(6px); }

.question-title { font-family: "Space Grotesk", sans-serif; font-size: 20px; margin-bottom: 8px; }
.question-desc { font-size: 15px; color: #666; margin-bottom: 12px; line-height: 1.5; }
.difficulty { padding: 4px 10px; border-radius: 4px; font-size: 11px; font-weight: 600; text-transform: uppercase; color: #fff; }
.easy { background: #28a745; }
.medium { background: #ffc107; color: #000; }
.hard { background: #dc3545; }

.completed-section { margin-top: 64px; border-top: 1px solid #eee; padding-top: 48px; }
.completed-section h2 { font-family: "Space Grotesk", sans-serif; margin-bottom: 24px; color: #444; }
.question-card.completed { opacity: 0.65; border-style: dashed; }

/* =====================
    DARK THEME
   ===================== */
.dark-theme { background-color: #0b0b0b; color: #eee; }
.dark-theme header { background-color: #0b0b0b; border-bottom: 1px solid #222; }
.dark-theme .logo a, .dark-theme nav.nav-links a, .dark-theme .login, .dark-theme .profile { color: #fff; border-color: #fff; }
.dark-theme .login:hover, .dark-theme .profile:hover { background: #fff; color: #000; }
.dark-theme .question-card { background: #111; border-color: #333; }
.dark-theme .question-desc, .dark-theme .topic-meta { color: #aaa; }
</style>
</head>

<body>

<header>
  <div class="nav-container">
    <div class="logo"><a href="index.php">>_Oops!</a></div>
    <nav class="nav-links">
      <a href="mycourses.php">My Courses</a>
      <a href="#">Practice</a>
      <a href="#">About</a>
    </nav>
    <div class="header-right">
      <?php if ($user_id): ?>
        <a class="profile" href="profile.php"><i class="fa-solid fa-user"></i> <?= htmlspecialchars($_SESSION['user_name'] ?? 'Profile') ?></a>
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

<section class="questions">
    <h1><?= htmlspecialchars($topicName) ?></h1>
    <p class="topic-meta"><?= $displayLang ?> Programming Track • Select a challenge to begin</p>

    <?php if ($incomplete->num_rows === 0 && $completed->num_rows === 0): ?>
        <p>No questions found for this topic yet.</p>
    <?php elseif ($incomplete->num_rows === 0): ?>
        <p style="color: #28a745; font-weight: 500;">✓ You've conquered all challenges in this topic!</p>
    <?php else: ?>
        <?php while ($row = $incomplete->fetch_assoc()): ?>
            <a class="question-card" 
href="<?= $is_logged_in 
    ? "compiler_test/compilerindex.php?question_id={$row['question_id']}&lang={$lang}&topic_id={$topic_id}" 
    : "login.php" ?>">
                <div class="question-title"><?= htmlspecialchars($row['title']) ?></div>
                <div class="question-desc"><?= htmlspecialchars($row['description']) ?></div>
                <div class="question-meta">
                    <span class="difficulty <?= strtolower($row['difficulty']) ?>"><?= $row['difficulty'] ?></span>
                </div>
            </a>
        <?php endwhile; ?>
    <?php endif; ?>

    <?php if ($is_logged_in && $completed->num_rows > 0): ?>
        <div class="completed-section">
            <h2>Completed Challenges</h2>
            <?php while ($row = $completed->fetch_assoc()): ?>
                <a class="question-card completed" href="compiler_test/compilerindex.php?question_id=<?= $row['question_id'] ?>&lang=<?= $lang ?>&topic_id=<?= $topic_id ?>">
                    <div class="question-title"><?= htmlspecialchars($row['title']) ?></div>
                    <div class="question-desc"><?= htmlspecialchars($row['description']) ?></div>
                    <div class="question-meta">
                        <span class="difficulty <?= strtolower($row['difficulty']) ?>"><?= $row['difficulty'] ?></span>
                        <span style="margin-left: 10px; color: #28a745;"><i class="fa-solid fa-circle-check"></i> Solved</span>
                    </div>
                </a>
            <?php endwhile; ?>
        </div>
    <?php endif; ?>
</section>

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