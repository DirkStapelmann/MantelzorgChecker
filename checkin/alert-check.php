<?php
require_once 'includes/database.php';
require_once 'includes/email.php';

$startTime = date('Y-m-d H:i:s');
$currentHour = date('H:i');
echo "Alert check gestart om: {$startTime}\n\n";

$overdueClients = getOverdueClients();

if (empty($overdueClients)) {
    $msg = "Geen clients die vandaag alert nodig hebben";
    echo "{$msg}\n";
    logSystem('info', 'alert-check', "Check om {$currentHour}: Geen clients");
    exit;
}

$clientCount = count($overdueClients);
echo "Gevonden clients die alert nodig hebben: {$clientCount}\n\n";

$summary = [];

foreach ($overdueClients as $client) {
    $alertTime = substr($client['alert_time'], 0, 5);
    $alertTimeToday = strtotime(date('Y-m-d') . ' ' . $alertTime);
    $now = time();

    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "Client: {$client['naam']}\n";
    echo "Alert tijd ingesteld op: {$alertTime}\n";
    echo "Huidige tijd: " . date('H:i') . "\n";
    echo "Check elke: {$client['check_every_days']} dag(en)\n";

    // Haal laatste check-in op
    $lastCheckIn = getLastCheckIn($client['id']);
    echo "Laatste check-in: " . ($lastCheckIn ?: 'nooit') . "\n";

    // Check of we AL VOORBIJ de alert tijd zijn vandaag
    if ($now < $alertTimeToday) {
        echo "Nog niet voorbij alert tijd (wacht tot {$alertTime})\n\n";
        $summary[] = "{$client['naam']}: Wacht op {$alertTime}";
        continue;
    }

    echo "Voorbij alert tijd! Checken of al verstuurd sinds laatste check-in...\n";

    // Check of alert al verstuurd is SINDS de laatste check-in (niet enkel vandaag).
    // Dit voorkomt dat bij een gemiste interval elke dag opnieuw een alert verstuurd wordt.
    $sinceDateTime = $lastCheckIn ?? '1970-01-01 00:00:00';
    $alertAlreadySent = wasAlertSentSince($client['id'], $sinceDateTime);
    echo "Alert al verstuurd sinds laatste check-in ({$sinceDateTime}): " . ($alertAlreadySent ? 'JA' : 'NEE') . "\n";

    if ($alertAlreadySent) {
        echo "Alert al verstuurd vandaag\n\n";
        $summary[] = "{$client['naam']}: Al verstuurd";
        continue;
    }

    // Haal gemiste check-ins op
    $missedCount = getMissedCheckInsCount($client['id'], 30);
    echo "Gemiste check-ins (30 dagen): {$missedCount}\n";

    // Haal ALLE mantelzorgers op voor deze client
    $mantelzorgers = getClientMantelzorgers($client['id']);

    if (empty($mantelzorgers)) {
        echo "❌ GEEN MANTELZORGERS GEKOPPELD!\n\n";
        $summary[] = "{$client['naam']}: Geen mantelzorgers";
        logSystem('error', 'alert-check', "Geen mantelzorgers gekoppeld aan {$client['naam']}", $client['id']);
        continue;
    }

    // Verstuur alert per mantelzorger op diens eigen ntfy topic
    $mantelzorgerCount = count($mantelzorgers);
    echo "🚨 Tijd om alert te versturen naar {$mantelzorgerCount} mantelzorger(s)!\n";
    $mantelzorgerNames = implode(', ', array_column($mantelzorgers, 'naam'));
    echo "Ontvangers: {$mantelzorgerNames}\n";

    $allSent = true;
    $failedNames = [];

    foreach ($mantelzorgers as $mz) {
        $topic = $mz['ntfy_topic'];
        if (empty($topic)) {
            echo "⚠️ Geen ntfy topic voor {$mz['naam']}, overgeslagen\n";
            $failedNames[] = $mz['naam'] . ' (geen topic)';
            $allSent = false;
            continue;
        }

        $sent = sendNtfyAlert($client['naam'], $topic);
        if ($sent) {
            echo "✅ ntfy alert verstuurd naar {$mz['naam']} (topic: {$topic})\n";
        } else {
            echo "❌ ntfy alert naar {$mz['naam']} mislukt\n";
            $failedNames[] = $mz['naam'];
            $allSent = false;
        }
    }

    if ($allSent) {
        logAlert($client['id']);
        echo "Alert gelogd in history\n";
        $summary[] = "{$client['naam']}: ✅ Verstuurd";
        logSystem('success', 'alert-check', "Alert verstuurd naar {$mantelzorgerNames}", $client['id']);
    } else {
        $failedStr = implode(', ', $failedNames);
        echo "❌ FOUT: ntfy alert versturen mislukt voor: {$failedStr}\n";
        $summary[] = "{$client['naam']}: ❌ DEELS MISLUKT ({$failedStr})";
        logSystem('error', 'alert-check', "ntfy alert mislukt voor: {$failedStr}", $client['id']);
    }

    echo "\n";
}

// Eén compacte samenvatting per check
$summaryText = implode("; ", $summary);
logSystem('info', 'alert-check', "Check {$currentHour}: {$summaryText}");

echo "Klaar om " . date('Y-m-d H:i:s') . "!\n";
