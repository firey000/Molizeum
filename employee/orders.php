<?php
require_once '../config.php';
if (!isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], ['admin','employee'])) {
    header("Location: ../login-form.php"); exit;
}

// Finish booking
if (isset($_GET['finish']) && is_numeric($_GET['finish'])) {
    $id = (int)$_GET['finish'];
    $b  = $conn->query("SELECT equipment_id FROM bookings WHERE id=$id")->fetch_assoc();
    if ($b) {
        $conn->query("UPDATE bookings SET status='finished' WHERE id=$id");
        $conn->query("UPDATE equipment SET status='free' WHERE id=".$b['equipment_id']);
    }
    header("Location: orders.php"); exit;
}

$bookings = $conn->query("
    SELECT b.*, 
           CONCAT(u.first_name,' ',u.last_name) AS client_name,
           CONCAT('ПК #', e.number) AS pc_label,
           s.name AS service_name
    FROM bookings b
    LEFT JOIN users u ON b.client_id = u.id
    LEFT JOIN equipment e ON b.equipment_id = e.id
    LEFT JOIN services s ON b.service_id = s.id
    ORDER BY b.status ASC, b.start_time DESC
    LIMIT 50
");

$current = 'orders.php';
$name = $_SESSION['user_name'] ?? '';
?>
<?php include 'header.php'; ?>

<div class="main-content">
    <div class="table-card">
        <div class="table-card-header">
            <div>
                <div class="table-card-title">Мониторинг заказов</div>
                <div class="table-card-desc">Текущие и завершённые сессии</div>
            </div>
        </div>

        <div style="max-height:calc(100vh - 220px);overflow-y:auto">
            <table class="data-table">
                <thead style="position:sticky;top:0;z-index:1;background:#fff">
                    <tr><th>Клиент</th><th>Компьютер</th><th>Тариф</th><th>Время начала</th><th>Длительность</th><th>Сумма</th><th>Статус</th><th>Действия</th></tr>
                </thead>
                <tbody>
                <?php while ($row = $bookings->fetch_assoc()):
                    $sc = $row['status']==='active' ? 'badge-green' : ($row['status']==='finished' ? 'badge-gray' : 'badge-red');
                    $st = $row['status']==='active' ? 'Активен' : ($row['status']==='finished' ? 'Завершён' : 'Отменён');
                ?>
                <tr>
                    <td><strong><?= htmlspecialchars($row['client_name']) ?></strong></td>
                    <td><?= htmlspecialchars($row['pc_label']) ?></td>
                    <td><?= htmlspecialchars($row['service_name']) ?></td>
                    <td><?= date('d.m H:i', strtotime($row['start_time'])) ?></td>
                    <td><?= $row['duration_hours'] ?> ч</td>
                    <td><?= $row['total_amount'] ? number_format($row['total_amount'],0,'.',' ').' ₽' : '—' ?></td>
                    <td><span class="badge <?= $sc ?>"><?= $st ?></span></td>
                    <td>
                        <?php if ($row['status'] === 'active'): ?>
                        <a href="?finish=<?= $row['id'] ?>" class="action-btn btn-complete"
                           onclick="return confirm('Завершить сессию?')">Завершить</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

</body>
</html>
