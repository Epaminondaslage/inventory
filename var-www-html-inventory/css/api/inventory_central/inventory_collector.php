<?php
/*
|--------------------------------------------------------------------------
| Sentinela - Inventory Central Collector (v3 - padronizado)
|--------------------------------------------------------------------------
| Servidor Central: 10.0.0.5
|--------------------------------------------------------------------------
| Padrão: /api/inventory_agent.php (porta 8090) em todos os servidores
|--------------------------------------------------------------------------
*/

header('Content-Type: application/json');

// ================= CONFIGURAÇÃO =================

$TOKEN = "sentinela_token_123";

$SERVERS = [
    "10.0.0.141",
    "10.0.0.37",
    "10.0.0.139",
    "10.0.0.5",
    "10.0.2.148"
];

$TIMEOUT = 8;

// ================================================


// ================= FUNÇÃO PRINCIPAL =================

function consultarServidor($ip, $token, $timeout)
{
    // 🔥 PADRÃO FINAL DEFINIDO
    // Cada agent escuta na porta 8090 e expõe o endpoint /api/inventory_agent.php
    // Adicionamos a porta explicitamente para evitar conflitos com outros serviços
    $url = "http://$ip:8090/api/inventory_agent.php";

    $opts = [
        "http" => [
            "method"  => "GET",
            "header"  => "Authorization: Bearer $token\r\n",
            "timeout" => $timeout,
            "ignore_errors" => true // importante para capturar HTTP 401/403/500
        ]
    ];

    $context = stream_context_create($opts);

    $inicio = microtime(true);
    $response = @file_get_contents($url, false, $context);
    $tempo = round((microtime(true) - $inicio) * 1000);

    // ================= CAPTURAR STATUS HTTP =================
    $httpCode = null;

    if (isset($http_response_header[0])) {
        preg_match('{HTTP/\S+\s(\d{3})}', $http_response_header[0], $match);
        $httpCode = $match[1] ?? null;
    }

    // ================= ERRO DE CONEXÃO =================
    if ($response === false) {
        return [
            "ip"          => $ip,
            "status"      => "offline",
            "response_ms" => $tempo,
            "error"       => "sem resposta HTTP"
        ];
    }

    // ================= ERRO HTTP =================
    if ($httpCode !== 200) {
        return [
            "ip"          => $ip,
            "status"      => "http_error",
            "http_code"   => $httpCode,
            "response_ms" => $tempo,
            "raw"         => substr($response, 0, 200)
        ];
    }

    // ================= JSON =================
    $json = json_decode($response, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        return [
            "ip"          => $ip,
            "status"      => "error_json",
            "response_ms" => $tempo,
            "error"       => json_last_error_msg(),
            "raw"         => substr($response, 0, 300)
        ];
    }

    // ================= OK =================
    return [
        "ip"          => $ip,
        "status"      => "online",
        "response_ms" => $tempo,
        "data"        => $json
    ];
}


// ================= EXECUÇÃO =================

$resultado = [];

foreach ($SERVERS as $server) {
    $resultado[] = consultarServidor($server, $TOKEN, $TIMEOUT);
}


// ================= RESPOSTA FINAL =================

echo json_encode([
    "collector" => "10.0.0.5",
    "timestamp" => date("Y-m-d H:i:s"),
    "total"     => count($SERVERS),
    "servers"   => $resultado
], JSON_PRETTY_PRINT);