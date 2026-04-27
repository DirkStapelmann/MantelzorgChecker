<?php
require_once 'includes/auth.php';
requireLogin();
require_once 'includes/database.php';

$message = '';
$error = '';

// Handle client toevoegen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_client'])) {
    $naam = trim($_POST['naam']);
    $mantelzorgerIds = isset($_POST['mantelzorger_ids']) ? $_POST['mantelzorger_ids'] : [];

    // Filter out empty values
    $mantelzorgerIds = array_filter($mantelzorgerIds, function ($id) {
        return !empty($id);
    });

    if (!empty($naam) && !empty($mantelzorgerIds)) {
        // Genereer unieke token
        $token = bin2hex(random_bytes(32));

        $conn = getDbConnection();

        // Voeg client toe
        $stmt = $conn->prepare("INSERT INTO clients (naam, unique_token) VALUES (?, ?)");
        $stmt->bind_param("ss", $naam, $token);

        if ($stmt->execute()) {
            $clientId = $conn->insert_id;

            // Koppel alle geselecteerde mantelzorgers (uniek)
            $mantelzorgerIds = array_unique($mantelzorgerIds);
            foreach ($mantelzorgerIds as $mantelzorgerId) {
                addMantelzorgerToClient($clientId, $mantelzorgerId);
            }

            $message = "Client '$naam' succesvol toegevoegd met " . count($mantelzorgerIds) . " mantelzorger(s)!";
        } else {
            $error = "Fout bij toevoegen: " . $stmt->error;
        }

        $stmt->close();
        $conn->close();
    } else {
        $error = "Vul alle velden in en selecteer minimaal 1 mantelzorger!";
    }
}

// Handle mantelzorgers wijzigen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_client_mantelzorgers'])) {
    $clientId = $_POST['client_id'];
    $mantelzorgerIds = isset($_POST['mantelzorger_ids']) ? $_POST['mantelzorger_ids'] : [];

    // Filter out empty values
    $mantelzorgerIds = array_filter($mantelzorgerIds, function ($id) {
        return !empty($id);
    });

    if (!empty($mantelzorgerIds)) {
        // Verwijder alle huidige koppelingen
        $conn = getDbConnection();
        $stmt = $conn->prepare("DELETE FROM client_mantelzorgers WHERE client_id = ?");
        $stmt->bind_param("i", $clientId);
        $stmt->execute();
        $stmt->close();
        $conn->close();

        // Voeg nieuwe koppelingen toe (uniek)
        $mantelzorgerIds = array_unique($mantelzorgerIds);
        foreach ($mantelzorgerIds as $mantelzorgerId) {
            addMantelzorgerToClient($clientId, $mantelzorgerId);
        }

        $message = "Mantelzorgers succesvol bijgewerkt (" . count($mantelzorgerIds) . " gekoppeld)!";
    } else {
        $error = "Selecteer minimaal 1 mantelzorger!";
    }
}

// Handle client naam wijzigen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_client_name'])) {
    $clientId = $_POST['client_id'];
    $nieuweNaam = trim($_POST['nieuwe_naam']);

    if (!empty($nieuweNaam)) {
        $conn = getDbConnection();
        $stmt = $conn->prepare("UPDATE clients SET naam = ? WHERE id = ?");
        $stmt->bind_param("si", $nieuweNaam, $clientId);

        if ($stmt->execute()) {
            $message = "Client naam succesvol gewijzigd!";
        } else {
            $error = "Fout bij wijzigen: " . $stmt->error;
        }

        $stmt->close();
        $conn->close();
    } else {
        $error = "Naam mag niet leeg zijn!";
    }
}

// Handle client verwijderen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_client'])) {
    $clientId = $_POST['client_id'];
    $clientNaam = $_POST['client_naam'];

    $conn = getDbConnection();
    $stmt = $conn->prepare("DELETE FROM clients WHERE id = ?");
    $stmt->bind_param("i", $clientId);

    if ($stmt->execute()) {
        $message = "Client '$clientNaam' succesvol verwijderd!";
    } else {
        $error = "Fout bij verwijderen: " . $stmt->error;
    }

    $stmt->close();
    $conn->close();
}

// Haal alle clients en mantelzorgers op
$conn = getDbConnection();
$clients = $conn->query("SELECT * FROM clients ORDER BY naam")->fetch_all(MYSQLI_ASSOC);
$mantelzorgers = $conn->query("SELECT * FROM mantelzorgers ORDER BY naam")->fetch_all(MYSQLI_ASSOC);
$conn->close();

// Voeg mantelzorger info toe aan elke client
foreach ($clients as &$client) {
    $client['mantelzorgers'] = getClientMantelzorgers($client['id']);
}
unset($client);
?>
<!DOCTYPE html>
<html lang="nl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clients Beheren</title>
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

        .add-form-row {
            display: grid;
            grid-template-columns: 130px 1fr;
            gap: 10px;
            align-items: start;
            margin-bottom: 15px;
        }

        .add-form-row label {
            font-weight: bold;
            text-align: left;
            padding-top: 8px;
        }

        .add-form-row input,
        .add-form-row select {
            width: 300px;
            max-width: 300px;
            min-width: 300px;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 3px;
            font-size: 14px;
            box-sizing: border-box;
        }

        .mantelzorger-select-group {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-bottom: 15px;
        }

        .mantelzorger-select-row {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .btn-add-mantelzorger {
            background-color: #2196F3;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 3px;
            cursor: pointer;
            font-size: 13px;
            margin-bottom: 15px;
        }

        .btn-add-mantelzorger:hover {
            background-color: #1976D2;
        }

        .btn-remove-mantelzorger {
            background-color: #f44336;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 3px;
            cursor: pointer;
            font-size: 12px;
        }

        .btn-remove-mantelzorger:hover {
            background-color: #da190b;
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

        .client-row {
            background-color: #fafafa;
        }

        .client-row:not(:last-child) {
            border-bottom: 2px solid #ddd;
        }

        .edit-container {
            display: flex;
            flex-direction: column;
            gap: 15px;
            margin-top: 20px;
        }

        .edit-row {
            display: grid;
            grid-template-columns: 130px 1fr;
            gap: 10px;
            align-items: start;
        }

        .edit-row label {
            font-weight: bold;
            text-align: left;
            padding-top: 8px;
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

        .edit-select {
            width: 250px;
            max-width: 250px;
            min-width: 250px;
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

        .mantelzorger-badges {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
            margin-top: 8px;
        }

        .badge {
            background-color: #e3f2fd;
            color: #1976D2;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            display: inline-block;
        }
    </style>
    <script>
        let mantelzorgerCount = 1;
        let editMantelzorgerCount = {};

        function addMantelzorgerSelect() {
            const container = document.getElementById('mantelzorger-selects');
            const newRow = document.createElement('div');
            newRow.className = 'mantelzorger-select-row';
            newRow.id = 'mantelzorger-row-' + mantelzorgerCount;

            newRow.innerHTML = `
                <select name="mantelzorger_ids[]" required style="width: 300px; padding: 8px; border: 1px solid #ddd; border-radius: 3px;">
                    <option value="">-- Selecteer mantelzorger --</option>
                    <?php foreach ($mantelzorgers as $mz): ?>
                        <option value="<?php echo $mz['id']; ?>">
                            <?php echo htmlspecialchars($mz['naam']); ?> (<?php echo htmlspecialchars($mz['email']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="button" class="btn-remove-mantelzorger" onclick="removeMantelzorgerSelect(${mantelzorgerCount})">
                    Verwijder
                </button>
            `;

            container.appendChild(newRow);
            mantelzorgerCount++;
        }

        function removeMantelzorgerSelect(id) {
            const row = document.getElementById('mantelzorger-row-' + id);
            if (row) {
                row.remove();
            }
        }

        function addEditMantelzorgerSelect(clientId) {
            if (!editMantelzorgerCount[clientId]) {
                editMantelzorgerCount[clientId] = 1;
            }

            const container = document.getElementById('edit-mantelzorger-selects-' + clientId);
            const newRow = document.createElement('div');
            newRow.className = 'mantelzorger-select-row';
            newRow.id = 'edit-mantelzorger-row-' + clientId + '-' + editMantelzorgerCount[clientId];

            newRow.innerHTML = `
                <select name="mantelzorger_ids[]" required class="edit-select">
                    <option value="">-- Selecteer mantelzorger --</option>
                    <?php foreach ($mantelzorgers as $mz): ?>
                        <option value="<?php echo $mz['id']; ?>">
                            <?php echo htmlspecialchars($mz['naam']); ?> (<?php echo htmlspecialchars($mz['email']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="button" class="btn-remove-mantelzorger" onclick="removeEditMantelzorgerSelect(${clientId}, ${editMantelzorgerCount[clientId]})">
                    Verwijder
                </button>
            `;

            container.appendChild(newRow);
            editMantelzorgerCount[clientId]++;
        }

        function removeEditMantelzorgerSelect(clientId, id) {
            const row = document.getElementById('edit-mantelzorger-row-' + clientId + '-' + id);
            if (row) {
                // Check hoeveel dropdowns er nog zijn
                const container = document.getElementById('edit-mantelzorger-selects-' + clientId);
                const remainingRows = container.querySelectorAll('.mantelzorger-select-row').length;

                // Alleen verwijderen als er meer dan 1 dropdown is
                if (remainingRows > 1) {
                    row.remove();
                } else {
                    alert('Er moet minimaal 1 mantelzorger gekoppeld zijn!');
                }
            }
        }

        function confirmDelete(clientNaam) {
            return confirm('Weet je zeker dat je "' + clientNaam + '" wilt verwijderen? Dit kan niet ongedaan gemaakt worden!');
        }

        function toggleEditMode(clientId) {
            const nameSpan = document.getElementById('info-' + clientId);
            const editForm = document.getElementById('edit-form-' + clientId);
            const actionButtons = document.getElementById('action-buttons-' + clientId);

            if (editForm.style.display === 'none') {
                nameSpan.style.display = 'none';
                editForm.style.display = 'block';
                actionButtons.style.display = 'none'; // Verberg Wijzigen/Verwijderen knoppen
            } else {
                nameSpan.style.display = 'block';
                editForm.style.display = 'none';
                actionButtons.style.display = 'flex'; // Toon Wijzigen/Verwijderen knoppen
            }
        }
    </script>
</head>

<body>
    <a href="index.php" class="back-link">← Terug naar overzicht</a>
    <a href="manage-mantelzorgers.php" class="back-link" style="margin-left: 20px;">→ Mantelzorgers Beheren</a>

    <h1>Clients Beheren</h1>

    <?php if ($message): ?>
        <div class="success-message"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <!-- Nieuwe client toevoegen -->
    <div class="section">
        <h2>Nieuwe Client Toevoegen</h2>
        <form method="POST">
            <div class="add-form-row">
                <label>Naam:</label>
                <input type="text" name="naam" required placeholder="Bijv. Client 4">
            </div>

            <div class="add-form-row">
                <label>Mantelzorger:</label>
                <div>
                    <div class="mantelzorger-select-group" id="mantelzorger-selects">
                        <div class="mantelzorger-select-row">
                            <select name="mantelzorger_ids[]" required style="width: 300px; padding: 8px; border: 1px solid #ddd; border-radius: 3px;">
                                <option value="">-- Selecteer mantelzorger --</option>
                                <?php foreach ($mantelzorgers as $mz): ?>
                                    <option value="<?php echo $mz['id']; ?>">
                                        <?php echo htmlspecialchars($mz['naam']); ?> (<?php echo htmlspecialchars($mz['email']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <button type="button" class="btn-add-mantelzorger" onclick="addMantelzorgerSelect()">
                        + Extra Mantelzorger Toevoegen
                    </button>
                </div>
            </div>

            <button type="submit" name="add_client" class="btn btn-primary">
                Toevoegen
            </button>
        </form>
    </div>

    <!-- Bestaande clients beheren -->
    <div class="section">
        <h2>Bestaande Clients</h2>

        <?php if (empty($clients)): ?>
            <p>Geen clients gevonden.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Naam</th>
                        <th>Mantelzorgers</th>
                        <th>Acties</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($clients as $client): ?>
                        <tr class="client-row">
                            <td>
                                <div id="info-<?php echo $client['id']; ?>">
                                    <strong><?php echo htmlspecialchars($client['naam']); ?></strong><br>
                                    <div class="mantelzorger-badges">
                                        <?php if (empty($client['mantelzorgers'])): ?>
                                            <span style="color: #999; font-style: italic;">Geen mantelzorgers</span>
                                        <?php else: ?>
                                            <?php foreach ($client['mantelzorgers'] as $mz): ?>
                                                <span class="badge"><?php echo htmlspecialchars($mz['naam']); ?></span>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div id="edit-form-<?php echo $client['id']; ?>" style="display: none;">
                                    <div class="edit-container">
                                        <!-- Naam wijzigen -->
                                        <form method="POST">
                                            <input type="hidden" name="client_id" value="<?php echo $client['id']; ?>">
                                            <div class="edit-row">
                                                <label>Naam:</label>
                                                <input type="text" name="nieuwe_naam" class="edit-input"
                                                    value="<?php echo htmlspecialchars($client['naam']); ?>"
                                                    required>
                                            </div>
                                            <div class="edit-buttons">
                                                <button type="submit" name="update_client_name" class="btn btn-primary">
                                                    Opslaan
                                                </button>
                                            </div>
                                        </form>

                                        <hr style="margin: 10px 0;">

                                        <!-- Mantelzorgers wijzigen -->
                                        <form method="POST">
                                            <input type="hidden" name="client_id" value="<?php echo $client['id']; ?>">
                                            <div class="edit-row">
                                                <label>Mantelzorgers:</label>
                                                <div>
                                                    <div class="mantelzorger-select-group" id="edit-mantelzorger-selects-<?php echo $client['id']; ?>">
                                                        <?php
                                                        $currentMantelzorgers = $client['mantelzorgers'];
                                                        if (empty($currentMantelzorgers)) {
                                                            $currentMantelzorgers = [null]; // Toon minimaal 1 dropdown
                                                        }
                                                        $counter = 0;
                                                        foreach ($currentMantelzorgers as $idx => $currentMz):
                                                        ?>
                                                            <div class="mantelzorger-select-row" id="edit-mantelzorger-row-<?php echo $client['id']; ?>-<?php echo $counter; ?>">
                                                                <select name="mantelzorger_ids[]" required class="edit-select">
                                                                    <option value="">-- Selecteer mantelzorger --</option>
                                                                    <?php foreach ($mantelzorgers as $mz): ?>
                                                                        <option value="<?php echo $mz['id']; ?>"
                                                                            <?php echo ($currentMz && $mz['id'] == $currentMz['id']) ? 'selected' : ''; ?>>
                                                                            <?php echo htmlspecialchars($mz['naam']); ?> (<?php echo htmlspecialchars($mz['email']); ?>)
                                                                        </option>
                                                                    <?php endforeach; ?>
                                                                </select>
                                                                <?php if (count($currentMantelzorgers) > 1): ?>
                                                                    <button type="button" class="btn-remove-mantelzorger"
                                                                        onclick="removeEditMantelzorgerSelect(<?php echo $client['id']; ?>, <?php echo $counter; ?>)">
                                                                        Verwijder
                                                                    </button>
                                                                <?php endif; ?>
                                                            </div>
                                                        <?php
                                                            $counter++;
                                                        endforeach;
                                                        ?>
                                                    </div>
                                                    <button type="button" class="btn-add-mantelzorger"
                                                        onclick="addEditMantelzorgerSelect(<?php echo $client['id']; ?>)">
                                                        + Extra Mantelzorger Toevoegen
                                                    </button>
                                                </div>
                                            </div>
                                            <div class="edit-buttons">
                                                <button type="submit" name="update_client_mantelzorgers" class="btn btn-primary">
                                                    Opslaan
                                                </button>
                                                <button type="button" class="btn btn-warning"
                                                    onclick="toggleEditMode(<?php echo $client['id']; ?>)">
                                                    Annuleren
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <?php if (empty($client['mantelzorgers'])): ?>
                                    <span style="color: #999;">-</span>
                                <?php else: ?>
                                    <?php foreach ($client['mantelzorgers'] as $mz): ?>
                                        <?php echo htmlspecialchars($mz['naam']); ?><br>
                                        <small style="color: #666;"><?php echo htmlspecialchars($mz['email']); ?></small><br>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="action-buttons" id="action-buttons-<?php echo $client['id']; ?>">
                                    <button class="btn btn-warning"
                                        onclick="toggleEditMode(<?php echo $client['id']; ?>)">
                                        Wijzigen
                                    </button>

                                    <form method="POST" class="inline-form"
                                        onsubmit="return confirmDelete('<?php echo htmlspecialchars($client['naam']); ?>')">
                                        <input type="hidden" name="client_id" value="<?php echo $client['id']; ?>">
                                        <input type="hidden" name="client_naam" value="<?php echo htmlspecialchars($client['naam']); ?>">
                                        <button type="submit" name="delete_client" class="btn btn-danger">
                                            Verwijderen
                                        </button>
                                    </form>
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