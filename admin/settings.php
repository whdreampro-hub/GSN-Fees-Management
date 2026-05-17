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
            // Redirect to maintain config_year context
            header("Location: settings.php?config_year=$year_id&msg=" . urlencode("Fee structure updated successfully!"));
            exit();
        } catch (PDOException $e) { $pdo->rollBack(); $error = "Error: " . $e->getMessage(); }
    }

    // 4. COPY FEES FROM ANOTHER YEAR
    if (isset($_POST['copy_fees'])) {
        $target_year_id = (int)$_POST['target_year_id'];
        $source_year_id = (int)$_POST['source_year_id'];
        
        if ($target_year_id == $source_year_id) {
            $error = "Source and target years must be different.";
        } else {
            try {
                $pdo->beginTransaction();
                // Get fees from source year
                $stmt = $pdo->prepare("SELECT section, term, amount FROM fees_structure WHERE academic_year_id = ?");
                $stmt->execute([$source_year_id]);
                $fees = $stmt->fetchAll();
                
                if (empty($fees)) {
                    $error = "Source year has no fees defined to copy.";
                    $pdo->rollBack();
                } else {
                    foreach ($fees as $fee) {
                        $stmt = $pdo->prepare("INSERT INTO fees_structure (section, term, academic_year_id, amount) 
                                              VALUES (?, ?, ?, ?) 
                                              ON DUPLICATE KEY UPDATE amount = VALUES(amount)");
                        $stmt->execute([$fee['section'], $fee['term'], $target_year_id, $fee['amount']]);
                    }
                    $pdo->commit();
                    header("Location: settings.php?config_year=$target_year_id&msg=" . urlencode("Fees copied successfully to the target year!"));
                    exit();
                }
            } catch (PDOException $e) { $pdo->rollBack(); $error = "Error copying fees: " . $e->getMessage(); }
        }
    }
}

// Handle messages from redirects
if (isset($_GET['msg'])) $message = $_GET['msg'];

$academicYears = getAllAcademicYears($pdo);
$currentYear = getGlobalDefaultYearData($pdo);

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
                <a href="manage_classes.php">Classes</a>
                <a href="settings.php">Settings</a>
                <a href="logout.php" style="color: var(--danger);">Logout</a>
            </nav>
            <div class="year-selector" style="margin-left: 1rem;">
                <form action="switch_year.php" method="POST">
                    <select name="switch_year_id" onchange="this.form.submit()" style="padding: 0.3rem 0.5rem; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 0.85rem; font-weight: 700; color: var(--primary-color);">
                        <?php foreach ($academicYears as $y): ?>
                            <option value="<?php echo $y['id']; ?>" <?php echo $y['id'] == getCurrentYearData($pdo)['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($y['year_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>
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
                
                <?php
                // Determine which year we are configuring fees for
                $config_year_id = isset($_GET['config_year']) ? (int)$_GET['config_year'] : getCurrentYearData($pdo)['id'];
                $configYearData = getAcademicYearById($pdo, $config_year_id);
                ?>

                <form method="GET" style="margin-bottom: 1.5rem; display: flex; gap: 0.5rem; align-items: flex-end;">
                    <div style="flex: 1;">
                        <label style="font-size: 0.75rem;">Year to Configure</label>
                        <select name="config_year" style="background: white; margin-bottom: 0;">
                            <?php foreach ($academicYears as $ay): ?>
                                <option value="<?php echo $ay['id']; ?>" <?php echo $ay['id'] == $config_year_id ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($ay['year_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-secondary" style="width: auto; padding: 0.8rem 1.2rem;">Load Fees</button>
                </form>
                
                <form method="POST">
                    <input type="hidden" name="year_id" value="<?php echo $config_year_id; ?>">
                    
                    <div style="background: #f8fafc; padding: 1rem; border-radius: 12px; border: 1px solid #e2e8f0; margin-bottom: 1rem;">
                        <h4 style="font-size: 0.85rem; margin-bottom: 1rem;">Fees for: <span style="color: var(--primary-color);"><?php echo htmlspecialchars($configYearData['year_name']); ?></span></h4>
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                            <?php
                            // Fetch ACTUAL fees for the SELECTED config_year
                            $stmt = $pdo->prepare("SELECT amount FROM fees_structure WHERE section = 'Primary' AND academic_year_id = ? LIMIT 1");
                            $stmt->execute([$config_year_id]);
                            $actual_p = $stmt->fetchColumn() ?: 0;
                            
                            $stmt = $pdo->prepare("SELECT amount FROM fees_structure WHERE section = 'Secondary' AND academic_year_id = ? LIMIT 1");
                            $stmt->execute([$config_year_id]);
                            $actual_s = $stmt->fetchColumn() ?: 0;
                            ?>
                            <div class="form-group" style="margin-bottom: 0;">
                                <label style="font-size: 0.7rem;">Primary Fee / Term</label>
                                <input type="number" name="primary_fee" value="<?php echo (int)$actual_p; ?>" required style="background: white;">
                            </div>
                            <div class="form-group" style="margin-bottom: 0;">
                                <label style="font-size: 0.7rem;">Secondary Fee / Term</label>
                                <input type="number" name="secondary_fee" value="<?php echo (int)$actual_s; ?>" required style="background: white;">
                            </div>
                        </div>
                    </div>
                    <button type="submit" name="update_fees" class="btn btn-primary" style="width: 100%;">Update <?php echo htmlspecialchars($configYearData['year_name']); ?> Fees</button>
                </form>

                <hr style="margin: 2rem 0; border: none; border-top: 1px solid #e2e8f0;">
                
                <h4 style="font-size: 0.9rem; font-weight: 700; margin-bottom: 0.5rem;">Quick Setup: Copy from Previous Year</h4>
                <p style="font-size: 0.75rem; color: var(--text-muted); margin-bottom: 1rem;">Use this to quickly initialize fees for a new year by copying from an existing one.</p>
                <form method="POST" style="background: #f1f5f9; padding: 1rem; border-radius: 8px;">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem; margin-bottom: 1rem;">
                        <div>
                            <label style="font-size: 0.7rem;">Copy FROM</label>
                            <select name="source_year_id" required style="font-size: 0.8rem; padding: 0.4rem;">
                                <?php foreach ($academicYears as $ay): ?>
                                    <option value="<?php echo $ay['id']; ?>"><?php echo htmlspecialchars($ay['year_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label style="font-size: 0.7rem;">Copy TO</label>
                            <select name="target_year_id" required style="font-size: 0.8rem; padding: 0.4rem;">
                                <?php foreach ($academicYears as $ay): ?>
                                    <option value="<?php echo $ay['id']; ?>" <?php echo $ay['id'] == $config_year_id ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($ay['year_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <button type="submit" name="copy_fees" class="btn btn-secondary" style="width: 100%; font-size: 0.8rem; padding: 0.5rem;" onclick="return confirm('This will overwrite any existing fees for the target year. Continue?')">Initialize Fees</button>
                </form>
            </div>
        </div>
    </main>
</body>
</html>
