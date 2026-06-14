<?php
// pages/password-request.php
requireAdmin();
$db = getDB();

$requests = $db->query("
    SELECT pr.*,
           u.username, u.email,
           e.name AS emp_name, e.department
    FROM password_reset pr
    JOIN users u ON u.id = pr.user_id
    JOIN employee e ON e.id = u.employee_id
    ORDER BY pr.is_used ASC, pr.created_at DESC
")->fetchAll();

$pendingPw = array_filter($requests, fn($r) => !$r['is_used']);
?>

<style>
  .modal-overlay {
    display: none; position: fixed; inset: 0;
    background: rgba(0,0,0,.65); z-index: 200; place-items: center;
    padding: 16px;
  }
  .modal-overlay.open { display: grid; }
  .modal {
    background: var(--surface); border: 1px solid var(--border);
    border-radius: var(--radius-lg); padding: 28px 24px;
    width: 100%; max-width: 440px; box-shadow: var(--shadow);
    max-height: 90vh; overflow-y: auto;
  }
  .modal h3 { font-size: 17px; font-weight: 600; margin-bottom: 6px; }
  .modal .modal-meta {
    background: var(--surface2); border: 1px solid var(--border);
    border-radius: var(--radius); padding: 12px 16px;
    font-size: 13px; color: var(--text-muted);
    margin-bottom: 20px; line-height: 1.9;
  }
  .modal .modal-meta strong { color: var(--text); }
  .pw-reveal { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
  .pw-reveal input { flex: 1; min-width: 140px; }
  .badge-pending  { background: rgba(245,158,11,.15); color: #fcd34d; }
  .badge-resolved { background: rgba(34,197,94,.15);  color: #86efac; }
</style>

<!-- Reset Modal -->
<div class="modal-overlay" id="resetModal">
  <div class="modal">
    <h3>🔑 Reset Password</h3>
    <div class="modal-meta" id="modal-meta"></div>
    <form method="POST" action="<?= BASE_URL ?>/index.php?page=password-requests">
      <input type="hidden" name="action"   value="reset_request">
      <input type="hidden" name="reset_id" id="modal-reset-id">
      <div class="form-group">
        <label class="form-label">New password</label>
        <div class="pw-reveal">
          <input class="form-control" type="text" name="new_password"
                 id="modal-password" placeholder="Enter new password" required autocomplete="off">
          <button type="button" class="btn btn-ghost btn-sm"
                  onclick="genPassword()" title="Generate password">⟳ Generate</button>
        </div>
        <div style="font-size:12px;color:var(--text-muted);margin-top:5px;">
          Share this password with the user directly after saving.
        </div>
      </div>
      <div style="display:flex;gap:10px;margin-top:8px;flex-wrap:wrap;">
        <button type="submit" class="btn btn-primary" style="flex:1;min-width:120px;margin-top:0;">Save &amp; Notify</button>
        <button type="button" class="btn btn-ghost" onclick="closeModal()">Cancel</button>
      </div>
    </form>
  </div>
</div>

<!-- Page Header -->
<div class="topbar">
  <div>
    <div class="page-title">Password Requests</div>
    <div class="page-sub">
      <?= count($pendingPw) ?> pending request<?= count($pendingPw) !== 1 ? 's' : '' ?>
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
      <div class="empty-state-icon">🔑</div>
      <p>No password reset requests yet.</p>
    </div>
  </div>
<?php else: ?>
<div class="card">
  <div class="card-header">
    <div class="card-title">All Requests</div>
    <?php if (count($pendingPw) > 0): ?>
      <span class="badge badge-pending"><?= count($pendingPw) ?> pending</span>
    <?php endif; ?>
  </div>
  <div class="table-responsive">
  <table>
    <thead>
      <tr>
        <th>Employee</th>
        <th>Department</th>
        <th>Username</th>
        <th>Email</th>
        <th>Status</th>
        <th>Requested</th>
        <th>Action</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($requests as $req): ?>
      <tr>
        <td><?= e($req['emp_name']) ?></td>
        <td style="color:var(--text-muted);"><?= e($req['department']) ?></td>
        <td style="font-family:var(--mono);font-size:13px;"><?= e($req['username']) ?></td>
        <td style="color:var(--text-muted);font-size:13px;"><?= e($req['email']) ?></td>
        <td>
          <?php if (!$req['is_used']): ?>
            <span class="badge badge-pending">pending</span>
          <?php else: ?>
            <span class="badge badge-resolved">resolved</span>
          <?php endif; ?>
        </td>
        <td style="color:var(--text-dim);font-size:13px;white-space:nowrap;">
          <?= $req['created_at'] ? date('d M Y, g:ia', strtotime($req['created_at'])) : '—' ?>
        </td>
        <td>
          <?php if (!$req['is_used']): ?>
          <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <button class="btn btn-sm btn-primary" style="margin-top:0;"
              onclick="openReset(
                <?= (int)$req['id'] ?>,
                '<?= e(addslashes($req['emp_name'])) ?>',
                '<?= e(addslashes($req['username'])) ?>',
                '<?= e(addslashes($req['email'])) ?>'
              )">🔑 Reset</button>
            <form method="POST" action="<?= BASE_URL ?>/index.php?page=password-requests" style="margin:0;">
              <input type="hidden" name="action"   value="dismiss_reset">
              <input type="hidden" name="reset_id" value="<?= (int)$req['id'] ?>">
              <button type="submit" class="btn btn-sm btn-ghost"
                      onclick="return confirm('Dismiss this request?')">Dismiss</button>
            </form>
          </div>
          <?php else: ?>
            <span style="font-size:13px;color:var(--text-dim);">✅ Done</span>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>
<?php endif; ?>

<script>
function openReset(id, name, username, email) {
  document.getElementById('modal-reset-id').value = id;
  document.getElementById('modal-meta').innerHTML =
    `<strong>${name}</strong><br>Username: <strong>${username}</strong><br>Email: ${email}`;
  document.getElementById('modal-password').value = '';
  document.getElementById('resetModal').classList.add('open');
  setTimeout(() => document.getElementById('modal-password').focus(), 100);
}
function closeModal() {
  document.querySelectorAll('.modal-overlay').forEach(m => m.classList.remove('open'));
}
function genPassword() {
  const chars = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789!@#$';
  let pw = '';
  for (let i = 0; i < 10; i++) pw += chars[Math.floor(Math.random() * chars.length)];
  document.getElementById('modal-password').value = pw;
}
document.querySelectorAll('.modal-overlay').forEach(overlay => {
  overlay.addEventListener('click', e => { if (e.target === overlay) closeModal(); });
});
</script>