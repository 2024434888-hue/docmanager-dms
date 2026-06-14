<?php

require_once '../config.php';

$db = getDB();

$totalDocuments = $db->query("
    SELECT COUNT(*) FROM document
")->fetchColumn();

$totalUsers = $db->query("
    SELECT COUNT(*) FROM users
")->fetchColumn();

$totalCategories = $db->query("
    SELECT COUNT(*) FROM document_category
")->fetchColumn();

$totalActivities = $db->query("
    SELECT COUNT(*) FROM document_activity
")->fetchColumn();

$recentDocuments = $db->query("
    SELECT
        d.*,
        c.name AS category_name
    FROM document d
    LEFT JOIN document_category c
        ON c.id = d.category_id
    ORDER BY d.created_at DESC
    LIMIT 5
")->fetchAll();

?>

<div class="topbar">
    <div>
        <div class="page-title">Dashboard</div>
        <div class="page-sub">
            Document Management System Overview
        </div>
    </div>
</div>

<div class="stats-grid">

```
<div class="stat-card">
    <div class="stat-icon blue">📄</div>
    <div>
        <div class="stat-value">
            <?= $totalDocuments ?>
        </div>
        <div class="stat-label">
            Documents
        </div>
    </div>
</div>

<div class="stat-card">
    <div class="stat-icon green">👥</div>
    <div>
        <div class="stat-value">
            <?= $totalUsers ?>
        </div>
        <div class="stat-label">
            Users
        </div>
    </div>
</div>

<div class="stat-card">
    <div class="stat-icon amber">🏷️</div>
    <div>
        <div class="stat-value">
            <?= $totalCategories ?>
        </div>
        <div class="stat-label">
            Categories
        </div>
    </div>
</div>

<div class="stat-card">
    <div class="stat-icon purple">📋</div>
    <div>
        <div class="stat-value">
            <?= $totalActivities ?>
        </div>
        <div class="stat-label">
            Activities
        </div>
    </div>
</div>
```

</div>

<div class="card">

<div class="card-header">
    <div class="card-title">
        Recent Documents
    </div>
</div>

<table>

<thead>
<tr>
    <th>Title</th>
    <th>Category</th>
    <th>Status</th>
    <th>Date</th>
</tr>
</thead>

<tbody>

<?php foreach($recentDocuments as $doc): ?>

<tr>

```
<td>
    <?= e($doc['title']) ?>
</td>

<td>
    <?= e($doc['category_name'] ?? '-') ?>
</td>

<td>
    <?= e($doc['status']) ?>
</td>

<td>
    <?= date('d M Y', strtotime($doc['created_at'])) ?>
</td>
```

</tr>

<?php endforeach; ?>

</tbody>

</table>

</div>
