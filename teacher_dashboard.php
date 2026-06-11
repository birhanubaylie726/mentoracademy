<?php
// --- STEP 1: INITIALIZATION & MULTI-DRIVER COMPATIBILITY LAYER ---
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once './db.php';
require_once './auth.php';
verifyAccess(['teacher']);

// Smart-detect connection handles (Supports both $pdo and $conn variables seamlessly)
$db = null;
if (isset($pdo) && $pdo instanceof PDO) { $db = $pdo; } 
elseif (isset($conn) && $conn instanceof PDO) { $db = $conn; }

if (!$db) {
    die("🚨 Architectural Error: Database connection link ($pdo or $conn) could not be identified.");
}

$teacher_id = $_SESSION['user_id'] ?? 0;
$selected_exam_id = isset($_GET['exam_id']) ? intval($_GET['exam_id']) : 0;
$success_msg = '';
$error_msg = '';

// --- STEP 2: DYNAMIC DATABASE SCHEMA PATCH (RUNS SILENTLY) ---
try {
    // Ensure table structure exists with flexible support for optional multi-choice values
    $db->exec("CREATE TABLE IF NOT EXISTS questions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        exam_id INT NOT NULL,
        question_text TEXT NOT NULL,
        option_a VARCHAR(255) NOT NULL,
        option_b VARCHAR(255) NOT NULL,
        option_c VARCHAR(255) DEFAULT '',
        option_d VARCHAR(255) DEFAULT '',
        correct_option ENUM('A', 'B', 'C', 'D') NOT NULL
    )");
} catch (PDOException $e) {
    // Continue gracefully if table already exists or permissions are restricted
}

// --- STEP 3: DATA VALIDATION & INJECTION CONTROLLER ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_question') {
    $exam_id         = intval($_POST['exam_id']);
    $question_text   = trim($_POST['question_text']);
    $option_a        = trim($_POST['option_a']);
    $option_b        = trim($_POST['option_b']);
    
    // Safely assign empty strings if Option C or D are omitted (e.g., True/False configurations)
    $option_c        = isset($_POST['option_c']) ? trim($_POST['option_c']) : '';
    $option_d        = isset($_POST['option_d']) ? trim($_POST['option_d']) : '';
    $correct_option  = $_POST['correct_option'] ?? 'A';

    if ($exam_id > 0 && !empty($question_text) && !empty($option_a) && !empty($option_b)) {
        try {
            $insert_query = "INSERT INTO questions (exam_id, question_text, option_a, option_b, option_c, option_d, correct_option) VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $db->prepare($insert_query);
            $stmt->execute([$exam_id, $question_text, $option_a, $option_b, $option_c, $option_d, $correct_option]);
            
            $success_msg = "🎉 Question successfully integrated into the active evaluation pool.";
            $selected_exam_id = $exam_id; // Persist view state focal context
        } catch (PDOException $e) {
            $error_msg = "🚨 Operational Failure: Verification failed. Description: " . $e->getMessage();
        }
    } else {
        $error_msg = "⚠️ Input Omission: Please populate the core question text and at least Options A and B.";
    }
}

// --- STEP 4: TELEMETRY DATA FETCHING ---
$exams_list = [];
$loaded_questions = [];

try {
    // Fetch only exams owned by this instructor account
    $my_exams = $db->prepare("SELECT id, title FROM exams WHERE created_by = ? ORDER BY id DESC");
    $my_exams->execute([$teacher_id]);
    $exams_list = $my_exams->fetchAll();

    // Pull currently allocated items if an exam is actively selected
    if ($selected_exam_id > 0) {
        $q_stmt = $db->prepare("SELECT * FROM questions WHERE exam_id = ? ORDER BY id ASC");
        $q_stmt->execute([$selected_exam_id]);
        $loaded_questions = $q_stmt->fetchAll();
    }
} catch (PDOException $e) {
    $error_msg = "🚨 Query Failure: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Question Setup Matrix</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background-color: #f8fafc; }
        .sidebar { min-width: 260px; max-width: 260px; background: #1e3a8a; min-height: 100vh; color: #fff; }
        .sidebar .nav-link { color: #bfdbfe; font-weight: 500; }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { color: #fff; background: #1d4ed8; border-radius: 6px; }
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
        <nav class="navbar navbar-white bg-white border-bottom px-4 py-3 sticky-top">
            <span class="navbar-brand mb-0 h5 text-dark fw-bold">Question Bank Setup</span>
        </nav>

        <div class="container-fluid p-4">
            <?php if($success_msg): ?> <div class="alert alert-success shadow-sm"><?= $success_msg ?></div> <?php endif; ?>
            <?php if($error_msg): ?> <div class="alert alert-danger shadow-sm"><?= $error_msg ?></div> <?php endif; ?>

            <div class="card p-3 mb-4 border-0 shadow-sm bg-white">
                <form method="GET" action="" class="row align-items-center g-3">
                    <div class="col-md-8">
                        <label class="form-label small fw-bold text-muted mb-1">Target Exam Selector</label>
                        <select name="exam_id" class="form-select" onchange="this.form.submit()" required>
                            <option value="">-- Choose Target Exam to Manage --</option>
                            <?php foreach($exams_list as $ex): ?>
                                <option value="<?= $ex['id'] ?>" <?= $selected_exam_id === $ex['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($ex['title']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4 pt-4">
                        <?php if($selected_exam_id > 0): ?>
                            <span class="badge bg-primary p-2 fs-6"><i class="bi bi-layers-half me-1"></i> Current Size: <?= count($loaded_questions) ?> Items</span>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <?php if($selected_exam_id > 0): ?>
                <div class="row g-4">
                    <div class="col-md-5">
                        <div class="card border-0 shadow-sm p-4 bg-white">
                            <h5 class="fw-bold text-dark mb-3"><i class="bi bi-file-earmark-medical me-1"></i> Build MCQ / Binary Item</h5>
                            
                            <form action="" method="POST">
                                <input type="hidden" name="action" value="add_question">
                                <input type="hidden" name="exam_id" value="<?= $selected_exam_id ?>">

                                <div class="mb-3">
                                    <label class="form-label small fw-bold text-secondary">Question Core Content Phrasing</label>
                                    <textarea name="question_text" class="form-control" rows="3" required placeholder="Type evaluation question text here..."></textarea>
                                </div>

                                <div class="mb-2">
                                    <label class="form-label small fw-bold text-secondary">Option [A]</label>
                                    <input type="text" name="option_a" class="form-control form-control-sm" required placeholder="First option response">
                                </div>
                                <div class="mb-2">
                                    <label class="form-label small fw-bold text-secondary">Option [B]</label>
                                    <input type="text" name="option_b" class="form-control form-control-sm" required placeholder="Second option response">
                                </div>
                                <div class="mb-2">
                                    <label class="form-label small fw-bold text-secondary">Option [C] <span class="text-muted text-capitalize font-monospace">(Optional)</span></label>
                                    <input type="text" name="option_c" class="form-control form-control-sm" placeholder="Leave empty if executing binary items">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label small fw-bold text-secondary">Option [D] <span class="text-muted text-capitalize font-monospace">(Optional)</span></label>
                                    <input type="text" name="option_d" class="form-control form-control-sm" placeholder="Leave empty if executing binary items">
                                </div>

                                <div class="mb-4">
                                    <label class="form-label small fw-bold text-primary">Target Answer Solution Key</label>
                                    <select name="correct_option" class="form-select fw-bold border-primary" required>
                                        <option value="A">Option [A]</option>
                                        <option value="B">Option [B]</option>
                                        <option value="C">Option [C]</option>
                                        <option value="D">Option [D]</option>
                                    </select>
                                </div>

                                <button type="submit" class="btn btn-primary w-100 fw-bold py-2">Submit</button>
                            </form>
                        </div>
                    </div>

                    <div class="col-md-7">
                        <div class="card border-0 shadow-sm p-4 bg-white">
                            <h5 class="fw-bold text-dark mb-3"><i class="bi bi-eye-fill me-1"></i> Current Loaded Questionnaire Matrix</h5>
                            
                            <?php if(empty($loaded_questions)): ?>
                                <div class="text-center text-muted py-5">
                                    <i class="bi bi-patch-question fs-1 text-black-50 d-block mb-2"></i>
                                    No questions registered for this evaluation module yet.
                                </div>
                            <?php else: ?>
                                <div class="accordion" id="qAccordion">
                                    <?php foreach($loaded_questions as $index => $q): ?>
                                        <div class="accordion-item mb-2 border rounded">
                                            <h2 class="accordion-header">
                                                <button class="accordion-button collapsed fw-bold text-dark bg-light" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?= $q['id'] ?>">
                                                    Q<?= $index + 1 ?>: <?= htmlspecialchars(substr($q['question_text'], 0, 50)) ?>...
                                                </button>
                                            </h2>
                                            <div id="collapse<?= $q['id'] ?>" class="accordion-collapse collapse" data-bs-parent="#qAccordion">
                                                <div class="accordion-body bg-white style-context">
                                                    <p class="mb-3 border-bottom pb-2 text-dark"><strong>Full Phrasing:</strong> <?= htmlspecialchars($q['question_text']) ?></p>
                                                    <div class="row g-2 text-secondary mb-3" style="font-size:0.9rem;">
                                                        <div class="col-6"><strong>A:</strong> <?= htmlspecialchars($q['option_a']) ?></div>
                                                        <div class="col-6"><strong>B:</strong> <?= htmlspecialchars($q['option_b']) ?></div>
                                                        <?php if(!empty($q['option_c'])): ?><div class="col-6"><strong>C:</strong> <?= htmlspecialchars($q['option_c']) ?></div><?php endif; ?>
                                                        <?php if(!empty($q['option_d'])): ?><div class="col-6"><strong>D:</strong> <?= htmlspecialchars($q['option_d']) ?></div><?php endif; ?>
                                                    </div>
                                                    <span class="badge bg-success p-2">Correct Target: Option <?= htmlspecialchars($q['correct_option']) ?></span>
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
                <div class="alert alert-info py-4 text-center border-0 shadow-sm bg-white">
                    <i class="bi bi-arrow-up-circle fs-3 d-block mb-2 text-primary"></i>
                    Please select an active assessment configuration module from the dropdown tool parameters configuration matrix context above to engage updates.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>