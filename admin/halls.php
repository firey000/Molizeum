<?php
require_once '../config.php';
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../login-form.php"); exit;
}

$msg = '';

// DELETE
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $hid = (int)$_GET['delete'];
    $used = $conn->query("SELECT COUNT(*) AS c FROM equipment WHERE hall_id=$hid")->fetch_assoc()['c'];
    if ($used > 0) {
        $msg = '<div class="alert alert-error">⚠️ Нельзя удалить зал: в нём есть ПК ('.$used.' шт.). Сначала переназначьте или удалите их.</div>';
    } else {
        $conn->query("DELETE FROM halls WHERE id=$hid");
        header("Location: halls.php"); exit;
    }
}

// ADD / EDIT
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id     = (int)($_POST['id'] ?? 0);
    $number = (int)$_POST['number'];
    $name   = trim($_POST['name']);

    if ($id > 0) {
        $stmt = $conn->prepare("UPDATE halls SET number=?, name=? WHERE id=?");
        $stmt->bind_param("isi", $number, $name, $id);
    } else {
        $stmt = $conn->prepare("INSERT INTO halls (number, name) VALUES (?, ?)");
        $stmt->bind_param("is", $number, $name);
    }
    $stmt->execute();
    header("Location: halls.php"); exit;
}

$halls = $conn->query("SELECT h.*, COUNT(e.id) AS pc_count FROM halls h LEFT JOIN equipment e ON e.hall_id=h.id GROUP BY h.id ORDER BY h.number");
$halls_arr = [];
while ($h = $halls->fetch_assoc()) $halls_arr[] = $h;

$current = 'halls.php';
$name_user = $_SESSION['user_name'] ?? '';
?>
<?php include 'header.php'; ?>

<div class="main-content">
<?= $msg ?>
<div class="table-card">
    <div class="table-card-header">
        <div>
            <div class="table-card-title">Залы</div>
            <div class="table-card-desc">Управление залами и составом оборудования</div>
        </div>
        <button class="btn-add" onclick="openModal()">+ Добавить зал</button>
    </div>

    <?php if (empty($halls_arr)): ?>
    <div style="padding:40px;text-align:center;color:#9ca3af">Залов пока нет</div>
    <?php else: ?>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(340px,1fr));gap:20px;padding:20px">
    <?php foreach ($halls_arr as $h):
        $pcs = $conn->query("SELECT * FROM equipment WHERE hall_id=".(int)$h['id']." ORDER BY number");
        $pcs_arr = [];
        while ($p = $pcs->fetch_assoc()) $pcs_arr[] = $p;
    ?>
        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;box-shadow:0 1px 4px rgba(0,0,0,.06)">
            <div style="padding:16px 20px;border-bottom:1px solid #f3f4f6;display:flex;justify-content:space-between;align-items:center">
                <div>
                    <div style="font-size:15px;font-weight:700;color:#111"><?= htmlspecialchars($h['name']) ?></div>
                    <div style="font-size:12px;color:#9ca3af;margin-top:2px">Зал №<?= $h['number'] ?> · <?= $h['pc_count'] ?> ПК</div>
                </div>
                <div style="display:flex;gap:8px">
                    <button class="action-btn btn-edit" onclick='editRow(<?= htmlspecialchars(json_encode($h)) ?>)'>Изменить</button>
                    <?php if ($h['pc_count'] == 0): ?>
                    <a href="?delete=<?= $h['id'] ?>" class="action-btn btn-delete"
                       onclick="return confirm('Удалить зал «<?= htmlspecialchars($h['name'], ENT_QUOTES) ?>»?')">Удалить</a>
                    <?php else: ?>
                    <span class="action-btn" style="opacity:.4;cursor:not-allowed;background:#f3f4f6;color:#9ca3af" title="Сначала удалите ПК">Удалить</span>
                    <?php endif; ?>
                </div>
            </div>
            <?php if (empty($pcs_arr)): ?>
            <div style="padding:20px;text-align:center;color:#9ca3af;font-size:13px">Оборудования нет</div>
            <?php else: ?>
            <div style="max-height:calc(5*37px + 36px);overflow-y:auto"><table style="width:100%;border-collapse:collapse;font-size:12.5px">
                <thead>
                    <tr style="background:#f9fafb;color:#9ca3af;font-size:11px;text-transform:uppercase;letter-spacing:.04em">
                        <th style="padding:8px 12px;text-align:left;font-weight:600">ПК</th>
                        <th style="padding:8px 12px;text-align:left;font-weight:600">CPU</th>
                        <th style="padding:8px 12px;text-align:left;font-weight:600">GPU</th>
                        <th style="padding:8px 12px;text-align:left;font-weight:600">Статус</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($pcs_arr as $p):
                    $sc = $p['status']==='free' ? '#dcfce7;color:#16a34a' : ($p['status']==='busy' ? '#fce7ef;color:#e11d48' : '#fef9c3;color:#a16207');
                    $st = $p['status']==='free' ? 'Свободен' : ($p['status']==='busy' ? 'Занят' : 'Ремонт');
                ?>
                <tr style="border-top:1px solid #f3f4f6">
                    <td style="padding:8px 12px;font-weight:700">#<?= $p['number'] ?></td>
                    <td style="padding:8px 12px;color:#4b5563"><?= htmlspecialchars($p['cpu'] ?? '—') ?></td>
                    <td style="padding:8px 12px;color:#4b5563;font-weight:500"><?= htmlspecialchars($p['gpu'] ?? '—') ?></td>
                    <td style="padding:8px 12px">
                        <span style="background:<?= $sc ?>;padding:2px 8px;border-radius:20px;font-size:11px;font-weight:500">
                            <?= $st ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
</div>

<div class="modal-overlay" id="modal">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title" id="modal-title">Добавить зал</span>
            <button class="modal-close" onclick="closeModal()">×</button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="id" id="f-id" value="0">
                <div class="form-group">
                    <label class="form-label">Номер зала</label>
                    <input type="number" class="form-input" name="number" id="f-number" min="1" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Название</label>
                    <input type="text" class="form-input" name="name" id="f-name" placeholder="VIP-зал" required>
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
    document.getElementById('modal-title').textContent = 'Добавить зал';
    document.getElementById('f-id').value = 0;
    document.getElementById('f-number').value = '';
    document.getElementById('f-name').value = '';
    document.getElementById('modal').classList.add('open');
}
function editRow(r) {
    document.getElementById('modal-title').textContent = 'Редактировать зал';
    document.getElementById('f-id').value = r.id;
    document.getElementById('f-number').value = r.number;
    document.getElementById('f-name').value = r.name;
    document.getElementById('modal').classList.add('open');
}
function closeModal() { document.getElementById('modal').classList.remove('open'); }
document.getElementById('modal').addEventListener('click', function(e){ if(e.target===this)closeModal(); });
</script>
</body>
</html>
