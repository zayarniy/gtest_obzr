<?php
header('Content-Type: application/json');
session_start();
require_once 'db_connect.php';
require_once 'log.php';
logMessage("get_user_data.php started");

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Пользователь не авторизован.']);
    exit;
}

try {
    $pdo = getDbConnection();    
    $stmt = $pdo->prepare("SELECT lastname, name, surname, email, telegram FROM user_info WHERE id = ?");
    logMessage($_SESSION['user_id']);
    $stmt->execute([$_SESSION['user_id']]);

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        echo json_encode(array_merge(['status' => 'success'], $user));
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Пользователь не найден.']);
    }
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Ошибка сервера.']);
}
?>
