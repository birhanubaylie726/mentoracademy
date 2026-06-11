<?php
require_once './db.php';
require_once './auth.php';
verifyAccess(['admin']);

// --- DATABASE INTEGRITY SAFEGUARD MIGRATION ---
$pdo->exec("CREATE TABLE IF NOT EXISTS flagged_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    exam_id INT NOT NULL,
    student_name VARCHAR(150) NOT NULL,
    reason TEXT NOT NULL,
    status ENUM('Pending', 'Investigated') DEFAULT 'Pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE CASCADE
)");

$success_msg = '';

// --- RESOLVE INCIDENT REPORT ---
if (isset($_GET['resolve_id'])) {
    $rep_id = intval($_GET['resolve_id']);
    $stmt = $pdo->prepare("UPDATE flagged_reports SET status = 'Investigated' WHERE id = ?");
    $stmt->execute([$rep_id]);
    $success_msg = "✅ Incident marker updated to 'Investigated' operational verification state.";
}

// Pull issues from the tracking cache matrix records
$reports = $pdo->query("SELECT r.*, e.title as exam_title FROM flagged_reports r JOIN exams e ON r.exam_id = e.id ORDER BY r.status ASC, r.id DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Integrity Incident Central - Admin Portal</title>
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
            <li><a href="manage_exams.php" class="nav-link"><i class="bi bi-journal-check me-2"></i> Manage Exams</a></li>
            <li><a href="schedules.php" class="nav-link"><i class="bi bi-calendar-event me-2"></i> Schedules</a></li>
            <li><a href="flagged_reports.php" class="nav-link active"><i class="bi bi-flag me-2"></i> Flagged Reports</a></li>
            <li><a href="system_settings.php" class="nav-link"><i class="bi bi-gear me-2"></i> System Settings</a></li>
        </ul>
        <hr class="border-secondary">
        <a href="./logout.php" class="btn btn-danger w-100 fw-bold"><i class="bi bi-box-arrow-left me-2"></i> Logout</a>
    </div>

    <div class="w-100">
        <nav class="navbar navbar-expand navbar-white bg-white border-bottom px-4 py-3 sticky-top">
            <span class="navbar-brand mb-0 h5 text-dark fw-bold">Flagged Incident & Validation Audits</span>
        </nav>

        <div class="container-fluid p-4">
            <?php if($success_msg): ?> <div class="alert alert-success"><?= $success_msg ?></div> <?php endif; ?>

            <div class="card border-0 shadow-sm p-4 bg-white">
                <h5 class="fw-bold text-dark mb-4"><i class="bi bi-exclamation-triangle-fill text-danger me-1"></i> Integrity Reports Feed</h5>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Incident ID</th>
                                <th>Target Session Suite</th>
                                <th>Reporting Entity Profile</th>
                                <th>Issue Context Description Log</th>
                                <th>State Tracking</th>
                                <th class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($reports)): ?>
                                <tr><td colspan="6" class="text-center text-muted py-4">🎉 Excellent! No integrity violations or flagged technical bugs are currently registered in the database cache queues.</td></tr>
                            <?php else: ?>
                                <?php foreach($reports as $rep): ?>
                                    <tr class="<?= $rep['status'] === 'Pending' ? 'table-warning' : '' ?>">
                                        <td><code>#INC-<?= $rep['id'] ?></code></td>
                                        <td><strong><?= htmlspecialchars($rep['exam_title']) ?></strong></td>
                                        <td><span class="text-secondary fw-bold"><?= htmlspecialchars($rep['student_name']) ?></span></td>
                                        <td><small class="text-dark d-block" style="max-width:350px; white-space: normal;"><?= htmlspecialchars($rep['reason']) ?></small></td>
                                        <td>
                                            <span class="badge <?= $rep['status'] === 'Pending' ? 'bg-danger' : 'bg-success' ?>">
                                                <?= $rep['status'] ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <?php if($rep['status'] === 'Pending'): ?>
                                                <a href="flagged_reports.php?resolve_id=<?= $rep['id'] ?>" class="btn btn-sm btn-success fw-bold px-3">
                                                    <i class="bi bi-check-circle-fill me-1"></i> Archive Dismiss
                                                </a>
                                            <?php else: ?>
                                                <button class="btn btn-sm btn-light text-muted" disabled><i class="bi bi-archive-fill"></i> Closed</button>
                                            <?php endif; ?>
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