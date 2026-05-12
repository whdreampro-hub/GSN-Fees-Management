<?php
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

if (isLoggedIn()) {
    redirect('admin/dashboard.php');
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    if ($username && $password) {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        try {
            $stmt = $pdo->prepare("INSERT INTO admins (username, password) VALUES (?, ?)");
            $stmt->execute([$username, $hashedPassword]);
            $message = 'Account created successfully. <a href="login.php">Login here</a>';
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                $error = 'Username already exists.';
            } else {
                $error = 'Something went wrong. Please try again.';
            }
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
    <title>Admin Sign Up - Fees Management</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="auth-container">
    <div class="card">
        <h2 class="text-center mb-4">Admin <span>Sign Up</span></h2>
        <?php if ($error): ?>
            <div style="color: var(--danger); text-align: center; margin-bottom: 1rem;"><?php echo $error; ?></div>
        <?php endif; ?>
        <?php if ($message): ?>
            <div style="color: var(--success); text-align: center; margin-bottom: 1rem;"><?php echo $message; ?></div>
        <?php endif; ?>
        <form method="POST">
            <div class="form-group">
                <label>Username</label>
                <input type="text" name="username" required placeholder="Create username">
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" required placeholder="Create password">
            </div>
            <button type="submit" class="btn btn-primary">Create Admin Account</button>
        </form>
        <p class="text-center mt-4" style="font-size: 0.9rem; color: var(--text-muted);">
            Already have an account? <a href="login.php" style="color: var(--primary-color); font-weight: 600;">Login</a>
        </p>
    </div>
</body>
</html>
