<?php
require_once './db.php';
require_once './auth.php';
verifyAccess(['admin']);

// --- DATABASE INTEGRITY SAFEGUARD MIGRATION ---
$pdo->exec("CREATE TABLE IF NOT EXISTS system_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT NOT NULL
)");

// Establish fallback values if empty configuration cache table blocks are detected
$defaults = [
    'portal_name'        => 'Mentor Academy High School - Testing Portal',
    'allow_registration' => '1',
    'maintenance_mode'   => '0'
];
foreach ($defaults as $key => $val) {
    $ins = $pdo->prepare("INSERT IGNORE INTO system_settings (setting_key, setting_value) VALUES (?, ?)");
    $ins->execute([$key, $val]);
}

$success_msg = '';

// --- POST INTERCEPTION: SAVE ALL SETTINGS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_settings') {
    foreach ($_POST['settings'] as $key => $value) {
        $upd = $pdo->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = ?");
        $upd->execute([trim($value), $key]);
    }
    $success_msg = "⚙️ Core Parameters Manifest tracking arrays saved locally within configuration databases cache systems.";
}

// Extract updated key-value arrays maps
$raw_settings = $pdo->query("SELECT setting_key, setting_value FROM system_settings")->fetchAll();
$sys = [];
foreach ($raw_settings as $row) {
    $sys[$row['setting_key']] = $row['setting_value'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Global System Manifest Configurations - Admin Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background-color: #f8fafc; }
        .sidebar { min-width: 260px; max-width: 260px; background: #0f172a; min-height: 100vh; color: #fff; }
        .sidebar .nav-link { color: #94a3b8; font-weight: 500; }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { color: #fff; background: #1e293b; border-radius: 6px; }
    </style>
</head>
<body>
<div class="d-flex">
    <div class="sidebar p-3 d-flex flex-column">
        <h4 class="text-center py-3 border-bottom border-secondary mb-4 fw-bold">Portal Admin</h4>
        <ul class="nav nav-pills flex-column mb-auto gap-1">
            <li><a href="admin_dashboard.php" class="nav-link"><i class="bi bi-speedometer2 me-2"></i> Dashboard</a></li>
            <li><a href="manage_users.php?role=teacher" class="nav-link"><i class="bi bi-person-badge me-2"></i> Manage Teachers</a></li>
            <li><a href="manage_users.php?role=student" class="nav-link"><i class="bi bi-people me-2"></i> Manage Students</a></li>
            <li><a href="manage_subjects.php" class="nav-link"><i class="bi bi-book me-2"></i> Manage Subjects</a></li>
            <li><a href="manage_exams.php" class="nav-link"><i class="bi bi-journal-check me-2"></i> Manage Exams</a></li>
            <li><a href="schedules.php" class="nav-link"><i class="bi bi-calendar-event me-2"></i> Schedules</a></li>
            <li><a href="flagged_reports.php" class="nav-link"><i class="bi bi-flag me-2"></i> Flagged Reports</a></li>
            <li><a href="system_settings.php" class="nav-link active"><i class="bi bi-gear me-2"></i> System Settings</a></li>
        </ul>
        <hr class="border-secondary">
        <a href="./logout.php" class="btn btn-danger w-100 fw-bold"><i class="bi bi-box-arrow-left me-2"></i> Logout</a>
    </div>

    <div class="w-100">
        <nav class="navbar navbar-expand navbar-white bg-white border-bottom px-4 py-3 sticky-top">
            <span class="navbar-brand mb-0 h5 text-dark fw-bold">System Parameters Engine Matrix Control</span>
        </nav>

        <div class="container-fluid p-4">
            <?php if($success_msg): ?> <div class="alert alert-success"><?= $success_msg ?></div> <?php endif; ?>

            <div class="card border-0 shadow-sm p-4 bg-white" style="max-width: 700px;">
                <h5 class="fw-bold text-dark mb-4 border-bottom pb-2"><i class="bi bi-sliders me-1"></i> Core Operational Parameters</h5>
                
                <form action="" method="POST">
                    <input type="hidden" name="action" value="save_settings">
                    
                    <div class="mb-4">
                        <label class="form-label fw-bold text-secondary mb-1">Global Application Title Identifier</label>
                        <input type="text" name="settings[portal_name]" class="form-control" required value="<?= htmlspecialchars($sys['portal_name']) ?>">
                        <div class="form-text text-muted">Defines the branding text displayed on user interfaces and browser tabs.</div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold text-secondary mb-1">Self-Registration Gate Controls</label>
                        <select name="settings[allow_registration]" class="form-select">
                            <option value="1" <?= $sys['allow_registration'] === '1' ? 'selected' : '' ?>>Open Access (Allow user self-registration)</option>
                            <option value="0" <?= $sys['allow_registration'] === '0' ? 'selected' : '' ?>>Closed Access (Admin data enrollment only)</option>
                        </select>
                        <div class="form-text text-muted">Determines if external students can register accounts manually via index shortcuts.</div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold text-secondary mb-1">Operational Mode Configuration</label>
                        <select name="settings[maintenance_mode]" class="form-select">
                            <option value="0" <?= $sys['maintenance_mode'] === '0' ? 'selected' : '' ?>>Production Mode (System open for active examination operations)</option>
                            <option value="1" <?= $sys['maintenance_mode'] === '1' ? 'selected' : '' ?>>Maintenance Lock (Suspends student access pools for auditing revisions)</option>
                        </select>
                        <div class="form-text text-muted">Locks student workstations completely during administrative maintenance updates.</div>
                    </div>

                    <button type="submit" class="btn btn-primary fw-bold px-4 py-2 mt-2"><i class="bi bi-save me-1"></i> Save Configuration Manifest</button>
                </form>
            </div>
        </div>
    </div>
</div>
</body>
</html>