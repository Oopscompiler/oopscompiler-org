<?php
session_start();
?>
<!doctype html>
<html lang="en">

<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>OopsCompiler</title>

<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=IBM+Plex+Sans:wght@400;500&display=swap" rel="stylesheet"/>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">

<!-- HEADER CSS -->
<link rel="stylesheet" href="header/header.css">

<style>
/* =====================
   RESET + VARIABLES
===================== */
* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
}

:root {
  --black: #000;
  --white: #fff;
  --gray-2: #444;
  --gray-3: #777;
  --border: rgba(0,0,0,0.12);
  --shadow-soft: 0 12px 32px rgba(0,0,0,0.05);
  --ease: cubic-bezier(0.4,0,0.2,1);
}

body {
  font-family: "IBM Plex Sans", sans-serif;
  background: var(--white);
  color: var(--black);
  line-height: 1.6;
  transition: background 0.3s ease, color 0.3s ease;
}

h1,h2,h3 {
  font-family: "Space Grotesk", sans-serif;
  letter-spacing: -0.6px;
}

/* =====================
   MAIN CONTENT STYLES
===================== */
.features {
  max-width: 1200px;
  margin: 40px auto 120px;
  padding: 0 32px;
}

.feature-card {
  border: 1px solid var(--border);
  padding: 56px;
  display: grid;
  grid-template-columns: 1.3fr 1fr;
  gap: 48px;
  align-items: center;
  box-shadow: var(--shadow-soft);
}

.feature-text h2 {
  font-size: 34px;
  margin-bottom: 18px;
}

.feature-illustration img {
  max-width: 100%;
  height: auto;
}

.languages {
  max-width: 1200px;
  margin: 0 auto 100px;
  padding: 0 32px;
}

.lang-cards {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 40px;
}

.lang-card {
  border: 1px solid var(--border);
  padding: 44px 40px;
  cursor: pointer;
  transition: 0.3s var(--ease);
}

.lang-card:hover {
  border-color: var(--black);
  transform: translateX(6px);
}

.lang-title {
  font-size: 28px;
  font-weight: 600;
  margin-bottom: 10px;
}

.lang-desc {
  color: var(--gray-3);
  font-size: 15px;
}

.prefoot {
  display: flex;
  justify-content: center;
  margin-bottom: 60px;
}

.prefoot img {
  max-width: 100%;
  max-height: 320px;
  object-fit: contain;
}

footer {
  border-top: 1px solid var(--border);
  padding: 50px;
  text-align: center;
  font-size: 13px;
  color: var(--gray-3);
}

.dark-theme {
  background-color: #0b0b0b;
  color: #eee;
}

.dark-theme .feature-card,
.dark-theme .lang-card {
  background: #111;
  border-color: #222;
}

.dark-theme .lang-desc,
.dark-theme footer {
  color: #aaa;
}
</style>
</head>

<body>

<!-- INCLUDE HEADER -->
<?php include 'header/header.php'; ?>

<section class="features">
  <div class="feature-card">
    <div class="feature-text">
      <h2>Build real understanding, step by step</h2>
      <p>OopsCompiler teaches C and C++ through carefully ordered question series — from warmups to pointers and memory.</p>
    </div>
    <div class="feature-illustration">
      <img src="coder2.png" alt="Illustration" />
    </div>
  </div>
</section>

<section class="languages">
  <h3 style="text-align:center; margin-bottom:40px; font-size:24px;">Select a track to begin</h3>
  <div class="lang-cards">
    <div class="lang-card" onclick="location.href='courses.php?lang=C'">
      <div class="lang-title">C Programming</div>
      <div class="lang-desc">Master memory, pointers, and low-level logic.</div>
    </div>

    <div class="lang-card" onclick="location.href='courses.php?lang=CPP'">
      <div class="lang-title">Modern C++</div>
      <div class="lang-desc">Learn OOP, STL, and performance-oriented design.</div>
    </div>
  </div>
</section>

<section class="prefoot">
  <img src="coder1.png" alt="Bottom Illustration" />
</section>

<footer>© 2026 >_Oops! — Learn the fundamentals properly.</footer>

<!-- HEADER JS -->
<script src="header/header.js"></script>
<script>
  const toggle = document.getElementById('theme-toggle');

  if (toggle) {
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
  }
</script>

</body>
</html>