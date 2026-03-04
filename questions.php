<?php
session_start();
$user_id = $_SESSION['user_id'] ?? null;

$conn = new mysqli("localhost", "root", "", "coding");
if ($conn->connect_error)
    die("DB connection failed: " . $conn->connect_error);

$topic_id = intval($_GET['topic_id'] ?? 0);

$lang = 'C';
if (isset($_GET['lang'])) {
    $inputLang = strtoupper(trim($_GET['lang']));
    if ($inputLang === 'CPP' || $inputLang === 'C++')
        $lang = 'CPP';
}

$topicRes = $conn->query("SELECT topic_name FROM topics WHERE topic_id = $topic_id");
$topicName = ($topicRes && $topicRes->num_rows > 0)
    ? $topicRes->fetch_assoc()['topic_name']
    : "Topic";

/* Incomplete Questions */
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

/* Completed Questions */
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
    <title><?= htmlspecialchars($topicName) ?> | &gt;_Oops!</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link
        href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@600;700&family=IBM+Plex+Sans:wght@400;500&display=swap"
        rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet" />

    <link rel="stylesheet" href="header/header.css">
    <style>
        /* =====================
           RESET & GLOBALS (Fixed)
        ===================== */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: "IBM Plex Sans", sans-serif;
            background: #fff;
            color: #000;
            line-height: 1.6;
            transition: background 0.3s ease;
        }

        /* =====================
           QUESTION SECTION (Matched to Code 2)
        ===================== */
        .questions {
            max-width: 1200px;
            margin: 60px auto 120px;
            padding: 0 32px;
            /* Added padding to prevent 'zoomed' look */
        }

        .questions h1 {
            font-family: "Space Grotesk", sans-serif;
            font-size: 34px;
            /* Slightly larger, matching Code 2 headers */
            letter-spacing: -0.6px;
            margin-bottom: 8px;
        }

        .topic-meta {
            color: #777;
            margin-bottom: 48px;
            font-size: 15px;
        }

        /* =====================
           QUESTION CARD (Updated UI)
        ===================== */
        .question-card {
            border: 1px solid rgba(0, 0, 0, 0.12);
            padding: 32px;
            /* Balanced padding */
            margin-bottom: 24px;
            transition: 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
            background: #fff;
            display: block;
            text-decoration: none;
            color: inherit;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.03);
        }

        .question-card:hover {
            border-color: #000;
            transform: translateX(6px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
        }

        .question-title {
            font-family: "Space Grotesk", sans-serif;
            font-size: 22px;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .question-desc {
            font-size: 15px;
            color: #555;
            margin-bottom: 16px;
            max-width: 800px;
        }

        /* =====================
           DIFFICULTY BADGES
        ===================== */
        .difficulty {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            color: #fff;
            letter-spacing: 0.5px;
        }

        .easy {
            background: #28a745;
        }

        .medium {
            background: #ffc107;
            color: #000;
        }

        .hard {
            background: #dc3545;
        }

        /* =====================
           COMPLETED SECTION
        ===================== */
        .completed-section {
            margin-top: 80px;
            border-top: 1px solid #eee;
            padding-top: 48px;
        }

        .completed-section h2 {
            font-family: "Space Grotesk", sans-serif;
            font-size: 26px;
            margin-bottom: 32px;
            color: #444;
        }

        .question-card.completed {
            opacity: 0.7;
            border-style: dashed;
            background: #fdfdfd;
        }

        /* =====================
           DARK THEME
        ===================== */
        .dark-theme {
            background-color: #0b0b0b;
            color: #eee;
        }

        .dark-theme .question-card {
            background: #111;
            border-color: #333;
        }

        .dark-theme .question-desc,
        .dark-theme .topic-meta {
            color: #aaa;
        }
    </style>
</head>

<body>

    <?php include 'header/header.php'; ?>

    <section class="questions">
        <h1><?= htmlspecialchars($topicName) ?></h1>
        <p class="topic-meta"><?= $displayLang ?> Programming Track • Select a challenge to begin</p>

        <?php if ($incomplete->num_rows === 0 && $completed->num_rows === 0): ?>
            <div style="padding: 40px; border: 1px dashed #ccc; text-align: center; color: #777;">
                No questions found for this topic yet.
            </div>
        <?php elseif ($incomplete->num_rows === 0): ?>
            <div
                style="padding: 20px; background: #e8f5e9; color:#28a745; font-weight:500; border-radius: 8px; margin-bottom: 20px;">
                <i class="fa-solid fa-trophy"></i> You've conquered all challenges in this topic!
            </div>
        <?php else: ?>
            <?php while ($row = $incomplete->fetch_assoc()): ?>
                <a class="question-card"
                    href="compiler/compilerindex.php?question_id=<?= $row['question_id'] ?>&lang=<?= $lang ?>&topic_id=<?= $topic_id ?>">
                    <div class="question-title"><?= htmlspecialchars($row['title']) ?></div>
                    <div class="question-desc"><?= htmlspecialchars($row['description']) ?></div>
                    <span class="difficulty <?= strtolower($row['difficulty']) ?>">
                        <?= $row['difficulty'] ?>
                    </span>
                </a>
            <?php endwhile; ?>
        <?php endif; ?>

        <?php if ($completed->num_rows > 0): ?>
            <div class="completed-section">
                <h2>Completed Challenges</h2>
                <?php while ($row = $completed->fetch_assoc()): ?>
                    <a class="question-card completed"
                        href="compiler/compilerindex.php?question_id=<?= $row['question_id'] ?>&lang=<?= $lang ?>&topic_id=<?= $topic_id ?>">
                        <div class="question-title"><?= htmlspecialchars($row['title']) ?></div>
                        <div class="question-desc"><?= htmlspecialchars($row['description']) ?></div>
                        <span class="difficulty <?= strtolower($row['difficulty']) ?>">
                            <?= $row['difficulty'] ?>
                        </span>
                        <span style="margin-left:15px; color:#28a745; font-weight: 600; font-size: 14px;">
                            <i class="fa-solid fa-circle-check"></i> Solved
                        </span>
                    </a>
                <?php endwhile; ?>
            </div>
        <?php endif; ?>
    </section>

    <footer>
        <div style="text-align: center; padding: 50px; color: #777; font-size: 13px; border-top: 1px solid #eee;">
            © 2026 ptrmaster — Learn the fundamentals properly.
        </div>
    </footer>

    <script src="header/header.js"></script>
    <script>
        // Profile Dropdown Logic
        const profileBtn = document.getElementById("profileBtn");
        const dropdownMenu = document.getElementById("dropdownMenu");

        if (profileBtn) {
            profileBtn.addEventListener("click", (e) => {
                e.stopPropagation();
                dropdownMenu.style.display = (dropdownMenu.style.display === "block") ? "none" : "block";
            });

            window.addEventListener("click", () => {
                if (dropdownMenu) dropdownMenu.style.display = "none";
            });
        }

        // Simple Theme Toggle Logic
        const themeToggle = document.getElementById('theme-toggle');
        if (themeToggle) {
            themeToggle.addEventListener('change', () => {
                document.body.classList.toggle('dark-theme');
            });
        }
    </script>

</body>

</html>