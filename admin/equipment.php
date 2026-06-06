<?php
require_once '../config.php';
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../login-form.php"); exit;
}

// DELETE
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $eid = (int)$_GET['delete'];
    // Сначала удаляем зависимые записи (FK)
    $conn->query("DELETE FROM bookings WHERE equipment_id = $eid");
    $conn->query("DELETE FROM equipment WHERE id = $eid");
    header("Location: equipment.php"); exit;
}

// ADD / EDIT
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id       = (int)($_POST['id'] ?? 0);
    $number   = (int)$_POST['number'];
    $cpu      = trim($_POST['cpu']);
    $gpu      = trim($_POST['gpu']);
    $ram      = trim($_POST['ram']);
    $monitor  = trim($_POST['monitor']);
    $keyboard = trim($_POST['keyboard']);
    $mouse    = trim($_POST['mouse']);
    $status   = $_POST['status'];
    $hall_id  = (int)$_POST['hall_id'];


      // Проверка на дубликат номера ПК
      $dup_stmt = $conn->prepare("SELECT id FROM equipment WHERE number = ? AND id != ?");
      $dup_stmt->bind_param("ii", $number, $id);
      $dup_stmt->execute();
      if ($dup_stmt->get_result()->num_rows > 0) {
          header("Location: equipment.php?error=duplicate&num=$number"); exit;
      }
  
    if ($id > 0) {
        $stmt = $conn->prepare("UPDATE equipment SET number=?,cpu=?,gpu=?,ram=?,monitor=?,keyboard=?,mouse=?,status=?,hall_id=? WHERE id=?");
        $stmt->bind_param("isssssssii", $number,$cpu,$gpu,$ram,$monitor,$keyboard,$mouse,$status,$hall_id,$id);
    } else {
        $stmt = $conn->prepare("INSERT INTO equipment (number,cpu,gpu,ram,monitor,keyboard,mouse,status,hall_id) VALUES (?,?,?,?,?,?,?,?,?)");
        $stmt->bind_param("isssssssi", $number,$cpu,$gpu,$ram,$monitor,$keyboard,$mouse,$status,$hall_id);
    }
    $stmt->execute();
    header("Location: equipment.php"); exit;
}

$equipment = $conn->query("SELECT e.*, h.name as hall_name FROM equipment e LEFT JOIN halls h ON e.hall_id=h.id ORDER BY e.number ASC");
$halls = $conn->query("SELECT * FROM halls ORDER BY number");
$current = 'equipment.php';
$name = $_SESSION['user_name'] ?? '';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Оборудование — Молизеум</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
<?php include 'header.php'; ?>
  <?php if (isset($_GET['error']) && $_GET['error'] === 'duplicate'): ?>
  <div class="main-content" style="padding-bottom:0">
      <div class="alert alert-error" style="margin:0 0 -12px">⚠️ ПК с номером <?= (int)$_GET['num'] ?> уже существует. Выберите другой номер.</div>
  </div>
  <?php endif; ?>

<div class="main-content">
    <div class="table-card">
        <div class="table-card-header">
            <div>
                <div class="table-card-title">Каталогизация оборудования</div>
            </div>
            <button class="btn-add" onclick="openModal()">+ Добавить оборудование</button>
        </div>

        <table class="data-table">
            <thead>
                <tr>
                    <th>ПК №</th><th>Процессор</th><th>Видеокарта</th><th>ОЗУ</th>
                    <th>Зал</th><th>Мышь</th><th>Клавиатура</th><th>Монитор</th>
                    <th>Статус</th><th>Действия</th>
                </tr>
            </thead>
            <tbody>
            <?php while ($row = $equipment->fetch_assoc()):
                $sc = $row['status']==='free' ? 'badge-green' : ($row['status']==='busy' ? 'badge-red' : 'badge-yellow');
                $st = $row['status']==='free' ? 'Свободен' : ($row['status']==='busy' ? 'Занят' : 'Ремонт');
            ?>
            <tr>
                <td><strong>ПК #<?= $row['number'] ?></strong></td>
                <td><?= htmlspecialchars($row['cpu']) ?></td>
                <td><?= htmlspecialchars($row['gpu']) ?></td>
                <td><?= htmlspecialchars($row['ram']) ?></td>
                <td><?= htmlspecialchars($row['hall_name'] ?? '—') ?></td>
                <td><?= htmlspecialchars($row['mouse']) ?></td>
                <td><?= htmlspecialchars($row['keyboard']) ?></td>
                <td><?= htmlspecialchars($row['monitor']) ?></td>
                <td><span class="badge <?= $sc ?>"><?= $st ?></span></td>
                <td style="white-space:nowrap">
                    <button class="action-btn btn-edit" onclick='editRow(<?= htmlspecialchars(json_encode($row)) ?>)'>Изменить</button>
                    <a href="?delete=<?= $row['id'] ?>" class="action-btn btn-delete" onclick="return confirm('Удалить ПК? Все связанные бронирования будут удалены.')">Удалить</a>
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
            <span class="modal-title" id="modal-title">Добавить оборудование</span>
            <button class="modal-close" onclick="closeModal()">×</button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="id" id="f-id" value="0">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">ПК №</label>
                        <input type="number" class="form-input" name="number" id="f-number" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">ОЗУ</label>
                        <input type="text" class="form-input" name="ram" id="f-ram" placeholder="16GB" required>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Процессор</label>
                    <input type="text" class="form-input" name="cpu" id="f-cpu" placeholder="Intel i7-12700K" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Видеокарта</label>
                    <input type="text" class="form-input" name="gpu" id="f-gpu" placeholder="RTX 3070" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Монитор</label>
                    <input type="text" class="form-input" name="monitor" id="f-monitor" required>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Мышь</label>
                        <input type="text" class="form-input" name="mouse" id="f-mouse" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Клавиатура</label>
                        <input type="text" class="form-input" name="keyboard" id="f-keyboard" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Зал</label>
                        <select class="form-select" name="hall_id" id="f-hall">
                            <?php
                            $halls_arr = [];
                            $halls->data_seek(0);
                            while ($h = $halls->fetch_assoc()) {
                                $halls_arr[] = $h;
                                echo "<option value='{$h['id']}'>{$h['name']}</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Статус</label>
                        <select class="form-select" name="status" id="f-status">
                            <option value="free">Свободен</option>
                            <option value="busy">Занят</option>
                            <option value="repair">Ремонт</option>
                        </select>
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
    document.getElementById('modal-title').textContent = 'Добавить оборудование';
    document.getElementById('f-id').value = 0;
    ['number','cpu','gpu','ram','monitor','mouse','keyboard'].forEach(f => document.getElementById('f-'+f).value = '');
    document.getElementById('f-status').value = 'free';
    document.getElementById('modal').classList.add('open');
}
function editRow(r) {
    document.getElementById('modal-title').textContent = 'Редактировать ПК';
    document.getElementById('f-id').value = r.id;
    document.getElementById('f-number').value = r.number;
    document.getElementById('f-cpu').value = r.cpu;
    document.getElementById('f-gpu').value = r.gpu;
    document.getElementById('f-ram').value = r.ram;
    document.getElementById('f-monitor').value = r.monitor;
    document.getElementById('f-mouse').value = r.mouse;
    document.getElementById('f-keyboard').value = r.keyboard;
    document.getElementById('f-status').value = r.status;
    document.getElementById('f-hall').value = r.hall_id;
    document.getElementById('modal').classList.add('open');
}
function closeModal() { document.getElementById('modal').classList.remove('open'); }
document.getElementById('modal').addEventListener('click', function(e){ if(e.target===this)closeModal(); });
</script>
</body>
</html>
