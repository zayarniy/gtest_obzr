<?php
// test_mail_capability.php
echo "<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <title>Тест отправки почты</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        .success { color: green; }
        .error { color: red; }
        .warning { color: orange; }
        .info { background: #f0f0f0; padding: 10px; border-radius: 5px; }
        pre { background: #f5f5f5; padding: 10px; border-radius: 5px; }
        .test-box { border: 1px solid #ddd; padding: 15px; margin: 10px 0; border-radius: 5px; }
    </style>
</head>
<body>
    <h2>📧 Тестирование возможности отправки почты</h2>";

// 1. Проверка базовых функций
echo "<div class='test-box'>
        <h3>1. Проверка функции mail()</h3>";

if (function_exists('mail')) {
    echo "<p class='success'>✓ Функция mail() доступна</p>";
    
    // Проверка сигнатуры функции
    $reflection = new ReflectionFunction('mail');
    $params = $reflection->getNumberOfParameters();
    echo "<p>Количество параметров: $params</p>";
    
    if ($params >= 5) {
        echo "<p class='success'>✓ Поддерживается 5-й параметр (additional_parameters)</p>";
    } else {
        echo "<p class='warning'>⚠ Функция поддерживает только $params параметров (5-й параметр может быть недоступен)</p>";
    }
} else {
    echo "<p class='error'>✗ Функция mail() недоступна</p>";
}

echo "</div>";

// 2. Проверка конфигурации PHP
echo "<div class='test-box'>
        <h3>2. Конфигурация PHP</h3>
        <pre>";

$configs = [
    'PHP Version' => phpversion(),
    'SMTP' => ini_get('SMTP'),
    'smtp_port' => ini_get('smtp_port'),
    'sendmail_from' => ini_get('sendmail_from'),
    'sendmail_path' => ini_get('sendmail_path'),
    'mail.add_x_header' => ini_get('mail.add_x_header'),
    'mail.force_extra_parameters' => ini_get('mail.force_extra_parameters'),
    'disable_functions' => ini_get('disable_functions'),
    'safe_mode' => ini_get('safe_mode')
];

foreach ($configs as $key => $value) {
    echo "$key: " . (empty($value) ? '(не задано)' : htmlspecialchars($value)) . "\n";
}

echo "</pre></div>";

// 3. Проверка домена и хоста
echo "<div class='test-box'>
        <h3>3. Информация о сервере</h3>
        <pre>";

$server_info = [
    'HTTP_HOST' => $_SERVER['HTTP_HOST'] ?? 'не определен',
    'SERVER_NAME' => $_SERVER['SERVER_NAME'] ?? 'не определен',
    'SERVER_ADDR' => $_SERVER['SERVER_ADDR'] ?? 'не определен',
    'SERVER_SOFTWARE' => $_SERVER['SERVER_SOFTWARE'] ?? 'не определен'
];

foreach ($server_info as $key => $value) {
    echo "$key: " . htmlspecialchars($value) . "\n";
}

echo "</pre></div>";

// 4. Тест отправки email (опционально)
echo "<div class='test-box'>
        <h3>4. Тест отправки email</h3>";

if (isset($_POST['test_email'])) {
    $test_email = filter_var($_POST['test_email'], FILTER_VALIDATE_EMAIL);
    
    if ($test_email) {
        echo "<form method='post' style='margin-bottom: 15px;'>
                <input type='hidden' name='do_test' value='1'>
                <input type='hidden' name='test_email' value='" . htmlspecialchars($test_email) . "'>
                <p>Отправляем тестовое письмо на: <strong>" . htmlspecialchars($test_email) . "</strong></p>
                <button type='submit' class='btn'>Начать тест</button>
              </form>";
    }
} else {
    echo "<form method='post'>
            <label for='test_email'>Введите email для теста:</label><br>
            <input type='email' name='test_email' id='test_email' required 
                   placeholder='ваш_email@gmail.com' style='width: 300px; padding: 5px; margin: 5px 0;'>
            <br>
            <button type='submit' style='padding: 8px 15px; margin-top: 10px;'>Протестировать</button>
          </form>";
}

// Выполнение теста отправки
if (isset($_POST['do_test']) && isset($_POST['test_email'])) {
    $test_email = $_POST['test_email'];
    
    echo "<h4>Результаты теста:</h4>";
    
    // Тест 1: Простая отправка без 5-го параметра
    echo "<p><strong>Тест 1:</strong> mail() без дополнительных параметров... ";
    $subject1 = "Тест отправки 1 - " . date('H:i:s');
    $message1 = "Это тестовое письмо 1 с сервера " . $_SERVER['HTTP_HOST'];
    $headers1 = "From: test@" . $_SERVER['HTTP_HOST'] . "\r\n";
    
    $result1 = @mail($test_email, $subject1, $message1, $headers1);
    echo $result1 ? "<span class='success'>✓ Отправлено</span>" : "<span class='error'>✗ Ошибка</span>";
    echo "</p>";
    
    // Тест 2: С кодировкой темы
    echo "<p><strong>Тест 2:</strong> mail() с кодировкой темы... ";
    $subject2 = "Тест отправки 2 с UTF-8 - " . date('H:i:s');
    $encoded_subject = '=?UTF-8?B?' . base64_encode($subject2) . '?=';
    $message2 = "Это тестовое письмо 2 с UTF-8 темой";
    $headers2 = "From: test@" . $_SERVER['HTTP_HOST'] . "\r\n";
    
    $result2 = @mail($test_email, $encoded_subject, $message2, $headers2);
    echo $result2 ? "<span class='success'>✓ Отправлено</span>" : "<span class='error'>✗ Ошибка</span>";
    echo "</p>";
    
    // Тест 3: С 5-м параметром (проблемный)
    echo "<p><strong>Тест 3:</strong> mail() с 5-м параметром... ";
    $subject3 = "Тест отправки 3 - " . date('H:i:s');
    $message3 = "Это тестовое письмо 3 с 5-м параметром";
    $headers3 = "From: test@" . $_SERVER['HTTP_HOST'] . "\r\n";
    
    try {
        $result3 = @mail($test_email, $subject3, $message3, $headers3, "-ftest@" . $_SERVER['HTTP_HOST']);
        echo $result3 ? "<span class='success'>✓ Отправлено</span>" : "<span class='error'>✗ Ошибка</span>";
        
        // Проверяем ошибки
        $last_error = error_get_last();
        if ($last_error) {
            echo "<br><small class='warning'>Ошибка: " . htmlspecialchars($last_error['message']) . "</small>";
        }
    } catch (Error $e) {
        echo "<span class='error'>✗ Ошибка выполнения: " . htmlspecialchars($e->getMessage()) . "</span>";
    }
    echo "</p>";
    
    // Тест 4: HTML письмо
    echo "<p><strong>Тест 4:</strong> HTML письмо... ";
    $subject4 = "Тест HTML письма - " . date('H:i:s');
    $message4 = "<!DOCTYPE html>
    <html>
    <head><meta charset='UTF-8'></head>
    <body>
        <h2>Тестовое HTML письмо</h2>
        <p>Отправлено с сервера: " . $_SERVER['HTTP_HOST'] . "</p>
        <p>Время: " . date('Y-m-d H:i:s') . "</p>
    </body>
    </html>";
    $headers4 = "MIME-Version: 1.0\r\n";
    $headers4 .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers4 .= "From: test@" . $_SERVER['HTTP_HOST'] . "\r\n";
    
    $result4 = @mail($test_email, $subject4, $message4, $headers4);
    echo $result4 ? "<span class='success'>✓ Отправлено</span>" : "<span class='error'>✗ Ошибка</span>";
    echo "</p>";
    
    echo "<div class='info'>
            <p><strong>Примечание:</strong> Проверьте папку 'Входящие' и 'Спам' на почте " . htmlspecialchars($test_email) . "</p>
            <p>Если письма не пришли, проблема может быть в настройках почты на хостинге.</p>
          </div>";
}

echo "</div>";

// 5. Рекомендации
echo "<div class='test-box'>
        <h3>5. Рекомендации</h3>
        <ol>
            <li>Если функция mail() недоступна - обратитесь к хостеру</li>
            <li>Если 5-й параметр не работает - не используйте его в коде</li>
            <li>Для надежной отправки рассмотрите:
                <ul>
                    <li>Настройку почты в панели управления хостингом</li>
                    <li>Использование SMTP через PHPMailer</li>
                    <li>Сервисы вроде SendGrid, Mailgun</li>
                </ul>
            </li>
            <li>Проверьте SPF/DKIM записи для вашего домена</li>
        </ol>
        
        <h4>Быстрые команды для проверки (если есть доступ к SSH):</h4>
        <pre>
# Проверка почтовых записей MX
nslookup -type=mx " . $_SERVER['HTTP_HOST'] . "

# Проверка порта SMTP (25)
telnet " . $_SERVER['HTTP_HOST'] . " 25

# Проверка sendmail
which sendmail
        </pre>
    </div>";

// 6. Быстрая проверка без формы
echo "<div class='test-box'>
        <h3>6. Быстрая проверка (автоматическая)</h3>";

// Проверка через mail() с заглушкой
echo "<p>Проверка возможности вызова mail()... ";
$test_result = @mail('test@example.com', 'test', 'test', 'From: test@test.com');
if ($test_result === false) {
    $last_error = error_get_last();
    echo "<span class='error'>✗ Ошибка</span>";
    if ($last_error) {
        echo "<br><small>" . htmlspecialchars($last_error['message']) . "</small>";
    }
} else {
    echo "<span class='success'>✓ Функция работает</span>";
}
echo "</p>";

// Проверка записи Return-Path
echo "<p>Проверка Return-Path... ";
$headers_with_return = "From: test@" . $_SERVER['HTTP_HOST'] . "\r\n";
$headers_with_return .= "Return-Path: test@" . $_SERVER['HTTP_HOST'] . "\r\n";

$test_result2 = @mail('test@example.com', 'test return', 'test', $headers_with_return);
echo $test_result2 ? "<span class='success'>✓ Работает</span>" : "<span class='error'>✗ Ошибка</span>";
echo "</p>";

echo "</div>";

echo "<hr>
      <p><strong>Следующие шаги:</strong></p>
      <ul>
          <li>Исправьте код отправки почты (уберите 5-й параметр)</li>
          <li>Если не работает - используйте альтернативные методы отправки</li>
          <li>Для сброса пароля можно временно показывать ссылку на экране</li>
      </ul>
      
      <p><a href='forgot_password.html'>← Вернуться к восстановлению пароля</a></p>";

echo "</body></html>";
?>