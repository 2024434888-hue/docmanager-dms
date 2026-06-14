<?php

requireLogin();

$authUser = getAuthUser();
$isAdmin  = ($authUser['role'] ?? '') === 'admin';
$db = getDB();

// IDs of all password-protected documents
$passwordDocs = [];
$stmt = $db->query("SELECT document_id FROM document_password");
while ($row = $stmt->fetch()) {
    $passwordDocs[] = (int)$row['document_id'];
}

// IDs of docs this user already has an approved password request for
$approvedDocs = [];
if (!$isAdmin) {
    $aStmt = $db->prepare("
        SELECT document_id FROM document_password_request
        WHERE user_id = ? AND status = 'approved'
    ");
    $aStmt->execute([$authUser['id']]);
    while ($row = $aStmt->fetch()) {
        $approvedDocs[] = (int)$row['document_id'];
    }
}

// IDs of docs this user has a PENDING request for (already requested, not yet approved)
$pendingDocs = [];
if (!$isAdmin) {
    $pStmt = $db->prepare("
        SELECT document_id FROM document_password_request
        WHERE user_id = ? AND status = 'pending'
    ");
    $pStmt->execute([$authUser['id']]);
    while ($row = $pStmt->fetch()) {
        $pendingDocs[] = (int)$row['document_id'];
    }
}

$documents = $db->query("
    SELECT d.*, c.name AS category_name, e.name AS uploader_name,
           u2.role AS uploader_role
    FROM document d
    LEFT JOIN document_category c ON d.category_id = c.id
    JOIN users u ON d.uploaded_by = u.id
    JOIN employee e ON u.employee_id = e.id
    LEFT JOIN users u2 ON u2.id = d.uploaded_by
    ORDER BY d.created_at DESC
")->fetchAll();

// All categories (id + name) for edit modal dropdown
$allCategories = $db->query("SELECT id, name FROM document_category ORDER BY name ASC")->fetchAll();

// Collect unique category names for filter bar
$categories = [];
foreach ($documents as $doc) {
    $cat = $doc['category_name'] ?? null;
    if ($cat && !in_array($cat, $categories)) {
        $categories[] = $cat;
    }
}
sort($categories);
?>

<style>
.search-filter-bar {
    display: flex;
    gap: 10px;
    align-items: center;
    flex-wrap: wrap;
    padding: 16px 20px;
    border-bottom: 1px solid var(--border);
}
.search-wrapper {
    position: relative;
    flex: 1;
    min-width: 180px;
}
.search-wrapper .search-icon {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-dim);
    pointer-events: none;
    font-size: 14px;
}
.search-wrapper input {
    padding-left: 36px;
    width: 100%;
}
.filter-select { min-width: 150px; }
.no-results { text-align: center; padding: 48px 20px; color: var(--text-muted); }
.no-results-icon { font-size: 36px; margin-bottom: 10px; }
.result-count { font-size: 12px; color: var(--text-dim); white-space: nowrap; padding: 6px 0; }
tr.hidden-row { display: none; }

/* Two-step password modal */
#pwModal {
    display: none;
    position: fixed; inset: 0;
    background: rgba(0,0,0,.65);
    z-index: 200; place-items: center;
    padding: 16px;
}
#pwModal.open { display: grid; }
#pwModal .modal-box {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    padding: 28px 24px;
    width: 100%; max-width: 420px;
    box-shadow: var(--shadow);
    max-height: 90vh; overflow-y: auto;
}
#pwModal h3 { font-size: 17px; font-weight: 600; margin-bottom: 8px; }
#pwModal .doc-meta {
    background: var(--surface2); border: 1px solid var(--border);
    border-radius: var(--radius); padding: 10px 14px;
    font-size: 13px; color: var(--text-muted);
    margin-bottom: 20px; font-weight: 500;
}
.modal-step { display: none; }
.modal-step.active { display: block; }
.step-note { font-size: 13px; color: var(--text-muted); margin-bottom: 18px; line-height: 1.6; }

@media (max-width: 600px) {
    .filter-select { min-width: 120px; flex: 1; }
    .search-filter-bar { padding: 12px 14px; }
}
</style>

<div class="topbar">
    <div>
        <div class="page-title">Documents</div>
        <div class="page-sub">Manage all documents</div>
    </div>
    <a href="?page=upload" class="btn btn-primary btn-sm">⬆ Upload Document</a>
</div>

<?php if ($ok = flash('success')): ?>
  <div class="alert alert-success" style="margin-bottom:20px;"><?= e($ok) ?></div>
<?php endif; ?>
<?php if ($err = flash('error')): ?>
  <div class="alert alert-error" style="margin-bottom:20px;"><?= e($err) ?></div>
<?php endif; ?>

<!-- ══════════════════════════════════════════
     Edit Document Modal
══════════════════════════════════════════ -->
<div id="editModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.65);z-index:200;place-items:center;padding:16px;">
  <div style="background:var(--surface);border:1px solid var(--border);border-radius:var(--radius-lg);padding:28px 24px;width:100%;max-width:500px;box-shadow:var(--shadow);max-height:90vh;overflow-y:auto;">
    <h3 style="font-size:17px;font-weight:600;margin-bottom:6px;">✏️ Edit Document</h3>
    <div id="edit-doc-meta" style="background:var(--surface2);border:1px solid var(--border);border-radius:var(--radius);padding:10px 14px;font-size:13px;color:var(--text-muted);margin-bottom:20px;font-weight:500;"></div>

    <form method="POST" action="<?= BASE_URL ?>/index.php?page=documents">
      <input type="hidden" name="action" value="edit_document">
      <input type="hidden" name="doc_id" id="edit-doc-id">

      <div class="form-group">
        <label class="form-label">Title</label>
        <input class="form-control" type="text" name="title" id="edit-title" required>
      </div>

      <div class="form-group">
        <label class="form-label">Description</label>
        <textarea class="form-control" name="description" id="edit-description" rows="3"></textarea>
      </div>

      <div class="form-group">
        <label class="form-label">Category</label>
        <select class="form-control" name="category_id" id="edit-category">
          <option value="">— No category —</option>
          <?php foreach ($allCategories as $cat): ?>
            <option value="<?= (int)$cat['id'] ?>"><?= e($cat['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group">
        <label class="form-label">Status</label>
        <select class="form-control" name="status" id="edit-status">
          <option value="active">Active</option>
          <option value="archived">Archived</option>
        </select>
      </div>

      <div class="form-group">
        <label class="form-label">File Password <span style="color:var(--text-dim);font-weight:400;">(leave blank to keep existing)</span></label>
        <input class="form-control" type="text" name="file_password" id="edit-password"
               placeholder="Enter new password or leave blank" autocomplete="off">
        <div style="font-size:12px;color:var(--text-muted);margin-top:4px;" id="edit-pw-note"></div>
      </div>

      <div style="display:flex;gap:10px;margin-top:8px;flex-wrap:wrap;">
        <button type="submit" class="btn btn-primary" style="flex:1;min-width:120px;margin-top:0;">Save Changes</button>
        <button type="button" class="btn btn-ghost" onclick="closeEditModal()">Cancel</button>
      </div>
    </form>
  </div>
</div>

<!-- ══════════════════════════════════════════
     Two-Step Password Modal
     Step 1 — Request access (AJAX, no reload)
     Step 2 — Enter password and download
══════════════════════════════════════════ -->
<div id="pwModal">
  <div class="modal-box">
    <h3 id="pw-modal-heading">🔒 Protected Document</h3>
    <div class="doc-meta" id="pw-doc-name"></div>

    <!-- Step 1: Request access -->
    <div class="modal-step" id="step1">
      <p class="step-note">
        This document is password-protected. Send an access request to the admin —
        once approved you can come back and enter the password to download.
      </p>
      <div style="display:flex;gap:10px;">
        <button class="btn btn-primary" id="btnSendRequest" style="flex:1;margin-top:0;">
          📨 Send Access Request
        </button>
        <button type="button" class="btn btn-ghost" onclick="closePwModal()">Cancel</button>
      </div>
      <div id="step1-msg" style="margin-top:12px;font-size:13px;display:none;"></div>
    </div>

    <!-- Step 2: Enter password and download -->
    <div class="modal-step" id="step2">
      <p class="step-note">
        Enter the document password provided by the admin to download this file.
      </p>
      <form method="POST" action="<?= BASE_URL ?>/index.php?action=download_with_password">
        <input type="hidden" name="document_id" id="pw-doc-id">
        <div class="form-group">
          <label class="form-label">Document Password</label>
          <input class="form-control" type="password" name="file_password"
                 id="pw-input" placeholder="Enter password…" required autocomplete="off">
        </div>
        <div style="display:flex;gap:10px;margin-top:4px;">
          <button type="submit" class="btn btn-primary" style="flex:1;margin-top:0;">⬇ Download</button>
          <button type="button" class="btn btn-ghost" onclick="closePwModal()">Cancel</button>
        </div>
      </form>
    </div>

    <!-- Step: already pending (no action needed) -->
    <div class="modal-step" id="stepPending">
      <p class="step-note">
        ✅ Your access request has already been sent. Please wait for the admin to approve it —
        then you will be able to enter the password and download.
      </p>
      <div style="display:flex;gap:10px;">
        <button type="button" class="btn btn-ghost" style="flex:1;" onclick="closePwModal()">Close</button>
      </div>
    </div>

  </div>
</div>

<div class="card">

    <!-- Search & Filter Bar -->
    <div class="search-filter-bar">
        <div class="search-wrapper">
            <span class="search-icon">🔍</span>
            <input
                class="form-control"
                type="text"
                id="docSearch"
                placeholder="Search title, uploader, type…"
                autocomplete="off">
        </div>
        <select class="form-control filter-select" id="catFilter">
            <option value="">All Categories</option>
            <?php foreach ($categories as $cat): ?>
                <option value="<?= e($cat) ?>"><?= e($cat) ?></option>
            <?php endforeach; ?>
            <option value="__none__">Uncategorised</option>
        </select>
        <select class="form-control filter-select" id="typeFilter">
            <option value="">All Types</option>
            <?php
            $types = array_unique(array_column($documents, 'file_type'));
            sort($types);
            foreach ($types as $t): ?>
                <option value="<?= e(strtolower($t)) ?>"><?= e(strtoupper($t)) ?></option>
            <?php endforeach; ?>
        </select>
        <span class="result-count" id="resultCount"></span>
        <button class="btn btn-ghost btn-sm" id="clearFilters" style="display:none;margin-top:0;">✕ Clear</button>
    </div>

    <?php if (!empty($documents)): ?>
    <div class="table-responsive">
    <table id="docsTable">
        <thead>
            <tr>
                <th>Title</th>
                <th>Category</th>
                <th>Type</th>
                <th>Size</th>
                <th>Uploaded By</th>
                <th>Status</th>
                <th>Date</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($documents as $doc): ?>
            <?php
            $docId       = (int)$doc['id'];
            $isOwn       = ($doc['uploaded_by'] == $authUser['id']);
            $isProtected = in_array($docId, $passwordDocs);
            $hasApproval = in_array($docId, $approvedDocs);
            $isPending   = in_array($docId, $pendingDocs);
            ?>
            <tr
                data-title="<?= e(strtolower($doc['title'])) ?>"
                data-category="<?= e(strtolower($doc['category_name'] ?? '')) ?>"
                data-category-raw="<?= e($doc['category_name'] ?? '') ?>"
                data-type="<?= e(strtolower($doc['file_type'])) ?>"
                data-uploader="<?= e(strtolower($doc['uploader_name'])) ?>"
            >
                <td>
                    <?= e($doc['title']) ?>
                    <?php if ($isOwn): ?>
                      <span style="font-size:10px;font-weight:600;background:rgba(79,124,255,.15);color:#93b4ff;padding:2px 7px;border-radius:20px;margin-left:6px;">Mine</span>
                    <?php endif; ?>
                    <?php if ($isProtected): ?>
                      <span style="font-size:10px;font-weight:600;background:rgba(245,158,11,.15);color:#fcd34d;padding:2px 7px;border-radius:20px;margin-left:4px;">🔒 Protected</span>
                    <?php endif; ?>
                </td>
                <td><?= e($doc['category_name'] ?? '—') ?></td>
                <td><span class="file-type"><?= strtoupper(e($doc['file_type'])) ?></span></td>
                <td><?= number_format($doc['file_size_kb']) ?> KB</td>
                <td><?= e($doc['uploader_name']) ?></td>
                <td>
                    <span class="badge badge-<?= e($doc['status']) ?>">
                        <?= e($doc['status']) ?>
                    </span>
                </td>
                <td><?= date('d M Y', strtotime($doc['created_at'])) ?></td>
                <td>
                    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">

                        <?php if ($isAdmin || !$isProtected): ?>
                            <!-- Admin or unprotected: plain download -->
                            <a href="<?= BASE_URL ?>/index.php?action=download_document&id=<?= $docId ?>"
                               class="btn btn-sm btn-ghost" style="text-decoration:none;">
                                ⬇ Download
                            </a>

                        <?php elseif ($hasApproval): ?>
                            <!-- Approved: open modal directly at step 2 (password entry) -->
                            <button class="btn btn-sm btn-primary" style="margin-top:0;"
                                onclick="openStep2(<?= $docId ?>, '<?= e(addslashes($doc['title'])) ?>')">
                                🔓 Enter Password
                            </button>

                        <?php elseif ($isPending): ?>
                            <!-- Already requested: open modal at pending step -->
                            <button class="btn btn-sm btn-ghost" style="margin-top:0;border-color:var(--warning);color:#fcd34d;"
                                onclick="openPending(<?= $docId ?>, '<?= e(addslashes($doc['title'])) ?>')">
                                ⏳ Pending Approval
                            </button>

                        <?php else: ?>
                            <!-- Not yet requested: open modal at step 1 -->
                            <button class="btn btn-sm btn-ghost" style="margin-top:0;border-color:var(--warning);color:#fcd34d;"
                                onclick="openStep1(<?= $docId ?>, '<?= e(addslashes($doc['title'])) ?>')">
                                🔑 Request Access
                            </button>

                        <?php endif; ?>

                        <?php if ($isAdmin || $isOwn): ?>
                        <button class="btn btn-sm btn-ghost" style="margin-top:0;border-color:var(--accent);color:var(--accent);"
                            onclick="openEditModal(
                                <?= $docId ?>,
                                '<?= e(addslashes($doc['title'])) ?>',
                                '<?= e(addslashes($doc['description'] ?? '')) ?>',
                                <?= (int)($doc['category_id'] ?? 0) ?>,
                                '<?= e($doc['status']) ?>',
                                <?= $isProtected ? 'true' : 'false' ?>
                            )">
                            ✏️ Edit
                        </button>
                        <?php endif; ?>

                        <?php if ($isAdmin): ?>
                        <form method="POST" action="<?= BASE_URL ?>/index.php?page=documents" style="margin:0;">
                            <input type="hidden" name="action" value="delete_document">
                            <input type="hidden" name="id"     value="<?= $docId ?>">
                            <button type="submit" class="btn btn-sm btn-danger"
                                    onclick="return confirm('Delete \'<?= e(addslashes($doc['title'])) ?>\'? This cannot be undone.')">
                                🗑 Delete
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div><!-- /.table-responsive -->

    <!-- Empty state shown when filters yield no results -->
    <div class="no-results" id="noResults" style="display:none;">
        <div class="no-results-icon">🔍</div>
        <p>No documents match your search.</p>
        <button class="btn btn-ghost btn-sm" onclick="clearAllFilters()">Clear filters</button>
    </div>

    <?php else: ?>
    <div class="empty-state">
        <div class="empty-state-icon">📂</div>
        <p>No documents found.</p>
    </div>
    <?php endif; ?>

</div>

<script>
// ── Search & Filter ──────────────────────────────
(function () {
    const searchInput = document.getElementById('docSearch');
    const catFilter   = document.getElementById('catFilter');
    const typeFilter  = document.getElementById('typeFilter');
    const resultCount = document.getElementById('resultCount');
    const clearBtn    = document.getElementById('clearFilters');
    const noResults   = document.getElementById('noResults');
    const table       = document.getElementById('docsTable');

    if (!table) return;

    const allRows = Array.from(table.querySelectorAll('tbody tr'));
    const total   = allRows.length;

    function applyFilters() {
        const q    = searchInput.value.trim().toLowerCase();
        const cat  = catFilter.value;
        const type = typeFilter.value.toLowerCase();
        let visible = 0;

        allRows.forEach(row => {
            const matchSearch = !q ||
                row.dataset.title.includes(q) ||
                row.dataset.uploader.includes(q) ||
                row.dataset.type.includes(q);

            const matchCat = !cat ||
                (cat === '__none__'
                    ? row.dataset.categoryRaw === ''
                    : row.dataset.categoryRaw === cat);

            const matchType = !type || row.dataset.type === type;

            const show = matchSearch && matchCat && matchType;
            row.classList.toggle('hidden-row', !show);
            if (show) visible++;
        });

        resultCount.textContent = (q || cat || type)
            ? `${visible} of ${total} result${total !== 1 ? 's' : ''}`
            : '';

        const hasFilter = q || cat || type;
        clearBtn.style.display  = hasFilter ? '' : 'none';
        noResults.style.display = (visible === 0) ? '' : 'none';
        table.style.display     = (visible === 0) ? 'none' : '';
    }

    window.clearAllFilters = function () {
        searchInput.value = '';
        catFilter.value   = '';
        typeFilter.value  = '';
        applyFilters();
        searchInput.focus();
    };

    document.getElementById('clearFilters').addEventListener('click', clearAllFilters);
    searchInput.addEventListener('input',  applyFilters);
    catFilter.addEventListener('change',   applyFilters);
    typeFilter.addEventListener('change',  applyFilters);
    applyFilters();
})();

// ── Two-step password modal ──────────────────────
const modal    = document.getElementById('pwModal');
const docName  = document.getElementById('pw-doc-name');
const docIdInp = document.getElementById('pw-doc-id');
const pwInput  = document.getElementById('pw-input');
const step1    = document.getElementById('step1');
const step2    = document.getElementById('step2');
const stepPend = document.getElementById('stepPending');
const heading  = document.getElementById('pw-modal-heading');
const sendBtn  = document.getElementById('btnSendRequest');
const step1Msg = document.getElementById('step1-msg');

let currentDocId = null;

function showStep(which) {
    [step1, step2, stepPend].forEach(s => s.classList.remove('active'));
    which.classList.add('active');
}

function openModal(docId, title) {
    currentDocId = docId;
    docName.textContent = title;
    docIdInp.value = docId;
    pwInput.value  = '';
    step1Msg.style.display = 'none';
    step1Msg.textContent   = '';
    sendBtn.disabled = false;
    sendBtn.textContent = '📨 Send Access Request';
    modal.classList.add('open');
}

// Open at Step 1 — user hasn't requested yet
window.openStep1 = function(docId, title) {
    heading.textContent = '🔒 Request Document Access';
    openModal(docId, title);
    showStep(step1);
};

// Open at Step 2 — user already has approval, just needs to enter password
window.openStep2 = function(docId, title) {
    heading.textContent = '🔓 Enter Document Password';
    openModal(docId, title);
    showStep(step2);
    setTimeout(() => pwInput.focus(), 100);
};

// Open at pending info step
window.openPending = function(docId, title) {
    heading.textContent = '⏳ Request Pending';
    openModal(docId, title);
    showStep(stepPend);
};

window.closePwModal = function() {
    modal.classList.remove('open');
};

// Click outside to close
modal.addEventListener('click', function(e) {
    if (e.target === modal) closePwModal();
});

// Step 1 → send request via AJAX, then slide to Step 2
sendBtn.addEventListener('click', async function() {
    sendBtn.disabled = true;
    sendBtn.textContent = 'Sending…';

    const formData = new FormData();
    formData.append('action', 'request_document_password');
    formData.append('document_id', currentDocId);

    try {
        const res = await fetch('<?= BASE_URL ?>/index.php', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: formData
        });
        const data = await res.json();

        if (data.ok) {
            // Request sent (or was already sent) — move to password entry
            step1Msg.style.display = 'block';
            step1Msg.style.color   = '#86efac';
            step1Msg.textContent   = '✅ Request sent! Now enter the password if admin has shared it with you.';

            // After a short pause, slide to step 2
            setTimeout(() => {
                heading.textContent = '🔓 Enter Document Password';
                showStep(step2);
                pwInput.focus();
            }, 1200);
        } else {
            step1Msg.style.display = 'block';
            step1Msg.style.color   = '#fca5a5';
            step1Msg.textContent   = '❌ Something went wrong. Please try again.';
            sendBtn.disabled = false;
            sendBtn.textContent = '📨 Send Access Request';
        }
    } catch (err) {
        step1Msg.style.display = 'block';
        step1Msg.style.color   = '#fca5a5';
        step1Msg.textContent   = '❌ Network error. Please try again.';
        sendBtn.disabled = false;
        sendBtn.textContent = '📨 Send Access Request';
    }
});

// ── Edit Document Modal ──────────────────────────
const editModal = document.getElementById('editModal');

window.openEditModal = function(id, title, description, categoryId, status, isProtected) {
    document.getElementById('edit-doc-id').value       = id;
    document.getElementById('edit-title').value        = title;
    document.getElementById('edit-description').value  = description;
    document.getElementById('edit-status').value       = status;
    document.getElementById('edit-password').value     = '';
    document.getElementById('edit-doc-meta').textContent = title;

    // Set category dropdown
    const catSel = document.getElementById('edit-category');
    catSel.value = categoryId || '';

    // Show hint about existing password
    const pwNote = document.getElementById('edit-pw-note');
    if (isProtected) {
        pwNote.textContent = '🔒 This file already has a password. Enter a new one to replace it, or leave blank to keep it.';
        pwNote.style.color = '#fcd34d';
    } else {
        pwNote.textContent = 'Optionally add a password to protect this file.';
        pwNote.style.color = 'var(--text-muted)';
    }

    editModal.style.display = 'grid';
    setTimeout(() => document.getElementById('edit-title').focus(), 100);
};

window.closeEditModal = function() {
    editModal.style.display = 'none';
};

editModal.addEventListener('click', function(e) {
    if (e.target === editModal) closeEditModal();
});
</script>