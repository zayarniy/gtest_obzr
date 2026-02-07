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

// Получаем полное ФИО пользователя
try {
    $pdo = getDbConnection();
    $stmt = $pdo->prepare("SELECT lastname, name, surname FROM user_info WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        // Формируем полное ФИО
        $fullName = trim($user['lastname'] . ' ' . $user['name'] . ' ' . ($user['surname'] ?? ''));
    } else {
        $fullName = $userLogin;
    }
} catch (Exception $e) {
    $fullName = $userLogin;
}

// Заголовок страницы
$pageTitle = "Результаты тестов для $fullName";
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <!-- Подключаем Bootstrap для стилей -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Подключаем Font Awesome для иконок -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
        .user-info-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
        }
        .badge-success { background-color: #198754; }
        .badge-warning { background-color: #ffc107; color: #000; }
        .badge-danger { background-color: #dc3545; }
        .badge-info { background-color: #0dcaf0; }
        .details-btn {
            padding: 3px 8px;
            font-size: 0.8rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Карточка с информацией о пользователе -->
        <div class="user-info-card">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h2 class="mb-2">
                        <i class="fas fa-user-graduate me-2"></i>
                        <?php echo htmlspecialchars($fullName); ?>
                    </h2>
                    <p class="mb-1">
                        <i class="fas fa-user me-2"></i>Логин: <?php echo htmlspecialchars($userLogin); ?>
                    </p>
                    <p class="mb-0">
                        <i class="fas fa-calendar-alt me-2"></i>
                        <?php echo date('d.m.Y'); ?> | 
                        <i class="fas fa-clock me-1"></i>
                        <?php echo date('H:i'); ?>
                    </p>
                </div>
                <div class="col-md-4 text-end">
                    <div class="stats-badge">
                        <h4 class="mb-0">
                            <i class="fas fa-chart-bar me-2"></i>Мои результаты
                        </h4>
                    </div>
                </div>
            </div>
        </div>

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
                    tr.time_used,
                    tr.time_limit,
                    ui.lastname,
                    ui.name,
                    ui.surname
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
                echo '
                <div class="alert alert-info text-center">
                    <i class="fas fa-info-circle fa-2x mb-3"></i>
                    <h4>У вас пока нет результатов тестов</h4>
                    <p class="mb-0">Пройти тесты можно на главной странице</p>
                </div>';
            } else {
                // Статистика
                $totalTests = count($testResults);
                $totalCorrect = 0;
                $totalQuestions = 0;
                
                foreach ($testResults as $test) {
                    $totalCorrect += $test['correct_answers'];
                    $totalQuestions += $test['total_questions'];
                }
                
                $overallPercentage = $totalQuestions > 0 ? round(($totalCorrect / $totalQuestions) * 100, 1) : 0;
                
                echo '
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <h6 class="card-subtitle mb-2 text-muted">Всего тестов</h6>
                                <h2 class="card-title text-primary">' . $totalTests . '</h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <h6 class="card-subtitle mb-2 text-muted">Всего вопросов</h6>
                                <h2 class="card-title text-info">' . $totalQuestions . '</h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <h6 class="card-subtitle mb-2 text-muted">Правильных ответов</h6>
                                <h2 class="card-title text-success">' . $totalCorrect . '</h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <h6 class="card-subtitle mb-2 text-muted">Общий результат</h6>
                                <h2 class="card-title text-warning">' . $overallPercentage . '%</h2>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th>#</th>
                                <th>Название теста</th>
                                <th>ФИО</th>
                                <th>Правильно</th>
                                <th>Всего</th>
                                <th>Результат</th>
                                <th>Время</th>
                                <th>Дата</th>
                                <th>Детали</th>
                            </tr>
                        </thead>
                        <tbody>
                ';

                // Перебираем результаты и выводим их в таблице
                foreach ($testResults as $index => $test) {
                    $percent = round(($test['correct_answers'] / $test['total_questions']) * 100);
                    $date = date("d.m.Y H:i", strtotime($test['date']));
                    
                    // Определяем цвет баджа для результата
                    $badgeClass = 'badge-danger';
                    if ($percent >= 75) {
                        $badgeClass = 'badge-success';
                    } elseif ($percent >= 60) {
                        $badgeClass = 'badge-warning';
                    }
                    
                    // Формируем полное ФИО для строки
                    $userFullName = trim($test['lastname'] . ' ' . $test['name'] . ' ' . ($test['surname'] ?? ''));
                    
                    // Форматируем время
                    $timeInfo = '';
                    if ($test['time_used'] > 0) {
                        $timeUsed = gmdate("i:s", $test['time_used']);
                        if ($test['time_limit'] > 0) {
                            $timeLimit = gmdate("i:s", $test['time_limit']);
                            $timeInfo = $timeUsed . '/' . $timeLimit;
                        } else {
                            $timeInfo = $timeUsed;
                        }
                    }

                    echo "
                        <tr>
                            <td>" . ($index + 1) . "</td>
                            <td><strong>" . htmlspecialchars($test['test_name']) . "</strong></td>
                            <td>" . htmlspecialchars($userFullName) . "</td>
                            <td><strong>" . $test['correct_answers'] . "</strong></td>
                            <td>" . $test['total_questions'] . "</td>
                            <td>
                                <span class='badge " . $badgeClass . "'>
                                    " . $percent . "%
                                </span>
                            </td>
                            <td><small>" . $timeInfo . "</small></td>
                            <td><small>" . $date . "</small></td>
                            <td>
                                <button class='btn btn-sm btn-outline-info details-btn' onclick='showAttemptDetails(" . $test['id'] . ")'>
                                    <i class='fas fa-eye'></i>
                                </button>
                            </td>
                        </tr>
                    ";
                }

                echo '
                        </tbody>
                    </table>
                </div>
                
                <div class="mt-3 text-muted">
                    <i class="fas fa-database me-2"></i>Всего записей: ' . $totalTests . '
                </div>
                ';
            }

        } catch (PDOException $e) {
            // Выводим сообщение об ошибке
            echo '
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle me-2"></i>
                Ошибка при получении результатов тестов: ' . htmlspecialchars($e->getMessage()) . '
            </div>
            ';
        }
        ?>

        <!-- Кнопка для возврата на главную страницу -->
        <div class="mt-4">
            <a href="../index.php" class="btn btn-primary">
                <i class="fas fa-home me-2"></i>Вернуться на главную
            </a>
            <button onclick="window.print()" class="btn btn-outline-secondary ms-2">
                <i class="fas fa-print me-2"></i>Печать
            </button>
        </div>
    </div>

    <!-- Модальное окно для деталей попытки -->
    <div class="modal fade" id="attemptDetailsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-file-alt me-2"></i>Детали попытки
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
                </div>
                <div class="modal-body p-0" id="attemptDetailsContent">
                    <!-- Контент будет загружен через AJAX -->
                    <div class="text-center py-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Загрузка...</span>
                        </div>
                        <p class="mt-2">Загрузка деталей попытки...</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>Закрыть
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Подключаем Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    // Функция для показа деталей попытки
    function showAttemptDetails(attemptId) {
        // Показываем модальное окно
        const modal = new bootstrap.Modal(document.getElementById('attemptDetailsModal'));
        modal.show();
        
        // Загружаем данные
        fetch('get_attempt_details.php?id=' + attemptId)
            .then(response => response.text())
            .then(data => {
                document.getElementById('attemptDetailsContent').innerHTML = data;
            })
            .catch(error => {
                document.getElementById('attemptDetailsContent').innerHTML = 
                    '<div class="alert alert-danger m-4">Ошибка загрузки данных: ' + error.message + '</div>';
            });
    }
    
    // Автоматическая сортировка таблицы по дате (новые сверху)
    document.addEventListener('DOMContentLoaded', function() {
        const table = document.querySelector('table');
        if (table) {
            const tbody = table.querySelector('tbody');
            const rows = Array.from(tbody.querySelectorAll('tr'));
            
            // Таблица уже отсортирована в PHP по дате DESC
            // Можно добавить кликабельные заголовки для сортировки
            const headers = table.querySelectorAll('thead th');
            headers.forEach((header, index) => {
                header.style.cursor = 'pointer';
                header.addEventListener('click', () => {
                    sortTable(index);
                });
            });
        }
    });
    
    // Функция сортировки таблицы
    function sortTable(columnIndex) {
        const table = document.querySelector('table');
        const tbody = table.querySelector('tbody');
        const rows = Array.from(tbody.querySelectorAll('tr'));
        
        rows.sort((a, b) => {
            const aText = a.children[columnIndex].textContent.trim();
            const bText = b.children[columnIndex].textContent.trim();
            
            // Пытаемся сравнить как числа
            const aNum = parseFloat(aText);
            const bNum = parseFloat(bText);
            
            if (!isNaN(aNum) && !isNaN(bNum)) {
                return aNum - bNum;
            }
            
            // Иначе сравниваем как строки
            return aText.localeCompare(bText, 'ru');
        });
        
        // Перезаписываем строки в таблице
        rows.forEach(row => tbody.appendChild(row));
    }
    </script>
</body>
</html>