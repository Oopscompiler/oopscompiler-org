<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/judge0.php';
header("Content-Type: application/json");
ini_set('display_errors', '0');
error_reporting(E_ERROR | E_PARSE);

function respond(array $data, int $statusCode = 200): void {
    http_response_code($statusCode);
    echo json_encode($data);
    exit;
}

function debug_log(string $label, $value): void {
    $logFile = __DIR__ . '/judge0_debug.txt';
    $content = "[" . date('Y-m-d H:i:s') . "] " . $label . ":\n";

    if (is_array($value) || is_object($value)) {
        $content .= print_r($value, true);
    } else {
        $content .= (string)$value;
    }

    $content .= "\n\n";
    file_put_contents($logFile, $content, FILE_APPEND);
}

function normalize_output(string $value): string {
    return rtrim(str_replace("\r\n", "\n", $value));
}

function judge0_request(array $payload, string $baseUrl, string $apiKey): array {
    $curl = curl_init();

    curl_setopt_array($curl, [
        CURLOPT_URL => rtrim($baseUrl, '/') . '/submissions?base64_encoded=false&wait=true',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'X-RapidAPI-Key: ' . $apiKey,
            'X-RapidAPI-Host: judge0-ce.p.rapidapi.com'
        ],
        CURLOPT_TIMEOUT => 30
    ]);

    $response = curl_exec($curl);
    $error = curl_error($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    debug_log('Judge0 payload', $payload);
    debug_log('Judge0 HTTP code', $httpCode);
    debug_log('Judge0 raw response', $response === false ? 'false' : $response);
    debug_log('Judge0 curl error', $error);

    if ($response === false) {
        return ['ok' => false, 'error' => $error ?: 'cURL request failed'];
    }

    if ($error) {
        return ['ok' => false, 'error' => $error];
    }

    $decoded = json_decode($response, true);
    if (!is_array($decoded)) {
        return [
            'ok' => false,
            'error' => 'Invalid response from Judge0',
            'raw_response' => $response,
            'http_code' => $httpCode
        ];
    }

    if ($httpCode >= 400) {
        return [
            'ok' => false,
            'error' => $decoded['message'] ?? ($decoded['error'] ?? 'Judge0 request failed'),
            'raw_response' => $decoded,
            'http_code' => $httpCode
        ];
    }

    return ['ok' => true, 'data' => $decoded];
}

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

if (!is_array($data) || empty($data)) {
    $data = $_POST;
}

$code = trim((string)($data['code'] ?? ''));
$lang = strtolower(trim((string)($data['language'] ?? $data['lang'] ?? 'c')));
$questionId = (int)($data['question_id'] ?? 0);
$customInput = (string)($data['input'] ?? $data['custom_input'] ?? '');
$useCustomInput = false;

if (isset($data['use_custom'])) {
    $useCustomInput = in_array((string)$data['use_custom'], ['1', 'true', 'yes'], true);
}

if ($code === '') {
    respond([
        'status' => 'error',
        'output' => 'No code received'
    ], 400);
}

$languageMap = [
    'c' => $JUDGE0_LANG_C,
    'cpp' => $JUDGE0_LANG_CPP,
    'c++' => $JUDGE0_LANG_CPP
];

if (!isset($languageMap[$lang])) {
    respond([
        'status' => 'error',
        'output' => 'Unsupported language'
    ], 400);
}

$languageId = $languageMap[$lang];

if ($useCustomInput || $questionId <= 0) {
    $payload = [
        'source_code' => $code,
        'language_id' => $languageId,
        'stdin' => $customInput
    ];

    $judge0 = judge0_request($payload, $JUDGE0_BASE_URL, $JUDGE0_API_KEY);
    if (!$judge0['ok']) {
        respond([
            'status' => 'error',
            'output' => $judge0['error'],
            'details' => $judge0['raw_response'] ?? null,
            'http_code' => $judge0['http_code'] ?? null
        ], 502);
    }

    $result = $judge0['data'];

    if (!empty($result['compile_output'])) {
        respond([
            'status' => 'compile_error',
            'output' => $result['compile_output']
        ]);
    }

    if (!empty($result['stderr'])) {
        respond([
            'status' => 'runtime_error',
            'output' => $result['stderr']
        ]);
    }

    if (!empty($result['message'])) {
        respond([
            'status' => 'runtime_error',
            'output' => $result['message']
        ]);
    }

    respond([
        'status' => 'success',
        'output' => $result['stdout'] ?? ''
    ]);
}

$stmt = $conn->prepare('SELECT test_case_id, input, expected_output, is_hidden FROM test_cases WHERE question_id = ? ORDER BY test_case_id ASC');
if (!$stmt) {
    respond([
        'status' => 'error',
        'output' => 'Failed to prepare test case query: ' . $conn->error
    ], 500);
}

$stmt->bind_param('i', $questionId);
$stmt->execute();
$resultSet = $stmt->get_result();

$testCases = [];
while ($row = $resultSet->fetch_assoc()) {
    $testCases[] = $row;
}
$stmt->close();

if (empty($testCases)) {
    respond([
        'status' => 'error',
        'output' => 'No test cases found for this question'
    ], 404);
}

$results = [];
$allPassed = true;

foreach ($testCases as $case) {
    $payload = [
        'source_code' => $code,
        'language_id' => $languageId,
        'stdin' => (string)$case['input']
    ];

    $judge0 = judge0_request($payload, $JUDGE0_BASE_URL, $JUDGE0_API_KEY);
    if (!$judge0['ok']) {
        respond([
            'status' => 'error',
            'output' => $judge0['error'],
            'details' => $judge0['raw_response'] ?? null,
            'http_code' => $judge0['http_code'] ?? null
        ], 502);
    }

    $judgeResult = $judge0['data'];

    if (!empty($judgeResult['compile_output'])) {
        respond([
            'status' => 'compile_error',
            'output' => $judgeResult['compile_output']
        ]);
    }

    if (!empty($judgeResult['stderr'])) {
        respond([
            'status' => 'runtime_error',
            'output' => $judgeResult['stderr']
        ]);
    }

    if (!empty($judgeResult['message'])) {
        respond([
            'status' => 'runtime_error',
            'output' => $judgeResult['message']
        ]);
    }

    $actualOutput = normalize_output((string)($judgeResult['stdout'] ?? ''));
    $expectedOutput = normalize_output((string)($case['expected_output'] ?? ''));
    $passed = ($actualOutput === $expectedOutput);

    if (!$passed) {
        $allPassed = false;
    }

    $results[] = [
        'test_case_id' => (int)$case['test_case_id'],
        'passed' => $passed,
        'expected_output' => $expectedOutput,
        'actual_output' => $actualOutput,
        'is_hidden' => (int)$case['is_hidden']
    ];
}

respond([
    'status' => $allPassed ? 'success' : 'failed',
    'output' => $allPassed ? 'All test cases passed' : 'Some test cases failed',
    'results' => $results
]);