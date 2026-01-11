<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: registration.html"); // Перенаправляем на страницу авторизации
    exit();
}
?>
