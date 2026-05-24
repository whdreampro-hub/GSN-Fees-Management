<?php
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

if (!isLoggedIn()) {
    redirect('../login.php');
}

$years = getAllAcademicYears($pdo);
$activeYear = getActiveYearData($pdo);
$selectedYearId = $activeYear['id'];

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

// Check if any fees are set for this year
$stmt = $pdo->prepare("SELECT COUNT(*) FROM fees_structure WHERE academic_year_id = ?");
$stmt->execute([$selectedYearId]);
$hasFees = $stmt->fetchColumn() > 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Professional Class Management - GSN Fees Management</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .page-layout { display: grid; grid-template-columns: 1fr 380px; gap: 2.5rem; align-items: start; }
        @media (max-width: 1024px) { .page-layout { grid-template-columns: 1fr; } }
        
        .class-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 1.5rem; }
        .dashboard-card { transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); border: 1px solid var(--border-color); background: #ffffff; border-radius: 16px; padding: 1.5rem; }
        .dashboard-card:hover { transform: translateY(-4px); box-shadow: 0 12px 24px -10px rgba(0, 0, 0, 0.1); border-color: rgba(79, 70, 229, 0.3); }
        
        .financials { margin: 1.5rem 0; padding: 1rem; background: #f8fafc; border-radius: 12px; font-size: 0.85rem; border: 1px solid #e2e8f0; }
        .progress-bar { height: 8px; background: #e2e8f0; border-radius: 99px; margin-top: 0.75rem; overflow: hidden; }
        .progress-fill { height: 100%; background: linear-gradient(90deg, #34d399 0%, #10b981 100%); transition: width 0.5s ease; border-radius: 99px; }
        
        .btn-header { background: linear-gradient(135deg, #4f46e5 0%, #3b82f6 100%); color: white; margin-bottom: 0; width: auto; text-decoration: none; padding: 0.8rem 1.5rem; border-radius: 12px; font-weight: 600; box-shadow: 0 4px 12px rgba(79, 70, 229, 0.2); transition: all 0.3s ease; display: inline-flex; align-items: center; gap: 0.5rem; }
        .btn-header:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(79, 70, 229, 0.3); color: white; }
        
        .action-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; margin-top: 1.5rem; }
        .btn-action { text-decoration: none; font-size: 0.8rem; padding: 0.6rem; border-radius: 8px; text-align: center; font-weight: 600; transition: all 0.2s; border: none; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 0.4rem; }
        
        .btn-add { background: #eff6ff; color: #2563eb; }
        .btn-add:hover { background: #dbeafe; }
        .btn-bulk { background: #fef2f2; color: #dc2626; }
        .btn-bulk:hover { background: #fee2e2; }
        .btn-promote { background: #f0fdf4; color: #16a34a; }
        .btn-promote:hover { background: #dcfce7; }
        .btn-enter { background: #f8fafc; color: #475569; border: 1px solid #e2e8f0; }
        .btn-enter:hover { background: #f1f5f9; color: #0f172a; border-color: #cbd5e1; }
        
        .system-classes-card { background: white; border-radius: 16px; padding: 1.5rem; box-shadow: var(--shadow-sm); border: 1px solid var(--border-color); }
        .form-input { width: 100%; padding: 0.75rem 1rem; border-radius: 10px; border: 1px solid #cbd5e1; font-size: 0.9rem; transition: all 0.2s; background: #f8fafc; }
        .form-input:focus { outline: none; border-color: var(--primary-color); background: white; box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1); }
    </style>
</head>
<body>
    <header>
        <div class="nav-container">
            <div class="logo">GSN <span>Fees Management</span></div>
            <nav class="nav-links">
                <a href="dashboard.php">Dashboard</a>
                <a href="manage_classes.php">Classes</a>
                <a href="add_student.php">Register</a>
                <a href="logout.php" style="color: var(--danger);">Logout</a>
            </nav>
            <div class="year-selector" style="margin-left: 1rem;">
                <form action="switch_year.php" method="POST">
                    <select name="switch_year_id" onchange="this.form.submit()" style="padding: 0.3rem 0.5rem; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 0.85rem; font-weight: 700; color: var(--primary-color);">
                        <?php foreach ($years as $y): ?>
                            <option value="<?php echo $y['id']; ?>" <?php echo $y['id'] == $selectedYearId ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($y['year_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>
        </div>
    </header>

    <main class="container">
        <?php if (!$hasFees): ?>
            <div style="background: #fff7ed; border: 1px solid #fdba74; color: #9a3412; padding: 1rem; border-radius: 12px; margin-bottom: 2rem; display: flex; align-items: center; gap: 1rem;">
                <span style="font-size: 1.5rem;">⚠️</span>
                <div>
                    <strong style="display: block;">Fee Structure Missing</strong>
                    <p style="font-size: 0.85rem; margin: 0;">Students in this workspace currently have no fees assigned. Please configure fees in <a href="settings.php" style="color: #c2410c; font-weight: 700;">Settings</a> to see accurate financial statuses.</p>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($message): ?><div style="background: #dcfce7; color: #166534; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; font-weight: 600;"><?php echo $message; ?></div><?php endif; ?>
        <?php if ($error): ?><div style="background: #fee2e2; color: #991b1b; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; font-weight: 600;"><?php echo $error; ?></div><?php endif; ?>

        <div class="page-layout">
            
            <!-- Left Column: Active Classes -->
            <div>
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
                    <h2 style="font-weight: 800; letter-spacing: -1px; color: var(--secondary-color); font-size: 1.8rem;">Operational Class Hub</h2>
                    <a href="add_student.php" class="btn-header">
                        <svg style="width: 18px; height: 18px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                        New Student
                    </a>
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
                        <div class="dashboard-card" style="border-top: 4px solid var(--primary-color);">
                            <div style="display: flex; justify-content: space-between; align-items: start;">
                                <div>
                                    <a href="view_class.php?class_id=<?php echo $class['class_id']; ?>&stream=<?php echo urlencode($class['stream']); ?>&year_id=<?php echo $selectedYearId; ?>" style="text-decoration: none;">
                                        <h3 style="color: var(--secondary-color); margin-bottom: 0.4rem; font-weight: 800; font-size: 1.25rem; transition: color 0.2s;" onmouseover="this.style.color='var(--primary-color)'" onmouseout="this.style.color='var(--secondary-color)'"><?php echo htmlspecialchars($class['class_name'] . ' ' . $class['stream']); ?></h3>
                                    </a>
                                    <span class="status-badge" style="background: #e0e7ff; color: #4338ca; padding: 0.3rem 0.8rem; font-size: 0.75rem; border-radius: 99px; font-weight: 700;"><?php echo htmlspecialchars($class['section']); ?> Section</span>
                                </div>
                                <div style="text-align: right; background: #f8fafc; padding: 0.5rem 1rem; border-radius: 12px; border: 1px solid #e2e8f0;">
                                    <span style="font-size: 1.5rem; font-weight: 800; color: var(--primary-color); display: block; line-height: 1;"><?php echo $class['student_count']; ?></span>
                                    <span style="font-size: 0.7rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Students</span>
                                </div>
                            </div>

                            <div class="financials">
                                <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem; align-items: center;">
                                    <span style="font-weight: 600; color: var(--text-muted); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px;">Collection Rate</span>
                                    <strong style="color: <?php echo $percent >= 80 ? '#059669' : ($percent >= 40 ? '#d97706' : '#dc2626'); ?>; font-size: 1rem;"><?php echo $percent; ?>%</strong>
                                </div>
                                <div class="progress-bar"><div class="progress-fill" style="width: <?php echo $percent; ?>%; background: <?php echo $percent >= 80 ? 'linear-gradient(90deg, #34d399 0%, #10b981 100%)' : ($percent >= 40 ? 'linear-gradient(90deg, #fbbf24 0%, #f59e0b 100%)' : 'linear-gradient(90deg, #f87171 0%, #ef4444 100%)'); ?>"></div></div>
                                <div style="display: flex; justify-content: space-between; margin-top: 0.75rem; font-size: 0.8rem;">
                                    <span style="color: var(--text-muted);">Collected: <strong style="color: var(--text-main);"><?php echo number_format($totalClassPaid); ?></strong></span>
                                    <span style="color: var(--text-muted);">Target: <strong style="color: var(--text-main);"><?php echo number_format($totalClassRequired); ?></strong></span>
                                </div>
                            </div>
                            
                            <div class="action-grid">
                                <a href="add_student.php?mode=single&section=<?php echo urlencode($class['section']); ?>&class_id=<?php echo $class['class_id']; ?>&stream=<?php echo urlencode($class['stream']); ?>&year_id=<?php echo $selectedYearId; ?>" class="btn-action btn-add">Add Student</a>
                                <a href="add_student.php?mode=bulk&section=<?php echo urlencode($class['section']); ?>&class_id=<?php echo $class['class_id']; ?>&stream=<?php echo urlencode($class['stream']); ?>&year_id=<?php echo $selectedYearId; ?>" class="btn-action btn-bulk">Bulk Import</a>
                                <a href="class_promotion.php?class_id=<?php echo $class['class_id']; ?>&stream=<?php echo urlencode($class['stream']); ?>&year_id=<?php echo $selectedYearId; ?>" class="btn-action btn-promote">Promote Next</a>
                                <a href="view_class.php?class_id=<?php echo $class['class_id']; ?>&stream=<?php echo urlencode($class['stream']); ?>&year_id=<?php echo $selectedYearId; ?>" class="btn-action btn-enter">Enter Class</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div> <!-- /Left Column -->

            <!-- Right Column: Global Class Management -->
            <div style="position: sticky; top: 2rem;">
                <div class="system-classes-card">
                    <div style="margin-bottom: 2rem;">
                        <h3 style="font-weight: 800; color: var(--secondary-color); font-size: 1.4rem; margin-bottom: 0.5rem;">System Classes</h3>
                        <p style="font-size: 0.85rem; color: var(--text-muted); line-height: 1.5;">Manage the global catalog of classes available across all academic years.</p>
                    </div>
                    
                    <form method="POST" style="margin-bottom: 2rem; background: #f8fafc; padding: 1.5rem; border-radius: 12px; border: 1px solid #e2e8f0;">
                        <h4 style="margin-bottom: 1.25rem; font-size: 0.95rem; font-weight: 700; color: var(--primary-color); display: flex; align-items: center; gap: 0.5rem;">
                            <svg style="width: 16px; height: 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                            <?php echo $editClass ? 'Edit Definition' : 'New Definition'; ?>
                        </h4>
                        <input type="hidden" name="edit_id" value="<?php echo $editClass ? $editClass['id'] : 0; ?>">
                        
                        <div class="form-group" style="margin-bottom: 1rem;">
                            <label style="font-size: 0.8rem; font-weight: 600; color: var(--text-main); margin-bottom: 0.4rem; display: block;">Class Name</label>
                            <input type="text" name="class_name" value="<?php echo $editClass ? htmlspecialchars($editClass['class_name']) : ''; ?>" placeholder="e.g. Senior 1" required class="form-input">
                        </div>
                        
                        <div class="form-group" style="margin-bottom: 1.5rem;">
                            <label style="font-size: 0.8rem; font-weight: 600; color: var(--text-main); margin-bottom: 0.4rem; display: block;">Section</label>
                            <select name="section" required class="form-input">
                                <option value="Primary" <?php echo ($editClass && $editClass['section'] == 'Primary') ? 'selected' : ''; ?>>Primary</option>
                                <option value="Secondary" <?php echo ($editClass && $editClass['section'] == 'Secondary') ? 'selected' : ''; ?>>Secondary</option>
                            </select>
                        </div>
                        
                        <div style="display: flex; gap: 0.75rem;">
                            <button type="submit" name="save_class" class="btn-header" style="flex: 1; padding: 0.75rem; text-align: center; justify-content: center;"><?php echo $editClass ? 'Update' : 'Create'; ?></button>
                            <?php if ($editClass): ?>
                                <a href="manage_classes.php" class="btn-action btn-enter" style="padding: 0.75rem 1rem;">Cancel</a>
                            <?php endif; ?>
                        </div>
                    </form>

                    <div style="border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; background: white;">
                        <table style="width: 100%; border-collapse: collapse; font-size: 0.85rem;">
                            <thead style="background: #f8fafc; border-bottom: 1px solid #e2e8f0;">
                                <tr>
                                    <th style="padding: 0.75rem 1rem; text-align: left; color: var(--text-muted); font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; font-size: 0.7rem;">Class</th>
                                    <th style="padding: 0.75rem 1rem; text-align: left; color: var(--text-muted); font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; font-size: 0.7rem;">Section</th>
                                    <th style="padding: 0.75rem 1rem; text-align: right; color: var(--text-muted); font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; font-size: 0.7rem;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($allClasses as $c): ?>
                                    <tr style="border-bottom: 1px solid #f1f5f9; transition: background 0.2s;" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='white'">
                                        <td style="padding: 0.75rem 1rem; font-weight: 700; color: var(--secondary-color);"><?php echo htmlspecialchars($c['class_name']); ?></td>
                                        <td style="padding: 0.75rem 1rem;">
                                            <span style="background: <?php echo $c['section'] == 'Primary' ? '#f0fdf4' : '#eff6ff'; ?>; color: <?php echo $c['section'] == 'Primary' ? '#16a34a' : '#2563eb'; ?>; padding: 0.2rem 0.5rem; border-radius: 4px; font-size: 0.7rem; font-weight: 600;">
                                                <?php echo $c['section']; ?>
                                            </span>
                                        </td>
                                        <td style="padding: 0.75rem 1rem; text-align: right; white-space: nowrap;">
                                            <a href="?edit_id=<?php echo $c['id']; ?>" style="color: var(--primary-color); text-decoration: none; font-weight: 600; margin-right: 0.75rem; font-size: 0.8rem;">Edit</a>
                                            <a href="?delete_id=<?php echo $c['id']; ?>" style="color: var(--danger); text-decoration: none; font-weight: 600; font-size: 0.8rem;" onclick="return confirm('Are you sure you want to delete this class?')">Delete</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
