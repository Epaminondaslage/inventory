<?php
/**
 * inventory_collector.php — Coletor central de inventário
 * Projeto: Sentinela — Sítio Pé de Serra
 *
 * Lê últimos snapshots do MySQL inventory_db em vez de fazer
 * polling HTTP nos agentes. Retorna JSON para o frontend.
 *
 * Compatível com PHP >= 7.0
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-cache, no-store');

$DB_HOST = '127.0.0.1';
$DB_PORT = 3306;
$DB_USER = 'root';
$DB_PASS = 'Ep@m1n0nd@s';
$DB_NAME = 'inventory_db';

$filter  = isset($_GET['server']) ? trim($_GET['server']) : null;
$history = isset($_GET['history']) ? max(1, min(100, intval($_GET['history']))) : 1;

try {
    $pdo = new PDO(
        "mysql:host={$DB_HOST};port={$DB_PORT};dbname={$DB_NAME};charset=utf8mb4",
        $DB_USER, $DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
         PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
         PDO::ATTR_TIMEOUT => 5]
    );
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'DB: ' . $e->getMessage()]);
    exit;
}

$where = $filter ? 'WHERE s.hostname = :f OR s.ip = :f2' : '';
$stmt  = $pdo->prepare("SELECT * FROM servers {$where} ORDER BY label");
if ($filter) { $stmt->bindValue(':f', $filter); $stmt->bindValue(':f2', $filter); }
$stmt->execute();
$servers = $stmt->fetchAll();

$result = ['generated_at' => date('c'), 'servers' => []];

foreach ($servers as $srv) {
    $snap_stmt = $pdo->prepare("
        SELECT * FROM inventory_snapshots
        WHERE server_id = :id ORDER BY collected_at DESC LIMIT :lim
    ");
    $snap_stmt->bindValue(':id',  $srv['id'], PDO::PARAM_INT);
    $snap_stmt->bindValue(':lim', $history,   PDO::PARAM_INT);
    $snap_stmt->execute();
    $snaps = $snap_stmt->fetchAll();

    if (empty($snaps)) {
        $result['servers'][] = [
            'id' => $srv['id'], 'hostname' => $srv['hostname'],
            'ip' => $srv['ip'], 'label' => $srv['label'],
            'description' => $srv['description'],
            'online' => false, 'last_seen' => null,
            'snapshot' => null, 'services' => [], 'history' => []
        ];
        continue;
    }

    $latest   = $snaps[0];
    // Ping real via TCP na porta 8090 (timeout 2s)
    $tcp = @fsockopen($srv['ip'], 8090, $errno, $errstr, 2);
    $is_online = ($tcp !== false);
    if ($tcp) fclose($tcp);

    $svc_stmt = $pdo->prepare("
        SELECT port, protocol, bind_addr, container, scope
        FROM server_services WHERE snapshot_id = :sid ORDER BY scope, port
    ");
    $svc_stmt->bindValue(':sid', $latest['id'], PDO::PARAM_INT);
    $svc_stmt->execute();
    $services = $svc_stmt->fetchAll();

    $payload = json_decode($latest['payload_json'], true) ?: [];

    $result['servers'][] = [
        'id'          => $srv['id'],
        'hostname'    => $srv['hostname'],
        'ip'          => $srv['ip'],
        'label'       => $srv['label'],
        'description' => $srv['description'],
        'online'      => $is_online,
        'last_seen'   => $latest['collected_at'],
        'snapshot'    => [
            'collected_at'  => $latest['collected_at'],
            'cpu_cores'     => $latest['cpu_cores'],
            'mem_total'     => $latest['mem_total'],
            'mem_used'      => $latest['mem_used'],
            'mem_free'      => $latest['mem_free'],
            'uptime_sec'    => $latest['uptime_sec'],
            'os_name'       => $latest['os_name'],
            'arch'          => $latest['arch'],
            'agent_version' => $latest['agent_version'],
            'exec_ms'       => $latest['exec_ms'],
        ],
        'history'  => array_map(function($s) {
            return ['collected_at' => $s['collected_at'], 'mem_used' => $s['mem_used'], 'exec_ms' => $s['exec_ms']];
        }, $snaps),
        'services' => $services,
        'docker'   => $payload['services']['docker'] ?? [],
        'raw'      => $payload['raw'] ?? null,
    ];
}

$online = count(array_filter($result['servers'], function($s) { return $s['online']; }));
$result['summary'] = ['total' => count($result['servers']), 'online' => $online, 'offline' => count($result['servers']) - $online];

echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
