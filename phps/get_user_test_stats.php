<?php
session_start();
require_once 'db_connect.php';

// Проверяем авторизацию
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Не авторизован']);
    exit();
}

// Получаем user_id из POST запроса или сессии
$user_id = isset($_POST['user_id']) ? $_POST['user_id'] : $_SESSION['user_id'];

try {
    $pdo = getDbConnection();
    
    // Получаем статистику по тестам пользователя
    $sql = "
        SELECT 
            test_name,
            COUNT(*) as attempts,
            MIN(date) as first_attempt,
            MAX(date) as last_attempt,
            MIN((correct_answers * 100.0 / total_questions)) as min_score,
            MAX((correct_answers * 100.0 / total_questions)) as max_score,
            AVG((correct_answers * 100.0 / total_questions)) as avg_score
        FROM test_result 
        WHERE user_id = ?
        GROUP BY test_name
        ORDER BY test_name
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Формируем массив статистики
    $stats = [];
    foreach ($results as $row) {
        $stats[$row['test_name']] = [
            'attempts' => (int)$row['attempts'],
            'first_attempt' => $row['first_attempt'],
            'last_attempt' => $row['last_attempt'],
            'min_score' => round((float)$row['min_score'], 1),
            'max_score' => round((float)$row['max_score'], 1),
            'avg_score' => round((float)$row['avg_score'], 1)
        ];
    }
    
    echo json_encode([
        'status' => 'success',
        'stats' => $stats
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Ошибка получения статистики: ' . $e->getMessage()
    ]);
}