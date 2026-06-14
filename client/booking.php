<?php
require_once '../config.php';
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'client') {
    header("Location: ../login-form.php"); exit;
}
$uid = $_SESSION['user_id'];
$msg = '';

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
        "SELECT equipment_id, total_amount FROM bookings
         WHERE id=$bid AND client_id=$uid AND status='active'"
    )->fetch_assoc();
    if ($b) {
        $conn->query("UPDATE bookings SET status='cancelled' WHERE id=$bid");
        $conn->query("UPDATE equipment SET status='free' WHERE id=" . (int)$b['equipment_id']);
        $ret = (float)$b['total_amount'];
        if ($ret > 0) {
            $conn->query("UPDATE users SET balance = balance + $ret WHERE id=$uid");
            $conn->query("INSERT INTO payments (user_id, amount, type, description)
                          VALUES ($uid, $ret, 'topup', 'Возврат за отменённое бронирование')");
        }
    }
    header("Location: booking.php"); exit;
}

// ── Продление бронирования (AJAX) ─────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'extend') {
    header('Content-Type: application/json');
    $bid   = (int)($_POST['booking_id']  ?? 0);
    $extra = max(1, (int)($_POST['extra_hours'] ?? 1));

    // Получаем бронирование и тариф
    $b = $conn->query(
        "SELECT b.equipment_id, b.service_id, b.end_time,
                s.price AS tariff_price, s.name AS tariff_name,
                e.number AS pc_number
         FROM bookings b
         JOIN services  s ON b.service_id   = s.id
         JOIN equipment e ON b.equipment_id = e.id
         WHERE b.id = $bid AND b.client_id = $uid AND b.status = 'active'
         LIMIT 1"
    )->fetch_assoc();

    if (!$b) {
        echo json_encode(['ok' => false, 'msg' => 'Бронирование не найдено или уже завершено']);
        exit;
    }

    $price = (float)$b['tariff_price'];
    $cost  = round($price * $extra, 2);
    $bal   = (float)$conn->query("SELECT balance FROM users WHERE id=$uid")->fetch_assoc()['balance'];

    if ($bal < $cost) {
        echo json_encode([
            'ok'  => false,
            'msg' => 'Недостаточно средств. Баланс: ' . number_format($bal, 0, '.', ' ')
                   . ' ₽, нужно: ' . number_format($cost, 0, '.', ' ') . ' ₽'
        ]);
        exit;
    }

    // Обновляем бронирование
    $stmt = $conn->prepare(
        "UPDATE bookings
         SET end_time      = DATE_ADD(end_time, INTERVAL ? HOUR),
             duration_hours = duration_hours + ?,
             total_amount   = total_amount + ?
         WHERE id = ?"
    );
    $stmt->bind_param("iidi", $extra, $extra, $cost, $bid);

    // Списываем с баланса
    $upd = $conn->prepare("UPDATE users SET balance = balance - ? WHERE id = ?");
    $upd->bind_param("di", $cost, $uid);

    // Записываем платёж
    $neg_cost = -$cost;
    $desc     = 'Продление сессии (ПК #' . $b['pc_number'] . ', +' . $extra
              . ' ч, тариф: ' . $b['tariff_name'] . ')';
    $pay = $conn->prepare(
        "INSERT INTO payments (user_id, amount, type, description) VALUES (?, ?, 'booking', ?)"
    );
    $pay->bind_param("ids", $uid, $neg_cost, $desc);

    if ($stmt->execute() && $upd->execute() && $pay->execute()) {
        $new_bal = (float)$conn->query("SELECT balance FROM users WHERE id=$uid")->fetch_assoc()['balance'];
        echo json_encode([
            'ok'      => true,
            'msg'     => 'Продлено на ' . $extra . ' ч. Списано ' . number_format($cost, 0, '.', ' ') . ' ₽',
            'new_bal' => $new_bal
        ]);
    } else {
        echo json_encode(['ok' => false, 'msg' => 'Ошибка БД: ' . htmlspecialchars($conn->error)]);
    }
    exit;
}

// ── Новое бронирование ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $equip_id = (int)($_POST['equipment_id'] ?? 0);
    $date     = $_POST['date']       ?? date('Y-m-d');
    $time     = $_POST['start_time'] ?? '10:00';
    $duration = max(1, (int)($_POST['duration'] ?? 1));
    $error    = '';

    if ($equip_id < 1) {
        $error = 'Выберите компьютер';
    } else {
        // ── Автоопределение тарифа ──────────────────────────
        $hour     = (int)date('H', strtotime("$date $time"));
        $is_night = ($hour >= 22 || $hour < 7);

        $pc_info = $conn->query(
            "SELECT e.*, h.name AS hall_name
             FROM equipment e
             LEFT JOIN halls h ON e.hall_id = h.id
             WHERE e.id = $equip_id"
        )->fetch_assoc();

        $is_vip_hall = $pc_info && stripos($pc_info['hall_name'] ?? '', 'vip') !== false;

        // Базовый тариф зависит от зала (VIP или Стандарт)
        if ($is_vip_hall) {
            $tariff_name = 'VIP';
        } else {
            $tariff_name = 'Стандарт';
        }
        // Ночное время — скидка 20% поверх базового тарифа
        $night_discount = $is_night ? 20 : 0;

        $svc = $conn->query(
            "SELECT * FROM services
             WHERE name = '$tariff_name' AND category = 'pc_rent' AND status = 'active'
             LIMIT 1"
        )->fetch_assoc();

        if (!$svc) {
            // Запасной вариант — берём любой активный тариф аренды
            $svc = $conn->query(
                "SELECT * FROM services WHERE category='pc_rent' AND status='active' LIMIT 1"
            )->fetch_assoc();
        }

        if (!$svc) {
            $error = 'Тарифы не настроены. Обратитесь к администратору.';
        } else {
            $service_id = (int)$svc['id'];
            $base_price = (float)$svc['price'];

            // ── Поиск применимой акции по condition_type ─────────
            $promo_id   = null;
            $discount   = 0;
            $promo_name = '';

            $dow       = (int)date('N', strtotime($date)); // 1=Пн, 7=Вс
            $is_happy  = ($hour >= 14 && $hour < 17 && $dow <= 5);
            $is_weekend = ($dow >= 6); // 6=Сб, 7=Вс

            // Убеждаемся что колонка condition_type есть
            $conn->query("ALTER TABLE promotions ADD COLUMN IF NOT EXISTS condition_type VARCHAR(30) NOT NULL DEFAULT 'always'");

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
                    default:            $fits = false;
                }
                if ($fits) {
                    $promo_id   = (int)$p['id'];
                    $discount   = (int)$p['discount_percent'];
                    $promo_name = $p['name'];
                    break; // берём первую подходящую с максимальной скидкой
                }
            }

            // Ночная скидка (20%) + акция суммируются
            $combined_discount = min(100, $discount + $night_discount);
            $total = round($base_price * $duration * (1 - $combined_discount / 100), 2);
            $bal   = (float)$conn->query("SELECT balance FROM users WHERE id=$uid")->fetch_assoc()['balance'];

            if (!$pc_info || $pc_info['status'] !== 'free') {
                $error = 'Выбранный компьютер уже занят';
            } elseif ($bal < $total) {
                $error = 'Недостаточно средств (' . number_format($bal, 0, '.', ' ') . ' ₽). Пополните баланс.';
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

        $emp    = $conn->query("SELECT id FROM users WHERE role IN ('employee','admin') LIMIT 1")->fetch_assoc();
        $emp_id = $emp ? (int)$emp['id'] : $uid;

        // Типы: uid(i) emp(i) equip(i) service(i) promo(i) start_time(s) duration(i) end_time(s) total(d)
        $stmt = $conn->prepare(
            "INSERT INTO bookings
             (client_id, employee_id, equipment_id, service_id, promotion_id,
              start_time, duration_hours, end_time, status, total_amount)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active', ?)"
        );
        $stmt->bind_param("iiiiisisd",
            $uid, $emp_id, $equip_id, $service_id, $promo_id,
            $s, $duration, $e, $total
        );

        $upd_bal = $conn->prepare("UPDATE users SET balance = balance - ? WHERE id = ?");
        $upd_bal->bind_param("di", $total, $uid);

        $upd_pc = $conn->prepare("UPDATE equipment SET status = 'busy' WHERE id = ?");
        $upd_pc->bind_param("i", $equip_id);

        $neg_total = -$total;
        $tariff_label = $tariff_name . ($is_night ? ' (ночной -20%)' : '');
        $pay_desc  = 'Оплата игровой сессии (ПК #' . ($pc_info['number'] ?? $equip_id) . ', ' . $duration . ' ч, тариф: ' . $tariff_label . ')';
        $log_pay   = $conn->prepare("INSERT INTO payments (user_id, amount, type, description) VALUES (?, ?, 'booking', ?)");
        $log_pay->bind_param("ids", $uid, $neg_total, $pay_desc);

        if ($stmt->execute() && $upd_bal->execute() && $upd_pc->execute() && $log_pay->execute()) {
            $night_text = $night_discount > 0 ? ' + ночная скидка ' . $night_discount . '%' : '';
            $promo_text = $discount > 0 ? " (скидка $discount% «$promo_name»$night_text)" : ($night_discount > 0 ? " (ночная скидка $night_discount%)" : '');
            $msg = '<div class="alert alert-success">Бронирование оформлено! Тариф: '
                   . htmlspecialchars($tariff_name) . $promo_text
                   . '. Списано ' . number_format($total, 0, '.', ' ') . ' ₽</div>';
        } else {
            $msg = '<div class="alert alert-error">Ошибка БД: ' . htmlspecialchars($conn->error) . '</div>';
        }
    }
}

// ── Данные для страницы ──────────────────────────────────────
$free_pcs_res = $conn->query(
    "SELECT e.*, h.name AS hall_name, h.id AS hall_id
     FROM equipment e
     LEFT JOIN halls h ON e.hall_id = h.id
     WHERE e.status = 'free'
     ORDER BY e.number"
);
$pcs_data = [];
while ($row = $free_pcs_res->fetch_assoc()) $pcs_data[] = $row;

// Тарифные ID для JS (чтобы считать цену без перезагрузки)
$tariffs_map = [];
$t_res = $conn->query("SELECT id, name, price FROM services WHERE category='pc_rent' AND status='active'");
while ($t = $t_res->fetch_assoc()) $tariffs_map[$t['name']] = $t;

// Активные акции для JS
$promos_js = [];
$p_res = $conn->query(
    "SELECT * FROM promotions
     WHERE status='active'
       AND (start_date IS NULL OR start_date <= CURDATE())
       AND (end_date   IS NULL OR end_date   >= CURDATE())
     ORDER BY discount_percent DESC"
);
while ($p = $p_res->fetch_assoc()) $promos_js[] = $p;

// ── Список бронирований клиента (с ценой тарифа для продления) ──
$my_bookings = $conn->query("
    SELECT b.*,
           CONCAT('ПК #', e.number) AS pc_label,
           s.name  AS tariff_name,
           s.price AS tariff_price
    FROM bookings b
    LEFT JOIN equipment e ON b.equipment_id = e.id
    LEFT JOIN services  s ON b.service_id   = s.id
    WHERE b.client_id = $uid
    ORDER BY b.created_at DESC
    LIMIT 10
");

$preselect = isset($_GET['pc']) ? (int)$_GET['pc'] : 0;
$bal_now   = (float)$conn->query("SELECT balance FROM users WHERE id=$uid")->fetch_assoc()['balance'];
?>
<?php include 'header.php'; ?>

<div class="employee-layout">

    <!-- ===== Форма бронирования ===== -->
    <div class="employee-panel">
        <div class="ep-header">
            <div class="ep-title">Новое бронирование</div>
            <div class="ep-desc">Забронировать рабочее место</div>
        </div>
        <div class="ep-body">
            <?= $msg ?>
            <form method="POST" id="booking_form">

                <div class="ep-form-group">
                    <label class="form-label">Компьютер</label>
                    <select class="form-select" name="equipment_id" id="pc_select" required onchange="updateTariff()">
                        <option value="">Выберите компьютер</option>
                        <?php foreach ($pcs_data as $p): ?>
                        <option value="<?= $p['id'] ?>"
                                data-hall="<?= htmlspecialchars($p['hall_name'] ?? '') ?>"
                                <?= $preselect === (int)$p['id'] ? 'selected' : '' ?>>
                            ПК #<?= $p['number'] ?> — <?= htmlspecialchars($p['gpu']) ?>
                            (<?= htmlspecialchars($p['hall_name'] ?? '') ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Конфиг выбранного ПК -->
                <div id="pc_config" style="display:none;border:1px solid var(--gray-200);border-radius:var(--radius-sm);
                     padding:12px 14px;background:var(--gray-50);margin-top:-6px;margin-bottom:14px;font-size:12.5px">
                    <div style="font-size:11px;text-transform:uppercase;letter-spacing:.05em;color:var(--gray-400);font-weight:600;margin-bottom:8px">
                        Характеристики ПК
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:5px 12px;color:var(--gray-700)">
                        <div>🖥 <span id="pc_cpu"></span></div>
                        <div>🎮 <span id="pc_gpu"></span></div>
                        <div>💾 <span id="pc_ram"></span></div>
                        <div>🖵 <span id="pc_monitor"></span></div>
                        <div>⌨️ <span id="pc_keyboard"></span></div>
                        <div>🖱 <span id="pc_mouse"></span></div>
                    </div>
                </div>

                <div class="ep-form-group">
                    <label class="form-label">Дата</label>
                    <input type="date" class="form-input" name="date" id="date_input"
                           value="<?= date('Y-m-d') ?>"
                           min="<?= date('Y-m-d') ?>" required onchange="updateTariff()">
                </div>

                <div class="ep-form-group">
                    <label class="form-label">Время начала</label>
                    <input type="time" class="form-input" name="start_time" id="time_input"
                           value="<?= date('H:i') ?>" required onchange="updateTariff()">
                </div>

                <div class="ep-form-group">
                    <label class="form-label">Продолжительность (часов)</label>
                    <input type="number" class="form-input" name="duration" id="dur_input"
                           value="1" min="1" max="24" required onchange="updateTariff()">
                </div>

                <!-- Автоматический тариф (только для отображения) -->
                <div class="ep-form-group">
                    <label class="form-label">Тариф <span style="color:var(--gray-400);font-weight:400">(определяется автоматически)</span></label>
                    <div id="tariff_info" style="border:1px solid var(--gray-200);border-radius:var(--radius-sm);padding:11px 14px;background:var(--gray-50);font-size:13.5px;color:var(--gray-800)">
                        — выберите ПК и время
                    </div>
                </div>

                <!-- Акция (если применяется) -->
                <div id="promo_block" style="display:none;border-left:3px solid var(--green);padding:10px 14px;background:#f0fdf4;border-radius:0 var(--radius-sm) var(--radius-sm) 0;margin-bottom:14px;font-size:13px">
                    <strong style="color:#15803d">🎉 Акция применена:</strong>
                    <span id="promo_name"></span> — скидка <span id="promo_pct"></span>%
                </div>

                <!-- Итого -->
                <div id="calc_block" style="display:none;background:var(--gray-50);border-radius:var(--radius-sm);padding:14px 16px;margin-bottom:14px;font-size:13.5px">
                    <div style="display:flex;justify-content:space-between;margin-bottom:6px">
                        <span style="color:var(--gray-600)">Базовая цена:</span>
                        <span id="base_price_str">0 ₽</span>
                    </div>
                    <div id="discount_row" style="display:none;justify-content:space-between;margin-bottom:6px">
                        <span style="color:var(--green)">Скидка:</span>
                        <span id="discount_str" style="color:var(--green)"></span>
                    </div>
                    <div style="display:flex;justify-content:space-between;margin-bottom:6px;font-weight:700">
                        <span style="color:var(--gray-600)">Итого к оплате:</span>
                        <strong id="total_sum">0 ₽</strong>
                    </div>
                    <div style="display:flex;justify-content:space-between">
                        <span style="color:var(--gray-600)">Баланс после оплаты:</span>
                        <span id="after_bal">— ₽</span>
                    </div>
                </div>

                <button type="submit" class="btn-primary">Забронировать и оплатить</button>
            </form>
        </div>
    </div>

    <!-- ===== Мои бронирования ===== -->
    <div class="employee-panel" style="display:flex;flex-direction:column;max-height:calc(100vh - 120px);overflow:hidden">
        <div class="ep-header">
            <div class="ep-title">Мои бронирования</div>
            <div class="ep-desc">История и активные бронирования</div>
        </div>
        <ul class="ep-list" style="flex:1;overflow-y:auto;max-height:calc(100vh - 220px)">
        <?php
        $any = false;
        while ($b = $my_bookings->fetch_assoc()):
            $any  = true;
            $sc   = $b['status'] === 'active'   ? 'badge-blue'
                  : ($b['status'] === 'finished' ? 'badge-gray' : 'badge-red');
            $st   = $b['status'] === 'active'   ? 'Активно'
                  : ($b['status'] === 'finished' ? 'Завершено'  : 'Отменено');
            $t_price = (float)($b['tariff_price'] ?? 0);
        ?>
        <li style="padding:14px 16px;border-bottom:1px solid var(--gray-100)">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:6px">
                <strong style="font-size:14px"><?= htmlspecialchars($b['pc_label'] ?? 'ПК') ?></strong>
                <span class="badge <?= $sc ?>"><?= $st ?></span>
            </div>
            <div style="font-size:12.5px;color:var(--gray-600);margin-bottom:4px">
                <?= date('d.m.Y в H:i', strtotime($b['start_time'])) ?>
                · <?= (int)$b['duration_hours'] ?> ч
                · <?= number_format((float)$b['total_amount'], 0, '.', ' ') ?> ₽
            </div>
            <?php if ($b['end_time']): ?>
            <div style="font-size:12px;color:var(--gray-400);margin-bottom:4px">
                До <?= date('d.m.Y H:i', strtotime($b['end_time'])) ?>
            </div>
            <?php endif; ?>
            <div style="font-size:12px;color:var(--gray-400);margin-bottom:8px">
                <?= htmlspecialchars($b['tariff_name'] ?? '') ?>
            </div>
            <?php if ($b['status'] === 'active'): ?>
            <div style="display:flex;gap:8px;flex-wrap:wrap">
                <button type="button"
                        class="action-btn"
                        style="font-size:12px;cursor:pointer;border:1px solid var(--blue);
                               color:var(--blue);background:var(--blue-light);border-radius:var(--radius-xs);
                               padding:4px 12px;font-family:inherit"
                        onclick="openExtend(<?= $b['id'] ?>, <?= $t_price ?>, '<?= htmlspecialchars($b['pc_label'], ENT_QUOTES) ?>')">
                    ⏱ Продлить
                </button>
                <a href="?cancel=<?= $b['id'] ?>"
                   class="action-btn btn-delete" style="font-size:12px"
                   onclick="return confirm('Отменить бронирование? Средства вернутся на баланс.')">
                   Отменить
                </a>
            </div>
            <?php endif; ?>
        </li>
        <?php endwhile; ?>
        <?php if (!$any): ?>
        <li style="padding:40px;text-align:center;color:var(--gray-400)">Бронирований пока нет</li>
        <?php endif; ?>
        </ul>
    </div>

</div>

<!-- ══════════════ МОДАЛКА ПРОДЛЕНИЯ ══════════════ -->
<div id="extend_modal"
     style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);
            z-index:1000;align-items:center;justify-content:center;padding:16px">
    <div style="background:#fff;border-radius:var(--radius);padding:28px 30px;
                width:100%;max-width:360px;box-shadow:0 20px 60px rgba(0,0,0,.22)">

        <!-- Заголовок -->
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:4px">
            <div style="font-size:16px;font-weight:700">⏱ Продление сессии</div>
            <button onclick="closeExtend()"
                    style="border:none;background:none;font-size:20px;cursor:pointer;
                           color:var(--gray-400);line-height:1">✕</button>
        </div>
        <div id="ext_pc_label" style="font-size:13px;color:var(--gray-600);margin-bottom:20px"></div>

        <!-- Поле часов -->
        <div style="margin-bottom:14px">
            <label style="font-size:12px;font-weight:600;color:var(--gray-600);
                          text-transform:uppercase;letter-spacing:.04em;display:block;margin-bottom:6px">
                Дополнительных часов
            </label>
            <input type="number" id="ext_hours" value="1" min="1" max="24"
                   class="form-input" oninput="updateExtCalc()">
        </div>

        <!-- Расчёт стоимости -->
        <div style="background:var(--gray-50);border-radius:var(--radius-sm);
                    padding:12px 14px;margin-bottom:16px;font-size:13px">
            <div style="display:flex;justify-content:space-between;margin-bottom:5px">
                <span style="color:var(--gray-600)">Тариф:</span>
                <span id="ext_tariff_str">— ₽/ч</span>
            </div>
            <div style="display:flex;justify-content:space-between;margin-bottom:5px;font-weight:700">
                <span style="color:var(--gray-600)">К оплате:</span>
                <strong id="ext_total_str">— ₽</strong>
            </div>
            <div style="display:flex;justify-content:space-between">
                <span style="color:var(--gray-600)">Баланс после:</span>
                <span id="ext_after_str" style="font-weight:600">— ₽</span>
            </div>
        </div>

        <!-- Сообщение об ошибке / успехе -->
        <div id="ext_msg" style="display:none;margin-bottom:12px;font-size:13px;
             padding:10px 12px;border-radius:var(--radius-sm)"></div>

        <!-- Кнопки -->
        <div style="display:flex;gap:10px">
            <button type="button" onclick="closeExtend()"
                    style="flex:1;padding:10px;border:1px solid var(--gray-200);
                           border-radius:var(--radius-sm);background:#fff;
                           font-family:inherit;font-size:14px;cursor:pointer;color:var(--gray-600)">
                Отмена
            </button>
            <button type="button" id="ext_submit" onclick="submitExtend()"
                    style="flex:1;padding:10px;border:none;border-radius:var(--radius-sm);
                           background:var(--blue);color:#fff;font-family:inherit;
                           font-size:14px;font-weight:600;cursor:pointer">
                Продлить и оплатить
            </button>
        </div>
    </div>
</div>

<script>
const userBalance = <?= json_encode($bal_now) ?>;

// Данные ПК (id → объект с характеристиками)
const PC_DATA = <?= json_encode(array_column($pcs_data, null, 'id')) ?>;

// Тарифы из БД (название → цена)
const TARIFFS = <?= json_encode([
    'Стандарт' => isset($tariffs_map['Стандарт']) ? (float)$tariffs_map['Стандарт']['price'] : 100,
    'VIP'      => isset($tariffs_map['VIP'])      ? (float)$tariffs_map['VIP']['price']      : 200,
    'Ночной'   => isset($tariffs_map['Ночной'])   ? (float)$tariffs_map['Ночной']['price']   : 80,
]) ?>;

// Акции из БД
const PROMOS = <?= json_encode(array_map(function($p) {
    return [
        'id'        => (int)$p['id'],
        'name'      => $p['name'],
        'discount'  => (int)$p['discount_percent'],
        'condition' => $p['condition_type'] ?? 'always',
    ];
}, $promos_js)) ?>;

// ── Расчёт тарифа для формы нового бронирования ──
function updateTariff() {
    const pcSel  = document.getElementById('pc_select');
    const timeIn = document.getElementById('time_input').value;
    const dateIn = document.getElementById('date_input').value;
    const dur    = parseInt(document.getElementById('dur_input').value) || 1;

    // Конфиг ПК
    const pcConfig = document.getElementById('pc_config');
    if (pcSel.value && PC_DATA[pcSel.value]) {
        const pc = PC_DATA[pcSel.value];
        document.getElementById('pc_cpu').textContent      = pc.cpu      || '—';
        document.getElementById('pc_gpu').textContent      = pc.gpu      || '—';
        document.getElementById('pc_ram').textContent      = pc.ram      || '—';
        document.getElementById('pc_monitor').textContent  = pc.monitor  || '—';
        document.getElementById('pc_keyboard').textContent = pc.keyboard || '—';
        document.getElementById('pc_mouse').textContent    = pc.mouse    || '—';
        pcConfig.style.display = 'block';
    } else {
        pcConfig.style.display = 'none';
    }

    if (!pcSel.value || !timeIn) {
        document.getElementById('tariff_info').textContent = '— выберите ПК и время';
        document.getElementById('calc_block').style.display = 'none';
        document.getElementById('promo_block').style.display = 'none';
        return;
    }

    const hall   = pcSel.options[pcSel.selectedIndex].dataset.hall || '';
    const hour   = parseInt(timeIn.split(':')[0]);
    const isNight = (hour >= 22 || hour < 7);
    const isVip   = hall.toLowerCase().includes('vip');

    // Базовый тариф
    let tariffName, tariffPrice;
    if (isVip) {
        tariffName  = 'VIP';
        tariffPrice = TARIFFS['VIP'] || 200;
    } else {
        tariffName  = 'Стандарт';
        tariffPrice = TARIFFS['Стандарт'] || 100;
    }
    const nightDiscount = isNight ? 20 : 0;

    // Определяем акцию
    let discount = 0, promoName = '';
    const date   = new Date(dateIn);
    const dow    = date.getDay();
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
    const base    = tariffPrice * dur;
    const saved   = Math.round(base * combinedDiscount / 100);
    const total   = base - saved;
    const after   = userBalance - total;

    // Тариф-блок
    const tariffLabels = {
        'VIP':      '⭐ VIP-зал — ' + tariffPrice + ' ₽/ч',
        'Стандарт': '🖥 Стандарт — ' + tariffPrice + ' ₽/ч',
    };
    let tariffDisplay = (tariffLabels[tariffName] || tariffName);
    if (isNight) tariffDisplay += ' 🌙 + ночная скидка 20%';
    document.getElementById('tariff_info').textContent = tariffDisplay;

    // Акция-блок
    const promoBlock = document.getElementById('promo_block');
    if (combinedDiscount > 0) {
        let promoDisplay = promoName || '';
        if (nightDiscount > 0 && discount > 0) {
            promoDisplay = promoName + ' + ночная скидка';
        } else if (nightDiscount > 0) {
            promoDisplay = 'Ночное время (22:00–07:00)';
        }
        document.getElementById('promo_name').textContent = promoDisplay;
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
    document.getElementById('after_bal').textContent = after.toLocaleString('ru') + ' ₽';
    document.getElementById('after_bal').style.color = after < 0 ? 'var(--red)' : 'var(--gray-800)';
    document.getElementById('calc_block').style.display = 'block';
}

// Инициализация при загрузке
document.addEventListener('DOMContentLoaded', updateTariff);

// ── Модалка продления ────────────────────────────────────────
let extBookingId   = null;
let extTariffPrice = 0;

function openExtend(bookingId, tariffPrice, pcLabel) {
    extBookingId   = bookingId;
    extTariffPrice = tariffPrice;
    document.getElementById('ext_pc_label').textContent = pcLabel;
    document.getElementById('ext_hours').value          = 1;
    document.getElementById('ext_msg').style.display    = 'none';
    document.getElementById('ext_submit').disabled      = false;
    document.getElementById('ext_submit').textContent   = 'Продлить и оплатить';
    updateExtCalc();
    document.getElementById('extend_modal').style.display = 'flex';
}

function closeExtend() {
    document.getElementById('extend_modal').style.display = 'none';
}

function updateExtCalc() {
    const hours   = parseInt(document.getElementById('ext_hours').value) || 1;
    const total   = extTariffPrice * hours;
    const after   = userBalance - total;

    document.getElementById('ext_tariff_str').textContent = extTariffPrice.toLocaleString('ru') + ' ₽/ч';
    document.getElementById('ext_total_str').textContent  = total.toLocaleString('ru') + ' ₽';

    const afterEl = document.getElementById('ext_after_str');
    afterEl.textContent  = after.toLocaleString('ru') + ' ₽';
    afterEl.style.color  = after < 0 ? 'var(--red)' : 'var(--gray-800)';
}

function submitExtend() {
    const hours  = parseInt(document.getElementById('ext_hours').value) || 1;
    const btn    = document.getElementById('ext_submit');
    const msgEl  = document.getElementById('ext_msg');

    btn.disabled      = true;
    btn.textContent   = 'Обработка...';
    msgEl.style.display = 'none';

    const fd = new FormData();
    fd.append('action',      'extend');
    fd.append('booking_id',  extBookingId);
    fd.append('extra_hours', hours);

    fetch('booking.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.ok) {
                msgEl.style.cssText =
                    'display:block;background:#f0fdf4;color:#15803d;' +
                    'border:1px solid #bbf7d0;padding:10px 12px;' +
                    'border-radius:var(--radius-sm);margin-bottom:12px;font-size:13px';
                msgEl.textContent = '✅ ' + data.msg;
                // Обновляем баланс в шапке без перезагрузки
                if (data.new_bal !== undefined) {
                    const balEls = document.querySelectorAll('.panel-header-right [style*="font-weight:700"]');
                    balEls.forEach(el => {
                        el.textContent = Number(data.new_bal).toLocaleString('ru') + ' ₽';
                    });
                }
                setTimeout(() => { closeExtend(); location.reload(); }, 1500);
            } else {
                msgEl.style.cssText =
                    'display:block;background:var(--red-light);color:var(--red);' +
                    'border:1px solid #fecdd3;padding:10px 12px;' +
                    'border-radius:var(--radius-sm);margin-bottom:12px;font-size:13px';
                msgEl.textContent = '⚠ ' + data.msg;
                btn.disabled    = false;
                btn.textContent = 'Продлить и оплатить';
            }
        })
        .catch(() => {
            msgEl.style.cssText =
                'display:block;background:var(--red-light);color:var(--red);' +
                'border:1px solid #fecdd3;padding:10px 12px;' +
                'border-radius:var(--radius-sm);margin-bottom:12px;font-size:13px';
            msgEl.textContent   = 'Ошибка соединения. Попробуйте ещё раз.';
            btn.disabled        = false;
            btn.textContent     = 'Продлить и оплатить';
        });
}

// Закрытие по клику вне модального окна
document.getElementById('extend_modal').addEventListener('click', function(e) {
    if (e.target === this) closeExtend();
});
</script>

</body>
</html>
