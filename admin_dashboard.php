<?php
require_once './db.php';
require_once './auth.php';
verifyAccess(['admin']);

// 1. ACADEMIC YEAR STATE HANDLING (Persisted in session)
if (!isset($_SESSION['academic_year'])) {
    $_SESSION['academic_year'] = date('Y') . '-' . (date('Y') + 1);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_academic_year'])) {
    $_SESSION['academic_year'] = trim($_POST['academic_year']);
}

// Fetch general system counters
$total_teachers = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'teacher'")->fetchColumn();
$total_students = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student'")->fetchColumn();
$total_subjects = $pdo->query("SELECT COUNT(*) FROM subjects")->fetchColumn();
$total_exams    = $pdo->query("SELECT COUNT(*) FROM exams")->fetchColumn();

// Fetch all registered student profiles
$studentQuery = "SELECT id, fullname, username, email FROM users WHERE role = 'student' ORDER BY fullname ASC";
$students = $pdo->query($studentQuery)->fetchAll();

// Pre-fetch and arrange report card details using the exact schema from view_student_results.php
$reportCardsData = [];
foreach ($students as $student) {
    // Read academic marks recorded inside student_marks table
    $marksStmt = $pdo->prepare("SELECT sm.*, s.subject_name FROM student_marks sm 
                                JOIN subjects s ON sm.subject_id = s.id 
                                WHERE sm.student_id = ?");
    $marksStmt->execute([$student['id']]);
    $marks = $marksStmt->fetchAll();

    // Read attendance and conduct recorded inside student_quarter_meta table
    $metaStmt = $pdo->prepare("SELECT * FROM student_quarter_meta WHERE student_id = ?");
    $metaStmt->execute([$student['id']]);
    $metaRaw = $metaStmt->fetchAll();
    
    $meta = [
        'Q1' => ['conduct' => 'A', 'absent_days' => 0],
        'Q2' => ['conduct' => 'A', 'absent_days' => 0],
        'Q3' => ['conduct' => 'A', 'absent_days' => 0],
        'Q4' => ['conduct' => 'A', 'absent_days' => 0]
    ];
    foreach ($metaRaw as $m) {
        $meta[$m['quarter']] = [
            'conduct' => $m['conduct'],
            'absent_days' => $m['absent_days']
        ];
    }
    
    $reportCardsData[$student['id']] = [
        'info'  => $student,
        'marks' => $marks,
        'meta'  => $meta
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mentor Academy Admin Dashboard Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js">
    </script>
    <style>
        body { background-color: #f8fafc; }
        .sidebar { min-width: 260px; max-width: 260px; background: #0f172a; min-height: 100vh; color: #fff; }
        .sidebar .nav-link { color: #94a3b8; border-radius: 6px; margin-bottom: 4px; padding: 10px 16px; text-decoration: none; display: block; }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { background: #1e293b; color: #fff; }
        .stat-card { border: none; border-radius: 12px; }
        
        /* Layout formatting tailored specifically for clean structural PDF generation */
        .report-card-pdf-template { background: #ffffff; padding: 30px; font-family: 'Times New Roman', serif; color: #000; }
        .report-header { border-bottom: 3px double #000; padding-bottom: 10px; margin-bottom: 20px; }
        .grid-table th, .grid-table td { border: 1px solid #000 !important; padding: 6px; text-align: center; }
        .page-break { page-break-after: always; }
    </style>
</head>
<body>
<div class="d-flex">
    <div class="sidebar p-3 d-flex flex-column">
        <div class="px-3 py-2 mb-4">
            <h4 class="fw-bold mb-0 text-white"><i class="bi bi-shield-lock-fill me-2"></i>Admin Area</h4>
            <span class="text-white-50 small">System Administrator Context</span>
        </div>
        <ul class="nav nav-pills flex-column mb-auto">
            <li><a href="admin_dashboard.php" class="nav-link active"><i class="bi bi-speedometer2 me-2"></i> Control Center</a></li>
            <li><a href="manage_users.php?role=teacher" class="nav-link"><i class="bi bi-person-badge me-2"></i> Faculty Registry</a></li>
            <li><a href="manage_users.php?role=student" class="nav-link"><i class="bi bi-people me-2"></i> Student Registry</a></li>
            <li><a href="manage_subjects.php" class="nav-link"><i class="bi bi-book me-2"></i> Subjects Inventory</a></li>
            <li><a href="view_results.php" class="nav-link"><i class="bi bi-bar-chart-line me-2"></i> Exam Metric Logs</a></li>
            <li><a href="flagged_reports.php" class="nav-link"><i class="bi bi-exclamation-triangle me-2"></i> Security Alerts</a></li>
        </ul>
        <hr class="text-secondary">
        <a href="index.php" class="btn btn-danger btn-sm text-white w-100 fw-bold py-2"><i class="bi bi-box-arrow-left me-2"></i> Sign Out</a>
    </div>

    <div class="flex-grow-1 p-4" style="overflow-y: auto; max-height: 100vh;">
        
        <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom">
            <div>
                <h2 class="fw-bold text-dark mb-0">System Control Metrics Center</h2>
                <p class="text-muted mb-0">Academic Operations Tracking Matrix</p>
            </div>
            
            <div class="card p-2 shadow-sm bg-white border-0" style="min-width: 330px;">
                <form method="POST" action="" class="d-flex align-items-center gap-2 m-0">
                    <label class="small text-secondary fw-bold text-nowrap mb-0"><i class="bi bi-calendar3 me-1"></i> Term Frame:</label>
                    <select name="academic_year" class="form-select form-select-sm fw-bold">
                        <?php
                        for ($year = 1900; $year <= 2099; $year++) {
                            $nextYear = $year + 1;
                            $optionValue = "{$year}-{$nextYear}";
                            $selectedAttr = ($_SESSION['academic_year'] === $optionValue) ? 'selected' : '';
                            echo "<option value='{$optionValue}' {$selectedAttr}>Session {$optionValue}</option>";
                        }
                        ?>
                    </select>
                    <button type="submit" name="set_academic_year" class="btn btn-sm btn-dark text-white fw-bold"><i class="bi bi-check2"></i></button>
                </form>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card stat-card bg-primary text-white p-3 shadow-sm">
                    <div class="d-flex justify-content-between align-items-center">
                        <div><p class="mb-1 text-white-50">Faculty Strength</p><h3><?= $total_teachers ?></h3></div>
                        <i class="bi bi-person-workspace fs-1 text-white-50"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card bg-success text-white p-3 shadow-sm">
                    <div class="d-flex justify-content-between align-items-center">
                        <div><p class="mb-1 text-white-50">Enrolled Students</p><h3><?= $total_students ?></h3></div>
                        <i class="bi bi-people-fill fs-1 text-white-50"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card bg-warning text-dark p-3 shadow-sm">
                    <div class="d-flex justify-content-between align-items-center">
                        <div><p class="mb-1 text-dark-50">Mapped Subjects</p><h3><?= $total_subjects ?></h3></div>
                        <i class="bi bi-collection-fill fs-1 text-dark-50"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card bg-dark text-white p-3 shadow-sm">
                    <div class="d-flex justify-content-between align-items-center">
                        <div><p class="mb-1 text-white-50">Total Exams Pool</p><h3><?= $total_exams ?></h3></div>
                        <i class="bi bi-journal-check fs-1 text-white-50"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm border-0 bg-white">
            <div class="card-header bg-transparent py-3 d-flex justify-content-between align-items-center border-bottom">
                <h5 class="mb-0 fw-bold text-dark"><i class="bi bi-journal-bookmark-fill text-primary me-2"></i>Student Performance & Consolidated Reports Engine</h5>
                <div>
                    <button class="btn btn-sm btn-primary fw-bold me-2 shadow-sm" onclick="processReportCards('print', 'all')">
                        <i class="bi bi-printer-fill me-1"></i> Print All Cards (Merged)
                    </button>
                    <button class="btn btn-sm btn-success fw-bold shadow-sm" onclick="processReportCards('download', 'all')">
                        <i class="bi bi-file-earmark-pdf-fill me-1"></i> Download All (Merged PDF)
                    </button>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 text-dark">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Student Identity Profile</th>
                            <th>Username</th>
                            <th>Email Address</th>
                            <th class="text-center" style="width: 320px;">Target Document Management Operations</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($students)): ?>
                            <tr>
                                <td colspan="4" class="text-center py-4 text-muted">No student profiles found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($students as $row): ?>
                                <tr>
                                    <td class="ps-4">
                                        <div class="fw-bold text-dark"><?= htmlspecialchars($row['fullname']) ?></div>
                                        <span class="text-muted small">Academic Registry Reference ID: #<?= $row['id'] ?></span>
                                    </td>
                                    <td><code><?= htmlspecialchars($row['username']) ?></code></td>
                                    <td><?= htmlspecialchars($row['email']) ?></td>
                                    <td class="text-center">
                                        <div class="btn-group shadow-sm">
                                            <button class="btn btn-sm btn-outline-primary fw-bold" onclick="processReportCards('print', <?= $row['id'] ?>)">
                                                <i class="bi bi-printer"></i> Print
                                            </button>
                                            <button class="btn btn-sm btn-outline-success fw-bold" onclick="processReportCards('download', <?= $row['id'] ?>)">
                                                <i class="bi bi-download"></i> Download PDF
                                            </button>
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

<div id="virtual-compilation-vault" style="display: none;">
    <?php foreach ($reportCardsData as $stuId => $payload): ?>
        <div class="report-card-pdf-template page-break" id="report-card-box-<?= $stuId ?>">
            
            <div class="report-header text-center">
                <h2 class="mb-1 text-uppercase fw-bold text-dark" style="letter-spacing: 1px;">Mentor Academy International School</h2>
                <h5 class="text-muted mb-2">OFFICIAL STUDENT TRANSCRIPT REPORT CARD</h5>
                <div class="row text-start mt-4 bg-light p-3 rounded border border-secondary-subtle" style="font-size: 0.95rem; color:#000;">
                    <div class="col-6 mb-1"><strong>Full Student Name:</strong> <?= htmlspecialchars($payload['info']['fullname']) ?></div>
                    <div class="col-6 mb-1"><strong>Academic Tracker ID:</strong> #<?= $payload['info']['id'] ?></div>
                    <div class="col-6"><strong>Academic Workspace Frame:</strong> <?= htmlspecialchars($_SESSION['academic_year']) ?></div>
                    <div class="col-6"><strong>Account Username Handle:</strong> <?= htmlspecialchars($payload['info']['username']) ?></div>
                </div>
            </div>

            <div class="mb-4">
                <h5 class="fw-bold mb-2 text-dark text-uppercase" style="font-size:0.9rem;"><i class="bi bi-grid-3x3-gap-fill me-1"></i> Performance Assessment Breakdown</h5>
                <table class="table table-bordered grid-table text-dark mb-0 align-middle w-100">
                    <thead class="table-dark text-center" style="font-size: 0.88rem;">
                        <tr>
                            <th class="text-start" style="width: 40%;">Subject Name</th>
                            <th style="width: 12%;">Quarter 1</th>
                            <th style="width: 12%;">Quarter 2</th>
                            <th style="width: 12%;">Quarter 3</th>
                            <th style="width: 12%;">Quarter 4</th>
                            <th style="width: 12%;">Average</th>
                        </tr>
                    </thead>
                    <tbody style="font-size: 0.9rem;">
                        <?php 
                        // Map database input matrices into a unified grid display loop
                        $subjectGrid = [];
                        foreach ($payload['marks'] as $m) {
                            $subjectGrid[$m['subject_name']][$m['quarter']] = $m['mark'];
                        }
                        
                        if (empty($subjectGrid)): 
                        ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-3">No evaluation grades found for this student profile row context yet.</td>
                            </tr>
                        <?php 
                        else: 
                            foreach ($subjectGrid as $subName => $quarters):
                                $q1 = $quarters['Q1'] ?? null; $q2 = $quarters['Q2'] ?? null;
                                $q3 = $quarters['Q3'] ?? null; $q4 = $quarters['Q4'] ?? null;
                                $validMarks = array_filter([$q1, $q2, $q3, $q4], 'is_numeric');
                                $avg = count($validMarks) > 0 ? (array_sum($validMarks) / count($validMarks)) : 0;
                        ?>
                            <tr>
                                <td class="fw-bold text-dark text-start"><?= htmlspecialchars($subName) ?></td>
                                <td><?= $q1 !== null ? number_format($q1, 2) . '%' : '-' ?></td>
                                <td><?= $q2 !== null ? number_format($q2, 2) . '%' : '-' ?></td>
                                <td><?= $q3 !== null ? number_format($q3, 2) . '%' : '-' ?></td>
                                <td><?= $q4 !== null ? number_format($q4, 2) . '%' : '-' ?></td>
                                <td class="fw-bold bg-light text-primary"><?= count($validMarks) > 0 ? number_format($avg, 2) . '%' : '-' ?></td>
                            </tr>
                        <?php 
                            endforeach; 
                        endif; 
                        ?>
                    </tbody>
                </table>
            </div>

            <div class="row mb-4 g-2">
                <?php foreach(['Q1', 'Q2', 'Q3', 'Q4'] as $qtr): ?>
                    <div class="col-3">
                        <div class="p-2 border rounded bg-light" style="font-size:0.8rem; color:#000;">
                            <div class="fw-bold text-center border-bottom pb-1 mb-1 text-uppercase text-secondary"><?= $qtr ?> Metadata</div>
                            <div>Conduct Score: <strong><?= htmlspecialchars($payload['meta'][$qtr]['conduct']) ?></strong></div>
                            <div>Absence Counter: <strong><?= intval($payload['meta'][$qtr]['absent_days']) ?> Days</strong></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="mt-5 pt-4 border-top">
                <div class="d-flex justify-content-between text-center" style="font-size: 0.88rem; color:#000;">
                    <div style="width: 200px;"><div class="border-bottom border-dark pb-3"></div><span class="text-muted small">Registrar Signature Stamp</span></div>
                    <div style="width: 200px;"><div class="border-bottom border-dark pb-3"></div><span class="text-muted small">Homeroom Instructor</span></div>
                    <div style="width: 200px;"><div class="border-bottom border-dark pb-1 text-muted"><code><?= date('Y-m-d H:i') ?></code></div><span class="text-muted small">System Issuance Timestamp</span></div>
                </div>
            </div>

        </div>
    <?php endforeach; ?>
</div>

<script>
function processReportCards(intentType, targetingContext) {
    const sandboxContainer = document.createElement('div');
    
    if (targetingContext === 'all') {
        const templates = document.querySelectorAll('.report-card-pdf-template');
        if(templates.length === 0) {
            alert("❌ Operational Halt: No active records found to process.");
            return;
        }
        templates.forEach(node => {
            sandboxContainer.appendChild(node.cloneNode(true));
        });
    } else {
        const targetedNode = document.getElementById(`report-card-box-${targetingContext}`);
        if(!targetedNode) {
            alert("❌ Query Interruption: Target template elements could not resolve.");
            return;
        }
        const singleClone = targetedNode.cloneNode(true);
        singleClone.classList.remove('page-break'); // Strip page-break element for standalone file rules
        sandboxContainer.appendChild(singleClone);
    }

    const academicSession = document.querySelector('select[name="academic_year"]').value;
    const outputFilename = targetingContext === 'all' 
        ? `Consolidated_Report_Cards_Term_${academicSession}.pdf`
        : `Report_Card_Student_ID_#${targetingContext}_Term_${academicSession}.pdf`;

    const optionsConfig = {
        margin:       10,
        filename:     outputFilename,
        image:        { type: 'jpeg', quality: 0.98 },
        html2canvas:  { scale: 2, useCORS: true, logging: false },
        jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' }
    };

    if (intentType === 'print') {
        html2pdf().set(optionsConfig).from(sandboxContainer).toPdf().get('pdf').then(function (pdfInstance) {
            pdfInstance.autoPrint();
            window.open(pdfInstance.output('bloburl'), '_blank');
        });
    } else if (intentType === 'download') {
        html2pdf().set(optionsConfig).from(sandboxContainer).save().catch(err => {
            console.error("PDF Thread Interruption: ", err);
            alert("🚨 Processing Exception: Failed to execute document export stream.");
        });
    }
}
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>