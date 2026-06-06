<?php
require_once '../config.php';
if (!isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], ['admin','employee'])) {
    header("Location: ../login-form.php"); exit;
}

$employees = $conn->query("SELECT id, first_name, last_name, schedule FROM users WHERE role='employee' ORDER BY id ASC");
$current = 'schedule.php';
$name = $_SESSION['user_name'] ?? '';
$emps = [];
while ($row = $employees->fetch_assoc()) $emps[] = $row;
?>
<?php include 'header.php'; ?>

<div class="main-content">
    <div class="table-card">
        <div class="table-card-header">
            <div>
                <div class="table-card-title">Расписание сотрудников</div>
                <div class="table-card-desc">График работы всего персонала</div>
            </div>
        </div>

        <div style="max-height:calc(100vh - 220px);overflow-y:auto">
            <table class="data-table">
                <thead style="position:sticky;top:0;z-index:1;background:#fff">
                    <tr>
                        <th style="width:60px">#</th>
                        <th>Сотрудник</th>
                        <th>График работы</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($emps)): ?>
                <tr>
                    <td colspan="3" style="text-align:center;padding:40px;color:#9ca3af">
                        Сотрудники не найдены
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($emps as $i => $row): ?>
                <tr>
                    <td style="color:#9ca3af;font-size:12px"><?= $i + 1 ?></td>
                    <td>
                        <strong><?= htmlspecialchars($row['first_name'].' '.$row['last_name']) ?></strong>
                        <?php if ((int)$row['id'] === (int)$_SESSION['user_id']): ?>
                        <span style="margin-left:8px;font-size:11px;background:#eff6ff;color:#2563eb;
                              padding:2px 8px;border-radius:20px;font-weight:500">Вы</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($row['schedule']): ?>
                        <span style="display:inline-flex;align-items:center;gap:6px">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none"
                                 viewBox="0 0 24 24" stroke-width="2" stroke="#6b7280">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                      d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5"/>
                            </svg>
                            <?= htmlspecialchars($row['schedule']) ?>
                        </span>
                        <?php else: ?>
                        <span style="color:#9ca3af;font-style:italic;font-size:13px">Не задан</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

</body>
</html>
