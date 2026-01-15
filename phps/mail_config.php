<?php
// mail_config.php - настройки почты
return [
    'smtp' => [
        'host' => 'smtp.yandex.ru',           // SMTP сервер
        'username' => 'zaazaa@yandex.ru', // Ваш email (полный адрес)
        'password' => 'nfztswiuiqgysefd',    // Пароль приложения
        'secure' => 'ssl',                    // 'ssl' или 'tls'
        'port' => 465,                        // 465 для SSL, 587 для TLS
        'from_email' => 'zaazaa@yandex.ru',
        'from_name' => 'Онлайн тесты',
        'reply_to' => 'zaazaa@yandex.ru' // Email для ответов
    ],
    
    // Настройки для разных сред (разработка/продакшен)
    'debug' => [
        'enabled' => false,                   // Включить отладку PHPMailer
        'level' => 0,                         // 0 = off, 1 = client messages, 2 = client and server messages
        'show_errors' => false                // Показывать ошибки пользователю
    ],
    
    // Настройки безопасности
    'security' => [
        'token_expiry_hours' => 1,            // Срок действия токена (часов)
        'max_attempts_per_hour' => 3,         // Максимум попыток сброса в час
        'allow_debug_links' => false          // Разрешить показ ссылок при ошибке отправки (только для разработки!)
    ]
];
?>