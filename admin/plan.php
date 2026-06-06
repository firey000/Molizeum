<?php
require_once '../config.php';
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../login-form.php"); exit;
}

if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $conn->query("DELETE FROM services WHERE id = ".(int)$_GET['delete']);
    header("Location: plan.php"); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id       = (int)($_POST['id'] ?? 0);
    $name     = trim($_POST['name']);
    $cat      = $_POST['category'];
    $price    = (float)$_POST['price'];
    $duration = $_POST['duration'] !== '' ? (int)$_POST['duration'] : null;
    $status   = $_POST['status'];

    if ($id > 0) {
        $stmt = $conn->prepare("UPDATE services SET name=?,category=?,price=?,duration_minutes=?,status=? WHERE id=?");
        $stmt->bind_param("ssdisi", $name,$cat,$price,$duration,$status,$id);
    } else {
        $stmt = $conn->prepare("INSERT INTO services (name,category,price,duration_minutes,status) VALUES (?,?,?,?,?)");
        $stmt->bind_param("ssdis", $name,$cat,$price,$duration,$status);
    }
    $stmt->execute();
    header("Location: plan.php"); exit;
}

$result = $conn->query("SELECT * FROM services ORDER BY category, price ASC");
$current = 'plan.php';
$name = $_SESSION['user_name'] ?? '';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Тарифы — Молизеум</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
<?php include 'header.php'; ?>

<div class="main-content">
    <div class="table-card">
        <div class="table-card-header">
            <div>
                <div class="table-card-title">Управление тарифами</div>
                <div class="table-card-desc">Установка цен и скидок</div>
            </div>
            <button class="btn-add" onclick="openModal()">+ Добавить тариф</button>
        </div>

        <table class="data-table">
            <thead>
                <tr><th>Название</th><th>Категория</th><th>Цена</th><th>Единица</th><th>Статус</th><th>Действия</th></tr>
            </thead>
            <tbody>
            <?php
            $cats = ['pc_rent'=>'Аренда ПК','food'=>'Еда/Напитки','additional'=>'Доп. услуги'];
            while ($row = $result->fetch_assoc()):
                $sc = $row['status']==='active' ? 'badge-green' : 'badge-red';
                $st = $row['status']==='active' ? 'Активен' : 'Неактивен';
                $unit = $row['duration_minutes'] ? $row['duration_minutes'].' мин' : 'шт';
            ?>
            <tr>
                <td><strong><?= htmlspecialchars($row['name']) ?></strong></td>
                <td><?= $cats[$row['category']] ?? $row['category'] ?></td>
                <td><?= number_format($row['price'], 0, '.', ' ') ?> ₽</td>
                <td><?= $unit ?></td>
                <td><span class="badge <?= $sc ?>"><?= $st ?></span></td>
                <td>
                    <button class="action-btn btn-edit" onclick="editRow(<?= htmlspecialchars(json_encode($row)) ?>)">Изменить</button>
                    <a href="?delete=<?= $row['id'] ?>" class="action-btn btn-delete" onclick="return confirm('Удалить тариф?')">Удалить</a>
                </td>
            </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal-overlay" id="modal">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title" id="modal-title">Добавить тариф</span>
            <button class="modal-close" onclick="closeModal()">×</button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="id" id="f-id" value="0">
                <div class="form-group">
                    <label class="form-label">Название *</label>
                    <input type="text" class="form-input" name="name" id="f-name" required>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Категория</label>
                        <select class="form-select" name="category" id="f-cat">
                            <option value="pc_rent">Аренда ПК</option>
                            <option value="food">Еда/Напитки</option>
                            <option value="additional">Доп. услуги</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Статус</label>
                        <select class="form-select" name="status" id="f-status">
                            <option value="active">Активен</option>
                            <option value="inactive">Неактивен</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Цена (₽)</label>
                        <input type="number" class="form-input" name="price" id="f-price" step="0.01" min="0" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Длительность (мин)</label>
                        <input type="number" class="form-input" name="duration" id="f-dur" placeholder="60 (для аренды)">
                    </div>
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
    document.getElementById('modal-title').textContent = 'Добавить тариф';
    document.getElementById('f-id').value = 0;
    document.getElementById('f-name').value = '';
    document.getElementById('f-cat').value = 'pc_rent';
    document.getElementById('f-price').value = '';
    document.getElementById('f-dur').value = '';
    document.getElementById('f-status').value = 'active';
    document.getElementById('modal').classList.add('open');
}
function editRow(r) {
    document.getElementById('modal-title').textContent = 'Редактировать тариф';
    document.getElementById('f-id').value = r.id;
    document.getElementById('f-name').value = r.name;
    document.getElementById('f-cat').value = r.category;
    document.getElementById('f-price').value = r.price;
    document.getElementById('f-dur').value = r.duration_minutes || '';
    document.getElementById('f-status').value = r.status;
    document.getElementById('modal').classList.add('open');
}
function closeModal() { document.getElementById('modal').classList.remove('open'); }
document.getElementById('modal').addEventListener('click', function(e){ if(e.target===this)closeModal(); });
</script>
</body>
</html>
