<?php
/*
|--------------------------------------------------------------------------
| Sentinela - Inventory Central Collector (ajustado para nova estrutura)
|--------------------------------------------------------------------------
| Servidor Central: 10.0.0.5
|--------------------------------------------------------------------------
| Padrão: /api/inventory_agent.php em todos os servidores na porta 8090
| Novo padrão: /api/v1/inventory.php (API versionada)
|--------------------------------------------------------------------------
| Este collector agora:
| - Suporta API nova (v1)
| - Mantém compatibilidade com API antiga
| - Normaliza dados brutos (raw) para uso estruturado
|--------------------------------------------------------------------------
*/

header('Content-Type: application/json');

// ================= CONFIGURAÇÃO =================

$TOKEN = "sentinela_token_123";

// Lista de IPs dos agentes; ajuste conforme necessário
$SERVERS = [
    "10.0.0.141",
    "10.0.0.37",
    "10.0.0.139",
    "10.0.0.5",
    "10.0.2.148"
];

$TIMEOUT = 8;

// ================================================


// ================= FUNÇÕES DE NORMALIZAÇÃO =================
/*
|--------------------------------------------------------------------------
| Estas funções convertem o conteúdo textual do campo "raw"
| em estruturas organizadas (arrays), facilitando:
| - dashboards
| - persistência em banco
| - análise futura
|--------------------------------------------------------------------------
*/

function parseMemory($text)
{
    $lines = explode("\n", trim($text));
    if (count($lines) < 2) return null;

    $parts = preg_split('/\s+/', $lines[1]);

    return [
        "total" => $parts[1] ?? null,
        "used"  => $parts[2] ?? null,
        "free"  => $parts[3] ?? null
    ];
}

function parseDisks($text)
{
    $lines = explode("\n", trim($text));
    $result = [];

    foreach ($lines as $i => $line) {
        if ($i == 0 || trim($line) === "") continue;

        $cols = preg_split('/\s+/', $line);
        $result[] = [
            "name" => $cols[0] ?? null,
            "size" => $cols[1] ?? null,
            "type" => $cols[2] ?? null
        ];
    }

    return $result;
}

function parseNetwork($text)
{
    if (!$text) return null;

    $parts = preg_split('/\s+/', trim($text));

    return [
        "interface" => $parts[0] ?? null,
        "status"    => $parts[1] ?? null,
        "ip"        => $parts[2] ?? null
    ];
}

function normalizeRaw($raw)
{
    if (!$raw) return null;

    return [
        "memory"  => parseMemory($raw["memory"] ?? ""),
        "disks"   => parseDisks($raw["disks"] ?? ""),
        "network" => parseNetwork($raw["network"] ?? "")
    ];
}

// ==========================================================


// ================= FUNÇÃO PRINCIPAL =================
/*
|--------------------------------------------------------------------------
| consultarServidor()
|--------------------------------------------------------------------------
| Responsável por:
| - Tentar acessar API nova (v1)
| - Fazer fallback para API antiga
| - Medir tempo de resposta
| - Tratar erros HTTP e JSON
| - Normalizar dados
|--------------------------------------------------------------------------
*/

function consultarServidor($ip, $token, $timeout)
{
    // Lista de endpoints (prioridade: novo → antigo)
    $endpoints = [
        "http://$ip:8090/api/v1/inventory.php",
        "http://$ip:8090/api/inventory_agent.php"
    ];

    foreach ($endpoints as $url) {

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

        // ================= ERRO DE CONEXÃO =================
        if ($response === false) {
            continue; // tenta próximo endpoint
        }

        // ================= JSON =================
        $json = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            continue; // tenta próximo endpoint
        }

        // ================= DETECTAR FORMATO =================
        if (isset($json["data"])) {
            // API nova (v1)
            $data = $json["data"];
            $api_version = $json["meta"]["version"] ?? "v1";
        } else {
            // API antiga (legacy)
            $data = $json;
            $api_version = "legacy";
        }

        // ================= NORMALIZAÇÃO =================
        $data["raw_parsed"] = normalizeRaw($data["raw"] ?? null);

        // ================= SUCESSO =================
        return [
            "ip"          => $ip,
            "status"      => "online",
            "api_version" => $api_version,
            "response_ms" => $tempo,
            "data"        => $data
        ];
    }

    // ================= FALHA TOTAL =================
    return [
        "ip"          => $ip,
        "status"      => "offline",
        "error"       => "nenhum endpoint respondeu"
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