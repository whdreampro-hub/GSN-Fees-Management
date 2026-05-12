<?php
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

if (!isLoggedIn()) {
    redirect('../login.php');
}

// Fetch all classes with student counts
$stmt = $pdo->query("SELECT section, current_class, current_stream, COUNT(*) as student_count 
                    FROM students 
                    GROUP BY section, current_class, current_stream 
                    ORDER BY section, current_class, current_stream");
$classes = $stmt->fetchAll();
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
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
            <h2>Professional Class Management</h2>
            <a href="add_student.php" class="btn-header">+ Create New Class</a>
        </div>

        <div class="class-grid">
            <?php foreach ($classes as $class): 
                $year = getCurrentYear($pdo);
                $feePerStudent = 0;
                for ($i=1; $i<=3; $i++) { $feePerStudent += getFeeAmount($pdo, $class['section'], $i); }
                $totalClassRequired = $feePerStudent * $class['student_count'];
                
                $stmt = $pdo->prepare("SELECT SUM(p.amount_paid) FROM payments p 
                                     JOIN students s ON p.student_id = s.id 
                                     WHERE s.section = ? AND s.current_class = ? AND s.current_stream = ? AND p.year = ?");
                $stmt->execute([$class['section'], $class['current_class'], $class['current_stream'], $year]);
                $totalClassPaid = $stmt->fetchColumn() ?: 0;
                $percent = $totalClassRequired > 0 ? round(($totalClassPaid / $totalClassRequired) * 100) : 0;
            ?>
                <div class="dashboard-card">
                    <div style="display: flex; justify-content: space-between; align-items: start;">
                        <div>
                            <h3 style="color: var(--primary-color); margin-bottom: 0.25rem;"><?php echo $class['current_class'] . ' ' . $class['current_stream']; ?></h3>
                            <span class="status-badge" style="background: #e2e8f0; color: #475569; padding: 0.15rem 0.5rem;"><?php echo $class['section']; ?></span>
                        </div>
                        <div style="text-align: right;">
                            <span style="font-size: 1.25rem; font-weight: 700; color: var(--secondary-color);"><?php echo $class['student_count']; ?></span>
                            <p style="font-size: 0.7rem; color: var(--text-muted);">Students</p>
                        </div>
                    </div>

                    <div class="financials">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 0.25rem;">
                            <span>Collection Rate:</span>
                            <strong><?php echo $percent; ?>%</strong>
                        </div>
                        <div class="progress-bar"><div class="progress-fill" style="width: <?php echo $percent; ?>%;"></div></div>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem;">
                        <a href="add_student.php?mode=single&section=<?php echo urlencode($class['section']); ?>&class=<?php echo urlencode($class['current_class']); ?>&stream=<?php echo urlencode($class['current_stream']); ?>" class="btn btn-secondary mt-2" style="text-decoration: none; font-size: 0.75rem; padding: 0.5rem; background-color: var(--success);">+ Student</a>
                        <a href="add_student.php?mode=bulk&section=<?php echo urlencode($class['section']); ?>&class=<?php echo urlencode($class['current_class']); ?>&stream=<?php echo urlencode($class['current_stream']); ?>" class="btn btn-secondary mt-2" style="text-decoration: none; font-size: 0.75rem; padding: 0.5rem; background-color: var(--accent-color);">Upload List</a>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem; margin-top: 0.5rem;">
                        <a href="class_promotion.php?section=<?php echo urlencode($class['section']); ?>&class=<?php echo urlencode($class['current_class']); ?>&stream=<?php echo urlencode($class['current_stream']); ?>" class="btn btn-primary" style="text-decoration: none; font-size: 0.75rem; padding: 0.5rem;">Promote</a>
                        <a href="print_class.php?section=<?php echo urlencode($class['section']); ?>&class=<?php echo urlencode($class['current_class']); ?>&stream=<?php echo urlencode($class['current_stream']); ?>" class="btn btn-secondary" style="text-decoration: none; font-size: 0.75rem; padding: 0.5rem; background: var(--secondary-color);">Print Report</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </main>
</body>
</html>
