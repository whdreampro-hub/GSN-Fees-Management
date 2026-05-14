<?php
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

if (!isLoggedIn()) {
    redirect('../login.php');
}

// Check for pre-filled data
$p_section = isset($_GET['section']) ? $_GET['section'] : 'Secondary';
$p_class_id = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
$p_stream = isset($_GET['stream']) ? $_GET['stream'] : '';
$mode = isset($_GET['mode']) ? $_GET['mode'] : 'single';

$currentYearData = getCurrentYearData($pdo);
$academicYears = getAllAcademicYears($pdo);
$classes = getClasses($pdo);

$message = '';
$error = '';

// Handle Single Registration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['single_register'])) {
    $full_name = trim($_POST['full_name']);
    $section = $_POST['section'];
    $class_id = (int)$_POST['class_id'];
    $stream = trim($_POST['stream']);
    $academic_year_id = (int)$_POST['academic_year_id'];
    
    if ($full_name && $section && $class_id && $academic_year_id) {
        $pdo->beginTransaction();
        try {
            // 1. Create Student Identity
            $reg_number = generateRegNumber($pdo);
            $stmt = $pdo->prepare("INSERT INTO students (reg_number, full_name, section, current_class, current_stream) VALUES (?, ?, ?, '', '')");
            $stmt->execute([$reg_number, $full_name, $section]);
            $student_id = $pdo->lastInsertId();
            
            // 2. Enroll Student
            enrollStudent($pdo, $student_id, $class_id, $stream, $academic_year_id, $section);
            
            $pdo->commit();
            $message = "Student registered and enrolled successfully! Reg: $reg_number";
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Error: " . $e->getMessage();
        }
    } else {
        $error = "All fields are required.";
    }
}

// Handle Bulk CSV Upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_register'])) {
    $target_class_id = (int)$_POST['class_id'];
    $target_year_id = (int)$_POST['academic_year_id'];
    $target_section = $_POST['section'];
    $target_stream = trim($_POST['stream']);

    if (isset($_FILES['student_list']) && $_FILES['student_list']['error'] == 0) {
        $file = $_FILES['student_list']['tmp_name'];
        if (($handle = fopen($file, "r")) !== FALSE) {
            fgetcsv($handle); // Skip header
            $success_count = 0;
            $pdo->beginTransaction();
            try {
                while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                    if (count($data) >= 1) {
                        $name = trim($data[0]);
                        if ($name) {
                            $reg = generateRegNumber($pdo);
                            $stmt = $pdo->prepare("INSERT INTO students (reg_number, full_name, section, current_class, current_stream) VALUES (?, ?, ?, '', '')");
                            $stmt->execute([$reg, $name, $target_section]);
                            $student_id = $pdo->lastInsertId();
                            
                            enrollStudent($pdo, $student_id, $target_class_id, $target_stream, $target_year_id, $target_section);
                            $success_count++;
                        }
                    }
                }
                $pdo->commit();
                $message = "Import complete! Successfully registered and enrolled $success_count students.";
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
                <a href="?mode=single&section=<?php echo $p_section; ?>&class_id=<?php echo $p_class_id; ?>&stream=<?php echo $p_stream; ?>" class="mode-btn <?php echo $mode == 'single' ? 'active' : ''; ?>">Single Entry</a>
                <a href="?mode=bulk&section=<?php echo $p_section; ?>&class_id=<?php echo $p_class_id; ?>&stream=<?php echo $p_stream; ?>" class="mode-btn <?php echo $mode == 'bulk' ? 'active' : ''; ?>">Upload CSV List</a>
            </div>

            <?php if ($message): ?><div style="color: var(--success); margin-bottom: 1rem; font-weight: 600;"><?php echo $message; ?></div><?php endif; ?>
            <?php if ($error): ?><div style="color: var(--danger); margin-bottom: 1rem; font-weight: 600;"><?php echo $error; ?></div><?php endif; ?>

            <!-- Single Entry Form -->
            <div class="tab-content <?php echo $mode == 'single' ? 'active' : ''; ?>">
                <h3 class="mb-4">Register One Student</h3>
                <form method="POST">
                    <div class="form-group">
                        <label>Academic Year</label>
                        <select name="academic_year_id" required>
                            <?php foreach ($academicYears as $ay): ?>
                                <option value="<?php echo $ay['id']; ?>" <?php echo $ay['is_current'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($ay['year_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Full Name</label>
                        <input type="text" name="full_name" required placeholder="Enter student's full name">
                    </div>
                    <div class="form-group">
                        <label>Section</label>
                        <select name="section" required id="section-select">
                            <option value="Primary" <?php echo $p_section == 'Primary' ? 'selected' : ''; ?>>Primary</option>
                            <option value="Secondary" <?php echo $p_section == 'Secondary' ? 'selected' : ''; ?>>Secondary</option>
                        </select>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label>Class</label>
                            <select name="class_id" required>
                                <?php foreach ($classes as $c): ?>
                                    <option value="<?php echo $c['id']; ?>" data-section="<?php echo $c['section']; ?>" <?php echo $c['id'] == $p_class_id ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($c['class_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Stream</label>
                            <input type="text" name="stream" value="<?php echo htmlspecialchars($p_stream); ?>" placeholder="e.g. A, B, North">
                        </div>
                    </div>
                    <button type="submit" name="single_register" class="btn btn-primary mt-4">Save and Enroll Student</button>
                </form>
            </div>

            <!-- Bulk Entry Form -->
            <div class="tab-content <?php echo $mode == 'bulk' ? 'active' : ''; ?>">
                <h3 class="mb-2">Bulk Enrollment</h3>
                <p class="mb-4" style="font-size: 0.85rem; color: var(--text-muted);">
                    Upload a CSV file containing student names. All students in the file will be enrolled in the target class and year selected below.
                </p>
                <form method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <label>Target Academic Year</label>
                        <select name="academic_year_id" required>
                            <?php foreach ($academicYears as $ay): ?>
                                <option value="<?php echo $ay['id']; ?>" <?php echo $ay['is_current'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($ay['year_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Target Section</label>
                        <select name="section" required>
                            <option value="Primary" <?php echo $p_section == 'Primary' ? 'selected' : ''; ?>>Primary</option>
                            <option value="Secondary" <?php echo $p_section == 'Secondary' ? 'selected' : ''; ?>>Secondary</option>
                        </select>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label>Target Class</label>
                            <select name="class_id" required>
                                <?php foreach ($classes as $c): ?>
                                    <option value="<?php echo $c['id']; ?>" data-section="<?php echo $c['section']; ?>" <?php echo $c['id'] == $p_class_id ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($c['class_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Target Stream</label>
                            <input type="text" name="stream" value="<?php echo htmlspecialchars($p_stream); ?>">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Select CSV File (Names only in first column)</label>
                        <input type="file" name="student_list" accept=".csv" required>
                    </div>
                    <button type="submit" name="bulk_register" class="btn btn-primary mt-4">Process Bulk Import</button>
                    <a href="../sample_students.csv" download class="btn btn-secondary mt-2" style="width: 100%; background: #94a3b8; font-size: 0.85rem;">Download Template</a>
                </form>
            </div>

            <a href="manage_classes.php" style="display: block; text-align: center; margin-top: 2rem; color: var(--text-muted); text-decoration: none; font-size: 0.9rem;">&larr; Back to Classes</a>
        </div>
    </main>
</body>
</html>
