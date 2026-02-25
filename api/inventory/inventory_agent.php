<?php

header('Content-Type: application/json');

// ===== CONFIGURAÇÃO =====
$TOKEN = "sentinela_token_123"; // ALTERE ISSO
$SCRIPT = "/var/www/html/api/inventory/inventory.sh";
// ==========================

// Verificar token via header Authorization
$headers = getallheaders();
$auth = $headers['Authorization'] ?? '';

if (!preg_match('/Bearer\s(\S+)/', $auth, $matches)) {
    http_response_code(401);
    echo json_encode(["error" => "Authorization header missing"]);
    exit;
}

if ($matches[1] !== $TOKEN) {
    http_response_code(403);
    echo json_encode(["error" => "Invalid token"]);
    exit;
}

// Verificar se script existe
if (!file_exists($SCRIPT)) {
    http_response_code(500);
    echo json_encode(["error" => "inventory.sh not found"]);
    exit;
}

// Executar o script
$output = shell_exec("/usr/bin/env bash " . escapeshellarg($SCRIPT));

if (!$output) {
    http_response_code(500);
    echo json_encode(["error" => "Failed to execute inventory"]);
    exit;
}

// Validar JSON retornado
$json = json_decode($output, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(500);
    echo json_encode([
        "error" => "Invalid JSON returned",
        "details" => json_last_error_msg()
    ]);
    exit;
}

// Retornar JSON formatado
echo json_encode($json, JSON_PRETTY_PRINT);
