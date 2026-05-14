<?php
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

if (!isLoggedIn()) {
    redirect('../login.php');
}

$class_id = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
$stream = isset($_GET['stream']) ? $_GET['stream'] : '';
$source_year_id = isset($_GET['year_id']) ? (int)$_GET['year_id'] : 0;

if (!$class_id || !$source_year_id) {
    redirect('manage_classes.php');
}

$stmt = $pdo->prepare("SELECT s.*, e.section, e.class_id, e.stream, c.class_name 
                      FROM students s 
                      JOIN enrollments e ON s.id = e.student_id 
                      JOIN classes c ON e.class_id = c.id
                      WHERE e.class_id = ? AND e.stream = ? AND e.academic_year_id = ? 
                      ORDER BY s.full_name ASC");
$stmt->execute([$class_id, $stream, $source_year_id]);
$students = $stmt->fetchAll();

$academicYears = getAllAcademicYears($pdo);
$classes = getClasses($pdo);

$source_class_name = 'N/A';
$source_section = 'N/A';
if (!empty($students)) {
    $source_class_name = $students[0]['class_name'];
    $source_section = $students[0]['section'];
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['promote_selected'])) {
    $selected_students = isset($_POST['student_ids']) ? $_POST['student_ids'] : [];
    $target_year_id = (int)$_POST['target_year_id'];
    $target_section = $_POST['target_section'];
    $target_class_id = (int)$_POST['target_class_id'];
    $target_stream = trim($_POST['target_stream']);
    
    if (!empty($selected_students) && $target_class_id && $target_year_id) {
        try {
            $pdo->beginTransaction();
            
            foreach ($selected_students as $student_id) {
                // 1. Update old enrollment status if it was 'Active'
                $stmt = $pdo->prepare("UPDATE enrollments SET status = 'Promoted' WHERE student_id = ? AND academic_year_id = ? AND status = 'Active'");
                $stmt->execute([$student_id, $source_year_id]);

                // 2. Create NEW enrollment for target year
                $stmt = $pdo->prepare("INSERT INTO enrollments (student_id, class_id, stream, academic_year_id, section, status) 
                                      VALUES (?, ?, ?, ?, ?, 'Active')");
                $stmt->execute([$student_id, $target_class_id, $target_stream, $target_year_id, $target_section]);
            }
            
            $pdo->commit();
            $message = "Successfully promoted " . count($selected_students) . " students to the selected workspace.";
            
            // Refresh list
            $stmt = $pdo->prepare("SELECT s.*, e.section, e.class_id, e.stream, c.class_name 
                                  FROM students s 
                                  JOIN enrollments e ON s.id = e.student_id 
                                  JOIN classes c ON e.class_id = c.id
                                  WHERE e.class_id = ? AND e.stream = ? AND e.academic_year_id = ? 
                                  ORDER BY s.full_name ASC");
            $stmt->execute([$class_id, $stream, $source_year_id]);
            $students = $stmt->fetchAll();
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = "Error: " . $e->getMessage();
        }
    } else {
        $error = "Please select students and a target class/year.";
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
            <h2 class="mt-4" style="font-weight: 800; letter-spacing: -1px;">Promoting Students from: <span style="color: var(--primary-color);"><?php echo "$source_class_name $stream ($source_section)"; ?></span></h2>
        </div>

        <?php if ($message): ?><div style="color: var(--success); margin-bottom: 1.5rem; padding: 1rem; background: #dcfce7; border-radius: 8px;"><?php echo $message; ?></div><?php endif; ?>
        <?php if ($error): ?><div style="color: var(--danger); margin-bottom: 1.5rem; padding: 1rem; background: #fee2e2; border-radius: 8px;"><?php echo $error; ?></div><?php endif; ?>

        <form method="POST">
            <div style="background: #eff6ff; padding: 1.5rem; border-radius: 12px; margin-bottom: 2rem; border: 1px solid #bfdbfe;">
                <h3 class="mb-4">Target Information</h3>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem;">
                    <div class="form-group">
                        <label>Target Year</label>
                        <select name="target_year_id" required>
                            <?php foreach ($academicYears as $ay): ?>
                                <option value="<?php echo $ay['id']; ?>">
                                    <?php echo htmlspecialchars($ay['year_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Target Section</label>
                        <select name="target_section" required>
                            <option value="Primary">Primary</option>
                            <option value="Secondary" selected>Secondary</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Target Class</label>
                        <select name="target_class_id" required>
                            <?php foreach ($classes as $c): ?>
                                <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['class_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
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
