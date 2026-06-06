<?php
require_once '../config.php';
if (!isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], ['admin','employee'])) {
    header("Location: ../login-form.php"); exit;
}

$result = $conn->query("SELECT * FROM services WHERE category = 'pc_rent' ORDER BY price ASC");
$current = 'tariffs.php';
$name = $_SESSION['user_name'] ?? '';
?>
<?php include 'header.php'; ?>

<div class="main-content">
    <div class="table-card">
        <div class="table-card-header">
            <div>
                <div class="table-card-title">Тарифы</div>
                <div class="table-card-desc">Просмотр тарифных планов клуба</div>
            </div>
        </div>

        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:16px;padding:20px">
        <?php
        $has_rows = false;
        while ($t = $result->fetch_assoc()):
            $has_rows = true;
            $price = (float)$t['price'];
            $unit = '/час';
            if (!empty($t['duration_minutes'])) {
                $unit = $t['duration_minutes'] == 60 ? '/час' : '/' . $t['duration_minutes'] . 'мин';
            }
            $descs = [
                'Стандарт' => 'Базовый тариф для обычных дней',
                'VIP'      => 'Доступ к лучшему оборудованию',
                'Ночной'   => 'Тариф с 00:00 до 08:00',
            ];
            $is_active = $t['status'] === 'active';
        ?>
            <div style="border:1px solid var(--gray-200);border-radius:var(--radius-sm);padding:18px 20px;
                        background:var(--white);position:relative;opacity:<?= $is_active ? '1' : '.6' ?>">
                <?php if (!$is_active): ?>
                <span style="position:absolute;top:12px;right:12px" class="badge badge-gray">Неактивен</span>
                <?php endif; ?>
                <div style="font-size:15px;font-weight:700;color:var(--black);margin-bottom:4px">
                    <?= htmlspecialchars($t['name']) ?>
                </div>
                <div style="font-size:12.5px;color:var(--gray-400);margin-bottom:14px">
                    <?= htmlspecialchars($descs[$t['name']] ?? 'Аренда компьютера') ?>
                </div>
                <div>
                    <span style="font-size:22px;font-weight:800;color:var(--black)">
                        <?= number_format($price, 0, '.', ' ') ?> ₽
                    </span>
                    <span style="font-size:13px;color:var(--gray-400)"><?= $unit ?></span>
                </div>
            </div>
        <?php endwhile; ?>
        <?php if (!$has_rows): ?>
            <div style="grid-column:1/-1;text-align:center;padding:40px;color:var(--gray-400)">Тарифов пока нет</div>
        <?php endif; ?>
        </div>
    </div>
</div>

</body>
</html>
