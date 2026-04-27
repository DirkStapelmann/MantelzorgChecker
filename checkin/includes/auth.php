<?php
// Start sessie als nog niet gestart
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check of mantelzorger is ingelogd
function requireLogin()
{
    if (!isset($_SESSION['mantelzorger_logged_in']) || $_SESSION['mantelzorger_logged_in'] !== true) {
        header("Location: login.php");
        exit;
    }
}

// Haal ingelogde mantelzorger op
function getLoggedInMantelzorger()
{
    if (!isset($_SESSION['mantelzorger_id'])) {
        return null;
    }

    require_once 'includes/database.php';
    $conn = getDbConnection();
    $stmt = $conn->prepare("SELECT * FROM mantelzorgers WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['mantelzorger_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $mantelzorger = $result->fetch_assoc();
    $stmt->close();
    $conn->close();

    return $mantelzorger;
}
