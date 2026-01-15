<?php
// Включите это в самом начале файла
error_reporting(0);
ini_set('display_errors', 0);

session_start();
require_once 'db_connect.php';
require_once 'log.php';

// Подключаем PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

// Путь к PHPMailer (настройте под вашу структуру)
require __DIR__ . '/PHPMailer/src/Exception.php';
require __DIR__ . '/PHPMailer/src/PHPMailer.php';
require __DIR__ . '/PHPMailer/src/SMTP.php';

header('Content-Type: application/json');

// Проверяем, что запрос POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Неправильный метод запроса']);
    exit();
}

// Получаем данные из запроса
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode(['status' => 'error', 'message' => 'Неверный формат JSON в запросе']);
    exit();
}

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
    
    logMessage("Password reset requested for user: " . $user['login'] . " ($email)");
    
    // Генерируем уникальный токен сброса
    $token = bin2hex(random_bytes(32));
    $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));
    
    // Создаем таблицу если она не существует
    $createTableSql = "
        CREATE TABLE IF NOT EXISTS password_reset_tokens (
            id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT NOT NULL,
            token VARCHAR(64) NOT NULL,
            expires_at DATETIME NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            used_at TIMESTAMP NULL,
            UNIQUE KEY unique_token (token),
            INDEX idx_user_id (user_id),
            INDEX idx_expires_at (expires_at),
            FOREIGN KEY (user_id) REFERENCES user_info(id) ON DELETE CASCADE
        )
    ";
    $pdo->exec($createTableSql);
    
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
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $script_dir = dirname($_SERVER['SCRIPT_NAME']);
    
    // Корректное формирование URL
    $reset_url = $protocol . '://' . $host . $script_dir . '/reset_password.php?token=' . urlencode($token);
    
    logMessage("Reset URL generated: $reset_url");
    
    // Отправляем email с ссылкой через PHPMailer
    $emailSent = sendResetEmailPHPMailer($email, $user['name'], $reset_url);
    
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
        
        // В режиме разработки можно показать ссылку
        if ($_SERVER['HTTP_HOST'] === 'localhost' || strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false) {
            echo json_encode([
                'status' => 'debug', 
                'message' => 'В режиме разработки: не удалось отправить email',
                'reset_url' => $reset_url
            ]);
        } else {
            echo json_encode([
                'status' => 'error', 
                'message' => 'Не удалось отправить email. Пожалуйста, обратитесь к администратору.'
            ]);
        }
    }
    
} catch (PDOException $e) {
    logMessage("Database error in send_reset_link: " . $e->getMessage(), "Error");
    echo json_encode(['status' => 'error', 'message' => 'Произошла ошибка при обработке запроса']);
    exit();
} catch (Exception $e) {
    logMessage("Error in send_reset_link: " . $e->getMessage(), "Error");
    echo json_encode(['status' => 'error', 'message' => 'Произошла внутренняя ошибка']);
    exit();
}

/**
 * Отправка email со ссылкой сброса пароля через PHPMailer
 */
function sendResetEmailPHPMailer($to, $userName, $reset_url) {
    try {
        $mail = new PHPMailer(true);
        
        // ==== НАСТРОЙКИ SMTP (ЗАПОЛНИТЕ СВОИМИ ДАННЫМИ!) ====
        $mail->isSMTP();
        $mail->Host = 'smtp.yandex.ru';           // SMTP сервер
        $mail->SMTPAuth = true;
        $mail->Username = 'zaazaa@yandex.ru';  // Ваш полный email
        $mail->Password = 'nfztswiuiqgysefd'; // Пароль приложения
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // SSL
        $mail->Port = 465;                        // 465 для SSL
        
        // Для отладки (временно включите)
        // $mail->SMTPDebug = SMTP::DEBUG_SERVER;
        
        // Отправитель и получатель
        $mail->setFrom('zaazaa@yandex.ru', 'Онлайн тесты');
        $mail->addAddress($to, $userName);
        $mail->addReplyTo('zaazaa@yandex.ru', 'Онлайн тесты');
        
        // Контент письма
        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        $mail->Subject = 'Сброс пароля - Онлайн тесты';
        
        // HTML тело письма
        $html_content = "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Сброс пароля</title></head><body style='font-family: Arial, sans-serif;'>
            <div style='max-width: 600px; margin: 0 auto;'>
                <div style='background-color: #4CAF50; color: white; padding: 20px; text-align: center;'>
                    <h2>Сброс пароля</h2>
                </div>
                <div style='padding: 25px; background-color: #f9f9f9; border: 1px solid #ddd;'>
                    <p>Здравствуйте, " . htmlspecialchars($userName) . "!</p>
                    <p>Вы запросили сброс пароля для вашего аккаунта.</p>
                    <div style='text-align: center; margin: 20px 0;'>
                        <a href='" . htmlspecialchars($reset_url) . "' style='display: inline-block; padding: 12px 24px; background-color: #4CAF50; color: white; text-decoration: none; border-radius: 5px;'>
                            <strong>СБРОСИТЬ ПАРОЛЬ</strong>
                        </a>
                    </div>
                    <p>Или скопируйте ссылку:</p>
                    <div style='background-color: #f5f5f5; padding: 10px; border-radius: 3px; word-break: break-all; font-size: 12px;'>
                        " . htmlspecialchars($reset_url) . "
                    </div>
                    <div style='color: #d32f2f; background-color: #ffebee; padding: 10px; border-radius: 3px; margin-top: 15px;'>
                        <strong>Внимание!</strong> Ссылка активна 1 час.
                    </div>
                </div>
            </div>
        </body></html>";
        
        $mail->Body = $html_content;
        
        // Текстовое тело
        $mail->AltBody = "Сброс пароля\n\nЗдравствуйте, $userName!\n\nВы запросили сброс пароля. Перейдите по ссылке:\n$reset_url\n\nСсылка активна 1 час.\n\n© " . date('Y') . " Онлайн тесты";
        
        // Отправляем
        return $mail->send();
        
    } catch (Exception $e) {
        logMessage("PHPMailer Error: " . $e->getMessage(), "Error");
        return false;
    }
}
