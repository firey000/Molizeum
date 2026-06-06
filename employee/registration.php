<?php
require_once '../config.php';
if (!isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], ['admin','employee'])) {
    header("Location: ../login-form.php"); exit;
}

$msg = '';

// ── Пополнение баланса через AJAX ───────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'topup') {
    header('Content-Type: application/json');
    $client_id = (int)$_POST['client_id'];
    $amount    = (float)$_POST['amount'];

    if ($amount < 1) {
        echo json_encode(['ok' => false, 'error' => 'Сумма должна быть больше 0']);
        exit;
    }
    $emp_name = $_SESSION['user_name'] ?? 'Сотрудник';
    $desc = 'Пополнение наличными (сотрудник: ' . $emp_name . ')';

    $conn->query("UPDATE users SET balance = balance + $amount WHERE id=$client_id");
    $stmt = $conn->prepare("INSERT INTO payments (user_id, amount, type, description) VALUES (?, ?, 'topup', ?)");
    $stmt->bind_param("ids", $client_id, $amount, $desc);
    $stmt->execute();

    $new_bal = (float)$conn->query("SELECT balance FROM users WHERE id=$client_id")->fetch_assoc()['balance'];
    echo json_encode(['ok' => true, 'new_balance' => $new_bal]);
    exit;
}

// ── Регистрация нового клиента ───────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['action'])) {
    $fullname = trim($_POST['fullname'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $balance  = (float)($_POST['balance'] ?? 0);
    $password = trim($_POST['password'] ?? '');

    $parts = explode(' ', $fullname, 2);
    $first = $parts[0] ?? $fullname;
    $last  = $parts[1] ?? '';

    if (strlen($password) < 6) {
        $msg = '<div class="alert alert-error">Пароль должен содержать минимум 6 символов</div>';
    } else {
        $check = $conn->prepare("SELECT id FROM users WHERE phone=?");
        $check->bind_param("s", $phone);
        $check->execute();

        if ($check->get_result()->num_rows > 0) {
            $msg = '<div class="alert alert-error">Клиент с таким телефоном уже зарегистрирован</div>';
        } else {
            $stmt = $conn->prepare(
                "INSERT INTO users (first_name, last_name, email, phone, password, role, status, balance)
                 VALUES (?,?,?,?,?,'client','active',?)"
            );
            $stmt->bind_param("sssssd", $first, $last, $email, $phone, $password, $balance);
            if ($stmt->execute()) {
                $msg = '<div class="alert alert-success">Клиент успешно зарегистрирован</div>';
            }
        }
    }
}

// ── Список всех клиентов ─────────────────────────────────────
$clients_res = $conn->query("SELECT id, first_name, last_name, phone, balance FROM users WHERE role='client' ORDER BY first_name, last_name");
$clients_all = [];
while ($c = $clients_res->fetch_assoc()) {
    $clients_all[] = $c;
}

$current = 'registration.php';
$name = $_SESSION['user_name'] ?? '';
?>
<?php include 'header.php'; ?>

<div class="employee-layout">
    <!-- Left: Form -->
    <div class="employee-panel">
        <div class="ep-header">
            <div class="ep-title">Регистрация нового клиента</div>
            <div class="ep-desc">Создание аккаунта для нового посетителя</div>
        </div>
        <div class="ep-body">
            <?= $msg ?>
            <form method="POST">
                <div class="ep-form-group">
                    <label class="form-label">Имя и фамилия</label>
                    <input type="text" class="form-input" name="fullname" placeholder="Введите ФИО" required>
                </div>
                <div class="ep-form-group">
                    <label class="form-label">Телефон</label>
                    <input type="tel" class="form-input" name="phone" placeholder="+7 999 123-45-67" required>
                </div>
                <div class="ep-form-group">
                    <label class="form-label">Email</label>
                    <input type="email" class="form-input" name="email" placeholder="email@example.com">
                </div>
                <div class="ep-form-group">
                    <label class="form-label">Пароль</label>
                    <input type="text" class="form-input" name="password" placeholder="Минимум 6 символов" required minlength="6">
                    <div style="font-size:11.5px;color:var(--gray-400);margin-top:4px">Придумайте пароль вместе с клиентом</div>
                </div>
                <div class="ep-form-group">
                    <label class="form-label">Начальный баланс (₽)</label>
                    <input type="number" class="form-input" name="balance" value="0" min="0" step="50">
                </div>
                <button type="submit" class="btn-primary" style="margin-top:6px">Зарегистрировать клиента</button>
            </form>
        </div>
    </div>

    <!-- Right: Client list with search -->
    <div class="employee-panel">
        <div class="ep-header">
            <div class="ep-title">Зарегистрированные клиенты</div>
            <div class="ep-desc">Нажмите на клиента, чтобы пополнить баланс</div>
        </div>

        <!-- Поиск -->
        <div style="padding:12px 16px;border-bottom:1px solid var(--gray-200)">
            <input type="text" id="client-search" class="form-input" 
                   placeholder="🔍 Поиск по имени, фамилии или телефону..."
                   oninput="filterClients(this.value)"
                   style="margin:0">
        </div>

        <ul class="ep-list" id="client-list" style="max-height:420px;overflow-y:auto">
        <?php foreach ($clients_all as $c): ?>
            <li class="ep-list-item client-row"
                style="cursor:pointer;transition:background .15s"
                onmouseenter="this.style.background='var(--gray-50)'"
                onmouseleave="this.style.background=''"
                onclick="openTopup(<?= $c['id'] ?>, '<?= htmlspecialchars($c['first_name'].' '.$c['last_name'], ENT_QUOTES) ?>', <?= (float)$c['balance'] ?>)"
                data-search="<?= mb_strtolower(htmlspecialchars($c['first_name'].' '.$c['last_name'].' '.$c['phone'], ENT_QUOTES)) ?>">
                <div>
                    <div class="ep-list-item-name"><?= htmlspecialchars($c['first_name'].' '.$c['last_name']) ?></div>
                    <div class="ep-list-item-sub"><?= htmlspecialchars($c['phone']) ?></div>
                </div>
                <div style="text-align:right">
                    <div class="ep-list-item-price" id="bal-<?= $c['id'] ?>"><?= number_format((float)$c['balance'], 0, '.', ' ') ?> ₽</div>
                    <div style="font-size:11px;color:var(--gray-400);margin-top:2px">нажмите для пополнения</div>
                </div>
            </li>
        <?php endforeach; ?>
        <?php if (empty($clients_all)): ?>
            <li style="padding:40px;text-align:center;color:var(--gray-400)">Клиентов пока нет</li>
        <?php endif; ?>
        </ul>
        <div id="no-results" style="display:none;padding:30px;text-align:center;color:var(--gray-400)">
            Ничего не найдено
        </div>
    </div>
</div>

<!-- ===== Модальное окно пополнения баланса ===== -->
<div id="topup-modal" style="display:none;position:fixed;inset:0;z-index:1000;
     background:rgba(0,0,0,.45);align-items:center;justify-content:center">
    <div style="background:var(--white);border-radius:var(--radius);padding:28px 30px;
                width:100%;max-width:380px;box-shadow:0 8px 40px rgba(0,0,0,.18);
                animation:fadeUp .2s ease">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:18px">
            <div>
                <div style="font-size:16px;font-weight:700;color:var(--black)" id="modal-name"></div>
                <div style="font-size:12px;color:var(--gray-400);margin-top:2px">
                    Текущий баланс: <strong id="modal-balance" style="color:var(--black)"></strong>
                </div>
            </div>
            <button onclick="closeModal()" style="border:none;background:none;font-size:20px;
                    cursor:pointer;color:var(--gray-400);line-height:1">✕</button>
        </div>

        <div id="modal-msg"></div>

        <div class="ep-form-group">
            <label class="form-label">Сумма пополнения (₽)</label>
            <input type="number" class="form-input" id="modal-amount"
                   value="500" min="1" max="100000" step="50">
        </div>

        <!-- Быстрые суммы -->
        <div style="display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap">
            <?php foreach ([100, 300, 500, 1000, 2000] as $q): ?>
            <button type="button"
                    onclick="document.getElementById('modal-amount').value=<?= $q ?>"
                    style="padding:5px 12px;border:1px solid var(--gray-200);
                           border-radius:var(--radius-sm);background:var(--white);
                           font-size:12px;font-weight:500;cursor:pointer;color:var(--gray-800)">
                <?= number_format($q, 0, '.', ' ') ?> ₽
            </button>
            <?php endforeach; ?>
        </div>

        <div style="display:flex;gap:10px">
            <button class="btn-primary" style="flex:1" onclick="submitTopup()">
                💰 Пополнить
            </button>
            <button onclick="closeModal()"
                    style="flex:1;padding:10px;border:1px solid var(--gray-200);
                           border-radius:var(--radius-sm);background:var(--white);
                           font-size:14px;font-weight:500;cursor:pointer;color:var(--gray-800)">
                Отмена
            </button>
        </div>
    </div>
</div>

<style>
@keyframes fadeUp {
    from { opacity:0; transform:translateY(16px); }
    to   { opacity:1; transform:translateY(0); }
}
</style>

<script>
let currentClientId = null;

function openTopup(id, name, balance) {
    currentClientId = id;
    document.getElementById('modal-name').textContent = name;
    document.getElementById('modal-balance').textContent = balance.toLocaleString('ru-RU') + ' ₽';
    document.getElementById('modal-amount').value = 500;
    document.getElementById('modal-msg').innerHTML = '';
    const m = document.getElementById('topup-modal');
    m.style.display = 'flex';
}

function closeModal() {
    document.getElementById('topup-modal').style.display = 'none';
    currentClientId = null;
}

// Закрытие по клику вне модалки
document.getElementById('topup-modal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});

function submitTopup() {
    const amount = parseFloat(document.getElementById('modal-amount').value);
    if (!amount || amount < 1) {
        document.getElementById('modal-msg').innerHTML =
            '<div class="alert alert-error" style="margin-bottom:12px">Введите корректную сумму</div>';
        return;
    }

    const fd = new FormData();
    fd.append('action', 'topup');
    fd.append('client_id', currentClientId);
    fd.append('amount', amount);

    fetch('registration.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.ok) {
                // Обновляем баланс в строке списка
                const balEl = document.getElementById('bal-' + currentClientId);
                if (balEl) balEl.textContent = Math.round(data.new_balance).toLocaleString('ru-RU') + ' ₽';
                document.getElementById('modal-balance').textContent =
                    Math.round(data.new_balance).toLocaleString('ru-RU') + ' ₽';
                document.getElementById('modal-msg').innerHTML =
                    '<div class="alert alert-success" style="margin-bottom:12px">✅ Баланс пополнен на '
                    + amount.toLocaleString('ru-RU') + ' ₽</div>';
            } else {
                document.getElementById('modal-msg').innerHTML =
                    '<div class="alert alert-error" style="margin-bottom:12px">' + data.error + '</div>';
            }
        })
        .catch(() => {
            document.getElementById('modal-msg').innerHTML =
                '<div class="alert alert-error" style="margin-bottom:12px">Ошибка соединения</div>';
        });
}

// ── Поиск по клиентам ─────────────────────────────────────────
function filterClients(q) {
    q = q.toLowerCase().trim();
    const rows = document.querySelectorAll('.client-row');
    let found = 0;
    rows.forEach(row => {
        const match = !q || row.dataset.search.includes(q);
        row.style.display = match ? '' : 'none';
        if (match) found++;
    });
    document.getElementById('no-results').style.display = (found === 0 && q) ? 'block' : 'none';
}
</script>

</body>
</html>
