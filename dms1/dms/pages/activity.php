<?php

$db = getDB();

$activities = $db->query("
    SELECT
        da.id,
        da.action,
        da.ip_address,
        da.acted_at,
        d.title AS document_title,
        e.name AS employee_name
    FROM document_activity da
    LEFT JOIN document d ON da.document_id = d.id
    LEFT JOIN users u ON da.user_id = u.id
    LEFT JOIN employee e ON u.employee_id = e.id
    ORDER BY da.acted_at DESC
")->fetchAll();

?>

<div class="topbar">
    <div>
        <div class="page-title">Activity Log</div>
        <div class="page-sub">Document activity history</div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <div class="card-title">Recent Activities</div>
    </div>
    <div class="table-responsive">
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Document</th>
                <th>User</th>
                <th>Action</th>
                <th>IP Address</th>
                <th>Date &amp; Time</th>
            </tr>
        </thead>
        <tbody>
        <?php if (!empty($activities)): ?>
            <?php foreach ($activities as $activity): ?>
            <tr>
                <td><?= (int)$activity['id'] ?></td>
                <td><?= e($activity['document_title'] ?? '—') ?></td>
                <td><?= e($activity['employee_name'] ?? '—') ?></td>
                <td><?= e(ucfirst(str_replace('_', ' ', $activity['action']))) ?></td>
                <td style="font-family:var(--mono);font-size:13px;"><?= e($activity['ip_address'] ?? '—') ?></td>
                <td style="white-space:nowrap;color:var(--text-dim);font-size:13px;">
                    <?= date('d M Y H:i', strtotime($activity['acted_at'])) ?>
                </td>
            </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr>
                <td colspan="6" style="text-align:center;padding:40px;">No activity records found.</td>
            </tr>
        <?php endif; ?>
        </tbody>
    </table>
    </div>
</div>