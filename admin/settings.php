<?php
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

if (!isLoggedIn()) {
    redirect('../login.php');
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_fees'])) {
        $primary_fee = $_POST['primary_fee'];
        $secondary_fee = $_POST['secondary_fee'];
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("UPDATE fees_structure SET amount = ? WHERE section = 'Primary'");
            $stmt->execute([$primary_fee]);
            $stmt = $pdo->prepare("UPDATE fees_structure SET amount = ? WHERE section = 'Secondary'");
            $stmt->execute([$secondary_fee]);
            $pdo->commit();
            $message = "Fee structure updated successfully!";
        } catch (PDOException $e) { $pdo->rollBack(); $error = "Error: " . $e->getMessage(); }
    }
    
    if (isset($_POST['update_year'])) {
        $new_year = trim($_POST['current_year']);
        try {
            $stmt = $pdo->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = 'current_year'");
            $stmt->execute([$new_year]);
            $message = "Academic Year updated to $new_year!";
        } catch (PDOException $e) { $error = "Error: " . $e->getMessage(); }
    }
}

// Fetch current fees and year
$p_fee = getFeeAmount($pdo, 'Primary', 1);
$s_fee = getFeeAmount($pdo, 'Secondary', 1);
$current_year = getCurrentYear($pdo);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>School Settings - GSN Fees Management</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <header>
        <div class="nav-container">
            <div class="logo">GSN <span>Fees Management</span></div>
            <nav class="nav-links">
                <a href="dashboard.php">Dashboard</a>
                <a href="manage_classes.php">Manage Classes</a>
                <a href="settings.php">Settings</a>
                <a href="logout.php" style="color: var(--danger);">Logout</a>
            </nav>
        </div>
    </header>

    <main class="container">
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
            <!-- Year Settings -->
            <div class="card">
                <h2 class="mb-4">System Academic Year</h2>
                <p class="mb-4" style="color: var(--text-muted);">Enter the year range (e.g., 2025-2026).</p>
                <form method="POST">
                    <div class="form-group">
                        <label>Current Academic Year Range</label>
                        <input type="text" name="current_year" value="<?php echo htmlspecialchars($current_year); ?>" placeholder="e.g. 2025-2026" required>
                    </div>
                    <button type="submit" name="update_year" class="btn btn-primary mt-4">Update School Year</button>
                </form>
            </div>

            <!-- Fee Settings -->
            <div class="card">
                <h2 class="mb-4">Fee Structure Settings</h2>
                <p class="mb-4" style="color: var(--text-muted);">Default termly fees for Primary and Secondary sections.</p>
                <form method="POST">
                    <div class="form-group">
                        <label>Primary Section Fee (per term)</label>
                        <input type="number" name="primary_fee" value="<?php echo (int)$p_fee; ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Secondary Section Fee (per term)</label>
                        <input type="number" name="secondary_fee" value="<?php echo (int)$s_fee; ?>" required>
                    </div>
                    <button type="submit" name="update_fees" class="btn btn-primary mt-4">Update Fee Amounts</button>
                </form>
            </div>
        </div>

        <?php if ($message): ?><div style="color: var(--success); text-align: center; margin-top: 2rem; font-weight: 600;"><?php echo $message; ?></div><?php endif; ?>
        <?php if ($error): ?><div style="color: var(--danger); text-align: center; margin-top: 2rem; font-weight: 600;"><?php echo $error; ?></div><?php endif; ?>
    </main>
</body>
</html>
