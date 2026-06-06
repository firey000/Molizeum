<?php
require_once 'config.php';
if (isset($_SESSION['user_role'])) {
    if ($_SESSION['user_role'] === 'admin') header("Location: admin/clients.php");
    elseif ($_SESSION['user_role'] === 'employee') header("Location: employee/registration.php");
    else header("Location: login-form.php");
    exit;
}
$error = $_GET['error'] ?? '';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Вход — Молизеум</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<main class="auth-page">
    <div class="auth-brand">
        <h1>Молизеум</h1>
        <p>Электронный журнал компьютерного клуба</p>
    </div>

    <div class="auth-card">
        <div class="auth-icon-wrap">
            <div class="auth-icon">→</div>
        </div>
        <p class="auth-title">Вход в систему</p>
        <p class="auth-subtitle">Введите ваши учётные данные</p>

        <?php if ($error === 'notfound'): ?>
            <div class="alert alert-error">Пользователь не найден</div>
        <?php elseif ($error === 'password'): ?>
            <div class="alert alert-error">Неверный пароль</div>
        <?php elseif ($error === 'blocked'): ?>
            <div class="alert alert-error">Аккаунт заблокирован</div>
        <?php elseif ($error === 'empty'): ?>
            <div class="alert alert-error">Заполните все поля</div>
        <?php endif; ?>

        <form action="login.php" method="POST">
            <div class="form-group">
                <label class="form-label">Email</label>
                <input type="email" class="form-input" name="email" placeholder="Введите email" required>
            </div>
            <div class="form-group">
                <label class="form-label">Пароль</label>
                <input type="password" class="form-input" name="password" placeholder="Введите пароль" required>
            </div>
            <button type="submit" class="btn-primary">Войти</button>
        </form>

        <hr class="auth-divider">
        <p class="auth-link-row">Нет аккаунта? <a href="registration-form.php">Зарегистрироваться</a></p>
    </div>
</main>
</body>
</html>
