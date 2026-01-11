<?php
header('Content-Type: application/json');
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Пользователь не авторизован.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

$lastname = $data['lastname'] ?? '';
$name = $data['name'] ?? '';
$surname = $data['surname'] ?? '';
$email = $data['email'] ?? '';
$telegram = $data['telegram'] ?? '';
$password = $data['password'] ?? null;

if (empty($lastname) || empty($name) || empty($email)) {
    echo json_encode(['status' => 'error', 'message' => 'Заполните все обязательные поля.']);
    exit;
}

try {
    $pdo = getDbConnection();
    if ($password) {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("
            UPDATE user_info
            SET lastname = ?, name = ?, surname = ?, email = ?, telegram = ?, password = ?
            WHERE id = ?
        ");
        $stmt->execute([$lastname, $name, $surname, $email, $telegram, $hashedPassword, $_SESSION['user_id']]);
    } else {
        $stmt = $pdo->prepare("
            UPDATE user_info
            SET lastname = ?, name = ?, surname = ?, email = ?, telegram = ?
            WHERE id = ?
        ");
        $stmt->execute([$lastname, $name, $surname, $email, $telegram, $_SESSION['user_id']]);
    }

    echo json_encode(['status' => 'success', 'message' => 'Данные успешно обновлены.']);
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Ошибка сервера.']);
}
?>
