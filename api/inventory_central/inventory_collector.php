<?php
/*
|--------------------------------------------------------------------------
| Sentinela - Inventory Central Collector
|--------------------------------------------------------------------------
| Servidor Central: 10.0.0.5
|--------------------------------------------------------------------------
| Consulta todos os agentes de inventário e consolida resultados.
|--------------------------------------------------------------------------
*/

header('Content-Type: application/json');

// ================= CONFIGURAÇÃO =================
$TOKEN = "sentinela_token_123";

$SERVERS = [
    "10.0.0.141",
    "10.0.0.37",
    "10.0.0.139"
];

$TIMEOUT = 8; // segundos
// ================================================


function consultarServidor($ip, $token, $timeout)
{
    $url = "http://$ip/api/inventory/inventory_agent.php";

    $context = stream_context_create([
        "http" => [
            "method"  => "GET",
            "header"  => "Authorization: Bearer $token\r\n",
            "timeout" => $timeout
        ]
    ]);

    $inicio = microtime(true);
    $response = @file_get_contents($url, false, $context);
    $tempo = round((microtime(true) - $inicio) * 1000); // ms

    if ($response === false) {
        return [
            "ip"           => $ip,
            "status"       => "offline",
            "response_ms"  => $tempo
        ];
    }

    $json = json_decode($response, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        return [
            "ip"           => $ip,
            "status"       => "error_json",
            "response_ms"  => $tempo
        ];
    }

    return [
        "ip"           => $ip,
        "status"       => "online",
        "response_ms"  => $tempo,
        "data"         => $json
    ];
}


// ================= EXECUÇÃO =================

$resultado = [];

foreach ($SERVERS as $server) {
    $resultado[] = consultarServidor($server, $TOKEN, $TIMEOUT);
}

echo json_encode([
    "collector" => "10.0.0.5",
    "timestamp" => date("Y-m-d H:i:s"),
    "servers"   => $resultado
], JSON_PRETTY_PRINT);
