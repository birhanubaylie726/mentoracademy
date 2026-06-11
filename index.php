<?php
session_start();
require_once 'db.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    if (!empty($username) && !empty($password)) {
        // Look up the user by username
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if (!$user) {
            $error = "❌ Username not found in the database.";
        } else {
            // Direct plain-text comparison for local development troubleshooting
            if ($password === $user['password']) {
                $_SESSION['user_id']   = $user['id'];
                $_SESSION['role']      = $user['role'];
                $_SESSION['fullname']  = $user['fullname'];
                
                // Redirect to the appropriate dashboard
                // Redirect to the appropriate dashboard sitting in the root folder
switch ($user['role']) {
    case 'admin':   header("Location: admin_dashboard.php"); break;
    case 'teacher': header("Location: teacher_dashboard.php"); break;
    case 'student': header("Location: student_dashboard.php"); break;
}
exit();
            } else {
                $error = "❌ Password incorrect. You typed: '" . htmlspecialchars($password) . "'. Expected: '" . htmlspecialchars($user['password']) . "'";
            }
        }
    } else {
        $error = "❌ Please fill in both fields.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mentor Academy login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #1e293b; height: 100vh; display: flex; align-items: center; justify-content: center; }
        .login-card { border: none; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.3); background: #ffffff; }
        .btn-primary { background: #2563eb; border: none; }
    </style>
</head>
<body>
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="card login-card p-4">
                <h3 class="text-center text-dark mb-3">Mentor Academy Educational Portal</h3>
                
                <?php if($error): ?>
                    <div class="alert alert-danger py-2 text-center" style="font-size: 0.9rem;"><?= $error ?></div>
                <?php endif; ?>

                <form action="" method="POST" autocomplete="off">
                    <div class="mb-3">
                        <label class="form-label text-secondary fw-bold">Username</label>
                        <input type="text" name="username" class="form-control" required placeholder="e.g., admin">
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-secondary fw-bold">Password</label>
                        <input type="password" name="password" class="form-control" required placeholder="e.g., admin123">
                    </div>
                    <button type="submit" class="btn btn-primary w-100 py-2 fw-bold text-white">Login</button>
                </form>
                
                <div class="mt-4 pt-2 border-top">
                    <small class="text-muted d-block text-center fw-bold">Current Plaintext Credentials:</small>
                    <div class="row text-center mt-2" style="font-size: 0.78rem;">
                        <div class="col-4 border-end"><strong>Admin</strong><br>admin<br>admin123</div>
                        <div class="col-4 border-end"><strong>Teacher</strong><br>teacher1<br>teacher123</div>
                        <div class="col-4"><strong>Student</strong><br>student1<br>student123</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>