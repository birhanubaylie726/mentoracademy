<?php
require_once './db.php';
require_once './auth.php';
verifyAccess(['admin']);

// =========================================================================
// 🛠️ SELF-HEALING SCHEMATIC DATABASE HOT-PATCH SYSTEM
// =========================================================================
try {
    // 1. Automatically patch and construct user_assignments table if absent
    $pdo->exec("CREATE TABLE IF NOT EXISTS `user_assignments` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `grade_level` VARCHAR(50) NOT NULL,
        `section` VARCHAR(50) NOT NULL,
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // 2. Automatically patch and construct user_documents table if absent
    $pdo->exec("CREATE TABLE IF NOT EXISTS `user_documents` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `file_path` VARCHAR(255) NOT NULL,
        `file_name` VARCHAR(255) NOT NULL,
        `uploaded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

} catch (PDOException $e) {
    die("🚨 Micro-Migration Pipeline Structural Failure: " . $e->getMessage());
}

// Dynamically determine the context segment role target
$target_role = (isset($_GET['role']) && $_GET['role'] === 'teacher') ? 'teacher' : 'student';
$display_title = ucfirst($target_role) . 's';

$success_msg = '';
$error_msg = '';

$upload_directory = './uploads/documents/';
if (!is_dir($upload_directory)) {
    mkdir($upload_directory, 0777, true);
}

// =========================================================================
// 📥 PIPELINE CONTROLLER: DATA MUTATION PROCESSING
// =========================================================================

// 1. OPERATION: REGISTER NEW PROFILE DATA LOG
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_user') {
    $fullname      = trim($_POST['fullname']);
    $username      = trim($_POST['username']);
    $email         = trim($_POST['email']);
    $password      = trim($_POST['password']); 
    $enrolled_date = !empty($_POST['enrolled_date']) ? $_POST['enrolled_date'] : null;
    
    $graduated_program       = ($target_role === 'teacher') ? trim($_POST['graduated_program']) : null;
    $current_teaching_subject = ($target_role === 'teacher') ? trim($_POST['current_teaching_subject']) : null;

    if (!empty($fullname) && !empty($username) && !empty($email) && !empty($password)) {
        try {
            $pdo->beginTransaction();

            $chk = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ? OR email = ?");
            $chk->execute([$username, $email]);
            if ($chk->fetchColumn() > 0) {
                $error_msg = "❌ Error: Username identity string or Email address already exists inside portal system records.";
                $pdo->rollBack();
            } else {
                $ins = $pdo->prepare("INSERT INTO users (fullname, username, email, password, role, graduated_program, current_teaching_subject, enrolled_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $ins->execute([$fullname, $username, $email, $password, $target_role, $graduated_program, $current_teaching_subject, $enrolled_date]);
                $new_user_id = $pdo->lastInsertId();

                // Process multi-assignment allocation matrices (Grades & Sections)
                if (isset($_POST['assignments']) && is_array($_POST['assignments'])) {
                    $insAssign = $pdo->prepare("INSERT INTO user_assignments (user_id, grade_level, section) VALUES (?, ?, ?)");
                    foreach ($_POST['assignments'] as $assign) {
                        if (!empty($assign['grade_level']) && !empty($assign['section'])) {
                            $insAssign->execute([$new_user_id, trim($assign['grade_level']), trim($assign['section'])]);
                        }
                    }
                }

                // Process attached multi-file folder collections safely
                if (isset($_FILES['uploaded_documents']) && is_array($_FILES['uploaded_documents']['name'])) {
                    $file_count = count($_FILES['uploaded_documents']['name']);
                    for ($i = 0; $i < $file_count; $i++) {
                        if ($_FILES['uploaded_documents']['error'][$i] === UPLOAD_ERR_OK) {
                            $tmp_name = $_FILES['uploaded_documents']['tmp_name'][$i];
                            $orig_name = basename($_FILES['uploaded_documents']['name'][$i]);
                            $extension = strtolower(pathinfo($orig_name, PATHINFO_EXTENSION));
                            
                            $secure_filename = "doc_" . uniqid() . "_" . time() . "_" . $i . "." . $extension;
                            $destination_target = $upload_directory . $secure_filename;

                            if (move_uploaded_file($tmp_name, $destination_target)) {
                                $insFile = $pdo->prepare("INSERT INTO user_documents (user_id, file_path, file_name) VALUES (?, ?, ?)");
                                $insFile->execute([$new_user_id, $destination_target, $orig_name]);
                            }
                        }
                    }
                }

                $pdo->commit();
                $success_msg = "✅ Success: Multi-tier account indexes generated seamlessly for $target_role profile entries.";
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            $error_msg = "🚨 Structural execution failure: " . $e->getMessage();
        }
    } else {
        $error_msg = "⚠️ Halted: Incomplete form properties validation matrix detected.";
    }
}

// 2. OPERATION: EXECUTE SYNCHRONIZED MATRIX UPDATE MODIFICATIONS (ACTIVE INTERACTIVE EDIT)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_user') {
    $user_id       = intval($_POST['user_id']);
    $fullname      = trim($_POST['fullname']);
    $username      = trim($_POST['username']);
    $email         = trim($_POST['email']);
    $password      = trim($_POST['password']);
    $enrolled_date = !empty($_POST['enrolled_date']) ? $_POST['enrolled_date'] : null;
    
    $graduated_program       = ($target_role === 'teacher') ? trim($_POST['graduated_program']) : null;
    $current_teaching_subject = ($target_role === 'teacher') ? trim($_POST['current_teaching_subject']) : null;

    if (!empty($fullname) && !empty($username) && !empty($email) && !empty($password)) {
        try {
            $pdo->beginTransaction();

            $upd = $pdo->prepare("UPDATE users SET fullname = ?, username = ?, email = ?, password = ?, graduated_program = ?, current_teaching_subject = ?, enrolled_date = ? WHERE id = ? AND role = ?");
            $upd->execute([$fullname, $username, $email, $password, $graduated_program, $current_teaching_subject, $enrolled_date, $user_id, $target_role]);
            
            // Wipe clean historical placements tracking logs before storing modified arrays
            $delAssign = $pdo->prepare("DELETE FROM user_assignments WHERE user_id = ?");
            $delAssign->execute([$user_id]);

            if (isset($_POST['assignments']) && is_array($_POST['assignments'])) {
                $insAssign = $pdo->prepare("INSERT INTO user_assignments (user_id, grade_level, section) VALUES (?, ?, ?)");
                foreach ($_POST['assignments'] as $assign) {
                    if (!empty($assign['grade_level']) && !empty($assign['section'])) {
                        $insAssign->execute([$user_id, trim($assign['grade_level']), trim($assign['section'])]);
                    }
                }
            }

            // Upload or append extra documents smoothly
            if (isset($_FILES['uploaded_documents']) && is_array($_FILES['uploaded_documents']['name'])) {
                $file_count = count($_FILES['uploaded_documents']['name']);
                for ($i = 0; $i < $file_count; $i++) {
                    if ($_FILES['uploaded_documents']['error'][$i] === UPLOAD_ERR_OK) {
                        $tmp_name = $_FILES['uploaded_documents']['tmp_name'][$i];
                        $orig_name = basename($_FILES['uploaded_documents']['name'][$i]);
                        $extension = strtolower(pathinfo($orig_name, PATHINFO_EXTENSION));
                        
                        $secure_filename = "doc_" . uniqid() . "_" . time() . "_" . $i . "." . $extension;
                        $destination_target = $upload_directory . $secure_filename;

                        if (move_uploaded_file($tmp_name, $destination_target)) {
                            $insFile = $pdo->prepare("INSERT INTO user_documents (user_id, file_path, file_name) VALUES (?, ?, ?)");
                            $insFile->execute([$user_id, $destination_target, $orig_name]);
                        }
                    }
                }
            }

            $pdo->commit();
            $success_msg = "✅ Success: Identity configuration parameters updated correctly.";
        } catch (Exception $e) {
            $pdo->rollBack();
            $error_msg = "🚨 Operational Exception updating profile records: " . $e->getMessage();
        }
    } else {
        $error_msg = "⚠️ Configuration error: Form validation fields can never pass empty properties.";
    }
}

// 3. OPERATION: PURGE DATA ALIGNMENT RECORD
if (isset($_GET['delete_id'])) {
    $del_id = intval($_GET['delete_id']);
    try {
        $filesQuery = $pdo->prepare("SELECT file_path FROM user_documents WHERE user_id = ?");
        $filesQuery->execute([$del_id]);
        $linked_files = $filesQuery->fetchAll();

        $del = $pdo->prepare("DELETE FROM users WHERE id = ? AND role = ?");
        $del->execute([$del_id, $target_role]);

        foreach ($linked_files as $f) {
            if (!empty($f['file_path']) && file_exists($f['file_path'])) {
                unlink($f['file_path']);
            }
        }
        $success_msg = "🗑️ Success: Target record profile and associated repository files deleted.";
    } catch (PDOException $e) {
        $error_msg = "🚨 Exception caught during transaction deletion sequence context: " . $e->getMessage();
    }
}

// Map user data collection configurations dynamically
$stmt = $pdo->prepare("SELECT * FROM users WHERE role = ? ORDER BY id DESC");
$stmt->execute([$target_role]);
$user_records = $stmt->fetchAll();

// Dynamic extraction map configurations
$all_documents = [];
$all_assignments = [];
if (!empty($user_records)) {
    $docs = $pdo->query("SELECT * FROM user_documents")->fetchAll();
    foreach ($docs as $d) { $all_documents[$d['user_id']][] = $d; }

    $assigns = $pdo->query("SELECT * FROM user_assignments ORDER BY grade_level ASC, section ASC")->fetchAll();
    foreach ($assigns as $a) { $all_assignments[$a['user_id']][] = $a; }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Portal <?= $display_title ?> Suite</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background-color: #f1f5f9; font-family: system-ui, -apple-system, sans-serif; }
        .sidebar { min-width: 260px; max-width: 260px; background: #0f172a; min-height: 100vh; color: #fff; position: sticky; top: 0; }
        .sidebar .nav-link { color: #94a3b8; font-weight: 500; }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { color: #fff; background: #1e293b; border-radius: 6px; }
        
        /* 📜 COMPACT CONGESTED REPOSITORY CONTAINER (INDEPENDENT VERTICAL & HORIZONTAL TRACKING SCROLLBARS) */
        .scrolled-repository-box {
            max-height: 110px;
            max-width: 240px;
            overflow-y: auto;   /* Active clean vertical track bars */
            overflow-x: auto;   /* Active clean horizontal track bars */
            white-space: nowrap;/* Stops the badge elements arrays from snapping downward */
            padding: 6px;
            border: 1px solid #e2e8f0;
            background-color: #f8fafc;
            border-radius: 6px;
        }
        /* Overrides standard styling behaviors to output streamlined elements */
        .scrolled-repository-box::-webkit-scrollbar { width: 5px; height: 5px; }
        .scrolled-repository-box::-webkit-scrollbar-track { background: #f1f5f9; }
        .scrolled-repository-box::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
        .scrolled-repository-box::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
        
        .dynamic-row-entry { background-color: #fdfdfd; transition: all 0.2s; }
        .dynamic-row-entry:hover { background-color: #f4f4f5; }
    </style>
</head>
<body>
<div class="d-flex">
    
    <div class="sidebar p-3 d-flex flex-column">
        <h4 class="text-center py-3 border-bottom border-secondary mb-4 fw-bold text-white"><i class="bi bi-shield-lock-fill me-2 text-primary"></i>Portal System</h4>
        <ul class="nav nav-pills flex-column mb-auto gap-1">
            <li><a href="admin_dashboard.php" class="nav-link"><i class="bi bi-speedometer2 me-2"></i> Dashboard</a></li>
            <li><a href="manage_users.php?role=teacher" class="nav-link <?= $target_role === 'teacher' ? 'active' : '' ?>"><i class="bi bi-person-badge me-2"></i> Manage Teachers</a></li>
            <li><a href="manage_users.php?role=student" class="nav-link <?= $target_role === 'student' ? 'active' : '' ?>"><i class="bi bi-people me-2"></i> Manage Students</a></li> 
            <li><a href="manage_subjects.php" class="nav-link"><i class="bi bi-book me-2"></i> Manage Subjects</a></li> 
        </ul>
        <hr class="border-secondary">
        <a href="index.php" class="btn btn-sm btn-danger w-100 fw-bold"><i class="bi bi-box-arrow-left me-2"></i> Log Out Suite</a>
    </div>

    <div class="w-100 flex-grow-1">
        <nav class="navbar navbar-white bg-white border-bottom px-4 py-3 sticky-top shadow-xs">
            <span class="navbar-brand mb-0 h5 text-dark fw-bold"><i class="bi bi-sliders me-2 text-primary"></i>Admin: <?= $display_title ?> Registry Console</span>
        </nav>

        <div class="container-fluid p-4">
            <?php if($success_msg): ?> <div class="alert alert-success border-0 shadow-sm fw-medium"><?= $success_msg ?></div> <?php endif; ?>
            <?php if($error_msg): ?> <div class="alert alert-danger border-0 shadow-sm fw-medium"><?= $error_msg ?></div> <?php endif; ?>

            <div class="card border-0 shadow-sm p-4 bg-white mb-4">
                <h5 class="fw-bold text-dark mb-3 border-bottom pb-2"><i class="bi bi-person-plus-fill text-primary me-2"></i>Add New Record Entry</h5>
                <form action="" method="POST" enctype="multipart/form-data" autocomplete="off">
                    <input type="hidden" name="action" value="add_user">
                    
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label small fw-bold text-secondary mb-1">Full Legal Profile Name</label>
                            <input type="text" name="fullname" class="form-control form-control-sm" required placeholder="e.g. Abebe Kebede">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold text-secondary mb-1">Unique Account Username</label>
                            <input type="text" name="username" class="form-control form-control-sm" required placeholder="e.g. abebe_k">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold text-secondary mb-1">Email Endpoint Gateway Address</label>
                            <input type="email" name="email" class="form-control form-control-sm" required placeholder="e.g. abebe@academy.org">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold text-secondary mb-1">Account Password</label>
                            <input type="text" name="password" class="form-control form-control-sm" required value="<?= $target_role ?>123">
                        </div>

                        <?php if ($target_role === 'teacher'): ?>
                            <div class="col-md-4">
                                <label class="form-label small fw-bold text-dark mb-1">Graduated Degree/Major Program</label>
                                <input type="text" name="graduated_program" class="form-control form-control-sm" placeholder="e.g. M.Sc in Pedagogy" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-bold text-dark mb-1">Current Primary Teaching Subject</label>
                                <input type="text" name="current_teaching_subject" class="form-control form-control-sm" placeholder="e.g. Physics / Chemistry" required>
                            </div>
                        <?php endif; ?>

                        <div class="col-md-4">
                            <label class="form-label small fw-bold text-secondary mb-1">Official Enrollment / Registration Date</label>
                            <input type="date" name="enrolled_date" class="form-control form-control-sm" required value="<?= date('Y-m-d') ?>">
                        </div>

                        <div class="col-12 border rounded p-3 bg-light-subtle">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="small fw-bold text-dark"><i class="bi bi-grid-3x3-gap-fill text-secondary me-1"></i>Assign Academic Placements (Multiple Grades & Sections Supported)</span>
                                <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-2 fw-bold" style="font-size: 0.75rem;" onclick="appendAssignmentRow('creation_assignment_box')"><i class="bi bi-plus-circle"></i> Add Allocation</button>
                            </div>
                            <div id="creation_assignment_box" class="row g-2">
                                <div class="col-12 d-flex gap-2 alignment-unit mb-1">
                                    <select name="assignments[0][grade_level]" class="form-select form-select-sm w-50" required>
                                        <option value="" disabled selected>-- Choose Grade --</option>
                                        <option value="Grade 9">Grade 9</option>
                                        <option value="Grade 10">Grade 10</option>
                                        <option value="Grade 11">Grade 11</option>
                                        <option value="Grade 12">Grade 12</option>
                                    </select>
                                    <input type="text" name="assignments[0][section]" class="form-control form-control-sm w-50" placeholder="Section Code (e.g. Section-A)" required>
                                    <button type="button" class="btn btn-sm btn-light border" onclick="this.parentElement.remove()"><i class="bi bi-x-lg text-danger"></i></button>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-8">
                            <label class="form-label small fw-bold text-secondary mb-1"><i class="bi bi-file-earmark-arrow-up text-success me-1"></i>Attach Verification Folders/Documents (Upload Multiple Simultaneously)</label>
                            <input type="file" name="uploaded_documents[]" class="form-control form-control-sm" accept=".pdf,.doc,.docx,.jpg,.png" multiple>
                            <span class="text-muted d-block" style="font-size:0.72rem;">Hold down <kbd>Ctrl</kbd> inside your device explorer file array map to execute simultaneous updates.</span>
                        </div>

                        <div class="col-md-4 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary btn-sm w-100 fw-bold py-2 shadow-xs"><i class="bi bi-plus-circle-fill me-1"></i> Process Profile Index Execution</button>
                        </div>
                    </div>
                </form>
            </div>

            <div class="card border-0 shadow-sm p-4 bg-white">
                <h5 class="fw-bold text-dark mb-3"><i class="bi bi-list-columns-reverse text-success me-2"></i>Registered System Indexes (<?= count($user_records) ?> Tracked Rows)</h5>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 text-dark">
                        <thead class="table-light text-secondary small">
                            <tr>
                                <th>Tracking Code</th>
                                <th>Basic Identity Information</th>
                                <th>Credential Configurations</th>
                                <th>Assigned Grades & Sections</th>
                                <?php if($target_role === 'teacher'): ?> <th>Academic Profile</th> <?php endif; ?>
                                <th>Repository Documents Vault</th>
                                <th class="text-center">Lifecycle Operations</th>
                            </tr>
                        </thead>
                        <tbody class="small">
                            <?php if(empty($user_records)): ?>
                                <tr><td colspan="7" class="text-center text-muted py-4">No logged profiles match current application scope properties.</td></tr>
                            <?php else: ?>
                                <?php foreach($user_records as $user): ?>
                                    <tr class="dynamic-row-entry">
                                        <td><code>#USR-<?= $user['id'] ?></code></td>
                                        <td>
                                            <div class="fw-bold text-dark text-nowrap"><?= htmlspecialchars($user['fullname']) ?></div>
                                            <small class="text-muted"><i class="bi bi-envelope me-1"></i><?= htmlspecialchars($user['email']) ?></small>
                                        </td>
                                        <td>
                                            <div>User: <code><?= htmlspecialchars($user['username']) ?></code></div>
                                            <div>Pass: <code class="text-dark bg-light px-1 border rounded"><?= htmlspecialchars($user['password']) ?></code></div>
                                        </td>
                                        <td>
                                            <div class="scrolled-repository-box">
                                                <?php if (!empty($all_assignments[$user['id']])): ?>
                                                    <div class="d-flex flex-column gap-1">
                                                        <?php foreach ($all_assignments[$user['id']] as $asg): ?>
                                                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1 align-self-start text-nowrap">
                                                                <i class="bi bi-bookmark-fill me-1"></i><?= htmlspecialchars($asg['grade_level']) ?> &raquo; Class <?= htmlspecialchars($asg['section']) ?>
                                                            </span>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="text-muted italic small"><i class="bi bi-exclamation-triangle"></i> Zero Placements</span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="mt-1 text-muted" style="font-size: 0.7rem;"><i class="bi bi-calendar me-1"></i>Reg: <?= htmlspecialchars($user['enrolled_date'] ?? 'N/A') ?></div>
                                        </td>
                                        
                                        <?php if($target_role === 'teacher'): ?>
                                            <td>
                                                <div class="text-truncate fw-medium" style="max-width: 140px;" title="<?= htmlspecialchars($user['graduated_program']) ?>">Major: <?= htmlspecialchars($user['graduated_program'] ?? 'N/A') ?></div>
                                                <div>Subject: <span class="badge bg-secondary"><?= htmlspecialchars($user['current_teaching_subject'] ?? 'N/A') ?></span></div>
                                            </td>
                                        <?php endif; ?>

                                        <td>
                                            <div class="scrolled-repository-box">
                                                <?php if (!empty($all_documents[$user['id']])): ?>
                                                    <div class="d-flex flex-column gap-1">
                                                        <?php foreach ($all_documents[$user['id']] as $index => $doc): ?>
                                                            <a href="<?= htmlspecialchars($doc['file_path']) ?>" target="_blank" class="badge bg-light text-dark border d-inline-block text-decoration-none text-truncate text-nowrap" style="max-width: 210px;" title="View Data Matrix File: <?= htmlspecialchars($doc['file_name']) ?>">
                                                                <i class="bi bi-file-earmark-text text-danger me-1"></i>Doc <?= $index + 1 ?>: <?= htmlspecialchars($doc['file_name']) ?>
                                                            </a>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="text-muted italic small"><i class="bi bi-folder-x"></i> Zero Files Found</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>

                                        <td class="text-center">
                                            <div class="btn-group">
                                                <button type="button" class="btn btn-xs btn-outline-warning text-dark fw-bold" 
                                                        onclick='launchUpdateModal(<?= json_encode($user) ?>, <?= json_encode($all_assignments[$user['id']] ?? []) ?>)'>
                                                    <i class="bi bi-pencil-square"></i> Edit
                                                </button>
                                                <a href="manage_users.php?role=<?= $target_role ?>&delete_id=<?= $user['id'] ?>" 
                                                   class="btn btn-xs btn-outline-danger fw-bold" 
                                                   onclick="return confirm('🚨 WARN: Deleting profiles wipes out all associated document vault logs from system directories entirely. Proceed?');">
                                                    <i class="bi bi-trash"></i> Delete
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</div>

<div class="modal fade" id="updateUserModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <form class="modal-content" method="POST" action="" enctype="multipart/form-data">
      <div class="modal-header bg-dark text-white py-3">
        <h5 class="modal-title fw-bold"><i class="bi bi-person-gear me-2"></i>Modify Configuration Profile Framework Parameters</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body bg-white p-4">
        <input type="hidden" name="action" value="update_user">
        <input type="hidden" name="user_id" id="update_user_id">
        
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label small fw-bold text-secondary">Full Profile Name Mapping</label>
                <input type="text" name="fullname" id="update_fullname" class="form-control form-control-sm" required>
            </div>
            <div class="col-md-6">
                <label class="form-label small fw-bold text-secondary">Unique Username String Identity</label>
                <input type="text" name="username" id="update_username" class="form-control form-control-sm" required>
            </div>
            <div class="col-md-6">
                <label class="form-label small fw-bold text-secondary">Email Target Gateway Endpoint</label>
                <input type="email" name="email" id="update_email" class="form-control form-control-sm" required>
            </div>
            <div class="col-md-6">
                <label class="form-label small fw-bold text-secondary">System Access Password</label>
                <input type="text" name="password" id="update_password" class="form-control form-control-sm" required>
            </div>
            <div class="col-md-12">
                <label class="form-label small fw-bold text-secondary">Official Enrollment Registration Date</label>
                <input type="date" name="enrolled_date" id="update_enrolled_date" class="form-control form-control-sm" required>
            </div>

            <?php if ($target_role === 'teacher'): ?>
                <div class="col-12 p-3 bg-light rounded border my-1">
                    <div class="fw-bold small text-dark mb-2"><i class="bi bi-mortarboard-fill text-secondary me-1"></i>Educator Academic Matrix Details</div>
                    <div class="row g-2">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-secondary">Graduated Program Track</label>
                            <input type="text" name="graduated_program" id="update_graduated_program" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-secondary">Assigned Subject Stream Focus</label>
                            <input type="text" name="current_teaching_subject" id="update_current_teaching_subject" class="form-control form-control-sm">
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="col-12 border rounded p-3 bg-light-subtle">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="small fw-bold text-dark"><i class="bi bi-grid-3x3-gap-fill text-primary me-1"></i>Configure Active Structural Grade & Section Assignments</span>
                    <button type="button" class="btn btn-xs btn-outline-primary py-0 px-2 fw-bold" style="font-size:0.75rem;" onclick="appendAssignmentRow('modal_assignment_box')"><i class="bi bi-plus-circle"></i> Append Row</button>
                </div>
                <div id="modal_assignment_box" class="row g-2">
                    </div>
            </div>

            <div class="col-12">
                <label class="form-label small fw-bold text-dark mb-1"><i class="bi bi-file-earmark-plus-fill text-primary"></i> Upload/Append Additional Repository Verification Files</label>
                <input type="file" name="uploaded_documents[]" class="form-control form-control-sm" accept=".pdf,.doc,.docx,.jpg,.png" multiple>
                <div class="text-muted mt-1" style="font-size:0.7rem;">Leave blank to preserve historical document vault files without modification.</div>
            </div>
        </div>
      </div>
      <div class="modal-footer bg-light border-top">
        <button type="button" class="btn btn-secondary btn-sm fw-bold" data-bs-dismiss="modal">Dismiss Close</button>
        <button type="submit" class="btn btn-primary btn-sm fw-bold px-4 shadow-sm">Commit System Modifications</button>
      </div>
    </form>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
let assignmentCounter = 200; // Counter allocation offset track matrix

/**
 * Appends selection fields into row tracking groups dynamically
 */
function appendAssignmentRow(targetContainerId, selectedGrade = '', selectedSection = '') {
    const targetContainer = document.getElementById(targetContainerId);
    assignmentCounter++;
    
    const wrapper = document.createElement('div');
    wrapper.className = 'col-12 d-flex gap-2 alignment-unit mb-1';
    
    const grades = ['Grade 9', 'Grade 10', 'Grade 11', 'Grade 12'];
    let optionsHtml = `<option value="" disabled ${selectedGrade === '' ? 'selected' : ''}>-- Choose Grade --</option>`;
    
    grades.forEach(g => {
        optionsHtml += `<option value="${g}" ${selectedGrade === g ? 'selected' : ''}>${g}</option>`;
    });

    wrapper.innerHTML = `
        <select name="assignments[${assignmentCounter}][grade_level]" class="form-select form-select-sm w-50" required>
            ${optionsHtml}
        </select>
        <input type="text" name="assignments[${assignmentCounter}][section]" class="form-control form-control-sm w-50" placeholder="Section (e.g. Sec-B)" value="${selectedSection}" required>
        <button type="button" class="btn btn-sm btn-light border" onclick="this.parentElement.remove()"><i class="bi bi-x-lg text-danger"></i></button>
    `;
    
    targetContainer.appendChild(wrapper);
}

/**
 * Maps background structural information directly to editable elements vectors in modal windows
 */
function launchUpdateModal(userPayload, activeAssignmentsArray) {
    document.getElementById('update_user_id').value = userPayload.id;
    document.getElementById('update_fullname').value = userPayload.fullname;
    document.getElementById('update_username').value = userPayload.username;
    document.getElementById('update_email').value = userPayload.email;
    document.getElementById('update_password').value = userPayload.password;
    document.getElementById('update_enrolled_date').value = userPayload.enrolled_date || '';

    if (document.getElementById('update_graduated_program')) {
        document.getElementById('update_graduated_program').value = userPayload.graduated_program || '';
        document.getElementById('update_current_teaching_subject').value = userPayload.current_teaching_subject || '';
    }

    // Clear previous modal array listings nodes cleanly
    const modalBox = document.getElementById('modal_assignment_box');
    modalBox.innerHTML = '';

    if (activeAssignmentsArray && activeAssignmentsArray.length > 0) {
        activeAssignmentsArray.forEach(asg => {
            appendAssignmentRow('modal_assignment_box', asg.grade_level, asg.section);
        });
    } else {
        appendAssignmentRow('modal_assignment_box');
    }

    var editModalElement = new bootstrap.Modal(document.getElementById('updateUserModal'));
    editModalElement.show();
}
</script>
</body>
</html>