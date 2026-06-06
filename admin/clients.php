<?php
require_once '../config.php';
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../login-form.php"); exit;
}

// DELETE
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    // Удаляем все зависимые записи (FK) перед удалением пользователя
    $conn->query("DELETE FROM snack_sales  WHERE client_id  = $id");
    $conn->query("DELETE FROM payments     WHERE user_id    = $id");
    $conn->query("DELETE FROM bookings     WHERE client_id  = $id OR employee_id = $id");
    $conn->query("DELETE FROM users        WHERE id = $id AND role != 'admin'");
    header("Location: clients.php"); exit;
}

// ADD / EDIT
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id        = (int)($_POST['id'] ?? 0);
    $first     = trim($_POST['first_name'] ?? '');
    $last      = trim($_POST['last_name'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $phone     = trim($_POST['phone'] ?? '');
    $role      = $_POST['role'] ?? 'client';
    $status    = $_POST['status'] ?? 'active';
    $password  = trim($_POST['password'] ?? '');

    if ($id > 0) {
        // Update
        if ($password) {
            $stmt = $conn->prepare("UPDATE users SET first_name=?, last_name=?, email=?, phone=?, role=?, status=?, password=? WHERE id=?");
            $stmt->bind_param("sssssssi", $first, $last, $email, $phone, $role, $status, $password, $id);
        } else {
            $stmt = $conn->prepare("UPDATE users SET first_name=?, last_name=?, email=?, phone=?, role=?, status=? WHERE id=?");
            $stmt->bind_param("ssssssi", $first, $last, $email, $phone, $role, $status, $id);
        }
    } else {
        // Insert
        $stmt = $conn->prepare("INSERT INTO users (first_name, last_name, email, phone, password, role, status) VALUES (?,?,?,?,?,?,?)");
        $stmt->bind_param("sssssss", $first, $last, $email, $phone, $password, $role, $status);
    }
    $stmt->execute();
    header("Location: clients.php"); exit;
}

$users = $conn->query("SELECT * FROM users ORDER BY created_at DESC");
$current = 'clients.php';
$name = $_SESSION['user_name'] ?? '';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Пользователи — Молизеум</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
<?php include 'header.php'; ?>

<div class="main-content">
    <div class="table-card">
        <div class="table-card-header">
            <div>
                <div class="table-card-title">Управление пользователями</div>
            </div>
            <button class="btn-add" onclick="openModal()">+ Добавить пользователя</button>
        </div>

        <table class="data-table">
            <thead>
                <tr>
                    <th>Имя</th>
                    <th>Роль</th>
                    <th>Email</th>
                    <th>Телефон</th>
                    <th>Статус</th>
                    <th style="text-align:center">Дата регистрации</th>
                    <th>Действия</th>
                </tr>
            </thead>
            <tbody>
            <?php
            $roles = ['admin'=>'Администратор','employee'=>'Сотрудник','client'=>'Клиент'];
            while ($row = $users->fetch_assoc()):
                $sc = $row['status'] === 'active' ? 'badge-green' : ($row['status'] === 'blocked' ? 'badge-red' : 'badge-yellow');
                $st = $row['status'] === 'active' ? 'Активен' : ($row['status'] === 'blocked' ? 'Заблокирован' : 'Не подтверждён');
            ?>
            <tr>
                <td><strong><?= htmlspecialchars($row['first_name'].' '.$row['last_name']) ?></strong></td>
                <td><?= $roles[$row['role']] ?? $row['role'] ?></td>
                <td><?= htmlspecialchars($row['email']) ?></td>
                <td><?= htmlspecialchars($row['phone']) ?></td>
                <td><span class="badge <?= $sc ?>"><?= $st ?></span></td>
                <td style="text-align:center"><?= date('d.m.Y', strtotime($row['created_at'])) ?></td>
                <td>
                    <button class="action-btn btn-edit" onclick="editUser(<?= htmlspecialchars(json_encode($row)) ?>)">Изменить</button>
                    <?php if ($row['role'] !== 'admin'): ?>
                    <a href="?delete=<?= $row['id'] ?>" class="action-btn btn-delete"
                       onclick="return confirm('Удалить пользователя?')">Удалить</a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal -->
<div class="modal-overlay" id="modal">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title" id="modal-title">Добавить пользователя</span>
            <button class="modal-close" onclick="closeModal()">×</button>
        </div>
        <form method="POST" action="clients.php">
            <div class="modal-body">
                <input type="hidden" name="id" id="f-id" value="0">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Имя *</label>
                        <input type="text" class="form-input" name="first_name" id="f-first" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Фамилия *</label>
                        <input type="text" class="form-input" name="last_name" id="f-last" required>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Email *</label>
                    <input type="email" class="form-input" name="email" id="f-email" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Телефон *</label>
                    <input type="tel" class="form-input" name="phone" id="f-phone" required>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Роль</label>
                        <select class="form-select" name="role" id="f-role">
                            <option value="client">Клиент</option>
                            <option value="employee">Сотрудник</option>
                            <option value="admin">Администратор</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Статус</label>
                        <select class="form-select" name="status" id="f-status">
                            <option value="active">Активен</option>
                            <option value="blocked">Заблокирован</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Пароль <span id="pass-hint" style="color:#9ca3af;font-weight:400">(оставьте пустым, чтобы не менять)</span></label>
                    <input type="text" class="form-input" name="password" id="f-pass" placeholder="Минимум 6 символов">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="closeModal()">Отмена</button>
                <button type="submit" class="btn-save">Сохранить</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal() {
    document.getElementById('modal-title').textContent = 'Добавить пользователя';
    document.getElementById('f-id').value = 0;
    document.getElementById('f-first').value = '';
    document.getElementById('f-last').value = '';
    document.getElementById('f-email').value = '';
    document.getElementById('f-phone').value = '';
    document.getElementById('f-role').value = 'client';
    document.getElementById('f-status').value = 'active';
    document.getElementById('f-pass').value = '';
    document.getElementById('pass-hint').style.display = 'none';
    document.getElementById('modal').classList.add('open');
}

function editUser(u) {
    document.getElementById('modal-title').textContent = 'Редактировать пользователя';
    document.getElementById('f-id').value = u.id;
    document.getElementById('f-first').value = u.first_name;
    document.getElementById('f-last').value = u.last_name;
    document.getElementById('f-email').value = u.email;
    document.getElementById('f-phone').value = u.phone;
    document.getElementById('f-role').value = u.role;
    document.getElementById('f-status').value = u.status;
    document.getElementById('f-pass').value = '';
    document.getElementById('pass-hint').style.display = '';
    document.getElementById('modal').classList.add('open');
}

function closeModal() {
    document.getElementById('modal').classList.remove('open');
}

document.getElementById('modal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});
</script>
</body>
</html>
