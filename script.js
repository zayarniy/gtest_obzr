// Ссылка на TSV-файл со списка тестов
const testsListUrl = "https://docs.google.com/spreadsheets/d/e/2PACX-1vRfEzIqVGlGPt7SS4JUP4tmVx3HiELICB-GbVsBa-acQfl2Yq0gheoEJyx-unEzjESPbzVN28Zv4y0s/pub?output=tsv";
let tests = [];
let selectedTest = null;

// Текущие данные теста
let currentTestData = {
    questions: [],
    shuffledQuestions: [],
    shuffledOptions: [],
    correctAnswersMap: [],
    userAnswers: [],
    currentQuestionIndex: 0,
    totalQuestions: 0,
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
    const progress = ((currentTestData.currentQuestionIndex + 1) / currentTestData.totalQuestions) * 100;
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
        const response = await fetch(testsListUrl);
        if (!response.ok) throw new Error("Не удалось загрузить список тестов");
        const tsvData = await response.text();
        parseTestsList(tsvData);
    } catch (error) {
        document.getElementById('loading-tests').innerHTML = `
            <div class="alert alert-danger">
                Ошибка загрузки списка тестов: ${error.message}
            </div>
        `;
        console.error(error);
    }
}

// Парсинг списка тестов (теперь считываем 5-й столбец - время)
function parseTestsList(tsvData) {
    const lines = tsvData.trim().split('\n').slice(1);
    tests = lines.map(line => {
        const [n, name, description, url, timeInSeconds] = line.split('\t');
        return { 
            name, 
            description, 
            url, 
            timeLimit: parseInt(timeInSeconds) || 0 
        };
    });
    renderTestsMenu();
}

// Отрисовка меню тестов
function renderTestsMenu() {
    const testList = document.getElementById('test-list');
    testList.innerHTML = '';

    tests.forEach((test, index) => {
        const testItem = document.createElement('div');
        testItem.className = 'list-group-item test-item';
        
        // Добавляем информацию о времени, если оно указано
        let timeInfo = '';
        if (test.timeLimit > 0) {
            const minutes = Math.floor(test.timeLimit / 60);
            const seconds = test.timeLimit % 60;
            timeInfo = `<small class="text-muted d-block mt-1">⏱️ ${minutes} мин ${seconds > 0 ? `${seconds} сек` : ''}</small>`;
        }
        
        testItem.innerHTML = `
            <div>${test.name}</div>
            ${timeInfo}
        `;
        
        testItem.addEventListener('click', () => {
            document.querySelectorAll('.test-item').forEach(item => {
                item.classList.remove('selected');
            });
            testItem.classList.add('selected');
            selectedTest = test;
            document.getElementById('start-test').style.display = 'block';
        });
        testList.appendChild(testItem);
    });

    document.getElementById('loading-tests').style.display = 'none';
    document.getElementById('test-menu').style.display = 'block';
}

// Загрузка и парсинг выбранного теста
async function loadSelectedTest() {
    if (!selectedTest) return;

    resetTestData();
    
    // Начинаем тест без выбора режима
    startTest();
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
        timeLimit: 0,
        timer: null,
        timeLeft: 0
    };
}

// Запуск теста
async function startTest() {
    document.getElementById('test-menu').style.display = 'none';
    document.getElementById('test-container').style.display = 'block';
    document.getElementById('test-description').textContent = selectedTest.description;
    document.getElementById('result-container').style.display = 'none';

    try {
        const response = await fetch(selectedTest.url);
        if (!response.ok) throw new Error("Не удалось загрузить тест");
        const tsvData = await response.text();
        await parseAndPrepareTest(tsvData);
        
        // Устанавливаем лимит времени
        currentTestData.timeLimit = selectedTest.timeLimit;
        
        updateProgressBar();
        showQuestion();
        startTimer(); // Запускаем таймер после показа вопроса
    } catch (error) {
        document.getElementById('test').innerHTML = `
            <div class="alert alert-danger">
                Ошибка загрузки теста: ${error.message}
            </div>
        `;
        console.error(error);
    }
}

// Парсинг и подготовка теста
function parseAndPrepareTest(tsvData) {
    const lines = tsvData.trim().split('\n').slice(1);
    currentTestData.questions = lines.map(line => {
        const [num, question, opt1, opt2, opt3, opt4, correct] = line.split('\t');
        return {
            question,
            options: [opt1, opt2, opt3, opt4],
            correct: parseInt(correct) - 1
        };
    });

    currentTestData.totalQuestions = currentTestData.questions.length;
    currentTestData.shuffledQuestions = shuffleArray([...currentTestData.questions]);
    currentTestData.userAnswers = new Array(currentTestData.totalQuestions).fill(null);
    currentTestData.correctAnswersMap = [];
    currentTestData.shuffledOptions = [];
    
    currentTestData.shuffledQuestions.forEach((q, i) => {
        const shuffledOptions = shuffleArray([...q.options]);
        const correctIndex = shuffledOptions.indexOf(q.options[q.correct]);
        currentTestData.shuffledOptions[i] = shuffledOptions;
        currentTestData.correctAnswersMap[i] = correctIndex;
    });
}

// Показать текущий вопрос
function showQuestion() {
    const testContainer = document.getElementById('test');
    const currentQuestion = currentTestData.shuffledQuestions[currentTestData.currentQuestionIndex];
    const shuffledOpts = currentTestData.shuffledOptions[currentTestData.currentQuestionIndex];
    
    testContainer.innerHTML = `
        <div class="question card mb-3">
            <div class="card-body">
                <h5 class="card-title">Вопрос ${currentTestData.currentQuestionIndex + 1} из ${currentTestData.totalQuestions}</h5>
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
                    ${currentTestData.currentQuestionIndex < currentTestData.totalQuestions - 1 ? `
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

    if (currentTestData.currentQuestionIndex < currentTestData.totalQuestions - 1) {
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
    for (let i = 0; i < currentTestData.totalQuestions; i++) {
        if (currentTestData.userAnswers[i] === currentTestData.correctAnswersMap[i]) {
            correctAnswers++;
        }
    }

    // Вычисляем использованное время
    const timeUsed = currentTestData.timeLimit > 0 ? 
        currentTestData.timeLimit - currentTestData.timeLeft : 
        null;

    // Показать результаты
    showResults(correctAnswers, currentTestData.totalQuestions, timeUsed);

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
                total_questions: currentTestData.totalQuestions,
                time_limit: currentTestData.timeLimit,
                time_used: timeUsed,
                game_mode: 0
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

// Инициализация
document.addEventListener('DOMContentLoaded', () => {
    loadTestsList();
    document.getElementById('start-test').addEventListener('click', loadSelectedTest);
});