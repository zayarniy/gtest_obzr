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
$selected_class = isset($_GET['class']) ? trim($_GET['class']) : '';
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
            box-shadow: 0 0 5px rgba(0, 0, 0, 0.05);
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
                <button class="nav-link" id="detailed-tab" data-bs-toggle="tab" data-bs-target="#detailed"
                    type="button" role="tab">
                    <i class="fas fa-chart-line me-2"></i>Детальная статистика
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="all-results-tab" data-bs-toggle="tab" data-bs-target="#all-results"
                    type="button" role="tab">
                    <i class="fas fa-list me-2"></i>Все результаты
                </button>
            </li>
        </ul>

        <div class="tab-content" id="statsTabsContent">
            <!-- Вкладка с детальной статистикой -->
            <div class="tab-pane fade show" id="detailed" role="tabpanel">
                <!-- Форма для выбора пользователя и класса -->
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
                                <label class="form-label">Выберите класс</label>
                                <select name="class" class="form-select" id="classSelect">
                                    <option value="">Все классы</option>
                                    <?php
                                    try {
                                        $classSql = "SELECT DISTINCT class FROM user_info WHERE class IS NOT NULL AND class != '' ORDER BY class";
                                        $classStmt = $pdo->query($classSql);
                                        $classes = $classStmt->fetchAll(PDO::FETCH_COLUMN);

                                        foreach ($classes as $class) {
                                            $selected = ($selected_class == $class) ? 'selected' : '';
                                            echo "<option value='" . htmlspecialchars($class) . "' {$selected}>" . htmlspecialchars($class) . "</option>";
                                        }
                                    } catch (PDOException $e) {
                                        echo '<option value="">Ошибка загрузки классов</option>';
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
                if ($selected_user_id > 0 || !empty($selected_class)) {
                    try {
                        $pdo = getDbConnection();

                        // Формируем условия WHERE
                        $whereConditions = [];
                        $params = [];

                        if ($selected_user_id > 0) {
                            $whereConditions[] = "tr.user_id = ?";
                            $params[] = $selected_user_id;
                        }

                        if (!empty($selected_class)) {
                            $whereConditions[] = "ui.class = ?";
                            $params[] = $selected_class;
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
        AVG(tr.total_questions) as avg_total,
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
                                ui.class as user_class,
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
                            function getScoreColor($score)
                            {
                                if ($score >= 90)
                                    return 'success';
                                if ($score >= 75)
                                    return 'primary';
                                if ($score >= 60)
                                    return 'warning';
                                return 'danger';
                            }
                            ?>

                            <!-- Панель с общей статистикой -->
                            <div class="detailed-statistics mt-4">
                                <h4 class="mb-4">
                                    <i class="fas fa-chart-pie me-2"></i>
                                    Общая статистика
                                    <?php if ($userInfo): ?>
                                        для пользователя: <span
                                            class="text-primary"><?php echo htmlspecialchars($userInfo['lastname'] . ' ' . $userInfo['name']); ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($selected_class)): ?>
                                        по классу: <span class="text-primary"><?php echo htmlspecialchars($selected_class); ?></span>
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
                                                        <h2 class="card-title text-primary">
                                                            <?php echo $detailedStats['attempts']; ?></h2>
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
                                                            Правильных:
                                                            <?php echo round($detailedStats['avg_correct'], 1); ?>/<?php echo round($detailedStats['avg_total'], 1); ?>
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
                                                            Правильных:
                                                            <?php echo $detailedStats['max_correct']; ?>/<?php echo $detailedStats['max_total']; ?>
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
                                                        <span
                                                            class="badge bg-<?php echo getScoreColor($avgScore); ?>"><?php echo $avgScore; ?>%</span>
                                                        (<?php echo round($detailedStats['avg_correct'], 1); ?>/<?php echo round($detailedStats['avg_total'], 1); ?>)
                                                    </div>
                                                </div>

                                                <?php if ($detailedStats['unique_tests'] > 1): ?>
                                                    <div class="stat-item">
                                                        <div class="stat-label">Уникальных тестов:</div>
                                                        <div class="stat-value">
                                                            <span
                                                                class="badge bg-info"><?php echo $detailedStats['unique_tests']; ?></span>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>

                                                <?php if (!empty($selected_class)): ?>
                                                    <div class="stat-item">
                                                        <div class="stat-label">Класс:</div>
                                                        <div class="stat-value">
                                                            <span class="badge bg-info"><?php echo htmlspecialchars($selected_class); ?></span>
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
                                                    <p class="mb-1"><strong>Логин:</strong>
                                                        <?php echo htmlspecialchars($userInfo['login']); ?></p>
                                                    <p class="mb-1"><strong>ФИО:</strong>
                                                        <?php echo htmlspecialchars($userInfo['lastname'] . ' ' . $userInfo['name']); ?>
                                                    </p>
                                                    <p class="mb-1"><strong>Email:</strong>
                                                        <?php echo htmlspecialchars($userInfo['email']); ?></p>
                                                    <p class="mb-1"><strong>Класс:</strong>
                                                        <?php echo htmlspecialchars($userInfo['class']); ?></p>
                                                    <?php if (!empty($userInfo['birthdate'])): ?>
                                                        <p class="mb-0"><strong>Дата рождения:</strong>
                                                            <?php echo date("d.m.Y", strtotime($userInfo['birthdate'])); ?></p>
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
                                                    <?php if (!$selected_user_id): ?>
                                                        <th class="sortable" onclick="sortTable(2)">
                                                            Пользователь
                                                            <span class="sort-icon"><i class="fas fa-sort"></i></span>
                                                        </th>
                                                    <?php endif; ?>
                                                    <th>Тест</th>
                                                    <?php if (empty($selected_class)): ?>
                                                        <th>Класс</th>
                                                    <?php endif; ?>
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
                                                    if ($attemptPercent >= 75)
                                                        $badgeClass = 'badge-success';
                                                    elseif ($attemptPercent >= 60)
                                                        $badgeClass = 'badge-warning';
                                                    ?>
                                                    <tr class="attempt-row">
                                                        <td><?php echo $index + 1; ?></td>
                                                        <td><?php echo $attemptDate; ?></td>
                                                        <?php if (!$selected_user_id): ?>
                                                            <td>
                                                                <?php echo htmlspecialchars($attempt['user_lastname'] . ' ' . $attempt['user_name']); ?>
                                                                <br><small
                                                                    class="text-muted"><?php echo htmlspecialchars($attempt['user_login']); ?></small>
                                                            </td>
                                                        <?php endif; ?>
                                                        <td><?php echo htmlspecialchars($attempt['test_name']); ?></td>
                                                        <?php if (empty($selected_class)): ?>
                                                            <td><?php echo htmlspecialchars($attempt['user_class'] ?: 'Не указан'); ?></td>
                                                        <?php endif; ?>
                                                        <td><strong><?php echo $attempt['correct_answers']; ?></strong></td>
                                                        <td><?php echo $attempt['total_questions']; ?></td>
                                                        <td>
                                                            <span class="badge <?php echo $badgeClass; ?>">
                                                                <?php echo $attemptPercent; ?>%
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <button class="btn btn-sm btn-outline-info"
                                                                onclick="showAttemptDetails(<?php echo $attempt['id']; ?>)">
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
                    echo '<div class="alert alert-info mt-4">Выберите пользователя и/или класс для просмотра статистики.</div>';
                }
                ?>
            </div>

            <!-- Вкладка со всеми результатами -->
            <div class="tab-pane fade show active" id="all-results" role="tabpanel">
                <!-- Форма фильтрации -->
                <div class="filter-form">
                    <form method="GET" action="all_test_results.php">
                        <input type="hidden" name="tab" value="all-results">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Пользователь</label>
                                <select name="user_id_all" class="form-select select2-user-all">
                                    <option value="">Все пользователи</option>
                                    <?php
                                    try {
                                        $pdo = getDbConnection();
                                        $usersSql = "SELECT id, login, name, lastname FROM user_info ORDER BY lastname, name";
                                        $usersStmt = $pdo->query($usersSql);
                                        $users = $usersStmt->fetchAll(PDO::FETCH_ASSOC);

                                        $selected_user_id_all = isset($_GET['user_id_all']) ? intval($_GET['user_id_all']) : 0;
                                        
                                        foreach ($users as $user) {
                                            $selected = ($selected_user_id_all == $user['id']) ? 'selected' : '';
                                            $displayName = htmlspecialchars($user['lastname'] . ' ' . $user['name'] . ' (' . $user['login'] . ')');
                                            echo "<option value='{$user['id']}' {$selected}>{$displayName}</option>";
                                        }
                                    } catch (PDOException $e) {
                                        echo '<option value="">Ошибка загрузки пользователей</option>';
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Класс</label>
                                <select name="class_all" class="form-select">
                                    <option value="">Все классы</option>
                                    <?php
                                    try {
                                        $classSql = "SELECT DISTINCT class FROM user_info WHERE class IS NOT NULL AND class != '' ORDER BY class";
                                        $classStmt = $pdo->query($classSql);
                                        $classes = $classStmt->fetchAll(PDO::FETCH_COLUMN);

                                        $selected_class_all = isset($_GET['class_all']) ? trim($_GET['class_all']) : '';
                                        
                                        foreach ($classes as $class) {
                                            $selected = ($selected_class_all == $class) ? 'selected' : '';
                                            echo "<option value='" . htmlspecialchars($class) . "' {$selected}>" . htmlspecialchars($class) . "</option>";
                                        }
                                    } catch (PDOException $e) {
                                        echo '<option value="">Ошибка загрузки классов</option>';
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">&nbsp;</label>
                                <div class="d-grid gap-2 d-md-flex">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-filter me-2"></i>Фильтровать
                                    </button>
                                    <a href="all_test_results.php?tab=all-results" class="btn btn-outline-secondary">
                                        <i class="fas fa-times me-2"></i>Сбросить
                                    </a>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Таблица со всеми результатами -->
                <div class="table-responsive">
                    <?php
                    try {
                        // Получаем параметры фильтрации для вкладки "все результаты"
                        $filter_user_id = isset($_GET['user_id_all']) ? intval($_GET['user_id_all']) : 0;
                        $filter_class = isset($_GET['class_all']) ? trim($_GET['class_all']) : '';
                        
                        // Определяем сортировку
                        $sort_column = isset($_GET['sort']) ? $_GET['sort'] : 'date';
                        $sort_order = isset($_GET['order']) ? $_GET['order'] : 'DESC';
                        
                        // Безопасный список колонок для сортировки
                        $allowed_columns = ['date', 'user_name', 'test_name', 'correct_answers', 'total_questions', 'percentage'];
                        $sort_column = in_array($sort_column, $allowed_columns) ? $sort_column : 'date';
                        $sort_order = $sort_order === 'ASC' ? 'ASC' : 'DESC';
                        
                        // Формируем условия WHERE
                        $whereConditions = [];
                        $params = [];

                        if ($filter_user_id > 0) {
                            $whereConditions[] = "tr.user_id = ?";
                            $params[] = $filter_user_id;
                        }

                        if (!empty($filter_class)) {
                            $whereConditions[] = "ui.class = ?";
                            $params[] = $filter_class;
                        }

                        $whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";

                        // Запрос для получения всех результатов с сортировкой
                        $sql = "
                            SELECT 
                                tr.*,
                                ui.login as user_login,
                                ui.name as user_name,
                                ui.lastname as user_lastname,
                                ui.class as user_class,
                                ROUND((tr.correct_answers * 100.0 / tr.total_questions), 1) as percentage
                            FROM test_result tr
                            LEFT JOIN user_info ui ON tr.user_id = ui.id
                            {$whereClause}
                            ORDER BY 
                                CASE WHEN ? = 'user_name' THEN CONCAT(ui.lastname, ' ', ui.name) END {$sort_order},
                                CASE WHEN ? = 'date' THEN tr.date END {$sort_order},
                                CASE WHEN ? = 'test_name' THEN tr.test_name END {$sort_order},
                                CASE WHEN ? = 'correct_answers' THEN tr.correct_answers END {$sort_order},
                                CASE WHEN ? = 'total_questions' THEN tr.total_questions END {$sort_order},
                                CASE WHEN ? = 'percentage' THEN (tr.correct_answers * 100.0 / tr.total_questions) END {$sort_order},
                                tr.date DESC
                        ";
                        
                        // Добавляем параметры сортировки (5 раз для каждого CASE)
                        for ($i = 0; $i < 6; $i++) {
                            $params[] = $sort_column;
                        }
                        
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute($params);
                        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        // Функция для создания ссылки сортировки
                        function getSortLink($column, $label, $current_sort, $current_order) {
                            $new_order = ($current_sort == $column && $current_order == 'DESC') ? 'ASC' : 'DESC';
                            $icon = '';
                            if ($current_sort == $column) {
                                $icon = ($current_order == 'ASC') ? ' <i class="fas fa-sort-up"></i>' : ' <i class="fas fa-sort-down"></i>';
                            } else {
                                $icon = ' <i class="fas fa-sort"></i>';
                            }
                            $url = "?tab=all-results&sort={$column}&order={$new_order}";
                            if (isset($_GET['user_id_all']) && $_GET['user_id_all']) $url .= "&user_id_all=" . $_GET['user_id_all'];
                            if (isset($_GET['class_all']) && $_GET['class_all']) $url .= "&class_all=" . $_GET['class_all'];
                            return "<a href='{$url}' class='text-decoration-none text-dark'>{$label}{$icon}</a>";
                        }
                    ?>
                    
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th><?php echo getSortLink('date', 'Дата', $sort_column, $sort_order); ?></th>
                                <th><?php echo getSortLink('user_name', 'Пользователь', $sort_column, $sort_order); ?></th>
                                <th>Класс</th>
                                <th><?php echo getSortLink('test_name', 'Тест', $sort_column, $sort_order); ?></th>
                                <th><?php echo getSortLink('correct_answers', 'Правильно', $sort_column, $sort_order); ?></th>
                                <th><?php echo getSortLink('total_questions', 'Всего', $sort_column, $sort_order); ?></th>
                                <th><?php echo getSortLink('percentage', 'Результат', $sort_column, $sort_order); ?></th>
                                <th>Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($results) > 0): ?>
                                <?php foreach ($results as $index => $row): ?>
                                    <?php
                                    $percentage = round(($row['correct_answers'] / $row['total_questions']) * 100, 1);
                                    $badgeClass = 'badge-danger';
                                    if ($percentage >= 75) $badgeClass = 'badge-success';
                                    elseif ($percentage >= 60) $badgeClass = 'badge-warning';
                                    ?>
                                    <tr>
                                        <td><?php echo $index + 1; ?></td>
                                        <td><?php echo date("d.m.Y H:i", strtotime($row['date'])); ?></td>
                                        <td>
                                            <?php echo htmlspecialchars($row['user_lastname'] . ' ' . $row['user_name']); ?>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($row['user_login']); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($row['user_class'] ?: 'Не указан'); ?></td>
                                        <td><?php echo htmlspecialchars($row['test_name']); ?></td>
                                        <td><strong><?php echo $row['correct_answers']; ?></strong></td>
                                        <td><?php echo $row['total_questions']; ?></td>
                                        <td>
                                            <span class="badge <?php echo $badgeClass; ?>">
                                                <?php echo $percentage; ?>%
                                            </span>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-info"
                                                onclick="showAttemptDetails(<?php echo $row['id']; ?>)">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="9" class="text-center">Нет данных для отображения</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    
                    <?php } catch (PDOException $e) { ?>
                        <div class="alert alert-danger">Ошибка при получении данных: <?php echo htmlspecialchars($e->getMessage()); ?></div>
                    <?php } ?>
                </div>
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
        $(document).ready(function () {
            $('.select2-user').select2({
                placeholder: "Выберите пользователя",
                allowClear: true,
                width: 'resolve'
            });
            
            $('.select2-user-all').select2({
                placeholder: "Выберите пользователя",
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

        // Функция сортировки таблицы (для детальной статистики)
        function sortTable(columnIndex) {
            const table = document.querySelector('.attempts-table');
            const tbody = table.querySelector('tbody');
            const rows = Array.from(tbody.querySelectorAll('tr'));
            
            // Определяем направление сортировки
            const isAscending = table.getAttribute('data-sort') === columnIndex && 
                               table.getAttribute('data-order') === 'asc';
            const newOrder = isAscending ? 'desc' : 'asc';
            
            // Сортируем строки
            rows.sort((a, b) => {
                const aText = a.cells[columnIndex].textContent.trim();
                const bText = b.cells[columnIndex].textContent.trim();
                
                // Пытаемся сравнить как числа
                const aNum = parseFloat(aText);
                const bNum = parseFloat(bText);
                
                if (!isNaN(aNum) && !isNaN(bNum)) {
                    return isAscending ? bNum - aNum : aNum - bNum;
                }
                
                // Иначе сравниваем как строки
                return isAscending ? 
                    bText.localeCompare(aText, 'ru') : 
                    aText.localeCompare(bText, 'ru');
            });
            
            // Обновляем порядок строк
            rows.forEach(row => tbody.appendChild(row));
            
            // Сохраняем состояние сортировки
            table.setAttribute('data-sort', columnIndex);
            table.setAttribute('data-order', newOrder);
            
            // Обновляем иконки сортировки
            updateSortIcons(columnIndex, newOrder);
        }
        
        function updateSortIcons(columnIndex, order) {
            const headers = document.querySelectorAll('.sortable');
            headers.forEach((header, index) => {
                const icon = header.querySelector('.sort-icon i');
                if (index === columnIndex) {
                    icon.className = order === 'asc' ? 'fas fa-sort-up' : 'fas fa-sort-down';
                } else {
                    icon.className = 'fas fa-sort';
                }
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
        document.addEventListener('DOMContentLoaded', function () {
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
                ['Класс', '<?php echo !empty($selected_class) ? htmlspecialchars($selected_class) : "Все классы"; ?>'],
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
            link.setAttribute('download', `статистика_${new Date().toISOString().slice(0, 10)}.csv`);
            link.style.visibility = 'hidden';

            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
    </script>
</body>

</html>