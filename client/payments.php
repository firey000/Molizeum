<?php
require_once '../config.php';
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'client') {
    header("Location: ../login-form.php");
    exit;
}

$uid = $_SESSION['user_id'];
$msg = '';

// ── Пополнение баланса ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['amount'])) {
    $amount = (float)($_POST['amount'] ?? 0);
    $method = trim($_POST['method'] ?? 'card');
    
    if ($amount < 50) {
        $_SESSION['payment_msg'] = '<div class="alert alert-error">Минимальная сумма пополнения — 50 ₽</div>';
    } elseif ($amount > 50000) {
        $_SESSION['payment_msg'] = '<div class="alert alert-error">Максимальная сумма — 50 000 ₽</div>';
    } else {
        $method_labels = ['card' => 'Банковская карта', 'sbp' => 'СБП', 'cash' => 'Наличные'];
        $label = $method_labels[$method] ?? 'Банковская карта';
        $desc = 'Пополнение баланса (' . $label . ')';
        
        // Обновляем баланс
        $stmt1 = $conn->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
        $stmt1->bind_param("di", $amount, $uid);
        $stmt1->execute();
        $stmt1->close();
        
        // Записываем в историю (таблица payments)
        $stmt2 = $conn->prepare("INSERT INTO payments (user_id, amount, type, description) VALUES (?, ?, 'topup', ?)");
        $stmt2->bind_param("ids", $uid, $amount, $desc);
        
        if ($stmt2->execute()) {
            $_SESSION['payment_msg'] = '<div class="alert alert-success">✓ Баланс пополнен на ' 
                   . number_format($amount, 0, '.', ' ') . ' ₽</div>';
        } else {
            $_SESSION['payment_msg'] = '<div class="alert alert-error">Ошибка БД: ' . htmlspecialchars($conn->error) . '</div>';
        }
        $stmt2->close();
    }
    
    // PRG: редирект после обработки POST
    header("Location: payments.php");
    exit;
}

// Показываем сообщение из сессии (если есть)
if (isset($_SESSION['payment_msg'])) {
    $msg = $_SESSION['payment_msg'];
    unset($_SESSION['payment_msg']);
}

// ── Получаем текущий баланс ──────────────────────────────────
$balance_result = $conn->query("SELECT balance FROM users WHERE id = $uid");
$balance = 0.0;
if ($balance_result && $balance_result->num_rows > 0) {
    $row_data = $balance_result->fetch_assoc();
    // ✅ КЛЮЧЕВОЕ ИСПРАВЛЕНИЕ: явное приведение к float
    $balance = isset($row_data['balance']) ? (float)$row_data['balance'] : 0.0;
}

// ── Получаем историю платежей ────────────────────────────────
$history_result = $conn->query(
    "SELECT * FROM payments WHERE user_id = $uid ORDER BY created_at DESC LIMIT 30"
);

$history = [];
if ($history_result) {
    while ($row = $history_result->fetch_assoc()) {
        // ✅ КЛЮЧЕВОЕ ИСПРАВЛЕНИЕ: кэшируем как float сразу
        $row['amount_float'] = isset($row['amount']) ? (float)$row['amount'] : 0.0;
        $history[] = $row;
    }
}

include 'header.php';
?>

<div class="employee-layout">
    <!-- ===== Левая колонка: Пополнение баланса ===== -->
    <div class="employee-panel" style="align-self:flex-start">
        <div class="ep-header">
            <div class="ep-title">Пополнение баланса</div>
            <div class="ep-desc">Онлайн-платежи через платёжные системы</div>
        </div>
        <div class="ep-body">
            <?= $msg ?>
            
            <!-- Текущий баланс -->
            <div style="background:var(--gray-50);border-radius:var(--radius-sm);
                        padding:16px;margin-bottom:18px;text-align:center">
                <div style="font-size:12px;color:var(--gray-400);margin-bottom:4px">Текущий баланс</div>
                <div style="font-size:28px;font-weight:800;color:var(--black)">
                    <?= number_format($balance, 0, '.', ' ') ?> ₽
                </div>
            </div>

            <form method="POST">
                <div class="ep-form-group">
                    <label class="form-label">Сумма пополнения (₽)</label>
                    <input type="number" class="form-input" name="amount"
                           value="500" min="50" max="50000" step="50" required>
                </div>
                
                <!-- Быстрые суммы -->
                <div style="display:flex;gap:8px;margin-bottom:14px;flex-wrap:wrap">
                    <?php foreach ([100, 300, 500, 1000, 2000] as $q): ?>
                        <button type="button"
                                onclick="document.querySelector('[name=amount]').value=<?= $q ?>"
                                style="padding:6px 14px;border:1px solid var(--gray-200);
                                       border-radius:var(--radius-sm);background:var(--white);
                                       font-size:13px;font-weight:500;cursor:pointer;color:var(--gray-800)">
                            <?= number_format($q, 0, '.', ' ') ?> ₽
                        </button>
                    <?php endforeach; ?>
                </div>

                <div class="ep-form-group">
                    <label class="form-label">Способ оплаты</label>
                    <select class="form-select" name="method">
                        <option value="card">💳 Банковская карта</option>
                        <option value="sbp">📲 СБП</option>
                        <option value="cash">💵 Наличные (у сотрудника)</option>
                    </select>
                </div>

                <button type="submit" class="btn-primary" style="margin-top:6px">
                    Пополнить баланс
                </button>
            </form>
        </div>
    </div>

    <!-- ===== Правая колонка: История платежей ===== -->
    <div class="employee-panel">
        <div class="ep-header">
            <div class="ep-title">История платежей</div>
            <div class="ep-desc">Последние транзакции</div>
        </div>
        <ul class="ep-list" style="max-height:calc(100vh - 220px);overflow-y:auto">
            <?php if (empty($history)): ?>
                <li style="padding:40px;text-align:center;color:var(--gray-400)">
                    История транзакций пуста
                </li>
            <?php else: ?>
                <?php foreach ($history as $p):
                    // ✅ Используем заранее приведённое значение
                    $amount_val = $p['amount_float'];
                    $plus   = $amount_val > 0;
                    $color  = $plus ? 'var(--green)' : 'var(--red)';
                    $prefix = $plus ? '+' : '';
                    $icon   = $plus ? '⬆️' : '⬇️';
                ?>
                    <li class="ep-list-item">
                        <div>
                            <div class="ep-list-item-name" style="font-size:13.5px">
                                <?= $icon ?> <?= htmlspecialchars($p['description']) ?>
                            </div>
                            <div class="ep-list-item-sub">
                                <?= date('d.m.Y, H:i', strtotime($p['created_at'])) ?>
                            </div>
                        </div>
                        <strong style="font-size:14px;font-weight:700;color:<?= $color ?>;white-space:nowrap">
                            <?= $prefix . number_format(abs($amount_val), 0, '.', ' ') ?> ₽
                        </strong>
                    </li>
                <?php endforeach; ?>
            <?php endif; ?>
        </ul>
    </div>
</div>

</body>
</html>