<?php
require_once './db.php';
require_once './auth.php';
verifyAccess(['admin']);

$success_msg = '';

// --- TOGGLE ACTIVATION STATUS ---
if (isset($_GET['toggle_id'])) {
    $exam_id = intval($_GET['toggle_id']);
    $stmt = $pdo->prepare("UPDATE exams SET is_active = NOT is_active WHERE id = ?");
    $stmt->execute([$exam_id]);
    $success_msg = "🔄 Success: Exam availability state updated successfully.";
}

// --- DROP EXAM CONFIGURATION ---
if (isset($_GET['delete_id'])) {
    $exam_id = intval($_GET['delete_id']);
    $stmt = $pdo->prepare("DELETE FROM exams WHERE id = ?");
    $stmt->execute([$exam_id]);
    $success_msg = "🗑️ Success: Target evaluation structure removed from master lists.";
}

// Fetch all registered exams with related subject and teacher details
$query = "SELECT e.*, s.subject_name, u.fullname as teacher_name,
          (SELECT COUNT(*) FROM questions WHERE exam_id = e.id) as q_count 
          FROM exams e 
          JOIN subjects s ON e.subject_id = s.id 
          JOIN users u ON e.created_by = u.id 
          ORDER BY e.id DESC";
$all_exams = $pdo->query($query)->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Global Exam Manager - Admin Portal</title>
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
            <li><a href="manage_subjects.php" class="nav-link"><i class="bi bi-book me-2"></i> Manage Subjects</a></li>
            <li><a href="manage_exams.php" class="nav-link active"><i class="bi bi-journal-check me-2"></i> Manage Exams</a></li>
            <li><a href="schedules.php" class="nav-link"><i class="bi bi-calendar-event me-2"></i> Schedules</a></li>
            <li><a href="flagged_reports.php" class="nav-link"><i class="bi bi-flag me-2"></i> Flagged Reports</a></li>
            <li><a href="system_settings.php" class="nav-link"><i class="bi bi-gear me-2"></i> System Settings</a></li>
        </ul>
        <hr class="border-secondary">
        <a href="./logout.php" class="btn btn-danger w-100 fw-bold"><i class="bi bi-box-arrow-left me-2"></i> Logout</a>
    </div>

    <div class="w-100">
        <nav class="navbar navbar-expand navbar-white bg-white border-bottom px-4 py-3 sticky-top">
            <span class="navbar-brand mb-0 h5 text-dark fw-bold">Master Examination Database Audit</span>
        </nav>

        <div class="container-fluid p-4">
            <?php if($success_msg): ?> <div class="alert alert-success"><?= $success_msg ?></div> <?php endif; ?>

            <div class="card border-0 shadow-sm p-4 bg-white">
                <h5 class="fw-bold text-dark mb-4"><i class="bi bi-shield-lock-fill me-1"></i> System-Wide Exam Deployments</h5>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Exam ID</th>
                                <th>Assessment Title</th>
                                <th>Linked Discipline</th>
                                <th>Assigned Faculty</th>
                                <th>Metrics Pool</th>
                                <th>Status</th>
                                <th class="text-center">Control Formations</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($all_exams)): ?>
                                <tr><td colspan="7" class="text-center text-muted py-3">No evaluation structures are registered inside the portal system yet.</td></tr>
                            <?php else: ?>
                                <?php foreach($all_exams as $ex): ?>
                                    <tr>
                                        <td><code>EXM-<?= $ex['id'] ?></code></td>
                                        <td><strong><?= htmlspecialchars($ex['title']) ?></strong></td>
                                        <td><span class="badge bg-secondary"><?= htmlspecialchars($ex['subject_name']) ?></span></td>
                                        <td><i class="bi bi-person me-1"></i><?= htmlspecialchars($ex['teacher_name']) ?></td>
                                        <td><code><?= $ex['q_count'] ?> MCQs</code> (<?= $ex['duration'] ?> min)</td>
                                        <td>
                                            <span class="badge <?= $ex['is_active'] ? 'bg-success' : 'bg-danger' ?>">
                                                <?= $ex['is_active'] ? 'Live / Visible' : 'Suspended' ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <a href="manage_exams.php?toggle_id=<?= $ex['id'] ?>" class="btn btn-sm <?= $ex['is_active'] ? 'btn-outline-warning text-dark' : 'btn-outline-success' ?> me-1">
                                                <i class="bi <?= $ex['is_active'] ? 'bi-eye-slash-fill' : 'bi-eye-fill' ?>"></i> <?= $ex['is_active'] ? 'Deactivate' : 'Activate' ?>
                                            </a>
                                            <a href="manage_exams.php?delete_id=<?= $ex['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Drop this examination suite permanently from database storage?');">
                                                <i class="bi bi-trash"></i> Drop
                                            </a>
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
</body>
</html>