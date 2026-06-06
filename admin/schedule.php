<?php
require_once '../config.php';
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../login-form.php"); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id       = (int)($_POST['id'] ?? 0);
    $emp_id   = (int)$_POST['employee_id'];
    $schedule = trim($_POST['schedule']);
    $salary   = $_POST['salary'] !== '' ? (int)$_POST['salary'] : null;

    $stmt = $conn->prepare("UPDATE users SET schedule=?, salary=? WHERE id=? AND role='employee'");
    $stmt->bind_param("sii", $schedule, $salary, $emp_id);
    $stmt->execute();
    header("Location: schedule.php"); exit;
}

$employees = $conn->query("SELECT id, first_name, last_name, schedule, salary FROM users WHERE role='employee' ORDER BY id ASC");
$current = 'schedule.php';
$name = $_SESSION['user_name'] ?? '';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Расписание — Молизеум</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
<?php include 'header.php'; ?>

<div class="main-content">
    <div class="table-card">
        <div class="table-card-header">
            <div>
                <div class="table-card-title">Расписание сотрудников</div>
                <div class="table-card-desc">Управление графиком работы</div>
            </div>
            <button class="btn-add" onclick="openModal()">+ Добавить смену</button>
        </div>

        <table class="data-table">
            <thead>
                <tr><th>Сотрудник</th><th>График работы</th><th>Зарплата</th><th>Действия</th></tr>
            </thead>
            <tbody>
            <?php
            $emps = [];
            while ($row = $employees->fetch_assoc()):
                $emps[] = $row;
            ?>
            <tr>
                <td><strong><?= htmlspecialchars($row['first_name'].' '.$row['last_name']) ?></strong></td>
                <td><?= $row['schedule'] ? htmlspecialchars($row['schedule']) : '<span style="color:#9ca3af">Не задан</span>' ?></td>
                <td><?= $row['salary'] ? number_format($row['salary'],0,'.',' ').' ₽' : '—' ?></td>
                <td>
                    <button class="action-btn btn-edit" onclick="editRow(<?= htmlspecialchars(json_encode($row)) ?>)">Изменить</button>
                </td>
            </tr>
            <?php endwhile; ?>
            <?php if (empty($emps)): ?>
            <tr><td colspan="4" style="text-align:center;padding:40px;color:#9ca3af">Сотрудники не найдены. Добавьте пользователей с ролью «Сотрудник».</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal-overlay" id="modal">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title">Редактировать график</span>
            <button class="modal-close" onclick="closeModal()">×</button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="employee_id" id="f-emp">
                <div class="form-group">
                    <label class="form-label">Сотрудник</label>
                    <input type="text" class="form-input" id="f-empname" readonly style="background:#f9fafb">
                </div>
                <div class="form-group">
                    <label class="form-label">График работы</label>
                    <input type="text" class="form-input" name="schedule" id="f-sched" placeholder="Пн, Вт, Ср 09:00–18:00">
                </div>
                <div class="form-group">
                    <label class="form-label">Зарплата (₽)</label>
                    <input type="number" class="form-input" name="salary" id="f-sal" placeholder="35000">
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
function openModal() { document.getElementById('modal').classList.add('open'); }
function editRow(r) {
    document.getElementById('f-emp').value = r.id;
    document.getElementById('f-empname').value = r.first_name + ' ' + r.last_name;
    document.getElementById('f-sched').value = r.schedule || '';
    document.getElementById('f-sal').value = r.salary || '';
    document.getElementById('modal').classList.add('open');
}
function closeModal() { document.getElementById('modal').classList.remove('open'); }
document.getElementById('modal').addEventListener('click', function(e){ if(e.target===this)closeModal(); });
</script>
</body>
</html>
