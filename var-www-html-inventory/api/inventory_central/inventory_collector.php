<?php
/*
|--------------------------------------------------------------------------
| Sentinela - Inventory Central Collector
|--------------------------------------------------------------------------
| Servidor central: 10.0.0.139
|--------------------------------------------------------------------------
| Objetivos desta versão:
| 1) Manter o frontend antigo funcionando sem alterações
| 2) Continuar retornando o JSON completo da coleta
| 3) Salvar os dados no MySQL em paralelo
| 4) Não quebrar a resposta caso o banco falhe
| 5) Suportar API nova (/api/v1/inventory.php) e antiga
|--------------------------------------------------------------------------
*/

header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('America/Sao_Paulo');

// ======================================================================
// CONFIGURAÇÃO GERAL
// ======================================================================

$TOKEN = "sentinela_token_123";

$SERVERS = [
    "10.0.0.141",
    "10.0.0.37",
    "10.0.0.139",
    "10.0.0.5",
    "10.0.2.148"
];

$TIMEOUT = 8;

// ----------------------------------------------------------------------
// CONFIGURAÇÃO DO BANCO
// ----------------------------------------------------------------------
$DB_HOST = "10.0.0.139";
$DB_NAME = "sentinela";
$DB_USER = "inventory";         
$DB_PASS = "Ep@m1n0nd@s";


// ======================================================================
// CONEXÃO MYSQL
// ======================================================================

function getPdo($host, $db, $user, $pass)
{
    try {
        return new PDO(
            "mysql:host={$host};dbname={$db};charset=utf8mb4",
            $user,
            $pass,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]
        );
    } catch (Throwable $e) {
        return null;
    }
}


// ======================================================================
// NORMALIZAÇÃO
// ======================================================================

function parseMemory($text)
{
    $lines = explode("\n", trim((string)$text));
    if (count($lines) < 2) return null;
    $parts = preg_split('/\s+/', trim($lines[1]));
    return [
        "total" => $parts[1] ?? null,
        "used"  => $parts[2] ?? null,
        "free"  => $parts[3] ?? null
    ];
}

function parseDisks($text)
{
    $lines = explode("\n", trim((string)$text));
    $result = [];

    foreach ($lines as $i => $line) {
        if ($i === 0 || trim($line) === '') continue;

        $cols = preg_split('/\s+/', trim($line));
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
    $text = trim((string)$text);
    if ($text === '') return null;

    $parts = preg_split('/\s+/', $text);

    return [
        "interface" => $parts[0] ?? null,
        "status"    => $parts[1] ?? null,
        "ip"        => $parts[2] ?? null
    ];
}

function normalizeRaw($raw)
{
    if (!$raw || !is_array($raw)) return null;

    return [
        "memory"  => parseMemory($raw["memory"] ?? ""),
        "disks"   => parseDisks($raw["disks"] ?? ""),
        "network" => parseNetwork($raw["network"] ?? "")
    ];
}


// ======================================================================
// CONSULTA
// ======================================================================

function consultarServidor($ip, $token, $timeout)
{
    $endpoints = [
        "http://{$ip}:8090/api/v1/inventory.php",
        "http://{$ip}:8090/api/inventory_agent.php"
    ];

    foreach ($endpoints as $url) {
        $context = stream_context_create([
            "http" => [
                "method"  => "GET",
                "header"  => "Authorization: Bearer {$token}\r\n",
                "timeout" => $timeout
            ]
        ]);

        $inicio = microtime(true);
        $response = @file_get_contents($url, false, $context);
        $tempo = round((microtime(true) - $inicio) * 1000);

        if (!$response) continue;

        $json = json_decode($response, true);
        if (!is_array($json)) continue;

        $data = $json["data"] ?? $json;
        $data["raw_parsed"] = normalizeRaw($data["raw"] ?? null);

        return [
            "ip" => $ip,
            "status" => "online",
            "api_version" => $json["meta"]["version"] ?? "legacy",
            "response_ms" => $tempo,
            "data" => $data
        ];
    }

    return [
        "ip" => $ip,
        "status" => "offline",
        "error" => "nenhum endpoint respondeu"
    ];
}


// ======================================================================
// BANCO
// ======================================================================

function salvarNoBanco($pdo, $server)
{
    if (!$pdo) return false;

    try {
        $stmt = $pdo->prepare("
            INSERT INTO server_inventory_logs (
                server_ip, hostname, api_version, response_ms, status,
                host_ip, agent_hostname, agent_version, agent_api_version,
                agent_execution_ms, cpu_cores, mem_total_kb, uptime_seconds,
                mem_total_str, mem_used_str, mem_free_str,
                network_interface, network_status, network_ip,
                raw_payload, raw_original, raw_parsed,
                error_message, time_bucket
            ) VALUES (
                :server_ip, :hostname, :api_version, :response_ms, :status,
                :host_ip, :agent_hostname, :agent_version, :agent_api_version,
                :agent_execution_ms, :cpu_cores, :mem_total_kb, :uptime_seconds,
                :mem_total_str, :mem_used_str, :mem_free_str,
                :network_interface, :network_status, :network_ip,
                :raw_payload, :raw_original, :raw_parsed,
                :error_message, :time_bucket
            )
            ON DUPLICATE KEY UPDATE status = VALUES(status)
        ");

        $timeBucket = date('Y-m-d H:i:00');

        if (($server["status"] ?? "") !== "online") {
            return $stmt->execute([
                ':server_ip' => $server["ip"] ?? null,
                ':status' => 'offline',
                ':error_message' => $server["error"] ?? 'erro',
                ':time_bucket' => $timeBucket
            ]);
        }

        $data = $server["data"] ?? [];

        return $stmt->execute([
            ':server_ip' => $server["ip"] ?? null,
            ':hostname' => $data["hostname"] ?? null,
            ':api_version' => $server["api_version"] ?? null,
            ':response_ms' => $server["response_ms"] ?? null,
            ':status' => 'online',
            ':raw_payload' => json_encode($data),
            ':time_bucket' => $timeBucket
        ]);

    } catch (Throwable $e) {
        return false;
    }
}


// ======================================================================
// MÉTRICAS PARA GRAFANA
// ======================================================================

function salvarMetricas($pdo, $server)
{
    if (!$pdo) return false;

    try {
        $stmt = $pdo->prepare("
            INSERT INTO server_metrics_full (
                server_ip, hostname, time, status,
                cpu_cores, mem_total_kb, mem_used_kb, mem_free_kb, mem_used_percent,
                uptime_seconds, response_ms, api_version,
                disks_json, network_json, raw_json
            ) VALUES (
                :server_ip, :hostname, :time, :status,
                :cpu_cores, :mem_total_kb, :mem_used_kb, :mem_free_kb, :mem_used_percent,
                :uptime_seconds, :response_ms, :api_version,
                :disks_json, :network_json, :raw_json
            )
            ON DUPLICATE KEY UPDATE
                status = VALUES(status),
                mem_used_kb = VALUES(mem_used_kb),
                mem_free_kb = VALUES(mem_free_kb),
                mem_used_percent = VALUES(mem_used_percent),
                response_ms = VALUES(response_ms)
        ");

        $data = $server["data"] ?? [];
        $parsed = $data["raw_parsed"] ?? [];
        $mem = $parsed["memory"] ?? null;

        $memTotal = isset($mem["total"]) ? intval($mem["total"]) : null;
        $memUsed  = isset($mem["used"]) ? intval($mem["used"]) : null;
        $memFree  = isset($mem["free"]) ? intval($mem["free"]) : null;
        $memPercent = ($memTotal > 0) ? round(($memUsed / $memTotal) * 100, 2) : null;

        return $stmt->execute([
            ':server_ip' => $server["ip"] ?? null,
            ':hostname' => $data["hostname"] ?? null,
            ':time' => date('Y-m-d H:i:s'),
            ':status' => $server["status"] ?? null,
            ':cpu_cores' => $data["system"]["cpu_cores"] ?? null,
            ':mem_total_kb' => $memTotal,
            ':mem_used_kb' => $memUsed,
            ':mem_free_kb' => $memFree,
            ':mem_used_percent' => $memPercent,
            ':uptime_seconds' => $data["system"]["uptime"] ?? null,
            ':response_ms' => $server["response_ms"] ?? null,
            ':api_version' => $server["api_version"] ?? null,
            ':disks_json' => json_encode($parsed["disks"] ?? []),
            ':network_json' => json_encode($parsed["network"] ?? null),
            ':raw_json' => json_encode($data)
        ]);

    } catch (Throwable $e) {
        return false;
    }
}


// ======================================================================
// EXECUÇÃO
// ======================================================================

$pdo = getPdo($DB_HOST, $DB_NAME, $DB_USER, $DB_PASS);

$resultado = [];
$db_saved_count = 0;

foreach ($SERVERS as $serverIp) {
    $res = consultarServidor($serverIp, $TOKEN, $TIMEOUT);

    $resultado[] = $res;

    if (($res["status"] ?? "") === "online") {
        salvarMetricas($pdo, $res);
    }

    if (salvarNoBanco($pdo, $res)) {
        $db_saved_count++;
    }
}


// ======================================================================
// LAST UPDATE (BANCO)
// ======================================================================

$last_update = null;

if ($pdo) {
    try {
        $stmt = $pdo->query("
            SELECT DATE_FORMAT(
                CONVERT_TZ(MAX(collected_at), '+00:00', '-03:00'),
                '%d-%m-%Y %H:%i:%s'
            ) as last_update
            FROM server_inventory_logs
        ");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $last_update = $row['last_update'] ?? null;
    } catch (Throwable $e) {
        $last_update = null;
    }
}


// ======================================================================
// RESPOSTA
// ======================================================================

echo json_encode([
    "collector"  => "10.0.0.139",
    "timestamp"  => date("Y-m-d H:i:s"),
    "total"      => count($SERVERS),
    "servers"    => $resultado,
    "last_update"=> $last_update,
    "db"         => [
        "enabled" => $pdo ? true : false,
        "saved"   => $db_saved_count
    ]
], JSON_PRETTY_PRINT);