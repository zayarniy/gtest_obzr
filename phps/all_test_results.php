<?php
// Запускаем сессию
session_start();

// Проверяем, авторизован ли пользователь (опционально: только для администраторов)
// Если нужно ограничить доступ, раскомментируйте:
// if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
//     header("Location: registration.html");
//     exit();
// }

// Подключаемся к базе данных
require_once 'db_connect.php';

// Заголовок страницы
$pageTitle = "Результаты тестов всех пользователей";
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
            max-width: 1200px;
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
        .filter-form {
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1><?php echo htmlspecialchars($pageTitle); ?></h1>

        <!-- Форма для фильтрации результатов -->
        <form method="GET" action="all_test_results.php" class="filter-form">
            <div class="row g-3">
                <div class="col-md-4">
                    <input
                        type="text"
                        name="test_name"
                        class="form-control"
                        placeholder="Фильтр по названию теста"
                        value="<?php echo isset($_GET['test_name']) ? htmlspecialchars($_GET['test_name']) : ''; ?>"
                    >
                </div>
                <div class="col-md-3">
                    <input
                        type="text"
                        name="user_name"
                        class="form-control"
                        placeholder="Фильтр по имени пользователя"
                        value="<?php echo isset($_GET['user_name']) ? htmlspecialchars($_GET['user_name']) : ''; ?>"
                    >
                </div>
                <div class="col-md-2">
                    <select name="min_correct" class="form-select">
                        <option value="">Минимум правильных ответов</option>
                        <?php for ($i = 0; $i <= 20; $i++): ?>
                            <option value="<?php echo $i; ?>"
                                <?php echo (isset($_GET['min_correct']) && $_GET['min_correct'] == $i) ? 'selected' : ''; ?>>
                                <?php echo $i; ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">Фильтровать</button>
                </div>
            </div>
        </form>

        <?php
        try {
            // Подключаемся к базе данных
            $pdo = getDbConnection();

            // Базовый SQL-запрос с JOIN для получения данных из двух таблиц
            $sql = "
                SELECT
                    tr.id,
                    tr.test_name,
                    tr.correct_answers,
                    tr.total_questions,
                    tr.date,
                    ui.id AS user_id,
                    ui.login AS user_login,
                    ui.name AS user_name,
                    ui.email AS user_email
                FROM
                    test_result tr
                JOIN
                    user_info ui ON tr.user_id = ui.id
            ";

            // Добавляем фильтры, если они указаны
            $params = [];
            $whereClauses = [];

            if (!empty($_GET['test_name'])) {
                $whereClauses[] = "tr.test_name LIKE ?";
                $params[] = "%" . $_GET['test_name'] . "%";
            }

            if (!empty($_GET['user_name'])) {
                $whereClauses[] = "(ui.name LIKE ? OR ui.login LIKE ?)";
                $params[] = "%" . $_GET['user_name'] . "%";
                $params[] = "%" . $_GET['user_name'] . "%";
            }

            if (!empty($_GET['min_correct'])) {
                $whereClauses[] = "tr.correct_answers >= ?";
                $params[] = (int)$_GET['min_correct'];
            }

            if (!empty($whereClauses)) {
                $sql .= " WHERE " . implode(" AND ", $whereClauses);
            }

            // Добавляем сортировку
            $sql .= " ORDER BY tr.date DESC";

            // Подготавливаем и выполняем запрос
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $testResults = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Проверяем, есть ли результаты
            if (empty($testResults)) {
                echo '<div class="alert alert-info">Нет результатов тестов по заданным фильтрам.</div>';
            } else {
                // Выводим таблицу с результатами
                echo '
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th>ID</th>
                                <th>Логин пользователя</th>
                                <th>Имя пользователя</th>
                                <th>Email</th>
                                <th>Название теста</th>
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
                            <td>{$test['user_id']}</td>
                            <td>{$test['user_login']}</td>
                            <td>{$test['user_name']}</td>
                            <td>{$test['user_email']}</td>
                            <td>{$test['test_name']}</td>
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
                </div>
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
