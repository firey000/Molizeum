<?php
require_once '../config.php';
if (!isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], ['admin','employee'])) {
    header("Location: ../login-form.php"); exit;
}

$result = $conn->query("SELECT * FROM promotions ORDER BY status DESC, end_date ASC");
$current = 'promotions.php';
$name = $_SESSION['user_name'] ?? '';

$condition_labels = [
    'always'      => 'Всегда (по датам)',
    'night'       => 'Ночное время (22:00–07:00)',
    'happy_hours' => 'Счастливые часы (14:00–17:00, пн–пт)',
    'weekend'     => 'Выходные (сб–вс)',
];
?>
<?php include 'header.php'; ?>

<div class="main-content">
    <div class="table-card">
        <div class="table-card-header">
            <div>
                <div class="table-card-title">Акции</div>
                <div class="table-card-desc">Просмотр текущих акций клуба</div>
            </div>
        </div>

        <table class="data-table">
            <thead>
                <tr>
                    <th>Название</th>
                    <th>Описание</th>
                    <th>Условие</th>
                    <th>Скидка</th>
                    <th>Дата начала</th>
                    <th>Дата окончания</th>
                    <th>Статус</th>
                </tr>
            </thead>
            <tbody>
            <?php
            $has_rows = false;
            while ($row = $result->fetch_assoc()):
                $has_rows = true;
                $sc  = $row['status'] === 'active' ? 'badge-green' : 'badge-gray';
                $st  = $row['status'] === 'active' ? 'Активна' : 'Неактивна';
                $cnd = $row['condition_type'] ?? 'always';
                $cnd_label = $condition_labels[$cnd] ?? $cnd;
            ?>
            <tr>
                <td><strong><?= htmlspecialchars($row['name']) ?></strong></td>
                <td style="font-size:13px;color:var(--gray-600)"><?= htmlspecialchars($row['description'] ?? '') ?></td>
                <td style="font-size:13px"><?= $cnd_label ?></td>
                <td><strong style="font-size:16px"><?= (int)$row['discount_percent'] ?>%</strong></td>
                <td><?= $row['start_date'] ? date('d.m.Y', strtotime($row['start_date'])) : '—' ?></td>
                <td><?= $row['end_date']   ? date('d.m.Y', strtotime($row['end_date']))   : 'Бессрочно' ?></td>
                <td><span class="badge <?= $sc ?>"><?= $st ?></span></td>
            </tr>
            <?php endwhile; ?>
            <?php if (!$has_rows): ?>
            <tr><td colspan="7" style="text-align:center;padding:40px;color:var(--gray-400)">Акций пока нет</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

</body>
</html>
