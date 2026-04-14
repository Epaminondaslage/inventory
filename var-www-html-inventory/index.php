<?php
/*
|--------------------------------------------------------------------------
| Sentinela - Dashboard de Inventário de Servidores
|--------------------------------------------------------------------------
| Este painel consome o collector central e exibe:
| - Status dos servidores (online/offline)
| - Informações básicas (hostname, IP)
| - Métricas (CPU, memória)
| - Versão da API (v1 ou legacy)
| - Detalhes completos via modal (RAW + parsed)
|--------------------------------------------------------------------------
| Compatível com:
| - API legacy (/api/inventory_agent.php)
| - API v1 (/api/v1/inventory.php)
|--------------------------------------------------------------------------
*/

$collectorUrl = "http://localhost/inventory/api/inventory_central/inventory_collector.php";

$response = @file_get_contents($collectorUrl);
$data = json_decode($response, true);

// Lista de servidores retornados pelo collector
$servers = $data['servers'] ?? [];

// Contador de offline
$offlineCount = 0;

foreach ($servers as $s) {
    if ($s['status'] !== 'online') {
        $offlineCount++;
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sentinela - Inventário</title>

<link rel="stylesheet" href="css/style.css">

<script>
/*
|--------------------------------------------------------------------------
| Controle do modal de detalhes
|--------------------------------------------------------------------------
*/
function openModal(id) {
    const content = document.getElementById(id).innerHTML;
    document.getElementById("modalBody").innerHTML = content;
    document.getElementById("modal").style.display = "flex";
}

function closeModal() {
    document.getElementById("modal").style.display = "none";
}

window.onclick = function(event) {
    const modal = document.getElementById("modal");
    if (event.target === modal) {
        modal.style.display = "none";
    }
};
</script>

</head>
<body>

<div class="container">

    <!-- ================= HEADER ================= -->
    <div class="header">
        <img src="img/logo_inventory.jpg" alt="Logo Inventário" class="logo">
        <h1>Sentinela - Inventário de Servidores</h1>
        <button onclick="history.back()" class="btn">Voltar</button>
    </div>

    <!-- ================= ALERTA ================= -->
    <?php if ($offlineCount > 0): ?>
        <div class="alert">
            ⚠ <?= $offlineCount ?> servidor(es) fora do ar!
        </div>
    <?php endif; ?>

    <!-- ================= TABELA ================= -->
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>IP</th>
                    <th>Status</th>
                    <th>Hostname</th>
                    <th>Host IP</th>
                    <th>CPU</th>
                    <th>Memória</th>
                    <th>API</th>
                    <th>Tempo (ms)</th>
                    <th>Detalhes</th>
                </tr>
            </thead>

            <tbody>

            <?php foreach ($servers as $i => $server): ?>
                <?php $d = $server['data'] ?? null; ?>

                <tr class="<?= $server['status'] ?>">

                    <!-- IP -->
                    <td><?= $server['ip'] ?></td>

                    <!-- STATUS -->
                    <td>
                        <?php if ($server['status'] === 'online'): ?>
                            <span class="badge badge-online">
                                <span class="dot dot-online"></span>
                                ONLINE
                            </span>
                        <?php else: ?>
                            <span class="badge badge-offline">
                                <span class="dot dot-offline"></span>
                                OFFLINE
                            </span>
                        <?php endif; ?>
                    </td>

                    <!-- HOSTNAME -->
                    <td><?= htmlspecialchars($d['hostname'] ?? '-') ?></td>

                    <!-- HOST IP -->
                    <td><?= htmlspecialchars($d['host_ip'] ?? '-') ?></td>

                    <!-- CPU -->
                    <td><?= $d['system']['cpu_cores'] ?? '-' ?></td>

                    <!-- MEMÓRIA -->
                    <td>
                        <?php
                        $mem = $d['raw_parsed']['memory'] ?? null;
                        echo $mem ? "{$mem['used']} / {$mem['total']}" : '-';
                        ?>
                    </td>

                    <!-- API VERSION -->
                    <td><?= $server['api_version'] ?? '-' ?></td>

                    <!-- TEMPO -->
                    <td><?= $server['response_ms'] ?? '-' ?></td>

                    <!-- DETALHES -->
                    <td>
                        <?php if ($server['status'] === 'online'): ?>
                            <button class="btn-small" onclick="openModal('raw<?= $i ?>')">
                                Detalhes
                            </button>

                            <!-- CONTEÚDO DO MODAL -->
                            <div id="raw<?= $i ?>" style="display:none;">

                                <h2>Servidor: <?= htmlspecialchars($d['hostname'] ?? '-') ?></h2>

                                <h3>Resumo</h3>
                                <pre><?= htmlspecialchars(json_encode([
                                    "ip" => $server['ip'],
                                    "api_version" => $server['api_version'],
                                    "tempo_ms" => $server['response_ms']
                                ], JSON_PRETTY_PRINT)) ?></pre>

                                <h3>Dados Estruturados (RAW PARSED)</h3>
                                <pre><?= htmlspecialchars(json_encode($d['raw_parsed'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>

                                <h3>Dados Completos</h3>
                                <pre><?= htmlspecialchars(json_encode($d, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>

                                <h3>RAW Original</h3>
                                <pre><?= htmlspecialchars(json_encode($d['raw'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>

                            </div>

                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>

                </tr>

            <?php endforeach; ?>

            </tbody>
        </table>
    </div>

</div>

<!-- ================= MODAL ================= -->
<div id="modal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeModal()">×</span>
        <div id="modalBody"></div>
    </div>
</div>

</body>
</html>