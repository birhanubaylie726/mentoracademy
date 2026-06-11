<?php
require_once './db.php';
require_once './auth.php';
verifyAccess(['admin']);

// --- DATABASE INTEGRITY SAFEGUARD MIGRATION ---
// Ensures a table exists to track real-time exam execution states and score summaries
$pdo->exec("CREATE TABLE IF NOT EXISTS student_exams (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    exam_id INT NOT NULL,
    score INT DEFAULT 0,
    total_questions INT DEFAULT 0,
    status ENUM('In Progress', 'Completed') DEFAULT 'Completed',
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE CASCADE
)");

$success_msg = '';

// --- OPTIONAL CLEANUP: ARCHIVE/RESET A STUDENT SCORE MATRIX ---
if (isset($_GET['clear_id'])) {
    $record_id = intval($_GET['clear_id']);
    $stmt = $pdo->prepare("DELETE FROM student_exams WHERE id = ?");
    $stmt->execute([$record_id]);
    $success_msg = "🗑️ Performance log sequence removed from tracking metrics table arrays.";
}

// Fetch all unified performance metrics records across the entire high school infrastructure
$query = "SELECT se.*, 
                 u_stu.fullname as student_name, u_stu.username as student_username,
                 ex.title as exam_title,
                 sub.subject_name
          FROM student_exams se
          JOIN users u_stu ON se.user_id = u_stu.id
          JOIN exams ex ON se.exam_id = ex.id
          JOIN subjects sub ON ex.subject_id = sub.id
          ORDER BY se.submitted_at DESC";
$results = $pdo->query($query)->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Master Analytics Results - Admin Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background-color: #f8fafc; }
        .sidebar { min-width: 260px; max-width: 260px; background: #0f172a; min-height: 100vh; color: #fff; }
        .sidebar .nav-link { color: #94a3b8; font-weight: 500; }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { color: #fff; background: #1e293b; border-radius: 6px; }
        .score-badge { font-family: monospace; font-size: 0.95rem; }
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
            <li><a href="flagged_reports.php" class="nav-link"><i class="bi bi-flag me-2"></i> Flagged Reports</a></li>
            <li><a href="system_settings.php" class="nav-link"><i class="bi bi-gear me-2"></i> System Settings</a></li>
        </ul>
        <hr class="border-secondary">
        <a href="./logout.php" class="btn btn-danger w-100 fw-bold"><i class="bi bi-box-arrow-left me-2"></i> Logout</a>
    </div>

    <div class="w-100">
        <nav class="navbar navbar-expand navbar-white bg-white border-bottom px-4 py-3 sticky-top">
            <span class="navbar-brand mb-0 h5 text-dark fw-bold">Global Performance Evaluation Matrices</span>
        </nav>

        <div class="container-fluid p-4">
            <?php if($success_msg): ?> <div class="alert alert-success"><?= $success_msg ?></div> <?php endif; ?>

            <div class="card border-0 shadow-sm p-4 bg-white">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h5 class="fw-bold text-dark mb-0"><i class="bi bi-bar-chart-line-fill text-primary me-1"></i> Student Transcript Logs Pool</h5>
                    <button class="btn btn-sm btn-outline-secondary" onclick="window.print()"><i class="bi bi-printer me-1"></i> Print System Log</button>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Student Identity</th>
                                <th>Assessment Target Suite</th>
                                <th>Linked Discipline</th>
                                <th class="text-center">Points Raw Score</th>
                                <th class="text-center">Percentage Evaluation</th>
                                <th>Submission Timeline</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($results)): ?>
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">
                                        <i class="bi bi-clipboard-x fs-2 d-block mb-2 text-black-50"></i>
                                        No performance evaluations or completed test arrays recorded in database trackers yet.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach($results as $row): 
                                    // Protect math rules against dividing by zero configuration holes
                                    $pct = $row['total_questions'] > 0 ? round(($row['score'] / $row['total_questions']) * 100, 1) : 0;
                                    
                                    // Assign conditional style attributes based on grading threshold scales
                                    $color_class = 'bg-danger text-white';
                                    if($pct >= 75) { $color_class = 'bg-success text-white'; }
                                    elseif($pct >= 50) { $color_class = 'bg-warning text-dark'; }
                                ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($row['student_name']) ?></strong>
                                            <small class="text-muted d-block">User: <code><?= htmlspecialchars($row['student_username']) ?></code></small>
                                        </td>
                                        <td><span class="fw-bold text-dark"><?= htmlspecialchars($row['exam_title']) ?></span></td>
                                        <td><span class="badge bg-secondary"><?= htmlspecialchars($row['subject_name']) ?></span></td>
                                        <td class="text-center fw-bold score-badge text-primary">
                                            <?= $row['score'] ?> / <?= $row['total_questions'] ?>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge <?= $color_class ?> p-2 shadow-sm" style="font-size:0.88rem; min-width: 65px;">
                                                <?= $pct ?>%
                                            </span>
                                        </td>
                                        <td><small class="text-secondary"><?= $row['submitted_at'] ?></small></td>
                                        <td class="text-center">
                                            <a href="view_results.php?clear_id=<?= $row['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Purge this target score metric from permanent database storage tables?');">
                                                <i class="bi bi-trash"></i> Drop Log
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