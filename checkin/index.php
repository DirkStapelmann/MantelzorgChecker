<?php
require_once 'includes/database.php';
require_once 'includes/auth.php';
requireLogin();

$error = '';

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_settings'])) {
    $clientId = $_POST['client_id'];
    $checkEveryDays = $_POST['check_every_days'];
    $alertTime = $_POST['alert_time'];

    // Valideer dat het een geldig heel uur is (05-20)
    $validHours = range(5, 20);
    $hour = (int) explode(':', $alertTime)[0];
    if (in_array($hour, $validHours) && preg_match('/^\d{2}:00$/', $alertTime)) {
        // Zorg dat het formaat HH:MM:SS is voor database
        $alertTimeFormatted = $alertTime . ':00';

        $conn = getDbConnection();
        $stmt = $conn->prepare("UPDATE clients SET check_every_days = ?, alert_time = ? WHERE id = ?");
        $stmt->bind_param("isi", $checkEveryDays, $alertTimeFormatted, $clientId);
        $stmt->execute();
        $stmt->close();
        $conn->close();

        header("Location: index.php?updated=1");
        exit;
    } else {
        $error = "Ongeldige tijd! Gebruik formaat HH:MM (bijv. 08:00 of 13:30)";
    }
}

// Handle pause/unpause
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pause_client'])) {
    $clientId = $_POST['client_id'];
    $pauseUntil = !empty($_POST['pause_until']) ? $_POST['pause_until'] : null;

    if (pauseClient($clientId, $pauseUntil)) {
        header("Location: index.php?paused=1");
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['unpause_client'])) {
    $clientId = $_POST['client_id'];

    if (unpauseClient($clientId)) {
        header("Location: index.php?unpaused=1");
        exit;
    }
}

// Haal alle clients op
$conn = getDbConnection();
$result = $conn->query("SELECT * FROM clients ORDER BY naam");
$clients = $result->fetch_all(MYSQLI_ASSOC);
$conn->close();

// Voeg extra data toe per client
foreach ($clients as &$client) {
    $lastCheckIn = getLastCheckIn($client['id']);
    $lastAlert = getLastAlert($client['id']);
    $missedCount = getMissedCheckInsCount($client['id'], 30);

    $client['last_check_in'] = $lastCheckIn;
    $client['last_alert_sent'] = $lastAlert;
    $client['missed_checkins'] = $missedCount;
    $client['mantelzorgers'] = getClientMantelzorgers($client['id']);

    // Check of gepauzeerd
    $isPaused = isClientPaused($client['id']);
    $client['is_paused'] = $isPaused;

    // Bepaal status
    $alertTime = substr($client['alert_time'], 0, 5);
    $alertTimeToday = strtotime(date('Y-m-d') . ' ' . $alertTime);
    $checkedInToday = hasCheckedInToday($client['id']);
    $shouldCheckToday = shouldCheckInToday($client['id'], $client['check_every_days']);

    if ($isPaused) {
        // Gepauzeerd
        $client['status'] = 'PAUSED';
        $client['status_text'] = 'Gepauzeerd';
    } elseif (!$shouldCheckToday) {
        // Hoeft vandaag niet in te checken
        $client['status'] = 'NOT_REQUIRED';
        $client['status_text'] = 'Niet nodig vandaag';
    } elseif ($checkedInToday) {
        // Heeft vandaag ingecheckt
        $client['status'] = 'OK';
        $client['status_text'] = 'OK';
    } elseif (time() > $alertTimeToday) {
        // Na alert tijd, niet ingecheckt
        $client['status'] = 'ALERT';
        $client['status_text'] = 'Alert nodig';
    } else {
        // Voor alert tijd, nog niet ingecheckt
        $hoursUntilAlert = ($alertTimeToday - time()) / 3600;
        if ($hoursUntilAlert < 2) {
            $client['status'] = 'WARNING';
            $client['status_text'] = 'Bijna tijd';
        } else {
            $client['status'] = 'PENDING';
            $client['status_text'] = 'Nog niet ingecheckt';
        }
    }
}
unset($client); // Break reference
?>
<!DOCTYPE html>
<html lang="nl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mantelzorg Checker - Overzicht</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 1200px;
            margin: 50px auto;
            padding: 20px;
            background-color: #f5f5f5;
        }

        h1 {
            color: #333;
        }

        .success-message {
            background-color: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            animation: fadeOut 3s forwards;
        }

        .error-message {
            background-color: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        @keyframes fadeOut {
            0% {
                opacity: 1;
            }

            70% {
                opacity: 1;
            }

            100% {
                opacity: 0;
                display: none;
            }
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background-color: white;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }

        th,
        td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        th {
            background-color: #4CAF50;
            color: white;
        }

        .status-ok {
            color: green;
            font-weight: bold;
        }

        .status-warning {
            color: orange;
            font-weight: bold;
        }

        .status-pending {
            color: #2196F3;
            font-weight: bold;
        }

        .status-alert {
            color: red;
            font-weight: bold;
        }

        .status-not-required {
            color: #999;
            font-style: italic;
        }

        .settings-section {
            background-color: white;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }

        .settings-section h2 {
            margin-top: 0;
            color: #333;
            border-bottom: 2px solid #4CAF50;
            padding-bottom: 10px;
        }

        .client-settings {
            border: 1px solid #ddd;
            padding: 15px;
            margin-bottom: 15px;
            border-radius: 5px;
            background-color: #fafafa;
        }

        .client-settings h3 {
            margin-top: 0;
            color: #555;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: inline-block;
            width: 200px;
            font-weight: bold;
        }

        .form-group input[type="number"],
        .form-group input[type="text"] {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 3px;
            width: 150px;
            font-size: 14px;
        }

        .btn-save {
            background-color: #4CAF50;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
        }

        .btn-save:hover {
            background-color: #45a049;
        }

        .link-container {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .link-short {
            background-color: #e8f5e9;
            padding: 5px 10px;
            border-radius: 3px;
            font-size: 12px;
            color: #666;
            font-family: monospace;
        }

        .btn-copy {
            background-color: #2196F3;
            color: white;
            border: none;
            padding: 5px 12px;
            border-radius: 3px;
            cursor: pointer;
            font-size: 12px;
            white-space: nowrap;
        }

        .btn-copy:hover {
            background-color: #1976D2;
        }

        .btn-copy:active {
            background-color: #0D47A1;
        }

        .status-paused {
            color: #9C27B0;
            font-weight: bold;
        }

        .pause-section {
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid #ddd;
        }

        .btn-pause {
            background-color: #9C27B0;
            color: white;
            padding: 8px 16px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 13px;
        }

        .btn-pause:hover {
            background-color: #7B1FA2;
        }

        .btn-unpause {
            background-color: #4CAF50;
            color: white;
            padding: 8px 16px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 13px;
        }

        .btn-unpause:hover {
            background-color: #45a049;
        }
    </style>
    <script>
        // Verwijder ?updated=1 uit URL na 3 seconden
        if (window.location.search.includes('updated=1')) {
            setTimeout(function() {
                window.history.replaceState({}, document.title, window.location.pathname);
            }, 3000);
        }

        function copyToClipboard(text, button) {
            // Probeer eerst de moderne clipboard API
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(text).then(function() {
                    const originalText = button.textContent;
                    button.textContent = 'Gekopieerd!';
                    button.style.backgroundColor = '#4CAF50';

                    setTimeout(function() {
                        button.textContent = originalText;
                        button.style.backgroundColor = '#2196F3';
                    }, 2000);
                }).catch(function(err) {
                    alert('Kopiëren mislukt: ' + err);
                });
            } else {
                // Fallback voor HTTP: gebruik oude methode
                const textarea = document.createElement('textarea');
                textarea.value = text;
                textarea.style.position = 'fixed';
                textarea.style.opacity = '0';
                document.body.appendChild(textarea);
                textarea.select();

                try {
                    document.execCommand('copy');
                    const originalText = button.textContent;
                    button.textContent = 'Gekopieerd!';
                    button.style.backgroundColor = '#4CAF50';

                    setTimeout(function() {
                        button.textContent = originalText;
                        button.style.backgroundColor = '#2196F3';
                    }, 2000);
                } catch (err) {
                    alert('Kopiëren mislukt: ' + err);
                }

                document.body.removeChild(textarea);
            }
        }
    </script>
</head>

<body>
    <h1>Mantelzorg Checker - Overzicht</h1>

    <div style="background-color: #f8f9fa; padding: 15px 20px; margin-bottom: 30px; border-radius: 8px; border: 1px solid #dee2e6; display: flex; justify-content: space-between; align-items: center;">
        <div>
            <strong style="color: #333;">Ingelogd als:</strong>
            <?php echo htmlspecialchars($_SESSION['mantelzorger_naam']); ?>
            <span style="color: #666; font-size: 14px;">(<?php echo htmlspecialchars($_SESSION['mantelzorger_email']); ?>)</span>
        </div>
        <div>
            <a href="change-password.php" style="margin-left: 15px; color: #2196F3; text-decoration: none; font-size: 14px;">🔑 Wachtwoord wijzigen</a>
            <a href="logout.php" style="margin-left: 15px; color: #f44336; text-decoration: none; font-size: 14px;">Uitloggen</a>
        </div>
    </div>

    <!-- Status overzicht -->
    <h2>Status Overzicht</h2>
    <a href="manage-clients.php" style="display: inline-block; margin-bottom: 20px; padding: 10px 20px; background-color: #2196F3; color: white; text-decoration: none; border-radius: 5px;">Clients Beheren</a>
    <a href="manage-mantelzorgers.php" style="display: inline-block; margin-bottom: 20px; margin-left: 10px; padding: 10px 20px; background-color: #9C27B0; color: white; text-decoration: none; border-radius: 5px;">Mantelzorgers Beheren</a>
    <a href="view-logs.php" style="display: inline-block; margin-bottom: 20px; margin-left: 10px; padding: 10px 20px; background-color: #FF9800; color: white; text-decoration: none; border-radius: 5px;">📋 Systeem Logs</a>

    <table>
        <thead>
            <tr>
                <th>Client</th>
                <th>Laatste Check-in</th>
                <th>Status</th>
                <th>Laatste Alert</th>
                <th>Mantelzorger</th>
                <th>Check-in Link</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($clients as $client): ?>
                <tr>
                    <td><?php echo htmlspecialchars($client['naam']); ?></td>
                    <td>
                        <?php
                        if ($client['last_check_in']) {
                            echo date('d-m-Y H:i', strtotime($client['last_check_in']));
                        } else {
                            echo "Nog nooit ingecheckt";
                        }
                        ?>
                    </td>
                    <td>
                        <?php
                        if ($client['status'] == 'PAUSED') {
                            echo '<span class="status-paused">' . $client['status_text'];
                            if ($client['paused_until']) {
                                echo '<br><small>Tot ' . date('d-m-Y', strtotime($client['paused_until'])) . '</small>';
                            }
                            echo '</span>';
                        } elseif ($client['status'] == 'ALERT') {
                            echo '<span class="status-alert">' . $client['status_text'] . '</span>';
                            if ($client['missed_checkins'] > 0) {
                                echo '<br><small style="color: #999;">(' . $client['missed_checkins'] . 'x gemist)</small>';
                            }
                        } elseif ($client['status'] == 'WARNING') {
                            echo '<span class="status-warning">' . $client['status_text'] . '</span>';
                        } elseif ($client['status'] == 'PENDING') {
                            echo '<span class="status-pending">' . $client['status_text'] . '</span>';
                        } elseif ($client['status'] == 'NOT_REQUIRED') {
                            echo '<span class="status-not-required">' . $client['status_text'] . '</span>';
                        } else {
                            echo '<span class="status-ok">' . $client['status_text'] . '</span>';
                        }
                        ?>
                    </td>
                    <td>
                        <?php
                        if ($client['last_alert_sent']) {
                            echo '<span style="color: #ff5722; font-weight: bold;">';
                            echo date('d-m-Y H:i', strtotime($client['last_alert_sent']));
                            echo '</span>';
                        } else {
                            echo '<span style="color: #999;">-</span>';
                        }
                        ?>
                    </td>
                    <td>
                        <?php foreach ($client['mantelzorgers'] as $mz): ?>
                            <?php echo htmlspecialchars($mz['naam']); ?><br>
                            <small><?php echo htmlspecialchars($mz['email']); ?></small><br>
                        <?php endforeach; ?>
                        <?php if (empty($client['mantelzorgers'])): ?>
                            <span style="color:#999;">Geen mantelzorgers</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php
                        // Detecteer automatisch het juiste pad
                        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
                        $host = $_SERVER['HTTP_HOST'];

                        // Probeer eerst ZONDER /checkin (voor custom domein)
                        $baseUrl = $protocol . "://" . $host;
                        $testFile = $_SERVER['DOCUMENT_ROOT'] . '/check-in.php';

                        // Als check-in.php NIET in root staat, voeg /checkin toe
                        if (!file_exists($testFile)) {
                            $baseUrl = $protocol . "://" . $host . "/checkin";
                        }

                        $fullUrl = $baseUrl . "/check-in.php?token=" . $client['unique_token'];
                        $displayUrl = $baseUrl . "/check-in.php";
                        ?>
                        <div class="link-container">
                            <span class="link-short" title="<?php echo htmlspecialchars($fullUrl); ?>">
                                <?php echo $displayUrl; ?>
                            </span>
                            <button class="btn-copy" data-url="<?php echo htmlspecialchars($fullUrl); ?>" onclick="copyToClipboard(this.getAttribute('data-url'), this)">
                                Kopiëren
                            </button>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <?php if (isset($_GET['updated'])): ?>
        <div class="success-message">
            Instellingen succesvol bijgewerkt!
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="error-message">
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['paused'])): ?>
        <div class="success-message">
            Client succesvol gepauzeerd!
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['unpaused'])): ?>
        <div class="success-message">
            Client succesvol hervat!
        </div>
    <?php endif; ?>

    <!-- Instellingen sectie -->
    <div class="settings-section">
        <h2>Instellingen</h2>

        <?php foreach ($clients as $client): ?>
            <div class="client-settings">
                <h3><?php echo htmlspecialchars($client['naam']); ?></h3>

                <form method="POST">
                    <input type="hidden" name="client_id" value="<?php echo $client['id']; ?>">

                    <div class="form-group">
                        <label>Check elke (dagen):</label>
                        <input type="number" name="check_every_days"
                            value="<?php echo $client['check_every_days']; ?>"
                            min="1" max="30" required>
                        <small style="color: #666;">(1 = dagelijks, 2 = om de dag, 7 = wekelijks)</small>
                    </div>

                    <div class="form-group">
                        <label>Alert versturen om:</label>
                        <select name="alert_time" required style="padding: 8px; border: 1px solid #ddd; border-radius: 3px; width: 150px; font-size: 14px;">
                            <?php
                            $currentHour = (int) substr($client['alert_time'], 0, 2);
                            for ($h = 5; $h <= 20; $h++):
                                $val = sprintf('%02d:00', $h);
                            ?>
                                <option value="<?php echo $val; ?>" <?php echo $currentHour === $h ? 'selected' : ''; ?>>
                                    <?php echo $val; ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>

                    <button type="submit" name="update_settings" class="btn-save">
                        Opslaan
                    </button>
                </form>

                <!-- Pauze sectie -->
                <div class="pause-section">
                    <?php if ($client['is_paused']): ?>
                        <p style="color: #9C27B0; font-weight: bold;">
                            🔔 Alerts zijn gepauzeerd
                            <?php if ($client['paused_until']): ?>
                                tot <?php echo date('d-m-Y', strtotime($client['paused_until'])); ?>
                            <?php endif; ?>
                        </p>
                        <form method="POST" style="display: inline-block;">
                            <input type="hidden" name="client_id" value="<?php echo $client['id']; ?>">
                            <button type="submit" name="unpause_client" class="btn-unpause">
                                Hervatten
                            </button>
                        </form>
                    <?php else: ?>
                        <form method="POST">
                            <input type="hidden" name="client_id" value="<?php echo $client['id']; ?>">
                            <div class="form-group">
                                <label>Pauzeren tot (optioneel):</label>
                                <input type="date" name="pause_until"
                                    min="<?php echo date('Y-m-d'); ?>"
                                    style="width: 150px;">
                                <small style="color: #666;">(Leeg laten = onbepaald)</small>
                            </div>
                            <button type="submit" name="pause_client" class="btn-pause">
                                Pauzeren
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</body>

</html>