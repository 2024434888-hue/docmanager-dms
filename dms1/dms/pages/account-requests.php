<?php
// pages/account-requests.php
requireAdmin();
$db = getDB();

$requests = $db->query("
    SELECT ur.*,
           e.name       AS emp_name,
           e.department AS emp_department,
           e.address    AS emp_address,
           approver.username AS approver_name
    FROM user_request ur
    JOIN employee e ON e.id = ur.employee_id
    LEFT JOIN users approver ON approver.id = ur.approved_by
    ORDER BY
        CASE ur.status WHEN 'pending' THEN 0 WHEN 'approved' THEN 1 ELSE 2 END,
        ur.requested_at DESC
")->fetchAll();

function renderAddress(string $raw): string {
    $parts = array_filter(array_map('trim', explode('|', $raw)));
    if (empty($parts)) return '<span style="color:var(--text-dim)">—</span>';
    $lines = array_map(fn($p) => htmlspecialchars($p, ENT_QUOTES|ENT_HTML5, 'UTF-8'), $parts);
    return '<span style="display:block;font-size:12px;line-height:1.8;color:var(--text-muted);">'
         . implode('<br>', $lines) . '</span>';
}

$pendingCount = count(array_filter($requests, fn($r) => $r['status'] === 'pending'));
?>

<style>
  /* Modals */
  .modal-overlay {
    display: none; position: fixed; inset: 0;
    background: rgba(0,0,0,.65); z-index: 200; place-items: center;
    padding: 16px;
  }
  .modal-overlay.open { display: grid; }
  .modal {
    background: var(--surface); border: 1px solid var(--border);
    border-radius: var(--radius-lg); padding: 28px 24px;
    width: 100%; max-width: 460px; box-shadow: var(--shadow);
    max-height: 90vh; overflow-y: auto;
  }
  .modal h3  { font-size: 17px; font-weight: 600; margin-bottom: 6px; }
  .modal .modal-meta {
    background: var(--surface2); border: 1px solid var(--border);
    border-radius: var(--radius); padding: 12px 16px;
    font-size: 13px; color: var(--text-muted);
    margin-bottom: 20px; line-height: 1.8;
  }
  .modal .modal-meta strong { color: var(--text); }
  .badge-pending  { background: rgba(245,158,11,.15); color: #fcd34d; }
  .badge-approved { background: rgba(34,197,94,.15);  color: #86efac; }
  .badge-rejected { background: rgba(244,63,94,.12);  color: #fca5a5; }

  /* Action buttons stack on very small screens */
  .action-btns { display: flex; gap: 8px; flex-wrap: wrap; }
  @media (max-width: 480px) {
    .action-btns { flex-direction: column; }
    .action-btns .btn { width: 100%; text-align: center; }
  }
</style>

<!-- Approve Modal -->
<div class="modal-overlay" id="approveModal">
  <div class="modal">
    <h3>✅ Approve Request</h3>
    <div class="modal-meta" id="modal-meta"></div>
    <form method="POST" action="<?= BASE_URL ?>/index.php?page=account-requests">
      <input type="hidden" name="action"  value="approve_request">
      <input type="hidden" name="req_id"  id="modal-req-id">
      <div class="form-group">
        <label class="form-label">Email Address</label>
        <input class="form-control" type="email" name="email" id="modal-email"
               placeholder="employee@company.com" required autocomplete="off">
        <div style="font-size:12px;color:var(--text-muted);margin-top:4px;">This becomes the user's login email.</div>
      </div>
      <div class="form-group">
        <label class="form-label">Username</label>
        <input class="form-control" type="text" name="username" id="modal-username"
               placeholder="e.g. ahmad.ismail" required autocomplete="off">
      </div>
      <div class="form-group">
        <label class="form-label">Password</label>
        <input class="form-control" type="text" name="password" id="modal-password"
               placeholder="Set a strong temporary password" required autocomplete="off">
        <div style="font-size:12px;color:var(--text-muted);margin-top:4px;">Share this with the user securely after creation.</div>
      </div>
      <div class="form-group">
        <label class="form-label">Role</label>
        <select class="form-control" name="role" id="modal-role">
          <option value="user">User</option>
          <option value="admin">Admin</option>
        </select>
      </div>
      <div style="display:flex;gap:10px;margin-top:8px;flex-wrap:wrap;">
        <button type="submit" class="btn btn-primary" style="flex:1;min-width:120px;margin-top:0;">Create Account</button>
        <button type="button" class="btn btn-ghost" onclick="closeModal()">Cancel</button>
      </div>
    </form>
  </div>
</div>

<!-- Reject Modal -->
<div class="modal-overlay" id="rejectModal">
  <div class="modal">
    <h3>❌ Reject Request</h3>
    <div class="modal-meta" id="reject-meta"></div>
    <p style="font-size:14px;color:var(--text-muted);margin-bottom:20px;">
      This cannot be undone. The employee record will remain but the request will be marked rejected.
    </p>
    <form method="POST" action="<?= BASE_URL ?>/index.php?page=account-requests">
      <input type="hidden" name="action"  value="reject_request">
      <input type="hidden" name="req_id"  id="reject-req-id">
      <div style="display:flex;gap:10px;flex-wrap:wrap;">
        <button type="submit" class="btn btn-danger" style="flex:1;min-width:120px;">Confirm Reject</button>
        <button type="button" class="btn btn-ghost" onclick="closeModal()">Cancel</button>
      </div>
    </form>
  </div>
</div>

<!-- Page Header -->
<div class="topbar">
  <div>
    <div class="page-title">Account Requests</div>
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
      <div class="empty-state-icon">📥</div>
      <p>No account requests yet. New requests will appear here when employees submit the form.</p>
    </div>
  </div>
<?php else: ?>
<div class="card">
  <div class="card-header">
    <div class="card-title">All Requests</div>
    <?php if ($pendingCount > 0): ?>
      <span class="badge badge-pending"><?= $pendingCount ?> pending</span>
    <?php endif; ?>
  </div>
  <div class="table-responsive">
  <table>
    <thead>
      <tr>
        <th>Employee Name</th>
        <th>Department</th>
        <th>Address</th>
        <th>Requested Role</th>
        <th>Status</th>
        <th>Submitted</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($requests as $req): ?>
      <tr>
        <td><?= e($req['emp_name']) ?></td>
        <td style="color:var(--text-muted);"><?= e($req['emp_department']) ?></td>
        <td style="min-width:140px;"><?= renderAddress($req['emp_address']) ?></td>
        <td>
          <span class="badge <?= $req['requested_role'] === 'admin' ? 'role-admin' : 'role-user' ?>">
            <?= e($req['requested_role']) ?>
          </span>
        </td>
        <td>
          <span class="badge badge-<?= e($req['status']) ?>">
            <?= e($req['status']) ?>
          </span>
        </td>
        <td style="color:var(--text-dim);font-size:13px;white-space:nowrap;">
          <?= $req['requested_at'] ? date('d M Y, g:ia', strtotime($req['requested_at'])) : '—' ?>
        </td>
        <td style="min-width:180px;">
          <?php if ($req['status'] === 'pending'): ?>
          <div class="action-btns">
            <button class="btn btn-sm btn-primary" style="margin-top:0;"
              onclick="openApprove(
                <?= (int)$req['id'] ?>,
                '<?= e(addslashes($req['emp_name'])) ?>',
                '<?= e(addslashes($req['emp_department'])) ?>',
                '<?= e(addslashes($req['emp_address'])) ?>',
                '<?= e($req['requested_role']) ?>',
                '<?= e(strtolower(strtok($req['emp_name'], ' '))) ?>'
              )">✅ Approve</button>
            <button class="btn btn-sm btn-danger"
              onclick="openReject(<?= (int)$req['id'] ?>, '<?= e(addslashes($req['emp_name'])) ?>')">
              ✕ Reject
            </button>
          </div>
          <?php else: ?>
            <span style="font-size:13px;color:var(--text-dim);">
              <?= $req['status'] === 'approved' ? '✅ Account created' : '❌ Rejected' ?>
              <?php if ($req['approver_name']): ?>
                <br>by <?= e($req['approver_name']) ?>
              <?php endif; ?>
            </span>
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
function formatAddress(raw) {
  return raw.split('|').map(s => s.trim()).filter(Boolean).join('<br>') || raw;
}
function openApprove(id, name, dept, address, requestedRole, suggestedUsername) {
  document.getElementById('modal-req-id').value   = id;
  document.getElementById('modal-username').value = suggestedUsername;
  document.getElementById('modal-password').value = '';
  document.getElementById('modal-email').value    = '';
  document.getElementById('modal-role').value     = requestedRole;
  document.getElementById('modal-meta').innerHTML =
    `<strong>${name}</strong><br>Department: <strong>${dept}</strong><br>Address: ${formatAddress(address)}`;
  document.getElementById('approveModal').classList.add('open');
  setTimeout(() => document.getElementById('modal-email').focus(), 100);
}
function openReject(id, name) {
  document.getElementById('reject-req-id').value = id;
  document.getElementById('reject-meta').innerHTML = `<strong>${name}</strong>`;
  document.getElementById('rejectModal').classList.add('open');
}
function closeModal() {
  document.querySelectorAll('.modal-overlay').forEach(m => m.classList.remove('open'));
}
document.querySelectorAll('.modal-overlay').forEach(overlay => {
  overlay.addEventListener('click', e => { if (e.target === overlay) closeModal(); });
});
</script>