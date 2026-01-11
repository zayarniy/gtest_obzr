// Ссылка на TSV-файл со списком тестов
const testsListUrl = "https://docs.google.com/spreadsheets/d/e/2PACX-1vRfEzIqVGlGPt7SS4JUP4tmVx3HiELICB-GbVsBa-acQfl2Yq0gheoEJyx-unEzjESPbzVN28Zv4y0s/pub?output=tsv";
let tests = [];
let selectedTest = null;
let countAttempt = 3;

// Функция для перемешивания массива (алгоритм Фишера-Йетса)
function shuffleArray(array) {
    for (let i = array.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [array[i], array[j]] = [array[j], array[i]];
    }
    return array;
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

// Парсинг списка тестов
function parseTestsList(tsvData) {
    const lines = tsvData.trim().split('\n').slice(1); // Пропускаем заголовок
    tests = lines.map(line => {
        const [n, name, description, url] = line.split('\t');
        return { name, description, url };
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
        testItem.textContent = test.name;
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

    document.getElementById('test-menu').style.display = 'none';
    document.getElementById('test-container').style.display = 'block';
    document.getElementById('test-description').textContent = selectedTest.description;
    document.getElementById('test').innerHTML = `
                <div class="text-center my-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Загрузка...</span>
                    </div>
                    <p>Загрузка теста...</p>
                </div>
            `;

    try {
        const response = await fetch(selectedTest.url);
        if (!response.ok) throw new Error("Не удалось загрузить тест");
        const tsvData = await response.text();
        parseAndBuildTest(tsvData);
    } catch (error) {
        document.getElementById('test').innerHTML = `
                    <div class="alert alert-danger">
                        Ошибка загрузки теста: ${error.message}
                    </div>
                `;
        console.error(error);
    }
}

// Парсинг и построение теста
function parseAndBuildTest(tsvData) {
    const lines = tsvData.trim().split('\n').slice(1); // Пропускаем заголовок
    let questions = lines.map(line => {
        const [num, question, opt1, opt2, opt3, opt4, correct] = line.split('\t');
        return {
            question,
            options: [opt1, opt2, opt3, opt4],
            correct: parseInt(correct) - 1
        };
    });

    // Перемешиваем вопросы
    questions = shuffleArray(questions);

    const testContainer = document.getElementById('test');
    testContainer.innerHTML = '';
    let userAnswers = [];
    let correctAnswersMap = []; // Для хранения правильных ответов после перемешивания

    questions.forEach((q, i) => {
        // Перемешиваем варианты ответов
        const shuffledOptions = shuffleArray([...q.options]);
        // Находим новый индекс правильного ответа после перемешивания
        const correctIndex = shuffledOptions.indexOf(q.options[q.correct]);

        const questionDiv = document.createElement('div');
        questionDiv.className = 'question card mb-3';
        questionDiv.innerHTML = `
                    <div class="card-body">
                        <h5 class="card-title">${i + 1}. ${q.question}</h5>
                        <div class="options">
                            ${shuffledOptions.map((opt, j) => `
                                <div class="form-check option">
                                    <input class="form-check-input" type="radio" id="q${i}-opt${j}" name="q${i}" value="${j}">
                                    <label class="form-check-label" for="q${i}-opt${j}">${opt}</label>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                `;
        testContainer.appendChild(questionDiv);
        correctAnswersMap.push(correctIndex); // Сохраняем новый индекс правильного ответа
    });

    //     document.getElementById('submit').addEventListener('click', () => {
    //         userAnswers = questions.map((_, i) => {
    //             const selected = document.querySelector(`input[name="q${i}"]:checked`);
    //             return selected ? parseInt(selected.value) : null;
    //         });

    //         const correctAnswers = userAnswers.reduce((sum, ans, i) => {
    //             return ans === correctAnswersMap[i] ? sum + 1 : sum;
    //         }, 0);

    //         document.getElementById('result').innerHTML = `
    //                     <strong>Ваш результат:</strong> ${correctAnswers} из ${questions.length}
    //                 `;
    //         if (countAttempt == 0)
    //             Swal.fire({
    //                 title: 'Попытки закончились',
    //                 icon: 'warning',
    //                 confirmButtonText: 'OK'
    //             })
    //         else {
    //             countAttempt--;
    //             document.getElementById("countAttempt").innerHTML = countAttempt+"";
    //         }
    //     });
    // }

    console.log("Текущий user_id:", currentUserId);

    document.getElementById('submit').addEventListener('click', async () => {
        userAnswers = questions.map((_, i) => {
            const selected = document.querySelector(`input[name="q${i}"]:checked`);
            return selected ? parseInt(selected.value) : null;
        });

        const correctAnswers = userAnswers.reduce((sum, ans, i) => {
            return ans === correctAnswersMap[i] ? sum + 1 : sum;
        }, 0);

        document.getElementById('result').innerHTML = `
        <strong>Ваш результат:</strong> ${correctAnswers} из ${questions.length}
    `;

        if (countAttempt === 0) {
            Swal.fire({
                title: 'Попытки закончились',
                icon: 'warning',
                confirmButtonText: 'OK'
            });
        } else {
            countAttempt--;
            document.getElementById("countAttempt").innerHTML = countAttempt + "";

            // Отправляем результаты на сервер
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
                        total_questions: questions.length,
                        attempts_left: countAttempt
                    }),
                });

                const result = await response.json();
                if (result.status === 'success') {
                    Swal.fire({
                        title: 'Результат сохранён!',
                        icon: 'success',
                        confirmButtonText: 'OK'
                    });
                } else {
                    Swal.fire({
                        title: 'Ошибка!',
                        text: result.message || 'Не удалось сохранить результат.',
                        icon: 'error',
                        confirmButtonText: 'OK'
                    });
                }
            } catch (error) {
                Swal.fire({
                    title: 'Ошибка!',
                    text: 'Произошла ошибка при сохранении результата.',
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
                console.error(error);
            }
        }
    });
}

// Инициализация
document.addEventListener('DOMContentLoaded', () => {
    loadTestsList();
    document.getElementById('start-test').addEventListener('click', loadSelectedTest);
});



// Функция для запроса логина и пароля
// async function requestCredentials() {
//     const { value: login } = await Swal.fire({
//         title: 'Введите логин',
//         input: 'text',
//         inputPlaceholder: 'Ваш логин',
//         showCancelButton: true,
//         confirmButtonText: 'Далее',
//         cancelButtonText: 'Отмена',
//         inputValidator: (value) => {
//             if (!value) {
//                 return 'Логин не может быть пустым!';
//             }
//         }
//     });

//     if (!login) {
//         return; // Пользователь нажал "Отмена"
//     }

//     const { value: password } = await Swal.fire({
//         title: 'Введите пароль',
//         input: 'password',
//         inputPlaceholder: 'Ваш пароль',
//         showCancelButton: true,
//         confirmButtonText: 'Войти',
//         cancelButtonText: 'Отмена',
//         inputValidator: (value) => {
//             if (!value) {
//                 return 'Пароль не может быть пустым!';
//             }
//         }
//     });

//     if (password) {

//         // Здесь можно добавить логику обработки логина и пароля
//   // Отправляем логин и пароль на сервер
//                 const response = await fetch('phps/login.php', {
//                     method: 'POST',
//                     headers: {
//                         'Content-Type': 'application/json',
//                     },
//                     body: JSON.stringify({ login, password }),
//                 });

//                 const result = await response.json();

//                 if (result.status === 'success') {
//                     Swal.fire({
//                         title: 'Успешная авторизация!',
//                         icon: 'success',
//                         confirmButtonText: 'OK'
//                     });
//                     document.getElementById('mainTests').style.visibility = 'visible';
//                 } else {
//                     Swal.fire({
//                         title: 'Ошибка!',
//                         text: result.message || 'Неверный логин или пароль',
//                         icon: 'error',
//                         confirmButtonText: 'OK'
//                     });
//                 }
//             }

//     }


// Запускаем функцию при загрузке страницы
//window.onload = requestCredentials;

