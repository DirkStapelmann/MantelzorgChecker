<?php
require_once __DIR__ . '/../config.php';

// ============================================================
// DEBUG HULPFUNCTIE
// Definieer MANTELZORG_DEBUG als true vóór de include om
// debug output in te schakelen (alleen in *-debug.php bestanden)
// ============================================================
function _dbDebug(string $message): void
{
    if (defined('MANTELZORG_DEBUG') && MANTELZORG_DEBUG === true) {
        echo '<pre style="background:#0d3349;border:1px solid #264f78;padding:8px;border-radius:4px;margin:4px 0;font-size:12px;color:#9cdcfe;">'
            . '[DB DEBUG] ' . htmlspecialchars($message)
            . '</pre>';
    }
}

// Haal client op via token
function getClientByToken($token)
{
    $conn = getDbConnection();
    $stmt = $conn->prepare("SELECT * FROM clients WHERE unique_token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    $client = $result->fetch_assoc();
    $stmt->close();
    $conn->close();
    return $client;
}

// Log check-in event
function logCheckIn($clientId, $wasLate = false)
{
    $conn = getDbConnection();
    $eventType = $wasLate ? 'late_check_in' : 'check_in';
    $notes = $wasLate ? "Late check-in (na alert tijd)" : "Check-in op tijd";

    $stmt = $conn->prepare("
        INSERT INTO check_in_history (client_id, event_type, event_datetime, notes) 
        VALUES (?, ?, NOW(), ?)
    ");
    $stmt->bind_param("iss", $clientId, $eventType, $notes);
    $success = $stmt->execute();
    $stmt->close();
    $conn->close();
    return $success;
}

// Log alert event
function logAlert($clientId)
{
    $conn = getDbConnection();
    $stmt = $conn->prepare("
        INSERT INTO check_in_history (client_id, event_type, event_datetime, notes) 
        VALUES (?, 'alert_sent', NOW(), 'Alert email verstuurd')
    ");
    $stmt->bind_param("i", $clientId);
    $success = $stmt->execute();
    $stmt->close();
    $conn->close();
    return $success;
}

// Haal laatste check-in op
function getLastCheckIn($clientId)
{
    _dbDebug("getLastCheckIn(client_id=$clientId) → SELECT event_datetime FROM check_in_history WHERE client_id=$clientId AND event_type IN ('check_in','late_check_in') ORDER BY event_datetime DESC LIMIT 1");
    $conn = getDbConnection();
    $stmt = $conn->prepare("
        SELECT event_datetime
        FROM check_in_history
        WHERE client_id = ? AND event_type IN ('check_in', 'late_check_in')
        ORDER BY event_datetime DESC
        LIMIT 1
    ");
    $stmt->bind_param("i", $clientId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    $conn->close();
    $value = $row ? $row['event_datetime'] : null;
    _dbDebug("getLastCheckIn(client_id=$clientId) → resultaat: " . ($value ?? 'NULL'));
    return $value;
}

// Haal laatste alert op
function getLastAlert($clientId)
{
    _dbDebug("getLastAlert(client_id=$clientId) → SELECT event_datetime FROM check_in_history WHERE client_id=$clientId AND event_type='alert_sent' ORDER BY event_datetime DESC LIMIT 1");
    $conn = getDbConnection();
    $stmt = $conn->prepare("
        SELECT event_datetime
        FROM check_in_history
        WHERE client_id = ? AND event_type = 'alert_sent'
        ORDER BY event_datetime DESC
        LIMIT 1
    ");
    $stmt->bind_param("i", $clientId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    $conn->close();
    $value = $row ? $row['event_datetime'] : null;
    _dbDebug("getLastAlert(client_id=$clientId) → resultaat: " . ($value ?? 'NULL'));
    return $value;
}

// Tel gemiste check-ins (alerts in laatste X dagen)
function getMissedCheckInsCount($clientId, $days = 30)
{
    _dbDebug("getMissedCheckInsCount(client_id=$clientId, days=$days) → COUNT alerts in laatste $days dagen");
    $conn = getDbConnection();
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count
        FROM check_in_history 
        WHERE client_id = ? 
        AND event_type = 'alert_sent'
        AND event_datetime >= DATE_SUB(NOW(), INTERVAL ? DAY)
    ");
    $stmt->bind_param("ii", $clientId, $days);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    $conn->close();
    _dbDebug("getMissedCheckInsCount(client_id=$clientId) → resultaat: {$row['count']} gemiste check-ins");
    return $row['count'];
}

// Check of vandaag al ingecheckt
function hasCheckedInToday($clientId)
{
    _dbDebug("hasCheckedInToday(client_id=$clientId) → SELECT COUNT WHERE DATE(event_datetime)=CURDATE() [" . date('Y-m-d') . "]");
    $conn = getDbConnection();
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count
        FROM check_in_history 
        WHERE client_id = ? 
        AND event_type IN ('check_in', 'late_check_in')
        AND DATE(event_datetime) = CURDATE()
    ");
    $stmt->bind_param("i", $clientId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    $conn->close();
    _dbDebug("hasCheckedInToday(client_id=$clientId) → resultaat: " . ($row['count'] > 0 ? 'JA' : 'NEE') . " (count={$row['count']})");
    return $row['count'] > 0;
}

// Check of client vandaag moet inchecken (rekening houdend met check_every_days)
function shouldCheckInToday($clientId, $checkEveryDays)
{
    _dbDebug("shouldCheckInToday(client_id=$clientId, check_every_days=$checkEveryDays) → start");
    // Check eerst of client gepauzeerd is
    if (isClientPaused($clientId)) {
        _dbDebug("shouldCheckInToday(client_id=$clientId) → FALSE (gepauzeerd)");
        return false; // Gepauzeerd, hoeft niet in te checken
    }

    if ($checkEveryDays == 1) {
        _dbDebug("shouldCheckInToday(client_id=$clientId) → TRUE (dagelijks)");
        return true; // Dagelijks
    }

    $conn = getDbConnection();
    $stmt = $conn->prepare("
        SELECT event_datetime 
        FROM check_in_history 
        WHERE client_id = ? 
        AND event_type IN ('check_in', 'late_check_in')
        ORDER BY event_datetime DESC 
        LIMIT 1
    ");
    $stmt->bind_param("i", $clientId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    $conn->close();

    if (!$row) {
        _dbDebug("shouldCheckInToday(client_id=$clientId) → TRUE (nooit ingecheckt)");
        return true; // Nooit ingecheckt, moet nu
    }

    // Bereken hoeveel dagen geleden laatste check-in was
    $lastCheckInDate = date('Y-m-d', strtotime($row['event_datetime']));
    $today = date('Y-m-d');
    $daysSinceLastCheckIn = (strtotime($today) - strtotime($lastCheckInDate)) / 86400;
    $result = $daysSinceLastCheckIn >= $checkEveryDays;
    _dbDebug("shouldCheckInToday(client_id=$clientId) → laatste check-in: $lastCheckInDate | vandaag: $today | dagen: $daysSinceLastCheckIn | interval: $checkEveryDays | resultaat: " . ($result ? 'TRUE' : 'FALSE'));
    return $result;
}

// Haal clients op die vandaag alert nodig hebben
function getOverdueClients()
{
    _dbDebug("getOverdueClients() → SELECT c.id, c.naam, c.alert_time, c.check_every_days FROM clients");
    $conn = getDbConnection();

    $query = "
        SELECT c.id, c.naam, c.alert_time, c.check_every_days
        FROM clients c
    ";

    $result = $conn->query($query);
    $clients = [];

    while ($row = $result->fetch_assoc()) {
        _dbDebug("getOverdueClients() → verwerken: ID={$row['id']} naam={$row['naam']} interval={$row['check_every_days']} alert_time={$row['alert_time']}");
        // Check of client vandaag moet inchecken
        if (shouldCheckInToday($row['id'], $row['check_every_days'])) {
            // Check of al ingecheckt vandaag
            if (!hasCheckedInToday($row['id'])) {
                _dbDebug("getOverdueClients() → ID={$row['id']} ({$row['naam']}) TOEGEVOEGD aan overdue lijst");
                $clients[] = $row;
            } else {
                _dbDebug("getOverdueClients() → ID={$row['id']} ({$row['naam']}) overgeslagen: al ingecheckt vandaag");
            }
        } else {
            _dbDebug("getOverdueClients() → ID={$row['id']} ({$row['naam']}) overgeslagen: hoeft vandaag niet in te checken");
        }
    }

    _dbDebug("getOverdueClients() → totaal " . count($clients) . " client(s) in overdue lijst");
    $conn->close();
    return $clients;
}

// Check of alert al verstuurd is vandaag
function wasAlertSentToday($clientId, $alertTime)
{
    $todayDate = date('Y-m-d');
    _dbDebug("wasAlertSentToday(client_id=$clientId, alert_time=$alertTime) → SELECT COUNT(*) FROM check_in_history WHERE client_id=$clientId AND event_type='alert_sent' AND DATE(event_datetime)='$todayDate'");
    $conn = getDbConnection();

    $stmt = $conn->prepare("
        SELECT COUNT(*) as count
        FROM check_in_history
        WHERE client_id = ?
        AND event_type = 'alert_sent'
        AND DATE(event_datetime) = ?
    ");
    $stmt->bind_param("is", $clientId, $todayDate);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    $conn->close();
    _dbDebug("wasAlertSentToday(client_id=$clientId) → resultaat: " . ($row['count'] > 0 ? 'JA (count=' . $row['count'] . ')' : 'NEE'));
    return $row['count'] > 0;
}

// Check of alert al verstuurd is SINDS een bepaalde datetime (bijv. laatste check-in)
// Vervangt wasAlertSentToday() in alert-check.php voor correcte interval-logica:
// eenmaal alert per interval, niet eenmaal per dag
function wasAlertSentSince($clientId, $sinceDateTime)
{
    _dbDebug("wasAlertSentSince(client_id=$clientId, since=$sinceDateTime) → SELECT COUNT(*) FROM check_in_history WHERE client_id=$clientId AND event_type='alert_sent' AND event_datetime >= '$sinceDateTime'");
    $conn = getDbConnection();
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count
        FROM check_in_history
        WHERE client_id = ?
        AND event_type = 'alert_sent'
        AND event_datetime >= ?
    ");
    $stmt->bind_param("is", $clientId, $sinceDateTime);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    $conn->close();
    _dbDebug("wasAlertSentSince(client_id=$clientId) → resultaat: " . ($row['count'] > 0 ? 'JA (count=' . $row['count'] . ')' : 'NEE'));
    return $row['count'] > 0;
}

// Check of we nu NA de alert tijd zijn
function isAfterAlertTime($alertTime)
{
    $alertTimeToday = strtotime(date('Y-m-d') . ' ' . substr($alertTime, 0, 5));
    return time() > $alertTimeToday;
}

// Check of client gepauzeerd is
function isClientPaused($clientId)
{
    $conn = getDbConnection();
    $stmt = $conn->prepare("
        SELECT paused, paused_until 
        FROM clients 
        WHERE id = ?
    ");
    $stmt->bind_param("i", $clientId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    $conn->close();

    if (!$row || !$row['paused']) {
        return false; // Niet gepauzeerd
    }

    // Check of er een einddatum is
    if ($row['paused_until']) {
        $pausedUntil = strtotime($row['paused_until']);
        $today = strtotime(date('Y-m-d'));

        if ($today > $pausedUntil) {
            // Einddatum voorbij, automatisch hervatten
            unpauseClient($clientId);
            return false;
        }
    }

    return true; // Gepauzeerd
}

// Pauzeer client
function pauseClient($clientId, $untilDate = null)
{
    $conn = getDbConnection();
    $stmt = $conn->prepare("UPDATE clients SET paused = TRUE, paused_until = ? WHERE id = ?");
    $stmt->bind_param("si", $untilDate, $clientId);
    $success = $stmt->execute();
    $stmt->close();
    $conn->close();
    return $success;
}

// Hervat client
function unpauseClient($clientId)
{
    $conn = getDbConnection();
    $stmt = $conn->prepare("UPDATE clients SET paused = FALSE, paused_until = NULL WHERE id = ?");
    $stmt->bind_param("i", $clientId);
    $success = $stmt->execute();
    $stmt->close();
    $conn->close();
    return $success;
}

// Log systeem event
function logSystem($type, $component, $message, $clientId = null)
{
    $conn = getDbConnection();
    $stmt = $conn->prepare("
        INSERT INTO system_logs (log_datetime, log_type, component, message, client_id) 
        VALUES (NOW(), ?, ?, ?, ?)
    ");
    $stmt->bind_param("sssi", $type, $component, $message, $clientId);
    $stmt->execute();
    $stmt->close();
    $conn->close();
}

// Haal alle mantelzorgers van een client op
function getClientMantelzorgers($clientId)
{
    _dbDebug("getClientMantelzorgers(client_id=$clientId) → SELECT m.* FROM mantelzorgers m JOIN client_mantelzorgers cm ON m.id=cm.mantelzorger_id WHERE cm.client_id=$clientId");
    $conn = getDbConnection();
    $stmt = $conn->prepare("
        SELECT m.*
        FROM mantelzorgers m
        JOIN client_mantelzorgers cm ON m.id = cm.mantelzorger_id
        WHERE cm.client_id = ?
    ");
    $stmt->bind_param("i", $clientId);
    $stmt->execute();
    $result = $stmt->get_result();
    $mantelzorgers = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    $conn->close();
    $namen = implode(', ', array_column($mantelzorgers, 'naam'));
    $ids   = implode(', ', array_column($mantelzorgers, 'id'));
    _dbDebug("getClientMantelzorgers(client_id=$clientId) → " . count($mantelzorgers) . " gevonden: IDs=[$ids] namen=[$namen]");
    return $mantelzorgers;
}

// Voeg mantelzorger toe aan client
function addMantelzorgerToClient($clientId, $mantelzorgerId)
{
    $conn = getDbConnection();
    $stmt = $conn->prepare("INSERT IGNORE INTO client_mantelzorgers (client_id, mantelzorger_id) VALUES (?, ?)");
    $stmt->bind_param("ii", $clientId, $mantelzorgerId);
    $success = $stmt->execute();
    $stmt->close();
    $conn->close();
    return $success;
}

// Verwijder mantelzorger van client
function removeMantelzorgerFromClient($clientId, $mantelzorgerId)
{
    $conn = getDbConnection();
    $stmt = $conn->prepare("DELETE FROM client_mantelzorgers WHERE client_id = ? AND mantelzorger_id = ?");
    $stmt->bind_param("ii", $clientId, $mantelzorgerId);
    $success = $stmt->execute();
    $stmt->close();
    $conn->close();
    return $success;
}
