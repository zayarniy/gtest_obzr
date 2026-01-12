<?php
session_start();
require_once 'log.php';

header('Content-Type: application/json');

// Проверяем, авторизован ли пользователь
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'expired']);
    exit;
}

// Проверяем время бездействия (1 час = 3600 секунд)
$inactive = 3600;
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $inactive)) {
    // Сессия истекла
    session_unset();
    session_destroy();
    echo json_encode(['status' => 'expired']);
    logMessage("Session expired for user: " . ($_SESSION['user_login'] ?? 'unknown'));
} else {
    // Обновляем время активности
    $_SESSION['last_activity'] = time();
    echo json_encode(['status' => 'active']);
}
?>