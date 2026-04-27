<?php
session_start();
require_once 'includes/database.php';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);

    if (!empty($email)) {
        $conn = getDbConnection();
        $stmt = $conn->prepare("SELECT * FROM mantelzorgers WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $mantelzorger = $result->fetch_assoc();

            // Genereer reset token (64 random karakters)
            $resetToken = bin2hex(random_bytes(32));

            // Token geldig voor 1 uur
            $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));

            // Sla token op in database
            $updateStmt = $conn->prepare("UPDATE mantelzorgers SET reset_token = ?, reset_token_expires = ? WHERE id = ?");
            $updateStmt->bind_param("ssi", $resetToken, $expiresAt, $mantelzorger['id']);
            $updateStmt->execute();
            $updateStmt->close();

            // Stuur email met reset link
            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
            $host = $_SERVER['HTTP_HOST'];
            $scriptDir = dirname($_SERVER['SCRIPT_NAME']);
            if ($scriptDir === '/' || $scriptDir === '') {
                $baseUrl = $protocol . "://" . $host;
            } else {
                $baseUrl = $protocol . "://" . $host . $scriptDir;
            }

            $resetLink = $baseUrl . "/reset-password.php?token=" . $resetToken;

            $subject = "Wachtwoord Reset - Mantelzorg Checker";
            $emailMessage = "
            <html>
            <head>
                <title>Wachtwoord Reset</title>
            </head>
            <body>
                <h2>Wachtwoord Reset Aanvraag</h2>
                <p>Beste {$mantelzorger['naam']},</p>
                <p>Je hebt een wachtwoord reset aangevraagd voor het Mantelzorg Check-in Systeem.</p>
                <p>Klik op onderstaande link om een nieuw wachtwoord in te stellen:</p>
                <p><a href='{$resetLink}' style='background-color: #4CAF50; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block;'>Wachtwoord Instellen</a></p>
                <p>Of kopieer deze link: <br><a href='{$resetLink}'>{$resetLink}</a></p>
                <p><strong>Deze link is 1 uur geldig.</strong></p>
                <p>Als je deze aanvraag niet hebt gedaan, negeer dan deze email.</p>
                <br>
                <p style='color: #666; font-size: 12px;'>Dit is een automatisch gegenereerd bericht.</p>
            </body>
            </html>
            ";

            $headers = "MIME-Version: 1.0" . "\r\n";
            $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
            $headers .= "From: noreply@stapelmann.nl" . "\r\n";

            if (mail($email, $subject, $emailMessage, $headers)) {
                $message = "✅ Er is een email verstuurd naar <strong>" . htmlspecialchars($email) . "</strong> met instructies om je wachtwoord te resetten. Check ook je spam folder!";
            } else {
                $error = "Er is iets misgegaan bij het versturen van de email. Probeer het later opnieuw.";
            }
        } else {
            // Toon dezelfde melding om te voorkomen dat mensen kunnen raden welke emails bestaan
            $message = "✅ Als dit email adres in ons systeem bestaat, is er een reset link verstuurd. Check ook je spam folder!";
        }

        $stmt->close();
        $conn->close();
    } else {
        $error = "Vul je email adres in!";
    }
}
?>
<!DOCTYPE html>
<html lang="nl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wachtwoord Vergeten</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f5f5f5;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
        }

        .container {
            background-color: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 500px;
        }

        h1 {
            color: #333;
            margin-top: 0;
            text-align: center;
        }

        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: #4CAF50;
            text-decoration: none;
            font-size: 14px;
        }

        .back-link:hover {
            text-decoration: underline;
        }

        .success-message {
            background-color: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        .error-message {
            background-color: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-weight: bold;
            margin-bottom: 8px;
            color: #333;
        }

        .form-group input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            box-sizing: border-box;
        }

        .btn-submit {
            width: 100%;
            padding: 12px;
            background-color: #2196F3;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
        }

        .btn-submit:hover {
            background-color: #1976D2;
        }

        .info-box {
            background-color: #e3f2fd;
            border-left: 4px solid #2196F3;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 3px;
        }
    </style>
</head>

<body>
    <div class="container">
        <a href="login.php" class="back-link">← Terug naar login</a>

        <h1>🔑 Wachtwoord Vergeten</h1>

        <?php if ($message): ?>
            <div class="success-message"><?php echo $message; ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if (!$message): ?>
            <div class="info-box">
                Vul je email adres in. Je ontvangt een link om een nieuw wachtwoord in te stellen.
            </div>

            <form method="POST">
                <div class="form-group">
                    <label>Email adres:</label>
                    <input type="email" name="email" required autofocus
                        placeholder="jouw@email.nl">
                </div>

                <button type="submit" class="btn-submit">Verstuur Reset Link</button>
            </form>
        <?php endif; ?>
    </div>
</body>

</html>