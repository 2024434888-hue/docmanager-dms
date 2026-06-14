<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/config.php';

startSecureSession();

// -- Download handler: runs before any HTML or auth redirects --
if (isset($_GET['action']) && $_GET['action'] === 'download_document') {
    $authUser = getAuthUser();
    if (!$authUser) { header('Location: ' . BASE_URL . '/index.php?page=login'); exit; }

    $db    = getDB();
    $id    = (int)($_GET['id'] ?? 0);
    $uid   = $authUser['id'];
    $isAdm = ($authUser['role'] ?? '') === 'admin';

    $stmt = $db->prepare("SELECT * FROM document WHERE id = ?");
    $stmt->execute([$id]);
    $doc = $stmt->fetch();

    if (!$doc) { die('File not found.'); }

    // All authenticated users can download any document.
    // (document_access is reserved for future fine-grained permissions)

    $filePath = rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . DIRECTORY_SEPARATOR . 'dms' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . $doc['file_path'];
    if (!file_exists($filePath)) { die('File missing from server.'); }

    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $doc['title'] . '.' . $doc['file_type'] . '"');
    header('Content-Length: ' . filesize($filePath));
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    ob_clean();
    flush();
    readfile($filePath);
    exit;
}

// -- Password-protected download: POST handler --
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_GET['action']) && $_GET['action'] === 'download_with_password'
) {
    $authUser = getAuthUser();
    if (!$authUser) { header('Location: ' . BASE_URL . '/index.php?page=login'); exit; }

    $db          = getDB();
    $doc_id      = (int)($_POST['document_id'] ?? 0);
    $enteredPass = $_POST['file_password'] ?? '';
    $uid         = $authUser['id'];
    $isAdm       = ($authUser['role'] ?? '') === 'admin';

    // Load document
    $stmt = $db->prepare("SELECT * FROM document WHERE id = ?");
    $stmt->execute([$doc_id]);
    $doc = $stmt->fetch();
    if (!$doc) { die('Document not found.'); }

    // Load the stored password hash
    $pwStmt = $db->prepare("SELECT password_hash FROM document_password WHERE document_id = ?");
    $pwStmt->execute([$doc_id]);
    $pwRow = $pwStmt->fetch();

    if (!$pwRow) { die('This document is not password-protected.'); }

    // Verify entered password
    if (!password_verify($enteredPass, $pwRow['password_hash'])) {
        flash('error', 'Incorrect document password. Please try again.');
        header('Location: ' . BASE_URL . '/index.php?page=documents');
        exit;
    }

    // For non-admins: must have an approved access request
    if (!$isAdm) {
        $chk = $db->prepare("
            SELECT id FROM document_password_request
            WHERE document_id = ? AND user_id = ? AND status = 'approved'
        ");
        $chk->execute([$doc_id, $uid]);
        if (!$chk->fetch()) {
            flash('error', 'You do not have approved access to this document.');
            header('Location: ' . BASE_URL . '/index.php?page=documents');
            exit;
        }
    }

    // Log the download activity
    $db->prepare("
        INSERT INTO document_activity (document_id, user_id, action, ip_address, acted_at)
        VALUES (?, ?, 'downloaded', ?, NOW())
    ")->execute([$doc_id, $uid, $_SERVER['REMOTE_ADDR']]);

    // Serve the file
    $filePath = rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . DIRECTORY_SEPARATOR . 'dms' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . $doc['file_path'];
    if (!file_exists($filePath)) { die('File missing from server.'); }

    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $doc['title'] . '.' . $doc['file_type'] . '"');
    header('Content-Length: ' . filesize($filePath));
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    ob_clean();
    flush();
    readfile($filePath);
    exit;
}

// ── Router ────────────────────────────────────────
$page   = $_GET['page'] ?? 'login';
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Pages that don't require login
$publicPages = [
    'login',
    'forgot_password',
    'reset_password',
    'request-account'
];
// Redirect logged-in users away from login page
if (in_array($page, $publicPages) && getAuthUser()) {
    redirect('dashboard');
}

// Protect private pages
if (!in_array($page, $publicPages) && !getAuthUser()) {
    redirect('login');
}

// ── Action Handlers (POST) ────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Login
    if ($action === 'login') {
        $username = trim($_POST['username'] ?? '');
        $password  = $_POST['password'] ?? '';

        if (empty($username) || empty($password)) {
            flash('error', 'Please enter your username and password.');
            redirect('login');
        }

        $db   = getDB();
        $stmt = $db->prepare("
            SELECT u.*, e.name AS employee_name, e.department, e.image
            FROM users u
            JOIN employee e ON e.id = u.employee_id
            WHERE u.username = ? AND u.is_active = 1
            LIMIT 1
        ");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && verifyPassword($password, $user['password_hash'])) {
            // Success — store user in session
            $_SESSION['user'] = [
                'id'            => $user['id'],
                'employee_id'   => $user['employee_id'],
                'username'      => $user['username'],
                'email'         => $user['email'],
                'role'          => $user['role'],
                'employee_name' => $user['employee_name'],
                'department'    => $user['department'],
                'image'         => $user['image'],
            ];

            // Update last login timestamp
            $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")
               ->execute([$user['id']]);

            // Log the activity (if they have any documents)
            $db->prepare("
                INSERT INTO document_activity (document_id, user_id, action, ip_address, acted_at)
                SELECT id, ?, 'viewed', ?, NOW() FROM document WHERE uploaded_by = ? LIMIT 0
            ")->execute([$user['id'], $_SERVER['REMOTE_ADDR'], $user['id']]);

            flash('success', 'Welcome back, ' . $user['employee_name'] . '!');
            redirect('dashboard');
        } else {
            flash('error', 'Invalid username or password.');
            redirect('login');
        }
    }

    // Logout
    if ($action === 'logout') {
        session_destroy();
        header('Location: ' . BASE_URL . '/index.php?page=login');
        exit;
    }

    // ── New account request (public form) ────────────────
    // Flow: create employee row first → then user_request row linking to it
    if ($action === 'request_account') {
        $name           = trim($_POST['name']           ?? '');
        $address1       = trim($_POST['address1']       ?? '');
        $address2       = trim($_POST['address2']       ?? '');
        $postcode       = trim($_POST['postcode']       ?? '');
        $address        = implode(' | ', array_filter([$address1, $address2, $postcode]));
        $department     = trim($_POST['department']     ?? '');
        $email          = trim($_POST['email']          ?? '');
        $requested_role = ($_POST['requested_role'] ?? 'user') === 'admin' ? 'admin' : 'user';

        if (!$name || !$address1 || !$postcode || !$department || !$email) {
            flash('error', 'Please fill in all fields.');
            redirect('request-account');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Please enter a valid email address.');
            redirect('request-account');
        }

        $db = getDB();

        // Prevent duplicate pending requests for the same employee email
        $chk = $db->prepare("
            SELECT ur.id FROM user_request ur
            JOIN employee e ON e.id = ur.employee_id
            WHERE e.name = ? AND ur.status = 'pending'
        ");
        $chk->execute([$name]);
        if ($chk->fetch()) {
            flash('error', 'A pending request for this name already exists.');
            redirect('request-account');
        }

        // 1. Create employee record (name, address, department, image nullable)
        $db->prepare("
            INSERT INTO employee (name, address, department)
            VALUES (?, ?, ?)
        ")->execute([$name, $address, $department]);
        $employee_id = $db->lastInsertId();

        // 2. Create user_request linked to the new employee
        //    Columns: user_id (null – no account yet), employee_id, requested_role, status, requested_at
        $db->prepare("
            INSERT INTO user_request (user_id, employee_id, requested_role, status, requested_at)
            VALUES (NULL, ?, ?, 'pending', NOW())
        ")->execute([$employee_id, $requested_role]);

        // Store email temporarily in session so admin can see it (employee table has no email column)
        $_SESSION['req_email_' . $employee_id] = $email;

        flash('success', 'Your request has been submitted. An admin will create your account shortly.');
        redirect('request-account');
    }

    // ── Admin: approve request → create users row ────────
    if ($action === 'approve_request') {
        requireAdmin();
        $db       = getDB();
        $req_id   = (int)($_POST['req_id']  ?? 0);
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $email    = trim($_POST['email']    ?? '');
        $role     = ($_POST['role'] ?? 'user') === 'admin' ? 'admin' : 'user';

        if (!$req_id || !$username || !$password || !$email) {
            flash('error', 'Username, password, and email are all required.');
            redirect('account-requests');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Please enter a valid email address.');
            redirect('account-requests');
        }

        // Load the request + employee details
        $stmt = $db->prepare("
            SELECT ur.*, e.name AS emp_name, e.department, e.address
            FROM user_request ur
            JOIN employee e ON e.id = ur.employee_id
            WHERE ur.id = ? AND ur.status = 'pending'
        ");
        $stmt->execute([$req_id]);
        $r = $stmt->fetch();
        if (!$r) {
            flash('error', 'Request not found or already processed.');
            redirect('account-requests');
        }

        // Check username uniqueness
        $uChk = $db->prepare("SELECT id FROM users WHERE username = ?");
        $uChk->execute([$username]);
        if ($uChk->fetch()) {
            flash('error', 'Username already taken. Please choose another.');
            redirect('account-requests');
        }

        // Create the user account
        $db->prepare("
            INSERT INTO users (employee_id, username, email, password_hash, role, is_active, created_at)
            VALUES (?, ?, ?, ?, ?, 1, NOW())
        ")->execute([$r['employee_id'], $username, $email, hashPassword($password), $role]);
        $new_user_id = $db->lastInsertId();

        // Mark request as approved, link user_id + approved_by
        $db->prepare("
            UPDATE user_request
            SET status = 'approved', user_id = ?, approved_by = ?
            WHERE id = ?
        ")->execute([$new_user_id, $authUser['id'], $req_id]);

        flash('success', "Account created for {$r['emp_name']}. Username: {$username}");
        redirect('account-requests');
    }

    // ── Admin: set new password for a reset request ──────
    if ($action === 'reset_request') {
        requireAdmin();
        $db         = getDB();
        $reset_id   = (int)($_POST['reset_id']  ?? 0);
        $newPass    = trim($_POST['new_password'] ?? '');

        if (!$reset_id || strlen($newPass) < 6) {
            flash('error', 'Password must be at least 6 characters.');
            redirect('password-requests');
        }

        $stmt = $db->prepare("
            SELECT pr.*, u.username, e.name AS emp_name
            FROM password_reset pr
            JOIN users u ON u.id = pr.user_id
            JOIN employee e ON e.id = u.employee_id
            WHERE pr.id = ? AND pr.is_used = 0
        ");
        $stmt->execute([$reset_id]);
        $req = $stmt->fetch();

        if (!$req) {
            flash('error', 'Request not found or already resolved.');
            redirect('password-requests');
        }

        $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?")
           ->execute([hashPassword($newPass), $req['user_id']]);

        $db->prepare("UPDATE password_reset SET is_used = 1 WHERE id = ?")
           ->execute([$reset_id]);

        flash('success', "Password reset for {$req['emp_name']} ({$req['username']}). New password: {$newPass}");
        redirect('password-requests');
    }

    // ── Admin: dismiss/ignore a reset request ─────────────
    if ($action === 'dismiss_reset') {
        requireAdmin();
        $reset_id = (int)($_POST['reset_id'] ?? 0);
        getDB()->prepare("UPDATE password_reset SET is_used = 1 WHERE id = ?")
               ->execute([$reset_id]);
        flash('success', 'Request dismissed.');
        redirect('password-requests');
    }

    // ── Admin: reject an account request ─────────────────
    if ($action === 'reject_request') {
        requireAdmin();
        $req_id = (int)($_POST['req_id'] ?? 0);
        getDB()->prepare("
            UPDATE user_request SET status = 'rejected', approved_by = ?
            WHERE id = ?
        ")->execute([$authUser['id'], $req_id]);
        flash('success', 'Request has been rejected.');
        redirect('account-requests');
    }


    // u2500u2500 Upload document u2500u2500u2500u2500u2500u2500u2500u2500u2500u2500u2500u2500u2500u2500u2500u2500u2500u2500u2500u2500u2500u2500u2500u2500u2500u2500u2500u2500u2500u2500u2500u2500u2500u2500u2500u2500u2500u2500u2500u2500
    if ($action === 'upload_document') {
        $db          = getDB();
        $title       = trim($_POST['title']       ?? '');
        $filePassword = trim($_POST['file_password'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $category_id = $_POST['category_id']      ?? null;
        if (empty($_FILES['document']['name'])) { flash('error', 'Please choose a file.'); redirect('upload'); }
        $file = $_FILES['document'];
        $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['pdf','doc','docx','xls','xlsx','ppt','pptx','txt','jpg','jpeg','png'];
        if (!in_array($ext, $allowed)) { flash('error', 'File type not allowed.'); redirect('upload'); }
        $uploadDir = rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . DIRECTORY_SEPARATOR . 'dms' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR;
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        $newName = uniqid() . '.' . $ext;
        if (move_uploaded_file($file['tmp_name'], $uploadDir . $newName)) {
            $db->prepare("INSERT INTO document (title,description,file_path,file_type,file_size_kb,uploaded_by,category_id,status) VALUES (?,?,?,?,?,?,?,'active')")->execute([$title,$description,$newName,$ext,round($file['size']/1024),$_SESSION['user']['id'],$category_id?:null]);
            $newDocId = $db->lastInsertId();
            if (!empty($filePassword)) {

    $db->prepare("
        INSERT INTO document_password
        (
            document_id,
            password_hash,
            created_by
        )
        VALUES
        (
            ?,
            ?,
            ?
        )
    ")->execute([
        $newDocId,
        password_hash(
            $filePassword,
            PASSWORD_DEFAULT
        ),
        $_SESSION['user']['id']
    ]);

}

            // Log upload activity
            $db->prepare("
                INSERT INTO document_activity (document_id, user_id, action, ip_address, acted_at)
                VALUES (?, ?, 'uploaded', ?, NOW())
            ")->execute([$newDocId, $_SESSION['user']['id'], $_SERVER['REMOTE_ADDR']]);

            flash('success', 'Document uploaded successfully. All users can now access it.');
        } else { flash('error', 'Failed to save file. Check folder permissions.'); }
        redirect('documents');
    }
    // ── Admin: delete document ───────────────────────────────
    if ($action === 'delete_document') {
        requireAdmin();
        $db  = getDB();
        $id  = (int)($_POST['id'] ?? 0);
        if ($id) {
            // Get file path so we can delete the physical file too
            $stmt = $db->prepare("SELECT file_path FROM document WHERE id = ?");
            $stmt->execute([$id]);
            $doc = $stmt->fetch();
            if ($doc) {
                $filePath = rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . DIRECTORY_SEPARATOR . 'dms' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . $doc['file_path'];
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
                // Also remove related records
                $db->prepare("DELETE FROM document_access   WHERE document_id = ?")->execute([$id]);
                $db->prepare("DELETE FROM document_activity WHERE document_id = ?")->execute([$id]);
                $db->prepare("DELETE FROM document          WHERE id = ?")->execute([$id]);
                flash('success', 'Document deleted successfully.');
            }
        }
        redirect('documents');
    }

    // ── Edit document (owner or admin) ────────────────────────
    if ($action === 'edit_document') {
        $db          = getDB();
        $doc_id      = (int)($_POST['doc_id']      ?? 0);
        $title       = trim($_POST['title']        ?? '');
        $description = trim($_POST['description']  ?? '');
        $category_id = $_POST['category_id'] !== '' ? (int)$_POST['category_id'] : null;
        $status      = in_array($_POST['status'] ?? '', ['active','archived']) ? $_POST['status'] : 'active';
        $newPassword = trim($_POST['file_password'] ?? '');

        if (!$doc_id || !$title) {
            flash('error', 'Title is required.');
            redirect('documents');
        }

        // Load the document to verify ownership
        $stmt = $db->prepare("SELECT uploaded_by FROM document WHERE id = ?");
        $stmt->execute([$doc_id]);
        $doc = $stmt->fetch();

        if (!$doc) {
            flash('error', 'Document not found.');
            redirect('documents');
        }

        // Permission check: must be admin OR the owner
        if (!$isAdmin && (int)$doc['uploaded_by'] !== (int)$authUser['id']) {
            flash('error', 'You do not have permission to edit this document.');
            redirect('documents');
        }

        // Update document metadata
        $db->prepare("
            UPDATE document
            SET title = ?, description = ?, category_id = ?, status = ?
            WHERE id = ?
        ")->execute([$title, $description, $category_id, $status, $doc_id]);

        // Handle password: update/add if provided, leave alone if blank
        if ($newPassword !== '') {
            $hash = password_hash($newPassword, PASSWORD_DEFAULT);
            // Upsert: update if exists, insert if not
            $exists = $db->prepare("SELECT id FROM document_password WHERE document_id = ?");
            $exists->execute([$doc_id]);
            if ($exists->fetch()) {
                $db->prepare("UPDATE document_password SET password_hash = ?, created_by = ? WHERE document_id = ?")
                   ->execute([$hash, $authUser['id'], $doc_id]);
            } else {
                $db->prepare("INSERT INTO document_password (document_id, password_hash, created_by) VALUES (?, ?, ?)")
                   ->execute([$doc_id, $hash, $authUser['id']]);
            }
        }

        // Log activity
        $db->prepare("
            INSERT INTO document_activity (document_id, user_id, action, ip_address, acted_at)
            VALUES (?, ?, 'edited', ?, NOW())
        ")->execute([$doc_id, $authUser['id'], $_SERVER['REMOTE_ADDR']]);

        flash('success', 'Document updated successfully.');
        redirect('documents');
    }

    // ── Admin: add category ───────────────────────────────
    if ($action === 'add_category') {
        requireAdmin();
        $db          = getDB();
        $name        = trim($_POST['name']        ?? '');
        $description = trim($_POST['description'] ?? '');
        if (!$name) {
            flash('error', 'Category name is required.');
        } else {
            $db->prepare("INSERT INTO document_category (name, description) VALUES (?, ?)")
               ->execute([$name, $description]);
            flash('success', 'Category added successfully.');
        }
        redirect('categories');
    }

    // ── Admin: delete category ────────────────────────────
    if ($action === 'delete_category') {
        requireAdmin();
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            getDB()->prepare("DELETE FROM document_category WHERE id = ?")->execute([$id]);
            flash('success', 'Category deleted successfully.');
        }
        redirect('categories');
    }

    // ── Admin: delete user ────────────────────────────────
    if ($action === 'delete_user') {
        requireAdmin();
        $db      = getDB();
        $user_id = (int)($_POST['user_id'] ?? 0);

        // Prevent self-deletion
        if ($user_id === (int)($authUser['id'] ?? 0)) {
            flash('error', 'You cannot delete your own account.');
            redirect('users');
        }

        if ($user_id) {
            try {
                $db->beginTransaction();

                // Fetch employee_id linked to this user
                $stmt = $db->prepare("SELECT employee_id FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $row = $stmt->fetch();

                if (!$row) {
                    flash('error', 'User not found.');
                    redirect('users');
                }

                $employee_id = $row['employee_id'];

                // Remove related records to avoid FK constraint errors
                $db->prepare("DELETE FROM document_activity WHERE user_id = ?")->execute([$user_id]);
                $db->prepare("DELETE FROM document_access   WHERE user_id = ?")->execute([$user_id]);
                $db->prepare("DELETE FROM password_reset    WHERE user_id = ?")->execute([$user_id]);
                $db->prepare("DELETE FROM user_request      WHERE user_id = ?")->execute([$user_id]);

                // Delete the user account
                $db->prepare("DELETE FROM users WHERE id = ?")->execute([$user_id]);

                // Delete the linked employee record
                $db->prepare("DELETE FROM employee WHERE id = ?")->execute([$employee_id]);

                $db->commit();
                flash('success', 'User deleted successfully.');
            } catch (Exception $e) {
                $db->rollBack();
                flash('error', 'Error deleting user: ' . $e->getMessage());
            }
        }
        redirect('users');
    }
}

if ($action === 'approve_document_password') {

    requireAdmin();

    $request_id = (int)$_POST['request_id'];

    getDB()->prepare("
        UPDATE document_password_request
        SET
            status='approved',
            approved_by=?
        WHERE id=?
    ")->execute([
        $_SESSION['user']['id'],
        $request_id
    ]);

    flash(
        'success',
        'Password request approved.'
    );

    redirect(
        'document-password-request'
    );
}

if ($action === 'request_document_password') {

    getDB()->prepare("
        INSERT INTO
        document_password_request
        (
            document_id,
            user_id
        )
        VALUES
        (
            ?,
            ?
        )
    ")->execute([
        $_POST['document_id'],
        $_SESSION['user']['id']
    ]);

    flash(
        'success',
        'Request sent to admin.'
    );

    redirect('documents');
}

// ── Page Data ─────────────────────────────────────
$authUser = getAuthUser();
$isAdmin  = ($authUser['role'] ?? '') === 'admin';

// Dashboard stats (admin vs user)
$stats = [];
if ($page === 'dashboard' && $authUser) {
    $db = getDB();
    if ($isAdmin) {
        $stats['total_docs']  = $db->query("SELECT COUNT(*) FROM document")->fetchColumn();
        $stats['total_users'] = $db->query("SELECT COUNT(*) FROM users WHERE is_active = 1")->fetchColumn();
        $stats['total_cats']  = $db->query("SELECT COUNT(*) FROM document_category")->fetchColumn();
        $stats['recent_docs'] = $db->query("
            SELECT d.*, u.username, e.name AS uploader_name
            FROM document d
            JOIN users u ON u.id = d.uploaded_by
            JOIN employee e ON e.id = u.employee_id
            ORDER BY d.created_at DESC LIMIT 5
        ")->fetchAll();
    } else {
        $uid = $authUser['id'];

        // Count docs the user uploaded themselves
        $stmt = $db->prepare("SELECT COUNT(*) FROM document WHERE uploaded_by = ?");
        $stmt->execute([$uid]); $stats['my_docs'] = $stmt->fetchColumn();

        // Count ALL documents available (all users see all docs)
        $stats['total_docs'] = $db->query("SELECT COUNT(*) FROM document")->fetchColumn();

        // Recent docs - all documents visible to user
        $stats['recent_docs'] = $db->query("
            SELECT d.*, e.name AS uploader_name
            FROM document d
            JOIN users u ON u.id = d.uploaded_by
            JOIN employee e ON e.id = u.employee_id
            ORDER BY d.created_at DESC LIMIT 5
        ")->fetchAll();
    }
}

// ── HTML Output ───────────────────────────────────
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= APP_NAME ?> <?= $page !== 'login' ? '— ' . ucfirst($page) : '' ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
      --bg:         #0f1117;
      --surface:    #181b23;
      --surface2:   #1e2130;
      --border:     #2a2f42;
      --accent:     #4f7cff;
      --accent-dim: #3a5fd9;
      --success:    #22c55e;
      --danger:     #f43f5e;
      --warning:    #f59e0b;
      --text:       #e8eaf0;
      --text-muted: #7b82a0;
      --text-dim:   #4a5070;
      --font:       'DM Sans', sans-serif;
      --mono:       'DM Mono', monospace;
      --radius:     10px;
      --radius-lg:  16px;
      --shadow:     0 4px 24px rgba(0,0,0,.4);
      --sidebar-w:  240px;
    }

    body {
      font-family: var(--font);
      background: var(--bg);
      color: var(--text);
      min-height: 100vh;
      font-size: 15px;
      line-height: 1.6;
    }

    a { color: var(--accent); text-decoration: none; }
    a:hover { text-decoration: underline; }

    /* ── Alerts ── */
    .alert {
      padding: 12px 16px;
      border-radius: var(--radius);
      font-size: 14px;
      margin-bottom: 20px;
      border-left: 3px solid;
    }
    .alert-error   { background: rgba(244,63,94,.1);  border-color: var(--danger);  color: #fca5a5; }
    .alert-success { background: rgba(34,197,94,.1);  border-color: var(--success); color: #86efac; }

    /* ── Login Page ── */
    .login-wrap {
      min-height: 100vh;
      display: grid;
      place-items: center;
      padding: 16px;
      background:
        radial-gradient(ellipse 80% 60% at 60% 0%, rgba(79,124,255,.12) 0%, transparent 60%),
        radial-gradient(ellipse 50% 40% at 0% 100%, rgba(79,124,255,.07) 0%, transparent 50%),
        var(--bg);
    }

    .login-card {
      width: 100%;
      max-width: 420px;
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: var(--radius-lg);
      padding: 44px 40px;
      box-shadow: var(--shadow);
    }

    .login-logo {
      display: flex;
      align-items: center;
      justify-content: center;
      margin-bottom: 32px;
    }

    .login-logo img {
      height: 80px;
      width: auto;
      object-fit: contain;
    }

    .login-heading { font-size: 22px; font-weight: 600; margin-bottom: 6px; }
    .login-sub     { color: var(--text-muted); font-size: 14px; margin-bottom: 28px; }

    /* ── Form ── */
    .form-group { margin-bottom: 18px; }
    .form-label {
      display: block;
      font-size: 13px;
      font-weight: 500;
      color: var(--text-muted);
      margin-bottom: 7px;
      letter-spacing: .3px;
      text-transform: uppercase;
    }
    .form-control {
      width: 100%;
      padding: 11px 14px;
      background: var(--surface2);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      color: var(--text);
      font-family: var(--font);
      font-size: 15px;
      outline: none;
      transition: border-color .2s, box-shadow .2s;
    }
    .form-control:focus {
      border-color: var(--accent);
      box-shadow: 0 0 0 3px rgba(79,124,255,.15);
    }
    .form-control::placeholder { color: var(--text-dim); }
    select.form-control {
      cursor: pointer;
      appearance: none;
      background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8'%3E%3Cpath fill='%237b82a0' d='M1 1l5 5 5-5'/%3E%3C/svg%3E");
      background-repeat: no-repeat;
      background-position: right 14px center;
      padding-right: 36px;
    }
    select.form-control option { background: #1e2130; }

    .btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      padding: 11px 20px;
      border-radius: var(--radius);
      font-family: var(--font);
      font-size: 15px;
      font-weight: 500;
      cursor: pointer;
      border: none;
      transition: all .2s;
      white-space: nowrap;
    }
    .btn-primary {
      background: var(--accent);
      color: #fff;
      width: 100%;
      margin-top: 6px;
    }
    .btn-primary:hover { background: var(--accent-dim); transform: translateY(-1px); }
    .btn-sm { padding: 7px 14px; font-size: 13px; }
    .btn-ghost {
      background: transparent;
      color: var(--text-muted);
      border: 1px solid var(--border);
    }
    .btn-ghost:hover { border-color: var(--accent); color: var(--accent); }
    .btn-danger { background: var(--danger); color: #fff; }

    .login-footer {
      margin-top: 24px;
      text-align: center;
      font-size: 13px;
      color: var(--text-muted);
    }

    /* ── App Shell ── */
    .app { display: flex; min-height: 100vh; }

    /* ── Sidebar toggle button (mobile hamburger) ── */
    .sidebar-toggle {
      position: fixed;
      top: 14px;
      left: 14px;
      z-index: 300;
      width: 38px;
      height: 38px;
      border-radius: 9px;
      background: var(--surface);
      border: 1px solid var(--border);
      color: var(--text-muted);
      cursor: pointer;
      display: none;
      align-items: center;
      justify-content: center;
      font-size: 18px;
      transition: background .15s, color .15s;
      box-shadow: var(--shadow);
    }
    .sidebar-toggle:hover { background: var(--surface2); color: var(--text); }

    /* Desktop collapse toggle (inside sidebar header) */
    .sidebar-collapse-btn {
      background: none;
      border: none;
      color: var(--text-dim);
      cursor: pointer;
      font-size: 16px;
      padding: 4px 6px;
      border-radius: 6px;
      transition: background .15s, color .15s;
      line-height: 1;
      flex-shrink: 0;
    }
    .sidebar-collapse-btn:hover { background: var(--surface2); color: var(--text); }

    .sidebar {
      width: var(--sidebar-w);
      flex-shrink: 0;
      background: var(--surface);
      border-right: 1px solid var(--border);
      display: flex;
      flex-direction: column;
      padding: 24px 0;
      position: fixed;
      top: 0; left: 0; bottom: 0;
      transition: width .25s ease, transform .25s ease;
      overflow: hidden;
      z-index: 250;
    }

    /* ── Collapsed sidebar (desktop) ── */
    .sidebar.collapsed { width: 60px; }
    .sidebar.collapsed .sidebar-logo-text,
    .sidebar.collapsed .nav-section-label,
    .sidebar.collapsed .nav-label,
    .sidebar.collapsed .user-info,
    .sidebar.collapsed .sidebar-logout,
    .sidebar.collapsed .nav-badge { display: none !important; }
    .sidebar.collapsed .nav-item { justify-content: center; padding: 9px 0; }
    .sidebar.collapsed .nav-icon { width: auto; margin: 0; }
    .sidebar.collapsed .sidebar-logo { justify-content: center; padding-left: 0; padding-right: 0; }
    .sidebar.collapsed .sidebar-user { justify-content: center; padding: 16px 0; }
    .sidebar.collapsed .user-avatar { margin: 0; }
    .sidebar.collapsed .sidebar-collapse-btn { transform: rotate(180deg); }

    /* ── Overlay (mobile only) ── */
    .sidebar-overlay {
      display: none;
      position: fixed;
      inset: 0;
      background: rgba(0,0,0,.55);
      z-index: 240;
    }
    .sidebar-overlay.open { display: block; }

    .sidebar-logo {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 0 16px 24px;
      border-bottom: 1px solid var(--border);
    }
    .sidebar-logo img { height: 48px; width: auto; object-fit: contain; }

    .sidebar-nav { flex: 1; padding: 16px 12px; overflow-y: auto; overflow-x: hidden; }
    .nav-section-label {
      font-size: 11px;
      font-weight: 600;
      letter-spacing: .8px;
      text-transform: uppercase;
      color: var(--text-dim);
      padding: 0 8px;
      margin: 16px 0 6px;
      white-space: nowrap;
    }
    .nav-item {
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 9px 10px;
      border-radius: 8px;
      color: var(--text-muted);
      font-size: 14px;
      font-weight: 500;
      transition: all .15s;
      cursor: pointer;
      margin-bottom: 2px;
      text-decoration: none;
      white-space: nowrap;
    }
    .nav-item:hover { background: var(--surface2); color: var(--text); text-decoration: none; }
    .nav-item.active { background: rgba(79,124,255,.15); color: var(--accent); }
    .nav-icon { font-size: 16px; width: 20px; text-align: center; flex-shrink: 0; }

    .sidebar-user {
      padding: 16px 20px;
      border-top: 1px solid var(--border);
      display: flex;
      align-items: center;
      gap: 10px;
    }
    .user-avatar {
      width: 34px; height: 34px;
      border-radius: 50%;
      background: var(--accent);
      display: grid; place-items: center;
      font-size: 14px; font-weight: 600;
      color: #fff; flex-shrink: 0; overflow: hidden;
    }
    .user-avatar img { width: 100%; height: 100%; object-fit: cover; }
    .user-info { flex: 1; min-width: 0; }
    .user-name { font-size: 13px; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .user-role { font-size: 11px; color: var(--text-muted); text-transform: capitalize; }
    .role-badge {
      display: inline-block; font-size: 10px; font-weight: 600;
      padding: 2px 7px; border-radius: 20px;
      text-transform: uppercase; letter-spacing: .5px;
    }
    .role-admin { background: rgba(79,124,255,.2); color: #93b4ff; }
    .role-user  { background: rgba(34,197,94,.15); color: #86efac; }

    /* ── Main Content ── */
    .main {
      margin-left: var(--sidebar-w);
      flex: 1;
      padding: 32px;
      transition: margin-left .25s ease;
      min-width: 0;
      max-width: 100%;
    }
    .main.collapsed { margin-left: 60px; }

    /* ── Topbar ── */
    .topbar {
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      gap: 12px;
      margin-bottom: 28px;
      flex-wrap: wrap;
    }
    .page-title { font-size: 22px; font-weight: 600; letter-spacing: -.3px; }
    .page-sub   { color: var(--text-muted); font-size: 14px; margin-top: 2px; }

    /* ── Stats Grid ── */
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
      gap: 16px;
      margin-bottom: 28px;
    }
    .stat-card {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: var(--radius-lg);
      padding: 20px 22px;
      display: flex;
      align-items: center;
      gap: 16px;
    }
    .stat-icon {
      width: 44px; height: 44px;
      border-radius: 10px;
      display: grid; place-items: center;
      font-size: 20px; flex-shrink: 0;
    }
    .stat-icon.blue   { background: rgba(79,124,255,.15); }
    .stat-icon.green  { background: rgba(34,197,94,.12); }
    .stat-icon.amber  { background: rgba(245,158,11,.12); }
    .stat-icon.purple { background: rgba(168,85,247,.12); }
    .stat-value { font-size: 28px; font-weight: 600; line-height: 1; }
    .stat-label { font-size: 13px; color: var(--text-muted); margin-top: 3px; }

    /* ── Card & Table ── */
    .card {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: var(--radius-lg);
      overflow: hidden;
    }
    .card-header {
      padding: 18px 24px;
      border-bottom: 1px solid var(--border);
      display: flex;
      align-items: center;
      justify-content: space-between;
      flex-wrap: wrap;
      gap: 10px;
    }
    .card-title { font-size: 15px; font-weight: 600; }

    /* All tables scroll horizontally instead of overflowing */
    .table-responsive {
      overflow-x: auto;
      -webkit-overflow-scrolling: touch;
    }
    table { width: 100%; border-collapse: collapse; min-width: 500px; }
    th {
      text-align: left;
      padding: 12px 20px;
      font-size: 12px; font-weight: 600;
      letter-spacing: .5px; text-transform: uppercase;
      color: var(--text-dim);
      background: var(--surface2);
      border-bottom: 1px solid var(--border);
      white-space: nowrap;
    }
    td {
      padding: 13px 20px;
      font-size: 14px;
      border-bottom: 1px solid var(--border);
      color: var(--text-muted);
    }
    td:first-child { color: var(--text); font-weight: 500; }
    tr:last-child td { border-bottom: none; }
    tr:hover td { background: rgba(255,255,255,.02); }

    .badge {
      display: inline-block; font-size: 11px; font-weight: 600;
      padding: 3px 9px; border-radius: 20px; text-transform: capitalize;
      white-space: nowrap;
    }
    .badge-active   { background: rgba(34,197,94,.15);  color: #86efac; }
    .badge-draft    { background: rgba(245,158,11,.15); color: #fcd34d; }
    .badge-archived { background: rgba(100,116,139,.15); color: #94a3b8; }

    .file-type {
      font-family: var(--mono); font-size: 11px;
      background: var(--surface2); border: 1px solid var(--border);
      padding: 2px 7px; border-radius: 5px;
      text-transform: uppercase; color: var(--text-muted);
    }

    .empty-state { text-align: center; padding: 48px 24px; color: var(--text-dim); }
    .empty-state-icon { font-size: 36px; margin-bottom: 12px; }
    .empty-state p { font-size: 14px; }

    /* ── Global modal responsiveness ── */
    .modal-overlay,
    [id$="Modal"]:not(.modal-box):not(.modal),
    #pwModal,
    #editModal {
      padding: 16px !important;
    }

    /* ── Mobile breakpoint ── */
    @media (max-width: 768px) {
      /* Show hamburger, hide sidebar collapse button */
      .sidebar-toggle { display: flex; }
      .sidebar-collapse-btn { display: none; }

      /* Sidebar slides in from left on mobile */
      .sidebar {
        transform: translateX(-100%);
        width: var(--sidebar-w) !important;
      }
      .sidebar.mobile-open { transform: translateX(0); }
      .sidebar.collapsed { width: var(--sidebar-w); transform: translateX(-100%); }

      /* Main content: no left margin, padding to clear hamburger button */
      .main { margin-left: 0 !important; padding: 16px 16px 24px 60px; }

      /* Login card */
      .login-card { padding: 32px 22px; }

      /* Topbar stacks if needed */
      .topbar { margin-bottom: 20px; }
      .page-title { font-size: 19px; }

      /* Stats: 2 columns on mobile */
      .stats-grid { grid-template-columns: repeat(2, 1fr); gap: 12px; }
      .stat-card { padding: 16px; gap: 12px; }
      .stat-value { font-size: 24px; }
      .stat-icon { width: 38px; height: 38px; font-size: 17px; }

      /* Tables: enforce scroll */
      table { min-width: 420px; }
      th, td { padding: 11px 14px; }

      /* Card padding on mobile */
      .card-header { padding: 14px 16px; }

      /* Forms inside cards */
      .card > form,
      .card > div > form { padding: 0 16px 16px; }
    }

    /* ── Very small screens ── */
    @media (max-width: 400px) {
      .main { padding: 12px 12px 20px 56px; }
      .login-card { padding: 24px 16px; }
      .stats-grid { grid-template-columns: 1fr 1fr; gap: 10px; }
      .stat-value { font-size: 20px; }
    }
  </style>
</head>
<body>

<?php if ($page === 'login'): ?>
<!-- ══════════════════════════════
     LOGIN PAGE
══════════════════════════════ -->
<div class="login-wrap">
  <div class="login-card">

    <div class="login-logo">
      <img src="/dms/uploads/profile/DocManager.png" alt="DocManager" style="height:80px;width:auto;display:block;" onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
      <div style="display:none;align-items:center;gap:10px;">
        <div style="width:38px;height:38px;background:var(--accent);border-radius:9px;display:grid;place-items:center;font-size:18px;">📁</div>
        <div style="font-size:20px;font-weight:600;">Doc<span style="color:var(--accent);">Manager</span></div>
      </div>
    </div>

    <h1 class="login-heading">Sign in</h1>
    <p class="login-sub">Access your document management portal</p>

    <?php if ($err = flash('error')): ?>
      <div class="alert alert-error"><?= e($err) ?></div>
    <?php endif; ?>

    <?php if ($ok = flash('success')): ?>
      <div class="alert alert-success"><?= e($ok) ?></div>
    <?php endif; ?>

    <form method="POST" action="<?= BASE_URL ?>/index.php">
      <input type="hidden" name="action" value="login">

      <div class="form-group">
        <label class="form-label">Username</label>
        <input class="form-control"
               type="text"
               name="username"
               required>
      </div>

      <div class="form-group">
        <label class="form-label">Password</label>
        <input class="form-control"
               type="password"
               name="password"
               required>
      </div>

      <button type="submit" class="btn btn-primary">
        Sign In
      </button>
    </form>

    <div class="login-footer">

      <a href="<?= BASE_URL ?>/index.php?page=forgot_password">
        Forgot password?
      </a>

      <br><br>

      <a href="<?= BASE_URL ?>/index.php?page=request-account"
         class="btn btn-ghost"
         style="width:100%;justify-content:center;text-decoration:none;">
         👤 New User Request
      </a>

    </div>

  </div>
</div>

<?php elseif ($page === 'request-account'): ?>

<?php include __DIR__ . '/pages/request-account.php'; ?>

<?php elseif ($page === 'forgot_password'): ?>
<?php
$fpMessage = '';
$fpError   = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fpEmail = trim($_POST['email'] ?? '');
    if (!$fpEmail || !filter_var($fpEmail, FILTER_VALIDATE_EMAIL)) {
        $fpError = 'Please enter a valid email address.';
    } else {
        $db   = getDB();
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND is_active = 1 LIMIT 1");
        $stmt->execute([$fpEmail]);
        $user = $stmt->fetch();
        if ($user) {
            // Check no pending request already exists for this user
            $chk = $db->prepare("SELECT id FROM password_reset WHERE user_id = ? AND is_used = 0");
            $chk->execute([$user['id']]);
            if ($chk->fetch()) {
                $fpMessage = 'A request is already pending. Please contact your admin.';
            } else {
                $db->prepare("
                    INSERT INTO password_reset (user_id, token_hash, is_used, expires_at, created_at)
                    VALUES (?, '', 0, DATE_ADD(NOW(), INTERVAL 24 HOUR), NOW())
                ")->execute([$user['id']]);
                $fpMessage = 'Your request has been submitted. Please contact your admin to get your new password.';
            }
        } else {
            // Don't reveal whether email exists
            $fpMessage = 'Your request has been submitted. Please contact your admin to get your new password.';
        }
    }
}
?>
<div class="login-wrap">
  <div class="login-card">
    <div class="login-logo">
      <img src="/dms/uploads/profile/DocManager.png" alt="DocManager" style="height:80px;width:auto;display:block;" onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
      <div style="display:none;align-items:center;gap:10px;">
        <div style="width:38px;height:38px;background:var(--accent);border-radius:9px;display:grid;place-items:center;font-size:18px;">📁</div>
        <div style="font-size:20px;font-weight:600;">Doc<span style="color:var(--accent);">Manager</span></div>
      </div>
    </div>
    <h1 class="login-heading">Forgot password</h1>
    <p class="login-sub">Submit your email and an admin will reset your password for you.</p>
    <?php if ($fpError): ?>
      <div class="alert alert-error"><?= e($fpError) ?></div>
    <?php endif; ?>
    <?php if ($fpMessage): ?>
      <div class="alert alert-success"><?= e($fpMessage) ?></div>
    <?php else: ?>
    <form method="POST" action="<?= BASE_URL ?>/index.php?page=forgot_password">
      <div class="form-group">
        <label class="form-label">Email address</label>
        <input class="form-control" type="email" name="email"
               placeholder="you@company.com" required autofocus>
      </div>
      <button type="submit" class="btn btn-primary">Submit Request</button>
    </form>
    <?php endif; ?>
    <div class="login-footer" style="margin-top:20px;">
      <a href="<?= BASE_URL ?>/index.php?page=login">← Back to Sign In</a>
    </div>
  </div>
</div>

<?php else: ?>
<!-- ══════════════════════════════
     APP SHELL (authenticated)
══════════════════════════════ -->

<!-- Mobile hamburger toggle -->
<button class="sidebar-toggle" id="sidebarToggle" aria-label="Open navigation">☰</button>

<!-- Mobile overlay -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<div class="app">

  <!-- Sidebar -->
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-logo">
      <div style="display:flex;align-items:center;gap:10px;" class="sidebar-logo-text">
        <img src="/dms/uploads/profile/DocManager.png" alt="DocManager" style="height:38px;width:auto;display:block;" onerror="this.style.display='none'">
        <div style="font-size:15px;font-weight:600;white-space:nowrap;">Doc<span style="color:var(--accent);">Manager</span></div>
      </div>
      <!-- Collapsed: show only icon -->
      <div style="display:none;" class="sidebar-logo-icon">
        <div style="width:32px;height:32px;background:var(--accent);border-radius:8px;display:grid;place-items:center;font-size:15px;">📁</div>
      </div>
      <button class="sidebar-collapse-btn" id="collapseBtn" title="Collapse sidebar">◀</button>
    </div>

    <nav class="sidebar-nav">
      <div class="nav-section-label">Main</div>
      <a href="?page=dashboard" class="nav-item <?= $page === 'dashboard' ? 'active' : '' ?>" title="Dashboard">
        <span class="nav-icon">⊞</span><span class="nav-label">Dashboard</span>
      </a>
      <a href="?page=documents" class="nav-item <?= $page === 'documents' ? 'active' : '' ?>" title="Documents">
        <span class="nav-icon">📄</span><span class="nav-label">Documents</span>
      </a>
      <a href="?page=upload" class="nav-item <?= $page === 'upload' ? 'active' : '' ?>" title="Upload">
        <span class="nav-icon">⬆</span><span class="nav-label">Upload</span>
      </a>
      <?php if($isAdmin): ?>
      <a href="?page=document-password-request" class="nav-item <?= $page === 'document-password-request' ? 'active' : '' ?>" title="File Password Requests">
        <span class="nav-icon">🔐</span><span class="nav-label">File Password Requests</span>
      </a>
<?php endif; ?>
      <?php if ($isAdmin): ?>
      <div class="nav-section-label">Admin</div>
      <a href="?page=users" class="nav-item <?= $page === 'users' ? 'active' : '' ?>" title="Users">
        <span class="nav-icon">👥</span><span class="nav-label">Users</span>
      </a>
      <?php
        $pendingCount = 0;
        try {
            $pendingCount = (int)getDB()->query("SELECT COUNT(*) FROM user_request WHERE status='pending'")->fetchColumn();
        } catch (\Exception $e) {}
      ?>
      <a href="?page=account-requests" class="nav-item <?= $page === 'account-requests' ? 'active' : '' ?>"
         style="justify-content:space-between;" title="Account Requests">
        <span style="display:flex;align-items:center;gap:10px;">
          <span class="nav-icon">📥</span><span class="nav-label">Account Requests</span>
        </span>
        <?php if ($pendingCount > 0): ?>
          <span class="nav-badge" style="background:var(--danger);color:#fff;font-size:10px;font-weight:700;padding:2px 7px;border-radius:20px;min-width:20px;text-align:center;"><?= $pendingCount ?></span>
        <?php endif; ?>
      </a>
      <?php
        $pendingPwCount = 0;
        try {
            $pendingPwCount = (int)getDB()->query("SELECT COUNT(*) FROM password_reset WHERE is_used = 0")->fetchColumn();
        } catch (\Exception $e) {}
      ?>
      <a href="?page=password-requests" class="nav-item <?= $page === 'password-requests' ? 'active' : '' ?>"
         style="justify-content:space-between;" title="Password Requests">
        <span style="display:flex;align-items:center;gap:10px;">
          <span class="nav-icon">🔑</span><span class="nav-label">Password Requests</span>
        </span>
        <?php if ($pendingPwCount > 0): ?>
          <span class="nav-badge" style="background:var(--warning);color:#000;font-size:10px;font-weight:700;padding:2px 7px;border-radius:20px;min-width:20px;text-align:center;"><?= $pendingPwCount ?></span>
        <?php endif; ?>
      </a>
      <a href="?page=categories" class="nav-item <?= $page === 'categories' ? 'active' : '' ?>" title="Categories">
        <span class="nav-icon">🏷</span><span class="nav-label">Categories</span>
      </a>
      <a href="?page=activity" class="nav-item <?= $page === 'activity' ? 'active' : '' ?>" title="Activity Log">
        <span class="nav-icon">📋</span><span class="nav-label">Activity Log</span>
      </a>
      <?php endif; ?>
    </nav>

    <div class="sidebar-user">
      <div class="user-avatar">
        <?php if (!empty($authUser['image'])): ?>
          <img src="<?= e($authUser['image']) ?>" alt="">
        <?php else: ?>
          <?= strtoupper(substr($authUser['employee_name'] ?? 'U', 0, 1)) ?>
        <?php endif; ?>
      </div>
      <div class="user-info">
        <div class="user-name"><?= e($authUser['employee_name'] ?? '') ?></div>
        <span class="role-badge <?= $isAdmin ? 'role-admin' : 'role-user' ?>">
          <?= e($authUser['role'] ?? '') ?>
        </span>
      </div>
      <form method="POST" style="margin:0" class="sidebar-logout">
        <input type="hidden" name="action" value="logout">
        <button type="submit" class="btn btn-sm btn-ghost" title="Sign out">↩</button>
      </form>
    </div>
  </aside>

  <!-- Main -->
  <main class="main" id="mainContent">

    <?php if ($ok = flash('success')): ?>
      <div class="alert alert-success"><?= e($ok) ?></div>
    <?php endif; ?>
    <?php if ($err = flash('error')): ?>
      <div class="alert alert-error"><?= e($err) ?></div>
    <?php endif; ?>

    <?php if ($page === 'dashboard'): ?>
    <!-- Dashboard -->
    <div class="topbar">
      <div>
        <div class="page-title">Dashboard</div>
        <div class="page-sub">Welcome back, <?= e($authUser['employee_name'] ?? '') ?></div>
      </div>
    </div>

    <?php if ($isAdmin): ?>
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon blue">📄</div>
        <div><div class="stat-value"><?= (int)($stats['total_docs'] ?? 0) ?></div><div class="stat-label">Total Documents</div></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon green">👥</div>
        <div><div class="stat-value"><?= (int)($stats['total_users'] ?? 0) ?></div><div class="stat-label">Active Users</div></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon amber">🏷</div>
        <div><div class="stat-value"><?= (int)($stats['total_cats'] ?? 0) ?></div><div class="stat-label">Categories</div></div>
      </div>
    </div>
    <?php else: ?>
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon blue">📄</div>
        <div><div class="stat-value"><?= (int)($stats['total_docs'] ?? 0) ?></div><div class="stat-label">Total Documents</div></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon green">📤</div>
        <div><div class="stat-value"><?= (int)($stats['my_docs'] ?? 0) ?></div><div class="stat-label">My Uploads</div></div>
      </div>
    </div>
    <?php endif; ?>

    <div class="card">
      <div class="card-header">
        <div class="card-title">Recent Documents</div>
        <a href="?page=documents" class="btn btn-sm btn-ghost">View all</a>
      </div>
      <?php if (!empty($stats['recent_docs'])): ?>
      <div class="table-responsive">
      <table>
        <thead>
          <tr>
            <th>Title</th>
            <th>Type</th>
            <th>Uploaded by</th>
            <th>Status</th>
            <th>Date</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($stats['recent_docs'] as $doc): ?>
          <tr>
            <td><?= e($doc['title']) ?></td>
            <td><span class="file-type"><?= e($doc['file_type']) ?></span></td>
            <td><?= e($doc['uploader_name']) ?></td>
            <td><span class="badge badge-<?= e($doc['status']) ?>"><?= e($doc['status']) ?></span></td>
            <td><?= date('d M Y', strtotime($doc['created_at'])) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      </div>
      <?php else: ?>
      <div class="empty-state">
        <div class="empty-state-icon">📂</div>
        <p>No documents yet. <a href="?page=upload">Upload your first document</a></p>
      </div>
      <?php endif; ?>
    </div>

    <?php elseif ($page === 'password-requests'): ?>

<?php
// ── Password Requests (Admin) ─────────────────────
requireAdmin();
$db = getDB();

$requests = $db->query("
    SELECT pr.*,
           u.username,
           u.email,
           e.name    AS emp_name,
           e.department
    FROM password_reset pr
    JOIN users     u ON u.id  = pr.user_id
    JOIN employee  e ON e.id  = u.employee_id
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
      <div style="display:flex;gap:10px;margin-top:8px;">
        <button type="submit" class="btn btn-primary" style="flex:1;margin-top:0;">Save &amp; Notify</button>
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
  <div class="alert alert-success" style="margin-bottom:20px;"><?= e($ok) ?></div>
<?php endif; ?>
<?php if ($err = flash('error')): ?>
  <div class="alert alert-error" style="margin-bottom:20px;"><?= e($err) ?></div>
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
              )">
              🔑 Reset
            </button>
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

    <?php else: ?>

<?php

$file = __DIR__ . '/pages/' . $page . '.php';

if(file_exists($file)){
    include $file;
}else{
?>

<div class="card" style="padding:50px">
    <h2>Page Not Found</h2>
</div>

<?php
}
?>

<?php endif; ?>

  </main>
</div>
<?php endif; ?>

<script>
(function () {
  const sidebar   = document.getElementById('sidebar');
  const main      = document.getElementById('mainContent');
  const overlay   = document.getElementById('sidebarOverlay');
  const toggleBtn = document.getElementById('sidebarToggle');
  const collapseBtn = document.getElementById('collapseBtn');

  if (!sidebar) return; // not on app shell pages

  const STORAGE_KEY = 'sidebar_collapsed';
  const isMobile = () => window.innerWidth <= 768;

  // ── Desktop collapse / expand ──
  function setCollapsed(collapsed) {
    sidebar.classList.toggle('collapsed', collapsed);
    if (main) main.classList.toggle('collapsed', collapsed);
    try { localStorage.setItem(STORAGE_KEY, collapsed ? '1' : '0'); } catch(e) {}
  }

  // Restore saved state on desktop
  if (!isMobile()) {
    try {
      if (localStorage.getItem(STORAGE_KEY) === '1') setCollapsed(true);
    } catch(e) {}
  }

  if (collapseBtn) {
    collapseBtn.addEventListener('click', function () {
      setCollapsed(!sidebar.classList.contains('collapsed'));
    });
  }

  // ── Mobile open / close ──
  function openMobile() {
    sidebar.classList.add('mobile-open');
    overlay.classList.add('open');
    if (toggleBtn) toggleBtn.textContent = '✕';
    document.body.style.overflow = 'hidden';
  }

  function closeMobile() {
    sidebar.classList.remove('mobile-open');
    overlay.classList.remove('open');
    if (toggleBtn) toggleBtn.textContent = '☰';
    document.body.style.overflow = '';
  }

  if (toggleBtn) {
    toggleBtn.addEventListener('click', function () {
      if (sidebar.classList.contains('mobile-open')) {
        closeMobile();
      } else {
        openMobile();
      }
    });
  }

  if (overlay) {
    overlay.addEventListener('click', closeMobile);
  }

  // Close mobile nav when a link is clicked
  sidebar.querySelectorAll('.nav-item').forEach(function (link) {
    link.addEventListener('click', function () {
      if (isMobile()) closeMobile();
    });
  });

  // Re-sync on resize
  window.addEventListener('resize', function () {
    if (!isMobile()) {
      closeMobile(); // clean up mobile state
      document.body.style.overflow = '';
    }
  });
})();
</script>

</body>
</html>