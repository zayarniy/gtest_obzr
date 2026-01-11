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
    <!-- Подключаем Font Awesome для иконок -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            padding: 20px;
            background-color: #f8f9fa;
        }
        .container {
            max-width: 1400px;
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
        .test-list-container {
            max-height: 200px;
            overflow-y: auto;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 10px;
            margin-top: 10px;
        }
        .test-checkbox-item {
            margin-bottom: 5px;
        }
        .form-check-label {
            cursor: pointer;
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
        .no-results {
            text-align: center;
            padding: 40px;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1><i class="fas fa-chart-bar me-2"></i><?php echo htmlspecialchars($pageTitle); ?></h1>

        <!-- Статистика -->
        <?php
        try {
            $pdo = getDbConnection();
            $statsSql = "SELECT 
                COUNT(*) as total_results,
                COUNT(DISTINCT tr.user_id) as total_users,
                COUNT(DISTINCT tr.test_name) as total_tests,
                AVG(tr.correct_answers * 100.0 / tr.total_questions) as avg_percentage
                FROM test_result tr";
            $statsStmt = $pdo->query($statsSql);
            $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $stats = ['total_results' => 0, 'total_users' => 0, 'total_tests' => 0, 'avg_percentage' => 0];
        }
        ?>

        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stats-card">
                    <h6>Всего результатов</h6>
                    <div class="stats-number"><?php echo $stats['total_results']; ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                    <h6>Пользователей</h6>
                    <div class="stats-number"><?php echo $stats['total_users']; ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                    <h6>Уникальных тестов</h6>
                    <div class="stats-number"><?php echo $stats['total_tests']; ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                    <h6>Средний результат</h6>
                    <div class="stats-number"><?php echo round($stats['avg_percentage'], 1); ?>%</div>
                </div>
            </div>
        </div>

        <!-- Форма для фильтрации результатов -->
        <form method="GET" action="all_test_results.php" class="filter-form">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Фильтр по названию теста</label>
                    <input
                        type="text"
                        name="test_name"
                        class="form-control"
                        placeholder="Введите название теста..."
                        value="<?php echo isset($_GET['test_name']) ? htmlspecialchars($_GET['test_name']) : ''; ?>"
                    >
                </div>
                
                <div class="col-md-4">
                    <label class="form-label">Фильтр по пользователю</label>
                    <input
                        type="text"
                        name="user_name"
                        class="form-control"
                        placeholder="Логин или имя..."
                        value="<?php echo isset($_GET['user_name']) ? htmlspecialchars($_GET['user_name']) : ''; ?>"
                    >
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">Минимум правильных</label>
                    <select name="min_correct" class="form-select">
                        <option value="">Все результаты</option>
                        <?php for ($i = 0; $i <= 20; $i++): ?>
                            <option value="<?php echo $i; ?>"
                                <?php echo (isset($_GET['min_correct']) && $_GET['min_correct'] == $i) ? 'selected' : ''; ?>>
                                ≥ <?php echo $i; ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">Сортировка</label>
                    <select name="sort_by" class="form-select">
                        <option value="date_desc" <?php echo (isset($_GET['sort_by']) && $_GET['sort_by'] == 'date_desc') ? 'selected' : ''; ?>>Дата (новые)</option>
                        <option value="date_asc" <?php echo (isset($_GET['sort_by']) && $_GET['sort_by'] == 'date_asc') ? 'selected' : ''; ?>>Дата (старые)</option>
                        <option value="percent_desc" <?php echo (isset($_GET['sort_by']) && $_GET['sort_by'] == 'percent_desc') ? 'selected' : ''; ?>>Результат (↓)</option>
                        <option value="percent_asc" <?php echo (isset($_GET['sort_by']) && $_GET['sort_by'] == 'percent_asc') ? 'selected' : ''; ?>>Результат (↑)</option>
                        <option value="test_name" <?php echo (isset($_GET['sort_by']) && $_GET['sort_by'] == 'test_name') ? 'selected' : ''; ?>>Название теста</option>
                        <option value="user_name" <?php echo (isset($_GET['sort_by']) && $_GET['sort_by'] == 'user_name') ? 'selected' : ''; ?>>Имя пользователя</option>
                    </select>
                </div>
                
                <div class="col-md-12">
                    <label class="form-label">Выбор тестов (несколько)</label>
                    <div class="test-list-container">
                        <?php
                        try {
                            // Получаем список уникальных тестов
                            $testsSql = "SELECT DISTINCT test_name FROM test_result ORDER BY test_name";
                            $testsStmt = $pdo->prepare($testsSql);
                            $testsStmt->execute();
                            $allTests = $testsStmt->fetchAll(PDO::FETCH_COLUMN);
                            
                            // Получаем выбранные тесты из GET-параметра
                            $selectedTests = isset($_GET['selected_tests']) ? explode(',', $_GET['selected_tests']) : [];
                            
                            if (empty($allTests)) {
                                echo '<div class="no-results">Нет доступных тестов</div>';
                            } else {
                                foreach ($allTests as $test) {
                                    $isChecked = in_array($test, $selectedTests) ? 'checked' : '';
                                    echo '
                                    <div class="form-check test-checkbox-item">
                                        <input class="form-check-input" type="checkbox" name="test_checkbox[]" value="' . htmlspecialchars($test) . '" id="test_' . md5($test) . '" ' . $isChecked . '>
                                        <label class="form-check-label" for="test_' . md5($test) . '">
                                            ' . htmlspecialchars($test) . '
                                        </label>
                                    </div>';
                                }
                            }
                        } catch (PDOException $e) {
                            echo '<div class="alert alert-warning">Не удалось загрузить список тестов</div>';
                        }
                        ?>
                    </div>
                </div>
                
                <div class="col-md-12">
                    <div class="d-flex justify-content-between">
                        <div>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-filter me-2"></i>Применить фильтры
                            </button>
                            <a href="all_test_results.php" class="btn btn-outline-secondary ms-2">
                                <i class="fas fa-times me-2"></i>Сбросить
                            </a>
                        </div>
                        <div>
                            <button type="button" class="btn btn-success" onclick="selectAllTests(true)">
                                <i class="fas fa-check-square me-2"></i>Выбрать все
                            </button>
                            <button type="button" class="btn btn-outline-danger ms-2" onclick="selectAllTests(false)">
                                <i class="fas fa-times-circle me-2"></i>Очистить все
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </form>

        <?php
        try {
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

            // Фильтр по выбранным тестам
            if (!empty($_GET['selected_tests']) || !empty($_GET['test_checkbox'])) {
                $selectedTests = [];
                
                if (!empty($_GET['selected_tests'])) {
                    $selectedTests = explode(',', $_GET['selected_tests']);
                }
                
                if (!empty($_GET['test_checkbox'])) {
                    $selectedTests = array_merge($selectedTests, $_GET['test_checkbox']);
                }
                
                if (!empty($selectedTests)) {
                    $selectedTests = array_unique($selectedTests);
                    $placeholders = str_repeat('?,', count($selectedTests) - 1) . '?';
                    $whereClauses[] = "tr.test_name IN ($placeholders)";
                    $params = array_merge($params, $selectedTests);
                }
            }

            if (!empty($whereClauses)) {
                $sql .= " WHERE " . implode(" AND ", $whereClauses);
            }

            // Добавляем сортировку
            $sortColumn = "tr.date";
            $sortDirection = "DESC";
            
            if (!empty($_GET['sort_by'])) {
                switch ($_GET['sort_by']) {
                    case 'date_asc':
                        $sortColumn = "tr.date";
                        $sortDirection = "ASC";
                        break;
                    case 'percent_desc':
                        $sortColumn = "(tr.correct_answers * 100.0 / tr.total_questions)";
                        $sortDirection = "DESC";
                        break;
                    case 'percent_asc':
                        $sortColumn = "(tr.correct_answers * 100.0 / tr.total_questions)";
                        $sortDirection = "ASC";
                        break;
                    case 'test_name':
                        $sortColumn = "tr.test_name";
                        $sortDirection = "ASC";
                        break;
                    case 'user_name':
                        $sortColumn = "ui.name";
                        $sortDirection = "ASC";
                        break;
                }
            }
            
            $sql .= " ORDER BY $sortColumn $sortDirection";

            // Подготавливаем и выполняем запрос
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $testResults = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Проверяем, есть ли результаты
            if (empty($testResults)) {
                echo '<div class="alert alert-info"><i class="fas fa-info-circle me-2"></i>Нет результатов тестов по заданным фильтрам.</div>';
            } else {
                // Выводим таблицу с результатами
                echo '
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th class="sortable" onclick="sortTable(\'user_id\')">ID <i class="fas fa-sort sort-icon"></i></th>
                                <th class="sortable" onclick="sortTable(\'user_login\')">Логин <i class="fas fa-sort sort-icon"></i></th>
                                <th class="sortable" onclick="sortTable(\'user_name\')">Имя <i class="fas fa-sort sort-icon"></i></th>
                                <th>Email</th>
                                <th class="sortable" onclick="sortTable(\'test_name\')">Тест <i class="fas fa-sort sort-icon"></i></th>
                                <th class="sortable" onclick="sortTable(\'correct_answers\')">Правильно <i class="fas fa-sort sort-icon"></i></th>
                                <th>Всего</th>
                                <th class="sortable" onclick="sortTable(\'percent\')">Результат <i class="fas fa-sort sort-icon"></i></th>
                                <th class="sortable" onclick="sortTable(\'date\')">Дата <i class="fas fa-sort sort-icon"></i></th>
                            </tr>
                        </thead>
                        <tbody>
                ';

                // Перебираем результаты и выводим их в таблице
                foreach ($testResults as $test) {
                    $percent = round(($test['correct_answers'] / $test['total_questions']) * 100);
                    $date = date("d.m.Y H:i", strtotime($test['date']));
                    
                    // Определяем цвет баджа для результата
                    $badgeClass = 'badge-danger';
                    if ($percent >= 75) {
                        $badgeClass = 'badge-success';
                    } elseif ($percent >= 60) {
                        $badgeClass = 'badge-warning';
                    }

                    echo "
                        <tr>
                            <td>{$test['user_id']}</td>
                            <td><strong>{$test['user_login']}</strong></td>
                            <td>{$test['user_name']}</td>
                            <td><small>{$test['user_email']}</small></td>
                            <td><span class='text-primary'>{$test['test_name']}</span></td>
                            <td><strong>{$test['correct_answers']}</strong></td>
                            <td>{$test['total_questions']}</td>
                            <td>
                                <span class='badge {$badgeClass}'>
                                    {$percent}%
                                </span>
                            </td>
                            <td><small>{$date}</small></td>
                        </tr>
                    ";
                }

                echo '
                        </tbody>
                    </table>
                </div>
                ';
                
                // Выводим количество найденных результатов
                echo '<div class="mt-3 text-muted">
                    <i class="fas fa-database me-2"></i>Найдено результатов: ' . count($testResults) . '
                </div>';
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
            <button onclick="exportToCSV()" class="btn btn-outline-success ms-2">
                <i class="fas fa-file-csv me-2"></i>Экспорт в CSV
            </button>
        </div>
    </div>

    <!-- Подключаем Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Функция для выбора/очистки всех тестов
        function selectAllTests(select) {
            const checkboxes = document.querySelectorAll('input[name="test_checkbox[]"]');
            checkboxes.forEach(checkbox => {
                checkbox.checked = select;
            });
        }
        
        // Функция для сортировки таблицы
        function sortTable(column) {
            const currentUrl = new URL(window.location.href);
            const currentSort = currentUrl.searchParams.get('sort_by');
            
            // Определяем направление сортировки
            let newSort = '';
            switch(column) {
                case 'date':
                    newSort = currentSort === 'date_desc' ? 'date_asc' : 'date_desc';
                    break;
                case 'percent':
                    newSort = currentSort === 'percent_desc' ? 'percent_asc' : 'percent_desc';
                    break;
                case 'test_name':
                    newSort = 'test_name';
                    break;
                case 'user_name':
                    newSort = 'user_name';
                    break;
                case 'user_login':
                    newSort = 'user_name';
                    break;
                case 'user_id':
                    newSort = currentSort === 'user_id_desc' ? 'user_id_asc' : 'user_id_desc';
                    break;
                default:
                    newSort = 'date_desc';
            }
            
            currentUrl.searchParams.set('sort_by', newSort);
            window.location.href = currentUrl.toString();
        }
        
        // Функция для экспорта в CSV
        function exportToCSV() {
            let csv = [];
            const rows = document.querySelectorAll('table tr');
            
            for (let i = 0; i < rows.length; i++) {
                let row = [], cols = rows[i].querySelectorAll('td, th');
                
                for (let j = 0; j < cols.length; j++) {
                    // Убираем HTML из ячеек
                    let text = cols[j].innerText.replace(/\s+/g, ' ').trim();
                    // Экранируем кавычки
                    text = text.replace(/"/g, '""');
                    // Добавляем кавычки если есть запятая или кавычка
                    if (text.includes(',') || text.includes('"') || text.includes('\n')) {
                        text = '"' + text + '"';
                    }
                    row.push(text);
                }
                csv.push(row.join(','));
            }
            
            // Создаем и скачиваем файл
            const csvContent = csv.join('\n');
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            const url = URL.createObjectURL(blob);
            
            link.setAttribute('href', url);
            link.setAttribute('download', 'test_results_' + new Date().toISOString().slice(0,10) + '.csv');
            link.style.visibility = 'hidden';
            
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
        
        // Обработка чекбоксов тестов при отправке формы
        document.querySelector('form').addEventListener('submit', function(e) {
            const checkboxes = document.querySelectorAll('input[name="test_checkbox[]"]:checked');
            const selectedValues = Array.from(checkboxes).map(cb => cb.value);
            
            // Добавляем скрытое поле с выбранными тестами
            if (selectedValues.length > 0) {
                let hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.name = 'selected_tests';
                hiddenInput.value = selectedValues.join(',');
                this.appendChild(hiddenInput);
            }
        });
        
        // Автоматическая отправка формы при изменении селекта сортировки
        document.querySelector('select[name="sort_by"]').addEventListener('change', function() {
            this.form.submit();
        });
    </script>
</body>
</html>