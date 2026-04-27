<?php
// ============================================================
// INDEX-DEBUG.PHP - Debug versie van index.php
// Toont volledige database informatie per client
// ============================================================
define('MANTELZORG_DEBUG', true);

require_once 'includes/database.php';
require_once 'includes/auth.php';
requireLogin();
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DEBUG - Mantelzorg Checker Index</title>
    <style>
        body { font-family: monospace; max-width: 1400px; margin: 20px auto; padding: 20px; background: #1e1e1e; color: #d4d4d4; }
        h1 { color: #4ec9b0; border-bottom: 2px solid #4ec9b0; padding-bottom: 10px; }
        h2 { color: #dcdcaa; margin-top: 30px; }
        h3 { color: #9cdcfe; }
        .client-block { border: 2px solid #3c3c3c; margin: 20px 0; padding: 20px; border-radius: 6px; background: #252526; }
        .client-block.ok { border-left: 6px solid #4ec9b0; }
        .client-block.warn { border-left: 6px solid #dcdcaa; }
        .client-block.error { border-left: 6px solid #f44747; }
        pre { background: #1e1e1e; padding: 12px; border-radius: 4px; overflow-x: auto; font-size: 13px; line-height: 1.6; }
        .label { color: #9cdcfe; font-weight: bold; display: inline-block; min-width: 260px; }
        .val-green { color: #4ec9b0; font-weight: bold; }
        .val-orange { color: #dcdcaa; font-weight: bold; }
        .val-red { color: #f44747; font-weight: bold; }
        .val-id { color: #c586c0; font-weight: bold; font-size: 15px; }
        .val-ts { color: #ce9178; }
        .sql-block { background: #0d3349; border: 1px solid #264f78; padding: 10px; border-radius: 4px; margin: 8px 0; }
        .sql-keyword { color: #569cd6; }
        .section-title { background: #37373d; padding: 6px 12px; border-radius: 4px; color: #dcdcaa; margin: 15px 0 8px 0; font-size: 13px; letter-spacing: 1px; text-transform: uppercase; }
        .badge { display: inline-block; background: #264f78; color: #9cdcfe; padding: 3px 10px; border-radius: 12px; margin: 2px; font-size: 12px; }
        .no-data { color: #f44747; font-style: italic; }
        .debug-header { background: #2d2d00; border: 1px solid #dcdcaa; padding: 15px; border-radius: 6px; margin-bottom: 25px; }
        table.db-table { width: 100%; border-collapse: collapse; margin: 8px 0; }
        table.db-table th { background: #37373d; color: #dcdcaa; padding: 8px 12px; text-align: left; font-size: 12px; }
        table.db-table td { padding: 7px 12px; border-bottom: 1px solid #3c3c3c; font-size: 12px; color: #d4d4d4; }
        .divider { border: none; border-top: 1px solid #3c3c3c; margin: 15px 0; }
    </style>
</head>
<body>

<div class="debug-header">
    <h1>🔍 DEBUG - index.php</h1>
    <p>Gestart om: <span class="val-ts"><?php echo date('Y-m-d H:i:s'); ?></span> |
       Ingelogd als: <span class="val-green"><?php echo htmlspecialchars($_SESSION['mantelzorger_naam']); ?></span>
       (<span class="val-ts"><?php echo htmlspecialchars($_SESSION['mantelzorger_email']); ?></span>)</p>
    <p style="color:#f44747; font-weight:bold;">⚠️ ALLEEN VOOR DEBUGGING - NIET IN PRODUCTIE GEBRUIKEN</p>
</div>

<?php

// ============================================================
// STAP 1: Toon de hoofd-query die index.php gebruikt
// ============================================================
$indexQuery = "SELECT * FROM clients ORDER BY naam";

echo '<h2>STAP 1: Hoofd-query van index.php</h2>';
echo '<div class="section-title">SQL Query</div>';
echo '<div class="sql-block"><pre>';
echo '<span class="sql-keyword">SELECT</span> * <span class="sql-keyword">FROM</span> clients <span class="sql-keyword">ORDER BY</span> naam';
echo '</pre></div>';
echo '<p class="val-green">✓ Gebruikt de <strong>client_mantelzorgers</strong> koppeltabel via getClientMantelzorgers() per client.</p>';

$conn = getDbConnection();
$result = $conn->query($indexQuery);
$clients = $result->fetch_all(MYSQLI_ASSOC);
$conn->close();

echo '<div class="section-title">Resultaat (' . count($clients) . ' clients gevonden)</div>';

// ============================================================
// STAP 2: Toon ALLE clients uit de database (zonder JOIN)
// ============================================================
echo '<h2>STAP 2: Alle clients rechtstreeks uit database (zonder JOIN)</h2>';
$conn = getDbConnection();
$rawClients = $conn->query("SELECT * FROM clients ORDER BY id")->fetch_all(MYSQLI_ASSOC);
echo '<div class="sql-block"><pre><span class="sql-keyword">SELECT</span> * <span class="sql-keyword">FROM</span> clients <span class="sql-keyword">ORDER BY</span> id</pre></div>';
echo '<table class="db-table">';
echo '<tr><th>id</th><th>naam</th><th>unique_token (eerste 16 chars)</th><th>check_every_days</th><th>alert_time</th><th>paused</th><th>paused_until</th></tr>';
foreach ($rawClients as $rc) {
    echo '<tr>';
    echo '<td><span class="val-id">' . $rc['id'] . '</span></td>';
    echo '<td><strong>' . htmlspecialchars($rc['naam']) . '</strong></td>';
    echo '<td class="val-ts">' . htmlspecialchars(substr($rc['unique_token'] ?? '', 0, 16)) . '...</td>';
    echo '<td>' . htmlspecialchars($rc['check_every_days'] ?? 'NULL') . '</td>';
    echo '<td class="val-ts">' . htmlspecialchars($rc['alert_time'] ?? 'NULL') . '</td>';
    echo '<td>' . ($rc['paused'] ? '<span class="val-orange">JA</span>' : '<span class="val-green">nee</span>') . '</td>';
    echo '<td class="val-ts">' . htmlspecialchars($rc['paused_until'] ?? '-') . '</td>';
    echo '</tr>';
}
echo '</table>';

// ============================================================
// STAP 3: Toon ALLE mantelzorgers uit database
// ============================================================
echo '<h2>STAP 3: Alle mantelzorgers in database</h2>';
$allMantelzorgers = $conn->query("SELECT * FROM mantelzorgers ORDER BY id")->fetch_all(MYSQLI_ASSOC);
echo '<div class="sql-block"><pre><span class="sql-keyword">SELECT</span> * <span class="sql-keyword">FROM</span> mantelzorgers <span class="sql-keyword">ORDER BY</span> id</pre></div>';
echo '<table class="db-table">';
echo '<tr><th>id</th><th>naam</th><th>email</th></tr>';
foreach ($allMantelzorgers as $mz) {
    echo '<tr>';
    echo '<td><span class="val-id">' . $mz['id'] . '</span></td>';
    echo '<td><strong>' . htmlspecialchars($mz['naam']) . '</strong></td>';
    echo '<td class="val-ts">' . htmlspecialchars($mz['email']) . '</td>';
    echo '</tr>';
}
echo '</table>';

// ============================================================
// STAP 4: Toon ALLE rijen uit client_mantelzorgers koppeltabel
// ============================================================
echo '<h2>STAP 4: Koppeltabel client_mantelzorgers (gebruikt door manage-clients.php)</h2>';
$koppelingen = $conn->query("SELECT cm.*, c.naam as client_naam, m.naam as mantelzorger_naam FROM client_mantelzorgers cm JOIN clients c ON cm.client_id = c.id JOIN mantelzorgers m ON cm.mantelzorger_id = m.id ORDER BY cm.client_id")->fetch_all(MYSQLI_ASSOC);
echo '<div class="sql-block"><pre><span class="sql-keyword">SELECT</span> cm.*, c.naam as client_naam, m.naam as mantelzorger_naam' . "\n";
echo '<span class="sql-keyword">FROM</span> client_mantelzorgers cm' . "\n";
echo '<span class="sql-keyword">JOIN</span> clients c <span class="sql-keyword">ON</span> cm.client_id = c.id' . "\n";
echo '<span class="sql-keyword">JOIN</span> mantelzorgers m <span class="sql-keyword">ON</span> cm.mantelzorger_id = m.id' . "\n";
echo '<span class="sql-keyword">ORDER BY</span> cm.client_id</pre></div>';
if (empty($koppelingen)) {
    echo '<p class="no-data">❌ Geen koppelingen gevonden in client_mantelzorgers!</p>';
} else {
    echo '<table class="db-table">';
    echo '<tr><th>client_id</th><th>client_naam</th><th>mantelzorger_id</th><th>mantelzorger_naam</th></tr>';
    foreach ($koppelingen as $kop) {
        echo '<tr>';
        echo '<td><span class="val-id">' . $kop['client_id'] . '</span></td>';
        echo '<td><strong>' . htmlspecialchars($kop['client_naam']) . '</strong></td>';
        echo '<td><span class="val-id">' . $kop['mantelzorger_id'] . '</span></td>';
        echo '<td>' . htmlspecialchars($kop['mantelzorger_naam']) . '</td>';
        echo '</tr>';
    }
    echo '</table>';
}
$conn->close();

// ============================================================
// STAP 5: Per-client debug sectie
// ============================================================
echo '<h2>STAP 5: Per-client debug (zoals index.php ze verwerkt)</h2>';

foreach ($clients as $client) {
    $clientId = $client['id'];

    // Bepaal borderstijl op basis van status
    $borderClass = 'ok';

    echo '<div class="client-block ' . $borderClass . '">';

    // === CLIENT BASISDATA ===
    echo '<h3>Client: ' . htmlspecialchars($client['naam']) . '</h3>';
    echo '<div class="section-title">Database Basisdata</div>';
    echo '<pre>';
    echo '<span class="label">client.id:</span>              <span class="val-id">' . $clientId . '</span>' . "\n";
    echo '<span class="label">client.naam:</span>            <span class="val-green">' . htmlspecialchars($client['naam']) . '</span>' . "\n";
    echo '<span class="label">client.check_every_days:</span><span class="val-orange">' . htmlspecialchars($client['check_every_days'] ?? 'NULL') . '</span>' . "\n";
    echo '<span class="label">client.alert_time:</span>      <span class="val-ts">' . htmlspecialchars($client['alert_time'] ?? 'NULL') . '</span>' . "\n";
    echo '<span class="label">client.paused:</span>          ' . ($client['paused'] ? '<span class="val-orange">JA</span>' : '<span class="val-green">nee</span>') . "\n";
    echo '<span class="label">client.paused_until:</span>    <span class="val-ts">' . htmlspecialchars($client['paused_until'] ?? '-') . '</span>' . "\n";
    echo '<span class="label">client.unique_token:</span>    <span class="val-ts">' . htmlspecialchars(substr($client['unique_token'] ?? '', 0, 20)) . '...</span>' . "\n";
    echo '</pre>';

    // === MANTELZORGERS VIA KOPPELTABEL ===
    $mantelzorgersViaJunction = getClientMantelzorgers($clientId);
    echo '<div class="section-title">Mantelzorgers via client_mantelzorgers koppeltabel</div>';
    echo '<div class="sql-block"><pre><span class="sql-keyword">SELECT</span> m.* <span class="sql-keyword">FROM</span> mantelzorgers m' . "\n";
    echo '<span class="sql-keyword">JOIN</span> client_mantelzorgers cm <span class="sql-keyword">ON</span> m.id = cm.mantelzorger_id' . "\n";
    echo '<span class="sql-keyword">WHERE</span> cm.client_id = <span class="val-id">' . $clientId . '</span></pre></div>';
    if (empty($mantelzorgersViaJunction)) {
        echo '<p class="no-data">❌ GEEN mantelzorgers gevonden in koppeltabel voor client_id=' . $clientId . '!</p>';
    } else {
        echo '<table class="db-table">';
        echo '<tr><th>m.id</th><th>m.naam</th><th>m.email</th></tr>';
        foreach ($mantelzorgersViaJunction as $mz) {
            echo '<tr>';
            echo '<td><span class="val-id">' . $mz['id'] . '</span></td>';
            echo '<td><strong>' . htmlspecialchars($mz['naam']) . '</strong></td>';
            echo '<td class="val-ts">' . htmlspecialchars($mz['email']) . '</td>';
            echo '</tr>';
        }
        echo '</table>';
    }

    // === LAATSTE CHECK-IN (volledige row) ===
    echo '<div class="section-title">Laatste check-in (volledige database row)</div>';
    $conn = getDbConnection();
    $stmtCI = $conn->prepare("
        SELECT * FROM check_in_history
        WHERE client_id = ? AND event_type IN ('check_in', 'late_check_in')
        ORDER BY event_datetime DESC LIMIT 1
    ");
    $stmtCI->bind_param("i", $clientId);
    $stmtCI->execute();
    $lastCIRow = $stmtCI->get_result()->fetch_assoc();
    $stmtCI->close();

    echo '<div class="sql-block"><pre><span class="sql-keyword">SELECT</span> * <span class="sql-keyword">FROM</span> check_in_history <span class="sql-keyword">WHERE</span> client_id = <span class="val-id">' . $clientId . '</span>' . "\n";
    echo '<span class="sql-keyword">AND</span> event_type <span class="sql-keyword">IN</span> (\'check_in\', \'late_check_in\') <span class="sql-keyword">ORDER BY</span> event_datetime <span class="sql-keyword">DESC LIMIT</span> 1</pre></div>';
    if ($lastCIRow) {
        echo '<table class="db-table">';
        echo '<tr><th>id</th><th>client_id</th><th>event_type</th><th>event_datetime</th><th>notes</th></tr>';
        echo '<tr>';
        echo '<td><span class="val-id">' . $lastCIRow['id'] . '</span></td>';
        echo '<td><span class="val-id">' . $lastCIRow['client_id'] . '</span></td>';
        echo '<td>' . htmlspecialchars($lastCIRow['event_type']) . '</td>';
        echo '<td class="val-ts">' . htmlspecialchars($lastCIRow['event_datetime']) . '</td>';
        echo '<td>' . htmlspecialchars($lastCIRow['notes'] ?? '-') . '</td>';
        echo '</tr>';
        echo '</table>';

        // Bereken dagen geleden
        $dagenGeleden = floor((time() - strtotime($lastCIRow['event_datetime'])) / 86400);
        echo '<pre><span class="label">Dagen geleden:</span> <span class="val-orange">' . $dagenGeleden . ' dag(en)</span></pre>';
    } else {
        echo '<p class="no-data">Nog nooit ingecheckt</p>';
    }

    // === LAATSTE ALERT (volledige row uit check_in_history) ===
    echo '<div class="section-title">Laatste alert (volledige row uit check_in_history)</div>';
    $stmtAL = $conn->prepare("
        SELECT * FROM check_in_history
        WHERE client_id = ? AND event_type = 'alert_sent'
        ORDER BY event_datetime DESC LIMIT 1
    ");
    $stmtAL->bind_param("i", $clientId);
    $stmtAL->execute();
    $lastAlertRow = $stmtAL->get_result()->fetch_assoc();
    $stmtAL->close();

    echo '<div class="sql-block"><pre><span class="sql-keyword">SELECT</span> * <span class="sql-keyword">FROM</span> check_in_history <span class="sql-keyword">WHERE</span> client_id = <span class="val-id">' . $clientId . '</span>' . "\n";
    echo '<span class="sql-keyword">AND</span> event_type = \'alert_sent\' <span class="sql-keyword">ORDER BY</span> event_datetime <span class="sql-keyword">DESC LIMIT</span> 1</pre></div>';
    if ($lastAlertRow) {
        echo '<table class="db-table">';
        echo '<tr><th>id</th><th>client_id</th><th>event_type</th><th>event_datetime</th><th>notes</th></tr>';
        echo '<tr>';
        echo '<td><span class="val-id">' . $lastAlertRow['id'] . '</span></td>';
        echo '<td><span class="val-id">' . $lastAlertRow['client_id'] . '</span></td>';
        echo '<td>' . htmlspecialchars($lastAlertRow['event_type']) . '</td>';
        echo '<td class="val-ts">' . htmlspecialchars($lastAlertRow['event_datetime']) . '</td>';
        echo '<td>' . htmlspecialchars($lastAlertRow['notes'] ?? '-') . '</td>';
        echo '</tr>';
        echo '</table>';
    } else {
        echo '<p style="color:#9cdcfe;">Nog nooit een alert verstuurd</p>';
    }

    // === LAATSTE ALERT UIT SYSTEM_LOGS ===
    echo '<div class="section-title">Laatste alert uit system_logs</div>';

    // Check of system_logs tabel bestaat en kolommen heeft
    $tableCheck = $conn->query("SHOW TABLES LIKE 'system_logs'")->fetch_assoc();
    if ($tableCheck) {
        $stmtSL = $conn->prepare("
            SELECT * FROM system_logs
            WHERE client_id = ?
            ORDER BY log_datetime DESC LIMIT 3
        ");
        $stmtSL->bind_param("i", $clientId);
        $stmtSL->execute();
        $sysLogs = $stmtSL->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmtSL->close();

        echo '<div class="sql-block"><pre><span class="sql-keyword">SELECT</span> * <span class="sql-keyword">FROM</span> system_logs <span class="sql-keyword">WHERE</span> client_id = <span class="val-id">' . $clientId . '</span>' . "\n";
        echo '<span class="sql-keyword">ORDER BY</span> log_datetime <span class="sql-keyword">DESC LIMIT</span> 3</pre></div>';

        if (empty($sysLogs)) {
            echo '<p style="color:#9cdcfe;">Geen logs voor deze client</p>';
        } else {
            echo '<table class="db-table">';
            $firstRow = reset($sysLogs);
            $cols = array_keys($firstRow);
            echo '<tr>' . implode('', array_map(fn($c) => "<th>$c</th>", $cols)) . '</tr>';
            foreach ($sysLogs as $log) {
                echo '<tr>';
                foreach ($log as $col => $val) {
                    if ($col === 'client_id' || $col === 'id') {
                        echo '<td><span class="val-id">' . htmlspecialchars($val ?? '') . '</span></td>';
                    } elseif (str_contains($col, 'datetime') || str_contains($col, 'time')) {
                        echo '<td class="val-ts">' . htmlspecialchars($val ?? '') . '</td>';
                    } else {
                        echo '<td>' . htmlspecialchars($val ?? '') . '</td>';
                    }
                }
                echo '</tr>';
            }
            echo '</table>';
        }
    } else {
        echo '<p class="val-orange">⚠️ Tabel system_logs niet gevonden</p>';
    }

    // === SHOULDCHECKINTODAY berekening ===
    echo '<div class="section-title">shouldCheckInToday() berekening</div>';
    $checkEveryDays = $client['check_every_days'];
    echo '<pre>';
    echo '<span class="label">check_every_days:</span> <span class="val-orange">' . $checkEveryDays . '</span>' . "\n";
    if ($checkEveryDays == 1) {
        echo '<span class="label">Resultaat:</span> <span class="val-green">TRUE</span> (dagelijks, altijd inchecken)' . "\n";
    } elseif ($lastCIRow) {
        $lastDate = date('Y-m-d', strtotime($lastCIRow['event_datetime']));
        $today = date('Y-m-d');
        $daysSince = (strtotime($today) - strtotime($lastDate)) / 86400;
        $shouldCheck = $daysSince >= $checkEveryDays;
        echo '<span class="label">Laatste check-in datum:</span>    <span class="val-ts">' . $lastDate . '</span>' . "\n";
        echo '<span class="label">Vandaag:</span>                   <span class="val-ts">' . $today . '</span>' . "\n";
        echo '<span class="label">Dagen sinds check-in:</span>      <span class="val-orange">' . $daysSince . '</span>' . "\n";
        echo '<span class="label">Berekening:</span>                ' . $daysSince . ' >= ' . $checkEveryDays . ' = ';
        echo ($shouldCheck ? '<span class="val-red">TRUE (moet inchecken)</span>' : '<span class="val-green">FALSE (nog niet nodig)</span>') . "\n";
        if ($shouldCheck && $daysSince > $checkEveryDays) {
            echo '<span class="val-red">⚠️ WAARSCHUWING: ' . round($daysSince) . ' dagen geleden - meer dan interval!</span>' . "\n";
            echo '<span class="val-red">   Dit verklaart dagelijkse alerts: shouldCheckInToday() returned TRUE elke dag</span>' . "\n";
            echo '<span class="val-red">   totdat client opnieuw incheckt.</span>' . "\n";
        }
    } else {
        echo '<span class="label">Resultaat:</span> <span class="val-red">TRUE</span> (nooit ingecheckt)' . "\n";
    }
    echo '</pre>';

    $conn->close();
    echo '</div>'; // .client-block
}

?>

<div style="margin-top: 40px; padding: 20px; background: #2d2d00; border: 1px solid #dcdcaa; border-radius: 6px;">
    <h2 style="color:#dcdcaa; margin-top:0;">📋 Samenvatting gevonden problemen</h2>
    <p style="color:#d4d4d4;">Controleer per client of de bovenstaande "Vergelijking: legacy kolom vs koppeltabel" sectie een discrepantie toont.</p>
    <p style="color:#d4d4d4;">Controleer ook de shouldCheckInToday() berekening voor clients met interval > 1 dag.</p>
    <p style="color:#f44747; font-weight:bold;">🔗 Zie ook: <a href="alert-check-debug.php" style="color:#9cdcfe;">alert-check-debug.php</a> voor alert-specifieke debug info.</p>
</div>

<p style="color:#666; margin-top:30px; font-size:11px;">Debug pagina gegenereerd om <?php echo date('Y-m-d H:i:s'); ?></p>

</body>
</html>
