<?php
require_once './db.php';
require_once './auth.php';
verifyAccess(['student']);

$attempt_id = isset($_GET['attempt_id']) ? intval($_GET['attempt_id']) : 0;

// Gather processed outcome variables parameters
$stmt = $pdo->prepare("SELECT er.*, e.title, e.pass_percentage FROM exam_results er 
                       JOIN exam_attempts ea ON er.attempt_id = ea.id 
                       JOIN exams e ON ea.exam_id = e.id 
                       WHERE ea.id = ? AND ea.student_id = ?");
$stmt->execute([$attempt_id, $_SESSION['user_id']]);
$report = $stmt->fetch();

if (!$report) {
    die("Report payload missing or inaccessible.");
}

// Extract historical tracking breakdown sets for review loops
$qaStmt = $pdo->prepare("SELECT q.question_text, sa.selected_option, ak.correct_option 
                         FROM student_answers sa
                         JOIN questions q ON sa.question_id = q.id
                         JOIN answer_keys ak ON q.id = ak.question_id
                         WHERE sa.attempt_id = ?");
$qaStmt->execute([$attempt_id]);
$breakdown = $qaStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Scorecard Overview - Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .correct-card { border-left: 5px solid #10b981; background-color: #f0fdf4; }
        .incorrect-card { border-left: 5px solid #ef4444; background-color: #fef2f2; }
    </style>
</head>
<body class="bg-light">
<div class="container my-5" style="max-width: 850px;">
    <div class="card p-5 shadow-sm bg-white mb-4">
        <h2 class="text-center mb-4">Performance Summary Report</h2>
        <h5 class="text-secondary">Exam Component: <strong><?= htmlspecialchars($report['title']) ?></strong></h5>
        <hr>
        <div class="row text-center my-4">
            <div class="col-md-4">
                <p class="text-muted mb-1">Score Earned</p>
                <h3><?= number_format($report['score_obtained'], 2) ?></h3>
            </div>
            <div class="col-md-4">
                <p class="text-muted mb-1">Percentage Conversion</p>
                <h3><?= number_format($report['percentage'], 2) ?>%</h3>
            </div>
            <div class="col-md-4">
                <p class="text-muted mb-1">Outcome Status</p>
                <h3 class="<?= $report['status'] === 'Pass' ? 'text-success' : 'text-danger' ?>"><?= $report['status'] ?></h3>
            </div>
        </div>
        <div class="text-center">
            <button onclick="window.print()" class="btn btn-outline-dark px-4">Print Official Transcript</button>
        </div>
    </div>

    <h4 class="mb-3 text-dark">Response Matrix Review</h4>
    <?php foreach ($breakdown as $i => $row): 
        $isCorrect = ($row['selected_option'] === $row['correct_option']);
    ?>
        <div class="card p-3 mb-2 shadow-xs <?= $isCorrect ? 'correct-card' : 'incorrect-card' ?>">
            <h6><strong>Q<?= $i+1 ?>.</strong> <?= htmlspecialchars($row['question_text']) ?></h6>
            <div class="mt-2" style="font-size:0.9rem;">
                <span>Your Selected Selection: <strong class="<?= $isCorrect ? 'text-success' : 'text-danger' ?>"><?= $row['selected_option'] ?? 'UNANSWERED' ?></strong></span> | 
                <span class="text-success">Verified Correct Answer: <strong><?= $row['correct_option'] ?></strong></span>
            </div>
        </div>
    <?php endforeach; ?>
</div>
</body>
</html>