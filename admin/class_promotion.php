<?php
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

if (!isLoggedIn()) {
    redirect('../login.php');
}

$section = isset($_GET['section']) ? $_GET['section'] : '';
$class_name = isset($_GET['class']) ? $_GET['class'] : '';
$stream = isset($_GET['stream']) ? $_GET['stream'] : '';

if (!$class_name) {
    redirect('manage_classes.php');
}

$stmt = $pdo->prepare("SELECT * FROM students WHERE section = ? AND current_class = ? AND current_stream = ? ORDER BY full_name ASC");
$stmt->execute([$section, $class_name, $stream]);
$students = $stmt->fetchAll();

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['promote_selected'])) {
    $selected_students = isset($_POST['student_ids']) ? $_POST['student_ids'] : [];
    $target_section = $_POST['target_section'];
    $target_class = $_POST['target_class'];
    $target_stream = $_POST['target_stream'];
    
    if (!empty($selected_students) && $target_class) {
        try {
            $pdo->beginTransaction();
            
            foreach ($selected_students as $student_id) {
                // Log history for each student
                $hist = $pdo->prepare("INSERT INTO academic_history (student_id, old_section, old_class, old_stream, new_section, new_class, new_stream) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $hist->execute([$student_id, $section, $class_name, $stream, $target_section, $target_class, $target_stream]);
                
                // Update current status
                $stmt = $pdo->prepare("UPDATE students SET section = ?, current_class = ?, current_stream = ? WHERE id = ?");
                $stmt->execute([$target_section, $target_class, $target_stream, $student_id]);
            }
            
            $pdo->commit();
            $message = "Successfully promoted " . count($selected_students) . " students. History recorded.";
            
            // Refresh list
            $stmt = $pdo->prepare("SELECT * FROM students WHERE section = ? AND current_class = ? AND current_stream = ? ORDER BY full_name ASC");
            $stmt->execute([$section, $class_name, $stream]);
            $students = $stmt->fetchAll();
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = "Error: " . $e->getMessage();
        }
    } else {
        $error = "Please select students and a target class.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Class Promotion - GSN Fees Management</title>
    <link rel="stylesheet" href="../css/style.css">
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
        <div style="margin-bottom: 2rem;">
            <a href="manage_classes.php" style="color: var(--text-muted); text-decoration: none;">&larr; Back to Classes</a>
            <h2 class="mt-4">Promoting Students from: <?php echo "$class_name $stream ($section)"; ?></h2>
        </div>

        <?php if ($message): ?><div style="color: var(--success); margin-bottom: 1.5rem; padding: 1rem; background: #dcfce7; border-radius: 8px;"><?php echo $message; ?></div><?php endif; ?>
        <?php if ($error): ?><div style="color: var(--danger); margin-bottom: 1.5rem; padding: 1rem; background: #fee2e2; border-radius: 8px;"><?php echo $error; ?></div><?php endif; ?>

        <form method="POST">
            <div style="background: #eff6ff; padding: 1.5rem; border-radius: 12px; margin-bottom: 2rem; border: 1px solid #bfdbfe;">
                <h3 class="mb-4">Target Information</h3>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                    <div class="form-group">
                        <label>Target Section</label>
                        <select name="target_section" required>
                            <option value="Primary" <?php echo $section == 'Primary' ? 'selected' : ''; ?>>Primary</option>
                            <option value="Secondary" <?php echo $section == 'Secondary' ? 'selected' : ''; ?>>Secondary</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Target Class (e.g. S4)</label>
                        <input type="text" name="target_class" required>
                    </div>
                    <div class="form-group">
                        <label>Target Stream</label>
                        <input type="text" name="target_stream" value="<?php echo htmlspecialchars($stream); ?>" required>
                    </div>
                </div>
            </div>

            <div class="dashboard-card">
                <h3 class="mb-4">Select Students to Move</h3>
                <div class="table-container" style="box-shadow: none; border: 1px solid var(--border-color);">
                    <table>
                        <thead>
                            <tr>
                                <th><input type="checkbox" id="select-all" checked></th>
                                <th>Reg Number</th>
                                <th>Full Name</th>
                                <th>Current Class</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students as $student): ?>
                                <tr>
                                    <td><input type="checkbox" name="student_ids[]" value="<?php echo $student['id']; ?>" class="student-checkbox" checked></td>
                                    <td><?php echo $student['reg_number']; ?></td>
                                    <td><?php echo htmlspecialchars($student['full_name']); ?></td>
                                    <td><?php echo $student['current_class'] . ' ' . $student['current_stream']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="mt-4" style="text-align: right;">
                    <button type="submit" name="promote_selected" class="btn btn-primary" style="width: auto;">Promote Selected and Log History</button>
                </div>
            </div>
        </form>
    </main>

    <script>
        document.getElementById('select-all').onclick = function() {
            var checkboxes = document.getElementsByClassName('student-checkbox');
            for (var checkbox of checkboxes) { checkbox.checked = this.checked; }
        }
    </script>
</body>
</html>
