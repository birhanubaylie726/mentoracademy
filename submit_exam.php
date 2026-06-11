<?php
require_once './db.php';
require_once './auth.php';
verifyAccess(['student']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $attempt_id = $_POST['attempt_id'];
    $answers = isset($_POST['answers']) ? $_POST['answers'] : []; // [question_id => selected_option]

    // Fetch details of attempt
    $stmt = $pdo->prepare("SELECT e.id as exam_id, e.pass_percentage, e.negative_marking FROM exam_attempts ea JOIN exams e ON ea.exam_id = e.id WHERE ea.id = ?");
    $stmt->execute([$attempt_id]);
    $exam = $stmt->fetch();

    // Fetch all true answer criteria keys
    $akStmt = $pdo->prepare("SELECT question_id, correct_option FROM answer_keys WHERE question_id IN (SELECT id FROM questions WHERE exam_id = ?)");
    $akStmt->execute([$exam['exam_id']]);
    $keys = $akStmt->fetchAll(PDO::FETCH_KEY_PAIR);

    $total_questions = count($keys);
    $correct_count = 0;
    $wrong_count = 0;

    $pdo->beginTransaction();

    foreach ($keys as $q_id => $correct_opt) {
        $selected = isset($answers[$q_id]) ? $answers[$q_id] : null;
        
        // Persist records into system logs
        $ins = $pdo->prepare("INSERT INTO student_answers (attempt_id, question_id, selected_option) VALUES (?, ?, ?)");
        $ins->execute([$attempt_id, $q_id, $selected]);

        if ($selected === $correct_opt) {
            $correct_count++;
        } elseif ($selected !== null) {
            $wrong_count++;
        }
    }

    // Processing grades incorporating negative weight penalty configurations
    $score = $correct_count - ($wrong_count * $exam['negative_marking']);
    if ($score < 0) $score = 0;

    $percentage = ($score / $total_questions) * 100;
    $status = ($percentage >= $exam['pass_percentage']) ? 'Pass' : 'Fail';

    // Update attempt completion flags
    $upd = $pdo->prepare("UPDATE exam_attempts SET submitted_at = CURRENT_TIMESTAMP, status = 'completed' WHERE id = ?");
    $upd->execute([$attempt_id]);

    // Save consolidated calculations to final outcomes matrix table
    $res = $pdo->prepare("INSERT INTO exam_results (attempt_id, score_obtained, percentage, status) VALUES (?, ?, ?, ?)");
    $res->execute([$attempt_id, $score, $percentage, $status]);

    $pdo->commit();

    // Redirect to visual scorecard display panel view
    header("Location: exam_report.php?attempt_id=" . $attempt_id);
    exit();
}
?>