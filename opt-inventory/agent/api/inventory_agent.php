<?php
/**
 * inventory_agent.php — Agente de inventário Sentinela
 * Projeto: Sítio Pé de Serra
 *
 * Funcionalidades:
 *  - Autentica via Bearer token
 *  - Coleta portas TCP do host lendo /host/proc/net/tcp (PHP, sem nsenter)
 *  - Coleta containers Docker com portas via docker ps
 *  - Executa inventory.sh passando portas/docker via env vars
 *  - Publica JSON no MQTT sentinela/inventory/{hostname} com retain
 *  - Retorna JSON completo via HTTP
 *
 * Compatível com PHP >= 7.0
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Authorization, Content-Type');

// ── Configuração ──────────────────────────────────────────
define('API_VERSION',   'v1');
define('AGENT_VERSION', '2.1.0');

$TOKEN        = 'sentinela_token_123';
$SCRIPT       = '/scripts/inventory.sh';
$TIMEOUT      = 10;
$MQTT_BROKER  = '10.0.0.141';
$MQTT_PORT    = 1883;
$MQTT_USER    = 'mqtt';
$MQTT_PASS    = 'planeta';
$MQTT_TOPIC_PREFIX = 'sentinela/inventory';

// ── Helpers ───────────────────────────────────────────────
function resp_error($code, $message, $details = null) {
    http_response_code($code);
    echo json_encode([
        'status' => 'error',
        'error'  => ['code' => $code, 'message' => $message, 'details' => $details]
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

// ── Auth ──────────────────────────────────────────────────
function get_auth_header() {
    if (!empty($_SERVER['HTTP_AUTHORIZATION']))           return $_SERVER['HTTP_AUTHORIZATION'];
    if (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) return $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    if (function_exists('getallheaders')) {
        foreach (getallheaders() as $k => $v) {
            if (strtolower($k) === 'authorization') return $v;
        }
    }
    return null;
}

$auth = get_auth_header();
if (!$auth)                                        resp_error(401, 'Authorization header missing');
if (!preg_match('/Bearer\s(\S+)/', $auth, $m))     resp_error(401, 'Invalid Authorization format');
if (!hash_equals($TOKEN, $m[1]))                   resp_error(403, 'Invalid token');

// ── Validações ────────────────────────────────────────────
if (!file_exists($SCRIPT))   resp_error(500, 'inventory.sh not found',      $SCRIPT);
if (!is_executable($SCRIPT)) resp_error(500, 'inventory.sh not executable', $SCRIPT);

// ── Coleta portas TCP do host via /host/proc/net/tcp ──────
// Lê diretamente em PHP — sem nsenter, sem ss, funciona dentro do container
function collect_host_ports() {
    $ports = [];
    $seen  = [];

    foreach (['/host/proc/net/tcp', '/host/proc/net/tcp6'] as $file) {
        if (!file_exists($file)) continue;

        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $i => $line) {
            if ($i === 0) continue; // pula header

            $parts = preg_split('/\s+/', trim($line));
            if (!isset($parts[3])) continue;

            // state 0A = LISTEN
            if (strtoupper($parts[3]) !== '0A') continue;

            // local_address: HEXIP:HEXPORT
            $addr = explode(':', $parts[1]);
            if (count($addr) < 2) continue;

            $port = hexdec($addr[count($addr) - 1]);
            if ($port <= 0 || $port > 65535) continue;
            if (isset($seen[$port])) continue;
            $seen[$port] = true;

            // Converte IP hex little-endian (apenas para tcp, não tcp6)
            $hex_ip = $addr[0];
            if (strlen($hex_ip) === 8) {
                // IPv4 little-endian
                $ip = long2ip(hexdec(implode('', array_reverse(str_split($hex_ip, 2)))));
            } else {
                $ip = '0.0.0.0';
            }

            $ports[] = ['bind' => $ip . ':' . $port, 'port' => $port];
        }
    }

    // Ordena por porta
    usort($ports, function($a, $b) { return $a['port'] - $b['port']; });

    return $ports;
}

// ── Coleta containers Docker com portas ───────────────────
function collect_docker() {
    $output = shell_exec("docker ps --all --format '{\"name\":\"{{.Names}}\",\"image\":\"{{.Image}}\",\"status\":\"{{.Status}}\",\"ports\":\"{{.Ports}}\"}' 2>/dev/null");
    if (!$output) return [];

    $containers = [];
    foreach (explode("\n", trim($output)) as $line) {
        if (empty($line)) continue;
        $ctr = json_decode($line, true);
        if (!$ctr) continue;

        // Parseia portas mapeadas do formato "0.0.0.0:3000->80/tcp"
        $mapped_ports = [];
        if (!empty($ctr['ports'])) {
            preg_match_all('/(?:[\d.]+):(\d+)->/', $ctr['ports'], $matches);
            foreach ($matches[1] as $p) {
                $mapped_ports[] = (int)$p;
            }
        }

        $containers[] = [
            'name'         => $ctr['name'],
            'image'        => $ctr['image'],
            'status'       => $ctr['status'],
            'ports_raw'    => $ctr['ports'],
            'mapped_ports' => $mapped_ports,
        ];
    }

    return $containers;
}

// ── Executa inventory.sh ──────────────────────────────────
$host_ports  = collect_host_ports();
$docker_ctrs = collect_docker();

// Serializa para passar ao shell como env vars
$ports_json  = json_encode($host_ports,  JSON_UNESCAPED_SLASHES);
$docker_json = json_encode($docker_ctrs, JSON_UNESCAPED_SLASHES);

$start  = microtime(true);
$cmd    = sprintf(
    'HOST_PORTS_JSON=%s HOST_DOCKER_JSON=%s timeout %ds /usr/bin/env bash %s 2>&1',
    escapeshellarg($ports_json),
    escapeshellarg($docker_json),
    $TIMEOUT,
    escapeshellarg($SCRIPT)
);
$output  = shell_exec($cmd);
$exec_ms = (int)round((microtime(true) - $start) * 1000);

if ($output === null) resp_error(500, 'Failed to execute inventory script');

$json = json_decode($output, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    resp_error(500, 'Invalid JSON from inventory script', [
        'json_error' => json_last_error_msg(),
        'raw'        => substr($output, 0, 500)
    ]);
}

// ── Corrige hostname ──────────────────────────────────────
$host_file = @file_get_contents('/host/etc/hostname');
$hostname  = $host_file !== false ? trim($host_file) : ($json['hostname'] ?? gethostname());
$json['hostname'] = $hostname;

// ── Metadados ─────────────────────────────────────────────
$json['_agent'] = [
    'hostname'      => $hostname,
    'execution_ms'  => $exec_ms,
    'timestamp'     => date('c'),
    'agent_version' => AGENT_VERSION,
    'api_version'   => API_VERSION,
];

// ── Publica no MQTT ───────────────────────────────────────
$topic   = $MQTT_TOPIC_PREFIX . '/' . $hostname;
$payload = json_encode($json, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

$mqtt_cmd = sprintf(
    'mosquitto_pub -h %s -p %d -u %s -P %s -t %s -m %s -r -q 1 > /dev/null 2>&1 &',
    escapeshellarg($MQTT_BROKER),
    $MQTT_PORT,
    escapeshellarg($MQTT_USER),
    escapeshellarg($MQTT_PASS),
    escapeshellarg($topic),
    escapeshellarg($payload)
);
shell_exec($mqtt_cmd);

// ── Resposta HTTP ─────────────────────────────────────────
http_response_code(200);
echo json_encode([
    'status' => 'success',
    'data'   => $json,
    'meta'   => [
        'version'   => API_VERSION,
        'exec_ms'   => $exec_ms,
        'timestamp' => time()
    ]
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
