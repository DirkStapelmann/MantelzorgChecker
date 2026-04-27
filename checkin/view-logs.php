<?php
require_once 'includes/database.php';
require_once 'includes/auth.php';
requireLogin();

$days = isset($_GET['days']) ? (int)$_GET['days'] : 7;
$type = isset($_GET['type']) ? $_GET['type'] : 'all';
$component = isset($_GET['component']) ? $_GET['component'] : 'all';

$conn = getDbConnection();

$query = "
    SELECT l.*, c.naam as client_naam
    FROM system_logs l
    LEFT JOIN clients c ON l.client_id = c.id
    WHERE l.log_datetime >= DATE_SUB(NOW(), INTERVAL ? DAY)
";

$params = [$days];
$types = "i";

if ($type != 'all') {
    $query .= " AND l.log_type = ?";
    $params[] = $type;
    $types .= "s";
}

if ($component != 'all') {
    $query .= " AND l.component = ?";
    $params[] = $component;
    $types .= "s";
}

$query .= " ORDER BY l.log_datetime DESC LIMIT 500";

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$logs = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="nl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Systeem Logs</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 1400px;
            margin: 20px auto;
            padding: 20px;
            background-color: #f5f5f5;
        }

        h1 {
            color: #333;
        }

        .filters {
            background: white;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .filters form {
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .filters select,
        .filters button {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 3px;
        }

        .filters button {
            background-color: #4CAF50;
            color: white;
            border: none;
            cursor: pointer;
        }

        .filters button:hover {
            background-color: #45a049;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background-color: white;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
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
            position: sticky;
            top: 0;
        }

        .log-info {
            background-color: #e3f2fd;
        }

        .log-success {
            background-color: #e8f5e9;
        }

        .log-warning {
            background-color: #fff3e0;
        }

        .log-error {
            background-color: #ffebee;
        }

        .type-badge {
            padding: 4px 8px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: bold;
        }

        .badge-info {
            background-color: #2196F3;
            color: white;
        }

        .badge-success {
            background-color: #4CAF50;
            color: white;
        }

        .badge-warning {
            background-color: #FF9800;
            color: white;
        }

        .badge-error {
            background-color: #f44336;
            color: white;
        }

        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            padding: 10px 20px;
            background-color: #2196F3;
            color: white;
            text-decoration: none;
            border-radius: 5px;
        }

        .back-link:hover {
            background-color: #1976D2;
        }

        .stats {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }

        .stat-box {
            background: white;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            flex: 1;
        }

        .stat-box h3 {
            margin: 0 0 10px 0;
            color: #666;
            font-size: 14px;
        }

        .stat-box .number {
            font-size: 32px;
            font-weight: bold;
            color: #333;
        }
    </style>
</head>

<body>
    <a href="index.php" class="back-link">← Terug naar Dashboard</a>

    <h1>📋 Systeem Logs</h1>

    <?php
    // Bereken statistieken
    $totalLogs = count($logs);
    $errorCount = count(array_filter($logs, fn($l) => $l['log_type'] == 'error'));
    $successCount = count(array_filter($logs, fn($l) => $l['log_type'] == 'success'));
    $alertCheckCount = count(array_filter($logs, fn($l) => $l['component'] == 'alert-check'));
    ?>

    <div class="stats">
        <div class="stat-box">
            <h3>Totaal Logs</h3>
            <div class="number"><?php echo $totalLogs; ?></div>
        </div>
        <div class="stat-box">
            <h3>Successen</h3>
            <div class="number" style="color: #4CAF50;"><?php echo $successCount; ?></div>
        </div>
        <div class="stat-box">
            <h3>Fouten</h3>
            <div class="number" style="color: #f44336;"><?php echo $errorCount; ?></div>
        </div>
        <div class="stat-box">
            <h3>Alert Checks</h3>
            <div class="number" style="color: #2196F3;"><?php echo $alertCheckCount; ?></div>
        </div>
    </div>

    <div class="filters">
        <form method="GET">
            <label>
                Periode:
                <select name="days">
                    <option value="1" <?php echo $days == 1 ? 'selected' : ''; ?>>Vandaag</option>
                    <option value="7" <?php echo $days == 7 ? 'selected' : ''; ?>>7 dagen</option>
                    <option value="30" <?php echo $days == 30 ? 'selected' : ''; ?>>30 dagen</option>
                    <option value="90" <?php echo $days == 90 ? 'selected' : ''; ?>>90 dagen</option>
                </select>
            </label>

            <label>
                Type:
                <select name="type">
                    <option value="all" <?php echo $type == 'all' ? 'selected' : ''; ?>>Alle</option>
                    <option value="info" <?php echo $type == 'info' ? 'selected' : ''; ?>>Info</option>
                    <option value="success" <?php echo $type == 'success' ? 'selected' : ''; ?>>Success</option>
                    <option value="warning" <?php echo $type == 'warning' ? 'selected' : ''; ?>>Warning</option>
                    <option value="error" <?php echo $type == 'error' ? 'selected' : ''; ?>>Error</option>
                </select>
            </label>

            <label>
                Component:
                <select name="component">
                    <option value="all" <?php echo $component == 'all' ? 'selected' : ''; ?>>Alle</option>
                    <option value="alert-check" <?php echo $component == 'alert-check' ? 'selected' : ''; ?>>Alert Check</option>
                    <option value="check-in" <?php echo $component == 'check-in' ? 'selected' : ''; ?>>Check-in</option>
                </select>
            </label>

            <button type="submit">Filteren</button>
            <a href="view-logs.php" style="margin-left: 10px; padding: 8px 12px; background-color: #999; color: white; text-decoration: none; border-radius: 3px;">Reset</a>
        </form>
    </div>

    <table>
        <thead>
            <tr>
                <th>Tijd</th>
                <th>Type</th>
                <th>Component</th>
                <th>Client</th>
                <th>Bericht</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($logs)): ?>
                <tr>
                    <td colspan="5" style="text-align: center; padding: 40px; color: #999;">
                        Geen logs gevonden voor deze filters.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($logs as $log): ?>
                    <tr class="log-<?php echo $log['log_type']; ?>">
                        <td style="white-space: nowrap;">
                            <?php echo date('d-m-Y H:i:s', strtotime($log['log_datetime'])); ?>
                        </td>
                        <td>
                            <span class="type-badge badge-<?php echo $log['log_type']; ?>">
                                <?php echo strtoupper($log['log_type']); ?>
                            </span>
                        </td>
                        <td><?php echo htmlspecialchars($log['component']); ?></td>
                        <td><?php echo $log['client_naam'] ?: '-'; ?></td>
                        <td><?php echo htmlspecialchars($log['message']); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <p style="margin-top: 20px; color: #666; font-size: 14px;">
        Laatste <?php echo count($logs); ?> logs (max 500)
    </p>
</body>

</html>