<?php
require_once 'includes/auth.php';
requireLogin();
$mantelzorger = getLoggedInMantelzorger();
require_once 'includes/database.php';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $currentPassword = $_POST['current_password'];
    $newPassword = $_POST['new_password'];
    $confirmPassword = $_POST['confirm_password'];

    if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
        $error = "Vul alle velden in!";
    } elseif ($newPassword !== $confirmPassword) {
        $error = "Nieuwe wachtwoorden komen niet overeen!";
    } elseif (strlen($newPassword) < 6) {
        $error = "Wachtwoord moet minimaal 6 tekens lang zijn!";
    } else {
        // Verifieer huidig wachtwoord
        $conn = getDbConnection();
        $stmt = $conn->prepare("SELECT password_hash FROM mantelzorgers WHERE id = ?");
        $stmt->bind_param("i", $_SESSION['mantelzorger_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $mantelzorger = $result->fetch_assoc();
        $stmt->close();

        if (password_verify($currentPassword, $mantelzorger['password_hash'])) {
            // Update wachtwoord
            $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $updateStmt = $conn->prepare("UPDATE mantelzorgers SET password_hash = ? WHERE id = ?");
            $updateStmt->bind_param("si", $newPasswordHash, $_SESSION['mantelzorger_id']);

            if ($updateStmt->execute()) {
                $message = "Wachtwoord succesvol gewijzigd!";
            } else {
                $error = "Fout bij wijzigen wachtwoord!";
            }

            $updateStmt->close();
        } else {
            $error = "Huidig wachtwoord is onjuist!";
        }

        $conn->close();
    }
}
?>
<!DOCTYPE html>
<html lang="nl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wachtwoord Wijzigen</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 600px;
            margin: 50px auto;
            padding: 20px;
            background-color: #f5f5f5;
        }

        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: #4CAF50;
            text-decoration: none;
        }

        .back-link:hover {
            text-decoration: underline;
        }

        .container {
            background-color: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        h1 {
            color: #333;
            margin-top: 0;
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

        .btn-save {
            background-color: #4CAF50;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
        }

        .btn-save:hover {
            background-color: #45a049;
        }
    </style>
</head>

<body>
    <a href="index.php" class="back-link">← Terug naar overzicht</a>

    <div class="container">
        <h1>Wachtwoord Wijzigen</h1>

        <?php if ($message): ?>
            <div class="success-message"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>Huidig wachtwoord:</label>
                <input type="password" name="current_password" required>
            </div>

            <div class="form-group">
                <label>Nieuw wachtwoord:</label>
                <input type="password" name="new_password" required
                    minlength="6" placeholder="Minimaal 6 tekens">
            </div>

            <div class="form-group">
                <label>Bevestig nieuw wachtwoord:</label>
                <input type="password" name="confirm_password" required>
            </div>

            <button type="submit" class="btn-save">Wachtwoord Wijzigen</button>
        </form>
    </div>
</body>

</html>