<?php
// Запускаем сессию
session_start();

// Проверяем, авторизован ли пользователь как администратор
if (!isset($_SESSION['user_login']) || $_SESSION['user_login'] !== 'admin') {
    header("Location: ../registration.html");
    exit();
}

// Подключаемся к базе данных
require_once 'db_connect.php';

// Заголовок страницы
$pageTitle = "Статистика тестов пользователей";

// Получаем параметры фильтрации
$selected_user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
$selected_test_name = isset($_GET['test_name_single']) ? trim($_GET['test_name_single']) : '';
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
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <style>
        body {
            padding: 20px;
            background-color: #f8f9fa;
        }
        .container {
            max-width: 1600px;
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
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
        }
        .sortable {
            cursor: pointer;
            user-select: none;
        }
        .sortable:hover {
            background-color: #e9ecef;
        }
        .sort-icon {
            margin-left: 5px;
            font-size: 0.8em;
        }
        .badge-success {
            background-color: #198754;
        }
        .badge-warning {
            background-color: #ffc107;
            color: #000;
        }
        .badge-danger {
            background-color: #dc3545;
        }
        .badge-info {
            background-color: #0dcaf0;
        }
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
        }
        .stats-number {
            font-size: 2rem;
            font-weight: bold;
        }
        .statistics-panel {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 30px;
        }
        .stat-item {
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e9ecef;
        }
        .stat-item:last-child {
            border-bottom: none;
        }
        .stat-label {
            font-weight: 600;
            color: #495057;
        }
        .stat-value {
            font-size: 1.1rem;
            color: #212529;
        }
        .select2-container {
            width: 100% !important;
        }
        .tab-content {
            margin-top: 20px;
        }
        .nav-tabs {
            margin-bottom: 20px;
        }
        .detailed-statistics {
            background-color: #fff;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 0 5px rgba(0,0,0,0.05);
        }
        .progress {
            height: 25px;
        }
        .chart-container {
            height: 300px;
            margin-bottom: 30px;
        }
        .test-details-card {
            border-left: 4px solid #0d6efd;
            background-color: #f8f9fa;
        }
        .user-details-card {
            border-left: 4px solid #198754;
            background-color: #f8f9fa;
        }
        .attempts-table {
            font-size: 0.9rem;
        }
        .attempt-row:hover {
            background-color: #f8f9fa;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1><i class="fas fa-chart-bar me-2"></i><?php echo htmlspecialchars($pageTitle); ?></h1>

        <!-- Навигационные вкладки -->
        <ul class="nav nav-tabs" id="statsTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="detailed-tab" data-bs-toggle="tab" data-bs-target="#detailed" type="button" role="tab">
                    <i class="fas fa-chart-line me-2"></i>Детальная статистика
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="all-results-tab" data-bs-toggle="tab" data-bs-target="#all-results" type="button" role="tab">
                    <i class="fas fa-list me-2"></i>Все результаты
                </button>
            </li>
        </ul>

        <div class="tab-content" id="statsTabsContent">
            <!-- Вкладка с детальной статистикой -->
            <div class="tab-pane fade show active" id="detailed" role="tabpanel">
                <!-- Форма для выбора пользователя и теста -->
                <div class="statistics-panel">
                    <h4><i class="fas fa-filter me-2"></i>Выбор параметров для статистики</h4>
                    <form method="GET" action="all_test_results.php" id="detailedStatsForm">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Выберите пользователя</label>
                                <select name="user_id" class="form-select select2-user" id="userSelect">
                                    <option value="">Все пользователи</option>
                                    <?php
                                    try {
                                        $pdo = getDbConnection();
                                        $usersSql = "SELECT id, login, name, lastname FROM user_info ORDER BY lastname, name";
                                        $usersStmt = $pdo->query($usersSql);
                                        $users = $usersStmt->fetchAll(PDO::FETCH_ASSOC);
                                        
                                        foreach ($users as $user) {
                                            $selected = ($selected_user_id == $user['id']) ? 'selected' : '';
                                            $displayName = htmlspecialchars($user['lastname'] . ' ' . $user['name'] . ' (' . $user['login'] . ')');
                                            echo "<option value='{$user['id']}' {$selected}>{$displayName}</option>";
                                        }
                                    } catch (PDOException $e) {
                                        echo '<option value="">Ошибка загрузки пользователей</option>';
                                    }
                                    ?>
                                </select>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">Выберите тест</label>
                                <select name="test_name_single" class="form-select select2-test" id="testSelect">
                                    <option value="">Все тесты</option>
                                    <?php
                                    try {
                                        $testsSql = "SELECT DISTINCT test_name FROM test_result ORDER BY test_name";
                                        $testsStmt = $pdo->query($testsSql);
                                        $tests = $testsStmt->fetchAll(PDO::FETCH_COLUMN);
                                        
                                        foreach ($tests as $test) {
                                            $selected = ($selected_test_name == $test) ? 'selected' : '';
                                            echo "<option value='" . htmlspecialchars($test) . "' {$selected}>" . htmlspecialchars($test) . "</option>";
                                        }
                                    } catch (PDOException $e) {
                                        echo '<option value="">Ошибка загрузки тестов</option>';
                                    }
                                    ?>
                                </select>
                            </div>
                            
                            <div class="col-md-12">
                                <div class="d-flex justify-content-between mt-3">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-chart-bar me-2"></i>Показать статистику
                                    </button>
                                    <a href="all_test_results.php" class="btn btn-outline-secondary">
                                        <i class="fas fa-times me-2"></i>Сбросить фильтры
                                    </a>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>

                <?php
                // Отображаем статистику только если есть выбранные параметры
                if ($selected_user_id > 0 || !empty($selected_test_name)) {
                    try {
                        $pdo = getDbConnection();
                        
                        // Формируем условия WHERE
                        $whereConditions = [];
                        $params = [];
                        
                        if ($selected_user_id > 0) {
                            $whereConditions[] = "tr.user_id = ?";
                            $params[] = $selected_user_id;
                        }
                        
                        if (!empty($selected_test_name)) {
                            $whereConditions[] = "tr.test_name = ?";
                            $params[] = $selected_test_name;
                        }
                        
                        $whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";
                        
                        // Получаем статистику
                        $statsSql = "
                            SELECT 
                                COUNT(*) as attempts,
                                MIN(tr.date) as first_attempt,
                                MAX(tr.date) as last_attempt,
                                MIN(tr.correct_answers * 100.0 / tr.total_questions) as min_score_percent,
                                MAX(tr.correct_answers * 100.0 / tr.total_questions) as max_score_percent,
                                AVG(tr.correct_answers * 100.0 / tr.total_questions) as avg_score_percent,
                                MIN(tr.correct_answers) as min_correct,
                                MAX(tr.correct_answers) as max_correct,
                                AVG(tr.correct_answers) as avg_correct,
                                MIN(tr.total_questions) as min_total,
                                MAX(tr.total_questions) as max_total,
                                COUNT(DISTINCT tr.user_id) as unique_users,
                                COUNT(DISTINCT tr.test_name) as unique_tests,
                                GROUP_CONCAT(DISTINCT tr.test_name ORDER BY tr.test_name SEPARATOR ', ') as test_names_list,
                                GROUP_CONCAT(DISTINCT CONCAT(ui.lastname, ' ', ui.name) ORDER BY ui.lastname, ui.name SEPARATOR ', ') as users_list
                            FROM test_result tr
                            LEFT JOIN user_info ui ON tr.user_id = ui.id
                            {$whereClause}
                        ";
                        
                        $statsStmt = $pdo->prepare($statsSql);
                        $statsStmt->execute($params);
                        $detailedStats = $statsStmt->fetch(PDO::FETCH_ASSOC);
                        
                        // Получаем информацию о пользователе, если выбран
                        $userInfo = null;
                        if ($selected_user_id > 0) {
                            $userSql = "SELECT id, login, name, lastname, email, class, birthdate FROM user_info WHERE id = ?";
                            $userStmt = $pdo->prepare($userSql);
                            $userStmt->execute([$selected_user_id]);
                            $userInfo = $userStmt->fetch(PDO::FETCH_ASSOC);
                        }
                        
                        // Получаем все попытки для детального просмотра
                        $attemptsSql = "
                            SELECT 
                                tr.*,
                                ui.login as user_login,
                                ui.name as user_name,
                                ui.lastname as user_lastname,
                                ROUND((tr.correct_answers * 100.0 / tr.total_questions), 1) as percentage
                            FROM test_result tr
                            LEFT JOIN user_info ui ON tr.user_id = ui.id
                            {$whereClause}
                            ORDER BY tr.date DESC
                        ";
                        
                        $attemptsStmt = $pdo->prepare($attemptsSql);
                        $attemptsStmt->execute($params);
                        $attempts = $attemptsStmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        if ($detailedStats && $detailedStats['attempts'] > 0) {
                            // Форматируем даты
                            $firstAttempt = $detailedStats['first_attempt'] ? date("d.m.Y H:i", strtotime($detailedStats['first_attempt'])) : 'Нет данных';
                            $lastAttempt = $detailedStats['last_attempt'] ? date("d.m.Y H:i", strtotime($detailedStats['last_attempt'])) : 'Нет данных';
                            
                            // Форматируем проценты
                            $minScore = round($detailedStats['min_score_percent'], 1);
                            $maxScore = round($detailedStats['max_score_percent'], 1);
                            $avgScore = round($detailedStats['avg_score_percent'], 1);
                            
                            // Определяем цвета для оценок
                            function getScoreColor($score) {
                                if ($score >= 90) return 'success';
                                if ($score >= 75) return 'primary';
                                if ($score >= 60) return 'warning';
                                return 'danger';
                            }
                            ?>
                            
                            <!-- Панель с общей статистикой -->
                            <div class="detailed-statistics mt-4">
                                <h4 class="mb-4">
                                    <i class="fas fa-chart-pie me-2"></i>
                                    Общая статистика
                                    <?php if ($userInfo): ?>
                                        для пользователя: <span class="text-primary"><?php echo htmlspecialchars($userInfo['lastname'] . ' ' . $userInfo['name']); ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($selected_test_name)): ?>
                                        по тесту: <span class="text-primary"><?php echo htmlspecialchars($selected_test_name); ?></span>
                                    <?php endif; ?>
                                </h4>
                                
                                <div class="row">
                                    <!-- Левая колонка - основные метрики -->
                                    <div class="col-md-8">
                                        <div class="row">
                                            <div class="col-md-4 mb-3">
                                                <div class="card bg-light">
                                                    <div class="card-body text-center">
                                                        <h6 class="card-subtitle mb-2 text-muted">Количество попыток</h6>
                                                        <h2 class="card-title text-primary"><?php echo $detailedStats['attempts']; ?></h2>
                                                        <p class="card-text small">
                                                            <?php echo $detailedStats['unique_users'] > 1 ? 'Уникальных пользователей: ' . $detailedStats['unique_users'] : ''; ?>
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="col-md-4 mb-3">
                                                <div class="card bg-light">
                                                    <div class="card-body text-center">
                                                        <h6 class="card-subtitle mb-2 text-muted">Средний балл</h6>
                                                        <h2 class="card-title text-<?php echo getScoreColor($avgScore); ?>">
                                                            <?php echo $avgScore; ?>%
                                                        </h2>
                                                        <p class="card-text small">
                                                            Правильных: <?php echo round($detailedStats['avg_correct'], 1); ?>/<?php echo round($detailedStats['avg_total'], 1); ?>
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="col-md-4 mb-3">
                                                <div class="card bg-light">
                                                    <div class="card-body text-center">
                                                        <h6 class="card-subtitle mb-2 text-muted">Лучший результат</h6>
                                                        <h2 class="card-title text-success"><?php echo $maxScore; ?>%</h2>
                                                        <p class="card-text small">
                                                            Правильных: <?php echo $detailedStats['max_correct']; ?>/<?php echo $detailedStats['max_total']; ?>
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="col-md-6 mb-3">
                                                <div class="card bg-light">
                                                    <div class="card-body">
                                                        <h6 class="card-subtitle mb-2 text-muted">
                                                            <i class="fas fa-calendar-plus me-1"></i>Первая попытка
                                                        </h6>
                                                        <p class="card-text h5"><?php echo $firstAttempt; ?></p>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="col-md-6 mb-3">
                                                <div class="card bg-light">
                                                    <div class="card-body">
                                                        <h6 class="card-subtitle mb-2 text-muted">
                                                            <i class="fas fa-calendar-check me-1"></i>Последняя попытка
                                                        </h6>
                                                        <p class="card-text h5"><?php echo $lastAttempt; ?></p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Прогресс-бар для диапазона результатов -->
                                        <div class="mt-4">
                                            <h6>Диапазон результатов:</h6>
                                            <div class="d-flex align-items-center mb-2">
                                                <small class="me-2">Мин: <?php echo $minScore; ?>%</small>
                                                <div class="progress flex-grow-1">
                                                    <div class="progress-bar bg-danger" role="progressbar" 
                                                         style="width: <?php echo ($minScore / 100) * 100; ?>%">
                                                    </div>
                                                </div>
                                                <small class="ms-2">Макс: <?php echo $maxScore; ?>%</small>
                                            </div>
                                            <div class="text-center text-muted small">
                                                Средний: <?php echo $avgScore; ?>%
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Правая колонка - детальная информация -->
                                    <div class="col-md-4">
                                        <div class="card test-details-card">
                                            <div class="card-body">
                                                <h6 class="card-title">
                                                    <i class="fas fa-info-circle me-2"></i>Детальная информация
                                                </h6>
                                                
                                                <div class="stat-item">
                                                    <div class="stat-label">Минимальный балл:</div>
                                                    <div class="stat-value">
                                                        <span class="badge bg-danger"><?php echo $minScore; ?>%</span>
                                                        (<?php echo $detailedStats['min_correct']; ?>/<?php echo $detailedStats['min_total']; ?>)
                                                    </div>
                                                </div>
                                                
                                                <div class="stat-item">
                                                    <div class="stat-label">Максимальный балл:</div>
                                                    <div class="stat-value">
                                                        <span class="badge bg-success"><?php echo $maxScore; ?>%</span>
                                                        (<?php echo $detailedStats['max_correct']; ?>/<?php echo $detailedStats['max_total']; ?>)
                                                    </div>
                                                </div>
                                                
                                                <div class="stat-item">
                                                    <div class="stat-label">Средний балл:</div>
                                                    <div class="stat-value">
                                                        <span class="badge bg-<?php echo getScoreColor($avgScore); ?>"><?php echo $avgScore; ?>%</span>
                                                        (<?php echo round($detailedStats['avg_correct'], 1); ?>/<?php echo round($detailedStats['avg_total'], 1); ?>)
                                                    </div>
                                                </div>
                                                
                                                <?php if ($detailedStats['unique_tests'] > 1): ?>
                                                <div class="stat-item">
                                                    <div class="stat-label">Уникальных тестов:</div>
                                                    <div class="stat-value">
                                                        <span class="badge bg-info"><?php echo $detailedStats['unique_tests']; ?></span>
                                                    </div>
                                                </div>
                                                <?php endif; ?>
                                                
                                                <?php if (!empty($detailedStats['test_names_list']) && $detailedStats['unique_tests'] <= 5): ?>
                                                <div class="stat-item">
                                                    <div class="stat-label">Тесты:</div>
                                                    <div class="stat-value small">
                                                        <?php echo htmlspecialchars($detailedStats['test_names_list']); ?>
                                                    </div>
                                                </div>
                                                <?php endif; ?>
                                                
                                                <?php if (!empty($detailedStats['users_list']) && $detailedStats['unique_users'] <= 3): ?>
                                                <div class="stat-item">
                                                    <div class="stat-label">Пользователи:</div>
                                                    <div class="stat-value small">
                                                        <?php echo htmlspecialchars($detailedStats['users_list']); ?>
                                                    </div>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        
                                        <?php if ($userInfo): ?>
                                        <div class="card user-details-card mt-3">
                                            <div class="card-body">
                                                <h6 class="card-title">
                                                    <i class="fas fa-user me-2"></i>Информация о пользователе
                                                </h6>
                                                <p class="mb-1"><strong>Логин:</strong> <?php echo htmlspecialchars($userInfo['login']); ?></p>
                                                <p class="mb-1"><strong>ФИО:</strong> <?php echo htmlspecialchars($userInfo['lastname'] . ' ' . $userInfo['name']); ?></p>
                                                <p class="mb-1"><strong>Email:</strong> <?php echo htmlspecialchars($userInfo['email']); ?></p>
                                                <p class="mb-1"><strong>Класс:</strong> <?php echo htmlspecialchars($userInfo['class']); ?></p>
                                                <?php if (!empty($userInfo['birthdate'])): ?>
                                                <p class="mb-0"><strong>Дата рождения:</strong> <?php echo date("d.m.Y", strtotime($userInfo['birthdate'])); ?></p>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <!-- Таблица с попытками -->
                                <div class="mt-5">
                                    <h5><i class="fas fa-history me-2"></i>История попыток</h5>
                                    <div class="table-responsive">
                                        <table class="table table-sm table-hover attempts-table">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>#</th>
                                                    <th>Дата</th>
                                                    <?php if (!$selected_user_id): ?><th>Пользователь</th><?php endif; ?>
                                                    <?php if (empty($selected_test_name)): ?><th>Тест</th><?php endif; ?>
                                                    <th>Правильно</th>
                                                    <th>Всего</th>
                                                    <th>Результат</th>
                                                    <th>Действия</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($attempts as $index => $attempt): ?>
                                                <?php
                                                $attemptPercent = round(($attempt['correct_answers'] / $attempt['total_questions']) * 100);
                                                $attemptDate = date("d.m.Y H:i", strtotime($attempt['date']));
                                                $badgeClass = 'badge-danger';
                                                if ($attemptPercent >= 75) $badgeClass = 'badge-success';
                                                elseif ($attemptPercent >= 60) $badgeClass = 'badge-warning';
                                                ?>
                                                <tr class="attempt-row">
                                                    <td><?php echo $index + 1; ?></td>
                                                    <td><?php echo $attemptDate; ?></td>
                                                    <?php if (!$selected_user_id): ?>
                                                    <td>
                                                        <?php echo htmlspecialchars($attempt['user_lastname'] . ' ' . $attempt['user_name']); ?>
                                                        <br><small class="text-muted"><?php echo htmlspecialchars($attempt['user_login']); ?></small>
                                                    </td>
                                                    <?php endif; ?>
                                                    <?php if (empty($selected_test_name)): ?>
                                                    <td><?php echo htmlspecialchars($attempt['test_name']); ?></td>
                                                    <?php endif; ?>
                                                    <td><strong><?php echo $attempt['correct_answers']; ?></strong></td>
                                                    <td><?php echo $attempt['total_questions']; ?></td>
                                                    <td>
                                                        <span class="badge <?php echo $badgeClass; ?>">
                                                            <?php echo $attemptPercent; ?>%
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <button class="btn btn-sm btn-outline-info" onclick="showAttemptDetails(<?php echo $attempt['id']; ?>)">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            
                            <?php
                        } else {
                            echo '<div class="alert alert-warning mt-4">Нет данных для отображения статистики по выбранным критериям.</div>';
                        }
                        
                    } catch (PDOException $e) {
                        echo '<div class="alert alert-danger mt-4">Ошибка при получении статистики: ' . htmlspecialchars($e->getMessage()) . '</div>';
                    }
                } else {
                    echo '<div class="alert alert-info mt-4">Выберите пользователя и/или тест для просмотра статистики.</div>';
                }
                ?>
            </div>

            <!-- Вкладка со всеми результатами (существующий код) -->
            <div class="tab-pane fade" id="all-results" role="tabpanel">
                <!-- Существующий код со всеми результатами -->
                <!-- ... (весь ваш существующий код с фильтрами и таблицей) ... -->
                
                <?php
                // Вставьте сюда весь существующий код, начиная с формы фильтрации и заканчивая таблицей
                // Я сохранил его в отдельной переменной для читаемости
                include 'all_results_partial.php'; // или просто скопируйте весь существующий код сюда
                ?>
            </div>
        </div>

        <!-- Модальное окно для деталей попытки -->
        <div class="modal fade" id="attemptDetailsModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Детали попытки</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
                    </div>
                    <div class="modal-body" id="attemptDetailsContent">
                        <!-- Контент будет загружен через AJAX -->
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрыть</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Кнопка для возврата на главную страницу -->
        <div class="mt-4">
            <a href="../index.php" class="btn btn-primary">
                <i class="fas fa-home me-2"></i>Вернуться на главную
            </a>
            <button onclick="window.print()" class="btn btn-outline-secondary ms-2">
                <i class="fas fa-print me-2"></i>Печать статистики
            </button>
        </div>
    </div>

    <!-- Подключаем Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Подключаем Select2 -->
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <!-- Подключаем Chart.js для графиков -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <script>
        // Инициализация Select2
        $(document).ready(function() {
            $('.select2-user').select2({
                placeholder: "Выберите пользователя",
                allowClear: true,
                width: 'resolve'
            });
            
            $('.select2-test').select2({
                placeholder: "Выберите тест",
                allowClear: true,
                width: 'resolve'
            });
        });
        
        // Функция для показа деталей попытки
        function showAttemptDetails(attemptId) {
            fetch('get_attempt_details.php?id=' + attemptId)
                .then(response => response.text())
                .then(data => {
                    document.getElementById('attemptDetailsContent').innerHTML = data;
                    const modal = new bootstrap.Modal(document.getElementById('attemptDetailsModal'));
                    modal.show();
                })
                .catch(error => {
                    document.getElementById('attemptDetailsContent').innerHTML = 
                        '<div class="alert alert-danger">Ошибка загрузки данных</div>';
                    const modal = new bootstrap.Modal(document.getElementById('attemptDetailsModal'));
                    modal.show();
                });
        }
        
        // Создание графика (если нужно)
        function createStatsChart() {
            const ctx = document.getElementById('statsChart');
            if (!ctx) return;
            
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: ['Мин', 'Сред', 'Макс'],
                    datasets: [{
                        label: 'Результат (%)',
                        data: [
                            <?php echo isset($minScore) ? $minScore : 0; ?>,
                            <?php echo isset($avgScore) ? $avgScore : 0; ?>,
                            <?php echo isset($maxScore) ? $maxScore : 0; ?>
                        ],
                        backgroundColor: [
                            'rgba(255, 99, 132, 0.5)',
                            'rgba(54, 162, 235, 0.5)',
                            'rgba(75, 192, 192, 0.5)'
                        ],
                        borderColor: [
                            'rgb(255, 99, 132)',
                            'rgb(54, 162, 235)',
                            'rgb(75, 192, 192)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100,
                            title: {
                                display: true,
                                text: 'Процент правильных ответов'
                            }
                        }
                    }
                }
            });
        }
        
        // Инициализация графика при загрузке страницы
        document.addEventListener('DOMContentLoaded', function() {
            createStatsChart();
            
            // Переключение вкладок с сохранением состояния
            const hash = window.location.hash;
            if (hash) {
                const tab = document.querySelector(`a[href="${hash}"]`);
                if (tab) {
                    tab.click();
                }
            }
            
            // Обработчик изменения вкладок
            document.querySelectorAll('button[data-bs-toggle="tab"]').forEach(tab => {
                tab.addEventListener('shown.bs.tab', function (event) {
                    window.location.hash = event.target.getAttribute('data-bs-target');
                });
            });
        });
        
        // Экспорт данных
        function exportStatsToCSV() {
            let csv = [
                ['Параметр', 'Значение'],
                ['Пользователь', '<?php echo $userInfo ? htmlspecialchars($userInfo["lastname"] . " " . $userInfo["name"]) : "Все пользователи"; ?>'],
                ['Тест', '<?php echo !empty($selected_test_name) ? htmlspecialchars($selected_test_name) : "Все тесты"; ?>'],
                ['Количество попыток', '<?php echo isset($detailedStats["attempts"]) ? $detailedStats["attempts"] : 0; ?>'],
                ['Средний балл (%)', '<?php echo isset($avgScore) ? $avgScore : 0; ?>'],
                ['Минимальный балл (%)', '<?php echo isset($minScore) ? $minScore : 0; ?>'],
                ['Максимальный балл (%)', '<?php echo isset($maxScore) ? $maxScore : 0; ?>'],
                ['Первая попытка', '<?php echo isset($firstAttempt) ? $firstAttempt : ""; ?>'],
                ['Последняя попытка', '<?php echo isset($lastAttempt) ? $lastAttempt : ""; ?>']
            ];
            
            const csvContent = csv.map(row => 
                row.map(cell => `"${cell}"`).join(',')
            ).join('\n');
            
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            const url = URL.createObjectURL(blob);
            
            link.setAttribute('href', url);
            link.setAttribute('download', `статистика_${new Date().toISOString().slice(0,10)}.csv`);
            link.style.visibility = 'hidden';
            
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
    </script>
</body>
</html>