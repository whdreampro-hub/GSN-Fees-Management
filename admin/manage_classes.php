<?php
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

if (!isLoggedIn()) {
    redirect('../login.php');
}

$years = getAllAcademicYears($pdo);
$currentYearData = getCurrentYearData($pdo);
$selectedYearId = isset($_GET['year_id']) ? (int)$_GET['year_id'] : $currentYearData['id'];

// Fetch all classes that have enrollments in the selected year
$stmt = $pdo->prepare("SELECT e.section, e.class_id, e.stream, c.class_name, COUNT(*) as student_count 
                      FROM enrollments e 
                      JOIN classes c ON e.class_id = c.id 
                      WHERE e.academic_year_id = ? 
                      GROUP BY e.section, e.class_id, e.stream 
                      ORDER BY e.section, c.class_name, e.stream");
$stmt->execute([$selectedYearId]);
$activeClasses = $stmt->fetchAll();
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
        <div class="year-selector" style="background: white; padding: 1rem; border-radius: 12px; display: flex; align-items: center; gap: 1rem; border: 1px solid var(--border-color); margin-bottom: 2rem;">
            <span style="font-weight: 600; color: var(--text-muted);">Academic Year:</span>
            <form method="GET" style="display: flex; gap: 0.5rem; align-items: center;">
                <select name="year_id" onchange="this.form.submit()" style="padding: 0.5rem; border-radius: 8px; width: auto; margin-bottom: 0;">
                    <?php foreach ($years as $y): ?>
                        <option value="<?php echo $y['id']; ?>" <?php echo $y['id'] == $selectedYearId ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($y['year_name']); ?> <?php echo $y['is_current'] ? '(Active)' : ''; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>

        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
            <h2>Class Workspaces</h2>
            <a href="add_student.php" class="btn-header">+ Register New Student</a>
        </div>

        <div class="class-grid">
            <?php foreach ($activeClasses as $class): 
                // Calculate fees for this class in this year
                $feePerStudent = 0;
                for ($i=1; $i<=3; $i++) { 
                    $stmt = $pdo->prepare("SELECT amount FROM fees_structure WHERE section = ? AND term = ? AND academic_year_id = ?");
                    $stmt->execute([$class['section'], $i, $selectedYearId]);
                    $feePerStudent += $stmt->fetchColumn() ?: 0;
                }
                $totalClassRequired = $feePerStudent * $class['student_count'];
                
                $stmt = $pdo->prepare("SELECT SUM(p.amount_paid) FROM payments p 
                                     JOIN enrollments e ON p.student_id = e.student_id 
                                     WHERE e.class_id = ? AND e.stream = ? AND e.academic_year_id = ? AND p.academic_year_id = ?");
                $stmt->execute([$class['class_id'], $class['stream'], $selectedYearId, $selectedYearId]);
                $totalClassPaid = $stmt->fetchColumn() ?: 0;
                $percent = $totalClassRequired > 0 ? round(($totalClassPaid / $totalClassRequired) * 100) : 0;
            ?>
                <div class="dashboard-card">
                    <div style="display: flex; justify-content: space-between; align-items: start;">
                        <div>
                            <h3 style="color: var(--primary-color); margin-bottom: 0.25rem;"><?php echo $class['class_name'] . ' ' . $class['stream']; ?></h3>
                            <span class="status-badge" style="background: #e2e8f0; color: #475569; padding: 0.15rem 0.5rem; font-size: 0.7rem;"><?php echo $class['section']; ?> Section</span>
                        </div>
                        <div style="text-align: right;">
                            <span style="font-size: 1.5rem; font-weight: 700; color: var(--secondary-color);"><?php echo $class['student_count']; ?></span>
                            <p style="font-size: 0.7rem; color: var(--text-muted);">Enrollments</p>
                        </div>
                    </div>

                    <div class="financials">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                            <span style="font-weight: 500;">Collection Progress</span>
                            <strong style="color: var(--success);"><?php echo $percent; ?>%</strong>
                        </div>
                        <div class="progress-bar"><div class="progress-fill" style="width: <?php echo $percent; ?>%;"></div></div>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem;">
                        <a href="add_student.php?mode=single&section=<?php echo urlencode($class['section']); ?>&class_id=<?php echo $class['class_id']; ?>&stream=<?php echo urlencode($class['stream']); ?>" class="btn btn-secondary mt-2" style="text-decoration: none; font-size: 0.75rem; padding: 0.5rem; background-color: var(--primary-color);">+ Student</a>
                        <a href="add_student.php?mode=bulk&section=<?php echo urlencode($class['section']); ?>&class_id=<?php echo $class['class_id']; ?>&stream=<?php echo urlencode($class['stream']); ?>" class="btn btn-secondary mt-2" style="text-decoration: none; font-size: 0.75rem; padding: 0.5rem; background-color: var(--accent-color);">Bulk Upload</a>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem; margin-top: 0.5rem;">
                        <a href="class_promotion.php?class_id=<?php echo $class['class_id']; ?>&stream=<?php echo urlencode($class['stream']); ?>&year_id=<?php echo $selectedYearId; ?>" class="btn btn-primary" style="text-decoration: none; font-size: 0.75rem; padding: 0.5rem;">Promote Class</a>
                        <a href="print_class.php?class_id=<?php echo $class['class_id']; ?>&stream=<?php echo urlencode($class['stream']); ?>&year_id=<?php echo $selectedYearId; ?>" class="btn btn-secondary" style="text-decoration: none; font-size: 0.75rem; padding: 0.5rem; background: var(--secondary-color);">Class Report</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </main>
</body>
</html>
