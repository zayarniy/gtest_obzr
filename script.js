// Ссылка на TSV-файл со списка тестов
const testsListUrl = "https://docs.google.com/spreadsheets/d/e/2PACX-1vRfEzIqVGlGPt7SS4JUP4tmVx3HiELICB-GbVsBa-acQfl2Yq0gheoEJyx-unEzjESPbzVN28Zv4y0s/pub?output=tsv";
let tests = [];
let selectedTest = null;
let filters = {
    class: 'all',
    subject: 'all',
    trimester: 'all'
};

// Текущие данные теста
let currentTestData = {
    questions: [],
    shuffledQuestions: [],
    shuffledOptions: [],
    correctAnswersMap: [],
    userAnswers: [],
    currentQuestionIndex: 0,
    totalQuestions: 0,
    questionsToShow: 0, // Сколько вопросов показывать
    timeLimit: 0, // Время на тест в секундах
    timer: null,
    timeLeft: 0
};

// Функция для перемешивания массива
function shuffleArray(array) {
    const newArray = [...array];
    for (let i = newArray.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [newArray[i], newArray[j]] = [newArray[j], newArray[i]];
    }
    return newArray;
}

// Обновление прогресс-бара
function updateProgressBar() {
    const progress = ((currentTestData.currentQuestionIndex + 1) / currentTestData.questionsToShow) * 100;
    const progressBar = document.getElementById('progress-bar');
    progressBar.style.width = `${progress}%`;
    progressBar.textContent = `${Math.round(progress)}%`;
}

// Форматирование времени (секунды в MM:SS)
function formatTime(seconds) {
    const minutes = Math.floor(seconds / 60);
    const secs = seconds % 60;
    return `${minutes.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
}

// Запуск таймера
function startTimer() {
    if (currentTestData.timeLimit <= 0) {
        document.getElementById('timer-container').style.display = 'none';
        return;
    }

    document.getElementById('timer-container').style.display = 'block';
    currentTestData.timeLeft = currentTestData.timeLimit;
    updateTimerDisplay();

    currentTestData.timer = setInterval(() => {
        currentTestData.timeLeft--;
        updateTimerDisplay();

        if (currentTestData.timeLeft <= 0) {
            clearInterval(currentTestData.timer);
            Swal.fire({
                title: 'Время вышло!',
                text: 'Тест автоматически завершен.',
                icon: 'warning',
                confirmButtonText: 'OK'
            }).then(() => {
                submitTest();
            });
        }
    }, 1000);
}

// Обновление отображения таймера
function updateTimerDisplay() {
    const timerElement = document.getElementById('timer');
    timerElement.textContent = formatTime(currentTestData.timeLeft);

    // Меняем цвет при низком времени
    if (currentTestData.timeLeft <= 30) {
        timerElement.className = 'text-danger fw-bold';
        document.getElementById('timer-container').className = 'alert alert-danger mb-4';
    } else if (currentTestData.timeLeft <= 60) {
        timerElement.className = 'text-warning fw-bold';
        document.getElementById('timer-container').className = 'alert alert-warning mb-4';
    } else {
        timerElement.className = 'text-dark';
        document.getElementById('timer-container').className = 'alert alert-info mb-4';
    }
}

// Остановка таймера
function stopTimer() {
    if (currentTestData.timer) {
        clearInterval(currentTestData.timer);
        currentTestData.timer = null;
    }
}

// Загрузка списка тестов
async function loadTestsList() {
    try {
        document.getElementById('loading-tests').innerHTML = `
            <div class="text-center my-5">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Загрузка...</span>
                </div>
                <p class="mt-2">Загрузка списка тестов...</p>
            </div>
        `;

        const response = await fetch(testsListUrl);
        if (!response.ok) {
            throw new Error(`Ошибка HTTP: ${response.status}`);
        }

        const tsvData = await response.text();
        await parseTestsList(tsvData);

    } catch (error) {
        console.error('Ошибка загрузки тестов:', error);
        document.getElementById('loading-tests').innerHTML = `
            <div class="alert alert-danger">
                <h5>Ошибка загрузки списка тестов</h5>
                <p>${error.message}</p>
                <button onclick="loadTestsList()" class="btn btn-warning btn-sm mt-2">Повторить загрузку</button>
            </div>
        `;
    }
}

// Парсинг списка тестов
function parseTestsList(tsvData) {
    try {
        const lines = tsvData.trim().split('\n');

        // Пропускаем заголовок
        if (lines.length <= 1) {
            throw new Error('Файл пуст или содержит только заголовок');
        }

        const dataLines = lines.slice(1);

        tests = dataLines.map((line, index) => {
            const columns = line.split('\t');

            // Проверяем минимальное количество столбцов
            if (columns.length < 4) {
                console.warn(`Строка ${index + 2} содержит меньше 4 столбцов:`, line);
                return null;
            }

            // Безопасно получаем значения
            const name = (columns[1] || `Тест ${index + 1}`).trim();
            const description = (columns[2] || '').trim();
            const url = (columns[3] || '').trim();
            const timeInSeconds = parseInt(columns[4]) || 0;
            const questionsCount = parseInt(columns[5]) || 0;
            const classNumber = (columns[6] || '').trim();
            const subject = (columns[7] || '').trim();
            const trimester = normalizeTrimester((columns[8] || '').trim());

            // Проверяем обязательные поля
            if (!url) {
                console.warn(`Тест "${name}" не имеет ссылки`);
                return null;
            }

            return {
                name,
                description,
                url,
                timeLimit: timeInSeconds,
                questionsCount: questionsCount,
                class: classNumber,
                subject: subject,
                trimester: trimester,
                // Добавляем индекс для быстрого поиска
                originalIndex: index
            };
        }).filter(test => test !== null); // Убираем null значения

        if (tests.length === 0) {
            throw new Error('Не найдено ни одного теста');
        }

        console.log(`Загружено ${tests.length} тестов`);

    } catch (error) {
        console.error('Ошибка парсинга тестов:', error);
        throw error;
    }
}

// Нормализация значения триместра
function normalizeTrimester(trimester) {
    if (!trimester) return '';

    // Убираем все лишние пробелы
    trimester = trimester.trim();

    if (!trimester) return '';

    // Приводим к единому формату
    const patterns = [
        { regex: /^1\s*триместр$/i, normalized: '1 триместр' },
        { regex: /^первый\s*триместр$/i, normalized: '1 триместр' },
        { regex: /^2\s*триместр$/i, normalized: '2 триместр' },
        { regex: /^второй\s*триместр$/i, normalized: '2 триместр' },
        { regex: /^3\s*триместр$/i, normalized: '3 триместр' },
        { regex: /^третий\s*триместр$/i, normalized: '3 триместр' },
        { regex: /^1$/i, normalized: '1 триместр' },
        { regex: /^2$/i, normalized: '2 триместр' },
        { regex: /^3$/i, normalized: '3 триместр' },
        { regex: /^триместр\s*1$/i, normalized: '1 триместр' },
        { regex: /^триместр\s*2$/i, normalized: '2 триместр' },
        { regex: /^триместр\s*3$/i, normalized: '3 триместр' }
    ];

    for (const pattern of patterns) {
        if (pattern.regex.test(trimester)) {
            return pattern.normalized;
        }
    }

    // Если не нашли совпадений, возвращаем очищенное значение
    return trimester;
}

// Инициализация после загрузки тестов
async function initAfterTestsLoaded() {
    if (tests.length === 0) {
        document.getElementById('loading-tests').innerHTML = `
            <div class="alert alert-warning">
                Не удалось загрузить тесты. Пожалуйста, проверьте подключение к интернету.
            </div>
        `;
        return;
    }

    renderFilters();
    renderTestsMenu();
}

// Создание фильтров
function renderFilters() {
    // Получаем уникальные значения для фильтров, предварительно нормализуя
    const uniqueClasses = [...new Set(tests.map(test => test.class).filter(Boolean))].sort((a, b) => {
        // Сортируем классы по числовому значению
        const numA = parseInt(a) || 0;
        const numB = parseInt(b) || 0;
        return numA - numB;
    });

    const uniqueSubjects = [...new Set(tests.map(test => test.subject).filter(Boolean))].sort();

    // Для триместров используем нормализованные значения
    const uniqueTrimesters = [...new Set(tests.map(test => test.trimester).filter(Boolean))].sort((a, b) => {
        // Сортируем триместры по номеру
        const numA = parseInt(a) || 0;
        const numB = parseInt(b) || 0;
        return numA - numB;
    });

    // Создаем HTML для фильтров
    let filtersHtml = `
        <div class="row mb-3">
            <div class="col-md-4">
                <label class="form-label">Класс</label>
                <select id="filter-class" class="form-select form-select-sm">
                    <option value="all">Все классы</option>
    `;

    uniqueClasses.forEach(className => {
        const selected = filters.class === className ? 'selected' : '';
        filtersHtml += `<option value="${className}" ${selected}>${className}</option>`;
    });

    filtersHtml += `
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Предмет</label>
                <select id="filter-subject" class="form-select form-select-sm">
                    <option value="all">Все предметы</option>
    `;

    uniqueSubjects.forEach(subject => {
        const selected = filters.subject === subject ? 'selected' : '';
        filtersHtml += `<option value="${subject}" ${selected}>${subject}</option>`;
    });

    filtersHtml += `
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Триместр</label>
                <select id="filter-trimester" class="form-select form-select-sm">
                    <option value="all">Все триместры</option>
    `;

    uniqueTrimesters.forEach(trimester => {
        const selected = filters.trimester === trimester ? 'selected' : '';
        filtersHtml += `<option value="${trimester}" ${selected}>${trimester}</option>`;
    });

    // Добавляем статистику по каждому фильтру
    filtersHtml += `
                </select>
            </div>
        </div>
        <div class="row mb-3">
            <div class="col-md-12">
                <div class="filter-counts small text-muted mb-2">
                    Доступно: 
                    <span class="badge bg-light text-dark">${uniqueClasses.length} классов</span>
                    <span class="badge bg-light text-dark mx-1">${uniqueSubjects.length} предметов</span>
                    <span class="badge bg-light text-dark">${uniqueTrimesters.length} триместров</span>
                </div>
            </div>
        </div>
        <div class="row mb-3">
            <div class="col-md-12">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="filter-stats" id="filter-stats">
                        Найдено тестов: ${tests.length}
                    </div>
                    <div>
                        <button id="reset-filters" class="btn btn-outline-secondary btn-sm">
                            <i class="fas fa-times"></i> Сбросить фильтры
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;

    document.getElementById('filters-container').innerHTML = filtersHtml;

    // Добавляем обработчики событий для фильтров
    document.getElementById('filter-class').addEventListener('change', (e) => {
        filters.class = e.target.value;
        renderTestsMenu();
        updateFilterStats();
    });

    document.getElementById('filter-subject').addEventListener('change', (e) => {
        filters.subject = e.target.value;
        renderTestsMenu();
        updateFilterStats();
    });

    document.getElementById('filter-trimester').addEventListener('change', (e) => {
        filters.trimester = e.target.value;
        renderTestsMenu();
        updateFilterStats();
    });

    document.getElementById('reset-filters').addEventListener('click', () => {
        document.getElementById('filter-class').value = 'all';
        document.getElementById('filter-subject').value = 'all';
        document.getElementById('filter-trimester').value = 'all';
        filters = { class: 'all', subject: 'all', trimester: 'all' };
        renderTestsMenu();
        updateFilterStats();
    });
}

// Получение отфильтрованных тестов
function getFilteredTests() {
    return tests.filter(test => {
        // Фильтр по классу
        if (filters.class !== 'all' && test.class !== filters.class) {
            return false;
        }

        // Фильтр по предмету
        if (filters.subject !== 'all' && test.subject !== filters.subject) {
            return false;
        }

        // Фильтр по триместру (используем нормализованные значения)
        if (filters.trimester !== 'all') {
            const testTrimester = test.trimester;
            const filterTrimester = filters.trimester;

            // Проверяем точное совпадение нормализованных значений
            if (testTrimester !== filterTrimester) {
                return false;
            }
        }

        return true;
    });
}

// Отображение статистики фильтрации
function updateFilterStats() {
    const filteredTests = getFilteredTests();
    const filteredCount = filteredTests.length;

    let statsText = `Найдено тестов: ${filteredCount}`;

    if (filteredCount < tests.length && filteredCount > 0) {
        const percent = Math.round((filteredCount / tests.length) * 100);
        statsText += ` (${percent}% от всех тестов)`;
    }

    document.getElementById('filter-stats').innerHTML = statsText;

    // Показываем активные фильтры
    showActiveFilters();
}

// Показать активные фильтры
function showActiveFilters() {
    const activeFilters = [];

    if (filters.class !== 'all') {
        activeFilters.push(`Класс: ${filters.class}`);
    }

    if (filters.subject !== 'all') {
        activeFilters.push(`Предмет: ${filters.subject}`);
    }

    if (filters.trimester !== 'all') {
        activeFilters.push(`Триместр: ${filters.trimester}`);
    }

    const activeFiltersContainer = document.getElementById('active-filters');
    if (!activeFiltersContainer) {
        // Создаем контейнер для активных фильтров, если его нет
        const container = document.createElement('div');
        container.id = 'active-filters';
        container.className = 'active-filters-container mb-3';
        document.getElementById('filters-container').appendChild(container);
    }

    const activeFiltersDiv = document.getElementById('active-filters');
    if (activeFilters.length > 0) {
        let badgesHtml = '<div class="d-flex flex-wrap gap-2 align-items-center">';
        badgesHtml += '<small class="text-muted me-2">Активные фильтры:</small>';

        activeFilters.forEach(filter => {
            badgesHtml += `<span class="badge bg-info">${filter}</span>`;
        });

        badgesHtml += '</div>';
        activeFiltersDiv.innerHTML = badgesHtml;
        activeFiltersDiv.style.display = 'block';
    } else {
        activeFiltersDiv.style.display = 'none';
    }
}

// Создание HTML для одного теста
function createTestItemHtml(test) {
    // Создаем бейджи для метаданных
    let metaBadges = '<div class="meta-badges mb-2">';

    if (test.class) {
        metaBadges += `<span class="badge bg-primary me-1">${test.class}</span>`;
    }

    if (test.subject) {
        metaBadges += `<span class="badge bg-success me-1">${test.subject}</span>`;
    }

    if (test.trimester) {
        let trimesterClass = 'bg-warning text-dark';
        if (test.trimester === '1 триместр') trimesterClass = 'bg-info';
        if (test.trimester === '2 триместр') trimesterClass = 'bg-warning text-dark';
        if (test.trimester === '3 триместр') trimesterClass = 'bg-danger';
        metaBadges += `<span class="badge ${trimesterClass}">${test.trimester}</span>`;
    }

    metaBadges += '</div>';

    // Добавляем информацию о времени и количестве вопросов
    let infoHtml = '';

    if (test.timeLimit > 0 || test.questionsCount > 0) {
        infoHtml += '<div class="test-info mt-2">';

        if (test.timeLimit > 0) {
            const minutes = Math.floor(test.timeLimit / 60);
            const seconds = test.timeLimit % 60;
            infoHtml += `<small class="text-muted me-3"><i class="fas fa-clock"></i> ${minutes} мин ${seconds > 0 ? `${seconds} сек` : ''}</small>`;
        }

        if (test.questionsCount > 0) {
            infoHtml += `<small class="text-muted"><i class="fas fa-question-circle"></i> ${test.questionsCount} вопросов</small>`;
        }

        infoHtml += '</div>';
    }

    // Сохраняем оригинальный индекс теста в data-атрибуте
    return `
        <div class="list-group-item test-item" data-test-index="${test.originalIndex}" data-test-name="${test.name}">
            <div class="test-header d-flex justify-content-between align-items-start">
                <div class="test-name fw-bold">${test.name}</div>
                ${test.description ? `<div class="text-muted small">${test.description}</div>` : ''}
            </div>
            ${metaBadges}
            ${infoHtml}
        </div>
    `;
}

// Отрисовка меню тестов с учетом фильтров
function renderTestsMenu() {
    const filteredTests = getFilteredTests();
    const testList = document.getElementById('test-list');
    testList.innerHTML = '';

    if (filteredTests.length === 0) {
        testList.innerHTML = `
            <div class="alert alert-warning text-center">
                <i class="fas fa-search me-2"></i>
                По выбранным фильтрам тестов не найдено.
                <br>
                <small class="mt-2 d-block">Попробуйте изменить критерии поиска</small>
            </div>
        `;
        document.getElementById('start-test').style.display = 'none';
        selectedTest = null; // Сбрасываем выбранный тест
        return;
    }

    // Группируем тесты по категориям для лучшей организации
    const groupedTests = groupTests(filteredTests);
    let testListHtml = '';

    // Если есть группировка по классам
    if (Object.keys(groupedTests.classes).length > 1) {
        Object.keys(groupedTests.classes).sort().forEach(className => {
            const classTests = groupedTests.classes[className];
            testListHtml += `
                <div class="class-group mb-4">
                    <h5 class="mb-3 text-primary border-bottom pb-2">
                        <i class="fas fa-graduation-cap me-2"></i>${className}
                        <span class="badge bg-secondary float-end">${classTests.length}</span>
                    </h5>
            `;
            
            classTests.forEach(test => {
                testListHtml += createTestItemHtml(test);
            });
            
            testListHtml += `</div>`;
        });
    } else {
        // Просто показываем все тесты
        filteredTests.forEach(test => {
            testListHtml += createTestItemHtml(test);
        });
    }

    testList.innerHTML = testListHtml;
    
    // Добавляем обработчики кликов на тесты
    document.querySelectorAll('.test-item').forEach(item => {
        item.addEventListener('click', function() {
            // Снимаем выделение со всех тестов
            document.querySelectorAll('.test-item').forEach(item => {
                item.classList.remove('selected');
            });
            
            // Выделяем текущий тест
            this.classList.add('selected');
            
            // Получаем индекс теста из data-атрибута
            const testIndex = parseInt(this.getAttribute('data-test-index'));
            
            // Находим тест по индексу в основном массиве tests
            if (!isNaN(testIndex) && testIndex >= 0 && testIndex < tests.length) {
                selectedTest = tests[testIndex];
            } else {
                // Если не удалось найти по индексу, ищем по имени во всем массиве tests
                const testName = this.getAttribute('data-test-name');
                selectedTest = tests.find(test => test.name === testName);
            }
            
            // Показываем кнопку "Пуск"
            document.getElementById('start-test').style.display = 'block';
            
            // Логируем для отладки
            console.log('Выбран тест:', selectedTest);
        });
    });

    document.getElementById('loading-tests').style.display = 'none';
    document.getElementById('test-menu').style.display = 'block';
    
    // Сбрасываем выбранный тест при перерисовке меню
    selectedTest = null;
    document.getElementById('start-test').style.display = 'none';
}

// Группировка тестов
function groupTests(testsList) {
    const grouped = {
        classes: {},
        subjects: {},
        trimesters: {}
    };

    testsList.forEach(test => {
        // Группировка по классам
        if (test.class) {
            if (!grouped.classes[test.class]) {
                grouped.classes[test.class] = [];
            }
            grouped.classes[test.class].push(test);
        }

        // Группировка по предметам
        if (test.subject) {
            if (!grouped.subjects[test.subject]) {
                grouped.subjects[test.subject] = [];
            }
            grouped.subjects[test.subject].push(test);
        }

        // Группировка по триместрам
        if (test.trimester) {
            if (!grouped.trimesters[test.trimester]) {
                grouped.trimesters[test.trimester] = [];
            }
            grouped.trimesters[test.trimester].push(test);
        }
    });

    return grouped;
}

// Загрузка и парсинг выбранного теста
async function loadSelectedTest() {
    // Проверяем, выбран ли тест
    if (!selectedTest) {
        // Проверяем, есть ли выбранный элемент в DOM
        const selectedItem = document.querySelector('.test-item.selected');
        if (selectedItem) {
            // Пытаемся получить тест по индексу
            const testIndex = parseInt(selectedItem.getAttribute('data-test-index'));
            if (!isNaN(testIndex) && testIndex >= 0 && testIndex < tests.length) {
                selectedTest = tests[testIndex];
            } else {
                // Если не удалось по индексу, ищем по имени
                const testName = selectedItem.getAttribute('data-test-name');
                selectedTest = tests.find(test => test.name === testName);
            }
        }

        // Если все еще нет, показываем ошибку
        if (!selectedTest) {
            Swal.fire({
                title: 'Ошибка',
                text: 'Пожалуйста, выберите тест из списка',
                icon: 'warning',
                confirmButtonText: 'OK'
            });
            return;
        }
    }

    resetTestData();

    // Начинаем тест
    await startTest();
}

// Сброс данных теста
function resetTestData() {
    stopTimer(); // Останавливаем таймер при сбросе
    currentTestData = {
        questions: [],
        shuffledQuestions: [],
        shuffledOptions: [],
        correctAnswersMap: [],
        userAnswers: [],
        currentQuestionIndex: 0,
        totalQuestions: 0,
        questionsToShow: 0,
        timeLimit: 0,
        timer: null,
        timeLeft: 0
    };
}

// Запуск теста
async function startTest() {
    document.getElementById('test-menu').style.display = 'none';
    document.getElementById('test-container').style.display = 'block';

    // Показываем информацию о тесте
    let descriptionHtml = `<strong>${selectedTest.name}</strong>`;
    if (selectedTest.description) {
        descriptionHtml += `<br>${selectedTest.description}`;
    }

    // Добавляем метаданные теста
    let metaInfo = '<div class="test-meta mt-2">';

    if (selectedTest.class) {
        metaInfo += `<span class="badge bg-primary me-2">${selectedTest.class}</span>`;
    }

    if (selectedTest.subject) {
        metaInfo += `<span class="badge bg-success me-2">${selectedTest.subject}</span>`;
    }

    if (selectedTest.trimester) {
        metaInfo += `<span class="badge bg-warning text-dark me-2">${selectedTest.trimester}</span>`;
    }

    metaInfo += '</div>';

    // Добавляем информацию о количестве вопросов и времени
    let detailsHtml = '<div class="test-details mt-2">';

    if (selectedTest.questionsCount > 0) {
        detailsHtml += `<span class="badge bg-info me-2"><i class="fas fa-question-circle"></i> ${selectedTest.questionsCount} вопросов</span>`;
    }

    if (selectedTest.timeLimit > 0) {
        const minutes = Math.floor(selectedTest.timeLimit / 60);
        const seconds = selectedTest.timeLimit % 60;
        detailsHtml += `<span class="badge bg-warning"><i class="fas fa-clock"></i> ${minutes} мин ${seconds > 0 ? `${seconds} сек` : ''}</span>`;
    }

    detailsHtml += '</div>';

    document.getElementById('test-description').innerHTML = descriptionHtml + metaInfo + detailsHtml;
    document.getElementById('result-container').style.display = 'none';

    try {
        document.getElementById('test').innerHTML = `
            <div class="text-center my-5">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Загрузка теста...</span>
                </div>
                <p class="mt-2">Загрузка теста...</p>
            </div>
        `;

        const response = await fetch(selectedTest.url);
        if (!response.ok) throw new Error(`Не удалось загрузить тест: ${response.status}`);
        const tsvData = await response.text();
        await parseAndPrepareTest(tsvData);

        // Устанавливаем лимит времени
        currentTestData.timeLimit = selectedTest.timeLimit;

        updateProgressBar();
        showQuestion();
        startTimer(); // Запускаем таймер после показа вопроса
    } catch (error) {
        console.error('Ошибка загрузки теста:', error);
        document.getElementById('test').innerHTML = `
            <div class="alert alert-danger">
                Ошибка загрузки теста: ${error.message}
                <br>
                <button onclick="loadSelectedTest()" class="btn btn-warning btn-sm mt-2">Повторить</button>
                <button onclick="renderTestsMenu()" class="btn btn-secondary btn-sm mt-2 ms-2">Вернуться к списку</button>
            </div>
        `;
    }
}

// Парсинг и подготовка теста
function parseAndPrepareTest(tsvData) {
    try {
        const lines = tsvData.trim().split('\n').slice(1);

        if (lines.length === 0) {
            throw new Error('Тест пуст или содержит только заголовок');
        }

        currentTestData.questions = lines.map(line => {
            const [num, question, opt1, opt2, opt3, opt4, correct] = line.split('\t');
            return {
                question: question || '',
                options: [opt1 || '', opt2 || '', opt3 || '', opt4 || ''],
                correct: parseInt(correct) - 1
            };
        });

        currentTestData.totalQuestions = currentTestData.questions.length;

        // Определяем сколько вопросов показывать
        if (selectedTest.questionsCount > 0 && selectedTest.questionsCount < currentTestData.totalQuestions) {
            currentTestData.questionsToShow = selectedTest.questionsCount;
        } else {
            currentTestData.questionsToShow = currentTestData.totalQuestions;
        }

        // Проверяем, что у нас достаточно вопросов
        if (currentTestData.totalQuestions < currentTestData.questionsToShow) {
            currentTestData.questionsToShow = currentTestData.totalQuestions;
        }

        // Перемешиваем вопросы
        currentTestData.shuffledQuestions = shuffleArray([...currentTestData.questions]);

        // Берем только нужное количество вопросов
        currentTestData.shuffledQuestions = currentTestData.shuffledQuestions.slice(0, currentTestData.questionsToShow);

        // Подготавливаем массивы для ответов
        currentTestData.userAnswers = new Array(currentTestData.questionsToShow).fill(null);
        currentTestData.correctAnswersMap = [];
        currentTestData.shuffledOptions = [];

        // Перемешиваем варианты ответов для каждого вопроса и сохраняем правильные индексы
        currentTestData.shuffledQuestions.forEach((q, i) => {
            const shuffledOptions = shuffleArray([...q.options]);
            const correctIndex = shuffledOptions.indexOf(q.options[q.correct]);
            currentTestData.shuffledOptions[i] = shuffledOptions;
            currentTestData.correctAnswersMap[i] = correctIndex;
        });

        console.log(`Загружено ${currentTestData.totalQuestions} вопросов, показываем ${currentTestData.questionsToShow}`);

    } catch (error) {
        console.error('Ошибка парсинга теста:', error);
        throw error;
    }
}

// Показать текущий вопрос
function showQuestion() {
    const testContainer = document.getElementById('test');
    const currentQuestion = currentTestData.shuffledQuestions[currentTestData.currentQuestionIndex];
    const shuffledOpts = currentTestData.shuffledOptions[currentTestData.currentQuestionIndex];

    // Информация о количестве вопросов
    const totalInfo = currentTestData.questionsToShow !== currentTestData.totalQuestions ?
        ` (${currentTestData.questionsToShow} из ${currentTestData.totalQuestions})` :
        '';

    testContainer.innerHTML = `
        <div class="question card mb-3">
            <div class="card-body">
                <h5 class="card-title">Вопрос ${currentTestData.currentQuestionIndex + 1} из ${currentTestData.questionsToShow}${totalInfo}</h5>
                <p class="question-text">${currentQuestion.question}</p>
                <div class="options">
                    ${shuffledOpts.map((opt, j) => `
                        <div class="form-check option">
                            <input class="form-check-input" type="radio" 
                                   id="q${currentTestData.currentQuestionIndex}-opt${j}" 
                                   name="q${currentTestData.currentQuestionIndex}" 
                                   value="${j}"
                                   ${currentTestData.userAnswers[currentTestData.currentQuestionIndex] === j ? 'checked' : ''}>
                            <label class="form-check-label" for="q${currentTestData.currentQuestionIndex}-opt${j}">
                                ${opt}
                            </label>
                        </div>
                    `).join('')}
                </div>
                <div class="mt-3">
                    ${currentTestData.currentQuestionIndex > 0 ? `
                        <button id="prev-question" class="btn btn-secondary me-2">
                            ← Назад
                        </button>
                    ` : ''}
                    ${currentTestData.currentQuestionIndex < currentTestData.questionsToShow - 1 ? `
                        <button id="next-question" class="btn btn-primary">
                            Далее →
                        </button>
                    ` : `
                        <button id="finish-test" class="btn btn-success">
                            Завершить тест
                        </button>
                    `}
                </div>
            </div>
        </div>
    `;

    // Обработчики кнопок
    if (currentTestData.currentQuestionIndex > 0) {
        document.getElementById('prev-question').addEventListener('click', () => {
            saveCurrentAnswer();
            currentTestData.currentQuestionIndex--;
            updateProgressBar();
            showQuestion();
        });
    }

    if (currentTestData.currentQuestionIndex < currentTestData.questionsToShow - 1) {
        document.getElementById('next-question').addEventListener('click', () => {
            saveCurrentAnswer();
            currentTestData.currentQuestionIndex++;
            updateProgressBar();
            showQuestion();
        });
    } else {
        document.getElementById('finish-test').addEventListener('click', submitTest);
    }
}

// Сохранить ответ на текущий вопрос
function saveCurrentAnswer() {
    const selected = document.querySelector(`input[name="q${currentTestData.currentQuestionIndex}"]:checked`);
    currentTestData.userAnswers[currentTestData.currentQuestionIndex] = selected ? parseInt(selected.value) : null;
}

// Показать результаты в процентах
function showResults(correctAnswers, totalQuestions, timeUsed = null) {
    const percentage = (correctAnswers / totalQuestions) * 100;
    const roundedPercentage = Math.round(percentage * 10) / 10;

    let grade = '';
    let colorClass = '';

    if (percentage >= 90) {
        grade = 'Отлично!';
        colorClass = 'text-success';
    } else if (percentage >= 75) {
        grade = 'Хорошо!';
        colorClass = 'text-primary';
    } else if (percentage >= 60) {
        grade = 'Удовлетворительно';
        colorClass = 'text-warning';
    } else {
        grade = 'Неудовлетворительно';
        colorClass = 'text-danger';
    }

    // Информация о количестве вопросов
    let questionsInfo = '';
    if (currentTestData.questionsToShow !== currentTestData.totalQuestions) {
        questionsInfo = `
            <div class="questions-info mt-2">
                <small class="text-muted">
                    <i class="fas fa-info-circle"></i> 
                    Тест содержал ${currentTestData.questionsToShow} случайных вопросов из ${currentTestData.totalQuestions} возможных
                </small>
            </div>
        `;
    }

    // Формируем информацию о времени
    let timeInfo = '';
    if (timeUsed !== null && currentTestData.timeLimit > 0) {
        const timeUsedFormatted = formatTime(timeUsed);
        const timeLimitFormatted = formatTime(currentTestData.timeLimit);
        timeInfo = `
            <div class="result-time mt-3">
                <p class="h5">Время выполнения: <span class="fw-bold">${timeUsedFormatted}</span> из ${timeLimitFormatted}</p>
            </div>
        `;
    }

    document.getElementById('result').innerHTML = `
        <div class="result-display">
            <h2 class="${colorClass}">${grade}</h2>
            <div class="mt-4">
                <div class="result-percentage">
                    <span class="percentage-number display-1 fw-bold ${colorClass}">${roundedPercentage}%</span>
                    <div class="progress mt-3" style="height: 25px;">
                        <div class="progress-bar ${colorClass.replace('text-', 'bg-')}" 
                             role="progressbar" 
                             style="width: ${percentage}%">
                        </div>
                    </div>
                </div>
                <div class="result-details mt-4">
                    <p class="h5">Правильных ответов: <span class="fw-bold">${correctAnswers}</span> из <span class="fw-bold">${totalQuestions}</span></p>
                    <p class="h5">Оценка: <span class="fw-bold">${grade}</span></p>
                    ${timeInfo}
                    ${questionsInfo}
                </div>
            </div>
        </div>
    `;

    document.getElementById('test').style.display = 'none';
    document.getElementById('result-container').style.display = 'block';
    document.getElementById('timer-container').style.display = 'none'; // Скрываем таймер после завершения
}

// Завершение теста
async function submitTest() {
    stopTimer(); // Останавливаем таймер

    saveCurrentAnswer();

    // Подсчет правильных ответов
    let correctAnswers = 0;
    for (let i = 0; i < currentTestData.questionsToShow; i++) {
        if (currentTestData.userAnswers[i] === currentTestData.correctAnswersMap[i]) {
            correctAnswers++;
        }
    }

    // Вычисляем использованное время
    const timeUsed = currentTestData.timeLimit > 0 ?
        currentTestData.timeLimit - currentTestData.timeLeft :
        null;

    // Показать результаты
    showResults(correctAnswers, currentTestData.questionsToShow, timeUsed);

    // Сохранение результата на сервер
    try {
        const response = await fetch('phps/save_test_result.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                user_id: currentUserId,
                test_name: selectedTest.name,
                correct_answers: correctAnswers,
                total_questions: currentTestData.questionsToShow,
                total_possible_questions: currentTestData.totalQuestions,
                time_limit: currentTestData.timeLimit,
                time_used: timeUsed
            }),
        });

        const result = await response.json();
        if (result.status !== 'success') {
            console.error('Ошибка сохранения результата:', result.message);
        }
    } catch (error) {
        console.error('Ошибка при сохранении результата:', error);
    }
}

// Функция для отладки выбора теста
function debugTestSelection() {
    console.log('Текущий selectedTest:', selectedTest);
    console.log('Всего тестов:', tests.length);
    console.log('Все тесты:', tests);
    
    const selectedItem = document.querySelector('.test-item.selected');
    console.log('Выбранный элемент DOM:', selectedItem);
    
    if (selectedItem) {
        const testIndex = selectedItem.getAttribute('data-test-index');
        const testName = selectedItem.getAttribute('data-test-name');
        console.log('Индекс теста из DOM:', testIndex);
        console.log('Имя теста из DOM:', testName);
        
        // Пробуем найти тест разными способами
        if (testIndex !== null && testIndex !== 'null') {
            const foundByIndex = tests[parseInt(testIndex)];
            console.log('Тест найденный по индексу:', foundByIndex);
        }
        
        const foundByName = tests.find(test => test.name === testName);
        console.log('Тест найденный по имени:', foundByName);
    }
}

// Инициализация
document.addEventListener('DOMContentLoaded', async () => {
    try {
        console.log('Начало загрузки тестов...');
        await loadTestsList();
        await initAfterTestsLoaded();
        console.log('Тесты успешно загружены');
    } catch (error) {
        console.error('Ошибка инициализации:', error);
        document.getElementById('loading-tests').innerHTML = `
            <div class="alert alert-danger">
                <h5>Ошибка инициализации</h5>
                <p>${error.message}</p>
                <button onclick="location.reload()" class="btn btn-warning btn-sm mt-2">Перезагрузить страницу</button>
            </div>
        `;
    }
    
    // Обработчик кнопки "Пуск" с улучшенной логикой
    document.getElementById('start-test').addEventListener('click', async () => {
        console.log('Нажата кнопка "Пуск"');
        console.log('Текущий selectedTest:', selectedTest);
        
        // Если selectedTest не установлен, пытаемся найти выбранный тест
        if (!selectedTest) {
            const selectedItem = document.querySelector('.test-item.selected');
            
            if (selectedItem) {
                // Пробуем по индексу
                const testIndex = parseInt(selectedItem.getAttribute('data-test-index'));
                if (!isNaN(testIndex) && testIndex >= 0 && testIndex < tests.length) {
                    selectedTest = tests[testIndex];
                    console.log('Тест найден по индексу:', selectedTest);
                } else {
                    // Пробуем по имени
                    const testName = selectedItem.getAttribute('data-test-name');
                    selectedTest = tests.find(test => test.name === testName);
                    console.log('Тест найден по имени:', selectedTest);
                }
            }
            
            // Если все еще не нашли
            if (!selectedTest) {
                Swal.fire({
                    title: 'Ошибка',
                    text: 'Тест не выбран. Пожалуйста, выберите тест из списка.',
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
                return;
            }
        }
        
        console.log('Запускаем тест:', selectedTest);
        await loadSelectedTest();
    });
});