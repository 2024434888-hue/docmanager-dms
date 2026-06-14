<?php
requireAdmin();
$db = getDB();

$message = "";

/* CREATE USER */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name       = trim($_POST['name']);
    $address1   = trim($_POST['address1'] ?? '');
    $address2   = trim($_POST['address2'] ?? '');
    $postcode   = trim($_POST['postcode'] ?? '');
    $address    = implode(' | ', array_filter([$address1, $address2, $postcode]));
    $department = trim($_POST['department']);
    $username   = trim($_POST['username']);
    $email      = trim($_POST['email']);
    $password   = $_POST['password'];
    $role       = $_POST['role'];

    try {
        $db->beginTransaction();
        $stmt = $db->prepare("INSERT INTO employee (name, address, department, image) VALUES (?, ?, ?, ?)");
        $stmt->execute([$name, $address, $department, 'default.png']);
        $employee_id = $db->lastInsertId();
        $stmt = $db->prepare("INSERT INTO users (employee_id, username, email, password_hash, role) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$employee_id, $username, $email, password_hash($password, PASSWORD_DEFAULT), $role]);
        $db->commit();
        $message = "User created successfully.";
    } catch(Exception $e) {
        $db->rollBack();
        $message = "Error: " . $e->getMessage();
    }
}

/* GET USERS */
$users = $db->query("
    SELECT u.id, u.username, u.email, u.role, u.is_active, u.created_at,
           e.name, e.department, e.address
    FROM users u
    JOIN employee e ON u.employee_id = e.id
    ORDER BY e.name ASC
")->fetchAll();
?>

<style>
  /* Delete Modal */
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
    margin-bottom: 20px; line-height: 1.8;
  }
  .modal .modal-meta strong { color: var(--text); }
  .modal p { font-size: 14px; color: var(--text-muted); margin-bottom: 20px; }

  /* Create user form grid */
  .create-user-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 14px;
  }
  .create-user-grid .span-2 { grid-column: span 2; }

  /* Address sub-grid */
  .address-group {
    grid-column: span 2;
    display: grid;
    grid-template-columns: 1fr 1fr 140px;
    gap: 10px;
  }

  /* Address lines display */
  .addr-lines { font-size: 12px; line-height: 1.7; color: var(--text-muted); }
  .addr-lines span { display: block; }

  /* Responsive breakpoints */
  @media (max-width: 860px) {
    .address-group { grid-template-columns: 1fr 1fr; }
    .address-group .postcode { grid-column: span 2; }
  }
  @media (max-width: 640px) {
    .create-user-grid { grid-template-columns: 1fr; }
    .create-user-grid .span-2 { grid-column: span 1; }
    .address-group { grid-column: span 1; grid-template-columns: 1fr; }
    .address-group .postcode { grid-column: span 1; }
  }
</style>

<!-- Delete User Modal -->
<div class="modal-overlay" id="deleteUserModal">
  <div class="modal">
    <h3>🗑️ Delete User</h3>
    <div class="modal-meta" id="delete-user-meta"></div>
    <p>This will permanently remove the user account and employee record. This action cannot be undone.</p>
    <form method="POST" action="<?= BASE_URL ?>/index.php?page=users">
      <input type="hidden" name="action"  value="delete_user">
      <input type="hidden" name="user_id" id="delete-user-id">
      <div style="display:flex;gap:10px;flex-wrap:wrap;">
        <button type="submit" class="btn btn-danger" style="flex:1;min-width:120px;">Delete User</button>
        <button type="button" class="btn btn-ghost" onclick="closeDeleteModal()">Cancel</button>
      </div>
    </form>
  </div>
</div>

<!-- Page Header -->
<div class="topbar">
    <div>
        <div class="page-title">Users</div>
        <div class="page-sub">Manage system users</div>
    </div>
</div>

<?php if ($ok = flash('success')): ?>
  <div class="alert alert-success"><?= e($ok) ?></div>
<?php endif; ?>
<?php if ($err = flash('error')): ?>
  <div class="alert alert-error"><?= e($err) ?></div>
<?php endif; ?>

<?php if($message): ?>
<div class="alert <?= str_starts_with($message, 'Error') ? 'alert-error' : 'alert-success' ?>" style="margin-bottom:20px;">
    <?= e($message) ?>
</div>
<?php endif; ?>

<!-- Create User Card -->
<div class="card" style="margin-bottom:20px;">
    <div class="card-header">
        <div class="card-title">Create User</div>
    </div>
    <div style="padding:20px 24px;">
    <form method="POST">
      <div class="create-user-grid">
        <input class="form-control" type="text" name="name" placeholder="Employee Name" required>
        <input class="form-control" type="text" name="department" placeholder="Department" required>

        <div class="address-group">
          <div>
            <label class="form-label" style="font-size:12px;margin-bottom:4px;display:block;">Address Line 1</label>
            <input class="form-control" type="text" name="address1" placeholder="e.g. No. 12, Jalan Merdeka" required>
          </div>
          <div>
            <label class="form-label" style="font-size:12px;margin-bottom:4px;display:block;">Address Line 2 <span style="color:var(--text-dim);font-weight:400;">(optional)</span></label>
            <input class="form-control" type="text" name="address2" placeholder="e.g. Taman Damai">
          </div>
          <div class="postcode">
            <label class="form-label" style="font-size:12px;margin-bottom:4px;display:block;">Postal Code</label>
            <input class="form-control" type="text" name="postcode" placeholder="e.g. 50480" required maxlength="10">
          </div>
        </div>

        <select class="form-control" name="role">
          <option value="user">User</option>
          <option value="admin">Admin</option>
        </select>

        <input class="form-control" type="text"     name="username" placeholder="Username" required>
        <input class="form-control" type="email"    name="email"    placeholder="Email"    required>
        <input class="form-control span-2" type="password" name="password" placeholder="Password" required>
      </div>

      <div style="margin-top:18px;">
        <button type="submit" class="btn btn-primary" style="width:auto;padding:10px 28px;">Create User</button>
      </div>
    </form>
    </div>
</div>

<!-- User List Card -->
<div class="card">
  <div class="card-header">
    <div class="card-title">User List</div>
    <span style="font-size:13px;color:var(--text-muted);"><?= count($users) ?> total</span>
  </div>
  <div class="table-responsive">
  <table>
    <thead>
      <tr>
        <th>ID</th>
        <th>Name</th>
        <th>Username</th>
        <th>Email</th>
        <th>Department</th>
        <th>Address</th>
        <th>Role</th>
        <th>Status</th>
        <th>Created</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach($users as $user):
        $addrParts = array_filter(array_map('trim', explode('|', $user['address'] ?? '')));
    ?>
    <tr>
      <td><?= $user['id'] ?></td>
      <td><?= e($user['name']) ?></td>
      <td style="font-family:var(--mono);font-size:13px;"><?= e($user['username']) ?></td>
      <td style="font-size:13px;color:var(--text-muted);"><?= e($user['email']) ?></td>
      <td><?= e($user['department']) ?></td>
      <td>
        <?php if (!empty($addrParts)): ?>
          <div class="addr-lines">
            <?php foreach($addrParts as $line): ?>
              <span><?= e($line) ?></span>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <span style="color:var(--text-dim);">—</span>
        <?php endif; ?>
      </td>
      <td>
        <?php if($user['role'] === 'admin'): ?>
          <span class="role-badge role-admin">Admin</span>
        <?php else: ?>
          <span class="role-badge role-user">User</span>
        <?php endif; ?>
      </td>
      <td>
        <?php if($user['is_active']): ?>
          <span class="badge badge-active">Active</span>
        <?php else: ?>
          <span class="badge badge-archived">Inactive</span>
        <?php endif; ?>
      </td>
      <td style="font-size:13px;white-space:nowrap;color:var(--text-dim);">
        <?= date('d M Y', strtotime($user['created_at'])) ?>
      </td>
      <td>
        <?php
        $authUser = getAuthUser();
        if ((int)$user['id'] !== (int)($authUser['id'] ?? 0)):
        ?>
          <button class="btn btn-sm btn-danger" style="margin-top:0;"
            onclick="openDeleteUser(
              <?= (int)$user['id'] ?>,
              '<?= e(addslashes($user['name'])) ?>',
              '<?= e(addslashes($user['username'])) ?>',
              '<?= e($user['role']) ?>'
            )">🗑️ Delete</button>
        <?php else: ?>
          <span style="font-size:12px;color:var(--text-dim);">—</span>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>

<script>
function openDeleteUser(id, name, username, role) {
  document.getElementById('delete-user-id').value = id;
  document.getElementById('delete-user-meta').innerHTML =
    `<strong>${name}</strong><br>Username: <strong>${username}</strong><br>Role: ${role}`;
  document.getElementById('deleteUserModal').classList.add('open');
}
function closeDeleteModal() {
  document.getElementById('deleteUserModal').classList.remove('open');
}
document.getElementById('deleteUserModal').addEventListener('click', function(e) {
  if (e.target === this) closeDeleteModal();
});
</script>