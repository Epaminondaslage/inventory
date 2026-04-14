<?php

header('Content-Type: application/json');

// ================= CONFIGURAÇÃO =================
$TOKEN  = "sentinela_token_123";
$SCRIPT = "/scripts/inventory.sh"; // corrigido para padrão do container
$TIMEOUT = 5;
// ===============================================


// ================= AUTH =========================
function getAuthorizationHeader() {
    if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        return $_SERVER['HTTP_AUTHORIZATION'];
    }

    if (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        return $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    }

    if (function_exists('getallheaders')) {
        $headers = getallheaders();
        foreach ($headers as $key => $value) {
            if (strtolower($key) === 'authorization') {
                return $value;
            }
        }
    }

    return null;
}

$authHeader = getAuthorizationHeader();

if (!$authHeader) {
    http_response_code(401);
    echo json_encode(["error" => "Authorization header missing"]);
    exit;
}

if (!preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
    http_response_code(401);
    echo json_encode(["error" => "Invalid Authorization format"]);
    exit;
}

if (!hash_equals($TOKEN, $matches[1])) {
    http_response_code(403);
    echo json_encode(["error" => "Invalid token"]);
    exit;
}


// ================= VALIDAÇÕES ===================

if (!file_exists($SCRIPT)) {
    http_response_code(500);
    echo json_encode([
        "error" => "inventory.sh not found",
        "path"  => $SCRIPT
    ]);
    exit;
}

if (!is_executable($SCRIPT)) {
    http_response_code(500);
    echo json_encode([
        "error" => "inventory.sh not executable",
        "path"  => $SCRIPT
    ]);
    exit;
}


// ================= EXECUÇÃO =====================

$start = microtime(true);

// comando seguro com timeout
$command = sprintf(
    "timeout %ds /usr/bin/env bash %s 2>&1",
    $TIMEOUT,
    escapeshellarg($SCRIPT)
);

$output = shell_exec($command);

$executionTime = round((microtime(true) - $start) * 1000);

if ($output === null) {
    http_response_code(500);
    echo json_encode([
        "error" => "Failed to execute inventory script"
    ]);
    exit;
}


// ================= VALIDA JSON ==================

$json = json_decode($output, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(500);
    echo json_encode([
        "error"   => "Invalid JSON returned",
        "details" => json_last_error_msg(),
        "raw"     => substr($output, 0, 500)
    ]);
    exit;
}


// ================= HOSTNAME (FIX DOCKER) ========
// Corrige problema de hostname dentro de container
$hostHostname = @file_get_contents('/host/etc/hostname');

if ($hostHostname !== false) {
    $hostHostname = trim($hostHostname);
} else {
    $hostHostname = gethostname(); // fallback padrão
}


// ================= METADADOS ====================

$json['_agent'] = [
    "hostname"     => $hostHostname, // corrigido
    "execution_ms" => $executionTime,
    "timestamp"    => date("c"), // ISO 8601
    "agent_version"=> "1.0.1"    // ajuste mínimo (legado corrigido)
];


// ================= SUCESSO ======================

echo json_encode($json, JSON_UNESCAPED_SLASHES);