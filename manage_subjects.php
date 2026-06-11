<?php
require_once './db.php';
require_once './auth.php';
verifyAccess(['admin']);

$success_msg = '';
$error_msg = '';

// --- FORM HANDLING: ADD SUBJECT ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_subject') {
    $subject_name = trim($_POST['subject_name']);

    if (!empty($subject_name)) {
        try {
            $chk = $pdo->prepare("SELECT COUNT(*) FROM subjects WHERE subject_name = ?");
            $chk->execute([$subject_name]);
            if ($chk->fetchColumn() > 0) {
                $error_msg = "❌ Configuration Collision: The requested field text descriptor name is already registered inside tracking matrices maps.";
            } else {
                $ins = $pdo->prepare("INSERT INTO subjects (subject_name) VALUES (?)");
                $ins->execute([$subject_name]);
                $success_msg = "✅ Success: Subject parameters set recorded within local databases index lookup arrays.";
            }
        } catch (PDOException $e) {
            $error_msg = "🚨 Operational execution halt sequence error: " . $e->getMessage();
        }
    } else {
        $error_msg = "⚠️ Input parameters cannot resolve empty text descriptors rows mappings variables fields.";
    }
}

// --- FORM HANDLING: DELETE SUBJECT ---
if (isset($_GET['delete_id'])) {
    $del_id = intval($_GET['delete_id']);
    try {
        $del = $pdo->prepare("DELETE FROM subjects WHERE id = ?");
        $del->execute([$del_id]);
        $success_msg = "🗑️ Structural subject block cleared from validation processing table sets.";
    } catch(PDOException $e) {
        $error_msg = "❌ Processing Lock: The selected subject cannot be deleted because it is currently linked to an active exam.";
    }
}

// Fetch all active records entries mapping subjects tables data rows sets
$subjects = $pdo->query("SELECT * FROM subjects ORDER BY subject_name ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Subjects - Core Engine Control Panel Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background-color: #f8fafc; }
        .sidebar { min-width: 260px; max-width: 260px; background: #0f172a; min-height: 100vh; color: #fff; }
        .sidebar .nav-link { color: #94a3b8; font-weight: 500; }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { color: #fff; background: #1e293b; border-radius: 6px; }
    </style>
</head>
<body>
<div class="d-flex">
    <div class="sidebar p-3 d-flex flex-column">
        <h4 class="text-center py-3 border-bottom border-secondary mb-4 fw-bold">Portal Admin</h4>
        <ul class="nav nav-pills flex-column mb-auto gap-1">
            <li><a href="admin_dashboard.php" class="nav-link"><i class="bi bi-speedometer2 me-2"></i> Dashboard</a></li>
            <li><a href="manage_users.php?role=teacher" class="nav-link"><i class="bi bi-person-badge me-2"></i> Manage Teachers</a></li>
            <li><a href="manage_users.php?role=student" class="nav-link"><i class="bi bi-people me-2"></i> Manage Students</a></li>
            <li><a href="manage_subjects.php" class="nav-link active"><i class="bi bi-book me-2"></i> Manage Subjects</a></li>
        </ul>
        <hr class="border-secondary">
        <a href="./logout.php" class="btn btn-danger w-100 fw-bold"><i class="bi bi-box-arrow-left me-2"></i> Logout</a>
    </div>

    <div class="w-100">
        <nav class="navbar navbar-expand navbar-white bg-white border-bottom px-4 py-3 sticky-top">
            <span class="navbar-brand mb-0 h5 text-dark fw-bold">Administration Suite: Manage Subjects Matrix</span>
        </nav>

        <div class="container-fluid p-4">
            <?php if($success_msg): ?> <div class="alert alert-success"><?= $success_msg ?></div> <?php endif; ?>
            <?php if($error_msg): ?> <div class="alert alert-danger"><?= $error_msg ?></div> <?php endif; ?>

            <div class="row g-4">
                <div class="col-md-5">
                    <div class="card border-0 shadow-sm p-4 bg-white">
                        <h5 class="fw-bold text-dark mb-3"><i class="bi bi-bookmark-plus-fill me-1"></i> Register New Academic Subject</h5>
                        <form action="" method="POST">
                            <input type="hidden" name="action" value="add_subject">
                            <div class="mb-3">
                                <label class="form-label small fw-bold text-secondary">Subject Course Title Descriptive Name</label>
                                <input type="text" name="subject_name" class="form-control" required placeholder="e.g. Information Technology">
                            </div>
                            <button type="submit" class="btn btn-success w-100 fw-bold py-2">Add Subject Configuration</button>
                        </form>
                    </div>
                </div>

                <div class="col-md-7">
                    <div class="card border-0 shadow-sm p-4 bg-white">
                        <h5 class="fw-bold text-dark mb-3"><i class="bi bi-collection-play me-1"></i> Current Configured Course Curriculums</h5>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 20%;">Subject ID</th>
                                        <th>Course Subject Label Name</th>
                                        <th class="text-center" style="width: 30%;">Database Action Blocks</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(empty($subjects)): ?>
                                        <tr><td colspan="3" class="text-center text-muted py-3">No active subjects registered.</td></tr>
                                    <?php else: ?>
                                        <?php foreach($subjects as $sub): ?>
                                            <tr>
                                                <td><code>SUB-0<?= $sub['id'] ?></code></td>
                                                <td><strong><?= htmlspecialchars($sub['subject_name']) ?></strong></td>
                                                <td class="text-center">
                                                    <a href="manage_subjects.php?delete_id=<?= $sub['id'] ?>" class="btn btn-sm btn-outline-danger px-3" onclick="return confirm('Caution! Confirm deletion execution targeting current data object indices map rows?');"><i class="bi bi-trash3-fill"></i> Delete</a>
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
    </div>
</div>
</body>
</html>