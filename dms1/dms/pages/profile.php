<?php

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $folder = 'uploads/profile/';
        if (!is_dir($folder)) mkdir($folder, 0777, true);
        $ext      = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        $filename = uniqid('profile_') . '.' . $ext;
        move_uploaded_file($_FILES['image']['tmp_name'], $folder . $filename);
        $image = $folder . $filename;
        $stmt = $db->prepare("UPDATE employee SET image = ? WHERE id = ?");
        $stmt->execute([$image, $authUser['employee_id']]);
        $_SESSION['user']['image'] = $image;
        flash('success', 'Profile picture updated!');
        redirect('profile');
    }
}
?>

<style>
  @media (max-width: 600px) {
    .profile-card { padding: 16px !important; }
  }
</style>

<div class="topbar">
    <div>
        <div class="page-title">Profile</div>
        <div class="page-sub">Manage your profile picture</div>
    </div>
</div>

<?php if ($ok = flash('success')): ?>
  <div class="alert alert-success"><?= e($ok) ?></div>
<?php endif; ?>

<div class="card profile-card" style="padding:24px;max-width:480px;">

    <?php if (!empty($authUser['image'])): ?>
    <div style="margin-bottom:20px;">
        <div style="font-size:13px;font-weight:500;color:var(--text-muted);margin-bottom:10px;text-transform:uppercase;letter-spacing:.3px;">Current Picture</div>
        <img src="<?= e($authUser['image']) ?>" alt="Profile"
             style="width:80px;height:80px;border-radius:50%;object-fit:cover;border:2px solid var(--border);">
    </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
        <div class="form-group">
            <label class="form-label">Upload New Picture</label>
            <input class="form-control" type="file" name="image" accept="image/*" required>
            <div style="font-size:12px;color:var(--text-muted);margin-top:6px;">JPG, PNG, or GIF. Recommended: square image.</div>
        </div>
        <button class="btn btn-primary" style="width:auto;padding:10px 24px;">Upload Picture</button>
    </form>
</div>