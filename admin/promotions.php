<?php
require_once '../config.php';
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../login-form.php"); exit;
}

// ── Удаление ─────────────────────────────────────────────────
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $conn->query("DELETE FROM promotions WHERE id = ".(int)$_GET['delete']);
    header("Location: promotions.php"); exit;
}

// ── Переключение статуса ──────────────────────────────────────
if (isset($_GET['toggle']) && is_numeric($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    $conn->query("UPDATE promotions SET status = IF(status='active','inactive','active') WHERE id=$id");
    header("Location: promotions.php"); exit;
}

// ── Сохранение (создание / редактирование) ────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id             = (int)($_POST['id'] ?? 0);
    $name           = trim($_POST['name']);
    $desc           = trim($_POST['description']);
    $pct            = max(1, min(100, (int)$_POST['discount_percent']));
    $condition_type = $_POST['condition_type'] ?? 'always';
    $start          = $_POST['start_date'] ?: null;
    $end            = $_POST['end_date']   ?: null;
    $status         = $_POST['status'];

    // Автоматически добавляем мигра­цию колонки если её нет (первый запуск)
    $conn->query("ALTER TABLE promotions ADD COLUMN IF NOT EXISTS condition_type VARCHAR(30) NOT NULL DEFAULT 'always'");

    if ($id > 0) {
        $stmt = $conn->prepare("UPDATE promotions SET name=?,description=?,discount_percent=?,condition_type=?,start_date=?,end_date=?,status=? WHERE id=?");
        $stmt->bind_param("ssissssi", $name,$desc,$pct,$condition_type,$start,$end,$status,$id);
    } else {
        $stmt = $conn->prepare("INSERT INTO promotions (name,description,discount_percent,condition_type,start_date,end_date,status) VALUES (?,?,?,?,?,?,?)");
        $stmt->bind_param("ssissss", $name,$desc,$pct,$condition_type,$start,$end,$status);
    }
    $stmt->execute();
    header("Location: promotions.php"); exit;
}

// Добавляем колонку если отсутствует (при первом открытии страницы)
$conn->query("ALTER TABLE promotions ADD COLUMN IF NOT EXISTS condition_type VARCHAR(30) NOT NULL DEFAULT 'always'");

$result = $conn->query("SELECT * FROM promotions ORDER BY status DESC, end_date ASC");
$current = 'promotions.php';
$name_u = $_SESSION['user_name'] ?? '';

// Метки типов условий
$condition_labels = [
    'always'      => '📅 Всегда (по датам)',
    'night'       => '🌙 Ночное время (22:00–07:00)',
    'happy_hours' => '☀️ Счастливые часы (14:00–17:00, пн–пт)',
    'weekend'     => '🎉 Выходные (сб–вс)',
];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Акции — Молизеум</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
<?php include 'header.php'; ?>

<div class="main-content">
    <div class="table-card">
        <div class="table-card-header">
            <div>
                <div class="table-card-title">Управление акциями</div>
                <div class="table-card-desc">Создание и настройка акций — условие срабатывания выбирается при создании</div>
            </div>
            <button class="btn-add" onclick="openModal()">+ Добавить акцию</button>
        </div>

        <!-- Подсказка по условиям -->
        <div style="margin:0 0 18px;padding:14px 18px;background:#f0f9ff;border-radius:8px;border:1px solid #bae6fd;font-size:13px;color:#0c4a6e">
            <strong>Как работают условия акций:</strong><br>
            <span style="display:inline-block;margin-top:6px">
                📅 <b>Всегда</b> — применяется автоматически в указанный период дат (если даты не указаны — бессрочно)<br>
                🌙 <b>Ночное время</b> — дополнительная скидка при бронировании с 22:00 до 07:00<br>
                ☀️ <b>Счастливые часы</b> — скидка с 14:00 до 17:00 по будням (пн–пт)<br>
                🎉 <b>Выходные</b> — скидка при бронировании в субботу или воскресенье
            </span>
        </div>

        <table class="data-table">
            <thead>
                <tr>
                    <th>Название</th>
                    <th>Условие срабатывания</th>
                    <th>Скидка</th>
                    <th>Дата начала</th>
                    <th>Дата окончания</th>
                    <th>Статус</th>
                    <th>Действия</th>
                </tr>
            </thead>
            <tbody>
            <?php while ($row = $result->fetch_assoc()):
                $sc  = $row['status'] === 'active' ? 'badge-green' : 'badge-gray';
                $cnd = $row['condition_type'] ?? 'always';
                $cnd_label = $condition_labels[$cnd] ?? $cnd;
            ?>
            <tr>
                <td>
                    <strong><?= htmlspecialchars($row['name']) ?></strong>
                    <?php if ($row['description']): ?>
                    <div style="font-size:12px;color:#9ca3af;margin-top:2px"><?= htmlspecialchars(mb_strimwidth($row['description'], 0, 60, '…')) ?></div>
                    <?php endif; ?>
                </td>
                <td style="font-size:13px"><?= $cnd_label ?></td>
                <td><strong style="font-size:16px"><?= $row['discount_percent'] ?>%</strong></td>
                <td><?= $row['start_date'] ? date('d.m.Y', strtotime($row['start_date'])) : '—' ?></td>
                <td><?= $row['end_date']   ? date('d.m.Y', strtotime($row['end_date']))   : 'Бессрочно' ?></td>
                <td>
                    <label class="toggle">
                        <input type="checkbox" <?= $row['status'] === 'active' ? 'checked' : '' ?>
                               onchange="location='?toggle=<?= $row['id'] ?>'">
                        <span class="toggle-slider"></span>
                    </label>
                </td>
                <td>
                    <button class="action-btn btn-edit" onclick='editRow(<?= htmlspecialchars(json_encode($row)) ?>)'>Изменить</button>
                    <a href="?delete=<?= $row['id'] ?>" class="action-btn btn-delete" onclick="return confirm('Удалить акцию?')">Удалить</a>
                </td>
            </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ── Модальное окно ─────────────────────────────────────────── -->
<div class="modal-overlay" id="modal">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title" id="modal-title">Добавить акцию</span>
            <button class="modal-close" onclick="closeModal()">×</button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="id" id="f-id" value="0">

                <div class="form-group">
                    <label class="form-label">Название *</label>
                    <input type="text" class="form-input" name="name" id="f-name" required placeholder="Напр.: Ночная акция, Летние выходные…">
                </div>

                <div class="form-group">
                    <label class="form-label">Описание</label>
                    <textarea class="form-input" name="description" id="f-desc" rows="2" style="resize:vertical" placeholder="Описание для клиентов"></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Скидка (%)</label>
                        <input type="number" class="form-input" name="discount_percent" id="f-pct" min="1" max="100" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Статус</label>
                        <select class="form-select" name="status" id="f-status">
                            <option value="active">Активна</option>
                            <option value="inactive">Неактивна</option>
                        </select>
                    </div>
                </div>

                <!-- Условие срабатывания -->
                <div class="form-group">
                    <label class="form-label">Условие срабатывания *</label>
                    <select class="form-select" name="condition_type" id="f-cond" onchange="updateConditionHint()">
                        <option value="always">📅 Всегда (по датам)</option>
                        <option value="night">🌙 Ночное время (22:00–07:00)</option>
                        <option value="happy_hours">☀️ Счастливые часы (14:00–17:00, пн–пт)</option>
                        <option value="weekend">🎉 Выходные (сб–вс)</option>
                    </select>
                    <div id="cond-hint" style="margin-top:7px;padding:8px 12px;border-radius:6px;font-size:12.5px;background:#f8fafc;border:1px solid #e2e8f0;color:#475569"></div>
                </div>

                <!-- Период действия -->
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Дата начала <span style="color:#9ca3af">(необязательно)</span></label>
                        <input type="date" class="form-input" name="start_date" id="f-start">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Дата окончания <span style="color:#9ca3af">(необязательно)</span></label>
                        <input type="date" class="form-input" name="end_date" id="f-end">
                    </div>
                </div>
                <div style="font-size:12px;color:#9ca3af;margin-top:-8px">
                    Если даты не указаны — акция действует бессрочно
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
const COND_HINTS = {
    'always':      'Акция применяется автоматически ко всем бронированиям в указанный период дат.',
    'night':       'Акция применяется когда время начала бронирования с 22:00 до 06:59.',
    'happy_hours': 'Акция применяется когда время начала с 14:00 до 16:59 в будние дни (пн–пт).',
    'weekend':     'Акция применяется при бронировании в субботу или воскресенье.',
};

function updateConditionHint() {
    const val = document.getElementById('f-cond').value;
    document.getElementById('cond-hint').textContent = COND_HINTS[val] || '';
}

function openModal() {
    document.getElementById('modal-title').textContent = 'Добавить акцию';
    document.getElementById('f-id').value    = 0;
    document.getElementById('f-name').value  = '';
    document.getElementById('f-desc').value  = '';
    document.getElementById('f-pct').value   = '';
    document.getElementById('f-start').value = '';
    document.getElementById('f-end').value   = '';
    document.getElementById('f-status').value = 'active';
    document.getElementById('f-cond').value  = 'always';
    updateConditionHint();
    document.getElementById('modal').classList.add('open');
}

function editRow(r) {
    document.getElementById('modal-title').textContent = 'Редактировать акцию';
    document.getElementById('f-id').value    = r.id;
    document.getElementById('f-name').value  = r.name;
    document.getElementById('f-desc').value  = r.description;
    document.getElementById('f-pct').value   = r.discount_percent;
    document.getElementById('f-start').value = r.start_date  || '';
    document.getElementById('f-end').value   = r.end_date    || '';
    document.getElementById('f-status').value = r.status;
    document.getElementById('f-cond').value  = r.condition_type || 'always';
    updateConditionHint();
    document.getElementById('modal').classList.add('open');
}

function closeModal() { document.getElementById('modal').classList.remove('open'); }
document.getElementById('modal').addEventListener('click', function(e){ if(e.target===this) closeModal(); });

// Инициализация подсказки
updateConditionHint();
</script>
</body>
</html>
