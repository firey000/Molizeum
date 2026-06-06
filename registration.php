<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: registration-form.php"); exit;
}

$first_name       = trim($_POST['first_name'] ?? '');
$last_name        = trim($_POST['last_name'] ?? '');
$email            = trim($_POST['email'] ?? '');
$phone            = trim($_POST['phone'] ?? '');
$password         = trim($_POST['password'] ?? '');
$password_confirm = trim($_POST['password_confirm'] ?? '');

if ($password !== $password_confirm) {
    header("Location: registration-form.php?error=passwords"); exit;
}

if (strlen($password) < 6) {
    header("Location: registration-form.php?error=short"); exit;
}

$check = $conn->prepare("SELECT id FROM users WHERE email = ? OR phone = ?");
$check->bind_param("ss", $email, $phone);
$check->execute();
if ($check->get_result()->num_rows > 0) {
    header("Location: registration-form.php?error=exists"); exit;
}

$stmt = $conn->prepare(
    "INSERT INTO users (first_name, last_name, email, phone, password, role, status)
     VALUES (?, ?, ?, ?, ?, 'client', 'active')"
);
$stmt->bind_param("sssss", $first_name, $last_name, $email, $phone, $password);

if ($stmt->execute()) {
    header("Location: login-form.php?registered=1"); exit;
} else {
    echo "Ошибка при регистрации: " . $conn->error;
}
?>
