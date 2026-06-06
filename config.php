<?php
// ====================== НАСТРОЙКИ БАЗЫ ДАННЫХ ======================
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '0907');
define('DB_NAME', 'molizeum');

define('SITE_NAME', 'Молизеум');
define('BASE_URL', 'http://localhost/Molizeum');

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    die("Ошибка подключения к базе данных: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");

if (session_status() === PHP_SESSION_NONE) {
    session_save_path(sys_get_temp_dir());
    session_start();
}
?>
