<?php
header('Content-Type: application/json');
//session_start();
require_once 'db_connect.php';
require_once 'log.php';

logMessage("Start saving test result");

$data = json_decode(file_get_contents('php://input'), true);

$user_id = $data['user_id'] ?? null;
$test_name = $data['test_name'] ?? '';
$correct_answers = $data['correct_answers'] ?? 0;
$total_questions = $data['total_questions'] ?? 0;
$attempts_left = $data['attempts_left'] ?? 0;

logMessage("Data: user_id=$user_id, test_name=$test_name, correct_answers=$correct_answers, total_questions=$total_questions, attempts_left=$attempts_left");

if (empty($user_id) || empty($test_name)) {
    echo json_encode(['status' => 'error', 'message' => 'Отсутствуют обязательные данные.']);
    logMessage("Missing required data", "Error");
    exit;
}

try {
    $pdo = getDbConnection();
    $stmt = $pdo->prepare("
        INSERT INTO test_result
        (user_id, test_name, correct_answers, total_questions, attempts_left)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([$user_id, $test_name, $correct_answers, $total_questions, $attempts_left]);

    echo json_encode(['status' => 'success', 'message' => 'Результат теста сохранён.']);
    logMessage("Test result saved successfully");
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Ошибка сервера.']);
    logMessage("Database error: " . $e->getMessage(), "Error");
}
?>
