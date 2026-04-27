<?php
session_start();
require_once 'includes/database.php';

$error = '';
$success = false;
$validToken = false;
$mantelzorger = null;

// Check token
if (isset($_GET['token'])) {
    $token = $_GET['token'];

    $conn = getDbConnection();
    $stmt = $conn->prepare("
        SELECT * FROM mantelzorgers 
        WHERE reset_token = ? 
        AND reset_token_expires > NOW()
    ");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $mantelzorger = $result->fetch_assoc();
        $validToken = true;
    } else {
        $error = "Deze reset link is verlopen of ongeldig. Vraag een nieuwe aan.";
    }

    $stmt->close();
    $conn->close();
} else {
    $error = "Geen reset token gevonden.";
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $validToken) {
    $newPassword = $_POST['new_password'];
    $confirmPassword = $_POST['confirm_password'];

    if (empty($newPassword) || empty($confirmPassword)) {
        $error = "Vul alle velden in!";
    } elseif ($newPassword !== $confirmPassword) {
        $error = "Wachtwoorden komen niet overeen!";
    } elseif (strlen($newPassword) < 6) {
        $error = "Wachtwoord moet minimaal 6 tekens lang zijn!";
    } else {
        // Update wachtwoord
        $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);

        $conn = getDbConnection();
        $stmt = $conn->prepare("
            UPDATE mantelzorgers 
            SET password_hash = ?, 
                reset_token = NULL, 
                reset_token_expires = NULL 
            WHERE id = ?
        ");
        $stmt->bind_param("si", $passwordHash, $mantelzorger['id']);

        if ($stmt->execute()) {
            $success = true;
        } else {
            $error = "Er is iets misgegaan. Probeer het opnieuw.";
        }

        $stmt->close();
        $conn->close();
    }
}
?>
<!DOCTYPE html>
<html lang="nl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wachtwoord Instellen</title>
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

        .success-message {
            background-color: #d4edda;
            color: #155724;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
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
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
        }

        .btn-submit:hover {
            background-color: #45a049;
        }

        .btn-login {
            display: inline-block;
            margin-top: 20px;
            padding: 12px 30px;
            background-color: #2196F3;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
        }

        .btn-login:hover {
            background-color: #1976D2;
        }

        .user-info {
            background-color: #e3f2fd;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
        }
    </style>
</head>

<body>
    <div class="container">
        <h1>🔐 Nieuw Wachtwoord Instellen</h1>

        <?php if ($success): ?>
            <div class="success-message">
                <h2>✅ Wachtwoord Succesvol Ingesteld!</h2>
                <p>Je kunt nu inloggen met je nieuwe wachtwoord.</p>
                <a href="login.php" class="btn-login">Naar Login</a>
            </div>
        <?php elseif (!$validToken): ?>
            <div class="error-message">
                <?php echo htmlspecialchars($error); ?>
            </div>
            <a href="forgot-password.php" class="btn-login" style="display: block; text-align: center;">Nieuwe Link Aanvragen</a>
        <?php else: ?>
            <?php if ($error): ?>
                <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <div class="user-info">
                <strong>Account:</strong> <?php echo htmlspecialchars($mantelzorger['naam']); ?><br>
                <small><?php echo htmlspecialchars($mantelzorger['email']); ?></small>
            </div>

            <form method="POST">
                <div class="form-group">
                    <label>Nieuw wachtwoord:</label>
                    <input type="password" name="new_password" required autofocus
                        minlength="6" placeholder="Minimaal 6 tekens">
                </div>

                <div class="form-group">
                    <label>Bevestig wachtwoord:</label>
                    <input type="password" name="confirm_password" required
                        placeholder="Herhaal je wachtwoord">
                </div>

                <button type="submit" class="btn-submit">Wachtwoord Instellen</button>
            </form>
        <?php endif; ?>
    </div>
</body>

</html>