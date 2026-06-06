<?php
require_once 'config.php';
$error = $_GET['error'] ?? '';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Регистрация — Молизеум</title>
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
            <div class="auth-icon" style="background:#dcfce7">👤</div>
        </div>
        <p class="auth-title">Регистрация</p>
        <p class="auth-subtitle">Создайте новый аккаунт</p>

        <?php if ($error === 'exists'): ?>
            <div class="alert alert-error">Пользователь с таким email или телефоном уже существует</div>
        <?php elseif ($error === 'passwords'): ?>
            <div class="alert alert-error">Пароли не совпадают</div>
        <?php elseif ($error === 'short'): ?>
            <div class="alert alert-error">Пароль должен содержать минимум 6 символов</div>
        <?php endif; ?>

        <form action="registration.php" method="POST">
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Имя *</label>
                    <input type="text" class="form-input" name="first_name" placeholder="Иван" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Фамилия *</label>
                    <input type="text" class="form-input" name="last_name" placeholder="Иванов" required>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Email *</label>
                <input type="email" class="form-input" name="email" placeholder="email@example.com" required>
            </div>
            <div class="form-group">
                <label class="form-label">Телефон *</label>
                <input type="tel" class="form-input" name="phone" placeholder="+7 (999) 123-45-67" required>
            </div>
            <div class="form-group">
                <label class="form-label">Пароль *</label>
                <input type="password" class="form-input" name="password" placeholder="Минимум 6 символов" required>
            </div>
            <div class="form-group">
                <label class="form-label">Подтвердите пароль *</label>
                <input type="password" class="form-input" name="password_confirm" placeholder="Повторите пароль" required>
            </div>
            <button type="submit" class="btn-primary">Зарегистрироваться</button>
        </form>

        <hr class="auth-divider">
        <p class="auth-link-row">Уже есть аккаунт? <a href="login-form.php">Войти</a></p>
    </div>
</main>
</body>
</html>
