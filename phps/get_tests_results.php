<?php
// Запускаем сессию
session_start();

// Проверяем, авторизован ли пользователь
if (!isset($_SESSION['user_id'])) {
    header("Location: registration.html");
    exit();
}

// Подключаемся к базе данных
require_once 'db_connect.php';

// Получаем ID пользователя из сессии
$userId = $_SESSION['user_id'];
$userLogin = $_SESSION['user_login'] ?? 'Пользователь';

// Заголовок страницы
$pageTitle = "Результаты тестов для $userLogin";
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <!-- Подключаем Bootstrap для стилей -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            padding: 20px;
            background-color: #f8f9fa;
        }
        .container {
            max-width: 1000px;
            margin: 0 auto;
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        h1 {
            margin-bottom: 20px;
            color: #343a40;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Результаты тестов для <?php echo htmlspecialchars($userLogin); ?></h1>

        <?php
        try {
            // Подключаемся к базе данных
            $pdo = getDbConnection();

            // SQL-запрос с JOIN для получения данных из двух таблиц
            $sql = "
                SELECT
                    tr.id,
                    tr.test_name,
                    tr.correct_answers,
                    tr.total_questions,
                    tr.date,
                    ui.name AS user_name
                FROM
                    test_result tr
                JOIN
                    user_info ui ON tr.user_id = ui.id
                WHERE
                    tr.user_id = ?
                ORDER BY
                    tr.date DESC
            ";

            // Подготавливаем и выполняем запрос
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$userId]);
            $testResults = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Проверяем, есть ли результаты
            if (empty($testResults)) {
                echo '<div class="alert alert-info">У вас пока нет результатов тестов.</div>';
            } else {
                // Выводим таблицу с результатами
                echo '
                <table class="table table-striped table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th>Название теста</th>
                            <th>Имя пользователя</th>
                            <th>Правильные ответы</th>
                            <th>Всего вопросов</th>
                            <th>Результат (%)</th>
                            <th>Дата</th>
                        </tr>
                    </thead>
                    <tbody>
                ';

                // Перебираем результаты и выводим их в таблице
                foreach ($testResults as $test) {
                    $percent = round(($test['correct_answers'] / $test['total_questions']) * 100);
                    $date = date("d.m.Y H:i", strtotime($test['date']));

                    echo "
                        <tr>
                            <td>{$test['test_name']}</td>
                            <td>{$test['user_name']}</td>
                            <td>{$test['correct_answers']}</td>
                            <td>{$test['total_questions']}</td>
                            <td>{$percent}%</td>
                            <td>{$date}</td>
                        </tr>
                    ";
                }

                echo '
                    </tbody>
                </table>
                ';
            }

        } catch (PDOException $e) {
            // Выводим сообщение об ошибке
            echo '
            <div class="alert alert-danger">
                Ошибка при получении результатов тестов: ' . htmlspecialchars($e->getMessage()) . '
            </div>
            ';
        }
        ?>

        <!-- Кнопка для возврата на главную страницу -->
        <div class="mt-4">
            <a href="../index.php" class="btn btn-primary">Вернуться на главную</a>
        </div>
    </div>

    <!-- Подключаем Bootstrap JS (опционально) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
