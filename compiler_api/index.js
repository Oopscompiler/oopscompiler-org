const express = require("express");
const { spawn } = require("child_process");
const fs = require("fs/promises");
const path = require("path");

const os = require("os");

require("dotenv").config();
const mysql = require("mysql2/promise");

const db = mysql.createPool({
  host: process.env.DB_HOST || "127.0.0.1",
  port: Number(process.env.DB_PORT || 3307),
  user: process.env.DB_USER || "coding_user",
  password: process.env.DB_PASS || "Iamperfect7#",
  database: process.env.DB_NAME || "coding",
  waitForConnections: true,
  connectionLimit: 10,
});

const app = express();
app.use(express.json({ limit: "200kb" })); // keep it small for safety
app.use(express.static(path.join(__dirname, "public")));

const IMAGE = "code-sandbox:latest";
const TIMEOUT_MS = 8000; // strict (2s). Increase later if needed.
const MEM_LIMIT = "256m";
const CPU_LIMIT = "0.5";
const PIDS_LIMIT = "64";

function runDocker({ workdirHost, cmd }) {
  return new Promise((resolve) => {
    const args = [
      "run",
      "--rm",
      "--network=none",
      `--memory=${MEM_LIMIT}`,
      `--cpus=${CPU_LIMIT}`,
      `--pids-limit=${PIDS_LIMIT}`,
      "-v",
      `${workdirHost}:/workspace`,
      // Ensure commands run where the mounted files exist.
      "-w",
      "/workspace",
      IMAGE,
      "bash",
      "-lc",
      cmd,
    ];

    const child = spawn("docker", args, { stdio: ["ignore", "pipe", "pipe"] });

    let stdout = "";
    let stderr = "";

    const MAX_OUTPUT = 50_000; // 50KB

child.stdout.on("data", (d) => {
  stdout += d.toString();
  if (stdout.length > MAX_OUTPUT) stdout = stdout.slice(0, MAX_OUTPUT) + "\n[Output truncated]";
});

child.stderr.on("data", (d) => {
  stderr += d.toString();
  if (stderr.length > MAX_OUTPUT) stderr = stderr.slice(0, MAX_OUTPUT) + "\n[Error output truncated]";
});

    const timer = setTimeout(() => {
      child.kill("SIGKILL");
      resolve({
        code: 124,
        stdout,
        stderr: (stderr || "") + "\n[Timed out]",
        timedOut: true,
      });
    }, TIMEOUT_MS);

    child.on("close", (code) => {
      clearTimeout(timer);
      resolve({ code, stdout, stderr, timedOut: false });
    });
  });
}

function normalizeOut(s) {
  // normalize line endings and remove trailing whitespace per-line
  return (s ?? "")
    .replace(/\r\n/g, "\n")
    .split("\n")
    .map((line) => line.replace(/[ \t]+$/g, ""))
    .join("\n")
    .trimEnd();
}

function firstDiff(expected, actual, maxLineLen = 200) {
  const exp = (expected ?? "").replace(/\r\n/g, "\n");
  const act = (actual ?? "").replace(/\r\n/g, "\n");

  const expLines = exp.split("\n");
  const actLines = act.split("\n");

  const max = Math.max(expLines.length, actLines.length);

  for (let i = 0; i < max; i++) {
    const eLine = expLines[i] ?? "";
    const aLine = actLines[i] ?? "";
    if (eLine !== aLine) {
      const clip = (s) => (s.length > maxLineLen ? s.slice(0, maxLineLen) + "…" : s);
      return {
        line: i + 1,
        expectedLine: clip(eLine),
        actualLine: clip(aLine),
      };
    }
  }
  return null; // no diff
}

app.get("/", (_, res) => {
  res.send("Compiler API is running. Use POST /run or POST /run-tests");
});


app.get("/health", (_, res) => res.json({ ok: true }));

app.get("/topics", async (req, res) => {
  try {
    const [rows] = await db.execute(
      "SELECT topic_id, topic_name, description FROM topics"
    );

    res.json(rows);
  } catch (err) {
    console.error("Failed to fetch topics:", err);
    res.status(500).json({ error: "Failed to fetch topics" });
  }
});

app.get("/questions", async (req, res) => {
  const { topicId, lang } = req.query;

  const language = lang === "cpp" ? "CPP" : "C";

  try {
    const [rows] = await db.execute(
      `
      SELECT DISTINCT q.question_id, q.title, q.description, q.difficulty
      FROM questions q
      JOIN question_variants v
        ON q.question_id = v.question_id
      WHERE q.topic_id = ?
        AND v.language = ?
      ORDER BY q.question_id
      `,
      [topicId, language]
    );

    res.json(rows);
  } catch (err) {
    console.error(err);
    res.status(500).json({ error: "Failed to fetch questions" });
  }
});

app.get("/question-variant", async (req, res) => {
  const { questionId, lang } = req.query;

  try {
    const [rows] = await db.execute(
      `SELECT function_signature, starter_code
       FROM question_variants
       WHERE question_id = ?
       AND language = ?`,
      [questionId, lang === "cpp" ? "CPP" : "C"]
    );

    if (rows.length === 0) {
      return res.status(404).json({ error: "Variant not found" });
    }

    res.json(rows[0]);
  } catch (err) {
    console.error(err);
    res.status(500).json({ error: "Failed to fetch variant" });
  }
});

/**
 * POST /run-question
 * Body:
 * {
 *   questionId: number,
 *   language: "c"|"cpp",
 *   code: string,
 *   normalize?: boolean
 * }
 *
 * Fetches test cases from DB and runs them using the SAME logic as /run-tests.
 */
app.post("/run-question", async (req, res) => {
  const { questionId, language, code, normalize = true } = req.body || {};

  if (!Number.isInteger(questionId)) {
    return res.status(400).json({ error: "questionId must be an integer" });
  }
  if (!["c", "cpp"].includes(language)) {
    return res.status(400).json({ error: "language must be 'c' or 'cpp'" });
  }
  if (typeof code !== "string" || code.trim().length === 0) {
    return res.status(400).json({ error: "code is required" });
  }

  // Pull testcases for this question (auto-detect column names)
  let tcRows;
  try {
    const [cols] = await db.execute(
      `SELECT COLUMN_NAME
       FROM INFORMATION_SCHEMA.COLUMNS
       WHERE TABLE_SCHEMA = DATABASE()
         AND TABLE_NAME = 'test_cases'`
    );

    const colSet = new Set(cols.map((c) => c.COLUMN_NAME));
    const pick = (candidates) => candidates.find((c) => colSet.has(c));

    const hiddenCol = pick(["is_hidden", "hidden", "isHidden"]);

    // Common naming patterns across teams/projects
    const stdinCol = pick(["stdin", "input", "test_input", "input_data", "input_text", "input_value"]);
    const expectedCol = pick(["expected_output", "expected", "output", "expected_text", "answer", "expectedValue"]);
    const orderCol = pick(["test_case_id", "id", "case_id", "tc_id"]);

    if (!stdinCol || !expectedCol) {
      return res.status(500).json({
        error: "test_cases column names don't match what backend expects",
        details: {
          foundColumns: Array.from(colSet).sort(),
          needOneOf: {
            stdin: ["stdin", "input", "test_input", "input_data", "input_text", "input_value"],
            expected: ["expected_output", "expected", "output", "expected_text", "answer", "expectedValue"],
          },
        },
      });
    }

    const sql =
  `SELECT \`${stdinCol}\` AS stdin, \`${expectedCol}\` AS expected_output` +
  (hiddenCol ? `, \`${hiddenCol}\` AS is_hidden` : ``) +
  ` FROM test_cases
     WHERE question_id = ?` +
  (orderCol ? ` ORDER BY \`${orderCol}\` ASC` : "");

    [tcRows] = await db.execute(sql, [questionId]);
  } catch (e) {
    return res.status(500).json({ error: "Unable to read test_cases from DB", details: String(e) });
  }

  if (!Array.isArray(tcRows) || tcRows.length === 0) {
    return res.status(404).json({ error: "No testcases found for this questionId" });
  }

  // Safety cap
  const testcases = tcRows.slice(0, 30).map((r) => ({
  stdin: typeof r.stdin === "string" ? r.stdin : "",
  expected: typeof r.expected_output === "string" ? r.expected_output : "",
  hidden: Number(r.is_hidden || 0) === 1
}));

  const jobDir = await fs.mkdtemp(path.join(os.tmpdir(), "coderun-"));
  const srcFile = language === "c" ? "main.c" : "main.cpp";

  try {
    await fs.writeFile(path.join(jobDir, srcFile), code, "utf8");

    const compileCmd =
      language === "c"
        ? `gcc ${srcFile} -O2 -std=c11 -o main`
        : `g++ ${srcFile} -O2 -std=c++17 -o main`;

    // Compile ONCE
    const compileResult = await runDocker({
      workdirHost: jobDir,
      cmd: `${compileCmd}`,
    });

    if (compileResult.code !== 0) {
      return res.json({
        ok: false,
        phase: "compile",
        compileError: compileResult.stderr || "Compilation failed",
        summary: { total: testcases.length, passed: 0, failed: testcases.length },
        results: [],
      });
    }

    // Run each testcase (sequential)
    const results = [];
    let passedCount = 0;

    for (let i = 0; i < testcases.length; i++) {
      const tc = testcases[i] || {};
      const stdin = typeof tc.stdin === "string" ? tc.stdin : "";
      const expectedRaw = typeof tc.expected === "string" ? tc.expected : "";

      await fs.writeFile(path.join(jobDir, "input.txt"), stdin, "utf8");

      const runResult = await runDocker({
        workdirHost: jobDir,
        cmd: `./main < input.txt`,
      });

      const actualRaw = runResult.stdout || "";
      const runtimeErrRaw = runResult.stderr || "";

      const actual = normalize ? normalizeOut(actualRaw) : actualRaw;
      const expected = normalize ? normalizeOut(expectedRaw) : expectedRaw;

      const passed = runResult.code === 0 && !runResult.timedOut && actual === expected;
      if (passed) passedCount++;

      let diff = null;
      if (!passed && runResult.code === 0 && !runResult.timedOut) {
        diff = firstDiff(expected, actual);
      }

        const hidden = !!tc.hidden;

  results.push({
    index: i,
    passed,
    timedOut: runResult.timedOut,
    exitCode: runResult.code,
    stdin: hidden ? "[hidden]" : stdin,
    expected: hidden ? "[hidden]" : expected,
    actual: hidden ? "[hidden]" : actual,
    diff: hidden ? null : diff,
    runtimeError:
      runResult.code === 0 && !runResult.timedOut
        ? ""
        : runtimeErrRaw || "Runtime error",
  });
    }

    return res.json({
      ok: passedCount === testcases.length,
      phase: "tests",
      summary: {
        total: testcases.length,
        passed: passedCount,
        failed: testcases.length - passedCount,
      },
      results,
    });
  } catch (e) {
    return res.status(500).json({ error: "Server error", details: String(e) });
  } finally {
    try {
      await fs.rm(jobDir, { recursive: true, force: true });
    } catch {}
  }
});

/**
 * POST /run
 * Body: { language: "c"|"cpp", code: string, stdin?: string }
 */
app.post("/run", async (req, res) => {
  const { language, code, stdin, input } = req.body || {};

  if (!["c", "cpp"].includes(language)) {
    return res.status(400).json({ error: "language must be 'c' or 'cpp'" });
  }
  if (typeof code !== "string" || code.trim().length === 0) {
    return res.status(400).json({ error: "code is required" });
  }

  const jobDir = await fs.mkdtemp(path.join(os.tmpdir(), "coderun-"));
  const srcFile = language === "c" ? "main.c" : "main.cpp";

  try {
    await fs.writeFile(path.join(jobDir, srcFile), code, "utf8");
    // Accept either `stdin` or `input` for convenience.
    const effectiveStdin = typeof stdin === "string" ? stdin : (typeof input === "string" ? input : "");
    await fs.writeFile(path.join(jobDir, "input.txt"), effectiveStdin || "", "utf8");

    const compileCmd =
      language === "c"
        ? `gcc ${srcFile} -O2 -std=c11 -o main`
        : `g++ ${srcFile} -O2 -std=c++17 -o main`;

    // compile then run
    const fullCmd = `${compileCmd} 2> compile.txt || exit 100; ./main < input.txt`;

    const result = await runDocker({ workdirHost: jobDir, cmd: fullCmd });

    // compile failed
    if (result.code === 100) {
      let compileError = "";
      try {
        compileError = await fs.readFile(path.join(jobDir, "compile.txt"), "utf8");
      } catch {}
      return res.json({
        ok: false,
        phase: "compile",
        output: "",
        compileError: compileError || result.stderr || "Compilation failed",
        runtimeError: "",
        timedOut: false,
      });
    }

    // runtime timeout / runtime error
    const runtimeError = result.code !== 0 ? (result.stderr || "Runtime error") : "";

    return res.json({
      ok: result.code === 0,
      phase: "run",
      output: result.stdout,
      compileError: "",
      runtimeError,
      timedOut: result.timedOut,
      exitCode: result.code,
    });
  } catch (e) {
    return res.status(500).json({ error: "Server error", details: String(e) });
  } finally {
    try {
      await fs.rm(jobDir, { recursive: true, force: true });
    } catch {}
  }
});

/**
 * POST /run-tests
 * Body:
 * {
 *   language: "c"|"cpp",
 *   code: string,
 *   testcases: [{ stdin: string, expected: string }],
 *   normalize?: boolean
 * }
 */
app.post("/run-tests", async (req, res) => {
  const { language, code, testcases, normalize = true } = req.body || {};

  if (!["c", "cpp"].includes(language)) {
    return res.status(400).json({ error: "language must be 'c' or 'cpp'" });
  }
  if (typeof code !== "string" || code.trim().length === 0) {
    return res.status(400).json({ error: "code is required" });
  }
  if (!Array.isArray(testcases) || testcases.length === 0) {
    return res.status(400).json({ error: "testcases must be a non-empty array" });
  }
  if (testcases.length > 30) {
    return res.status(400).json({ error: "too many testcases (max 30)" });
  }

  const jobDir = await fs.mkdtemp(path.join(os.tmpdir(), "coderun-"));
  const srcFile = language === "c" ? "main.c" : "main.cpp";

  try {
    await fs.writeFile(path.join(jobDir, srcFile), code, "utf8");

    const compileCmd =
      language === "c"
        ? `gcc ${srcFile} -O2 -std=c11 -o main`
        : `g++ ${srcFile} -O2 -std=c++17 -o main`;

    // Compile ONCE
    const compileResult = await runDocker({
      workdirHost: jobDir,
      cmd: `${compileCmd}`,
    });

    if (compileResult.code !== 0) {
      return res.json({
        ok: false,
        phase: "compile",
        compileError: compileResult.stderr || "Compilation failed",
        summary: { total: testcases.length, passed: 0, failed: testcases.length },
        results: [],
      });
    }

    // Run each testcase (sequential)
    const results = [];
    let passedCount = 0;

    for (let i = 0; i < testcases.length; i++) {
      const tc = testcases[i] || {};
      const stdin = typeof tc.stdin === "string" ? tc.stdin : "";
      const expectedRaw = typeof tc.expected === "string" ? tc.expected : "";

      await fs.writeFile(path.join(jobDir, "input.txt"), stdin, "utf8");

      const runResult = await runDocker({
        workdirHost: jobDir,
        cmd: `./main < input.txt`,
      });

      const actualRaw = runResult.stdout || "";
      const runtimeErrRaw = runResult.stderr || "";

      const actual = normalize ? normalizeOut(actualRaw) : actualRaw;
      const expected = normalize ? normalizeOut(expectedRaw) : expectedRaw;

      const passed = runResult.code === 0 && !runResult.timedOut && actual === expected;
if (passed) passedCount++;

let diff = null;
if (!passed && runResult.code === 0 && !runResult.timedOut) {
  // mismatch only (not runtime error/timeout)
  diff = firstDiff(expected, actual);
}

results.push({
  index: i,
  passed,
  timedOut: runResult.timedOut,
  exitCode: runResult.code,
  stdin,
  expected,
  actual,
  diff,
  runtimeError:
    runResult.code === 0 && !runResult.timedOut ? "" : (runtimeErrRaw || "Runtime error"),
});
     // if (passed) passedCount++;
    }

    return res.json({
      ok: passedCount === testcases.length,
      phase: "tests",
      summary: {
        total: testcases.length,
        passed: passedCount,
        failed: testcases.length - passedCount,
      },
      results,
    });
  } catch (e) {
    return res.status(500).json({ error: "Server error", details: String(e) });
  } finally {
    try {
      await fs.rm(jobDir, { recursive: true, force: true });
    } catch {}
  }
});

const PORT = 3001;
app.listen(PORT, () => console.log(`Compiler API running on http://localhost:${PORT}`));