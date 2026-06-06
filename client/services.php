<?php
require_once '../config.php';
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'client') {
    header("Location: ../login-form.php"); exit;
}

$free_pcs = $conn->query(
    "SELECT e.*, h.name AS hall_name
     FROM equipment e
     LEFT JOIN halls h ON e.hall_id = h.id
     WHERE e.status = 'free'
     ORDER BY e.number
     LIMIT 4"
);

$tariffs = $conn->query(
    "SELECT * FROM services WHERE category = 'pc_rent' AND status = 'active' ORDER BY price ASC"
);
?>
<?php include 'header.php'; ?>

<div class="main-content">

    <!-- ===== Доступное оборудование ===== -->
    <div class="table-card" style="margin-bottom:24px">
        <div class="table-card-header">
            <div>
                <div class="table-card-title">Доступное оборудование</div>
                <div class="table-card-desc">Просмотр свободных компьютеров</div>
            </div>
        </div>

        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:16px;padding:20px">
        <?php
        $pcs = [];
        while ($p = $free_pcs->fetch_assoc()) $pcs[] = $p;

        if (empty($pcs)):
        ?>
            <div class="empty-state" style="grid-column:1/-1">
                <p>Свободных компьютеров нет</p>
            </div>
        <?php else: foreach ($pcs as $p): ?>
            <div style="border:1px solid var(--gray-200);border-radius:var(--radius-sm);padding:16px;background:var(--white)">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:12px">
                    <strong style="font-size:15px">ПК #<?= $p['number'] ?></strong>
                    <span class="badge badge-green">Свободен</span>
                </div>
                <div style="display:flex;flex-direction:column;gap:5px;margin-bottom:14px;font-size:13px;color:var(--gray-600)">
                    <div style="display:flex;align-items:center;gap:6px">
                        <span style="font-size:14px">🖥</span><?= htmlspecialchars($p['cpu']) ?>
                    </div>
                    <div style="display:flex;align-items:center;gap:6px">
                        <span style="font-size:14px">🎮</span><?= htmlspecialchars($p['gpu']) ?>
                    </div>
                    <div style="display:flex;align-items:center;gap:6px">
                        <span style="font-size:14px">💾</span><?= htmlspecialchars($p['ram']) ?>
                    </div>
                    <div style="display:flex;align-items:center;gap:6px;color:var(--gray-400)">
                        <span style="font-size:14px">🏢</span><?= htmlspecialchars($p['hall_name'] ?? '') ?>
                    </div>
                </div>
                <a href="booking.php?pc=<?= $p['id'] ?>"
                   style="display:block;text-align:center;background:var(--black);color:#fff;
                          padding:9px;border-radius:var(--radius-sm);font-size:13px;
                          font-weight:600;text-decoration:none;transition:background .15s"
                   onmouseover="this.style.background='#333'"
                   onmouseout="this.style.background='var(--black)'">
                    Забронировать
                </a>
            </div>
        <?php endforeach; endif; ?>
        </div>
    </div>

    <!-- ===== Тарифы ===== -->
    <div class="table-card">
        <div class="table-card-header">
            <div>
                <div class="table-card-title">Тарифы</div>
                <div class="table-card-desc">Доступные тарифные планы</div>
            </div>
        </div>

        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:16px;padding:20px">
        <?php while ($t = $tariffs->fetch_assoc()):
            $price = (float)$t['price'];
            $unit = $t['duration_minutes'] ? ($t['duration_minutes'] === 60 ? '/час' : '/'.$t['duration_minutes'].'мин') : '/час';
        ?>
            <div style="border:1px solid var(--gray-200);border-radius:var(--radius-sm);padding:18px 20px;background:var(--white)">
                <div style="font-size:15px;font-weight:700;color:var(--black);margin-bottom:4px">
                    <?= htmlspecialchars($t['name']) ?>
                </div>
                <div style="font-size:12.5px;color:var(--gray-400);margin-bottom:14px">
                    <?php
                    $descs = ['Стандарт'=>'Базовый тариф для обычных дней','VIP'=>'Доступ к лучшему оборудованию','Ночной'=>'Тариф с 00:00 до 08:00'];
                    echo $descs[$t['name']] ?? 'Аренда компьютера';
                    ?>
                </div>
                <div>
                    <span style="font-size:22px;font-weight:800;color:var(--black)">
                        <?= number_format($price, 0, '.', ' ') ?> ₽
                    </span>
                    <span style="font-size:13px;color:var(--gray-400)"><?= $unit ?></span>
                </div>
            </div>
        <?php endwhile; ?>
        </div>
    </div>

</div>

</body>
</html>
