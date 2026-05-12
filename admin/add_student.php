<?php
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

if (!isLoggedIn()) {
    redirect('../login.php');
}

// Check for pre-filled data
$p_section = isset($_GET['section']) ? $_GET['section'] : '';
$p_class = isset($_GET['class']) ? $_GET['class'] : '';
$p_stream = isset($_GET['stream']) ? $_GET['stream'] : '';
$mode = isset($_GET['mode']) ? $_GET['mode'] : 'single';

$message = '';
$error = '';

// Handle Single Registration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['single_register'])) {
    $full_name = trim($_POST['full_name']);
    $section = $_POST['section'];
    $current_class = $_POST['current_class'];
    $current_stream = $_POST['current_stream'];
    
    if ($full_name && $section && $current_class) {
        // DUPLICATE CHECK
        $check = $pdo->prepare("SELECT id FROM students WHERE full_name = ? AND current_class = ? AND current_stream = ?");
        $check->execute([$full_name, $current_class, $current_stream]);
        
        if ($check->fetch()) {
            $error = "Error: A student named '$full_name' is already registered in $current_class $current_stream.";
        } else {
            $reg_number = generateRegNumber($pdo);
            try {
                $stmt = $pdo->prepare("INSERT INTO students (reg_number, full_name, section, current_class, current_stream) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$reg_number, $full_name, $section, $current_class, $current_stream]);
                $message = "Student registered successfully! Reg: $reg_number";
            } catch (PDOException $e) { $error = "Error: " . $e->getMessage(); }
        }
    }
}

// Handle Bulk CSV Upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_register'])) {
    if (isset($_FILES['student_list']) && $_FILES['student_list']['error'] == 0) {
        $file = $_FILES['student_list']['tmp_name'];
        if (($handle = fopen($file, "r")) !== FALSE) {
            fgetcsv($handle); // Skip header
            $success_count = 0;
            $duplicate_count = 0;
            $pdo->beginTransaction();
            try {
                while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                    if (count($data) >= 1) {
                        $name = trim($data[0]);
                        $sect = (isset($data[1]) && !empty($data[1])) ? trim($data[1]) : $p_section;
                        $clss = (isset($data[2]) && !empty($data[2])) ? trim($data[2]) : $p_class;
                        $strm = (isset($data[3]) && !empty($data[3])) ? trim($data[3]) : $p_stream;
                        
                        if ($name && $sect && $clss) {
                            // DUPLICATE CHECK
                            $check = $pdo->prepare("SELECT id FROM students WHERE full_name = ? AND current_class = ? AND current_stream = ?");
                            $check->execute([$name, $clss, $strm]);
                            
                            if ($check->fetch()) {
                                $duplicate_count++;
                                continue; // Skip this student
                            }

                            $reg = generateRegNumber($pdo);
                            $stmt = $pdo->prepare("INSERT INTO students (reg_number, full_name, section, current_class, current_stream) VALUES (?, ?, ?, ?, ?)");
                            $stmt->execute([$reg, $name, $sect, $clss, $strm]);
                            $success_count++;
                        }
                    }
                }
                $pdo->commit();
                $message = "Import complete! Success: $success_count | Skipped (Duplicates): $duplicate_count";
            } catch (Exception $e) { $pdo->rollBack(); $error = "Import failed: " . $e->getMessage(); }
            fclose($handle);
        }
    } else { $error = "Please select a valid CSV file."; }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Students - GSN Fees Management</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .mode-selector { display: flex; gap: 1rem; margin-bottom: 2rem; border-bottom: 2px solid var(--border-color); padding-bottom: 1rem; }
        .mode-btn { padding: 0.5rem 1rem; cursor: pointer; font-weight: 600; color: var(--text-muted); border-radius: 8px; text-decoration: none; }
        .mode-btn.active { background: var(--primary-color); color: white; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
    </style>
</head>
<body>
    <header>
        <div class="nav-container">
            <div class="logo">GSN <span>Fees Management</span></div>
            <nav class="nav-links">
                <a href="dashboard.php">Dashboard</a>
                <a href="manage_classes.php">Manage Classes</a>
                <a href="logout.php" style="color: var(--danger);">Logout</a>
            </nav>
        </div>
    </header>

    <main class="container">
        <div class="card" style="margin: 0 auto; max-width: 600px;">
            <div class="mode-selector">
                <a href="?mode=single&section=<?php echo $p_section; ?>&class=<?php echo $p_class; ?>&stream=<?php echo $p_stream; ?>" class="mode-btn <?php echo $mode == 'single' ? 'active' : ''; ?>">Single Entry</a>
                <a href="?mode=bulk&section=<?php echo $p_section; ?>&class=<?php echo $p_class; ?>&stream=<?php echo $p_stream; ?>" class="mode-btn <?php echo $mode == 'bulk' ? 'active' : ''; ?>">Upload CSV List</a>
            </div>

            <?php if ($message): ?><div style="color: var(--success); margin-bottom: 1rem; font-weight: 600;"><?php echo $message; ?></div><?php endif; ?>
            <?php if ($error): ?><div style="color: var(--danger); margin-bottom: 1rem; font-weight: 600;"><?php echo $error; ?></div><?php endif; ?>

            <!-- Single Entry Form -->
            <div class="tab-content <?php echo $mode == 'single' ? 'active' : ''; ?>">
                <h3 class="mb-4">Register One Student</h3>
                <form method="POST">
                    <div class="form-group">
                        <label>Full Name</label>
                        <input type="text" name="full_name" required placeholder="Enter name">
                    </div>
                    <div class="form-group">
                        <label>Section</label>
                        <select name="section" required>
                            <option value="Primary" <?php echo $p_section == 'Primary' ? 'selected' : ''; ?>>Primary</option>
                            <option value="Secondary" <?php echo $p_section == 'Secondary' ? 'selected' : ''; ?>>Secondary</option>
                        </select>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label>Class</label>
                            <input type="text" name="current_class" required value="<?php echo $p_class; ?>">
                        </div>
                        <div class="form-group">
                            <label>Stream</label>
                            <input type="text" name="current_stream" value="<?php echo $p_stream; ?>">
                        </div>
                    </div>
                    <button type="submit" name="single_register" class="btn btn-primary mt-4">Save Student</button>
                </form>
            </div>

            <!-- Bulk Entry Form -->
            <div class="tab-content <?php echo $mode == 'bulk' ? 'active' : ''; ?>">
                <h3 class="mb-2">Upload Student List</h3>
                <p class="mb-4" style="font-size: 0.85rem; color: var(--text-muted);">
                    Upload a CSV file. If you are adding to <strong><?php echo $p_class ?: 'a new class'; ?></strong>, 
                    the CSV only needs the student names in the first column.
                </p>
                <form method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <label>Select CSV File</label>
                        <input type="file" name="student_list" accept=".csv" required>
                    </div>
                    <button type="submit" name="bulk_register" class="btn btn-primary mt-4">Import All Students</button>
                    <a href="../sample_students.csv" download class="btn btn-secondary mt-2" style="width: 100%; background: #94a3b8; font-size: 0.85rem;">Download Sample CSV Template</a>
                </form>
                <div style="background: #f1f5f9; padding: 1rem; border-radius: 8px; margin-top: 1.5rem; font-size: 0.8rem;">
                    <strong>Note:</strong> The system will automatically skip any students who already exist in the target class.
                </div>
            </div>

            <a href="manage_classes.php" style="display: block; text-align: center; margin-top: 2rem; color: var(--text-muted); text-decoration: none; font-size: 0.9rem;">&larr; Back to Classes</a>
        </div>
    </main>
</body>
</html>
