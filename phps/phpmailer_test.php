<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

$mail = new PHPMailer(true);
try {
    // Настройки сервера
    $mail->isSMTP();
    $mail->Host       = 'smtp.yandex.ru'; // Ваш SMTP-сервер
    $mail->SMTPAuth   = true;
    $mail->Username   = 'zaazaa@yandex.ru'; // Ваш полный email
    $mail->Password   = 'nfztswiuiqgysefd'; // Пароль от почты
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // SSL
    $mail->Port       = 465; // Порт для SSL

    // Отправитель и получатель
    $mail->setFrom('zaazaa@yandex.ru', 'Ваше Имя/Сайт');
    $mail->addAddress('zayarniy@gmail.com', 'Имя Получателя');

    // Содержание письма
    $mail->isHTML(true);
    $mail->Subject = 'Тема письма';
    $mail->Body    = 'Это <b>HTML-сообщение</b> с вашего сайта.';

    $mail->send();
    echo 'Письмо успешно отправлено!';
} catch (Exception $e) {
    echo "Не удалось отправить письмо. Ошибка: {$mail->ErrorInfo}";
}
?>