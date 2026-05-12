<?php
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

if (isLoggedIn()) {
    redirect('admin/dashboard.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    if ($username && $password) {
        $stmt = $pdo->prepare("SELECT id, password FROM admins WHERE username = ?");
        $stmt->execute([$username]);
        $admin = $stmt->fetch();
        
        if ($admin && password_verify($password, $admin['password'])) {
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_name'] = $username;
            redirect('admin/dashboard.php');
        } else {
            $error = 'Invalid username or password.';
        }
    } else {
        $error = 'Please fill in all fields.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Fees Management</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="auth-container">
    <div class="card">
        <h2 class="text-center mb-4">Admin <span>Login</span></h2>
        <?php if ($error): ?>
            <div style="color: var(--danger); text-align: center; margin-bottom: 1rem;"><?php echo $error; ?></div>
        <?php endif; ?>
        <form method="POST">
            <div class="form-group">
                <label>Username</label>
                <input type="text" name="username" required placeholder="Enter username">
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" required placeholder="Enter password">
            </div>
            <button type="submit" class="btn btn-primary">Login to Dashboard</button>
        </form>
        <p class="text-center mt-4" style="font-size: 0.9rem; color: var(--text-muted);">
            Don't have an account? <a href="signup.php" style="color: var(--primary-color); font-weight: 600;">Sign Up</a>
        </p>
    </div>
</body>
</html>
