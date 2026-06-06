<?php
// config.php уже подключён в родительском файле (require_once не подключит повторно,
// но session_start внутри него выдаст warning если сессия уже идёт — поэтому
// просто проверяем что переменные уже доступны)
if (!isset($conn)) { require_once '../config.php'; }

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'client') {
    header("Location: ../login-form.php"); exit;
}

$uid     = $_SESSION['user_id'];
$current = basename($_SERVER['PHP_SELF']);

// Актуальный баланс из БД
$row = $conn->query("SELECT first_name, last_name, balance FROM users WHERE id=$uid")->fetch_assoc();
$header_balance = number_format((float)$row['balance'], 0, '.', ' ');
$fullname = htmlspecialchars($row['first_name'] . ' ' . $row['last_name']);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Личный кабинет — Молизеум</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>

<header class="panel-header">
    <div class="panel-header-left">
        <a href="services.php" class="panel-logo">Молизеум</a>
        <span class="panel-subtitle">Личный кабинет · Добро пожаловать, <?= $fullname ?>!</span>
    </div>
    <div class="panel-header-right">
        <div style="text-align:right;line-height:1.2">
            <div style="font-size:11px;color:#9ca3af;font-weight:500">Баланс:</div>
            <div style="font-size:15px;font-weight:700;color:#111"><?= $header_balance ?> ₽</div>
        </div>
        <button onclick="openChangePwd()"
                style="display:inline-flex;align-items:center;gap:6px;padding:7px 14px;
                       border:1px solid var(--gray-200);border-radius:var(--radius-sm);
                       background:var(--white);font-size:13px;font-weight:500;
                       cursor:pointer;color:var(--gray-800);margin-right:4px">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 013 3m3 0a6 6 0 01-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.169.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1121.75 8.25z"/></svg>
            Сменить пароль
        </button>
        <a href="../logout.php" class="btn-logout">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l-3 3m0 0l3 3m-3-3h12.75"/></svg>
            Выйти
        </a>
    </div>
</header>

<nav class="panel-nav">
    <a href="services.php" class="<?= $current === 'services.php' ? 'active' : '' ?>">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 17.25v1.007a3 3 0 01-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0115 18.257V17.25m6-12V15a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 15V5.25m18 0A2.25 2.25 0 0018.75 3H5.25A2.25 2.25 0 003 5.25m18 0V12a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 12V5.25"/></svg>
        Услуги
    </a>
    <a href="booking.php" class="<?= $current === 'booking.php' ? 'active' : '' ?>">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 9v7.5"/></svg>
        Бронирование
    </a>
    <a href="promotions.php" class="<?= $current === 'promotions.php' ? 'active' : '' ?>">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 003 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 005.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 009.568 3z"/><path stroke-linecap="round" stroke-linejoin="round" d="M6 6h.008v.008H6V6z"/></svg>
        Акции
    </a>
    <a href="payments.php" class="<?= $current === 'payments.php' ? 'active' : '' ?>">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z"/></svg>
        Платежи
    </a>
</nav>

<!-- ===== Модалка смены пароля ===== -->
<div id="pwd-modal" style="display:none;position:fixed;inset:0;z-index:2000;
     background:rgba(0,0,0,.45);align-items:center;justify-content:center">
    <div style="background:#fff;border-radius:12px;padding:28px 30px;
                width:100%;max-width:380px;box-shadow:0 8px 40px rgba(0,0,0,.18)">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px">
            <div style="font-size:16px;font-weight:700">🔒 Смена пароля</div>
            <button onclick="closeChangePwd()" style="border:none;background:none;font-size:20px;cursor:pointer;color:#9ca3af">✕</button>
        </div>
        <div id="pwd-msg"></div>
        <div class="ep-form-group" style="margin-bottom:12px">
            <label class="form-label">Текущий пароль</label>
            <input type="password" class="form-input" id="pwd-current" placeholder="Введите текущий пароль">
        </div>
        <div class="ep-form-group" style="margin-bottom:12px">
            <label class="form-label">Новый пароль</label>
            <input type="password" class="form-input" id="pwd-new" placeholder="Минимум 6 символов">
        </div>
        <div class="ep-form-group" style="margin-bottom:18px">
            <label class="form-label">Подтвердите пароль</label>
            <input type="password" class="form-input" id="pwd-confirm" placeholder="Повторите новый пароль">
        </div>
        <div style="display:flex;gap:10px">
            <button class="btn-primary" style="flex:1" onclick="submitChangePwd()">Сохранить</button>
            <button onclick="closeChangePwd()"
                    style="flex:1;padding:10px;border:1px solid #e5e7eb;border-radius:8px;
                           background:#fff;font-size:14px;font-weight:500;cursor:pointer">
                Отмена
            </button>
        </div>
    </div>
</div>

<script>
function openChangePwd() {
    document.getElementById('pwd-current').value = '';
    document.getElementById('pwd-new').value = '';
    document.getElementById('pwd-confirm').value = '';
    document.getElementById('pwd-msg').innerHTML = '';
    const m = document.getElementById('pwd-modal');
    m.style.display = 'flex';
}
function closeChangePwd() {
    document.getElementById('pwd-modal').style.display = 'none';
}
function submitChangePwd() {
    const cur  = document.getElementById('pwd-current').value;
    const nw   = document.getElementById('pwd-new').value;
    const conf = document.getElementById('pwd-confirm').value;
    const msg  = document.getElementById('pwd-msg');

    if (!cur || !nw || !conf) {
        msg.innerHTML = '<div class="alert alert-error" style="margin-bottom:12px">Заполните все поля</div>';
        return;
    }
    if (nw.length < 6) {
        msg.innerHTML = '<div class="alert alert-error" style="margin-bottom:12px">Пароль должен быть минимум 6 символов</div>';
        return;
    }
    if (nw !== conf) {
        msg.innerHTML = '<div class="alert alert-error" style="margin-bottom:12px">Пароли не совпадают</div>';
        return;
    }

    const fd = new FormData();
    fd.append('current', cur);
    fd.append('new_pwd', nw);

    fetch('change_password.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.ok) {
                msg.innerHTML = '<div class="alert alert-success" style="margin-bottom:12px">✅ Пароль успешно изменён</div>';
                setTimeout(closeChangePwd, 1500);
            } else {
                msg.innerHTML = '<div class="alert alert-error" style="margin-bottom:12px">' + data.error + '</div>';
            }
        })
        .catch(() => {
            msg.innerHTML = '<div class="alert alert-error" style="margin-bottom:12px">Ошибка соединения</div>';
        });
}
document.getElementById('pwd-modal').addEventListener('click', function(e){ if(e.target===this)closeChangePwd(); });
</script>
