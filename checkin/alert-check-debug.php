<?php
// ============================================================
// ALERT-CHECK-DEBUG.PHP - Debug versie van alert-check.php
// Toont elke beslissingsstap uitgebreid
// ============================================================
define('MANTELZORG_DEBUG', true);

require_once 'includes/database.php';

$startTime = date('Y-m-d H:i:s');
$currentHour = date('H:i');
$now = time();
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <title>DEBUG - Alert Check</title>
    <style>
        body { font-family: monospace; max-width: 1400px; margin: 20px auto; padding: 20px; background: #1e1e1e; color: #d4d4d4; }
        h1 { color: #f44747; border-bottom: 2px solid #f44747; padding-bottom: 10px; }
        h2 { color: #dcdcaa; margin-top: 30px; }
        h3 { color: #9cdcfe; }
        .client-block { border: 2px solid #3c3c3c; margin: 25px 0; padding: 20px; border-radius: 6px; background: #252526; }
        .decision-yes { border-left: 8px solid #f44747; }
        .decision-no { border-left: 8px solid #4ec9b0; }
        .decision-skip { border-left: 8px solid #dcdcaa; }
        pre { background: #1e1e1e; padding: 12px; border-radius: 4px; overflow-x: auto; font-size: 13px; line-height: 1.8; margin: 6px 0; }
        .label { color: #9cdcfe; font-weight: bold; display: inline-block; min-width: 300px; }
        .val-green { color: #4ec9b0; font-weight: bold; }
        .val-orange { color: #dcdcaa; font-weight: bold; }
        .val-red { color: #f44747; font-weight: bold; }
        .val-id { color: #c586c0; font-weight: bold; }
        .val-ts { color: #ce9178; }
        .sql-block { background: #0d3349; border: 1px solid #264f78; padding: 10px; border-radius: 4px; margin: 8px 0; }
        .sql-keyword { color: #569cd6; }
        .section-title { background: #37373d; padding: 6px 12px; border-radius: 4px; color: #dcdcaa; margin: 15px 0 8px 0; font-size: 13px; text-transform: uppercase; letter-spacing: 1px; }
        .decision-box { padding: 15px; border-radius: 6px; margin: 10px 0; font-size: 15px; font-weight: bold; }
        .decision-send { background: #3d0000; border: 2px solid #f44747; color: #f44747; }
        .decision-skip-box { background: #003d00; border: 2px solid #4ec9b0; color: #4ec9b0; }
        .decision-wait { background: #3d3d00; border: 2px solid #dcdcaa; color: #dcdcaa; }
        .step { margin: 12px 0; padding: 10px; background: #2d2d2d; border-radius: 4px; }
        .step-pass { border-left: 4px solid #4ec9b0; }
        .step-fail { border-left: 4px solid #f44747; }
        .step-info { border-left: 4px solid #9cdcfe; }
        table.db-table { width: 100%; border-collapse: collapse; margin: 8px 0; }
        table.db-table th { background: #37373d; color: #dcdcaa; padding: 8px 12px; text-align: left; font-size: 12px; }
        table.db-table td { padding: 7px 12px; border-bottom: 1px solid #3c3c3c; font-size: 12px; }
        .debug-header { background: #3d0000; border: 1px solid #f44747; padding: 15px; border-radius: 6px; margin-bottom: 25px; }
        .summary-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .summary-table th { background: #37373d; color: #dcdcaa; padding: 10px; text-align: left; }
        .summary-table td { padding: 10px; border-bottom: 1px solid #3c3c3c; }
    </style>
</head>
<body>

<div class="debug-header">
    <h1>🚨 DEBUG - alert-check.php</h1>
    <pre>
<span class="label">Start tijd:</span>    <span class="val-ts"><?php echo $startTime; ?></span>
<span class="label">Huidige uur:</span>   <span class="val-orange"><?php echo $currentHour; ?></span>
<span class="label">Unix timestamp:</span><span class="val-ts"><?php echo $now; ?></span>
    </pre>
    <p style="color:#f44747; font-weight:bold;">⚠️ ALLEEN VOOR DEBUGGING - NIET IN PRODUCTIE GEBRUIKEN</p>
</div>

<?php

// ============================================================
// STAP 1: Haal alle clients op (zelfde query als getOverdueClients)
// ============================================================
echo '<h2>STAP 1: Alle clients ophalen uit database</h2>';

$allClientsQuery = "
    SELECT c.id, c.naam, c.alert_time, c.check_every_days, c.paused, c.paused_until
    FROM clients c
";

echo '<div class="sql-block"><pre>';
echo '<span class="sql-keyword">SELECT</span> c.id, c.naam, c.alert_time, c.check_every_days, c.paused, c.paused_until' . "\n";
echo '<span class="sql-keyword">FROM</span> clients c';
echo '</pre></div>';

$conn = getDbConnection();
$result = $conn->query($allClientsQuery);
$allClients = $result->fetch_all(MYSQLI_ASSOC);
$conn->close();

echo '<p><span class="val-green">✓</span> ' . count($allClients) . ' client(s) gevonden in database</p>';
echo '<table class="db-table">';
echo '<tr><th>id</th><th>naam</th><th>check_every_days</th><th>alert_time</th><th>paused</th><th>paused_until</th></tr>';
foreach ($allClients as $c) {
    echo '<tr>';
    echo '<td><span class="val-id">' . $c['id'] . '</span></td>';
    echo '<td><strong>' . htmlspecialchars($c['naam']) . '</strong></td>';
    echo '<td><span class="val-orange">' . $c['check_every_days'] . '</span></td>';
    echo '<td class="val-ts">' . htmlspecialchars($c['alert_time']) . '</td>';
    echo '<td>' . ($c['paused'] ? '<span class="val-orange">JA</span>' : '<span class="val-green">nee</span>') . '</td>';
    echo '<td class="val-ts">' . htmlspecialchars($c['paused_until'] ?? '-') . '</td>';
    echo '</tr>';
}
echo '</table>';

// ============================================================
// STAP 2: Per client - volledige beslissingsboom
// ============================================================
echo '<h2>STAP 2: Per-client analyse (volledige beslissingsboom)</h2>';

$summary = [];

foreach ($allClients as $client) {
    $clientId = $client['id'];
    $alertTime = substr($client['alert_time'], 0, 5);
    $alertTimeToday = strtotime(date('Y-m-d') . ' ' . $alertTime);
    $checkEveryDays = $client['check_every_days'];

    $finalDecision = 'ONBEKEND';
    $decisionClass = '';

    echo '<div class="client-block">';
    echo '<h3>Client: <span class="val-green">' . htmlspecialchars($client['naam']) . '</span> (ID: <span class="val-id">' . $clientId . '</span>)</h3>';

    // --- Check 1: Gepauzeerd? ---
    echo '<div class="section-title">Check 1: Is client gepauzeerd?</div>';

    // Haal verse data op
    $conn = getDbConnection();
    $stmtPaused = $conn->prepare("SELECT paused, paused_until FROM clients WHERE id = ?");
    $stmtPaused->bind_param("i", $clientId);
    $stmtPaused->execute();
    $pausedRow = $stmtPaused->get_result()->fetch_assoc();
    $stmtPaused->close();

    echo '<div class="sql-block"><pre><span class="sql-keyword">SELECT</span> paused, paused_until <span class="sql-keyword">FROM</span> clients <span class="sql-keyword">WHERE</span> id = <span class="val-id">' . $clientId . '</span></pre></div>';
    echo '<pre>';
    echo '<span class="label">paused:</span>       ' . ($pausedRow['paused'] ? '<span class="val-orange">1 (JA)</span>' : '<span class="val-green">0 (nee)</span>') . "\n";
    echo '<span class="label">paused_until:</span> <span class="val-ts">' . htmlspecialchars($pausedRow['paused_until'] ?? 'NULL') . '</span>' . "\n";
    echo '</pre>';

    $isPaused = isClientPaused($clientId);
    if ($isPaused) {
        $finalDecision = 'OVERGESLAGEN (gepauzeerd)';
        $decisionClass = 'decision-no';
        echo '<div class="step step-pass"><span class="val-orange">→ Client is GEPAUZEERD - geen alert</span></div>';
        $summary[] = ['client' => $client['naam'], 'id' => $clientId, 'beslissing' => 'OVERGESLAGEN', 'reden' => 'Gepauzeerd'];
        echo '</div>';
        continue;
    } else {
        echo '<div class="step step-pass"><span class="val-green">✓ Niet gepauzeerd - doorgaan</span></div>';
    }

    // --- Check 2: Moet vandaag inchecken? ---
    echo '<div class="section-title">Check 2: Moet client vandaag inchecken? (shouldCheckInToday)</div>';
    echo '<pre>';
    echo '<span class="label">check_every_days:</span> <span class="val-orange">' . $checkEveryDays . '</span>' . "\n";
    echo '</pre>';

    $stmtLastCI = $conn->prepare("
        SELECT event_datetime FROM check_in_history
        WHERE client_id = ? AND event_type IN ('check_in', 'late_check_in')
        ORDER BY event_datetime DESC LIMIT 1
    ");
    $stmtLastCI->bind_param("i", $clientId);
    $stmtLastCI->execute();
    $lastCIRow = $stmtLastCI->get_result()->fetch_assoc();
    $stmtLastCI->close();

    echo '<div class="sql-block"><pre><span class="sql-keyword">SELECT</span> event_datetime <span class="sql-keyword">FROM</span> check_in_history' . "\n";
    echo '<span class="sql-keyword">WHERE</span> client_id = <span class="val-id">' . $clientId . '</span> <span class="sql-keyword">AND</span> event_type <span class="sql-keyword">IN</span> (\'check_in\', \'late_check_in\')' . "\n";
    echo '<span class="sql-keyword">ORDER BY</span> event_datetime <span class="sql-keyword">DESC LIMIT</span> 1</pre></div>';
    echo '<pre>';
    echo '<span class="label">Resultaat query:</span> <span class="val-ts">' . ($lastCIRow ? $lastCIRow['event_datetime'] : 'NULL (nooit ingecheckt)') . '</span>' . "\n";

    if ($checkEveryDays == 1) {
        $shouldCheck = true;
        echo '<span class="label">Berekening:</span>     check_every_days=1 → <span class="val-red">altijd TRUE</span>' . "\n";
    } elseif (!$lastCIRow) {
        $shouldCheck = true;
        echo '<span class="label">Berekening:</span>     Nooit ingecheckt → <span class="val-red">TRUE (moet nu inchecken)</span>' . "\n";
    } else {
        $lastDate = date('Y-m-d', strtotime($lastCIRow['event_datetime']));
        $today = date('Y-m-d');
        $daysSince = (strtotime($today) - strtotime($lastDate)) / 86400;
        $shouldCheck = $daysSince >= $checkEveryDays;
        echo '<span class="label">Laatste check-in datum:</span> <span class="val-ts">' . $lastDate . '</span>' . "\n";
        echo '<span class="label">Vandaag:</span>                <span class="val-ts">' . $today . '</span>' . "\n";
        echo '<span class="label">Dagen verschil:</span>         <span class="val-orange">' . $daysSince . '</span>' . "\n";
        echo '<span class="label">Berekening:</span>             ' . $daysSince . ' >= ' . $checkEveryDays . ' = ';
        if ($shouldCheck) {
            echo '<span class="val-red">TRUE</span>';
            if ($daysSince > $checkEveryDays) {
                echo "\n" . '<span class="val-red">   ⚠️ PROBLEEM: ' . round($daysSince) . ' dagen geleden! Meer dan interval.</span>';
                echo "\n" . '<span class="val-red">   Client staat elke dag in de "overdue" lijst totdat opnieuw ingecheckt.</span>';
            }
        } else {
            echo '<span class="val-green">FALSE</span>';
        }
        echo "\n";
    }
    echo '</pre>';

    if (!$shouldCheck) {
        $finalDecision = 'OVERGESLAGEN (geen check-in nodig vandaag)';
        $decisionClass = 'decision-no';
        echo '<div class="step step-pass"><span class="val-green">✓ Hoeft vandaag niet in te checken - geen alert</span></div>';
        $summary[] = ['client' => $client['naam'], 'id' => $clientId, 'beslissing' => 'OVERGESLAGEN', 'reden' => 'Geen check-in nodig vandaag'];
        $conn->close();
        echo '</div>';
        continue;
    } else {
        echo '<div class="step step-fail"><span class="val-red">→ Moet vandaag inchecken - doorgaan</span></div>';
    }

    // --- Check 3: Al ingecheckt vandaag? ---
    echo '<div class="section-title">Check 3: Heeft client vandaag al ingecheckt?</div>';

    $stmtToday = $conn->prepare("
        SELECT COUNT(*) as count, MAX(event_datetime) as last_time
        FROM check_in_history
        WHERE client_id = ? AND event_type IN ('check_in', 'late_check_in')
        AND DATE(event_datetime) = CURDATE()
    ");
    $stmtToday->bind_param("i", $clientId);
    $stmtToday->execute();
    $todayRow = $stmtToday->get_result()->fetch_assoc();
    $stmtToday->close();

    echo '<div class="sql-block"><pre><span class="sql-keyword">SELECT</span> COUNT(*) as count, MAX(event_datetime) as last_time <span class="sql-keyword">FROM</span> check_in_history' . "\n";
    echo '<span class="sql-keyword">WHERE</span> client_id = <span class="val-id">' . $clientId . '</span> <span class="sql-keyword">AND</span> event_type <span class="sql-keyword">IN</span> (\'check_in\', \'late_check_in\')' . "\n";
    echo '<span class="sql-keyword">AND</span> DATE(event_datetime) = CURDATE()  -- CURDATE() = ' . date('Y-m-d') . '</pre></div>';
    echo '<pre>';
    echo '<span class="label">Aantal check-ins vandaag:</span> <span class="val-orange">' . $todayRow['count'] . '</span>' . "\n";
    echo '<span class="label">Laatste vandaag:</span>           <span class="val-ts">' . ($todayRow['last_time'] ?? 'geen') . '</span>' . "\n";
    echo '</pre>';

    $checkedInToday = $todayRow['count'] > 0;
    if ($checkedInToday) {
        $finalDecision = 'OVERGESLAGEN (al ingecheckt vandaag)';
        $decisionClass = 'decision-no';
        echo '<div class="step step-pass"><span class="val-green">✓ Al ingecheckt vandaag - geen alert nodig</span></div>';
        $summary[] = ['client' => $client['naam'], 'id' => $clientId, 'beslissing' => 'OVERGESLAGEN', 'reden' => 'Al ingecheckt om ' . $todayRow['last_time']];
        $conn->close();
        echo '</div>';
        continue;
    } else {
        echo '<div class="step step-fail"><span class="val-red">→ Niet ingecheckt vandaag - doorgaan</span></div>';
    }

    // --- Check 4: Voorbij alert tijd? ---
    echo '<div class="section-title">Check 4: Zijn we voorbij de alert tijd?</div>';
    echo '<pre>';
    echo '<span class="label">alert_time (database):</span>  <span class="val-ts">' . htmlspecialchars($client['alert_time']) . '</span>' . "\n";
    echo '<span class="label">alert_time (HH:MM):</span>     <span class="val-orange">' . $alertTime . '</span>' . "\n";
    echo '<span class="label">alertTimeToday (datum+tijd):</span> <span class="val-ts">' . date('Y-m-d H:i:s', $alertTimeToday) . '</span>' . "\n";
    echo '<span class="label">Nu (timestamp):</span>         <span class="val-ts">' . date('Y-m-d H:i:s', $now) . '</span>' . "\n";
    $secondsDiff = $now - $alertTimeToday;
    echo '<span class="label">Verschil:</span>               ';
    if ($secondsDiff > 0) {
        echo '<span class="val-red">' . round($secondsDiff / 60) . ' minuten VOORBIJ alert tijd</span>';
    } else {
        echo '<span class="val-green">' . round(abs($secondsDiff) / 60) . ' minuten te gaan tot alert tijd</span>';
    }
    echo "\n";
    echo '<span class="label">Berekening ($now > $alertTimeToday):</span> ';
    echo ($now > $alertTimeToday ? '<span class="val-red">TRUE (voorbij)</span>' : '<span class="val-green">FALSE (nog niet)</span>') . "\n";
    echo '</pre>';

    if ($now < $alertTimeToday) {
        $finalDecision = 'WACHT (nog niet voorbij alert tijd ' . $alertTime . ')';
        $decisionClass = 'decision-skip';
        echo '<div class="step step-info"><span class="val-orange">→ Nog niet voorbij alert tijd - overslaan</span></div>';
        $summary[] = ['client' => $client['naam'], 'id' => $clientId, 'beslissing' => 'WACHT', 'reden' => 'Alert tijd ' . $alertTime . ' nog niet bereikt'];
        $conn->close();
        echo '</div>';
        continue;
    } else {
        echo '<div class="step step-fail"><span class="val-red">→ Alert tijd VOORBIJ - doorgaan</span></div>';
    }

    // --- Check 5: Alert al verstuurd SINDS laatste check-in? ---
    echo '<div class="section-title">Check 5: Was alert al verstuurd SINDS laatste check-in? (wasAlertSentSince)</div>';

    $sinceDateTime = $lastCIRow ? $lastCIRow['event_datetime'] : '1970-01-01 00:00:00';
    $stmtAlertSince = $conn->prepare("
        SELECT COUNT(*) as count, MIN(event_datetime) as first_alert
        FROM check_in_history
        WHERE client_id = ? AND event_type = 'alert_sent'
        AND event_datetime >= ?
    ");
    $stmtAlertSince->bind_param("is", $clientId, $sinceDateTime);
    $stmtAlertSince->execute();
    $alertSinceRow = $stmtAlertSince->get_result()->fetch_assoc();
    $stmtAlertSince->close();

    echo '<div class="sql-block"><pre><span class="sql-keyword">SELECT</span> COUNT(*) as count, MIN(event_datetime) as first_alert <span class="sql-keyword">FROM</span> check_in_history' . "\n";
    echo '<span class="sql-keyword">WHERE</span> client_id = <span class="val-id">' . $clientId . '</span> <span class="sql-keyword">AND</span> event_type = \'alert_sent\'' . "\n";
    echo '<span class="sql-keyword">AND</span> event_datetime &gt;= \'<span class="val-ts">' . $sinceDateTime . '</span>\'</pre></div>';
    echo '<pre>';
    echo '<span class="label">Referentiepunt (laatste check-in):</span> <span class="val-ts">' . $sinceDateTime . '</span>' . "\n";
    echo '<span class="label">Alerts gevonden sinds dat moment:</span> <span class="val-orange">' . $alertSinceRow['count'] . '</span>' . "\n";
    echo '<span class="label">Eerste alert na check-in:</span>        <span class="val-ts">' . ($alertSinceRow['first_alert'] ?? 'geen') . '</span>' . "\n";
    echo '<span class="label">Resultaat (count > 0):</span>           ' . ($alertSinceRow['count'] > 0 ? '<span class="val-green">TRUE (al verstuurd in dit interval)</span>' : '<span class="val-red">FALSE (nog niet verstuurd)</span>') . "\n";
    echo '</pre>';

    $alertAlreadySent = $alertSinceRow['count'] > 0;
    if ($alertAlreadySent) {
        $finalDecision = 'OVERGESLAGEN (alert al verstuurd in dit interval, om ' . $alertSinceRow['first_alert'] . ')';
        $decisionClass = 'decision-no';
        echo '<div class="step step-pass"><span class="val-green">✓ Alert al verstuurd in dit interval - overslaan</span></div>';
        $summary[] = ['client' => $client['naam'], 'id' => $clientId, 'beslissing' => 'AL VERSTUURD', 'reden' => 'Verstuurd om ' . $alertSinceRow['first_alert']];
        $conn->close();
        echo '</div>';
        continue;
    } else {
        echo '<div class="step step-fail"><span class="val-red">→ Alert nog NIET verstuurd in dit interval - doorgaan</span></div>';
    }

    // --- Check 6: Mantelzorgers ophalen ---
    echo '<div class="section-title">Check 6: Mantelzorgers ophalen (getClientMantelzorgers)</div>';

    $stmtMZ = $conn->prepare("
        SELECT m.* FROM mantelzorgers m
        JOIN client_mantelzorgers cm ON m.id = cm.mantelzorger_id
        WHERE cm.client_id = ?
    ");
    $stmtMZ->bind_param("i", $clientId);
    $stmtMZ->execute();
    $mantelzorgers = $stmtMZ->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmtMZ->close();

    echo '<div class="sql-block"><pre><span class="sql-keyword">SELECT</span> m.* <span class="sql-keyword">FROM</span> mantelzorgers m' . "\n";
    echo '<span class="sql-keyword">JOIN</span> client_mantelzorgers cm <span class="sql-keyword">ON</span> m.id = cm.mantelzorger_id' . "\n";
    echo '<span class="sql-keyword">WHERE</span> cm.client_id = <span class="val-id">' . $clientId . '</span></pre></div>';

    if (empty($mantelzorgers)) {
        echo '<pre><span class="val-red">❌ GEEN MANTELZORGERS GEKOPPELD via koppeltabel!</span></pre>';
        echo '<pre><span class="val-orange">Geen koppeling gevonden in client_mantelzorgers voor client_id=' . $clientId . '</span></pre>';
        $finalDecision = 'FOUT (geen mantelzorgers)';
        $decisionClass = 'decision-yes';
        $summary[] = ['client' => $client['naam'], 'id' => $clientId, 'beslissing' => 'FOUT', 'reden' => 'Geen mantelzorgers gekoppeld in koppeltabel'];
    } else {
        echo '<table class="db-table">';
        echo '<tr><th>m.id</th><th>m.naam</th><th>m.email</th></tr>';
        foreach ($mantelzorgers as $mz) {
            echo '<tr>';
            echo '<td><span class="val-id">' . $mz['id'] . '</span></td>';
            echo '<td><strong>' . htmlspecialchars($mz['naam']) . '</strong></td>';
            echo '<td class="val-ts">' . htmlspecialchars($mz['email']) . '</td>';
            echo '</tr>';
        }
        echo '</table>';

        // --- EINDBESLISSING: Verstuur alert ---
        $finalDecision = 'JA - ALERT VERSTUREN naar ' . count($mantelzorgers) . ' mantelzorger(s)';
        $decisionClass = 'decision-yes';
        echo '<p><span class="val-red">→ Alle checks doorstaan: alert ZOU worden verstuurd in productie</span></p>';
        $summary[] = ['client' => $client['naam'], 'id' => $clientId, 'beslissing' => 'ALERT VERSTUREN', 'reden' => count($mantelzorgers) . ' mantelzorger(s): ' . implode(', ', array_column($mantelzorgers, 'naam'))];
    }

    // --- Toon eindbeslissing ---
    echo '<div class="section-title">Eindbeslissing voor deze client</div>';
    $boxClass = ($decisionClass === 'decision-yes') ? 'decision-send' : (($decisionClass === 'decision-no') ? 'decision-skip-box' : 'decision-wait');
    echo '<div class="decision-box ' . $boxClass . '">';
    echo '► ' . $finalDecision;
    echo '</div>';

    $conn->close();
    echo '</div>'; // .client-block
}

// ============================================================
// STAP 3: Samenvatting
// ============================================================
echo '<h2>STAP 3: Samenvatting alle clients</h2>';
echo '<table class="summary-table">';
echo '<tr><th>Client ID</th><th>Naam</th><th>Beslissing</th><th>Reden</th></tr>';
foreach ($summary as $s) {
    $color = '#d4d4d4';
    if ($s['beslissing'] === 'ALERT VERSTUREN') $color = '#f44747';
    elseif ($s['beslissing'] === 'OVERGESLAGEN' || $s['beslissing'] === 'AL VERSTUURD') $color = '#4ec9b0';
    elseif ($s['beslissing'] === 'FOUT') $color = '#dcdcaa';
    elseif ($s['beslissing'] === 'WACHT') $color = '#9cdcfe';

    echo '<tr>';
    echo '<td><span class="val-id">' . $s['id'] . '</span></td>';
    echo '<td><strong>' . htmlspecialchars($s['client']) . '</strong></td>';
    echo '<td style="color:' . $color . '; font-weight:bold;">' . htmlspecialchars($s['beslissing']) . '</td>';
    echo '<td>' . htmlspecialchars($s['reden']) . '</td>';
    echo '</tr>';
}
echo '</table>';

echo '<p style="color:#666; margin-top:30px; font-size:11px;">Debug pagina gegenereerd om ' . date('Y-m-d H:i:s') . ' | ';
echo '<a href="index-debug.php" style="color:#9cdcfe;">index-debug.php</a></p>';
?>
</body>
</html>
