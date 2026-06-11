<?php
require_once './db.php';
require_once './auth.php';
verifyAccess(['admin']);

// --- DATABASE INTEGRITY SAFEGUARD MIGRATION ---
$pdo->exec("CREATE TABLE IF NOT EXISTS schedules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    exam_id INT NOT NULL,
    start_time DATETIME NOT NULL,
    end_time DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE CASCADE
)");

$success_msg = '';
$error_msg = '';

// --- POST INTERCEPTION: ALLOCATE SCHEDULE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_schedule') {
    $exam_id    = intval($_POST['exam_id']);
    $start_time = $_POST['start_time'];
    $end_time   = $_POST['end_time'];

    if ($exam_id > 0 && !empty($start_time) && !empty($end_time)) {
        if (strtotime($start_time) >= strtotime($end_time)) {
            $error_msg = "❌ Constraints Error: Release configuration timeframe must occur prior to expiration lock deadline.";
        } else {
            $stmt = $pdo->prepare("INSERT INTO schedules (exam_id, start_time, end_time) VALUES (?, ?, ?)");
            $stmt->execute([$exam_id, $start_time, $end_time]);
            $success_msg = "✅ Access Schedule mapped successfully to testing matrix parameters.";
        }
    } else {
        $error_msg = "⚠️ Complete all form parameters before committing configuration vectors.";
    }
}

// --- GET INTERCEPTION: CLEAR SCHEDULE ---
if (isset($_GET['delete_id'])) {
    $sched_id = intval($_GET['delete_id']);
    $stmt = $pdo->prepare("DELETE FROM schedules WHERE id = ?");
    $stmt->execute([$sched_id]);
    $success_msg = "🗑️ Timeframe allocation row dropped from system tracking queues.";
}

// Gather dynamic datasets
$exams_list = $pdo->query("SELECT id, title FROM exams WHERE is_active = 1 ORDER BY title ASC")->fetchAll();
$schedules  = $pdo->query("SELECT sch.*, ex.title as exam_title FROM schedules sch JOIN exams ex ON sch.exam_id = ex.id ORDER BY sch.start_time ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Schedules Registry - Admin Portal</title>
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
            <li><a href="schedules.php" class="nav-link active"><i class="bi bi-calendar-event me-2"></i> Schedules</a></li>
            <li><a href="flagged_reports.php" class="nav-link"><i class="bi bi-flag me-2"></i> Flagged Reports</a></li>
            <li><a href="system_settings.php" class="nav-link"><i class="bi bi-gear me-2"></i> System Settings</a></li>
        </ul>
        <hr class="border-secondary">
        <a href="./logout.php" class="btn btn-danger w-100 fw-bold"><i class="bi bi-box-arrow-left me-2"></i> Logout</a>
    </div>

    <div class="w-100">
        <nav class="navbar navbar-expand navbar-white bg-white border-bottom px-4 py-3 sticky-top">
            <span class="navbar-brand mb-0 h5 text-dark fw-bold">Automated Session Schedule Registry</span>
        </nav>

        <div class="container-fluid p-4">
            <?php if($success_msg): ?> <div class="alert alert-success"><?= $success_msg ?></div> <?php endif; ?>
            <?php if($error_msg): ?> <div class="alert alert-danger"><?= $error_msg ?></div> <?php endif; ?>

            <div class="row g-4">
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm p-4 bg-white">
                        <h5 class="fw-bold text-dark mb-3"><i class="bi bi-clock me-1"></i> Map Access Timeframe</h5>
                        <form action="" method="POST">
                            <input type="hidden" name="action" value="add_schedule">
                            <div class="mb-3">
                                <label class="form-label small fw-bold text-secondary">Select Examination Module</label>
                                <select name="exam_id" class="form-select" required>
                                    <option value="">-- Choose Active Suite --</option>
                                    <?php foreach($exams_list as $el): ?>
                                        <option value="<?= $el['id'] ?>"><?= htmlspecialchars($el['title']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-bold text-secondary">Activation Door Opens</label>
                                <input type="datetime-local" name="start_time" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-bold text-secondary">System Lock Expiration</label>
                                <input type="datetime-local" name="end_time" class="form-control" required>
                            </div>
                            <button type="submit" class="btn btn-primary w-100 fw-bold py-2">Commit Access Window</button>
                        </form>
                    </div>
                </div>

                <div class="col-md-8">
                    <div class="card border-0 shadow-sm p-4 bg-white">
                        <h5 class="fw-bold text-dark mb-3"><i class="bi bi-calendar3 me-1"></i> Active Testing Allocations</h5>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Target Examination Module</th>
                                        <th>Door Opens</th>
                                        <th>Session Expires</th>
                                        <th class="text-center">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(empty($schedules)): ?>
                                        <tr><td colspan="4" class="text-center text-muted py-3">No testing block windows scheduled inside tracking indexes.</td></tr>
                                    <?php else: ?>
                                        <?php foreach($schedules as $sch): ?>
                                            <tr>
                                                <td><strong><?= htmlspecialchars($sch['exam_title']) ?></strong></td>
                                                <td><code><?= $sch['start_time'] ?></code></td>
                                                <td><code><?= $sch['end_time'] ?></code></td>
                                                <td class="text-center">
                                                    <a href="schedules.php?delete_id=<?= $sch['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Revoke this scheduled time window configuration row?');">
                                                        <i class="bi bi-trash3-fill"></i> Clear
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
    </div>
</div>
</body>
</html>