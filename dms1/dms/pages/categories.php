<?php
$db = getDB();

$categories = $db->query("
    SELECT * FROM document_category ORDER BY name ASC
")->fetchAll();
?>

<style>
  @media (max-width: 600px) {
    .category-form { padding: 16px !important; }
  }
</style>

<div class="topbar">
    <div>
        <div class="page-title">Categories</div>
        <div class="page-sub">Manage document categories</div>
    </div>
</div>

<?php if ($ok = flash('success')): ?>
  <div class="alert alert-success"><?= e($ok) ?></div>
<?php endif; ?>
<?php if ($err = flash('error')): ?>
  <div class="alert alert-error"><?= e($err) ?></div>
<?php endif; ?>

<div class="card category-form" style="padding:24px;margin-bottom:20px;">
    <div style="font-size:15px;font-weight:600;margin-bottom:18px;">Add Category</div>
    <form method="POST" action="<?= BASE_URL ?>/index.php?page=categories">
        <input type="hidden" name="action" value="add_category">
        <div class="form-group">
            <label class="form-label">Category Name</label>
            <input type="text" name="name" class="form-control" placeholder="e.g. Finance" required>
        </div>
        <div class="form-group">
            <label class="form-label">Description</label>
            <textarea name="description" class="form-control" rows="3" placeholder="Optional description"></textarea>
        </div>
        <button type="submit" class="btn btn-primary" style="width:auto;padding:10px 24px;">Add Category</button>
    </form>
</div>

<div class="card">
    <div class="card-header">
        <div class="card-title">Category List</div>
    </div>
    <div class="table-responsive">
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Category Name</th>
                <th>Description</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
        <?php if (!empty($categories)): ?>
            <?php foreach ($categories as $category): ?>
            <tr>
                <td><?= (int)$category['id'] ?></td>
                <td><?= e($category['name']) ?></td>
                <td style="color:var(--text-muted);"><?= e($category['description']) ?></td>
                <td>
                    <form method="POST" action="<?= BASE_URL ?>/index.php?page=categories" style="margin:0;">
                        <input type="hidden" name="action" value="delete_category">
                        <input type="hidden" name="id" value="<?= (int)$category['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-danger"
                                onclick="return confirm('Delete \'<?= e(addslashes($category['name'])) ?>\'? This cannot be undone.')">
                            🗑 Delete
                        </button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr>
                <td colspan="4" style="text-align:center;padding:40px;">No categories found.</td>
            </tr>
        <?php endif; ?>
        </tbody>
    </table>
    </div>
</div>