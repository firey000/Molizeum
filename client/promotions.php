<?php
require_once '../config.php';
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'client') {
    header("Location: ../login-form.php"); exit;
}

$promos = $conn->query(
    "SELECT * FROM promotions
     WHERE status = 'active'
       AND (end_date IS NULL OR end_date >= CURDATE())
     ORDER BY discount_percent DESC"
);
?>
<?php include 'header.php'; ?>

<div class="main-content">
    <div class="table-card">
        <div class="table-card-header">
            <div>
                <div class="table-card-title">Акции и специальные предложения</div>
                <div class="table-card-desc">Текущие акции клуба Молизеум</div>
            </div>
        </div>

        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(270px,1fr));gap:16px;padding:20px">
        <?php
        $rows = [];
        while ($p = $promos->fetch_assoc()) $rows[] = $p;

        if (empty($rows)):
        ?>
            <div class="empty-state" style="grid-column:1/-1">
                <p>Активных акций пока нет. Следите за обновлениями!</p>
            </div>
        <?php else: foreach ($rows as $p): ?>
            <div style="border:1px solid var(--gray-200);border-radius:var(--radius-sm);
                        padding:20px;background:var(--white);position:relative;
                        transition:box-shadow .2s"
                 onmouseover="this.style.boxShadow='0 4px 20px rgba(0,0,0,.08)'"
                 onmouseout="this.style.boxShadow='none'">

                <!-- Процент скидки -->
                <div style="position:absolute;top:16px;right:16px;
                            background:var(--red);color:#fff;
                            font-size:14px;font-weight:800;
                            padding:5px 11px;border-radius:20px;
                            letter-spacing:-0.3px">
                    -<?= $p['discount_percent'] ?>%
                </div>

                <div style="font-size:16px;font-weight:700;color:var(--black);margin-bottom:6px;padding-right:56px">
                    <?= htmlspecialchars($p['name']) ?>
                </div>

                <div style="font-size:13px;color:var(--gray-600);margin-bottom:14px;line-height:1.5">
                    <?= htmlspecialchars($p['description']) ?>
                </div>

                <div style="display:flex;align-items:center;justify-content:space-between">
                    <span class="badge badge-green">Активна</span>
                    <?php if ($p['end_date']): ?>
                    <span style="font-size:12px;color:var(--gray-400)">
                        до <?= date('d.m.Y', strtotime($p['end_date'])) ?>
                    </span>
                    <?php else: ?>
                    <span style="font-size:12px;color:var(--gray-400)">Бессрочно</span>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; endif; ?>
        </div>
    </div>
</div>

</body>
</html>
