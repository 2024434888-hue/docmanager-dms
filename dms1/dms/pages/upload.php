<?php
// pages/upload.php
$db = getDB();
$categories = $db->query("SELECT * FROM document_category ORDER BY name")->fetchAll();
?>

<style>
  @media (max-width: 600px) {
    .upload-card { padding: 16px !important; }
  }
</style>

<div class="topbar">
    <div>
        <div class="page-title">Upload Document</div>
        <div class="page-sub">Upload a new document</div>
    </div>
</div>

<?php if ($err = flash('error')): ?>
  <div class="alert alert-error"><?= e($err) ?></div>
<?php endif; ?>

<div class="card upload-card" style="padding:24px;">
    <form method="POST" enctype="multipart/form-data" action="<?= BASE_URL ?>/index.php?page=upload">
        <input type="hidden" name="action" value="upload_document">

        <div class="form-group">
            <label class="form-label">Document Title</label>
            <input type="text" name="title" class="form-control" placeholder="e.g. Q3 Financial Report" required>
        </div>

        <div class="form-group">
            <label class="form-label">Description</label>
            <textarea name="description" class="form-control" rows="4" placeholder="Optional description"></textarea>
        </div>

        <div class="form-group">
            <label class="form-label">Category</label>
            <select name="category_id" class="form-control" required>
                <option value="">Select Category</option>
                <?php foreach ($categories as $cat): ?>
                <option value="<?= $cat['id'] ?>"><?= e($cat['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label class="form-label">Choose File</label>
            <input type="file" name="document" class="form-control" required>
            <div style="font-size:12px;color:var(--text-muted);margin-top:6px;">
                Allowed: PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX, TXT, JPG, PNG
            </div>
        </div>

        <div class="form-group">
            <label class="form-label">File Password <span style="color:var(--text-dim);font-weight:400;">(Optional)</span></label>
            <input type="text" name="file_password" class="form-control"
                   placeholder="Leave blank for no password protection" autocomplete="off">
        </div>

        <button type="submit" class="btn btn-primary" style="width:auto;padding:10px 28px;">Upload Document</button>
    </form>
</div>