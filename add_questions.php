<?php
require_once './db.php';
require_once './auth.php';
verifyAccess(['teacher']);

// ==========================================
// 🛠️ EXPANDED SCHEMA HOT-PATCH ENGINE 
// ==========================================
try {
    $columns_to_patch = [
        'question_text'  => "TEXT NOT NULL",
        'option_a'       => "VARCHAR(255) NOT NULL",
        'option_b'       => "VARCHAR(255) NOT NULL",
        'option_c'       => "VARCHAR(255) DEFAULT NULL",
        'option_d'       => "VARCHAR(255) DEFAULT NULL",
        'correct_option' => "ENUM('A', 'B', 'C', 'D') NOT NULL",
        'is_approved'    => "TINYINT(1) NOT NULL DEFAULT 1" // Live status toggle tracking column
    ];

    foreach ($columns_to_patch as $colName => $colDefinition) {
        $check = $pdo->query("SHOW COLUMNS FROM `questions` LIKE '$colName'")->fetch();
        if (!$check) {
            $pdo->exec("ALTER TABLE `questions` ADD `$colName` $colDefinition");
        }
    }
} catch (PDOException $e) {
    die("🚨 Schema Patch Error: " . $e->getMessage());
}
// ==========================================

$teacher_id = $_SESSION['user_id'];
$selected_exam_id = isset($_GET['exam_id']) ? intval($_GET['exam_id']) : 0;
$success_msg = '';
$error_msg = '';

// ==========================================
// 📥 CONTROLLER ACTIONS HANDLING DATA OPERATIONS
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    // 1. ACTION: ADD NEW QUESTION ITEM
    if ($action === 'add_question') {
        $exam_id         = intval($_POST['exam_id']);
        $question_text   = trim($_POST['question_text']);
        $option_a        = trim($_POST['option_a']);
        $option_b        = trim($_POST['option_b']);
        $option_c        = trim($_POST['option_c']);
        $option_d        = trim($_POST['option_d']);
        $correct_option  = $_POST['correct_option'];

        if ($exam_id > 0 && !empty($question_text) && !empty($option_a) && !empty($option_b)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO questions (exam_id, question_text, option_a, option_b, option_c, option_d, correct_option) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$exam_id, $question_text, $option_a, $option_b, $option_c, $option_d, $correct_option]);
                $success_msg = "✅ Success: MCQ item recorded in target exam pool.";
                $selected_exam_id = $exam_id;
            } catch (PDOException $e) { $error_msg = "🚨 Insertion Error: " . $e->getMessage(); }
        } else { $error_msg = "⚠️ Input Blocked: Complete core phrasing details."; }
    }

    // 2. ACTION: UPDATE EXISTING QUESTION ITEM
    if ($action === 'update_question') {
        $q_id            = intval($_POST['question_id']);
        $exam_id         = intval($_POST['exam_id']);
        $question_text   = trim($_POST['question_text']);
        $option_a        = trim($_POST['option_a']);
        $option_b        = trim($_POST['option_b']);
        $option_c        = trim($_POST['option_c']);
        $option_d        = trim($_POST['option_d']);
        $correct_option  = $_POST['correct_option'];

        if ($q_id > 0 && !empty($question_text) && !empty($option_a) && !empty($option_b)) {
            try {
                $stmt = $pdo->prepare("UPDATE questions SET question_text=?, option_a=?, option_b=?, option_c=?, option_d=?, correct_option=? WHERE id=?");
                $stmt->execute([$question_text, $option_a, $option_b, $option_c, $option_d, $correct_option, $q_id]);
                $success_msg = "💾 Changes saved: Question structural variables successfully updated.";
                $selected_exam_id = $exam_id;
                // Redirect out of edit mode context cleanly
                header("Location: add_questions.php?exam_id=" . $exam_id . "&success=" . urlencode($success_msg));
                exit();
            } catch (PDOException $e) { $error_msg = "🚨 Update Error: " . $e->getMessage(); }
        }
    }

    // 3. ACTION: INDIVIDUAL TARGET ITEM DELETION
    if ($action === 'delete_question') {
        $q_id = intval($_POST['question_id']);
        try {
            $stmt = $pdo->prepare("DELETE FROM questions WHERE id = ?");
            $stmt->execute([$q_id]);
            $success_msg = "🗑️ Item Deleted: Selected question permanently stripped from the pool.";
        } catch (PDOException $e) { $error_msg = "🚨 Deletion Failure: " . $e->getMessage(); }
    }

    // 4. ACTION: PURGE/CLEAR ALL QUESTIONS ASSOCIATED WITH CHOSEN EXAM
    if ($action === 'clear_all_questions') {
        $exam_id = intval($_POST['exam_id']);
        try {
            $stmt = $pdo->prepare("DELETE FROM questions WHERE exam_id = ?");
            $stmt->execute([$exam_id]);
            $success_msg = "💥 Purge Complete: All question metrics associated with this module have been cleared.";
        } catch (PDOException $e) { $error_msg = "🚨 Purge Failure: " . $e->getMessage(); }
    }

    // 5. ACTION: TOGGLE APPROVAL AUDIT STATUS
    if ($action === 'toggle_approve') {
        $q_id = intval($_POST['question_id']);
        try {
            $stmt = $pdo->prepare("UPDATE questions SET is_approved = NOT is_approved WHERE id = ?");
            $stmt->execute([$q_id]);
            $success_msg = "🔄 Status Changed: Question verification context updated.";
        } catch (PDOException $e) { $error_msg = "🚨 Status Error: " . $e->getMessage(); }
    }
}

// Check URL redirect alerts
if (isset($_GET['success'])) { $success_msg = htmlspecialchars($_GET['success']); }

// --- LIVE EDIT EXTRACTION PARSER ---
$edit_mode = false;
$edit_q = [];
if (isset($_GET['edit_id'])) {
    $edit_id = intval($_GET['edit_id']);
    $edit_stmt = $pdo->prepare("SELECT * FROM questions WHERE id = ?");
    $edit_stmt->execute([$edit_id]);
    $edit_q = $edit_stmt->fetch();
    if ($edit_q) {
        $edit_mode = true;
        $selected_exam_id = $edit_q['exam_id'];
    }
}

// Fetch context definitions for navigation
$my_exams = $pdo->prepare("SELECT id, title FROM exams WHERE created_by = ? ORDER BY id DESC");
$my_exams->execute([$teacher_id]);
$exams_list = $my_exams->fetchAll();

$loaded_questions = [];
if ($selected_exam_id > 0) {
    $q_stmt = $pdo->prepare("SELECT * FROM questions WHERE exam_id = ? ORDER BY id ASC");
    $q_stmt->execute([$selected_exam_id]);
    $loaded_questions = $q_stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Question Injector Desk - Faculty Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background-color: #f8fafc; }
        .sidebar { min-width: 260px; max-width: 260px; background: #1e3a8a; min-height: 100vh; color: #fff; }
        .sidebar .nav-link { color: #bfdbfe; font-weight: 500; }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { color: #fff; background: #1d4ed8; border-radius: 6px; }
        .edit-highlight { border: 2px dashed #0d6efd !important; background-color: #f0f7ff !important; }
    </style>
</head>
<body>
<div class="d-flex">
    <div class="sidebar p-3 d-flex flex-column">
        <h4 class="text-center py-3 border-bottom border-primary mb-4 fw-bold">Faculty Portal</h4>
        <ul class="nav nav-pills flex-column mb-auto gap-1">
            <li><a href="teacher_dashboard.php" class="nav-link"><i class="bi bi-house-door me-2"></i> Overview Desk</a></li>
            <li><a href="create_exam.php" class="nav-link"><i class="bi bi-file-earmark-plus me-2"></i> Create Exam</a></li>
            <li><a href="add_questions.php" class="nav-link active"><i class="bi bi-patch-question me-2"></i> Question Injector</a></li>
            <li><a href="view_student_results.php" class="nav-link"><i class="bi bi-journal-text me-2"></i> Student Results</a></li>
        </ul>
        <hr class="border-primary">
        <a href="./logout.php" class="btn btn-danger w-100 fw-bold"><i class="bi bi-box-arrow-left me-2"></i> Logout</a>
    </div>

    <div class="w-100">
        <nav class="navbar navbar-expand navbar-white bg-white border-bottom px-4 py-3 sticky-top">
            <span class="navbar-brand mb-0 h5 text-dark fw-bold">Curriculum Assessment Question Builder Engine</span>
        </nav>

        <div class="container-fluid p-4">
            <?php if($success_msg): ?> <div class="alert alert-success alert-dismissible fade show"><?= $success_msg ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div> <?php endif; ?>
            <?php if($error_msg): ?> <div class="alert alert-danger alert-dismissible fade show"><?= $error_msg ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div> <?php endif; ?>

            <div class="card p-3 mb-4 border-0 shadow-sm bg-white">
                <div class="row align-items-center g-3">
                    <div class="col-md-6">
                        <form id="examSelectionForm" method="GET" action="">
                            <label class="form-label small fw-bold text-muted mb-1">Target Evaluation Suite Focal Focus Context</label>
                            <select name="exam_id" class="form-select" onchange="this.form.submit()" required <?= $edit_mode ? 'disabled' : '' ?>>
                                <option value="">-- Select Exam Module to Manage Questions --</option>
                                <?php foreach($exams_list as $ex): ?>
                                    <option value="<?= $ex['id'] ?>" <?= $selected_exam_id === $ex['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($ex['title']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if($edit_mode): ?><input type="hidden" name="exam_id" value="<?= $selected_exam_id ?>"><?php endif; ?>
                        </form>
                    </div>
                    <div class="col-md-6 text-md-end pt-4">
                        <?php if($selected_exam_id > 0): ?>
                            <span class="badge bg-primary p-2 fs-6 me-2"><i class="bi bi-layers-half me-1"></i> Loaded: <?= count($loaded_questions) ?> MCQs</span>
                            
                            <form action="" method="POST" class="d-inline" onsubmit="return confirm('🚨 CRITICAL WARNING: This will permanently delete ALL questions in this exam. This action cannot be undone! Proceed?');">
                                <input type="hidden" name="action" value="clear_all_questions">
                                <input type="hidden" name="exam_id" value="<?= $selected_exam_id ?>">
                                <button type="submit" class="btn btn-sm btn-outline-dangerfw-bold btn-danger text-white"><i class="bi bi-trash3-fill me-1"></i> Clear All Questions</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <?php if($selected_exam_id > 0): ?>
                <div class="row g-4">
                    <div class="col-md-5">
                        <div class="card border-0 shadow-sm p-4 bg-white <?= $edit_mode ? 'edit-highlight' : '' ?>">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="fw-bold text-dark mb-0">
                                    <i class="bi <?= $edit_mode ? 'bi-pencil-square text-primary' : 'bi-file-earmark-medical text-success' ?> me-1"></i> 
                                    <?= $edit_mode ? 'Modify Question Matrix' : 'Add MCQ Question Item' ?>
                                </h5>
                                <?php if($edit_mode): ?>
                                    <a href="add_questions.php?exam_id=<?= $selected_exam_id ?>" class="btn btn-xs btn-outline-secondary btn-sm">Cancel Edit</a>
                                <?php endif; ?>
                            </div>
                            
                            <form action="" method="POST">
                                <input type="hidden" name="action" value="<?= $edit_mode ? 'update_question' : 'add_question' ?>">
                                <input type="hidden" name="exam_id" value="<?= $selected_exam_id ?>">
                                <?php if($edit_mode): ?>
                                    <input type="hidden" name="question_id" value="<?= $edit_q['id'] ?>">
                                <?php endif; ?>

                                <div class="mb-3">
                                    <label class="form-label small fw-bold text-secondary">Question Core Phrasing Text</label>
                                    <textarea name="question_text" class="form-control" rows="3" required placeholder="Type the question query text here..."><?= $edit_mode ? htmlspecialchars($edit_q['question_text']) : '' ?></textarea>
                                </div>

                                <div class="mb-2">
                                    <label class="form-label small fw-bold text-secondary">Option [A]</label>
                                    <input type="text" name="option_a" class="form-control form-control-sm" required placeholder="First option response" value="<?= $edit_mode ? htmlspecialchars($edit_q['option_a']) : '' ?>">
                                </div>
                                <div class="mb-2">
                                    <label class="form-label small fw-bold text-secondary">Option [B]</label>
                                    <input type="text" name="option_b" class="form-control form-control-sm" required placeholder="Second option response" value="<?= $edit_mode ? htmlspecialchars($edit_q['option_b']) : '' ?>">
                                </div>
                                <div class="mb-2">
                                    <label class="form-label small fw-bold text-secondary">Option [C]</label>
                                    <input type="text" name="option_c" class="form-control form-control-sm" placeholder="Third option response (Optional)" value="<?= $edit_mode ? htmlspecialchars($edit_q['option_c']) : '' ?>">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label small fw-bold text-secondary">Option [D]</label>
                                    <input type="text" name="option_d" class="form-control form-control-sm" placeholder="Fourth option response (Optional)" value="<?= $edit_mode ? htmlspecialchars($edit_q['option_d']) : '' ?>">
                                </div>

                                <div class="mb-4">
                                    <label class="form-label small fw-bold text-danger">Verified Correct Answer Mapping Key</label>
                                    <select name="correct_option" class="form-select fw-bold border-danger" required>
                                        <option value="A" <?= ($edit_mode && $edit_q['correct_option'] === 'A') ? 'selected' : '' ?>>Option [A]</option>
                                        <option value="B" <?= ($edit_mode && $edit_q['correct_option'] === 'B') ? 'selected' : '' ?>>Option [B]</option>
                                        <option value="C" <?= ($edit_mode && $edit_q['correct_option'] === 'C') ? 'selected' : '' ?>>Option [C]</option>
                                        <option value="D" <?= ($edit_mode && $edit_q['correct_option'] === 'D') ? 'selected' : '' ?>>Option [D]</option>
                                    </select>
                                </div>

                                <button type="submit" class="btn <?= $edit_mode ? 'btn-primary' : 'btn-success' ?> w-100 fw-bold">
                                    <?= $edit_mode ? 'Save Structural Changes' : 'Commit Item Allocation' ?>
                                </button>
                            </form>
                        </div>
                    </div>

                    <div class="col-md-7">
                        <div class="card border-0 shadow-sm p-4 bg-white">
                            <h5 class="fw-bold text-dark mb-3"><i class="bi bi-eye-fill me-1"></i> Questionnaire Ledger Management Panel</h5>
                            
                            <?php if(empty($loaded_questions)): ?>
                                <div class="text-center text-muted py-5">
                                    <i class="bi bi-patch-question fs-1 text-black-50 d-block mb-2"></i>No questions registered yet.
                                </div>
                            <?php else: ?>
                                <div class="accordion" id="qAccordion">
                                    <?php foreach($loaded_questions as $index => $q): 
                                        $is_approved = (int)$q['is_approved'] === 1;
                                    ?>
                                        <div class="accordion-item mb-3 border rounded shadow-sm">
                                            <div class="accordion-header d-flex justify-content-between align-items-center bg-light pe-3">
                                                <button class="accordion-button collapsed fw-bold text-dark bg-light flex-grow-1 border-0" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?= $q['id'] ?>" style="box-shadow:none;">
                                                    <span class="badge bg-secondary me-2">Q<?= $index + 1 ?></span>
                                                    <?= htmlspecialchars(substr($q['question_text'], 0, 42)) ?>...
                                                </button>
                                                
                                                <div class="d-flex align-items-center gap-1 no-print">
                                                    <form action="" method="POST" class="m-0">
                                                        <input type="hidden" name="action" value="toggle_approve">
                                                        <input type="hidden" name="question_id" value="<?= $q['id'] ?>">
                                                        <button type="submit" class="btn btn-sm btn-link p-1 text-decoration-none" title="<?= $is_approved ? 'Deactivate/Unapprove' : 'Approve Item' ?>">
                                                            <i class="bi <?= $is_approved ? 'bi-eye-fill text-success' : 'bi-eye-slash-fill text-muted' ?> fs-5"></i>
                                                        </button>
                                                    </form>

                                                    <a href="add_questions.php?edit_id=<?= $q['id'] ?>" class="btn btn-sm btn-link p-1" title="Edit Question Parameters">
                                                        <i class="bi bi-pencil-square text-primary fs-5"></i>
                                                    </a>

                                                    <form action="" method="POST" class="m-0" onsubmit="return confirm('Are you sure you want to drop this individual question parameter?');">
                                                        <input type="hidden" name="action" value="delete_question">
                                                        <input type="hidden" name="question_id" value="<?= $q['id'] ?>">
                                                        <button type="submit" class="btn btn-sm btn-link p-1 text-decoration-none" title="Drop Question Permanently">
                                                            <i class="bi bi-trash3 text-danger fs-5"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                            
                                            <div id="collapse<?= $q['id'] ?>" class="accordion-collapse collapse" data-bs-parent="#qAccordion">
                                                <div class="accordion-body bg-white" style="font-size:0.92rem;">
                                                    <p class="mb-3 border-bottom pb-2 text-dark"><strong>Full Text:</strong> <?= htmlspecialchars($q['question_text']) ?></p>
                                                    <div class="row g-2 text-secondary mb-3">
                                                        <div class="col-6"><strong>A:</strong> <?= htmlspecialchars($q['option_a']) ?></div>
                                                        <div class="col-6"><strong>B:</strong> <?= htmlspecialchars($q['option_b']) ?></div>
                                                        <div class="col-6"><strong>C:</strong> <?= htmlspecialchars($q['option_c'] ?: '---') ?></div>
                                                        <div class="col-6"><strong>D:</strong> <?= htmlspecialchars($q['option_d'] ?: '---') ?></div>
                                                    </div>
                                                    <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                                                        <span class="badge bg-success p-2">Correct Answer Key: Option [<?= $q['correct_option'] ?>]</span>
                                                        <span class="small font-monospace">Status: <?= $is_approved ? '<b class="text-success">Active/Approved</b>' : '<b class="text-warning">Hidden/Draft</b>' ?></span>
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
            <?php else: ?>
                <div class="alert alert-info py-4 text-center border-0 shadow-sm">
                    <i class="bi bi-arrow-up-circle fs-3 d-block mb-2"></i>
                    Please select an active examination module structure from the dropdown block header configuration context tool to engage layout updates.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>