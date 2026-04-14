<?php

// ================= CONFIG =================
define('API_VERSION', 'v1');
$TOKEN  = "sentinela_token_123";
$SCRIPT = "/scripts/inventory.sh";
$TIMEOUT = 5;

// ================= HEADERS =================
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Authorization, Content-Type');

// ================= RESPONSE PADRÃO =================
function response_success($data) {
    http_response_code(200);
    echo json_encode([
        "status" => "success",
        "data" => $data,
        "meta" => [
            "version" => API_VERSION,
            "timestamp" => time()
        ]
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

function response_error($code, $message, $details = null) {
    http_response_code($code);
    echo json_encode([
        "status" => "error",
        "error" => [
            "code" => $code,
            "message" => $message,
            "details" => $details
        ]
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

// ================= AUTH =================
function getAuthorizationHeader() {
    if (isset($_SERVER['HTTP_AUTHORIZATION'])) return $_SERVER['HTTP_AUTHORIZATION'];
    if (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) return $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];

    if (function_exists('getallheaders')) {
        foreach (getallheaders() as $key => $value) {
            if (strtolower($key) === 'authorization') return $value;
        }
    }
    return null;
}

$authHeader = getAuthorizationHeader();

if (!$authHeader) {
    response_error(401, 'Authorization header missing');
}

if (!preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
    response_error(401, 'Invalid Authorization format');
}

if (!hash_equals($TOKEN, $matches[1])) {
    response_error(403, 'Invalid token');
}

// ================= VALIDAÇÕES =================
if (!file_exists($SCRIPT)) {
    response_error(500, 'inventory.sh not found', $SCRIPT);
}

if (!is_executable($SCRIPT)) {
    response_error(500, 'inventory.sh not executable', $SCRIPT);
}

// ================= EXECUÇÃO =================
$start = microtime(true);

$command = sprintf(
    "timeout %ds /usr/bin/env bash %s 2>&1",
    $TIMEOUT,
    escapeshellarg($SCRIPT)
);

$output = shell_exec($command);

$executionTime = round((microtime(true) - $start) * 1000);

if ($output === null) {
    response_error(500, 'Failed to execute inventory script');
}

// ================= VALIDA JSON =================
$json = json_decode($output, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    response_error(500, 'Invalid JSON returned', [
        "json_error" => json_last_error_msg(),
        "raw" => substr($output, 0, 500)
    ]);
}

// ================= METADADOS =================
$json['_agent'] = [
    "hostname"       => gethostname(),
    "execution_ms"   => $executionTime,
    "timestamp"      => date("c"),
    "agent_version"  => "1.1.0",
    "api_version"    => API_VERSION
];

// ================= SUCESSO =================
response_success($json);