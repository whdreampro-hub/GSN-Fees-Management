<?php
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

if (isLoggedIn()) {
    redirect('admin/dashboard.php');
}

$message = '';
$error = '';
$registrationOpen = (int) $pdo->query('SELECT COUNT(*) FROM admins')->fetchColumn() === 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lockAcquired = false;

    try {
        $lock = $pdo->query("SELECT GET_LOCK('gsn_fees_initial_admin_signup', 5)")->fetchColumn();
        $lockAcquired = (int) $lock === 1;

        if (!$lockAcquired) {
            $error = 'Administrator setup is busy. Please try again.';
        } else {
            $adminCount = (int) $pdo->query('SELECT COUNT(*) FROM admins')->fetchColumn();

            if ($adminCount > 0) {
                $error = 'Administrator registration is closed. Contact the existing administrator.';
            } else {
                $username = trim($_POST['username'] ?? '');
                $password = $_POST['password'] ?? '';

                if ($username === '' || !is_string($password)) {
                    $error = 'Please fill in all fields.';
                } elseif (strlen($username) > 100) {
                    $error = 'Username must be 100 characters or fewer.';
                } elseif (strlen($password) < 12) {
                    $error = 'Use a password with at least 12 characters.';
                } else {
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare('INSERT INTO admins (username, password) VALUES (?, ?)');
                    $stmt->execute([$username, $hashedPassword]);
                    $message = 'Administrator account created. You can now sign in.';
                }
            }
        }
    } catch (PDOException $e) {
        error_log('Initial administrator registration failed: ' . $e->getMessage());
        $error = $e->getCode() == 23000
            ? 'Username already exists.'
            : 'Unable to create the administrator account right now.';
    } finally {
        if ($lockAcquired) {
            try {
                $pdo->query("SELECT RELEASE_LOCK('gsn_fees_initial_admin_signup')");
            } catch (PDOException $releaseError) {
                error_log('Unable to release administrator signup lock: ' . $releaseError->getMessage());
            }
        }
    }

    $registrationOpen = (int) $pdo->query('SELECT COUNT(*) FROM admins')->fetchColumn() === 0;
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
        <h2 class="text-center mb-4">Initial Admin <span>Setup</span></h2>

        <?php if ($error): ?>
            <div role="alert" style="color: var(--danger); text-align: center; margin-bottom: 1rem;">
                <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <?php if ($message): ?>
            <div role="status" style="color: var(--success); text-align: center; margin-bottom: 1rem;">
                <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <?php if ($registrationOpen): ?>
            <p class="text-center">Create the first administrator account. Registration closes automatically after this account is created.</p>
            <form method="POST">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input id="username" type="text" name="username" maxlength="100" required placeholder="Create username">
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input id="password" type="password" name="password" minlength="12" required autocomplete="new-password" placeholder="At least 12 characters">
                </div>
                <button type="submit" class="btn btn-primary">Create Administrator</button>
            </form>
        <?php else: ?>
            <p role="status" class="text-center">Administrator registration is closed. Contact the existing administrator if you need access.</p>
        <?php endif; ?>

        <p class="text-center mt-4" style="font-size: 0.9rem; color: var(--text-muted);">
            <a href="login.php" style="color: var(--primary-color); font-weight: 600;">Go to login</a>
        </p>
    </div>
</body>
</html>
