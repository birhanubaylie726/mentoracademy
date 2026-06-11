<?php
require_once './db.php';
require_once './auth.php';
verifyAccess(['student']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['exam_id'])) {
    die("🚨 Direct URL interception blocked.");
}

$student_id = $_SESSION['user_id'];
$exam_id = intval($_POST['exam_id']);
$student_answers = $_POST['answers'] ?? []; // Map of [question_id => selected_option]

try {
    // Double Check: Ensure no existing grading record rows match this target mapping configuration
    $check = $pdo->prepare("SELECT id FROM student_exams WHERE user_id = ? AND exam_id = ?");
    $check->execute([$student_id, $exam_id]);
    if ($check->fetch()) {
        die("🚫 Duplication Protection: Score record already saved for this session profile.");
    }

    // Fetch absolute master validation keys from the questions collection
    $q_stmt = $pdo->prepare("SELECT id, correct_option FROM questions WHERE exam_id = ?");
    $q_stmt->execute([$exam_id]);
    $correct_keys = $q_stmt->fetchAll(PDO::FETCH_KEY_PAIR); // Generates array map: [id => correct_option]

    $total_questions = count($correct_keys);
    $score = 0;

    // Cross-reference grading parameters matrices rows
    if ($total_questions > 0) {
        foreach ($correct_keys as $q_id => $correct_opt) {
            if (isset($student_answers[$q_id]) && strtoupper(trim($student_answers[$q_id])) === strtoupper(trim($correct_opt))) {
                $score++;
            }
        }
    }

    // Insert results transaction record
    $insert_stmt = $pdo->prepare("INSERT INTO student_exams (user_id, exam_id, score, total_questions, status) VALUES (?, ?, ?, ?, 'Completed')");
    $insert_stmt->execute([$student_id, $exam_id, $score, $total_questions]);

    // Calculate dynamic visual telemetry percentages
    $pct = $total_questions > 0 ? round(($score / $total_questions) * 100, 1) : 0;
    $theme_class = ($pct >= 50) ? 'alert-success' : 'alert-danger';

} catch (PDOException $e) {
    die("🚨 Transaction Interrupted: Failed to record exam data: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Evaluation Receipt Summary</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card border-0 shadow-lg text-center p-4 bg-white">
                <div class="card-body">
                    <i class="bi bi-file-earmark-check-fill fs-1 text-primary mb-3"></i>
                    <h3 class="fw-bold text-dark mb-1">Assessment Complete</h3>
                    <p class="text-muted small">Your scores are securely logged in the system.</p>
                    
                    <div class="alert <?= $theme_class ?> my-4 p-4">
                        <span class="d-block text-secondary text-uppercase small font-monospace fw-bold mb-1">Evaluated Score Metric</span>
                        <h1 class="display-4 fw-bold m-0"><?= $pct ?>%</h1>
                        <p class="mb-0 mt-2 fw-semibold">Points Secured: <?= $score ?> out of <?= $total_questions ?> total metrics</p>
                    </div>

                    <a href="student_dashboard.php" class="btn btn-primary px-4 fw-bold"><i class="bi bi-house-door-fill me-1"></i> Return to Student Desk</a>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>