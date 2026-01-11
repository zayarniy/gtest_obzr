<?php
header('Content-Type: application/json');
require_once 'log.php';
require_once 'db_connect.php';

logMessage("Start registration");

try {
    // Получаем данные из POST-запроса
    $data = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        logMessage("Invalid JSON data", "Error");
        throw new Exception("Некорректные JSON данные");
    }

    // Проверяем наличие всех обязательных полей
    $requiredFields = ['lastname', 'name', 'login', 'email', 'password', 'birthdate', 'class'];
    foreach ($requiredFields as $field) {
        if (!isset($data[$field])) {
            logMessage("Missing required field: $field", "Error");
            throw new Exception("Отсутствует обязательное поле: $field");
        }
    }

    // Извлекаем данные
    $lastname = $data['lastname'];
    $name = $data['name'];
    $surname = $data['surname'] ?? '';
    $login = $data['login'];
    $email = $data['email'];
    $password = $data['password'];
    $birthdate = $data['birthdate'];
    $class = $data['class'];
    $telegram = $data['telegram'] ?? '';

    // Подключаемся к базе данных
    $pdo = getDbConnection();

    // Проверяем, существует ли пользователь с таким логином или почтой
    $stmt = $pdo->prepare("SELECT id FROM user_info WHERE login = ? OR email = ?");
    $stmt->execute([$login, $email]);

    if ($stmt->rowCount() > 0) {
        logMessage("User with this login or email already exists", "Error");
        echo json_encode([
            "status" => "error",
            "message" => "Пользователь с таким логином или почтой уже существует."
        ]);
        exit;
    }

    // Хешируем пароль
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    // Добавляем пользователя в базу данных
    $stmt = $pdo->prepare("
        INSERT INTO user_info
        (surname, name, lastname, login, email, password, birthdate, class, telegram)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $surname,
        $name,
        $lastname,
        $login,
        $email,
        $hashedPassword,
        $birthdate,
        $class,
        $telegram
    ]);

    logMessage("User registered successfully: $login");
    echo json_encode([
        "status" => "success",
        "message" => "Регистрация прошла успешно."
    ]);

} catch (PDOException $e) {
    logMessage("Database error: " . $e->getMessage(), "Error");
    echo json_encode([
        "status" => "error",
        "message" => "Ошибка базы данных: " . $e->getMessage()
    ]);
} catch (Exception $e) {
    logMessage($e->getMessage(), "Error");
    echo json_encode([
        "status" => "error",
        "message" => $e->getMessage()
    ]);
}
?>
