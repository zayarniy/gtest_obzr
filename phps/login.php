<?php
header('Content-Type: application/json');
session_start();
require_once 'db_connect.php';
require_once 'log.php';

logMessage("Start login attempt");

$data = json_decode(file_get_contents('php://input'), true);
$login = trim($data['login'] ?? '');
$password = trim($data['password'] ?? '');

logMessage("Received login: '$login', password length: " . strlen($password));

if (empty($login) || empty($password)) {
    echo json_encode(['status' => 'error', 'message' => 'Заполните все поля.']);
    logMessage("Empty login or password", "Error");
    exit;
}

try {
    $pdo = getDbConnection();
    $stmt = $pdo->prepare("SELECT id, login, password FROM user_info WHERE login = ?");
    $stmt->execute([$login]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        logMessage("User found in DB. Checking password...");
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_login'] = $user['login'];
            echo json_encode(['status' => 'success', 'message' => 'Вход выполнен.']);
            logMessage("Login successful for user: $login");
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Неверный логин или пароль.']);
            logMessage("Wrong password for user: $login", "Error");
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Неверный логин или пароль.']);
        logMessage("User not found: $login", "Error");
    }
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Ошибка сервера.']);
    logMessage("Database error: " . $e->getMessage(), "Error");
}
?>
