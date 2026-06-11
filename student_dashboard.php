<?php
require_once './db.php';
require_once './auth.php';
verifyAccess(['student']);

$student_id = $_SESSION['user_id'];
$student_name = $_SESSION['fullname'] ?? 'Student Member';

try {
    // 1. Fetch all active examination records along with their corresponding subject classifications
    $exams_query = "
        SELECT e.id AS exam_id, e.title, e.duration, s.subject_name 
        FROM exams e
        JOIN subjects s ON e.subject_id = s.id
        WHERE e.is_active = 1
        ORDER BY e.id DESC
    ";
    $exams_stmt = $pdo->query($exams_query);
    $active_exams = $exams_stmt->fetchAll();

    // 2. Fetch all completed attempts for this specific student to manage active dashboard action items
    $history_query = "
        SELECT se.exam_id, se.score, se.total_questions, se.submitted_at 
        FROM student_exams se
        WHERE se.user_id = ?
    ";
    $history_stmt = $pdo->prepare($history_query);
    $history_stmt->execute([$student_id]);
    
    // Key pair mapping format array: [exam_id => score_metadata_row_array]
    $completed_attempts = [];
    while ($row = $history_stmt->fetch()) {
        $completed_attempts[$row['exam_id']] = $row;
    }

} catch (PDOException $e) {
    die("🚨 Telemetry Mapping Link Interrupted: Database processing failure: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Control Center - Evaluation Desk</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background-color: #f8fafc; }
        .sidebar { min-width: 260px; max-width: 260px; background: #0f172a; min-height: 100vh; color: #fff; }
        .sidebar .nav-link { color: #94a3b8; font-weight: 500; }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { color: #fff; background: #1e293b; border-radius: 6px; }
        .exam-card { border: none; transition: transform 0.2s, box-shadow 0.2s; }
        .exam-card:hover { transform: translateY(-2px); box-shadow: 0 10px 15px -3px rgba(0,0,0,0.05) !important; }
    </style>
</head>
<body>
<div class="d-flex">
    <div class="sidebar p-3 d-flex flex-column">
        <h4 class="text-center py-3 border-bottom border-secondary mb-4 fw-bold">Student Hub</h4>
        <ul class="nav nav-pills flex-column mb-auto gap-1">
            <li><a href="student_dashboard.php" class="nav-link active"><i class="bi bi-grid-1x2-fill me-2"></i> Available Exams</a></li>
        </ul>
        <hr class="border-secondary">
        <a href="./logout.php" class="btn btn-danger w-100 fw-bold"><i class="bi bi-box-arrow-left me-2"></i> Log Out</a>
    </div>

    <div class="w-100">
        <nav class="navbar navbar-expand navbar-white bg-white border-bottom px-4 py-3 sticky-top">
            <div class="container-fluid p-0">
                <span class="navbar-brand mb-0 h5 text-dark fw-bold">Welcome, <?= htmlspecialchars($student_name) ?></span>
                <span class="badge bg-light text-secondary border px-3 py-2 fw-semibold">Student Terminal</span>
            </div>
        </nav>

        <div class="container-fluid p-4">
            <h5 class="fw-bold text-dark mb-4"><i class="bi bi-journal-code text-primary me-2"></i>Active Assessment Modules</h5>
            
            <?php if (empty($active_exams)): ?>
                <div class="card text-center p-5 border-0 shadow-sm bg-white">
                    <div class="card-body">
                        <i class="bi bi-calendar-x fs-1 text-black-50 d-block mb-3"></i>
                        <h5 class="fw-bold text-dark">No Assessments Available</h5>
                        <p class="text-muted mb-0">There are no live exams published for your dashboard at this time. Check back later.</p>
                    </div>
                </div>
            <?php else: ?>
                <div class="row g-4">
                    <?php foreach ($active_exams as $exam): 
                        $e_id = $exam['exam_id'];
                        $is_completed = isset($completed_attempts[$e_id]);
                    ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="card exam-card h-100 shadow-sm p-3 bg-white">
                                <div class="card-body d-flex flex-column justify-content-between">
                                    <div>
                                        <div class="d-flex justify-content-between align-items-start mb-3">
                                            <span class="badge bg-primary-subtext text-primary border border-primary-subtle rounded-pill px-3 py-1 small fw-bold">
                                                <?= htmlspecialchars($exam['subject_name']) ?>
                                            </span>
                                            <small class="text-muted font-monospace"><i class="bi bi-clock me-1"></i><?= $exam['duration'] ?> Mins</small>
                                        </div>
                                        <h5 class="card-title fw-bold text-dark mb-3"><?= htmlspecialchars($exam['title']) ?></h5>
                                    </div>

                                    <div class="mt-4 pt-3 border-top">
                                        <?php if ($is_completed): 
                                            $attempt = $completed_attempts[$e_id];
                                            $pct = $attempt['total_questions'] > 0 ? round(($attempt['score'] / $attempt['total_questions']) * 100, 1) : 0;
                                        ?>
                                            <div class="d-flex align-items-center justify-content-between bg-light p-2 rounded">
                                                <span class="small fw-bold text-success"><i class="bi bi-check-circle-fill me-1"></i> Completed</span>
                                                <span class="badge bg-success font-monospace p-2">Score: <?= $pct ?>%</span>
                                            </div>
                                            <button class="btn btn-sm btn-secondary w-100 mt-2 card-link" disabled>Locked Matrix</button>
                                        <?php else: ?>
                                            <a href="take_exam.php?id=<?= $e_id ?>" class="btn btn-primary w-100 fw-bold shadow-sm" onclick="return confirm('Initiate active testing window sequence? Your timer begins instantly.');">
                                                <i class="bi bi-pencil-square me-1"></i> Launch Assessment
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>