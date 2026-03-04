<?php
session_start();
if(!isset($_SESSION['user_id'])){
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'] ?? 'User';

$conn = new mysqli("localhost","root","","coding");
if($conn->connect_error) die("DB connection failed: ".$conn->connect_error);

/* Fetch all topics */
$sql_topics = "SELECT t.topic_id, t.topic_name, t.description
FROM topics t
ORDER BY t.topic_name";
$topics_res = $conn->query($sql_topics);

/* Prepare arrays */
$on_progress = [];
$completed = [];

/* Loop through topics to check user's progress */
while($topic = $topics_res->fetch_assoc()){
    $topic_id = $topic['topic_id'];

    // Total questions in this topic
    $sql_total = "
    SELECT COUNT(DISTINCT qv.variant_id) AS total_questions
    FROM questions q
    JOIN question_variants qv ON q.question_id = qv.question_id
    WHERE q.topic_id = ?";
    $stmt_total = $conn->prepare($sql_total);
    $stmt_total->bind_param("i", $topic_id);
    $stmt_total->execute();
    $res_total = $stmt_total->get_result()->fetch_assoc();
    $total_questions = $res_total['total_questions'] ?? 0;

    if($total_questions == 0) continue;

    // Completed questions by user
    $sql_completed = "
    SELECT COUNT(*) AS completed_questions
    FROM user_completed_questions
    WHERE user_id = ? AND topic_id = ?";
    $stmt_c = $conn->prepare($sql_completed);
    $stmt_c->bind_param("ii", $user_id, $topic_id);
    $stmt_c->execute();
    $res_c = $stmt_c->get_result()->fetch_assoc();
    $completed_questions = $res_c['completed_questions'] ?? 0;

    // Categorize
    if($completed_questions >= $total_questions){
        $completed[] = [
            'topic_id'=>$topic_id,
            'topic_name'=>$topic['topic_name'],
            'description'=>$topic['description'],
            'total_questions'=>$total_questions
        ];
    } else {
        $on_progress[] = [
            'topic_id'=>$topic_id,
            'topic_name'=>$topic['topic_name'],
            'description'=>$topic['description'],
            'total_questions'=>$total_questions,
            'completed_questions'=>$completed_questions
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>My Courses | ptrmaster</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@600;700&family=IBM+Plex+Sans:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="header/header.css">
<style>
body { font-family:"IBM Plex Sans", sans-serif; background:#fff; color:#000; margin:0; padding:0; }
.courses { max-width:1200px; margin:100px auto 120px; padding:0 32px; }
.courses h2 { font-family:"Space Grotesk",sans-serif; font-size:28px; margin-bottom:20px; border-bottom:1px solid #ccc; padding-bottom:6px; }
.course-card { border:1px solid #ccc; padding:24px; margin-bottom:20px; cursor:pointer; transition:0.3s; display:block; text-decoration:none; color:inherit; }
.course-card:hover { border-color:#000; transform:translateX(6px); }
.course-title { font-family:"Space Grotesk",sans-serif; font-size:22px; margin-bottom:8px; }
.course-desc { font-size:15px; color:#666; margin-bottom:10px; }
.course-meta { font-size:13px; color:#777; }
.progress-bar { height:8px; background:#eee; border-radius:4px; overflow:hidden; margin-top:6px; }
.progress-fill { height:100%; background:#28a745; width:0%; }
</style>
</head>
<body>

<!-- Header -->
<header>
  <div class="nav-container">
    <div class="logo"><a href="home2.php">ptrmaster</a></div>
    <nav>
      <a href="mycourses.php">My Courses</a>
      <a href="#">Practice</a>
      <a href="#">About</a>
    </nav>
    <div class="header-right">
      <a href="profile.php" class="login"><?= htmlspecialchars($user_name) ?></a>
    </div>
  </div>
</header>

<section class="courses">
  <h2>On Progress Courses</h2>
  <?php if(empty($on_progress)): ?>
      <p>No courses in progress. Start learning!</p>
  <?php else: ?>
      <?php foreach($on_progress as $course): 
          $progress = round(($course['completed_questions']/$course['total_questions'])*100);
      ?>
          <a class="course-card" href="questions.php?topic_id=<?= $course['topic_id'] ?>">
            <div class="course-title"><?= htmlspecialchars($course['topic_name']) ?></div>
            <div class="course-desc"><?= htmlspecialchars($course['description']) ?></div>
            <div class="course-meta"><?= $progress ?>% completed</div>
            <div class="progress-bar"><div class="progress-fill" style="width:<?= $progress ?>%;"></div></div>
          </a>
      <?php endforeach; ?>
  <?php endif; ?>

  <h2>Completed Courses</h2>
  <?php if(empty($completed)): ?>
      <p>No courses completed yet.</p>
  <?php else: ?>
      <?php foreach($completed as $course): ?>
          <a class="course-card" href="questions.php?topic_id=<?= $course['topic_id'] ?>">
            <div class="course-title"><?= htmlspecialchars($course['topic_name']) ?></div>
            <div class="course-desc"><?= htmlspecialchars($course['description']) ?></div>
            <div class="course-meta">100% completed</div>
            <div class="progress-bar"><div class="progress-fill" style="width:100%;"></div></div>
          </a>
      <?php endforeach; ?>
  <?php endif; ?>
</section>

<script>
// Dark/Light Theme Toggle (optional)
const toggle = document.getElementById('theme-toggle');
if(toggle){
    toggle.addEventListener('change', () => {
        document.body.classList.toggle('dark-theme', toggle.checked);
    });
}
</script>

</body>
</html>
