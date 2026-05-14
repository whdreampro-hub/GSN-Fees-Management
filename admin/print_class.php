<?php
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

if (!isLoggedIn()) {
    redirect('../login.php');
}

$class_id = (int)$_GET['class_id'];
$stream = $_GET['stream'];
$year_id = (int)$_GET['year_id'];
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';

$yearData = getAcademicYearById($pdo, $year_id);
$stmt = $pdo->prepare("SELECT class_name FROM classes WHERE id = ?");
$stmt->execute([$class_id]);
$className = $stmt->fetchColumn();

$query = "SELECT s.*, e.section, e.class_id, e.stream 
          FROM students s 
          JOIN enrollments e ON s.id = e.student_id 
          WHERE e.class_id = ? AND e.stream = ? AND e.academic_year_id = ?";
$stmt = $pdo->prepare($query);
$stmt->execute([$class_id, $stream, $year_id]);
$allStudents = $stmt->fetchAll();

$students = [];
foreach ($allStudents as $s) {
    $status = getDetailedYearlyStatus($pdo, $s['id'], $year_id);
    if ($filter == 'cleared' && $status['balance'] < 0) continue;
    if ($filter == 'debtors' && $status['balance'] >= 0) continue;
    $s['financials'] = $status;
    $students[] = $s;
}

$section = !empty($students) ? $students[0]['section'] : 'N/A';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Class Report - <?php echo "$className $stream"; ?></title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        body { background: #f1f5f9; padding: 0; font-family: 'Outfit', sans-serif; }
        .print-header { text-align: center; margin-bottom: 2rem; border-bottom: 3px double #334155; padding-bottom: 1.5rem; }
        .print-table { width: 100%; border-collapse: collapse; margin-top: 1rem; background: white; }
        .print-table th, .print-table td { border: 1px solid #cbd5e1; padding: 12px 8px; text-align: left; font-size: 0.85rem; }
        .print-table th { background: #f8fafc; text-transform: uppercase; letter-spacing: 0.05em; color: #475569; }
        .no-print { margin: 2rem; text-align: center; display: flex; justify-content: center; gap: 1rem; }
        .filter-links { margin-bottom: 1rem; }
        .filter-links a { text-decoration: none; padding: 0.4rem 0.8rem; border-radius: 6px; background: #e2e8f0; color: #475569; font-size: 0.8rem; font-weight: 600; }
        .filter-links a.active { background: var(--primary-color); color: white; }
        @media print {
            .no-print { display: none; }
            body { background: white; }
            .container { max-width: 100%; padding: 0; }
            .print-table th { background: #eee !important; color: black; }
        }
    </style>
</head>
<body>
    <div class="no-print">
        <div style="background: white; padding: 1.5rem; border-radius: 12px; box-shadow: var(--shadow-md);">
            <div class="filter-links">
                <a href="?class_id=<?php echo $class_id; ?>&stream=<?php echo urlencode($stream); ?>&year_id=<?php echo $year_id; ?>&filter=all" class="<?php echo $filter == 'all' ? 'active' : ''; ?>">All Students</a>
                <a href="?class_id=<?php echo $class_id; ?>&stream=<?php echo urlencode($stream); ?>&year_id=<?php echo $year_id; ?>&filter=cleared" class="<?php echo $filter == 'cleared' ? 'active' : ''; ?>">Cleared Only</a>
                <a href="?class_id=<?php echo $class_id; ?>&stream=<?php echo urlencode($stream); ?>&year_id=<?php echo $year_id; ?>&filter=debtors" class="<?php echo $filter == 'debtors' ? 'active' : ''; ?>">Debtors Only</a>
            </div>
            <button onclick="window.print()" class="btn btn-primary" style="width: auto;">Print This Report</button>
            <a href="manage_classes.php?year_id=<?php echo $year_id; ?>" class="btn btn-secondary" style="width: auto; text-decoration: none;">Return to Workspace</a>
        </div>
    </div>

    <div class="container" style="background: white; padding: 3rem; border-radius: 16px; box-shadow: var(--shadow-lg); margin-top: 1rem;">
        <div class="print-header">
            <h1 style="color: #1e293b; letter-spacing: -1px;">GS NYAGISOZI</h1>
            <h3 style="color: #64748b; font-weight: 500;">Financial Reporting & Fee Management</h3>
            <div style="margin-top: 1.5rem; font-size: 1rem;">
                <strong>Workspace:</strong> <?php echo htmlspecialchars($yearData['year_name']); ?> | 
                <strong>Class:</strong> <?php echo "$className $stream"; ?> | 
                <strong>Filter:</strong> <?php echo ucfirst($filter); ?>
            </div>
        </div>

        <table class="print-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Reg Number</th>
                    <th>Full Name</th>
                    <th>Term 1</th>
                    <th>Term 2</th>
                    <th>Term 3</th>
                    <th>Total Paid</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($students as $index => $student): 
                    $fs = $student['financials'];
                ?>
                    <tr>
                        <td><?php echo $index + 1; ?></td>
                        <td><strong><?php echo $student['reg_number']; ?></strong></td>
                        <td><?php echo htmlspecialchars($student['full_name']); ?></td>
                        <td><?php echo number_format($fs['total_required']); ?> FRW</td>
                        <td><?php echo number_format($fs['total_paid']); ?> FRW</td>
                        <td>
                            <strong style="color: <?php echo $fs['balance'] >= 0 ? '#16a34a' : '#dc2626'; ?>;">
                                <?php echo number_format($fs['balance']); ?> FRW
                            </strong>
                        </td>
                        <td>
                            <?php if ($fs['balance'] >= 0): ?>
                                <span style="color: #16a34a; font-weight: 700;">CLEARED</span>
                            <?php else: ?>
                                <span style="color: #dc2626; font-weight: 700;">DEBTOR</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div style="margin-top: 3rem; display: flex; justify-content: space-between;">
            <div>
                <p>__________________________</p>
                <p>Class Teacher Signature</p>
            </div>
            <div>
                <p>__________________________</p>
                <p>School Administrator</p>
            </div>
        </div>
        <p style="text-align: center; margin-top: 2rem; font-size: 0.8rem; color: #666;">Generated on: <?php echo date('Y-m-d H:i:s'); ?></p>
    </div>
</body>
</html>
