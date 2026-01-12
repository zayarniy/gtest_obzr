<?php
session_start();
require_once 'db_connect.php';

$token = isset($_GET['token']) ? trim($_GET['token']) : '';
$is_valid_token = false;
$user_id = 0;
$error_message = '';
$success_message = '';

if (!empty($token)) {
    try {
        $pdo = getDbConnection();
        
        // Проверяем токен
        $stmt = $pdo->prepare("
            SELECT prt.user_id, prt.expires_at, ui.login 
            FROM password_reset_tokens prt
            JOIN user_info ui ON prt.user_id = ui.id
            WHERE prt.token = ? AND prt.expires_at > NOW()
        ");
        $stmt->execute([$token]);
        $token_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($token_data) {
            $is_valid_token = true;
            $user_id = $token_data['user_id'];
            $user_login = $token_data['login'];
        } else {
            $error_message = 'Ссылка для сброса пароля недействительна или истек срок действия.';
        }
    } catch (PDOException $e) {
        $error_message = 'Ошибка проверки токена.';
    }
} else {
    $error_message = 'Неверная ссылка для сброса пароля.';
}

// Обработка POST запроса на установку нового пароля
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $is_valid_token) {
    $new_password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($new_password) || empty($confirm_password)) {
        $error_message = 'Пожалуйста, заполните все поля.';
    } elseif (strlen($new_password) < 6) {
        $error_message = 'Пароль должен содержать не менее 6 символов.';
    } elseif ($new_password !== $confirm_password) {
        $error_message = 'Пароли не совпадают.';
    } else {
        try {
            // Хэшируем новый пароль
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            
            // Обновляем пароль
            $updateStmt = $pdo->prepare("UPDATE user_info SET password = ? WHERE id = ?");
            $updateStmt->execute([$hashed_password, $user_id]);
            
            // Удаляем использованный токен
            $deleteStmt = $pdo->prepare("DELETE FROM password_reset_tokens WHERE token = ?");
            $deleteStmt->execute([$token]);
            
            $success_message = 'Пароль успешно изменен! Теперь вы можете войти в систему с новым паролем.';
            $is_valid_token = false; // Делаем форму неактивной после успешной смены пароля
            
        } catch (PDOException $e) {
            $error_message = 'Ошибка при изменении пароля. Пожалуйста, попробуйте еще раз.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Установка нового пароля - Онлайн тесты</title>
    <!-- Подключаем Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Подключаем Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .reset-password-card {
            max-width: 500px;
            width: 100%;
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
        }
        .reset-password-header {
            background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .reset-password-body {
            padding: 30px;
        }
        .logo-container {
            text-align: center;
            margin-bottom: 20px;
        }
        .logo {
            max-height: 60px;
            margin-bottom: 10px;
        }
        .password-requirements {
            background-color: #f8f9fa;
            border-left: 4px solid #0dcaf0;
            padding: 10px 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            font-size: 0.9rem;
        }
        .password-requirements ul {
            margin-bottom: 0;
            padding-left: 20px;
        }
        .btn-success {
            background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
            border: none;
        }
        .btn-success:hover {
            opacity: 0.9;
        }
    </style>
</head>
<body>
    <div class="reset-password-card">
        <div class="reset-password-header">
            <div class="logo-container">
                <img src="../logo.jpg" alt="Логотип" class="logo" onerror="this.style.display='none'">
            </div>
            <h2><i class="fas fa-lock me-2"></i>Новый пароль</h2>
            <?php if ($is_valid_token): ?>
                <p class="mb-0">Установите новый пароль для вашего аккаунта</p>
            <?php endif; ?>
        </div>
        
        <div class="reset-password-body">
            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
                <div class="text-center mt-3">
                    <a href="forgot_password.html" class="btn btn-outline-primary">
                        <i class="fas fa-redo me-2"></i>Запросить новую ссылку
                    </a>
                </div>
            <?php elseif (!empty($success_message)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle me-2"></i>
                    <?php echo htmlspecialchars($success_message); ?>
                </div>
                <div class="text-center mt-3">
                    <a href="../registration.html" class="btn btn-success">
                        <i class="fas fa-sign-in-alt me-2"></i>Перейти к входу
                    </a>
                </div>
            <?php elseif ($is_valid_token): ?>
                <?php if (isset($user_login)): ?>
                <div class="alert alert-info">
                    <i class="fas fa-user me-2"></i>
                    Сброс пароля для: <strong><?php echo htmlspecialchars($user_login); ?></strong>
                </div>
                <?php endif; ?>
                
                <div class="password-requirements">
                    <strong>Требования к паролю:</strong>
                    <ul>
                        <li>Не менее 6 символов</li>
                        <li>Рекомендуется использовать буквы, цифры и специальные символы</li>
                    </ul>
                </div>
                
                <form method="POST" id="resetPasswordForm">
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                    
                    <div class="mb-3">
                        <label for="password" class="form-label">Новый пароль</label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-lock"></i>
                            </span>
                            <input 
                                type="password" 
                                class="form-control" 
                                id="password" 
                                name="password"
                                required
                                minlength="6"
                                autocomplete="new-password"
                            >
                            <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label for="confirm_password" class="form-label">Подтвердите пароль</label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-lock"></i>
                            </span>
                            <input 
                                type="password" 
                                class="form-control" 
                                id="confirm_password" 
                                name="confirm_password"
                                required
                                minlength="6"
                                autocomplete="new-password"
                            >
                            <button class="btn btn-outline-secondary" type="button" id="toggleConfirmPassword">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <div class="form-text" id="passwordMatch"></div>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-success btn-lg">
                            <i class="fas fa-save me-2"></i>Сохранить новый пароль
                        </button>
                    </div>
                </form>
                
                <div class="text-center mt-3">
                    <a href="../registration.html" class="text-decoration-none">
                        <i class="fas fa-arrow-left me-1"></i>Вернуться к входу
                    </a>
                </div>
            <?php else: ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    Неверная или устаревшая ссылка для сброса пароля.
                </div>
                <div class="text-center">
                    <a href="forgot_password.html" class="btn btn-primary">
                        <i class="fas fa-key me-2"></i>Запросить новую ссылку
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Подключаем Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Переключение видимости пароля
            const togglePassword = document.getElementById('togglePassword');
            const toggleConfirmPassword = document.getElementById('toggleConfirmPassword');
            const passwordInput = document.getElementById('password');
            const confirmPasswordInput = document.getElementById('confirm_password');
            const passwordMatch = document.getElementById('passwordMatch');
            
            togglePassword.addEventListener('click', function() {
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
                this.querySelector('i').classList.toggle('fa-eye');
                this.querySelector('i').classList.toggle('fa-eye-slash');
            });
            
            toggleConfirmPassword.addEventListener('click', function() {
                const type = confirmPasswordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                confirmPasswordInput.setAttribute('type', type);
                this.querySelector('i').classList.toggle('fa-eye');
                this.querySelector('i').classList.toggle('fa-eye-slash');
            });
            
            // Проверка совпадения паролей
            function checkPasswordMatch() {
                const password = passwordInput.value;
                const confirmPassword = confirmPasswordInput.value;
                
                if (password === '' && confirmPassword === '') {
                    passwordMatch.textContent = '';
                    passwordMatch.className = 'form-text';
                    return;
                }
                
                if (password === confirmPassword) {
                    passwordMatch.innerHTML = '<i class="fas fa-check text-success"></i> Пароли совпадают';
                    passwordMatch.className = 'form-text text-success';
                } else {
                    passwordMatch.innerHTML = '<i class="fas fa-times text-danger"></i> Пароли не совпадают';
                    passwordMatch.className = 'form-text text-danger';
                }
            }
            
            passwordInput.addEventListener('input', checkPasswordMatch);
            confirmPasswordInput.addEventListener('input', checkPasswordMatch);
            
            // Валидация формы
            const form = document.getElementById('resetPasswordForm');
            if (form) {
                form.addEventListener('submit', function(e) {
                    const password = passwordInput.value;
                    const confirmPassword = confirmPasswordInput.value;
                    
                    if (password.length < 6) {
                        e.preventDefault();
                        alert('Пароль должен содержать не менее 6 символов');
                        passwordInput.focus();
                        return false;
                    }
                    
                    if (password !== confirmPassword) {
                        e.preventDefault();
                        alert('Пароли не совпадают. Пожалуйста, проверьте введенные данные.');
                        confirmPasswordInput.focus();
                        return false;
                    }
                    
                    return true;
                });
            }
            
            // Автофокус на поле пароля
            if (passwordInput) {
                passwordInput.focus();
            }
        });
    </script>
</body>
</html>