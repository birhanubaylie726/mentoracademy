<?php
require_once './db.php';
require_once './auth.php';
verifyAccess(['teacher']);
$teacher_id = $_SESSION['user_id'];
$success_msg = '';
$error_msg = '';

// Path configurations for your changeable logos
$logo_left_path  = "../assets/logo_left.png"; 
$logo_right_path = "../assets/logo_right.png";

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS student_marks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        subject_id INT NOT NULL,
        quarter ENUM('Q1', 'Q2', 'Q3', 'Q4') NOT NULL,
        mark DECIMAL(5,2) NOT NULL,
        UNIQUE KEY unique_mark (student_id, subject_id, quarter)
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS student_quarter_meta (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        quarter ENUM('Q1', 'Q2', 'Q3', 'Q4') NOT NULL,
        conduct VARCHAR(10) DEFAULT 'A',
        absent_days INT DEFAULT 0,
        UNIQUE KEY unique_meta (student_id, quarter)
    )");
} catch (PDOException $e) {
    die("🚨 Core Initialization Failure: " . $e->getMessage());
}

$selected_subject_id = isset($_GET['subject_id']) ? intval($_GET['subject_id']) : 0;
$selected_quarter    = isset($_GET['quarter']) ? $_GET['quarter'] : 'Q1';

// Dynamic Name Fallback Resolution Engine
$name_column = 'username';
try {
    $user_cols = $pdo->query("SHOW COLUMNS FROM `users`")->fetchAll(PDO::FETCH_COLUMN);
    if (in_array('name', $user_cols)) $name_column = 'name';
    elseif (in_array('full_name', $user_cols)) $name_column = 'full_name';
} catch (PDOException $e) {}

$subjects = $pdo->query("SELECT id, subject_name FROM subjects ORDER BY subject_name ASC")->fetchAll(PDO::FETCH_ASSOC);
$students = $pdo->query("SELECT id, {$name_column} AS name FROM users WHERE role = 'student' ORDER BY {$name_column} ASC")->fetchAll(PDO::FETCH_ASSOC);

// ==========================================
// 🛠️ ACTION PROCESSING ENGINE (SAVE & CLEAR)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'save_marks') {
        $sub_id = intval($_POST['subject_id']);
        $qtr    = $_POST['quarter'];
       
        try {
            $pdo->beginTransaction();
            
            if (isset($_POST['marks']) && is_array($_POST['marks'])) {
                foreach ($_POST['marks'] as $stu_id => $mark_value) {
                    if ($mark_value !== '') {
                        $mark = floatval($mark_value);
                        
                        // CRITICAL RULE: Prevent overwrite if an evaluation match already exists
                        $check_stmt = $pdo->prepare("SELECT id FROM student_marks WHERE student_id = ? AND subject_id = ? AND quarter = ?");
                        $check_stmt->execute([$stu_id, $sub_id, $qtr]);
                        
                        if (!$check_stmt->fetch()) {
                            $stmt = $pdo->prepare("INSERT INTO student_marks (student_id, subject_id, quarter, mark) VALUES (?, ?, ?, ?)");
                            $stmt->execute([$stu_id, $sub_id, $qtr, $mark]);
                        }
                    }
                }
            }
            
            if (isset($_POST['conduct']) && is_array($_POST['conduct'])) {
                foreach ($_POST['conduct'] as $stu_id => $cond) {
                    $abs = isset($_POST['absent_days'][$stu_id]) ? intval($_POST['absent_days'][$stu_id]) : 0;
                    
                    $meta_check = $pdo->prepare("SELECT id FROM student_quarter_meta WHERE student_id = ? AND quarter = ?");
                    $meta_check->execute([$stu_id, $qtr]);
                    
                    if (!$meta_check->fetch()) {
                        $stmt = $pdo->prepare("INSERT INTO student_quarter_meta (student_id, quarter, conduct, absent_days) VALUES (?, ?, ?, ?)");
                        $stmt->execute([$stu_id, $qtr, $cond, $abs]);
                    }
                }
            }
            
            $pdo->commit();
            $success_msg = "💾 Assessment array changes recorded. Established entries remained locked.";
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error_msg = "🚨 Core Entry Rejection: " . $e->getMessage();
        }
    }

    // NEW: Clear marks processor context
    if ($_POST['action'] === 'clear_marks') {
        $sub_id = intval($_POST['subject_id']);
        $qtr    = $_POST['quarter'];
        try {
            $stmt = $pdo->prepare("DELETE FROM student_marks WHERE subject_id = ? AND quarter = ?");
            $stmt->execute([$sub_id, $qtr]);
            $success_msg = "🗑️ Ledger entries for the target subject and quarter context have been safely purged.";
        } catch (PDOException $e) {
            $error_msg = "🚨 Core Erasure Failure: " . $e->getMessage();
        }
    }
}

$current_marks = [];
$current_meta  = [];
if ($selected_subject_id > 0) {
    $m_stmt = $pdo->prepare("SELECT student_id, mark FROM student_marks WHERE subject_id = ? AND quarter = ?");
    $m_stmt->execute([$selected_subject_id, $selected_quarter]);
    $current_marks = $m_stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    $meta_stmt = $pdo->prepare("SELECT student_id, conduct, absent_days FROM student_quarter_meta WHERE quarter = ?");
    $meta_stmt->execute([$selected_quarter]);
    while($row = $meta_stmt->fetch(PDO::FETCH_ASSOC)) {
        $current_meta[$row['student_id']] = $row;
    }
}

// ==========================================
// 🧮 MASTER LEDGER MATRIX ARRAYS GENERATOR
// ==========================================
$raw_marks = $pdo->query("SELECT student_id, subject_id, quarter, mark FROM student_marks")->fetchAll(PDO::FETCH_ASSOC);
$raw_meta  = $pdo->query("SELECT student_id, quarter, conduct, absent_days FROM student_quarter_meta")->fetchAll(PDO::FETCH_ASSOC);

$matrix = [];
foreach ($students as $s) {
    $matrix[$s['id']] = [
        'info' => $s, 'marks' => [],
        'meta' => [
            'Q1'=>['conduct'=>'-','absent'=>0], 'Q2'=>['conduct'=>'-','absent'=>0],
            'Q3'=>['conduct'=>'-','absent'=>0], 'Q4'=>['conduct'=>'-','absent'=>0]
        ]
    ];
}

foreach ($raw_marks as $m) {
    if (isset($matrix[$m['student_id']])) {
        $matrix[$m['student_id']]['marks'][$m['quarter']][$m['subject_id']] = $m['mark'];
    }
}
foreach ($raw_meta as $meta) {
    if (isset($matrix[$meta['student_id']])) {
        $matrix[$meta['student_id']]['meta'][$meta['quarter']] = ['conduct' => $meta['conduct'], 'absent' => $meta['absent_days']];
    }
}

$total_subjects_count = max(count($subjects), 1);
$row_types = ['Q1', 'Q2', 'S1_AVG', 'Q3', 'Q4', 'S2_AVG', 'YEARLY_AVG'];

foreach ($matrix as $sid => &$data) {
    // 1. Direct Quarter Computing Blocks
    foreach (['Q1','Q2','Q3','Q4'] as $q) {
        $sum = 0; $count = 0;
        foreach($subjects as $sub) {
            if (isset($data['marks'][$q][$sub['id']])) {
                $sum += $data['marks'][$q][$sub['id']];
                $count++;
            }
        }
        $avg = $total_subjects_count > 0 ? ($sum / $total_subjects_count) : 0;
        $data['summary'][$q] = ['sum' => $sum, 'avg' => $avg];
    }
    
    // 2. Semesters & Yearly Calculation Loops
    $s1_sum = 0; $s2_sum = 0; $yr_sum = 0;
    foreach($subjects as $sub) {
        $q1 = isset($data['marks']['Q1'][$sub['id']]) ? $data['marks']['Q1'][$sub['id']] : 0;
        $q2 = isset($data['marks']['Q2'][$sub['id']]) ? $data['marks']['Q2'][$sub['id']] : 0;
        $q3 = isset($data['marks']['Q3'][$sub['id']]) ? $data['marks']['Q3'][$sub['id']] : 0;
        $q4 = isset($data['marks']['Q4'][$sub['id']]) ? $data['marks']['Q4'][$sub['id']] : 0;
        
        $s1_sub_avg = ($q1 + $q2) / 2;
        $s2_sub_avg = ($q3 + $q4) / 2;
        $yr_sub_avg = ($q1 + $q2 + $q3 + $q4) / 4;
        
        $data['derived_metrics']['S1_AVG'][$sub['id']] = $s1_sub_avg;
        $data['derived_metrics']['S2_AVG'][$sub['id']] = $s2_sub_avg;
        $data['derived_metrics']['YEARLY_AVG'][$sub['id']] = $yr_sub_avg;
        
        $s1_sum += $s1_sub_avg;
        $s2_sum += $s2_sub_avg;
        $yr_sum += $yr_sub_avg;
    }
    
    $data['summary']['S1_AVG'] = ['sum' => $s1_sum, 'avg' => ($s1_sum / $total_subjects_count)];
    $data['summary']['S2_AVG'] = ['sum' => $s2_sum, 'avg' => ($s2_sum / $total_subjects_count)];
    $data['summary']['YEARLY_AVG'] = ['sum' => $yr_sum, 'avg' => ($yr_sum / $total_subjects_count)];
}
unset($data);

// 3. Dynamic Rank Assignment System
foreach ($row_types as $type) {
    uasort($matrix, function($a, $b) use ($type) { return $b['summary'][$type]['avg'] <=> $a['summary'][$type]['avg']; });
    $rank = 1; foreach ($matrix as $sid => $v) { $matrix[$sid]['ranks'][$type] = $rank++; }
}
uasort($matrix, function($a, $b) { return strcmp($a['info']['name'], $b['info']['name']); });

// ==========================================
// 📥 NEW: LIVE ROSTER DOWNLOAD ENGINE (CSV)
// ==========================================
if (isset($_GET['download']) && $_GET['download'] === 'roster') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=Master_Cohort_Roster_Ledger.csv');
    $out = fopen('php://output', 'w');
    
    $headers = ['Student Name', 'Assessment Cycle'];
    foreach($subjects as $sub) { $headers[] = $sub['subject_name']; }
    foreach(['Sum', 'Avg', 'Rank', 'Conduct', 'Absences'] as $h) { $headers[] = $h; }
    fputcsv($out, $headers);

    foreach ($matrix as $row) {
        foreach ($row_types as $type) {
            $line = [$row['info']['name'], $type];
            foreach ($subjects as $sub) {
                if (in_array($type, ['Q1','Q2','Q3','Q4'])) {
                    $line[] = isset($row['marks'][$type][$sub['id']]) ? $row['marks'][$type][$sub['id']] : '-';
                } else {
                    $line[] = number_format($row['derived_metrics'][$type][$sub['id']], 1);
                }
            }
            if ($type === 'YEARLY_AVG') {
                $line[] = '-';
                $line[] = number_format($row['summary'][$type]['avg'], 2);
            } else {
                $line[] = number_format($row['summary'][$type]['sum'], 1);
                $line[] = number_format($row['summary'][$type]['avg'], 1);
            }
            $line[] = '#' . $row['ranks'][$type];
            
            if (in_array($type, ['Q1','Q2','Q3','Q4'])) {
                $line[] = $row['meta'][$type]['conduct'];
                $line[] = $row['meta'][$type]['absent'];
            } else {
                $line[] = '-';
                if ($type === 'S1_AVG') $line[] = ($row['meta']['Q1']['absent'] + $row['meta']['Q2']['absent']);
                elseif ($type === 'S2_AVG') $line[] = ($row['meta']['Q3']['absent'] + $row['meta']['Q4']['absent']);
                else $line[] = ($row['meta']['Q1']['absent'] + $row['meta']['Q2']['absent'] + $row['meta']['Q3']['absent'] + $row['meta']['Q4']['absent']);
            }
            fputcsv($out, $line);
        }
    }
    fclose($out);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Mentor Academy - Grading Terminal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background-color: #f1f5f9; font-family: system-ui, sans-serif; }
        .sidebar { width: 250px; background: #0f172a; min-height: 100vh; color: #fff; }
        .sidebar .nav-link { color: #94a3b8; font-weight: 500; border-radius: 6px; }
        .sidebar .nav-link.active, .sidebar .nav-link:hover { color: #fff; background: #2563eb; }
        .card-custom { border-radius: 8px; border: none; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        
        /* High-Density Roster Styling Rules */
        .table-roster { border: 1px solid #475569 !important; font-size: 0.76rem !important; }
        .table-roster th, .table-roster td { border: 1px solid #475569 !important; padding: 4px !important; text-align: center; }
        .table-roster th { background: #1e293b !important; color: white !important; }
        .row-q12 { background-color: #f8fafc; }
        .row-s1 { background-color: #e2e8f0; font-weight: bold; color: #1e3a8a; }
        .row-q34 { background-color: #f0fdf4; }
        .row-s2 { background-color: #dcfce7; font-weight: bold; color: #15803d; }
        .row-yr { background-color: #fef9c3; font-weight: bold; font-size: 0.8rem; color: #a16207; }

        /* Report Card Structural Core Blueprint Styles */
        .rc-panel { background: #fff; border: 4px double #000; padding: 25px; margin-bottom: 30px; color: #000; }
        .rc-logo { height: 75px; width: 75px; object-fit: contain; }
        .rc-section-hdr { font-weight: 700; text-transform: uppercase; background: #f1f5f9; padding: 5px 8px; font-size: 0.85rem; border: 1px solid #000; margin: 15px 0 10px 0; }
        .table-report { border: 1px solid #000 !important; font-size: 0.85rem; }
        .table-report th, .table-report td { border: 1px solid #000 !important; padding: 5px !important; text-align: center; }

        @media print {
            .no-print, .sidebar, .navbar { display: none !important; }
            body { background: #fff; color: #000; }
            .container-fluid { padding: 0 !important; }
            .card-custom { border: none !important; box-shadow: none !important; }
            .rc-panel { page-break-after: always; border: 2px solid #000; }
            .d-none { display: block !important; }
        }
    </style>
</head>
<body>
<div class="d-flex">
    <div class="sidebar p-3 d-flex flex-column no-print">
        <h5 class="text-center py-2 border-bottom border-secondary mb-4 fw-bold text-info">MENTOR PORTAL</h5>
        <ul class="nav flex-column mb-auto gap-1">
            <li><a href="teacher_dashboard.php" class="nav-link"><i class="bi bi-speedometer2 me-2"></i> Dashboard</a></li>
            <li><a href="view_student_results.php" class="nav-link active"><i class="bi bi-journal-text me-2"></i> Report Desk</a></li>
        </ul>
        <a href="./logout.php" class="btn btn-sm btn-danger w-100 fw-bold"><i class="bi bi-box-arrow-left me-1"></i> Sign Out</a>
    </div>

    <div class="w-100">
        <nav class="navbar navbar-light bg-white border-bottom px-4 py-3 sticky-top no-print">
            <span class="navbar-brand mb-0 h6 fw-bold text-dark"><i class="bi bi-shield-check me-2"></i>Mentor Academy Assessment Console</span>
        </nav>

        <div class="container-fluid p-4">
            <?php if($success_msg): ?> <div class="alert alert-success alert-dismissible fade show no-print"><?= $success_msg ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div> <?php endif; ?>
            <?php if($error_msg): ?> <div class="alert alert-danger alert-dismissible fade show no-print"><?= $error_msg ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div> <?php endif; ?>

            <ul class="nav nav-tabs mb-4 no-print" id="dashboardTabs" role="tablist">
                <li class="nav-item"><button class="nav-link active" id="entry-tab" data-bs-toggle="tab" data-bs-target="#entry-pane" type="button">📥 Result Upload Desk</button></li>
                <li class="nav-item"><button class="nav-link" id="roster-tab" data-bs-toggle="tab" data-bs-target="#roster-pane" type="button">📊 Master Roster Ledger</button></li>
                <li class="nav-item"><button class="nav-link" id="report-tab" data-bs-toggle="tab" data-bs-target="#report-pane" type="button">📜 Student Report Cards</button></li>
            </ul>

            <div class="tab-content">
                <div class="tab-pane fade show active no-print" id="entry-pane" role="tabpanel">
                    <div class="card card-custom p-4 bg-white border">
                        <form method="GET" action="" class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-muted">Subject Line Focus</label>
                                <select name="subject_id" class="form-select" onchange="this.form.submit()" required>
                                    <option value="">-- Choose Subject Course --</option>
                                    <?php foreach($subjects as $sub): ?>
                                        <option value="<?= $sub['id'] ?>" <?= $selected_subject_id === $sub['id'] ? 'selected' : '' ?>><?= htmlspecialchars($sub['subject_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-muted">Assessment Quarter</label>
                                <select name="quarter" class="form-select" onchange="this.form.submit()">
                                    <?php foreach(['Q1','Q2','Q3','Q4'] as $q): ?>
                                        <option value="<?= $q ?>" <?= $selected_quarter === $q ? 'selected' : '' ?>><?= $q ?> Cycle</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </form>

                        <?php if ($selected_subject_id > 0): ?>
                            <div class="d-flex justify-content-end gap-2 mb-3">
                                <form action="" method="POST" onsubmit="return confirm('⚠️ CRITICAL WARNING: Are you sure you want to purge all marks for this subject and cycle? This process is irreversible.');">
                                    <input type="hidden" name="action" value="clear_marks">
                                    <input type="hidden" name="subject_id" value="<?= $selected_subject_id ?>">
                                    <input type="hidden" name="quarter" value="<?= $selected_quarter ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger fw-bold"><i class="bi bi-trash3-fill me-1"></i> Clear Selection Marks</button>
                                </form>
                            </div>

                            <form action="" method="POST">
                                <input type="hidden" name="action" value="save_marks">
                                <input type="hidden" name="subject_id" value="<?= $selected_subject_id ?>">
                                <input type="hidden" name="quarter" value="<?= $selected_quarter ?>">
                                
                                <table class="table table-bordered align-middle">
                                    <thead class="table-light small text-uppercase">
                                        <tr>
                                            <th>Student Profile Name</th>
                                            <th style="width: 25%;">Mark (Max 100)</th>
                                            <th style="width: 25%;">Conduct Evaluation</th>
                                            <th style="width: 25%;">Days Absent</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($students as $st): 
                                            $has_mark = isset($current_marks[$st['id']]);
                                            $m_val = $has_mark ? $current_marks[$st['id']] : '';
                                            $c_val = isset($current_meta[$st['id']]['conduct']) ? $current_meta[$st['id']]['conduct'] : 'A';
                                            $a_val = isset($current_meta[$st['id']]['absent_days']) ? $current_meta[$st['id']]['absent_days'] : 0;
                                        ?>
                                            <tr>
                                                <td class="fw-bold text-dark">
                                                    <?= htmlspecialchars($st['name']) ?>
                                                    <?php if($has_mark): ?> <span class="badge bg-lock text-dark border ms-2 font-monospace" style="font-size:0.68rem; background-color:#fee2e2;"><i class="bi bi-lock-fill"></i> COMMITTED</span> <?php endif; ?>
                                                </td>
                                                <td>
                                                    <input type="number" step="0.01" min="0" max="100" name="marks[<?= $st['id'] ?>]" class="form-control" placeholder="0.00" value="<?= $m_val ?>" <?= $has_mark ? 'readonly style="background-color:#f1f5f9; color:#64748b;"' : '' ?>>
                                                </td>
                                                <td>
                                                    <select name="conduct[<?= $st['id'] ?>]" class="form-select fw-bold" <?= $has_mark ? 'disabled style="background-color:#f1f5f9; color:#64748b;"' : '' ?>>
                                                        <?php foreach(['A','B','C','D','F'] as $g): ?>
                                                            <option value="<?= $g ?>" <?= $c_val === $g ? 'selected' : '' ?>><?= $g ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </td>
                                                <td>
                                                    <input type="number" min="0" name="absent_days[<?= $st['id'] ?>]" class="form-control" value="<?= $a_val ?>" <?= $has_mark ? 'readonly style="background-color:#f1f5f9; color:#64748b;"' : '' ?>>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                                <button type="submit" class="btn btn-primary fw-bold px-4 mt-3">Commit Marks Ledger</button>
                            </form>
                        <?php else: ?>
                            <div class="alert alert-light text-center border py-4 text-muted"><i class="bi bi-funnel me-2"></i>Select an active course line and assessment quarter to initialize the upload spreadsheet matrix.</div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="tab-pane fade" id="roster-pane" role="tabpanel">
                    <div class="card card-custom p-4 bg-white border">
                        <div class="d-flex justify-content-between align-items-center mb-3 no-print">
                            <h6 class="fw-bold m-0 text-dark"><i class="bi bi-grid-3x3-gap me-2"></i>OFFICIAL MASTER COHORT RECORD LEDGER ROSTER</h6>
                            <div class="d-flex gap-2">
                                <a href="?download=roster" class="btn btn-sm btn-success fw-bold px-3"><i class="bi bi-file-earmark-spreadsheet me-1"></i> Download Roster CSV</a>
                                <button onclick="window.print()" class="btn btn-sm btn-dark fw-bold px-3"><i class="bi bi-printer-fill me-1"></i> Print Master Roster</button>
                            </div>
                        </div>
                        
                        <div class="table-responsive">
                            <table class="table table-bordered table-roster font-monospace text-center align-middle">
                                <thead>
                                    <tr>
                                        <th>Student Full Name</th>
                                        <th>Assessment Param</th>
                                        <?php foreach($subjects as $sub): ?>
                                            <th style="min-width:75px; font-size:0.7rem;"><?= htmlspecialchars($sub['subject_name']) ?></th>
                                        <?php endforeach; ?>
                                        <th class="table-dark">Sum</th>
                                        <th class="table-dark">Avg</th>
                                        <th class="table-dark">Rank</th>
                                        <th class="table-dark">Conduct</th>
                                        <th class="table-dark">Abs</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($matrix as $sid => $row): ?>
                                        <tr class="row-q12">
                                            <td rowspan="7" class="text-start fw-bold bg-white text-dark border-end-thick" style="font-size:0.8rem; min-width:160px;"><?= htmlspecialchars($row['info']['name']) ?></td>
                                            <td class="fw-bold text-secondary">Quarter 1</td>
                                            <?php foreach($subjects as $sub): ?>
                                                <td><?= isset($row['marks']['Q1'][$sub['id']]) ? number_format($row['marks']['Q1'][$sub['id']], 1) : '-' ?></td>
                                            <?php endforeach; ?>
                                            <td><?= number_format($row['summary']['Q1']['sum'], 1) ?></td>
                                            <td><?= number_format($row['summary']['Q1']['avg'], 1) ?></td>
                                            <td class="fw-bold text-primary">#<?= $row['ranks']['Q1'] ?></td>
                                            <td><?= $row['meta']['Q1']['conduct'] ?></td>
                                            <td><?= $row['meta']['Q1']['absent'] ?></td>
                                        </tr>
                                        <tr class="row-q12">
                                            <td class="fw-bold text-secondary">Quarter 2</td>
                                            <?php foreach($subjects as $sub): ?>
                                                <td><?= isset($row['marks']['Q2'][$sub['id']]) ? number_format($row['marks']['Q2'][$sub['id']], 1) : '-' ?></td>
                                            <?php endforeach; ?>
                                            <td><?= number_format($row['summary']['Q2']['sum'], 1) ?></td>
                                            <td><?= number_format($row['summary']['Q2']['avg'], 1) ?></td>
                                            <td class="fw-bold text-primary">#<?= $row['ranks']['Q2'] ?></td>
                                            <td><?= $row['meta']['Q2']['conduct'] ?></td>
                                            <td><?= $row['meta']['Q2']['absent'] ?></td>
                                        </tr>
                                        <tr class="row-s1">
                                            <td>S1 Average</td>
                                            <?php foreach($subjects as $sub): ?>
                                                <td><?= number_format($row['derived_metrics']['S1_AVG'][$sub['id']], 1) ?></td>
                                            <?php endforeach; ?>
                                            <td><?= number_format($row['summary']['S1_AVG']['sum'], 1) ?></td>
                                            <td><?= number_format($row['summary']['S1_AVG']['avg'], 1) ?></td>
                                            <td>#<?= $row['ranks']['S1_AVG'] ?></td>
                                            <td>-</td>
                                            <td><?= ($row['meta']['Q1']['absent'] + $row['meta']['Q2']['absent']) ?></td>
                                        </tr>
                                        <tr class="row-q34">
                                            <td class="fw-bold text-secondary">Quarter 3</td>
                                            <?php foreach($subjects as $sub): ?>
                                                <td><?= isset($row['marks']['Q3'][$sub['id']]) ? number_format($row['marks']['Q3'][$sub['id']], 1) : '-' ?></td>
                                            <?php endforeach; ?>
                                            <td><?= number_format($row['summary']['Q3']['sum'], 1) ?></td>
                                            <td><?= number_format($row['summary']['Q3']['avg'], 1) ?></td>
                                            <td class="fw-bold text-primary">#<?= $row['ranks']['Q3'] ?></td>
                                            <td><?= $row['meta']['Q3']['conduct'] ?></td>
                                            <td><?= $row['meta']['Q3']['absent'] ?></td>
                                        </tr>
                                        <tr class="row-q34">
                                            <td class="fw-bold text-secondary">Quarter 4</td>
                                            <?php foreach($subjects as $sub): ?>
                                                <td><?= isset($row['marks']['Q4'][$sub['id']]) ? number_format($row['marks']['Q4'][$sub['id']], 1) : '-' ?></td>
                                            <?php endforeach; ?>
                                            <td><?= number_format($row['summary']['Q4']['sum'], 1) ?></td>
                                            <td><?= number_format($row['summary']['Q4']['avg'], 1) ?></td>
                                            <td class="fw-bold text-primary">#<?= $row['ranks']['Q4'] ?></td>
                                            <td><?= $row['meta']['Q4']['conduct'] ?></td>
                                            <td><?= $row['meta']['Q4']['absent'] ?></td>
                                        </tr>
                                        <tr class="row-s2">
                                            <td>S2 Average</td>
                                            <?php foreach($subjects as $sub): ?>
                                                <td><?= number_format($row['derived_metrics']['S2_AVG'][$sub['id']], 1) ?></td>
                                            <?php endforeach; ?>
                                            <td><?= number_format($row['summary']['S2_AVG']['sum'], 1) ?></td>
                                            <td><?= number_format($row['summary']['S2_AVG']['avg'], 1) ?></td>
                                            <td>#<?= $row['ranks']['S2_AVG'] ?></td>
                                            <td>-</td>
                                            <td><?= ($row['meta']['Q3']['absent'] + $row['meta']['Q4']['absent']) ?></td>
                                        </tr>
                                        <tr class="row-yr">
                                            <td>Year Average</td>
                                            <?php foreach($subjects as $sub): ?>
                                                <td><?= number_format($row['derived_metrics']['YEARLY_AVG'][$sub['id']], 1) ?></td>
                                            <?php endforeach; ?>
                                            <td>-</td>
                                            <td class="bg-warning text-dark fs-6"><?= number_format($row['summary']['YEARLY_AVG']['avg'], 2) ?></td>
                                            <td class="bg-dark text-white fs-6">#<?= $row['ranks']['YEARLY_AVG'] ?></td>
                                            <td>-</td>
                                            <td><?= ($row['meta']['Q1']['absent'] + $row['meta']['Q2']['absent'] + $row['meta']['Q3']['absent'] + $row['meta']['Q4']['absent']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="tab-pane fade" id="report-pane" role="tabpanel">
                    <div class="row g-3 mb-4 no-print">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-muted">Select Target Portfolio Profile</label>
                            <select id="reportCardSelector" class="form-select" onchange="switchReportCardContext(this.value)">
                                <option value="">-- Choose Profile Sheet --</option>
                                <?php foreach($students as $st): ?>
                                    <option value="card_box_<?= $st['id'] ?>"><?= htmlspecialchars($st['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 d-flex align-items-end gap-2 justify-content-md-end">
                            <button onclick="printSingleCard()" class="btn btn-outline-dark fw-bold"><i class="bi bi-printer me-1"></i> Print Selected Card</button>
                            <button onclick="printMergedReportCards()" class="btn btn-primary fw-bold"><i class="bi bi-file-earmark-pdf-fill me-1"></i> Download Merged Report Cards (PDF)</button>
                        </div>
                    </div>
                    
                    <div id="reportCardsRoot">
                        <?php foreach($matrix as $sid => $st_data): ?>
                            <div class="student-report-card-wrapper-node d-none" id="card_box_<?= $sid ?>">
                                <div class="rc-panel">     
                                    <div class="d-flex justify-content-between align-items-center border-bottom border-dark pb-3 mb-4">
                                        <img src="<?= $logo_left_path ?>" alt="Left Logo" class="rc-logo" onerror="this.src='https://via.placeholder.com/80?text=Left+Logo'">
                                        <div class="text-center flex-grow-1 mx-2">
                                            <h2 class="fw-bold text-uppercase m-0" style="color: #1e3a8a; letter-spacing:1px;">Mentor Academy</h2>
                                            <h5 class="fw-bold text-uppercase text-secondary m-0 mt-1" style="font-size:1.05rem;">Official Student Performance Report Card</h5>
                                            <p class="text-muted small m-0 font-monospace">Academic Evaluation Registry System Summary</p>
                                        </div>
                                        <img src="<?= $logo_right_path ?>" alt="Right Logo" class="rc-logo" onerror="this.src='https://via.placeholder.com/80?text=Right+Logo'">
                                    </div>
                                    
                                    <div class="rc-section-hdr">I. Academic Biography Details</div>
                                    <div class="row g-2 font-monospace mb-3" style="font-size: 0.88rem;">
                                        <div class="col-sm-6"><strong>Student Full Name:</strong> <span class="text-primary border-bottom pb-1 fw-bold"><?= htmlspecialchars($st_data['info']['name']) ?></span></div>
                                        <div class="col-sm-6 text-sm-end"><strong>Unique System Ref Key:</strong> #0026<?= $sid ?></div>
                                        <div class="col-sm-6"><strong>Academic Year Session:</strong> 2026/2027 E.C.</div>
                                        <div class="col-sm-6 text-sm-end"><strong>Stream Class Level:</strong> Registered Grade Line</div>
                                    </div>
                                    
                                    <div class="rc-section-hdr">II. Complete Evaluation performance Matrix Grid</div>
                                    <table class="table table-bordered table-report align-middle font-monospace">
                                        <thead>
                                            <tr class="table-light text-uppercase" style="font-size:0.78rem;">
                                                <th class="text-start">Subject Name Course</th>
                                                <th>Q1</th>
                                                <th>Q2</th>
                                                <th class="table-secondary">S1 Avg</th>
                                                <th>Q3</th>
                                                <th>Q4</th>
                                                <th class="table-secondary">S2 Avg</th>
                                                <th class="table-warning text-dark">Year Avg</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach($subjects as $sub): ?>
                                                <tr>
                                                    <td class="text-start fw-bold"><?= htmlspecialchars($sub['subject_name']) ?></td>
                                                    <td><?= isset($st_data['marks']['Q1'][$sub['id']]) ? number_format($st_data['marks']['Q1'][$sub['id']], 1) : '-' ?></td>
                                                    <td><?= isset($st_data['marks']['Q2'][$sub['id']]) ? number_format($st_data['marks']['Q2'][$sub['id']], 1) : '-' ?></td>
                                                    <td class="fw-bold bg-light"><?= number_format($st_data['derived_metrics']['S1_AVG'][$sub['id']], 1) ?></td>
                                                    <td><?= isset($st_data['marks']['Q3'][$sub['id']]) ? number_format($st_data['marks']['Q3'][$sub['id']], 1) : '-' ?></td>
                                                    <td><?= isset($st_data['marks']['Q4'][$sub['id']]) ? number_format($st_data['marks']['Q4'][$sub['id']], 1) : '-' ?></td>
                                                    <td class="fw-bold bg-light"><?= number_format($st_data['derived_metrics']['S2_AVG'][$sub['id']], 1) ?></td>
                                                    <td class="fw-bold table-warning text-dark"><?= number_format($st_data['derived_metrics']['YEARLY_AVG'][$sub['id']], 1) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                            <tr class="table-dark font-monospace fw-bold" style="font-size:0.82rem;">
                                                <td class="text-start text-uppercase">Cohort General Average</td>
                                                <td><?= number_format($st_data['summary']['Q1']['avg'], 1) ?></td>
                                                <td><?= number_format($st_data['summary']['Q2']['avg'], 1) ?></td>
                                                <td><?= number_format($st_data['summary']['S1_AVG']['avg'], 1) ?></td>
                                                <td><?= number_format($st_data['summary']['Q3']['avg'], 1) ?></td>
                                                <td><?= number_format($st_data['summary']['Q4']['avg'], 1) ?></td>
                                                <td><?= number_format($st_data['summary']['S2_AVG']['avg'], 1) ?></td>
                                                <td class="bg-warning text-dark font-weight-black text-center fs-6"><?= number_format($st_data['summary']['YEARLY_AVG']['avg'], 2) ?></td>
                                            </tr>
                                            <tr class="font-monospace text-uppercase text-secondary fw-bold" style="font-size:0.75rem;">
                                                <td class="text-start text-dark">Institutional Rank Classification</td>
                                                <td>#<?= $st_data['ranks']['Q1'] ?></td>
                                                <td>#<?= $st_data['ranks']['Q2'] ?></td>
                                                <td>#<?= $st_data['ranks']['S1_AVG'] ?></td>
                                                <td>#<?= $st_data['ranks']['Q3'] ?></td>
                                                <td>#<?= $st_data['ranks']['Q4'] ?></td>
                                                <td>#<?= $st_data['ranks']['S2_AVG'] ?></td>
                                                <td class="text-dark bg-light font-weight-black fs-6">#<?= $st_data['ranks']['YEARLY_AVG'] ?></td>
                                            </tr>
                                        </tbody>
                                    </table>

                                    <div class="rc-section-hdr">III. Institutional Metadata Roster Dynamics</div>
                                    <div class="row font-monospace text-center mt-2" style="font-size:0.8rem;">
                                        <div class="col-3 border-end"><strong>Q1 Conduct:</strong> <?= $st_data['meta']['Q1']['conduct'] ?> (Abs: <?= $st_data['meta']['Q1']['absent'] ?>)</div>
                                        <div class="col-3 border-end"><strong>Q2 Conduct:</strong> <?= $st_data['meta']['Q2']['conduct'] ?> (Abs: <?= $st_data['meta']['Q2']['absent'] ?>)</div>
                                        <div class="col-3 border-end"><strong>Q3 Conduct:</strong> <?= $st_data['meta']['Q3']['conduct'] ?> (Abs: <?= $st_data['meta']['Q3']['absent'] ?>)</div>
                                        <div class="col-3"><strong>Q4 Conduct:</strong> <?= $st_data['meta']['Q4']['conduct'] ?> (Abs: <?= $st_data['meta']['Q4']['absent'] ?>)</div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function switchReportCardContext(cardId) {
    document.querySelectorAll('.student-report-card-wrapper-node').forEach(el => {
        el.classList.add('d-none');
    });
    if (cardId) {
        document.getElementById(cardId).classList.remove('d-none');
    }
}

/**
 * Mentor Academy - Grading Terminal
 * Monitored Print & Export Operations
 */

// A. Fix for: Print Selected Card Button
function printSelectedReportCard(event) {
    if (event) event.preventDefault(); // Stop form submission loops instantly
    
    // Trigger native printing directly for the currently visible card
    window.print();
}

// B. Fix for: Download / Print Merged Cards Button
function printMergedReportCards(event) {
    if (event) event.preventDefault(); // Stop form submission loops instantly

    const allCards = document.querySelectorAll('.student-report-card-wrapper-node');
    const selector = document.getElementById('reportCardSelector');
    
    // Safety Guard Clause: Prevent code execution on missing DOM elements
    if (!allCards.length) {
        console.error("Terminal Error: No report card wrapper nodes detected in DOM.");
        alert("Error: No report cards found to process.");
        return;
    }

    // Capture the active selection state before making DOM modifications
    const originalViewContext = selector ? selector.value : null;

    try {
        // Step 1: Force reveal all cards for the print spooler
        allCards.forEach(card => {
            card.classList.remove('d-none');
            card.classList.add('print-forced-layout'); // Class helper for consistent styling
        });

        // Step 2: Define a definitive cleanup operation
        const executeLayoutCleanup = () => {
            allCards.forEach(card => {
                card.classList.remove('print-forced-layout');
            });

            // Safely revert back to the student they were viewing pre-print
            if (originalViewContext && typeof switchReportCardContext === 'function') {
                switchReportCardContext(originalViewContext);
            }
            
            // Remove event listener to prevent memory leaks
            window.removeEventListener('afterprint', executeLayoutCleanup);
        };

        // Step 3: Register native browser print lifecycle hook
        window.addEventListener('afterprint', executeLayoutCleanup, { once: true });

        // Step 4: Yield the main execution thread (150ms buffer)
        // This stops the infinite window spinning by allowing the browser to paint layouts cleanly
        setTimeout(() => {
            window.print();
            
            // Fallback runner: If a browser environment blocks 'afterprint', force cleanup after 1 second
            setTimeout(executeLayoutCleanup, 1000);
        }, 150);

    } catch (error) {
        console.error("Critical Failure in Print Module Execution:", error);
        // Fail-safe: Ensure view is restored even if an error occurs
        if (originalViewContext && typeof switchReportCardContext === 'function') {
            switchReportCardContext(originalViewContext);
        }
    }
}
</script>
</body>
</html>