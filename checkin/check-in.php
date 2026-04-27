<?php
require_once 'includes/database.php';
require_once 'includes/email.php';
require_once 'includes/auth.php';

$message = '';
$success = false;
$alreadyCheckedInToday = false;

// Check of token aanwezig is
if (isset($_GET['token'])) {
    $token = $_GET['token'];
    $client = getClientByToken($token);

    if ($client) {
        // Check of vandaag al ingecheckt
        $alreadyCheckedInToday = hasCheckedInToday($client['id']);

        if ($alreadyCheckedInToday) {
            // Bereken volgende check-in dag
            $checkEveryDays = $client['check_every_days'];
            $alertTime = substr($client['alert_time'], 0, 5);

            if ($checkEveryDays == 1) {
                $nextDay = "morgen";
            } elseif ($checkEveryDays == 2) {
                $nextDay = "overmorgen";
            } else {
                $nextDay = "over {$checkEveryDays} dagen";
            }

            $nextCheckInFormatted = $alertTime;
        }

        // Haal laatste check-in op voor info
        $lastCheckIn = getLastCheckIn($client['id']);

        // Als er op de knop is gedrukt
        if (isset($_POST['check_in'])) {
            // Blokkeer als al vandaag ingecheckt
            if ($alreadyCheckedInToday) {
                $success = true; // Toon "success" scherm maar doe NIETS
            } else {
                // Check of we NA de alert tijd zijn (= te laat)
                $wasLate = isAfterAlertTime($client['alert_time']);

                if ($wasLate) {
                    // Verstuur late check-in ntfy naar alle gekoppelde mantelzorgers op hun eigen topic
                    $mantelzorgers = getClientMantelzorgers($client['id']);
                    foreach ($mantelzorgers as $mz) {
                        if (!empty($mz['ntfy_topic'])) {
                            sendLateCheckInNtfy(
                                $client['naam'],
                                substr($client['alert_time'], 0, 5),
                                $mz['ntfy_topic']
                            );
                        }
                    }
                }

                // Log check-in in history
                if (logCheckIn($client['id'], $wasLate)) {
                    $success = true;
                    if ($wasLate) {
                        $message = "Bedankt voor je check-in, " . htmlspecialchars($client['naam']) . "! Je was te laat, je mantelzorger is op de hoogte gesteld.";
                    } else {
                        $message = "Bedankt voor je check-in, " . htmlspecialchars($client['naam']) . "!";
                    }
                } else {
                    $message = "Er ging iets mis. Probeer het opnieuw.";
                }
            }
        }
    } else {
        $message = "Ongeldige link. Neem contact op met je mantelzorger.";
    }
} else {
    $message = "Geen geldige link gevonden.";
}
?>

<!DOCTYPE html>
<html lang="nl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Check-in - Mantelzorg Checker</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .container {
            background-color: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
            text-align: center;
            max-width: 400px;
        }

        h1 {
            color: #333;
            margin-bottom: 20px;
        }

        .thumbs-up {
            font-size: 80px;
            margin: 20px 0;
        }

        button {
            background-color: #4CAF50;
            color: white;
            padding: 15px 40px;
            font-size: 18px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        button:hover {
            background-color: #45a049;
        }

        .message {
            margin-top: 20px;
            padding: 15px;
            border-radius: 5px;
            font-weight: bold;
        }

        .success {
            background-color: #d4edda;
            color: #155724;
        }

        .error {
            background-color: #f8d7da;
            color: #721c24;
        }

        .info {
            color: #666;
            font-size: 14px;
            margin-top: 20px;
        }

        .info-recent {
            background-color: #e3f2fd;
            color: #1565c0;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 14px;
        }
    </style>
</head>

<body>
    <div class="container">
        <?php if ($client && !$success): ?>
            <h1>Hallo <?php echo htmlspecialchars($client['naam']); ?>!</h1>

            <?php if ($alreadyCheckedInToday): ?>
                <div class="info-recent">
                    Je hebt vandaag al ingecheckt.<br>
                    Je hoeft pas <?php echo $nextDay; ?> om <?php echo $nextCheckInFormatted; ?> opnieuw in te checken.
                </div>
                <div class="thumbs-up">✅</div>
                <p class="info">Je kunt deze pagina nu sluiten.</p>
            <?php else: ?>
                <div class="thumbs-up">👍</div>
                <p>Druk op de knop om te laten weten dat alles goed met je is.</p>

                <form method="POST">
                    <button type="submit" name="check_in">Ik ben er nog!</button>
                </form>

                <?php if ($lastCheckIn): ?>
                    <p class="info">
                        Laatste check-in: <?php echo date('d-m-Y H:i', strtotime($lastCheckIn)); ?>
                    </p>
                <?php endif; ?>
            <?php endif; ?>

        <?php elseif ($success): ?>
            <?php if ($alreadyCheckedInToday): ?>
                <!-- Al ingecheckt: toon info -->
                <h1>Je hebt al ingecheckt!</h1>
                <div class="thumbs-up">✅</div>
                <div class="info-recent">
                    Je hebt vandaag al ingecheckt.<br>
                    Je hoeft pas <?php echo $nextDay; ?> om <?php echo $nextCheckInFormatted; ?> opnieuw in te checken.
                </div>
                <p class="info">Je kunt deze pagina nu sluiten.</p>
            <?php else: ?>
                <!-- Succesvolle check-in -->
                <h1>Gelukt!</h1>
                <div class="thumbs-up">🎉</div>
                <div class="message success">
                    <?php echo $message; ?>
                </div>
                <p class="info">Je kunt deze pagina nu sluiten.</p>
            <?php endif; ?>

        <?php else: ?>
            <h1>Oeps...</h1>
            <div class="message error">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
    </div>
</body>

</html>