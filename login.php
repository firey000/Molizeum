<?php
require_once 'config.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: login-form.php"); exit;
}

$email    = trim($_POST['email'] ?? '');
$password = trim($_POST['password'] ?? '');

if (empty($email) || empty($password)) {
    header("Location: login-form.php?error=empty"); exit;
}

$stmt = $conn->prepare(
    "SELECT id, first_name, last_name, role, password, status 
     FROM users WHERE (email = ? OR phone = ?) LIMIT 1"
);
$stmt->bind_param("ss", $email, $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: login-form.php?error=notfound"); exit;
}

$user = $result->fetch_assoc();

if ($user['status'] === 'blocked') {
    header("Location: login-form.php?error=blocked"); exit;
}
if ($user['password'] !== $password) { // Пароль в открытом виде, как просили
    header("Location: login-form.php?error=password"); exit;
}

$_SESSION['user_id']   = $user['id'];
$_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
$_SESSION['user_role'] = $user['role'];

// ✅ Исправлены пути на существующие страницы
if ($user['role'] === 'admin') {
    header("Location: admin/clients.php");
} elseif ($user['role'] === 'employee') {
    header("Location: employee/registration.php");
} else {
    header("Location: client/services.php");
}
exit;
?>