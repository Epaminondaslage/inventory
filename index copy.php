<?php

$collectorUrl = "http://localhost/api/inventory_central/inventory_collector.php";
$response = @file_get_contents($collectorUrl);
$data = json_decode($response, true);

$servers = $data['servers'] ?? [];
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
<title>Inventário de Servidores</title>
<link rel="stylesheet" href="css/style.css">

<script>
function openModal(id) {
    const content = document.getElementById(id).innerHTML;
    document.getElementById("modalBody").innerHTML = content;
    document.getElementById("modal").style.display = "flex";
}

function closeModal() {
    document.getElementById("modal").style.display = "none";
}

// Fecha modal clicando fora
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

    <div class="header">
        <img src="img/logo_inventory.jpg" alt="Logo Inventário" class="logo">
        <h1>Inventário de Servidores</h1>
        <button onclick="history.back()" class="btn">Voltar</button>
    </div>

    <?php if ($offlineCount > 0): ?>
        <div class="alert">
            ⚠ <?= $offlineCount ?> servidor(es) fora do ar!
        </div>
    <?php endif; ?>

    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>IP</th>
                    <th>Status</th>
                    <th>Hostname</th>
                    <th>Tempo (ms)</th>
                    <th>Detalhes</th>
                </tr>
            </thead>
            <tbody>

            <?php foreach ($servers as $i => $server): ?>
                <?php $d = $server['data'] ?? null; ?>
                <tr class="<?= $server['status'] ?>">
                    <td><?= $server['ip'] ?></td>
                    <td><?= strtoupper($server['status']) ?></td>
                    <td><?= $d['hostname'] ?? '-' ?></td>
                    <td><?= $server['response_ms'] ?? '-' ?></td>
                    <td>
                        <?php if ($server['status'] === 'online'): ?>
                            <button class="btn-small" onclick="openModal('raw<?= $i ?>')">
                                Ver RAW
                            </button>

                            <!-- Conteúdo RAW escondido -->
                            <div id="raw<?= $i ?>" style="display:none;">

                                <?php foreach ($d as $key => $value): ?>
                                    <h3><?= strtoupper($key) ?></h3>
                                    <pre><?= htmlspecialchars($value) ?></pre>
                                <?php endforeach; ?>

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

<!-- MODAL -->
<div id="modal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeModal()">×</span>
        <div id="modalBody"></div>
    </div>
</div>

</body>
</html>