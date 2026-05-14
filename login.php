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
<body style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 1rem;">
    <div class="card" style="max-width: 450px; width: 100%; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5); border: 1px solid rgba(255,255,255,0.05); background: rgba(255,255,255,0.98);">
        <div style="text-align: center; margin-bottom: 2rem;">
            <h1 style="font-size: 2rem; font-weight: 800; letter-spacing: -1px; margin-bottom: 0.5rem;">Admin <span style="color: var(--primary-color);">Login</span></h1>
            <p style="color: var(--text-muted); font-size: 0.9rem;">Secure access to GSN Financial Hub</p>
        </div>

        <?php if ($error): ?>
            <div style="background: #fee2e2; color: #991b1b; padding: 1rem; border-radius: 8px; text-align: center; margin-bottom: 1.5rem; font-size: 0.9rem; font-weight: 600;">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label style="font-weight: 700; font-size: 0.85rem; text-transform: uppercase; color: var(--text-muted);">Admin Username</label>
                <input type="text" name="username" required placeholder="e.g. administrator" style="padding: 1rem; border-radius: 12px; border: 1px solid #e2e8f0; width: 100%;">
            </div>
            <div class="form-group">
                <label style="font-weight: 700; font-size: 0.85rem; text-transform: uppercase; color: var(--text-muted);">Secure Password</label>
                <input type="password" name="password" required placeholder="••••••••" style="padding: 1rem; border-radius: 12px; border: 1px solid #e2e8f0; width: 100%;">
            </div>
            <button type="submit" class="btn btn-primary" style="padding: 1rem; border-radius: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; margin-top: 1rem;">Authorize & Access</button>
        </form>

        <div style="text-align: center; margin-top: 2rem; padding-top: 1.5rem; border-top: 1px solid #f1f5f9;">
            <p style="font-size: 0.85rem; color: var(--text-muted);">
                Looking for the student portal? <a href="portal.php" style="color: var(--primary-color); font-weight: 700; text-decoration: none;">Click Here</a>
            </p>
            <p style="font-size: 0.8rem; color: #cbd5e1; margin-top: 1rem;">GS Nyagisozi Finance Division</p>
        </div>
    </div>
</body>
</html>
