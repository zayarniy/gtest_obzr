<?php
// Функция логирования
function logMessage($message, $level = 'INFO') {
    $logFile = 'app.log';
    $logEntry = date('Y-m-d H:i:s') . " [$level] $message\n";
    file_put_contents($logFile, $logEntry, FILE_APPEND);
}

?>