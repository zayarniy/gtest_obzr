<?php
header('Content-Type: application/json');
session_start();
require_once 'db_connect.php';
require_once 'log.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Пользователь не авторизован.']);
    exit;
}


try {
    $pdo = getDbConnection();
    $pdo->beginTransaction();


    // Удаляем результаты тестов пользователя
    $stmt = $pdo->prepare("DELETE FROM test_result WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);

    // Удаляем пользователя
    $stmt = $pdo->prepare("DELETE FROM user_info WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);

    $pdo->commit();

    // Очищаем сессию
    session_unset();
    session_destroy();

    echo json_encode(['status' => 'success', 'message' => 'Аккаунт удалён.']);
} catch (PDOException $e) {
    $pdo->rollBack();
    echo json_encode(['status' => 'error', 'message' => 'Ошибка сервера.']);
}
?>
