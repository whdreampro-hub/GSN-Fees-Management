<?php
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

if (!isLoggedIn()) {
    redirect('../login.php');
}

$years = getAllAcademicYears($pdo);
$currentYearData = getCurrentYearData($pdo);
$selectedYearId = isset($_GET['year_id']) ? (int)$_GET['year_id'] : $currentYearData['id'];

$message = '';
$error = '';

// 1. ADD/UPDATE CLASS DEFINITION
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_class'])) {
    $class_name = trim($_POST['class_name']);
    $section = $_POST['section'];
    $edit_id = isset($_POST['edit_id']) ? (int)$_POST['edit_id'] : 0;
    
    if ($class_name && $section) {
        try {
            if ($edit_id > 0) {
                $stmt = $pdo->prepare("UPDATE classes SET class_name = ?, section = ? WHERE id = ?");
                $stmt->execute([$class_name, $section, $edit_id]);
                $message = "Class updated successfully!";
            } else {
                $stmt = $pdo->prepare("INSERT INTO classes (class_name, section) VALUES (?, ?)");
                $stmt->execute([$class_name, $section]);
                $message = "Class '$class_name' added to the system!";
            }
        } catch (PDOException $e) { $error = "Error: " . $e->getMessage(); }
    }
}

// 2. DELETE CLASS DEFINITION
if (isset($_GET['delete_id'])) {
    $delete_id = (int)$_GET['delete_id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM classes WHERE id = ?");
        $stmt->execute([$delete_id]);
        $message = "Class definition removed.";
    } catch (PDOException $e) { $error = "Cannot delete: Class is currently in use."; }
}

// Fetch all classes that have enrollments in the selected year
$stmt = $pdo->prepare("SELECT e.section, e.class_id, e.stream, c.class_name, COUNT(*) as student_count 
                      FROM enrollments e 
                      JOIN classes c ON e.class_id = c.id 
                      WHERE e.academic_year_id = ? 
                      GROUP BY e.section, e.class_id, e.stream 
                      ORDER BY e.section, c.class_name, e.stream");
$stmt->execute([$selectedYearId]);
$activeClasses = $stmt->fetchAll();

// Fetch ALL classes for the global management section
$allClasses = getClasses($pdo);

$editClass = null;
if (isset($_GET['edit_id'])) {
    $edit_id = (int)$_GET['edit_id'];
    foreach ($allClasses as $c) {
        if ($c['id'] == $edit_id) {
            $editClass = $c;
            break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Professional Class Management - GSN Fees Management</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .class-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 1.5rem; margin-top: 2rem; }
        .financials { margin: 1rem 0; padding: 0.75rem; background: #f1f5f9; border-radius: 8px; font-size: 0.85rem; }
        .progress-bar { height: 8px; background: #e2e8f0; border-radius: 4px; margin-top: 0.5rem; overflow: hidden; }
        .progress-fill { height: 100%; background: var(--success); transition: width 0.3s ease; }
        .btn-header { background-color: var(--success); color: white; margin-bottom: 0; width: auto; text-decoration: none; padding: 0.6rem 1.2rem; border-radius: 8px; font-weight: 600; }
    </style>
</head>
<body>
    <header>
        <div class="nav-container">
            <div class="logo">GSN <span>Fees Management</span></div>
            <nav class="nav-links">
                <a href="dashboard.php">Dashboard</a>
                <a href="manage_classes.php">Manage Classes</a>
                <a href="add_student.php">Register Student</a>
                <a href="logout.php" style="color: var(--danger);">Logout</a>
            </nav>
        </div>
    </header>

    <main class="container">
        <?php if ($message): ?><div style="background: #dcfce7; color: #166534; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; font-weight: 600;"><?php echo $message; ?></div><?php endif; ?>
        <?php if ($error): ?><div style="background: #fee2e2; color: #991b1b; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; font-weight: 600;"><?php echo $error; ?></div><?php endif; ?>

        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 2rem; align-items: start;">
            <div>
                <div class="year-selector" style="background: white; padding: 1rem; border-radius: 12px; display: flex; align-items: center; gap: 1rem; border: 1px solid var(--border-color); margin-bottom: 2rem;">
                    <span style="font-weight: 600; color: var(--text-muted);">Workplace Filter:</span>
                    <form method="GET" style="display: flex; gap: 0.5rem; align-items: center;">
                        <select name="year_id" onchange="this.form.submit()" style="padding: 0.5rem; border-radius: 8px; width: auto; margin-bottom: 0;">
                            <?php foreach ($years as $y): ?>
                                <option value="<?php echo $y['id']; ?>" <?php echo $y['id'] == $selectedYearId ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($y['year_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </div>

                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
                    <h2 style="font-weight: 800; letter-spacing: -1px;">Operational Class Hub</h2>
                    <a href="add_student.php" class="btn-header">+ Register New Student</a>
                </div>

                <div class="class-grid">
                    <?php foreach ($activeClasses as $class): 
                        // Calculate total required for this class (sum of fees for all students in the class)
                        $stmt = $pdo->prepare("SELECT SUM(amount) FROM fees_structure WHERE section = ? AND academic_year_id = ?");
                        $stmt->execute([$class['section'], $selectedYearId]);
                        $feePerStudent = $stmt->fetchColumn() ?: 0;
                        $totalClassRequired = $feePerStudent * $class['student_count'];
                        
                        $stmt = $pdo->prepare("SELECT SUM(p.amount_paid) FROM payments p 
                                             JOIN enrollments e ON p.student_id = e.student_id 
                                             WHERE e.class_id = ? AND e.stream = ? AND e.academic_year_id = ? AND p.academic_year_id = ?");
                        $stmt->execute([$class['class_id'], $class['stream'], $selectedYearId, $selectedYearId]);
                        $totalClassPaid = $stmt->fetchColumn() ?: 0;
                        $percent = $totalClassRequired > 0 ? round(($totalClassPaid / $totalClassRequired) * 100) : 0;
                    ?>
                        <div class="dashboard-card" style="box-shadow: var(--shadow-md); border-left: 5px solid var(--primary-color);">
                            <div style="display: flex; justify-content: space-between; align-items: start;">
                                <div>
                                    <a href="view_class.php?class_id=<?php echo $class['class_id']; ?>&stream=<?php echo urlencode($class['stream']); ?>&year_id=<?php echo $selectedYearId; ?>" style="text-decoration: none;">
                                        <h3 style="color: var(--primary-color); margin-bottom: 0.25rem; font-weight: 800;"><?php echo $class['class_name'] . ' ' . $class['stream']; ?></h3>
                                    </a>
                                    <span class="status-badge" style="background: #f1f5f9; color: #475569; padding: 0.2rem 0.6rem; font-size: 0.7rem; border-radius: 4px;"><?php echo $class['section']; ?></span>
                                </div>
                                <div style="text-align: right;">
                                    <span style="font-size: 1.5rem; font-weight: 800; color: var(--secondary-color);"><?php echo $class['student_count']; ?></span>
                                    <p style="font-size: 0.7rem; color: var(--text-muted); font-weight: 600;">Students</p>
                                </div>
                            </div>

                            <div class="financials">
                                <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                                    <span style="font-weight: 600; color: var(--text-muted);">Collection Rate</span>
                                    <strong style="color: var(--success);"><?php echo $percent; ?>%</strong>
                                </div>
                                <div class="progress-bar" style="background: #e2e8f0;"><div class="progress-fill" style="width: <?php echo $percent; ?>%;"></div></div>
                                <div style="display: flex; justify-content: space-between; margin-top: 0.5rem; font-size: 0.75rem;">
                                    <span>Collected: <strong><?php echo number_format($totalClassPaid); ?></strong></span>
                                    <span>Target: <strong><?php echo number_format($totalClassRequired); ?></strong></span>
                                </div>
                            </div>
                            
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem;">
                                <a href="add_student.php?mode=single&section=<?php echo urlencode($class['section']); ?>&class_id=<?php echo $class['class_id']; ?>&stream=<?php echo urlencode($class['stream']); ?>&year_id=<?php echo $selectedYearId; ?>" class="btn btn-secondary mt-2" style="text-decoration: none; font-size: 0.75rem; padding: 0.5rem; background-color: var(--primary-color); border: none;">Add Student</a>
                                <a href="add_student.php?mode=bulk&section=<?php echo urlencode($class['section']); ?>&class_id=<?php echo $class['class_id']; ?>&stream=<?php echo urlencode($class['stream']); ?>&year_id=<?php echo $selectedYearId; ?>" class="btn btn-secondary mt-2" style="text-decoration: none; font-size: 0.75rem; padding: 0.5rem; background-color: var(--accent-color); border: none;">Bulk Import</a>
                            </div>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem; margin-top: 0.5rem;">
                                <a href="class_promotion.php?class_id=<?php echo $class['class_id']; ?>&stream=<?php echo urlencode($class['stream']); ?>&year_id=<?php echo $selectedYearId; ?>" class="btn btn-primary" style="text-decoration: none; font-size: 0.75rem; padding: 0.5rem; background: var(--secondary-color); border: none;">Promote Next</a>
                                <a href="view_class.php?class_id=<?php echo $class['class_id']; ?>&stream=<?php echo urlencode($class['stream']); ?>&year_id=<?php echo $selectedYearId; ?>" class="btn btn-secondary" style="text-decoration: none; font-size: 0.75rem; padding: 0.5rem; background: #334155; color: white; border: none;">Enter Class</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Global Class Management -->
            <div class="card" style="position: sticky; top: 1rem;">
                <h3 class="mb-4">System Classes</h3>
                <p class="mb-4" style="font-size: 0.85rem; color: var(--text-muted);">Manage the global list of classes available in the school.</p>
                
                <form method="POST" style="margin-bottom: 2rem; background: #f8fafc; padding: 1.5rem; border-radius: 12px; border: 1px solid var(--border-color);">
                    <h4 style="margin-bottom: 1rem; font-size: 0.9rem;"><?php echo $editClass ? 'Edit' : 'Create'; ?> Class Definition</h4>
                    <input type="hidden" name="edit_id" value="<?php echo $editClass ? $editClass['id'] : 0; ?>">
                    
                    <div class="form-group">
                        <label style="font-size: 0.75rem;">Class Name</label>
                        <input type="text" name="class_name" value="<?php echo $editClass ? htmlspecialchars($editClass['class_name']) : ''; ?>" placeholder="e.g. Senior 1" required style="background: white; border: 1px solid #cbd5e1;">
                    </div>
                    
                    <div class="form-group">
                        <label style="font-size: 0.75rem;">Section</label>
                        <select name="section" required style="background: white; border: 1px solid #cbd5e1;">
                            <option value="Primary" <?php echo ($editClass && $editClass['section'] == 'Primary') ? 'selected' : ''; ?>>Primary</option>
                            <option value="Secondary" <?php echo ($editClass && $editClass['section'] == 'Secondary') ? 'selected' : ''; ?>>Secondary</option>
                        </select>
                    </div>
                    
                    <div style="display: flex; gap: 0.5rem;">
                        <button type="submit" name="save_class" class="btn btn-primary"><?php echo $editClass ? 'Update Class' : 'Create Class'; ?></button>
                        <?php if ($editClass): ?>
                            <a href="manage_classes.php" class="btn btn-secondary" style="width: auto; text-decoration: none;">Cancel</a>
                        <?php endif; ?>
                    </div>
                </form>

                <div class="table-container" style="box-shadow: none; border: 1px solid var(--border-color);">
                    <table style="font-size: 0.85rem;">
                        <thead style="background: #f8fafc;">
                            <tr><th>Class</th><th>Section</th><th style="text-align: right;">Action</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($allClasses as $c): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($c['class_name']); ?></strong></td>
                                    <td><span style="font-size: 0.75rem; opacity: 0.8;"><?php echo $c['section']; ?></span></td>
                                    <td style="text-align: right; white-space: nowrap;">
                                        <a href="?edit_id=<?php echo $c['id']; ?>" style="color: var(--primary-color); text-decoration: none; font-weight: 600; margin-right: 0.5rem;">Edit</a>
                                        <a href="?delete_id=<?php echo $c['id']; ?>" style="color: var(--danger); text-decoration: none; font-weight: 600;" onclick="return confirm('Delete this class?')">Rem</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
