<?php
require_once 'includes/database.php';
require_once 'includes/auth.php';
requireLogin();

$message = '';
$error = '';

// Handle mantelzorger toevoegen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_mantelzorger'])) {
    $naam = trim($_POST['naam']);
    $email = trim($_POST['email']);

    if (!empty($naam) && !empty($email)) {
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $bytes = random_bytes(16);
            $bytes[6] = chr(ord($bytes[6]) & 0x0f | 0x40);
            $bytes[8] = chr(ord($bytes[8]) & 0x3f | 0x80);
            $uuid = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
            $ntfyTopic = 'Checkin_Alert_' . $uuid;

            $conn = getDbConnection();
            $stmt = $conn->prepare("INSERT INTO mantelzorgers (naam, email, ntfy_topic) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $naam, $email, $ntfyTopic);

            if ($stmt->execute()) {
                $message = "Mantelzorger '$naam' succesvol toegevoegd!";
            } else {
                if ($conn->errno == 1062) {
                    $error = "Dit email adres bestaat al!";
                } else {
                    $error = "Fout bij toevoegen: " . $stmt->error;
                }
            }

            $stmt->close();
            $conn->close();
        } else {
            $error = "Ongeldig email adres!";
        }
    } else {
        $error = "Naam en email zijn verplicht!";
    }
}

// Handle mantelzorger naam/email wijzigen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_mantelzorger'])) {
    $mantelzorgerId = $_POST['mantelzorger_id'];
    $nieuweNaam = trim($_POST['nieuwe_naam']);
    $nieuwEmail = trim($_POST['nieuw_email']);

    if (!empty($nieuweNaam) && !empty($nieuwEmail)) {
        if (filter_var($nieuwEmail, FILTER_VALIDATE_EMAIL)) {
            $conn = getDbConnection();
            $stmt = $conn->prepare("UPDATE mantelzorgers SET naam = ?, email = ? WHERE id = ?");
            $stmt->bind_param("ssi", $nieuweNaam, $nieuwEmail, $mantelzorgerId);

            if ($stmt->execute()) {
                $message = "Mantelzorger succesvol gewijzigd!";
            } else {
                $error = "Fout bij wijzigen: " . $stmt->error;
            }

            $stmt->close();
            $conn->close();
        } else {
            $error = "Ongeldig email adres!";
        }
    } else {
        $error = "Naam en email mogen niet leeg zijn!";
    }
}

// Handle mantelzorger verwijderen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_mantelzorger'])) {
    $mantelzorgerId = $_POST['mantelzorger_id'];

    // Check eerst of er clients gekoppeld zijn via client_mantelzorgers
    $conn = getDbConnection();
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM client_mantelzorgers WHERE mantelzorger_id = ?");
    $stmt->bind_param("i", $mantelzorgerId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $clientCount = $row['count'];
    $stmt->close();

    if ($clientCount > 0) {
        $error = "Kan mantelzorger niet verwijderen: er zijn nog $clientCount client(s) gekoppeld!";
    } else {
        $stmt = $conn->prepare("DELETE FROM mantelzorgers WHERE id = ?");
        $stmt->bind_param("i", $mantelzorgerId);

        if ($stmt->execute()) {
            $message = "Mantelzorger succesvol verwijderd!";
        } else {
            $error = "Fout bij verwijderen: " . $stmt->error;
        }

        $stmt->close();
    }

    $conn->close();
}

// Haal alle mantelzorgers op met aantal clients
$conn = getDbConnection();
$mantelzorgers = $conn->query("
    SELECT m.*, COUNT(cm.client_id) as client_count 
    FROM mantelzorgers m 
    LEFT JOIN client_mantelzorgers cm ON m.id = cm.mantelzorger_id 
    GROUP BY m.id 
    ORDER BY m.naam
")->fetch_all(MYSQLI_ASSOC);
$conn->close();
?>
<!DOCTYPE html>
<html lang="nl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mantelzorgers Beheren</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 1000px;
            margin: 50px auto;
            padding: 20px;
            background-color: #f5f5f5;
        }

        h1 {
            color: #333;
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

        .section {
            background-color: white;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }

        .section h2 {
            margin-top: 0;
            color: #333;
            border-bottom: 2px solid #4CAF50;
            padding-bottom: 10px;
        }

        .form-group {
            display: grid;
            grid-template-columns: 130px 1fr;
            gap: 10px;
            align-items: center;
            margin-bottom: 15px;
        }

        .form-group label {
            font-weight: bold;
            text-align: left;
        }

        .form-group input {
            width: 300px;
            max-width: 300px;
            min-width: 300px;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 3px;
            font-size: 14px;
            box-sizing: border-box;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            width: 120px;
            text-align: center;
        }

        .btn-primary {
            background-color: #4CAF50;
            color: white;
        }

        .btn-primary:hover {
            background-color: #45a049;
        }

        .btn-warning {
            background-color: #ff9800;
            color: white;
        }

        .btn-warning:hover {
            background-color: #e68900;
        }

        .btn-danger {
            background-color: #f44336;
            color: white;
        }

        .btn-danger:hover {
            background-color: #da190b;
        }

        .btn-danger:disabled {
            background-color: #ccc;
            cursor: not-allowed;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        th {
            background-color: #f8f9fa;
            font-weight: bold;
        }

        .mantelzorger-row {
            background-color: #fafafa;
        }

        .mantelzorger-row:not(:last-child) {
            border-bottom: 2px solid #ddd;
        }

        .edit-row {
            display: grid;
            grid-template-columns: 130px 1fr;
            gap: 10px;
            align-items: center;
            margin-bottom: 10px;
        }

        .edit-row label {
            font-weight: bold;
            text-align: left;
        }

        .edit-input {
            width: 200px;
            max-width: 200px;
            min-width: 200px;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 3px;
            box-sizing: border-box;
        }

        .edit-buttons {
            display: flex;
            gap: 10px;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
        }

        .inline-form {
            display: inline-block;
        }
    </style>
    <script>
        function confirmDelete(naam, clientCount) {
            if (clientCount > 0) {
                alert('Kan niet verwijderen: er zijn nog ' + clientCount + ' client(s) gekoppeld!');
                return false;
            }
            return confirm('Weet je zeker dat je "' + naam + '" wilt verwijderen?');
        }

        function toggleEditMode(id) {
            const infoSpan = document.getElementById('info-' + id);
            const editForm = document.getElementById('edit-form-' + id);
            const actionButtons = document.getElementById('action-buttons-' + id);
            const editButtons = document.getElementById('edit-buttons-' + id);

            if (editForm.style.display === 'none') {
                infoSpan.style.display = 'none';
                editForm.style.display = 'block';
                actionButtons.style.display = 'none';
                editButtons.style.display = 'flex';
            } else {
                infoSpan.style.display = 'block';
                editForm.style.display = 'none';
                actionButtons.style.display = 'flex';
                editButtons.style.display = 'none';
            }
        }

        function submitEditForm(id) {
            document.getElementById('hidden-form-' + id).submit();
        }
    </script>
</head>

<body>
    <a href="index.php" class="back-link">← Terug naar overzicht</a>
    <a href="manage-clients.php" class="back-link" style="margin-left: 20px;">→ Clients Beheren</a>

    <h1>Mantelzorgers Beheren</h1>

    <?php if ($message): ?>
        <div class="success-message"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <!-- Nieuwe mantelzorger toevoegen -->
    <div class="section">
        <h2>Nieuwe Mantelzorger Toevoegen</h2>
        <form method="POST">
            <div class="form-group">
                <label>Naam:</label>
                <input type="text" name="naam" required placeholder="Bijv. Jan de Vries">
            </div>

            <div class="form-group">
                <label>Email:</label>
                <input type="email" name="email" required placeholder="jan@example.com">
            </div>

            <button type="submit" name="add_mantelzorger" class="btn btn-primary">
                Toevoegen
            </button>
        </form>
    </div>

    <!-- Bestaande mantelzorgers beheren -->
    <div class="section">
        <h2>Bestaande Mantelzorgers</h2>

        <?php if (empty($mantelzorgers)): ?>
            <p>Geen mantelzorgers gevonden.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Naam & Email</th>
                        <th>ntfy Topic</th>
                        <th>Aantal Clients</th>
                        <th>Acties</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($mantelzorgers as $mz): ?>
                        <tr class="mantelzorger-row">
                            <td>
                                <span id="info-<?php echo $mz['id']; ?>">
                                    <strong><?php echo htmlspecialchars($mz['naam']); ?></strong><br>
                                    <small><?php echo htmlspecialchars($mz['email']); ?></small>
                                </span>

                                <div id="edit-form-<?php echo $mz['id']; ?>" style="display: none;">
                                    <div class="edit-row">
                                        <label>Naam:</label>
                                        <input type="text" id="nieuwe-naam-<?php echo $mz['id']; ?>" class="edit-input"
                                            value="<?php echo htmlspecialchars($mz['naam']); ?>"
                                            required>
                                    </div>

                                    <div class="edit-row">
                                        <label>Email:</label>
                                        <input type="email" id="nieuw-email-<?php echo $mz['id']; ?>" class="edit-input"
                                            value="<?php echo htmlspecialchars($mz['email']); ?>"
                                            required>
                                    </div>

                                    <!-- Hidden form voor submit -->
                                    <form method="POST" id="hidden-form-<?php echo $mz['id']; ?>" style="display: none;">
                                        <input type="hidden" name="mantelzorger_id" value="<?php echo $mz['id']; ?>">
                                        <input type="hidden" name="nieuwe_naam" id="hidden-naam-<?php echo $mz['id']; ?>">
                                        <input type="hidden" name="nieuw_email" id="hidden-email-<?php echo $mz['id']; ?>">
                                        <input type="hidden" name="update_mantelzorger" value="1">
                                    </form>
                                </div>
                            </td>
                            <td>
                                <?php if (!empty($mz['ntfy_topic'])): ?>
                                    <small style="font-family: monospace; color: #555;"><?php echo htmlspecialchars($mz['ntfy_topic']); ?></small>
                                <?php else: ?>
                                    <small style="color: #c00;">Niet ingesteld</small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $mz['client_count']; ?> client(s)</td>
                            <td>
                                <div class="action-buttons" id="action-buttons-<?php echo $mz['id']; ?>">
                                    <button class="btn btn-warning"
                                        onclick="toggleEditMode(<?php echo $mz['id']; ?>)">
                                        Wijzigen
                                    </button>

                                    <form method="POST" class="inline-form"
                                        onsubmit="return confirmDelete('<?php echo htmlspecialchars($mz['naam']); ?>', <?php echo $mz['client_count']; ?>)">
                                        <input type="hidden" name="mantelzorger_id" value="<?php echo $mz['id']; ?>">
                                        <button type="submit" name="delete_mantelzorger" class="btn btn-danger"
                                            <?php echo $mz['client_count'] > 0 ? 'disabled' : ''; ?>>
                                            Verwijderen
                                        </button>
                                    </form>
                                </div>

                                <div class="edit-buttons" id="edit-buttons-<?php echo $mz['id']; ?>" style="display: none;">
                                    <button type="button" class="btn btn-primary"
                                        onclick="
                                            document.getElementById('hidden-naam-<?php echo $mz['id']; ?>').value = document.getElementById('nieuwe-naam-<?php echo $mz['id']; ?>').value;
                                            document.getElementById('hidden-email-<?php echo $mz['id']; ?>').value = document.getElementById('nieuw-email-<?php echo $mz['id']; ?>').value;
                                            submitEditForm(<?php echo $mz['id']; ?>);">
                                        Opslaan
                                    </button>
                                    <button type="button" class="btn btn-warning"
                                        onclick="toggleEditMode(<?php echo $mz['id']; ?>)">
                                        Annuleren
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</body>

</html>