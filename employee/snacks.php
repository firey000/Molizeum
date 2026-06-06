<?php
require_once '../config.php';
if (!isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], ['admin','employee'])) {
    header("Location: ../login-form.php"); exit;
}

$msg = '';
$emp_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $client_id  = (int)$_POST['client_id'];
    $service_id = (int)$_POST['service_id'];
    $qty        = max(1, (int)$_POST['quantity']);

    $svc = $conn->query("SELECT name, price FROM services WHERE id=$service_id AND category='food' AND status='active'")->fetch_assoc();
    $cli = $conn->query("SELECT first_name, last_name, balance FROM users WHERE id=$client_id AND role='client'")->fetch_assoc();

    if ($svc && $cli) {
        $total   = round($svc['price'] * $qty, 2);
        $balance = (float)$cli['balance'];
        $cname   = htmlspecialchars($cli['first_name'].' '.$cli['last_name']);

        if ($balance < $total) {
            $shortage = $total - $balance;
            $msg = '<div class="alert alert-error">⚠️ Недостаточно средств у клиента <strong>'.$cname.'</strong>. '
                 . 'Баланс: '.number_format($balance,0,'.',' ').' ₽, '
                 . 'необходимо: '.number_format($total,0,'.',' ').' ₽, '
                 . 'не хватает: '.number_format($shortage,0,'.',' ').' ₽.</div>';
        } else {
            $stmt = $conn->prepare(
                "INSERT INTO snack_sales (client_id, service_id, employee_id, quantity, total_price)
                 VALUES (?,?,?,?,?)"
            );
            $stmt->bind_param("iiiid", $client_id, $service_id, $emp_id, $qty, $total);
            if ($stmt->execute()) {
                $neg = -$total;
                $desc = 'Покупка снеков: '.htmlspecialchars($svc['name']).' × '.$qty.' шт.';
                $conn->query("UPDATE users SET balance = balance - $total WHERE id=$client_id");
                $conn->prepare("INSERT INTO payments (user_id, amount, type, description) VALUES (?,?,'snack',?)")
                     ->bind_param("ids", $client_id, $neg, $desc) && true;
                $conn->execute_query(
                    "INSERT INTO payments (user_id, amount, type, description) VALUES (?,?,?,?)",
                    [$client_id, $neg, 'snack', $desc]
                );
                $msg = '<div class="alert alert-success">✅ Продажа оформлена: <strong>'.htmlspecialchars($svc['name']).'</strong> × '.$qty.' шт. — '
                     . number_format($total,0,'.',' ').' ₽ списано с баланса клиента <strong>'.$cname.'</strong>.</div>';
            } else {
                $msg = '<div class="alert alert-error">Ошибка БД: '.htmlspecialchars($conn->error).'</div>';
            }
        }
    } else {
        $msg = '<div class="alert alert-error">Клиент или товар не найден.</div>';
    }
}

$clients_res = $conn->query("SELECT id, first_name, last_name, phone, balance FROM users WHERE role='client' AND status='active' ORDER BY first_name");
$clients_arr = [];
while ($c = $clients_res->fetch_assoc()) $clients_arr[] = $c;

$snacks_res = $conn->query("SELECT * FROM services WHERE category='food' AND status='active' ORDER BY name");
$snacks_arr = [];
while ($s = $snacks_res->fetch_assoc()) $snacks_arr[] = $s;

$current = 'snacks.php';
$name = $_SESSION['user_name'] ?? '';
?>
<?php include 'header.php'; ?>

<div class="employee-layout">
    <!-- Left: Sale Form -->
    <div class="employee-panel">
        <div class="ep-header">
            <div class="ep-title">Продажа снеков</div>
            <div class="ep-desc">Оформление покупки для клиента</div>
        </div>
        <div class="ep-body">
            <?= $msg ?>

            <!-- Баланс и итог — подсказка -->
            <div id="balance-hint" style="display:none;background:var(--blue-light);border-radius:var(--radius-sm);
                 padding:12px 14px;margin-bottom:14px;font-size:13px;color:var(--blue)">
                💰 Баланс клиента: <strong id="hint-balance">0 ₽</strong>
                &nbsp;·&nbsp; К оплате: <strong id="hint-cost">0 ₽</strong>
                <span id="hint-warn" style="display:none;color:var(--red);font-weight:600">
                    &nbsp;— Не хватает <span id="hint-shortage"></span> ₽!
                </span>
            </div>

            <form method="POST" id="snack-form">
                <div class="ep-form-group">
                    <label class="form-label">Клиент</label>
                    <select class="form-select" name="client_id" id="sel-client" required onchange="recalc()">
                        <option value="">Выберите клиента</option>
                        <?php foreach ($clients_arr as $c): ?>
                        <option value="<?= $c['id'] ?>"
                                data-balance="<?= number_format((float)$c['balance'], 2, '.', '') ?>">
                            <?= htmlspecialchars($c['first_name'].' '.$c['last_name']) ?>
                            — <?= htmlspecialchars($c['phone']) ?>
                            (<?= number_format((float)$c['balance'], 0, '.', ' ') ?> ₽)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="ep-form-group">
                    <label class="form-label">Товар</label>
                    <select class="form-select" name="service_id" id="sel-snack" required onchange="recalc()">
                        <option value="" data-price="0">Выберите товар</option>
                        <?php foreach ($snacks_arr as $s): ?>
                        <option value="<?= $s['id'] ?>"
                                data-price="<?= number_format((float)$s['price'], 2, '.', '') ?>">
                            <?= htmlspecialchars($s['name']) ?> — <?= number_format($s['price'],0,'.',' ') ?> ₽
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="ep-form-group">
                    <label class="form-label">Количество</label>
                    <input type="number" class="form-input" name="quantity" id="inp-qty"
                           value="1" min="1" max="99" required oninput="recalc()">
                </div>

                <!-- Итог -->
                <div id="calc-block" style="display:none;background:var(--gray-50);border-radius:var(--radius-sm);
                     padding:14px 16px;margin-bottom:14px;font-size:13.5px">
                    <div style="display:flex;justify-content:space-between;margin-bottom:6px">
                        <span style="color:var(--gray-600)">Цена за шт.:</span>
                        <span id="calc-unit">0 ₽</span>
                    </div>
                    <div style="display:flex;justify-content:space-between;font-weight:700">
                        <span style="color:var(--gray-600)">Итого к оплате:</span>
                        <strong id="calc-total">0 ₽</strong>
                    </div>
                </div>

                <button type="submit" id="btn-submit" class="btn-primary" style="margin-top:6px">Продать</button>
            </form>
        </div>
    </div>

    <!-- Right: Snack list -->
    <div class="employee-panel">
        <div class="ep-header">
            <div class="ep-title">Товары в наличии</div>
            <div class="ep-desc">Доступные снеки и напитки</div>
        </div>
        <ul class="ep-list" style="max-height:420px;overflow-y:auto">
        <?php foreach ($snacks_arr as $s): ?>
            <li class="ep-list-item" style="cursor:pointer" onclick="selectSnack(<?= $s['id'] ?>)"
                onmouseover="this.style.background='var(--gray-50)'" onmouseout="this.style.background=''">
                <div>
                    <div class="ep-list-item-name"><?= htmlspecialchars($s['name']) ?></div>
                </div>
                <div class="ep-list-item-price"><?= number_format($s['price'],0,'.',' ') ?> ₽</div>
            </li>
        <?php endforeach; ?>
        </ul>
    </div>
</div>

<script>
function recalc() {
    const clientSel = document.getElementById('sel-client');
    const snackSel  = document.getElementById('sel-snack');
    const qty       = parseInt(document.getElementById('inp-qty').value) || 1;
    const hint      = document.getElementById('balance-hint');
    const calcBlock = document.getElementById('calc-block');
    const btn       = document.getElementById('btn-submit');

    const balance = parseFloat(clientSel.options[clientSel.selectedIndex]?.dataset?.balance || 0);
    const price   = parseFloat(snackSel.options[snackSel.selectedIndex]?.dataset?.price || 0);
    const total   = Math.round(price * qty * 100) / 100;

    // Показываем блок итога только если выбран товар
    if (price > 0) {
        document.getElementById('calc-unit').textContent  = price.toLocaleString('ru') + ' ₽';
        document.getElementById('calc-total').textContent = total.toLocaleString('ru') + ' ₽';
        calcBlock.style.display = 'block';
    } else {
        calcBlock.style.display = 'none';
    }

    // Показываем подсказку по балансу только если выбран клиент
    if (clientSel.value && price > 0) {
        document.getElementById('hint-balance').textContent = balance.toLocaleString('ru') + ' ₽';
        document.getElementById('hint-cost').textContent    = total.toLocaleString('ru') + ' ₽';
        hint.style.display = 'block';

        const warn     = document.getElementById('hint-warn');
        const shortage = total - balance;
        if (shortage > 0) {
            document.getElementById('hint-shortage').textContent = Math.ceil(shortage).toLocaleString('ru');
            warn.style.display = 'inline';
            hint.style.background = 'var(--red-light)';
            hint.style.color      = 'var(--red)';
            btn.disabled = true;
            btn.style.opacity = '0.5';
        } else {
            warn.style.display = 'none';
            hint.style.background = 'var(--blue-light)';
            hint.style.color      = 'var(--blue)';
            btn.disabled = false;
            btn.style.opacity = '1';
        }
    } else {
        hint.style.display = 'none';
        btn.disabled = false;
        btn.style.opacity = '1';
    }
}

// Клик по строке списка — выбирает товар в форме
function selectSnack(id) {
    const sel = document.getElementById('sel-snack');
    sel.value = id;
    recalc();
}
</script>

</body>
</html>
