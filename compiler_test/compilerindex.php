<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$user_id   = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'] ?? 'Student';
$course    = "DSA Basics";

require_once __DIR__ . "/../config/database.php";
if ($conn->connect_error) die("DB connection failed");

$questionId = intval($_GET['question_id'] ?? 0);
$topicId = intval($_GET['topic_id'] ?? 0);
$lang = strtoupper($_GET['lang'] ?? 'C');
if ($questionId <= 0) die("Invalid question");

$q = $conn->query("SELECT * FROM questions WHERE question_id=$questionId");
$question = $q->fetch_assoc();
if (!$question) die("Question not found");

$v = $conn->query("SELECT * FROM question_variants 
                   WHERE question_id=$questionId 
                   AND language='".$conn->real_escape_string($lang)."'");
$variant = $v->fetch_assoc();
if (!$variant) die("Language variant not found");

$signature = trim(rtrim($variant['function_signature'], ';'));

// Extract return type, function name and parameters
preg_match('/^(\w+)\s+(\w+)\s*\((.*)\)$/', $signature, $matches);

$returnType = $matches[1] ?? 'int';
$functionName = $matches[2] ?? 'func';
$params = trim($matches[3] ?? '');

$headerCode = "#include <stdio.h>\n";

$userCode = "$signature\n{\n    \n}\n";

$footerCode = "\n\nint main() {\n";

if (!empty($params) && strtolower($params) !== 'void') {

    $paramList = explode(',', $params);
    $vars = [];

    foreach ($paramList as $p) {
        $p = trim($p);
        $parts = explode(' ', $p);
        $type = $parts[0];
        $name = $parts[1] ?? '';

        $footerCode .= "    $type $name;\n";
        $vars[] = "&$name";
    }

    $format = implode(' ', array_fill(0, count($vars), '%d'));

    $footerCode .= "    scanf(\"$format\", " . implode(', ', $vars) . ");\n";

    if ($returnType !== 'void') {
        $footerCode .= "    printf(\"%d\", $functionName(";
        $callVars = array_map(fn($v) => substr($v,1), $vars);
        $footerCode .= implode(', ', $callVars) . "));\n";
    } else {
        $callVars = array_map(fn($v) => substr($v,1), $vars);
        $footerCode .= "    $functionName(" . implode(', ', $callVars) . ");\n";
    }

} else {

    if ($returnType !== 'void') {
        $footerCode .= "    printf(\"%d\", $functionName());\n";
    } else {
        $footerCode .= "    $functionName();\n";
    }
}

$footerCode .= "    return 0;\n}";
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($question['title']) ?></title>

<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans&family=Space+Grotesk:wght@600;700&display=swap" rel="stylesheet">

<style>
/* ---- STYLING FROZEN (UNCHANGED) ---- */
body{margin:0;font-family:"IBM Plex Sans",sans-serif;background:#f7f7fb;font-size:15.5px}
.topbar{display:flex;justify-content:space-between;align-items:center;padding:18px 36px;background:#fff;border-bottom:1px solid #e5e7eb}
.top-left{display:flex;align-items:center;gap:14px}
.back-btn{text-decoration:none;font-size:20px;color:#444}
.title{font-size:22px;font-weight:700}
.meta{font-size:14px;color:#555;margin-top:4px}
.top-right{display:flex;align-items:center;gap:20px;font-size:14.5px;font-weight:500}
.container{max-width:1450px;margin:26px auto;padding:0 32px; display:flex;gap:16px}
.left,.right{background:#fff;border-radius:16px;box-shadow:0 6px 18px rgba(0,0,0,.06)}
.left{flex:1.15;padding:30px}
.right{flex:1.45;padding:28px;display:flex;flex-direction:column;gap:14px}
.left p,.output,textarea{font-size:15.8px}
.rounded-table{width:100%;border-collapse:separate;border-spacing:0;border-radius:14px;overflow:hidden;margin-top:12px}
.rounded-table th,.rounded-table td{padding:13px;border-bottom:1px solid #e5e7eb;font-family:monospace}
.rounded-table th{background:#f3f4f6;font-weight:600}
.rounded-table tr:last-child td{border-bottom:none}
textarea{width:100%;font-family:monospace;border-radius:12px;border:1px solid #d1d5db;padding:14px;resize:none}
#header-editor{
  height:20px;
  background:#f3f4f6;
  color:#555;
}

#code-editor{
  height:220px;
}

#footer-editor{
  height:160px;
  background:#f3f4f6;
  color:#555;
}

#customInput{
  height:80px;
}

textarea[readonly]{
  cursor:not-allowed;
  opacity:0.95;
}
pre{background:#f3f4f6;padding:14px;border-radius:12px;font-family:monospace;font-size:15.5px;margin-top:10px}
button{padding:12px 20px;border:none;border-radius:12px;background:#6c63ff;color:#fff;font-weight:600;cursor:pointer;font-size:15.5px}
button:hover{background:#5b54d6}
.checkbox{display:flex;align-items:center;gap:8px;font-size:15px}
.output{background:#f3f4f6;padding:14px;border-radius:12px;font-family:monospace}
.pass{color:green;font-weight:600}
.fail{color:red;font-weight:600}
.row-between{display:flex;align-items:center;justify-content:space-between}
</style>
</head>

<body>

<!-- TOP BAR -->
<div class="topbar">
  <div>
    <div class="top-left">
      <a class="back-btn" href="../questions.php?topic_id=<?= $topicId ?>&lang=<?= urlencode($lang) ?>">←</a>
      <div class="title"><?= htmlspecialchars($question['title']) ?></div>
    </div>
    <div class="meta">
      Course: <b><?= $course ?></b> |
      Language: <b><?= $lang ?></b>
    </div>
  </div>

  <div class="top-right">
      <div>👤 <?= htmlspecialchars($user_name) ?></div>
      <div id="timer" style="display:none">⏱ 00:00</div>
      <div id="timerControls">
        <button id="setTimerBtn">Set Timer</button>
      </div>
    </div>
</div>

<div class="container">

<!-- LEFT -->
<div class="left">
  <h2>Description</h2>
  <p><?= htmlspecialchars($question['description']) ?></p>

  <h3>Constraints</h3>
  <p><?= htmlspecialchars($question['constraints'] ?? '1 ≤ N ≤ 10⁵') ?></p>

  <h3>Sample Input & Output</h3>
<table class="rounded-table">
  <tr><th>Input</th><th>Output</th></tr>

<?php
$samples = $conn->query("
  SELECT input, expected_output
  FROM test_cases
  WHERE question_id = $questionId
    AND (is_hidden IS NULL OR is_hidden = 0)
  LIMIT 2
");

while ($row = $samples->fetch_assoc()) {
  echo "<tr>";
  echo "<td>" . htmlspecialchars($row['input']) . "</td>";
  echo "<td>" . htmlspecialchars($row['expected_output']) . "</td>";
  echo "</tr>";
}
?>
</table>

  <div class="checkbox" style="margin-top:14px">
    <input type="checkbox" id="starterToggle">
    <label for="starterToggle">Show Starter Code</label>
  </div>

  <pre id="starterCode" style="display:none"><?= htmlspecialchars($variant['starter_code']) ?></pre>
</div>

<!-- RIGHT -->
<div class="right">

  <label style="font-weight:600; margin-top:4px;">Header</label>
  <textarea id="header-editor" readonly><?= htmlspecialchars($headerCode) ?></textarea>

  <label style="font-weight:600; margin-top:10px;">Write Your Function</label>
  <textarea id="code-editor"><?= htmlspecialchars($userCode) ?></textarea>

  <label style="font-weight:600; margin-top:10px;">Footer / Driver Code</label>
  <textarea id="footer-editor" readonly><?= htmlspecialchars($footerCode) ?></textarea>

  <div class="row-between">
    <div class="checkbox">
      <input type="checkbox" id="customToggle">
      <label for="customToggle">Enable Custom Input</label>
    </div>
    <button id="runBtn">Run Code</button>
  </div>

  <textarea id="customInput" style="display:none" placeholder="Enter custom input"></textarea>

  <div class="output" id="output">Output will appear here</div>

  <table class="rounded-table">
  <thead>
    <tr><th>Expected Output</th><th>Obtained Output</th></tr>
  </thead>
  <tbody id="resultsBody">
    <tr>
      <td>-</td>
      <td>-</td>
    </tr>
  </tbody>
</table>
  <div id="status"></div>

</div>
</div>

<script>
const headerEditorEl = document.getElementById('header-editor');
const codeEditorEl = document.getElementById('code-editor');
const footerEditorEl = document.getElementById('footer-editor');
const runBtnEl = document.getElementById('runBtn');
const outputEl = document.getElementById('output');
const resultsBodyEl = document.getElementById('resultsBody');
const statusEl = document.getElementById('status');
const customToggleEl = document.getElementById('customToggle');
const customInputEl = document.getElementById('customInput');
const starterToggleEl = document.getElementById('starterToggle');
const starterCodeEl = document.getElementById('starterCode');

let remaining = 0, running = false, timerInt;

    const timerEl = document.getElementById("timer");
    const timerControls = document.getElementById("timerControls");
    const starterCheckbox = document.getElementById("starterToggle");

    starterCheckbox.addEventListener("mouseenter", () => {
      if (running) {
        starterCheckbox.title = "Starter code will be visible only when the timer ends.";
      }
    });

    starterCheckbox.addEventListener("mouseleave", () => {
      starterCheckbox.title = "";
    });
    starterCheckbox.addEventListener("click", (e) => {
      if (running) {
        e.preventDefault();
        e.stopPropagation();
      }
    });


    function showTimerInputs() {
      timerControls.innerHTML = `
    <input id="mm" class="time-input" placeholder="MM">
    :
    <input id="ss" class="time-input" placeholder="SS">
    <button id="startBtn">Start</button>
  `;

      document.getElementById("mm").addEventListener("keydown", e => {
        if (e.key === "Enter") document.getElementById("ss").focus();
      });

      document.getElementById("startBtn").onclick = startTimer;
    }

    function startTimer() {
      const mm = document.getElementById("mm");
      const ss = document.getElementById("ss");

      remaining = (+mm.value || 0) * 60 + (+ss.value || 0);
      if (remaining <= 0) return;

      mm.style.display = ss.style.display = "none";
      starterToggle.style.cursor = "not-allowed";


      timerEl.style.display = "block";
      running = true;

      timerControls.innerHTML = `
    <button id="pauseBtn">Pause</button>
    <button id="resetBtn">Reset</button>
  `;

      document.getElementById("pauseBtn").onclick = pauseTimer;
      document.getElementById("resetBtn").onclick = resetTimer;

      tick();
      timerInt = setInterval(tick, 1000);
    }

    function tick() {
      timerEl.textContent = "⏱ " + String(Math.floor(remaining / 60)).padStart(2, '0') + ":" + String(remaining % 60).padStart(2, '0');

      if (remaining === 60) {
        const c = document.createElement("div");
        c.className = "cloud";
        c.textContent = "⚠️ Only 1 minute left!";
        document.body.appendChild(c);
        setTimeout(() => c.remove(), 4000);
      }

      if (--remaining < 0) resetTimer();
    }

    function pauseTimer() {
      const btn = document.getElementById("pauseBtn");
      if (running) {
        clearInterval(timerInt);
        running = false;
        btn.textContent = "Resume";
      } else {
        running = true;
        btn.textContent = "Pause";
        timerInt = setInterval(tick, 1000);
      }
    }

    function resetTimer() {
      clearInterval(timerInt);
      running = false;
      remaining = 0;
      timerEl.style.display = "none";
      starterToggle.style.cursor = "pointer";

      showTimerInputs();
    }

    setTimerBtn.onclick = showTimerInputs;


/* TOGGLES */
starterToggleEl.onchange = e =>
  starterCodeEl.style.display = e.target.checked ? "block" : "none";

customToggleEl.onchange = e =>
  customInputEl.style.display = e.target.checked ? "block" : "none";

function setStatus(ok, msg) {
  statusEl.innerHTML = ok
    ? `<span class='pass'>✓ PASS</span> ${msg ? ' - ' + msg : ''}`
    : `<span class='fail'>✗ FAIL</span> ${msg ? ' - ' + msg : ''}`;
}

async function postForm(url, formData) {
  const res = await fetch(url, { method: 'POST', body: formData });
  const data = await res.json().catch(() => null);
  if (!data) throw new Error('Invalid server response');
  return data;
}

/* REAL RUN (Judge0 via PHP endpoint) */
runBtnEl.onclick = async () => {
  outputEl.textContent = 'Running...';
  resultsBodyEl.innerHTML = `
  <tr>
    <td>-</td>
    <td>-</td>
  </tr>
`;
  statusEl.innerHTML = '';

  const fd = new FormData();
  fd.append('question_id', '<?= $questionId ?>');
  fd.append('lang', '<?= $lang ?>');
  const fullCode =
  headerEditorEl.value +
  codeEditorEl.value +
  footerEditorEl.value;

fd.append('code', fullCode);
  fd.append('use_custom', customToggleEl.checked ? '1' : '0');
  fd.append('custom_input', customInputEl.value || '');

  try {
    const resp = await postForm('run_code.php', fd);

    // Custom input mode
    if (customToggleEl.checked) {
      if (resp.status === 'compile_error') {
        outputEl.textContent = resp.output || 'Compilation failed';
        setStatus(false, 'Compile error');
        return;
      }

      if (resp.status === 'runtime_error' || resp.status === 'error') {
        outputEl.textContent = (resp.output || 'Runtime error').trim();
        setStatus(false, 'Runtime error');
        return;
      }

      outputEl.textContent = (resp.output || '').trim() || '(no output)';
      setStatus(true, 'Ran successfully');
      return;
    }

    // Test case mode
    if (resp.status === 'compile_error') {
      outputEl.textContent = resp.output || 'Compilation failed';
      setStatus(false, 'Compile error');
      return;
    }

    if (resp.status === 'runtime_error' || resp.status === 'error') {
      outputEl.textContent = (resp.output || 'Runtime error').trim();
      setStatus(false, 'Runtime error');
      return;
    }


const results = Array.isArray(resp.results) ? resp.results : [];
const failedCases = results.filter(r => r && r.passed === false);

const passedCount = results.filter(r => r && r.passed).length;
const totalCount = results.length;
const summary = totalCount ? `${passedCount}/${totalCount} passed` : '';

if (resp.status === 'success') {
  outputEl.textContent = summary || resp.output || 'All test cases passed';

  if (results.length) {
    resultsBodyEl.innerHTML = results.map(r => `
      <tr>
        <td>${String(r.expected_output ?? '-').replace(/</g, '&lt;').replace(/>/g, '&gt;')}</td>
        <td>${String(r.actual_output ?? '-').replace(/</g, '&lt;').replace(/>/g, '&gt;')}</td>
      </tr>
    `).join('');
  } else {
    resultsBodyEl.innerHTML = `
      <tr>
        <td>-</td>
        <td>-</td>
      </tr>
    `;
  }

  setStatus(true, summary || 'All test cases passed');
} else {
  if (failedCases.length) {
    outputEl.textContent = failedCases
      .map((r, i) => `Test ${i + 1}: Expected ${r.expected_output} | Got ${r.actual_output}`)
      .join('\n');

    resultsBodyEl.innerHTML = failedCases.map(r => `
      <tr>
        <td>${String(r.expected_output ?? '-').replace(/</g, '&lt;').replace(/>/g, '&gt;')}</td>
        <td>${String(r.actual_output ?? '-').replace(/</g, '&lt;').replace(/>/g, '&gt;')}</td>
      </tr>
    `).join('');
  } else {
    outputEl.textContent = resp.output || 'Some test cases failed';
    resultsBodyEl.innerHTML = `
      <tr>
        <td>-</td>
        <td>-</td>
      </tr>
    `;
  }

  setStatus(false, summary || 'Some test cases failed');
}
  } catch (e) {
    outputEl.textContent = 'Compiler service error. Check run_code.php or Judge0 configuration.';
    setStatus(false, 'Compiler offline');
  }
};
</script>

</body>
</html>
