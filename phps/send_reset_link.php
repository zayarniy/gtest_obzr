<?php
session_start();
require_once 'db_connect.php';
require_once 'log.php';

header('Content-Type: application/json');

// Проверяем, что запрос POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Неправильный метод запроса']);
    exit();
}

// Получаем данные из запроса
$data = json_decode(file_get_contents('php://input'), true);
$email = isset($data['email']) ? trim($data['email']) : '';

// Валидация email
if (empty($email)) {
    echo json_encode(['status' => 'error', 'message' => 'Email не может быть пустым']);
    exit();
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['status' => 'error', 'message' => 'Неверный формат email']);
    exit();
}

try {
    $pdo = getDbConnection();
    
    // Ищем пользователя по email
    $stmt = $pdo->prepare("SELECT id, login, name, lastname FROM user_info WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        // Для безопасности не сообщаем, что пользователь не найден
        logMessage("Password reset requested for non-existent email: $email", "Security");
        echo json_encode([
            'status' => 'success', 
            'message' => 'Если email зарегистрирован в системе, на него будет отправлена ссылка для сброса пароля.'
        ]);
        exit();
    }
    
    // Генерируем уникальный токен сброса
    $token = bin2hex(random_bytes(32));
    $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour')); // Действителен 1 час
    
    // Сохраняем токен в базе данных
    $insertTokenSql = "
        INSERT INTO password_reset_tokens (user_id, token, expires_at) 
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE 
            token = VALUES(token),
            expires_at = VALUES(expires_at),
            created_at = NOW()
    ";
    
    $tokenStmt = $pdo->prepare($insertTokenSql);
    $tokenStmt->execute([$user['id'], $token, $expires_at]);
    
    // Формируем URL для сброса пароля
    $reset_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http")
        . "://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) 
        . "/reset_password.php?token=" . $token;
    
    // Отправляем email с ссылкой
    $emailSent = sendResetEmail($email, $user['name'], $reset_url);
    
    if ($emailSent) {
        logMessage("Password reset link sent to: $email, user_id: " . $user['id']);
        echo json_encode([
            'status' => 'success', 
            'message' => 'Ссылка для сброса пароля отправлена на ваш email. Проверьте папку "Входящие".'
        ]);
    } else {
        // Удаляем токен если не удалось отправить email
        $pdo->prepare("DELETE FROM password_reset_tokens WHERE token = ?")
            ->execute([$token]);
            
        logMessage("Failed to send reset email to: $email", "Error");
        echo json_encode([
            'status' => 'error', 
            'message' => 'Не удалось отправить email. Пожалуйста, попробуйте позже.'
        ]);
    }
    
} catch (PDOException $e) {
    error_log("Ошибка отправки ссылки сброса: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Произошла ошибка при обработке запроса']);
    exit();
} catch (Exception $e) {
    error_log("Ошибка генерации токена: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Произошла ошибка при создании токена']);
    exit();
}

/**
 * Отправка email со ссылкой сброса пароля
 */
function sendResetEmail($to, $userName, $reset_url) {
    $subject = 'Сброс пароля - Онлайн тесты';
    
    $message = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <title>Сброс пароля</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background-color: #4CAF50; color: white; padding: 15px; text-align: center; border-radius: 5px 5px 0 0; }
            .content { padding: 20px; background-color: #f9f9f9; border: 1px solid #ddd; }
            .reset-button { display: inline-block; padding: 12px 24px; background-color: #4CAF50; color: white; text-decoration: none; border-radius: 5px; margin: 15px 0; }
            .warning { color: #d32f2f; font-weight: bold; background-color: #ffebee; padding: 10px; border-radius: 3px; }
            .footer { margin-top: 20px; padding-top: 10px; border-top: 1px solid #ddd; font-size: 12px; color: #666; }
            .link-box { word-break: break-all; background-color: #f5f5f5; padding: 10px; border-radius: 3px; margin: 10px 0; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>Сброс пароля</h2>
            </div>
            <div class='content'>
                <p>Здравствуйте, " . htmlspecialchars($userName) . "!</p>
                <p>Вы запросили сброс пароля для вашего аккаунта в системе онлайн-тестов.</p>
                
                <div style='text-align: center;'>
                    <a href='$reset_url' class='reset-button' style='color: white;'>
                        <strong>СБРОСИТЬ ПАРОЛЬ</strong>
                    </a>
                </div>
                
                <p>Или скопируйте ссылку вручную:</p>
                <div class='link-box'>
                    <small>$reset_url</small>
                </div>
                
                <div class='warning'>
                    ⚠️ <strong>Внимание!</strong> 
                    <ul>
                        <li>Эта ссылка будет активна в течение 1 часа</li>
                        <li>Если вы не запрашивали сброс пароля, проигнорируйте это письмо</li>
                        <li>Никому не передавайте эту ссылку</li>
                    </ul>
                </div>
                
                <p>Если кнопка выше не работает, скопируйте ссылку в адресную строку браузера.</p>
            </div>
            <div class='footer'>
                <p>Это письмо было отправлено автоматически. Пожалуйста, не отвечайте на него.</p>
                <p>&copy; " . date('Y') . " Онлайн тесты. Все права защищены.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    // Альтернативный текст для почтовых клиентов, не поддерживающих HTML
    $altMessage = "Сброс пароля\n\n" .
                  "Здравствуйте, " . $userName . "!\n\n" .
                  "Вы запросили сброс пароля. Перейдите по ссылке:\n" .
                  $reset_url . "\n\n" .
                  "Ссылка будет активна 1 час.\n\n" .
                  "Если вы не запрашивали сброс пароля, проигнорируйте это письмо.\n\n" .
                  "© " . date('Y') . " Онлайн тесты";
    
    // Заголовки для HTML-письма
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: Онлайн тесты <noreply@" . $_SERVER['HTTP_HOST'] . ">\r\n";
    $headers .= "Reply-To: noreply@" . $_SERVER['HTTP_HOST'] . "\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();
    $headers .= "X-Priority: 1 (Highest)\r\n";
    $headers .= "Importance: High\r\n";
    
    // Отправляем email
    try {
        return mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $message, $headers);
    } catch (Exception $e) {
        error_log("Ошибка отправки email: " . $e->getMessage());
        return false;
    }
}
?>