<?php
// pages/request-account.php
?>
<style>
  .addr-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
  }
  .addr-row .postcode-col { grid-column: span 2; }
  @media (max-width: 480px) {
    .addr-row { grid-template-columns: 1fr; }
    .addr-row .postcode-col { grid-column: span 1; }
  }
</style>

<div class="login-wrap">
  <div class="login-card" style="max-width:480px;">

    <div class="login-logo">
      <div style="display:flex;align-items:center;gap:10px;">
        <div style="width:38px;height:38px;background:var(--accent);border-radius:9px;display:grid;place-items:center;font-size:18px;">📁</div>
        <div style="font-size:20px;font-weight:600;">Doc<span style="color:var(--accent);">Manager</span></div>
      </div>
    </div>

    <h1 class="login-heading">Request an Account</h1>
    <p class="login-sub">Fill in your details below. An admin will review and create your login credentials.</p>

    <?php if ($err = flash('error')): ?>
      <div class="alert alert-error"><?= e($err) ?></div>
    <?php endif; ?>

    <?php if ($ok = flash('success')): ?>
      <div class="alert alert-success"><?= e($ok) ?></div>
    <?php else: ?>

    <form method="POST" action="<?= BASE_URL ?>/index.php">
      <input type="hidden" name="action" value="request_account">
      <input type="hidden" name="requested_role" value="user">

      <div class="form-group">
        <label class="form-label">Full Name</label>
        <input class="form-control" type="text" name="name"
               placeholder="e.g. Ahmad bin Ismail"
               value="<?= e($_POST['name'] ?? '') ?>" required>
      </div>

      <div class="form-group">
        <label class="form-label">Department</label>
        <input class="form-control" type="text" name="department"
               placeholder="e.g. Human Resources"
               value="<?= e($_POST['department'] ?? '') ?>" required>
      </div>

      <div class="form-group">
        <label class="form-label">Address</label>
        <div class="addr-row">
          <div>
            <input class="form-control" type="text" name="address1"
                   placeholder="Address Line 1"
                   value="<?= e($_POST['address1'] ?? '') ?>" required>
          </div>
          <div>
            <input class="form-control" type="text" name="address2"
                   placeholder="Address Line 2 (optional)"
                   value="<?= e($_POST['address2'] ?? '') ?>">
          </div>
          <div class="postcode-col">
            <input class="form-control" type="text" name="postcode"
                   placeholder="Postal Code"
                   value="<?= e($_POST['postcode'] ?? '') ?>"
                   required maxlength="10">
          </div>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Email Address</label>
        <input class="form-control" type="email" name="email"
               placeholder="e.g. ahmad@company.com"
               value="<?= e($_POST['email'] ?? '') ?>" required>
      </div>

      <button type="submit" class="btn btn-primary">Submit Request</button>
    </form>

    <?php endif; ?>

    <div class="login-footer" style="margin-top:20px;">
      <a href="<?= BASE_URL ?>/index.php?page=login">← Back to Sign In</a>
    </div>

  </div>
</div>