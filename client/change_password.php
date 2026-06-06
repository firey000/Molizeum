<?php
require_once '../config.php';
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'client') {
    echo json_encode(['ok'=>false,'error'=>'Не авторизован']); exit;
}
header('Content-Type: application/json');

$uid     = (int)$_SESSION['user_id'];
$current = $_POST['current'] ?? '';
$new_pwd = $_POST['new_pwd'] ?? '';

if (strlen($new_pwd) < 6) {
    echo json_encode(['ok'=>false,'error'=>'Пароль должен быть минимум 6 символов']); exit;
}

$row = $conn->query("SELECT password FROM users WHERE id=$uid")->fetch_assoc();
if (!$row || $row['password'] !== $current) {
    echo json_encode(['ok'=>false,'error'=>'Текущий пароль неверный']); exit;
}

$stmt = $conn->prepare("UPDATE users SET password=? WHERE id=?");
$stmt->bind_param("si", $new_pwd, $uid);
$ok = $stmt->execute();
echo json_encode(['ok'=>$ok]);
