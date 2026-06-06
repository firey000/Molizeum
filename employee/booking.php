<?php
require_once '../config.php';
if (!isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], ['admin','employee'])) {
    header("Location: ../login-form.php"); exit;
}

$msg = '';
$emp_id = $_SESSION['user_id'];

  // Автоматически завершаем просроченные бронирования
  $conn->query("
      UPDATE bookings b
      LEFT JOIN equipment e ON b.equipment_id = e.id
      SET b.status = 'finished', e.status = 'free'
      WHERE b.status = 'active' AND b.end_time < NOW()
  ");
  

// ── Отмена бронирования ──────────────────────────────────────
if (isset($_GET['cancel']) && is_numeric($_GET['cancel'])) {
    $bid = (int)$_GET['cancel'];
    $b = $conn->query(
        "SELECT equipment_id, total_amount, client_id FROM bookings
         WHERE id=$bid AND status='active'"
    )->fetch_assoc();
    if ($b) {
        $conn->query("UPDATE bookings SET status='cancelled' WHERE id=$bid");
        $conn->query("UPDATE equipment SET status='free' WHERE id=" . (int)$b['equipment_id']);
        $ret = (float)$b['total_amount'];
        $cid = (int)$b['client_id'];
        if ($ret > 0 && $cid > 0) {
            $conn->query("UPDATE users SET balance = balance + $ret WHERE id=$cid");
            $conn->query("INSERT INTO payments (user_id, amount, type, description)
                          VALUES ($cid, $ret, 'topup', 'Возврат за отменённое бронирование (сотрудник)')");
        }
    }
    header("Location: booking.php"); exit;
}

// ── Новое бронирование ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $client_id = (int)($_POST['client_id'] ?? 0);
    $equip_id  = (int)($_POST['equipment_id'] ?? 0);
    $date      = $_POST['date']       ?? date('Y-m-d');
    $time      = $_POST['start_time'] ?? '10:00';
    $duration  = max(1, (int)($_POST['duration'] ?? 1));
    $error     = '';

    if ($client_id < 1) { $error = 'Выберите клиента'; }
    elseif ($equip_id < 1) { $error = 'Выберите компьютер'; }
    else {
        // Автоопределение тарифа (как у клиента)
        $hour     = (int)date('H', strtotime("$date $time"));
        $is_night = ($hour >= 22 || $hour < 7);

        $pc_info = $conn->query(
            "SELECT e.*, h.name AS hall_name
             FROM equipment e LEFT JOIN halls h ON e.hall_id = h.id
             WHERE e.id = $equip_id"
        )->fetch_assoc();

        $is_vip_hall = $pc_info && stripos($pc_info['hall_name'] ?? '', 'vip') !== false;
        $tariff_name = $is_vip_hall ? 'VIP' : 'Стандарт';
        $night_discount = $is_night ? 20 : 0;

        $svc = $conn->query(
            "SELECT * FROM services WHERE name = '$tariff_name' AND category = 'pc_rent' AND status = 'active' LIMIT 1"
        )->fetch_assoc();
        if (!$svc) {
            $svc = $conn->query("SELECT * FROM services WHERE category='pc_rent' AND status='active' LIMIT 1")->fetch_assoc();
        }

        if (!$svc) {
            $error = 'Тарифы не настроены. Обратитесь к администратору.';
        } else {
            $service_id = (int)$svc['id'];
            $base_price = (float)$svc['price'];

            // Поиск применимой акции
            $promo_id = null; $discount = 0; $promo_name = '';
            $dow       = (int)date('N', strtotime($date));
            $is_happy  = ($hour >= 14 && $hour < 17 && $dow <= 5);
            $is_weekend = ($dow >= 6);

            $conn->query("ALTER TABLE promotions ADD COLUMN IF NOT EXISTS condition_type VARCHAR(30) NOT NULL DEFAULT 'always'");
            // Автоисправление: акция "Выходные с друзьями" — только weekends
            $conn->query("UPDATE promotions SET condition_type='weekend' WHERE name='Выходные с друзьями' AND condition_type='always'");

            $promo_res = $conn->query(
                "SELECT * FROM promotions
                 WHERE status = 'active'
                   AND (start_date IS NULL OR start_date <= '$date')
                   AND (end_date   IS NULL OR end_date   >= '$date')
                 ORDER BY discount_percent DESC"
            );
            while ($p = $promo_res->fetch_assoc()) {
                $cond = $p['condition_type'] ?? 'always';
                $fits = false;
                switch ($cond) {
                    case 'always':      $fits = true; break;
                    case 'night':       $fits = $is_night; break;
                    case 'happy_hours': $fits = $is_happy; break;
                    case 'weekend':     $fits = $is_weekend; break;
                }
                if ($fits) {
                    $promo_id   = (int)$p['id'];
                    $discount   = (int)$p['discount_percent'];
                    $promo_name = $p['name'];
                    break;
                }
            }

            $combined_discount = min(100, $discount + $night_discount);
            $total = round($base_price * $duration * (1 - $combined_discount / 100), 2);

            // Проверка баланса клиента
            $bal_row = $conn->query("SELECT balance, first_name, last_name FROM users WHERE id=$client_id")->fetch_assoc();
            $client_balance = $bal_row ? (float)$bal_row['balance'] : 0;
            $client_name = $bal_row ? htmlspecialchars($bal_row['first_name'].' '.$bal_row['last_name']) : 'Клиент';

            if (!$pc_info || $pc_info['status'] !== 'free') {
                $error = 'Выбранный компьютер уже занят';
            } elseif ($client_balance < $total) {
                $shortage = $total - $client_balance;
                $error = '⚠️ Недостаточно средств у клиента '.$client_name.
                         '. Баланс: '.number_format($client_balance,0,'.',' ').' ₽, '.
                         'необходимо: '.number_format($total,0,'.',' ').' ₽, '.
                         'не хватает: '.number_format($shortage,0,'.',' ').' ₽.';
            }
        }
    }

    if ($error) {
        $msg = '<div class="alert alert-error">' . htmlspecialchars($error) . '</div>';
    } else {
        $start_dt = new DateTime("$date $time");
        $end_dt   = clone $start_dt;
        $end_dt->modify("+{$duration} hours");
        $s = $start_dt->format('Y-m-d H:i:s');
        $e = $end_dt->format('Y-m-d H:i:s');

        $stmt = $conn->prepare(
            "INSERT INTO bookings
             (client_id, employee_id, equipment_id, service_id, promotion_id,
              start_time, duration_hours, end_time, status, total_amount)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active', ?)"
        );
        $stmt->bind_param("iiiiisisd",
            $client_id, $emp_id, $equip_id, $service_id, $promo_id,
            $s, $duration, $e, $total
        );

        $upd_bal = $conn->prepare("UPDATE users SET balance = balance - ? WHERE id = ?");
        $upd_bal->bind_param("di", $total, $client_id);

        $upd_pc = $conn->prepare("UPDATE equipment SET status = 'busy' WHERE id = ?");
        $upd_pc->bind_param("i", $equip_id);

        $neg_total    = -$total;
        $tariff_label = $tariff_name . ($is_night ? ' (ночной -20%)' : '');
        $pay_desc     = 'Оплата игровой сессии (ПК #'.($pc_info['number'] ?? $equip_id).', '.$duration.' ч, тариф: '.$tariff_label.')';
        $log_pay = $conn->prepare("INSERT INTO payments (user_id, amount, type, description) VALUES (?, ?, 'booking', ?)");
        $log_pay->bind_param("ids", $client_id, $neg_total, $pay_desc);

        if ($stmt->execute() && $upd_bal->execute() && $upd_pc->execute() && $log_pay->execute()) {
            $night_text = $night_discount > 0 ? ' + ночная скидка '.$night_discount.'%' : '';
            $promo_text = $discount > 0 ? " (скидка $discount% «$promo_name»$night_text)" : ($night_discount > 0 ? " (ночная скидка $night_discount%)" : '');
            $msg = '<div class="alert alert-success">✅ Бронирование оформлено для клиента <strong>'.htmlspecialchars($client_name).'</strong>! Тариф: '
                   . htmlspecialchars($tariff_name) . $promo_text
                   . '. Списано ' . number_format($total, 0, '.', ' ') . ' ₽.</div>';
        } else {
            $msg = '<div class="alert alert-error">Ошибка БД: ' . htmlspecialchars($conn->error) . '</div>';
        }
    }
}

// ── Данные для страницы ──────────────────────────────────────
$clients_res = $conn->query("SELECT id, first_name, last_name, phone, balance FROM users WHERE role='client' AND status='active' ORDER BY first_name");
$clients_arr = [];
while ($c = $clients_res->fetch_assoc()) $clients_arr[] = $c;

$free_pcs_res = $conn->query(
    "SELECT e.*, h.name AS hall_name, h.id AS hall_id
     FROM equipment e LEFT JOIN halls h ON e.hall_id = h.id
     WHERE e.status = 'free'
     ORDER BY e.number"
);
$pcs_data = [];
while ($row = $free_pcs_res->fetch_assoc()) $pcs_data[] = $row;

// Тарифы для JS
$tariffs_map = [];
$t_res = $conn->query("SELECT id, name, price FROM services WHERE category='pc_rent' AND status='active'");
while ($t = $t_res->fetch_assoc()) $tariffs_map[$t['name']] = $t;

// Акции для JS
$promos_js = [];
$p_res = $conn->query(
    "SELECT * FROM promotions
     WHERE status='active'
       AND (start_date IS NULL OR start_date <= CURDATE())
       AND (end_date   IS NULL OR end_date   >= CURDATE())
     ORDER BY discount_percent DESC"
);
while ($p = $p_res->fetch_assoc()) $promos_js[] = $p;

// Последние бронирования (для сотрудника — все активные)
$recent_bookings = $conn->query("
    SELECT b.*, CONCAT('ПК #', e.number) AS pc_label,
           s.name AS tariff_name,
           CONCAT(u.first_name, ' ', u.last_name) AS client_name
    FROM bookings b
    LEFT JOIN equipment e ON b.equipment_id = e.id
    LEFT JOIN services  s ON b.service_id   = s.id
    LEFT JOIN users     u ON b.client_id    = u.id
    WHERE b.status = 'active'
    ORDER BY b.created_at DESC
    LIMIT 15
");

$current = 'booking.php';
$name = $_SESSION['user_name'] ?? '';
?>
<?php include 'header.php'; ?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;padding:24px;max-width:1400px;margin:0 auto">

    <!-- ===== КОЛОНКА 1: Форма бронирования ===== -->
    <div class="employee-panel">
        <div class="ep-header">
            <div class="ep-title">Оформление бронирования</div>
            <div class="ep-desc">Автоматический расчёт тарифа и акций</div>
        </div>
        <div class="ep-body">
            <?= $msg ?>

            <!-- Подсказка о балансе клиента -->
            <div id="balance-hint" style="display:none;background:var(--blue-light);border-radius:var(--radius-sm);
                padding:12px 14px;margin-bottom:14px;font-size:13px;color:var(--blue)">
                💰 Баланс клиента: <strong id="hint-balance">0 ₽</strong>
                &nbsp;·&nbsp; К оплате: <strong id="hint-cost">0 ₽</strong>
                <span id="hint-warn" style="display:none;color:var(--red);font-weight:600">
                    &nbsp;— Не хватает <span id="hint-shortage"></span> ₽!
                </span>
            </div>

            <form method="POST" id="booking-form">

                <div class="ep-form-group">
                    <label class="form-label">Клиент</label>
                    <select class="form-select" name="client_id" id="sel-client" required onchange="recalc()">
                        <option value="">Выберите клиента</option>
                        <?php foreach ($clients_arr as $c): ?>
                        <option value="<?= $c['id'] ?>"
                                data-balance="<?= (float)$c['balance'] ?>">
                            <?= htmlspecialchars($c['first_name'].' '.$c['last_name']) ?>
                            — <?= htmlspecialchars($c['phone']) ?>
                            (<?= number_format((float)$c['balance'], 0, '.', ' ') ?> ₽)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="ep-form-group">
                    <label class="form-label">Компьютер</label>
                    <select class="form-select" name="equipment_id" id="pc_select" required onchange="onPcChange()">
                        <option value="">Выберите компьютер</option>
                        <?php foreach ($pcs_data as $p): ?>
                        <option value="<?= $p['id'] ?>"
                                data-hall="<?= htmlspecialchars($p['hall_name'] ?? '') ?>"
                                data-num="<?= $p['number'] ?>"
                                data-cpu="<?= htmlspecialchars($p['cpu'] ?? '') ?>"
                                data-gpu="<?= htmlspecialchars($p['gpu'] ?? '') ?>"
                                data-ram="<?= htmlspecialchars($p['ram'] ?? '') ?>"
                                data-monitor="<?= htmlspecialchars($p['monitor'] ?? '') ?>"
                                data-keyboard="<?= htmlspecialchars($p['keyboard'] ?? '') ?>"
                                data-mouse="<?= htmlspecialchars($p['mouse'] ?? '') ?>">
                            ПК #<?= $p['number'] ?> — <?= htmlspecialchars($p['gpu']) ?>
                            (<?= htmlspecialchars($p['hall_name'] ?? '') ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="ep-form-group">
                    <label class="form-label">Дата</label>
                    <input type="date" class="form-input" name="date" id="date_input"
                           value="<?= date('Y-m-d') ?>"
                           min="<?= date('Y-m-d') ?>" required onchange="recalc()">
                </div>

                <div class="ep-form-group">
                    <label class="form-label">Время начала</label>
                    <input type="time" class="form-input" name="start_time" id="time_input"
                           value="<?= date('H:i') ?>" required onchange="recalc()">
                </div>

                <div class="ep-form-group">
                    <label class="form-label">Продолжительность (часов)</label>
                    <input type="number" class="form-input" name="duration" id="dur_input"
                           value="1" min="1" max="24" required onchange="recalc()">
                </div>

                <!-- Автотариф -->
                <div class="ep-form-group">
                    <label class="form-label">Тариф <span style="color:var(--gray-400);font-weight:400">(авто)</span></label>
                    <div id="tariff_info" style="border:1px solid var(--gray-200);border-radius:var(--radius-sm);
                         padding:11px 14px;background:var(--gray-50);font-size:13.5px;color:var(--gray-800)">
                        — выберите ПК и время
                    </div>
                </div>

                <!-- Акция -->
                <div id="promo_block" style="display:none;border-left:3px solid var(--green);padding:10px 14px;
                     background:#f0fdf4;border-radius:0 var(--radius-sm) var(--radius-sm) 0;margin-bottom:14px;font-size:13px">
                    <strong style="color:#15803d">🎉 Акция применена:</strong>
                    <span id="promo_name"></span> — скидка <span id="promo_pct"></span>%
                </div>

                <!-- Итого -->
                <div id="calc_block" style="display:none;background:var(--gray-50);border-radius:var(--radius-sm);
                     padding:14px 16px;margin-bottom:14px;font-size:13.5px">
                    <div style="display:flex;justify-content:space-between;margin-bottom:6px">
                        <span style="color:var(--gray-600)">Базовая цена:</span>
                        <span id="base_price_str">0 ₽</span>
                    </div>
                    <div id="discount_row" style="display:none;justify-content:space-between;margin-bottom:6px">
                        <span style="color:var(--green)">Скидка:</span>
                        <span id="discount_str" style="color:var(--green)"></span>
                    </div>
                    <div style="display:flex;justify-content:space-between;font-weight:700;margin-bottom:6px">
                        <span style="color:var(--gray-600)">Итого к оплате:</span>
                        <strong id="total_sum">0 ₽</strong>
                    </div>
                    <div style="display:flex;justify-content:space-between">
                        <span style="color:var(--gray-600)">Баланс клиента после оплаты:</span>
                        <span id="after_bal" style="font-weight:600">— ₽</span>
                    </div>
                </div>

                <button type="submit" class="btn-primary" style="margin-top:6px">Оформить бронирование</button>
            </form>
        </div>
    </div>

    <!-- ===== КОЛОНКА 2: Конфигурация ПК + активные брони ===== -->
    <div style="display:flex;flex-direction:column;gap:24px">

        <!-- Конфигурация выбранного ПК -->
        <div class="employee-panel">
            <div class="ep-header">
                <div class="ep-title">Конфигурация ПК</div>
                <div class="ep-desc" id="pc_conf_title">Выберите компьютер для просмотра</div>
            </div>
            <div id="pc_conf_empty" style="padding:32px;text-align:center;color:var(--gray-400);font-size:13.5px">
                🖥 Выберите ПК в форме слева
            </div>
            <div id="pc_conf_body" style="display:none;padding:16px 20px">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px 20px">
                    <div class="pc-spec-card">
                        <div class="pc-spec-label">⚙️ Процессор</div>
                        <div class="pc-spec-value" id="spec_cpu">—</div>
                    </div>
                    <div class="pc-spec-card">
                        <div class="pc-spec-label">🎮 Видеокарта</div>
                        <div class="pc-spec-value" id="spec_gpu">—</div>
                    </div>
                    <div class="pc-spec-card">
                        <div class="pc-spec-label">💾 Оперативная память</div>
                        <div class="pc-spec-value" id="spec_ram">—</div>
                    </div>
                    <div class="pc-spec-card">
                        <div class="pc-spec-label">🖥 Монитор</div>
                        <div class="pc-spec-value" id="spec_monitor">—</div>
                    </div>
                    <div class="pc-spec-card">
                        <div class="pc-spec-label">⌨️ Клавиатура</div>
                        <div class="pc-spec-value" id="spec_keyboard">—</div>
                    </div>
                    <div class="pc-spec-card">
                        <div class="pc-spec-label">🖱 Мышь</div>
                        <div class="pc-spec-value" id="spec_mouse">—</div>
                    </div>
                </div>
                <div style="margin-top:14px;padding-top:12px;border-top:1px solid var(--gray-100)">
                    <span class="badge badge-green">✅ Свободен</span>
                    <span id="spec_hall" style="margin-left:10px;font-size:12.5px;color:var(--gray-500)"></span>
                </div>
            </div>
        </div>

        <!-- Все свободные ПК -->
        <div class="employee-panel" style="flex:1;display:flex;flex-direction:column">
            <div class="ep-header">
                <div class="ep-title">Свободные компьютеры</div>
                <div class="ep-desc"><?= count($pcs_data) ?> шт. доступно прямо сейчас</div>
            </div>
            <?php if (empty($pcs_data)): ?>
                <div style="padding:32px;text-align:center;color:var(--gray-400)">Все ПК заняты</div>
            <?php else: ?>
            <div style="flex:1;overflow-y:auto">
                <table style="width:100%;border-collapse:collapse;font-size:12.5px">
                    <thead>
                        <tr style="background:var(--gray-50);color:var(--gray-500);font-size:11.5px;text-transform:uppercase;letter-spacing:.04em">
                            <th style="padding:8px 12px;text-align:left;font-weight:600">ПК</th>
                            <th style="padding:8px 12px;text-align:left;font-weight:600">CPU</th>
                            <th style="padding:8px 12px;text-align:left;font-weight:600">GPU</th>
                            <th style="padding:8px 12px;text-align:left;font-weight:600">RAM</th>
                            <th style="padding:8px 12px;text-align:left;font-weight:600">Монитор</th>
                            <th style="padding:8px 12px;text-align:left;font-weight:600">Зал</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($pcs_data as $p): ?>
                        <tr style="border-bottom:1px solid var(--gray-100);cursor:pointer;transition:background .15s"
                            onmouseover="this.style.background='var(--gray-50)'" onmouseout="this.style.background=''"
                            onclick="selectPc(<?= $p['id'] ?>)">
                            <td style="padding:9px 12px;font-weight:700">
                                <span style="background:var(--blue-light);color:var(--blue);padding:2px 8px;border-radius:20px;font-size:12px">
                                    #<?= $p['number'] ?>
                                </span>
                            </td>
                            <td style="padding:9px 12px;color:var(--gray-700)"><?= htmlspecialchars($p['cpu'] ?? '—') ?></td>
                            <td style="padding:9px 12px;color:var(--gray-700);font-weight:500"><?= htmlspecialchars($p['gpu'] ?? '—') ?></td>
                            <td style="padding:9px 12px;color:var(--gray-600)"><?= htmlspecialchars($p['ram'] ?? '—') ?></td>
                            <td style="padding:9px 12px;color:var(--gray-600)"><?= htmlspecialchars($p['monitor'] ?? '—') ?></td>
                            <td style="padding:9px 12px">
                                <span style="background:<?= stripos($p['hall_name']??'','vip')!==false ? '#fef9c3' : 'var(--gray-100)' ?>;
                                      color:<?= stripos($p['hall_name']??'','vip')!==false ? '#854d0e' : 'var(--gray-600)' ?>;
                                      padding:2px 7px;border-radius:20px;font-size:11px;font-weight:500">
                                    <?= htmlspecialchars($p['hall_name'] ?? '—') ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

    </div><!-- /колонка 2 -->
</div>

<!-- ===== Активные бронирования (полная ширина) ===== -->
<div style="padding:0 24px 28px;max-width:1400px;margin:0 auto">
    <div class="employee-panel">
        <div class="ep-header">
            <div class="ep-title">Активные бронирования</div>
            <div class="ep-desc">Текущие сессии клиентов</div>
        </div>
        <div style="max-height:380px;overflow-y:auto">
        <ul class="ep-list" style="margin:0;padding:0;list-style:none">
            <?php
            $any = false;
            while ($b = $recent_bookings->fetch_assoc()):
                $any = true;
            ?>
            <li style="padding:12px 16px;border-bottom:1px solid var(--gray-100)">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:4px">
                    <strong style="font-size:13.5px"><?= htmlspecialchars($b['pc_label'] ?? 'ПК') ?></strong>
                    <span class="badge badge-blue">Активно</span>
                </div>
                <div style="font-size:12.5px;color:var(--gray-600);margin-bottom:2px">
                    👤 <?= htmlspecialchars($b['client_name'] ?? '') ?>
                </div>
                <div style="font-size:12px;color:var(--gray-500)">
                    <?= date('d.m.Y H:i', strtotime($b['start_time'])) ?>
                    → <?= date('H:i', strtotime($b['end_time'])) ?>
                    · <?= (int)$b['duration_hours'] ?> ч
                    · <?= number_format((float)$b['total_amount'], 0, '.', ' ') ?> ₽
                </div>
                <a href="?cancel=<?= $b['id'] ?>"
                   style="font-size:11.5px;color:var(--red);text-decoration:none;margin-top:4px;display:inline-block"
                   onclick="return confirm('Отменить бронирование? Средства вернутся клиенту.')">
                   Отменить бронирование
                </a>
            </li>
            <?php endwhile; ?>
            <?php if (!$any): ?>
            <li style="padding:32px;text-align:center;color:var(--gray-400)">Активных бронирований нет</li>
            <?php endif; ?>
        </ul>
        </div>
    </div>
</div>

<style>
.pc-spec-card {
    background: var(--gray-50);
    border: 1px solid var(--gray-100);
    border-radius: var(--radius-sm);
    padding: 10px 14px;
}
.pc-spec-label {
    font-size: 11px;
    color: var(--gray-400);
    text-transform: uppercase;
    letter-spacing: .05em;
    margin-bottom: 4px;
    font-weight: 600;
}
.pc-spec-value {
    font-size: 13.5px;
    color: var(--gray-800);
    font-weight: 500;
    word-break: break-word;
}
</style>

<script>
// ── Данные из PHP ────────────────────────────────────────────
const TARIFFS = <?= json_encode([
    'Стандарт' => isset($tariffs_map['Стандарт']) ? (float)$tariffs_map['Стандарт']['price'] : 100,
    'VIP'      => isset($tariffs_map['VIP'])      ? (float)$tariffs_map['VIP']['price']      : 200,
]) ?>;

const PROMOS = <?= json_encode(array_map(function($p) {
    return [
        'id'        => (int)$p['id'],
        'name'      => $p['name'],
        'discount'  => (int)$p['discount_percent'],
        'condition' => $p['condition_type'] ?? 'always',
    ];
}, $promos_js)) ?>;

// ── Показать конфигурацию ПК ─────────────────────────────────
function showPcConfig(opt) {
    if (!opt || !opt.value) {
        document.getElementById('pc_conf_empty').style.display = 'block';
        document.getElementById('pc_conf_body').style.display  = 'none';
        document.getElementById('pc_conf_title').textContent   = 'Выберите компьютер для просмотра';
        return;
    }
    const d = opt.dataset;
    document.getElementById('spec_cpu').textContent      = d.cpu      || '—';
    document.getElementById('spec_gpu').textContent      = d.gpu      || '—';
    document.getElementById('spec_ram').textContent      = d.ram      || '—';
    document.getElementById('spec_monitor').textContent  = d.monitor  || '—';
    document.getElementById('spec_keyboard').textContent = d.keyboard || '—';
    document.getElementById('spec_mouse').textContent    = d.mouse    || '—';
    document.getElementById('spec_hall').textContent     = d.hall ? '📍 ' + d.hall : '';
    document.getElementById('pc_conf_title').textContent = 'ПК #' + d.num + ' — ' + (d.hall || '');
    document.getElementById('pc_conf_empty').style.display = 'none';
    document.getElementById('pc_conf_body').style.display  = 'block';
}

// ── Клик по строке таблицы ───────────────────────────────────
function selectPc(id) {
    const sel = document.getElementById('pc_select');
    sel.value = id;
    onPcChange();
    sel.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

function onPcChange() {
    const sel = document.getElementById('pc_select');
    showPcConfig(sel.options[sel.selectedIndex]);
    recalc();
}

// ── Расчёт тарифа и суммы ────────────────────────────────────
function recalc() {
    const pcSel    = document.getElementById('pc_select');
    const clientSel = document.getElementById('sel-client');
    const timeIn   = document.getElementById('time_input').value;
    const dateIn   = document.getElementById('date_input').value;
    const dur      = parseInt(document.getElementById('dur_input').value) || 1;
    const hint     = document.getElementById('balance-hint');

    if (!pcSel.value || !timeIn) {
        document.getElementById('tariff_info').textContent = '— выберите ПК и время';
        document.getElementById('calc_block').style.display  = 'none';
        document.getElementById('promo_block').style.display = 'none';
        hint.style.display = 'none';
        return;
    }

    const hall    = (pcSel.options[pcSel.selectedIndex].dataset.hall || '').toLowerCase();
    const hour    = parseInt(timeIn.split(':')[0]);
    const isNight = (hour >= 22 || hour < 7);
    const isVip   = hall.includes('vip');

    let tariffName  = isVip ? 'VIP' : 'Стандарт';
    let tariffPrice = TARIFFS[tariffName] || 100;
    const nightDiscount = isNight ? 20 : 0;

    // Акция
    let discount = 0, promoName = '';
    const dateObj   = new Date(dateIn);
    const dow       = dateObj.getDay();
    const isWeekday = (dow >= 1 && dow <= 5);
    const isHappy   = isWeekday && (hour >= 14 && hour < 17);
    const isWeekend = (dow === 0 || dow === 6);

    for (const p of PROMOS) {
        let fits = false;
        switch (p.condition) {
            case 'always':      fits = true; break;
            case 'night':       fits = isNight; break;
            case 'happy_hours': fits = isHappy; break;
            case 'weekend':     fits = isWeekend; break;
        }
        if (fits) { discount = p.discount; promoName = p.name; break; }
    }

    const combinedDiscount = Math.min(100, discount + nightDiscount);
    const base  = tariffPrice * dur;
    const saved = Math.round(base * combinedDiscount / 100);
    const total = base - saved;

    // Тариф-блок
    let tariffDisplay = (isVip ? '⭐ VIP — ' : '🖥 Стандарт — ') + tariffPrice + ' ₽/ч';
    if (isNight) tariffDisplay += ' 🌙 +ночная скидка 20%';
    document.getElementById('tariff_info').textContent = tariffDisplay;

    // Акция-блок
    const promoBlock = document.getElementById('promo_block');
    if (combinedDiscount > 0) {
        let label = promoName || '';
        if (nightDiscount > 0 && discount > 0) label = promoName + ' + ночная скидка';
        else if (nightDiscount > 0) label = 'Ночное время (22:00–07:00)';
        document.getElementById('promo_name').textContent = label;
        document.getElementById('promo_pct').textContent  = combinedDiscount;
        promoBlock.style.display = 'block';
    } else {
        promoBlock.style.display = 'none';
    }

    // Итого-блок
    document.getElementById('base_price_str').textContent = base.toLocaleString('ru') + ' ₽';
    const discRow = document.getElementById('discount_row');
    if (combinedDiscount > 0) {
        document.getElementById('discount_str').textContent = '−' + saved.toLocaleString('ru') + ' ₽';
        discRow.style.display = 'flex';
    } else {
        discRow.style.display = 'none';
    }
    document.getElementById('total_sum').textContent = total.toLocaleString('ru') + ' ₽';
    document.getElementById('calc_block').style.display = 'block';

    // Баланс клиента
    const clientOpt = clientSel.options[clientSel.selectedIndex];
    if (clientOpt && clientOpt.value) {
        const balance  = parseFloat(clientOpt.dataset.balance) || 0;
        const after    = balance - total;
        const shortage = total - balance;
        hint.style.display = 'block';
        document.getElementById('hint-balance').textContent = balance.toLocaleString('ru') + ' ₽';
        document.getElementById('hint-cost').textContent    = total.toLocaleString('ru') + ' ₽';
        const warnEl = document.getElementById('hint-warn');
        if (after < 0) {
            warnEl.style.display = 'inline';
            document.getElementById('hint-shortage').textContent = shortage.toLocaleString('ru');
            hint.style.background = 'var(--red-light, #fff1f2)';
            hint.style.color = 'var(--red)';
        } else {
            warnEl.style.display = 'none';
            hint.style.background = 'var(--blue-light)';
            hint.style.color = 'var(--blue)';
        }
        document.getElementById('after_bal').textContent   = after.toLocaleString('ru') + ' ₽';
        document.getElementById('after_bal').style.color   = after < 0 ? 'var(--red)' : 'var(--gray-800)';
    } else {
        hint.style.display = 'none';
    }
}

document.addEventListener('DOMContentLoaded', recalc);
</script>

</body>
</html>