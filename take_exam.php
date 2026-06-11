<?php
require_once './db.php';
require_once './auth.php';
verifyAccess(['student']);

$student_id = $_SESSION['user_id'];
$exam_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($exam_id <= 0) {
    die("🚨 Error: Invalid evaluation sequence reference identifier.");
}

try {
    // 1. Fetch Exam Configurations and Verify it is Active
    $exam_stmt = $pdo->prepare("SELECT e.*, s.subject_name FROM exams e JOIN subjects s ON e.subject_id = s.id WHERE e.id = ? AND e.is_active = 1");
    $exam_stmt->execute([$exam_id]);
    $exam = $exam_stmt->fetch();

    if (!$exam) {
        die("⚠️ Access Blocked: This examination is either offline, inactive, or does not exist.");
    }

    // 2. Prevent Fraud: Block re-entry if this student has already submitted this exam
    $check_stmt = $pdo->prepare("SELECT id FROM student_exams WHERE user_id = ? AND exam_id = ?");
    $check_stmt->execute([$student_id, $exam_id]);
    if ($check_stmt->fetch()) {
        die("🚫 Security Halt: System telemetry shows you have already completed this evaluation matrix entry.");
    }

    // 3. Extract loaded questionnaire arrays
    $q_stmt = $pdo->prepare("SELECT * FROM questions WHERE exam_id = ? ORDER BY id ASC");
    $q_stmt->execute([$exam_id]);
    $questions = $q_stmt->fetchAll();

    if (empty($questions)) {
        die("ℹ️ Notice: This exam structure is deployed, but no questions have been loaded by the instructor yet.");
    }

} catch (PDOException $e) {
    die("🚨 Core System Failure: Database synchronization disrupted: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Active Terminal - <?= htmlspecialchars($exam['title']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background-color: #f1f5f9; user-select: none; } /* Prevents copy-pasting answers */
        .timer-banner { background: #0f172a; color: #fff; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); }
        .question-card { border: none; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .option-container { cursor: pointer; transition: background 0.2s; border: 1px solid #e2e8f0; border-radius: 6px; padding: 12px 16px; margin-bottom: 8px; display: block; }
        .option-container:hover { background: #f8fafc; border-color: #cbd5e1; }
        .form-check-input:checked + .option-text { font-weight: 600; color: #1e3a8a; }
    </style>
</head>
<body>

<div class="timer-banner sticky-top py-3 px-4 d-flex justify-content-between align-items-center">
    <div>
        <h5 class="mb-0 fw-bold text-white"><?= htmlspecialchars($exam['title']) ?></h5>
        <span class="badge bg-secondary"><?= htmlspecialchars($exam['subject_name']) ?></span>
    </div>
    <div class="d-flex align-items-center gap-3">
        <div class="text-end">
            <small class="text-muted d-block text-uppercase small fw-bold">Remaining Duration</small>
            <h4 id="timer_clock" class="mb-0 font-monospace fw-bold text-warning">00:00</h4>
        </div>
        <i class="bi bi-clock-history fs-2 text-warning"></i>
    </div>
</div>

<div class="container my-5" style="max-width: 850px;">
    <form id="exam_terminal_form" action="process_submission.php" method="POST">
        <input type="hidden" name="exam_id" value="<?= $exam_id ?>">
        
        <?php foreach ($questions as $index => $q): ?>
            <div class="card question-card p-4 mb-4 bg-white">
                <h6 class="fw-bold text-secondary mb-3">Question Item <?= $index + 1 ?> of <?= count($questions) ?></h6>
                <p class="fs-5 text-dark fw-semibold mb-4"><?= htmlspecialchars($q['question_text']) ?></p>
                
                <label class="option-container">
                    <input class="form-check-input me-2" type="radio" name="answers[<?= $q['id'] ?>]" value="A" required>
                    <span class="option-text">A. <?= htmlspecialchars($q['option_a']) ?></span>
                </label>
                
                <label class="option-container">
                    <input class="form-check-input me-2" type="radio" name="answers[<?= $q['id'] ?>]" value="B">
                    <span class="option-text">B. <?= htmlspecialchars($q['option_b']) ?></span>
                </label>
                
                <?php if (!empty($q['option_c'])): ?>
                    <label class="option-container">
                        <input class="form-check-input me-2" type="radio" name="answers[<?= $q['id'] ?>]" value="C">
                        <span class="option-text">C. <?= htmlspecialchars($q['option_c']) ?></span>
                    </label>
                <?php endif; ?>
                
                <?php if (!empty($q['option_d'])): ?>
                    <label class="option-container">
                        <input class="form-check-input me-2" type="radio" name="answers[<?= $q['id'] ?>]" value="D">
                        <span class="option-text">D. <?= htmlspecialchars($q['option_d']) ?></span>
                    </label>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>

        <div class="card p-3 border-0 shadow-sm bg-white text-end">
            <button type="submit" class="btn btn-lg btn-success px-5 fw-bold" onclick="return confirm('Verify all selections. Are you ready to finalise and upload this transcript?');">Commit Evaluation Submission</button>
        </div>
    </form>
</div>

<script>
    // Convert duration minutes directly into execution seconds
    let totalSeconds = <?= intval($exam['duration']) ?> * 60;
    const clockElement = document.getElementById('timer_clock');
    const formElement = document.getElementById('exam_terminal_form');

    const countdownEngine = setInterval(() => {
        if (totalSeconds <= 0) {
            clearInterval(countdownEngine);
            clockElement.innerHTML = "00:00";
            alert("⏳ Evaluation Period Expired! The system is locking inputs and processing your transcript now.");
            
            // Clear require attributes to bypass browser validation triggers during fallback auto-submit
            const requiredInputs = formElement.querySelectorAll('[required]');
            requiredInputs.forEach(input => input.removeAttribute('required'));
            
            formElement.submit();
        } else {
            totalSeconds--;
            let mins = Math.floor(totalSeconds / 60);
            let secs = totalSeconds % 60;
            
            // Format padding zeros
            mins = mins < 10 ? '0' : '' + mins;
            secs = secs < 10 ? '0' : '' + secs;
            
            clockElement.innerHTML = mins + ":" + secs;
            
            // Change colors to red when remaining time drops under 2 minutes
            if (totalSeconds < 120) {
                clockElement.classList.replace('text-warning', 'text-danger');
            }
        }
    }, 1000);
</script>
</body>
</html>