<?php
require_once './db.php';
require_once './auth.php';
verifyAccess(['teacher']);

$teacher_id = $_SESSION['user_id'];
$success_msg = '';
$error_msg = '';

// ==========================================
// 📥 EXAM CONTROL SUITE SYSTEM CONTROLLER
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    // 1. ACTION: GENERATE NEW EXAM STRUCTURE (FIXED INSERT COLUMN MISMATCH)
    if ($action === 'create_exam') {
        $title            = trim($_POST['title']);
        $subject_id       = intval($_POST['subject_id']);
        $duration         = intval($_POST['duration']);
        $pass_percentage  = isset($_POST['pass_percentage']) ? floatval($_POST['pass_percentage']) : 50.00;
        $negative_marking = isset($_POST['negative_marking']) ? floatval($_POST['negative_marking']) : 0.00;
        $is_active        = isset($_POST['is_active']) ? 1 : 0;

        if (!empty($title) && $subject_id > 0 && $duration > 0) {
            try {
                // FIXED: Included missing database structural matrix options required by database.sql
                $stmt = $pdo->prepare("INSERT INTO exams (title, subject_id, duration, pass_percentage, negative_marking, is_active, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$title, $subject_id, $duration, $pass_percentage, $negative_marking, $is_active, $teacher_id]);
                $success_msg = "🎉 Success: New assessment blueprint has been generated and compiled.";
            } catch (PDOException $e) { 
                $error_msg = "🚨 Generation Failure: " . $e->getMessage(); 
            }
        } else { 
            $error_msg = "⚠️ Fields Error: Ensure all required fields have valid non-empty inputs."; 
        }
    }

    // 2. ACTION: TOGGLE ASSESSMENT VISIBILITY STATE
    if ($action === 'toggle_status') {
        $exam_id = intval($_POST['exam_id']);
        $current_status = intval($_POST['current_status']);
        $new_status = ($current_status === 1) ? 0 : 1;

        try {
            $stmt = $pdo->prepare("UPDATE exams SET is_active = ? WHERE id = ? AND created_by = ?");
            $stmt->execute([$new_status, $exam_id, $teacher_id]);
            $success_msg = "🔄 Success: Status has been toggled successfully.";
        } catch (PDOException $e) { 
            $error_msg = "🚨 Visibility Alteration Interrupted: " . $e->getMessage(); 
        }
    }

    // 3. ACTION: DROP EXAM ENTIRELY
    if ($action === 'delete_exam') {
        $exam_id = intval($_POST['exam_id']);

        try {
            $stmt = $pdo->prepare("DELETE FROM exams WHERE id = ? AND created_by = ?");
            $stmt->execute([$exam_id, $teacher_id]);
            $success_msg = "🗑️ Success: Target assessment record has been purged from the schema configuration grids.";
        } catch (PDOException $e) { 
            $error_msg = "🚨 Purge Halted: " . $e->getMessage(); 
        }
    }
}

// Fetch active dependencies needed to render dynamic forms
$subjects = $pdo->query("SELECT * FROM subjects ORDER BY subject_name ASC")->fetchAll();
$exams = $pdo->query("SELECT e.*, s.subject_name FROM exams e JOIN subjects s ON e.subject_id = s.id ORDER BY e.id DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assessment Blueprint Builder - Teacher Area</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background-color: #f1f5f9; }
        .sidebar { min-width: 260px; max-width: 260px; background: #1e293b; min-height: 100vh; color: #fff; }
        .sidebar .nav-link { color: #cbd5e1; border-radius: 6px; margin-bottom: 4px; padding: 10px 16px; text-decoration: none; display: block; }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { background: #334155; color: #fff; }
        .main-workspace { overflow-y: auto; max-height: 100vh; }
    </style>
</head>
<body>
<div class="d-flex">
    
    <div class="sidebar p-3 d-flex flex-column">
        <div class="px-3 py-2 mb-4">
            <h4 class="fw-bold mb-0 text-white"><i class="bi bi-journal-text me-2"></i>Faculty Deck</h4>
            <span class="text-slate-400 small">Instructor Account Panel</span>
        </div>
        <ul class="nav nav-pills flex-column mb-auto">
            <li><a href="teacher_dashboard.php" class="nav-link"><i class="bi bi-speedometer2 me-2"></i> Faculty Dashboard</a></li>
            <li><a href="create_exam.php" class="nav-link active"><i class="bi bi-file-earmark-plus me-2"></i> Build Exam Blueprints</a></li>
            <li><a href="add_questions.php" class="nav-link"><i class="bi bi-plus-circle me-2"></i> Manage Question Pools</a></li>
            <li><a href="view_student_results.php" class="nav-link"><i class="bi bi-journal-check me-2"></i> View Report Cards</a></li>
        </ul>
        <hr class="text-secondary">
        <a href="index.php" class="btn btn-danger btn-sm text-white w-100 fw-bold py-2"><i class="bi bi-box-arrow-left me-2"></i> Sign Out</a>
    </div>

    <div class="flex-grow-1 p-4 main-workspace">
        <div class="container-fluid p-0">
            
            <div class="mb-4">
                <h2 class="fw-bold text-dark">Assessment Blueprint Builder</h2>
                <p class="text-muted">Create, configure, and monitor examination parameters and grading rules.</p>
            </div>

            <?php if(!empty($success_msg)): ?>
                <div class="alert alert-success alert-dismissible fade show p-3" role="alert">
                    <?= $success_msg ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            <?php if(!empty($error_msg)): ?>
                <div class="alert alert-danger alert-dismissible fade show p-3" role="alert">
                    <?= $error_msg ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <div class="row g-4">
                <div class="col-xl-4 col-lg-5">
                    <div class="card border-0 shadow-sm p-4 bg-white">
                        <h5 class="fw-bold text-dark mb-3"><i class="bi bi-file-earmark-medical-fill text-primary me-2"></i>Generate Assessment Blueprint</h5>
                        <form action="" method="POST" autocomplete="off">
                            <input type="hidden" name="action" value="create_exam">
                            
                            <div class="mb-3">
                                <label class="form-label text-secondary fw-bold">Assessment Display Title</label>
                                <input type="text" name="title" class="form-control" placeholder="e.g., Mid-Term Examination 2026" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label text-secondary fw-bold">Target Core Subject</label>
                                <select name="subject_id" class="form-select" required>
                                    <option value="" disabled selected>-- Select Connected Subject --</option>
                                    <?php foreach($subjects as $sub): ?>
                                        <option value="<?= $sub['id'] ?>"><?= htmlspecialchars($sub['subject_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label text-secondary fw-bold">Allotted Duration (Minutes)</label>
                                <div class="input-group">
                                    <input type="number" name="duration" class="form-control" min="1" max="480" placeholder="e.g., 60" required>
                                    <span class="input-group-text bg-light text-secondary">Mins</span>
                                </div>
                            </div>

                            <div class="row mb-3 g-2">
                                <div class="col-6">
                                    <label class="form-label text-secondary fw-bold">Passing Grade (%)</label>
                                    <input type="number" name="pass_percentage" class="form-control" min="1" max="100" step="0.01" value="50.00" required>
                                </div>
                                <div class="col-6">
                                    <label class="form-label text-secondary fw-bold">Negative Marks</label>
                                    <input type="number" name="negative_marking" class="form-control" min="0" max="10" step="0.01" value="0.00" required>
                                </div>
                            </div>

                            <div class="form-check form-switch mb-4">
                                <input class="form-check-input" type="checkbox" name="is_active" id="activeToggle" value="1" checked>
                                <label class="form-check-input-label text-dark fw-bold ms-2" for="activeToggle">Activate Assessment Instantly</label>
                            </div>

                            <button type="submit" class="btn btn-primary w-100 fw-bold py-2"><i class="bi bi-cloud-upload-fill me-2"></i>Compile & Publish Blueprint</button>
                        </form>
                    </div>
                </div>

                <div class="col-xl-8 col-lg-7">
                    <div class="card border-0 shadow-sm bg-white">
                        <div class="card-header bg-transparent py-3 border-bottom">
                            <h5 class="mb-0 fw-bold text-dark"><i class="bi bi-list-columns-reverse text-success me-2"></i>Compiled Assessment Repositories</h5>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0 text-dark">
                                <thead class="table-light">
                                    <tr>
                                        <th class="ps-3">Identity Code</th>
                                        <th>Blueprint Details Title</th>
                                        <th>Target Subject</th>
                                        <th class="text-center">Limit</th>
                                        <th class="text-center">State Status</th>
                                        <th class="text-center pe-3">Control Operations</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(empty($exams)): ?>
                                        <tr>
                                            <td colspan="6" class="text-center py-4 text-muted">No configuration profiles have been declared inside this database schema instance.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach($exams as $row): ?>
                                            <tr>
                                                <td class="ps-3"><code>#EX-<?= $row['id'] ?></code></td>
                                                <td><div class="fw-bold text-dark"><?= htmlspecialchars($row['title']) ?></div></td>
                                                <td><span class="badge bg-secondary"><?= htmlspecialchars($row['subject_name']) ?></span></td>
                                                <td class="text-center fw-bold text-dark"><?= $row['duration'] ?> Mins</td>
                                                <td class="text-center">
                                                    <?= $row['is_active'] == 1 
                                                        ? '<span class="badge bg-success shadow-xs"><i class="bi bi-eye-fill me-1"></i> Open/Active</span>' 
                                                        : '<span class="badge bg-warning text-dark shadow-xs"><i class="bi bi-eye-slash-fill me-1"></i> Hidden/Draft</span>' 
                                                    ?>
                                                </td>
                                                <td class="text-center pe-3">
                                                    <div class="d-flex justify-content-center gap-1">
                                                        <a href="add_questions.php?exam_id=<?= $row['id'] ?>" class="btn btn-sm btn-outline-primary px-2 py-1" title="Append questions to this assessment layout">
                                                            <i class="bi bi-patch-plus"></i> Add Qs
                                                        </a>

                                                        <form action="" method="POST" class="m-0">
                                                            <input type="hidden" name="action" value="toggle_status">
                                                            <input type="hidden" name="exam_id" value="<?= $row['id'] ?>">
                                                            <input type="hidden" name="current_status" value="<?= $row['is_active'] ?>">
                                                            <button type="submit" class="btn btn-sm <?= $row['is_active'] == 1 ? 'btn-outline-warning' : 'btn-success text-white' ?> px-2 py-1" title="Toggle current live tracking state values">
                                                                <i class="bi <?= $row['is_active'] == 1 ? 'bi-shield-slash' : 'bi-shield-check' ?>"></i> Status
                                                            </button>
                                                        </form>

                                                        <form action="" method="POST" class="m-0" onsubmit="return confirm('🚨 CRITICAL DECISION: This will delete this assessment and all of its question pools permanently. Proceed?');">
                                                            <input type="hidden" name="action" value="delete_exam">
                                                            <input type="hidden" name="exam_id" value="<?= $row['id'] ?>">
                                                            <button type="submit" class="btn btn-sm btn-danger text-white px-2 py-1" title="Destroy configuration mapping parameters completely">
                                                                <i class="bi bi-trash3"></i> Delete
                                                            </button>
                                                        </form>
                                                    </div>
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>