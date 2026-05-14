<?php
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

if (!isLoggedIn()) {
    redirect('../login.php');
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. ADD NEW ACADEMIC YEAR
    if (isset($_POST['add_year'])) {
        $year_name = trim($_POST['year_name']);
        try {
            $stmt = $pdo->prepare("INSERT INTO academic_years (year_name) VALUES (?)");
            $stmt->execute([$year_name]);
            $message = "New academic year '$year_name' added successfully!";
        } catch (PDOException $e) { $error = "Error adding year: " . $e->getMessage(); }
    }
    
    // 2. SET CURRENT ACADEMIC YEAR
    if (isset($_POST['set_current_year'])) {
        $year_id = (int)$_POST['year_id'];
        try {
            $pdo->beginTransaction();
            $pdo->exec("UPDATE academic_years SET is_current = 0");
            $stmt = $pdo->prepare("UPDATE academic_years SET is_current = 1 WHERE id = ?");
            $stmt->execute([$year_id]);
            
            // Also update the old system_settings for backward compatibility if any
            $year_name = $pdo->query("SELECT year_name FROM academic_years WHERE id = $year_id")->fetchColumn();
            $stmt = $pdo->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = 'current_year'");
            $stmt->execute([$year_name]);
            
            $pdo->commit();
            $message = "Current workspace switched to '$year_name'!";
        } catch (PDOException $e) { $pdo->rollBack(); $error = "Error: " . $e->getMessage(); }
    }

    // 3. UPDATE FEES FOR SELECTED YEAR
    if (isset($_POST['update_fees'])) {
        $year_id = (int)$_POST['year_id'];
        $p_fee = $_POST['primary_fee'];
        $s_fee = $_POST['secondary_fee'];
        
        try {
            $pdo->beginTransaction();
            foreach (['Primary' => $p_fee, 'Secondary' => $s_fee] as $section => $amount) {
                for ($term = 1; $term <= 3; $term++) {
                    $stmt = $pdo->prepare("INSERT INTO fees_structure (section, term, academic_year_id, amount) 
                                          VALUES (?, ?, ?, ?) 
                                          ON DUPLICATE KEY UPDATE amount = VALUES(amount)");
                    $stmt->execute([$section, $term, $year_id, $amount]);
                }
            }
            $pdo->commit();
            $message = "Fee structure updated for the selected year!";
        } catch (PDOException $e) { $pdo->rollBack(); $error = "Error: " . $e->getMessage(); }
    }
}

$academicYears = getAllAcademicYears($pdo);
$currentYear = getCurrentYearData($pdo);

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
        <h2 class="mb-4">Global System Configuration</h2>

        <?php if ($message): ?><div style="background: #dcfce7; color: #166534; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; font-weight: 600;"><?php echo $message; ?></div><?php endif; ?>
        <?php if ($error): ?><div style="background: #fee2e2; color: #991b1b; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; font-weight: 600;"><?php echo $error; ?></div><?php endif; ?>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; align-items: start;">
            <!-- Year Management -->
            <div class="card" style="max-width: 100%;">
                <h3 class="mb-4">Academic Workspaces</h3>
                
                <form method="POST" style="margin-bottom: 2rem; background: #f8fafc; padding: 1rem; border-radius: 12px; border: 1px solid var(--border-color);">
                    <label>Add New Academic Year</label>
                    <div style="display: flex; gap: 0.5rem;">
                        <input type="text" name="year_name" placeholder="e.g. 2026-2027" required style="background: white;">
                        <button type="submit" name="add_year" class="btn btn-primary" style="width: auto;">Add</button>
                    </div>
                </form>

                <label>Active Workspace (Operational Year)</label>
                <form method="POST">
                    <select name="year_id" style="background: white; margin-bottom: 1rem;">
                        <?php foreach ($academicYears as $ay): ?>
                            <option value="<?php echo $ay['id']; ?>" <?php echo $ay['is_current'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($ay['year_name']); ?> <?php echo $ay['is_current'] ? '(CURRENT)' : ''; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" name="set_current_year" class="btn btn-secondary" style="width: 100%;">Set as Global Active Year</button>
                </form>
            </div>

            <!-- Fee Configuration -->
            <div class="card" style="max-width: 100%;">
                <h3 class="mb-4">Financial Structure</h3>
                <p class="mb-4" style="font-size: 0.85rem; color: var(--text-muted);">Configure default term fees for a specific year.</p>
                
                <form method="POST">
                    <div class="form-group">
                        <label>Target Academic Year</label>
                        <select name="year_id" required style="background: white;">
                            <?php foreach ($academicYears as $ay): ?>
                                <option value="<?php echo $ay['id']; ?>" <?php echo $ay['is_current'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($ay['year_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <?php
                        // Fetch sample fees for the current year to pre-fill
                        $stmt = $pdo->prepare("SELECT amount FROM fees_structure WHERE section = 'Primary' AND academic_year_id = ? LIMIT 1");
                        $stmt->execute([$currentYear['id']]);
                        $sample_p = $stmt->fetchColumn() ?: 0;
                        
                        $stmt = $pdo->prepare("SELECT amount FROM fees_structure WHERE section = 'Secondary' AND academic_year_id = ? LIMIT 1");
                        $stmt->execute([$currentYear['id']]);
                        $sample_s = $stmt->fetchColumn() ?: 0;
                        ?>
                        <div class="form-group">
                            <label>Primary Fee / Term</label>
                            <input type="number" name="primary_fee" value="<?php echo (int)$sample_p; ?>" required style="background: white;">
                        </div>
                        <div class="form-group">
                            <label>Secondary Fee / Term</label>
                            <input type="number" name="secondary_fee" value="<?php echo (int)$sample_s; ?>" required style="background: white;">
                        </div>
                    </div>
                    <button type="submit" name="update_fees" class="btn btn-primary mt-4">Save Fee Structure</button>
                </form>
            </div>
        </div>
    </main>
</body>
</html>
