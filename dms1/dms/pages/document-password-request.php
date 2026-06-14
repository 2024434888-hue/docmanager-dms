<?php
requireAdmin();
$db = getDB();

$requests = $db->query("
    SELECT r.*, d.title, u.username
    FROM document_password_request r
    JOIN document d ON d.id = r.document_id
    JOIN users u    ON u.id = r.user_id
    ORDER BY
        CASE r.status WHEN 'pending' THEN 0 ELSE 1 END,
        r.requested_at DESC
")->fetchAll();

$pendingCount = count(array_filter($requests, fn($r) => $r['status'] === 'pending'));
?>

<div class="topbar">
  <div>
    <div class="page-title">File Password Requests</div>
    <div class="page-sub">
      <?= $pendingCount ?> pending request<?= $pendingCount !== 1 ? 's' : '' ?>
    </div>
  </div>
</div>

<?php if ($ok = flash('success')): ?>
  <div class="alert alert-success"><?= e($ok) ?></div>
<?php endif; ?>
<?php if ($err = flash('error')): ?>
  <div class="alert alert-error"><?= e($err) ?></div>
<?php endif; ?>

<?php if (empty($requests)): ?>
  <div class="card">
    <div class="empty-state">
      <div class="empty-state-icon">🔐</div>
      <p>No file password requests yet.</p>
    </div>
  </div>
<?php else: ?>
<div class="card">
  <div class="card-header">
    <div class="card-title">All Requests</div>
    <?php if ($pendingCount > 0): ?>
      <span class="badge" style="background:rgba(245,158,11,.15);color:#fcd34d;">
        <?= $pendingCount ?> pending
      </span>
    <?php endif; ?>
  </div>
  <div class="table-responsive">
  <table>
    <thead>
      <tr>
        <th>Document</th>
        <th>Requested By</th>
        <th>Status</th>
        <th>Requested At</th>
        <th>Action</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($requests as $r): ?>
      <tr>
        <td><?= e($r['title']) ?></td>
        <td><?= e($r['username']) ?></td>
        <td>
          <?php if ($r['status'] === 'pending'): ?>
            <span class="badge" style="background:rgba(245,158,11,.15);color:#fcd34d;">pending</span>
          <?php else: ?>
            <span class="badge" style="background:rgba(34,197,94,.15);color:#86efac;">approved</span>
          <?php endif; ?>
        </td>
        <td style="font-size:13px;color:var(--text-dim);white-space:nowrap;">
          <?= $r['requested_at'] ? date('d M Y, g:ia', strtotime($r['requested_at'])) : '—' ?>
        </td>
        <td>
          <?php if ($r['status'] === 'pending'): ?>
            <form method="POST" action="<?= BASE_URL ?>/index.php?page=document-password-request" style="margin:0;">
              <input type="hidden" name="action"     value="approve_document_password">
              <input type="hidden" name="request_id" value="<?= (int)$r['id'] ?>">
              <button type="submit" class="btn btn-sm btn-primary" style="margin-top:0;">✅ Approve</button>
            </form>
          <?php else: ?>
            <span style="font-size:13px;color:var(--text-dim);">✅ Approved</span>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>
<?php endif; ?>