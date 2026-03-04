
<?php
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', '0');
// Real compiler proxy: forwards requests to the Docker-based Compiler API (Node + docker)
// Expected to be running locally (see /coding/compiler_api/README.md)

header('Content-Type: application/json');

// ---- helpers ----
function json_out($statusCode, $payload) {
  http_response_code($statusCode);
  echo json_encode($payload);
  exit;
}

/**
 * POST JSON to the local Node compiler API.
 *
 * On many macOS PHP installations (especially with `php -S`), the curl extension
 * may be missing. This wrapper transparently falls back to `file_get_contents`
 * so the site works without XAMPP/MAMP.
 */
function curl_json_post($url, $payload, $timeoutSeconds = 6) {
  $body = json_encode($payload);
  if ($body === false) {
    return [null, 'Unable to encode JSON payload', 0];
  }

  // Preferred path: cURL (if extension is available)
  if (function_exists('curl_init')) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeoutSeconds);

    $raw = curl_exec($ch);
    $err = curl_error($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($raw === false) {
      return [null, "cURL error: $err", 0];
    }

    $decoded = json_decode($raw, true);
    if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
      $snippet = substr($raw, 0, 500);
      return [null, "Invalid JSON from compiler API (HTTP $http): $snippet", $http];
    }

    return [$decoded, null, $http];
  }

  // Fallback path: file_get_contents
  $ctx = stream_context_create([
    'http' => [
      'method'  => 'POST',
      'header'  => "Content-Type: application/json\r\n",
      'content' => $body,
      'timeout' => $timeoutSeconds,
      'ignore_errors' => true, // capture non-2xx response bodies
    ],
  ]);

  $raw = @file_get_contents($url, false, $ctx);
  $http = 0;
  $err = null;

  if (isset($http_response_header) && is_array($http_response_header)) {
    foreach ($http_response_header as $h) {
      if (preg_match('#^HTTP/\S+\s+(\d{3})#', $h, $m)) {
        $http = intval($m[1]);
        break;
      }
    }
  }

  if ($raw === false) {
    $err = error_get_last();
    $msg = $err && isset($err['message']) ? $err['message'] : 'HTTP request failed';
    return [null, "HTTP error: $msg", $http];
  }

  $decoded = json_decode($raw, true);
  if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
    $snippet = substr($raw, 0, 500);
    return [null, "Invalid JSON from compiler API (HTTP $http): $snippet", $http];
  }

  return [$decoded, null, $http];
}

// ---- input ----
$questionId = intval($_POST['question_id'] ?? 0);
$code = $_POST['code'] ?? '';
$lang = strtoupper(trim($_POST['lang'] ?? 'C'));

$useCustom = ($_POST['use_custom'] ?? '0') === '1';
$customInput = $_POST['custom_input'] ?? '';

if ($questionId <= 0) {
  json_out(400, ['ok' => false, 'error' => 'Invalid question_id']);
}

if (!is_string($code) || trim($code) === '') {
  json_out(400, ['ok' => false, 'error' => 'Code is required']);
}

// Map your PHP app language to the compiler API language
$apiLang = ($lang === 'CPP' || $lang === 'C++') ? 'cpp' : 'c';

// Compiler API URL (override via env var if needed)
$baseUrl = getenv('COMPILER_API_URL');
if (!$baseUrl) {
  $baseUrl = getenv('COMPILER_API_URL') ?: 'http://127.0.0.1:3001';
}

if ($useCustom) {
  $url = rtrim($baseUrl, '/') . '/run';
  $payload = [
  'language' => $apiLang,
  'code' => $code,
  // Support both keys (different local builds may expect either `stdin` or `input`).
  'stdin' => $customInput . "\n",
  'input' => $customInput . "\n",
];

  [$resp, $err, $http] = curl_json_post($url, $payload);
  if ($resp === null) {
    json_out(502, [
      'ok' => false,
      'phase' => 'proxy',
      'error' => 'Compiler API not reachable',
      'details' => $err,
      'hint' => 'Start the Node compiler API (see /coding/compiler_api/README.md)'
    ]);
  }

  // Pass-through (with a tiny bit of normalization for UI)
  json_out(200, [
    'ok' => (bool)($resp['ok'] ?? false),
    'mode' => 'custom',
    'phase' => $resp['phase'] ?? 'run',
    'output' => $resp['output'] ?? '',
    'compileError' => $resp['compileError'] ?? '',
    'runtimeError' => $resp['runtimeError'] ?? '',
    'timedOut' => $resp['timedOut'] ?? false,
    'exitCode' => $resp['exitCode'] ?? null,
  ]);
}

// Default: run against DB testcases for that question via Node API
$url = rtrim($baseUrl, '/') . '/run-question';
$payload = [
  'questionId' => $questionId,
  'language' => $apiLang,
  'code' => $code,
  'normalize' => true
];

[$resp, $err, $http] = curl_json_post($url, $payload, 20);

if ($resp === null) {
  json_out(502, [
    'ok' => false,
    'phase' => 'proxy',
    'error' => 'Compiler API not reachable',
    'details' => $err,
    'hint' => 'Start the Node compiler API on ' . $baseUrl
  ]);
}

// Add mode so frontend can branch correctly; keep Node response shape intact
$resp['mode'] = 'tests';
json_out(200, $resp);