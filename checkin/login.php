<?php
session_start();

// Als al ingelogd, redirect naar index
if (isset($_SESSION['mantelzorger_logged_in']) && $_SESSION['mantelzorger_logged_in'] === true) {
    header("Location: index.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once 'includes/database.php';

    $usernameOrEmail = trim($_POST['username_or_email']);
    $password = $_POST['password'];

    if (!empty($usernameOrEmail) && !empty($password)) {
        $conn = getDbConnection();

        // Zoek op naam OF email
        $stmt = $conn->prepare("SELECT * FROM mantelzorgers WHERE naam = ? OR email = ?");
        $stmt->bind_param("ss", $usernameOrEmail, $usernameOrEmail);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $mantelzorger = $result->fetch_assoc();

            // Check of wachtwoord is ingesteld
            if (empty($mantelzorger['password_hash'])) {
                $error = "Je wachtwoord is nog niet ingesteld. Gebruik 'Wachtwoord vergeten' om een wachtwoord aan te maken.";
            }
            // Verifieer wachtwoord
            elseif (password_verify($password, $mantelzorger['password_hash'])) {
                $_SESSION['mantelzorger_logged_in'] = true;
                $_SESSION['mantelzorger_id'] = $mantelzorger['id'];
                $_SESSION['mantelzorger_naam'] = $mantelzorger['naam'];
                $_SESSION['mantelzorger_email'] = $mantelzorger['email'];

                // Update last_login
                $updateStmt = $conn->prepare("UPDATE mantelzorgers SET last_login = NOW() WHERE id = ?");
                $updateStmt->bind_param("i", $mantelzorger['id']);
                $updateStmt->execute();
                $updateStmt->close();

                header("Location: index.php");
                exit;
            } else {
                $error = "Onjuiste gebruikersnaam/email of wachtwoord!";
            }
        } else {
            $error = "Onjuiste gebruikersnaam/email of wachtwoord!";
        }

        $stmt->close();
        $conn->close();
    } else {
        $error = "Vul alle velden in!";
    }
}
?>
<!DOCTYPE html>
<html lang="nl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inloggen - Mantelzorg Checker</title>
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

        .login-container {
            background-color: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 400px;
        }

        h1 {
            color: #333;
            margin-top: 0;
            text-align: center;
        }

        .error-message {
            background-color: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        .success-message {
            background-color: #d4edda;
            color: #155724;
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

        .form-group input:focus {
            outline: none;
            border-color: #4CAF50;
        }

        .btn-login {
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

        .btn-login:hover {
            background-color: #45a049;
        }

        .forgot-password {
            text-align: center;
            margin-top: 20px;
        }

        .forgot-password a {
            color: #2196F3;
            text-decoration: none;
            font-size: 14px;
        }

        .forgot-password a:hover {
            text-decoration: underline;
        }
    </style>
</head>

<body>
    <div class="login-container">
        <h1>🔒 Mantelzorg Checker</h1>

        <?php if (isset($_GET['reset']) && $_GET['reset'] === 'success'): ?>
            <div class="success-message">
                ✅ Wachtwoord succesvol gewijzigd! Je kunt nu inloggen.
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>Gebruikersnaam of Email:</label>
                <input type="text" name="username_or_email" required autofocus
                    placeholder="Naam of email">
            </div>

            <div class="form-group">
                <label>Wachtwoord:</label>
                <input type="password" name="password" required
                    placeholder="••••••••">
            </div>

            <button type="submit" class="btn-login">Inloggen</button>
        </form>

        <div class="forgot-password">
            <a href="forgot-password.php">🔑 Wachtwoord vergeten?</a>
        </div>
    </div>
</body>

</html>