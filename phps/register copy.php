<?php
require_once 'log.php';
require_once 'db_connect.php';

logMessage("Start registration");

try {
    $conn = new mysqli($host, $user, $pass, $db);
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }

    // Получаем данные из POST-запроса
    $data = json_decode(file_get_contents('php://input'), true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        logMessage("Invalid JSON data", "Error");
        throw new Exception("Invalid JSON data");
    }

    // Проверяем наличие всех обязательных полей
    $requiredFields = ['lastname', 'name', 'login', 'email', 'password', 'birthdate', 'class'];
    foreach ($requiredFields as $field) {
        if (!isset($data[$field])) {
            logMessage("Missing required field: $field", "Error");
            throw new Exception("Missing required field: $field");
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

    // Проверяем, существует ли пользователь с таким логином или почтой
    $stmt = $conn->prepare("SELECT id FROM user_info WHERE login = ? OR email = ?");
    $stmt->bind_param("ss", $login, $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        logMessage("User with this login or email already exists", "Error");
        echo json_encode(["status" => "error", "message" => "Пользователь с таким логином или паролем уже существует."]);
        //exit("not login");
        //throw new Exception("Пользователь с таким логином или почтой уже существует.");
    } 
    else 
    {

        // Хешируем пароль (рекомендуется использовать password_hash)
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        // Добавляем пользователя в базу данных
        $stmt = $conn->prepare("
        INSERT INTO user_info
        (surname, name, lastname, login, email, password, birthdate, class, telegram)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
        $stmt->bind_param(
            "sssssssss",
            $surname,
            $name,
            $lastname,
            $login,
            $email,
            $hashedPassword,
            $birthdate,
            $class,
            $telegram
        );

        if ($stmt->execute()) {
            logMessage("User registered successfully: $login");
            echo json_encode(["status" => "success", "message" => "Регистрация прошла успешно."]);
        } else {
            logMessage("Error during registration: " . $stmt->error, "Error");
            throw new Exception("Error during registration: " . $stmt->error);
        }
    }

    $stmt->close();
    $conn->close();
} 
catch (Exception $e) 
{
    logMessage($e->getMessage(), "ERROR");
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>
