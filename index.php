<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: registration.html"); // Перенаправляем на страницу авторизации
    exit();
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Онлайн тесты Курина Тимофея</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Личный кабинет</title>
    <!-- <link rel="stylesheet" href="{{ url_for('static', filename='style.css') }}"> -->
    <!-- Подключаем Bootstrap 5 -->
    <script src="libs/sweetalert2@11.js"></script>
    <script src="script.js" defer></script>
    <link href="style.css" rel="stylesheet">
    <link href="libs/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container py-4">
        <!-- Шапка с логотипом -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <img src="logo.jpg" alt="Логотип" class="logo" onclick="window.location.reload();">
            </div>
            <h1 class="text-center flex-grow-1">Онлайн тесты</h1>
            <div>
                <button id="profile-button" class="btn btn-sm btn-outline-secondary ms-3">Личный кабинет</button>
                <a href="phps/logout.php"><button class="btn btn-sm btn-outline-danger ms-3">Выход</button></a>
            </div>
            <div id="test-result-button" style='visibility:collapse;'>
            <a href='phps/all_test_results.php'><button  class="btn btn-sm btn-outline-secondary ms-3">Результаты тестов</button></a>
            </div>
        </div>
        <h1>Добро пожаловать,
            <?php
            if (isset($_SESSION['user_login'])) {
                echo htmlspecialchars($_SESSION['user_login']);
            } else {
                echo "Гость";
            }
            ?>
        !
        </h1>
        <!-- Блок загрузки списка тестов -->
        <div id="mainTests" style="visibility:visible;">
            <div id="loading-tests" class="text-center my-5">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Загрузка...</span>
                </div>
                <p>Загрузка списка тестов...</p>
            </div>
            <!-- Меню выбора теста -->
            <div id="test-menu" class="card shadow-sm p-4 mb-4" style="display: none;">
                <h3 class="mb-3">Выберите тест:</h3>
                <div id="test-list" class="list-group"></div>
                <button id="start-test" class="btn btn-primary mt-3" style="display: none;">Пуск</button>
            </div>
            <!-- Контейнер для теста -->
            <div id="test-container" style="display: none;">
                <div class="card shadow-sm p-4 mb-4">
                    <div id="test-description" class="alert alert-info mb-4"></div>
                    <div id="test"></div>
                    <button id="submit" class="btn btn-success mt-3">Проверить результаты(<span id='countAttempt'>3</span>)</button>
                    <div id="result" class="alert alert-success mt-3"></div>
                </div>
            </div>
        </div>
    </div>

<!-- Модальное окно для личного кабинета -->
<div class="modal fade" id="profileModal" tabindex="-1" aria-labelledby="profileModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="profileModalLabel">Личный кабинет</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
            </div>
            <div class="modal-body">
                <form id="updateProfileForm">
                    <div class="mb-3">
                        <label for="updateLastname" class="form-label">Фамилия</label>
                        <input type="text" class="form-control" id="updateLastname" required>
                    </div>
                    <div class="mb-3">
                        <label for="updateName" class="form-label">Имя</label>
                        <input type="text" class="form-control" id="updateName" required>
                    </div>
                    <div class="mb-3">
                        <label for="updateSurname" class="form-label">Отчество</label>
                        <input type="text" class="form-control" id="updateSurname">
                    </div>
                    <div class="mb-3">
                        <label for="updateEmail" class="form-label">Почта</label>
                        <input type="email" class="form-control" id="updateEmail" required>
                    </div>
                    <div class="mb-3">
                        <label for="updateTelegram" class="form-label">Telegram</label>
                        <input type="text" class="form-control" id="updateTelegram" placeholder="@username">
                    </div>
                    <div class="mb-3">
                        <label for="updatePassword" class="form-label">Новый пароль (оставьте пустым, чтобы не менять)</label>
                        <input type="password" class="form-control" id="updatePassword">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-danger" id="deleteAccount">Удалить аккаунт</button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрыть</button>
                <button type="button" class="btn btn-primary" id="saveProfile">Сохранить изменения</button>
            </div>
        </div>
    </div>
</div>

<script>
    // Передаём user_id в JavaScript
    const currentUserId = <?php echo isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'null'; ?>;
   const currentUserLogin = '<?php echo isset($_SESSION['user_login']) ? $_SESSION['user_login'] : 'null'; ?>';
    console.log(currentUserLogin);
    if (currentUserLogin=='admin')
    {
        document.getElementById('test-result-button').style.visibility='visible';
    }
    else{
        document.getElementById('test-result-button').style.visibility='collapse';
    }

    // Загрузка данных пользователя в модальное окно
    async function loadUserData() {
        try {
            const response = await fetch('phps/get_user_data.php');
            const userData = await response.json();
            if (userData.status === 'success') {
                document.getElementById('updateLastname').value = userData.lastname || '';
                document.getElementById('updateName').value = userData.name || '';
                document.getElementById('updateSurname').value = userData.surname || '';
                document.getElementById('updateEmail').value = userData.email || '';
                document.getElementById('updateTelegram').value = userData.telegram || '';
            } else {
                Swal.fire({
                    title: 'Ошибка!',
                    text: userData.message || 'Не удалось загрузить данные пользователя.',
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
            }
        } catch (error) {
            Swal.fire({
                title: 'Ошибка!',
                text: 'Произошла ошибка при загрузке данных.',
                icon: 'error',
                confirmButtonText: 'OK'
            });
            console.error(error);
        }
    }
    // Сохранение изменений профиля
    document.getElementById('saveProfile').addEventListener('click', async () => {
        const lastname = document.getElementById('updateLastname').value.trim();
        const name = document.getElementById('updateName').value.trim();
        const surname = document.getElementById('updateSurname').value.trim();
        const email = document.getElementById('updateEmail').value.trim();
        const telegram = document.getElementById('updateTelegram').value.trim();
        const password = document.getElementById('updatePassword').value;
        if (!lastname || !name || !email) {
            Swal.fire({
                title: 'Ошибка!',
                text: 'Пожалуйста, заполните все обязательные поля.',
                icon: 'error',
                confirmButtonText: 'OK'
            });
            return;
        }
        try {
            const response = await fetch('phps/update_profile.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    lastname,
                    name,
                    surname,
                    email,
                    telegram,
                    password
                }),
            });
            const result = await response.json();
            if (result.status === 'success') {
                Swal.fire({
                    title: 'Успех!',
                    text: 'Данные успешно обновлены!',
                    icon: 'success',
                    confirmButtonText: 'OK'
                });
                // Обновляем приветствие
                document.querySelector('h1').innerHTML = `
                    Добро пожаловать, ${lastname} ${name}!
                    <button id="profile-button" class="btn btn-sm btn-outline-secondary ms-3">Личный кабинет</button>
                `;
            } else {
                Swal.fire({
                    title: 'Ошибка!',
                    text: result.message || 'Произошла ошибка при обновлении данных.',
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
            }
        } catch (error) {
            Swal.fire({
                title: 'Ошибка!',
                text: 'Произошла ошибка при отправке данных.',
                icon: 'error',
                confirmButtonText: 'OK'
            });
            console.error(error);
        }
    });
    // Удаление аккаунта
    document.getElementById('deleteAccount').addEventListener('click', async () => {
        Swal.fire({
            title: 'Вы уверены?',
            text: 'Вы не сможете восстановить аккаунт!',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Да, удалить!',
            cancelButtonText: 'Отмена'
        }).then(async (result) => {
            if (result.isConfirmed) {
                try {
                    const response = await fetch('phps/delete_account.php', {
                        method: 'POST',
                    });
                    const result = await response.json();
                    if (result.status === 'success') {
                        Swal.fire({
                            title: 'Удалено!',
                            text: 'Ваш аккаунт был удалён.',
                            icon: 'success',
                            confirmButtonText: 'OK'
                        }).then(() => {
                            window.location.href = '../registration.html';
                        });
                    } else {
                        Swal.fire({
                            title: 'Ошибка!',
                            text: result.message || 'Произошла ошибка при удалении аккаунта.',
                            icon: 'error',
                            confirmButtonText: 'OK'
                        });
                    }
                } catch (error) {
                    Swal.fire({
                        title: 'Ошибка!',
                        text: 'Произошла ошибка при удалении аккаунта.',
                        icon: 'error',
                        confirmButtonText: 'OK'
                    });
                    console.error(error);
                }
            }
        });
    });
    // Открытие модального окна
    document.getElementById('profile-button').addEventListener('click', () => {
        loadUserData();
        const modal = new bootstrap.Modal(document.getElementById('profileModal'));
        modal.show();
    });
</script>
    <!-- Подключаем Bootstrap JS -->
    <script src="libs/bootstrap.bundle.min.js"></script>
</body>
</html>
