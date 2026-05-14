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
$stmt = $pdo->prepare("SELECT class_name, section FROM classes WHERE id = ?");
$stmt->execute([$class_id]);
$classData = $stmt->fetch();

$query = "SELECT s.*, e.section, e.class_id, e.stream 
          FROM students s 
          JOIN enrollments e ON s.id = e.student_id 
          WHERE e.class_id = ? AND e.stream = ? AND e.academic_year_id = ?";
$stmt = $pdo->prepare($query);
$stmt->execute([$class_id, $stream, $year_id]);
$allStudents = $stmt->fetchAll();

$students = [];
$totals = ['required' => 0, 'paid' => 0, 'balance' => 0];

foreach ($allStudents as $s) {
    $status = getDetailedYearlyStatus($pdo, $s['id'], $year_id);
    if ($filter == 'cleared' && $status['balance'] < 0) continue;
    if ($filter == 'debtors' && $status['balance'] >= 0) continue;
    
    $s['financials'] = $status;
    $students[] = $s;
    
    $totals['required'] += $status['total_required'];
    $totals['paid'] += $status['total_paid'];
    $totals['balance'] += $status['balance'];
}

$className = $classData['class_name'];
$section = $classData['section'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Professional Class Report - <?php echo "$className $stream"; ?></title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        body { background: #f8fafc; padding: 0; font-family: 'Outfit', sans-serif; }
        .print-container { background: white; padding: 3rem; margin: 2rem auto; max-width: 1000px; border-radius: 16px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; }
        .print-header { text-align: center; border-bottom: 4px double #1e293b; padding-bottom: 2rem; margin-bottom: 2rem; }
        .print-table { width: 100%; border-collapse: collapse; margin: 2rem 0; }
        .print-table th { background: #f1f5f9; border: 1px solid #cbd5e1; padding: 12px; font-size: 0.75rem; text-transform: uppercase; color: #475569; letter-spacing: 1px; }
        .print-table td { border: 1px solid #e2e8f0; padding: 12px; font-size: 0.85rem; color: #1e293b; }
        .totals-row { background: #f8fafc; font-weight: 800; }
        .no-print { position: sticky; top: 0; z-index: 100; background: rgba(255,255,255,0.9); backdrop-filter: blur(8px); padding: 1rem; border-bottom: 1px solid #e2e8f0; text-align: center; }
        .status-pill { padding: 4px 8px; border-radius: 4px; font-size: 0.7rem; font-weight: 800; }
        @media print {
            .no-print { display: none; }
            body { background: white; padding: 0; }
            .print-container { box-shadow: none; border: none; margin: 0; max-width: 100%; }
        }
    </style>
</head>
<body>
    <div class="no-print">
        <div style="display: flex; justify-content: center; gap: 1rem; align-items: center;">
            <div style="background: #f1f5f9; padding: 0.3rem; border-radius: 8px;">
                <a href="?class_id=<?php echo $class_id; ?>&stream=<?php echo urlencode($stream); ?>&year_id=<?php echo $year_id; ?>&filter=all" class="btn btn-secondary <?php echo $filter == 'all' ? 'active' : ''; ?>" style="width: auto; padding: 0.4rem 1rem; font-size: 0.8rem; border: none;">All Students</a>
                <a href="?class_id=<?php echo $class_id; ?>&stream=<?php echo urlencode($stream); ?>&year_id=<?php echo $year_id; ?>&filter=cleared" class="btn btn-secondary <?php echo $filter == 'cleared' ? 'active' : ''; ?>" style="width: auto; padding: 0.4rem 1rem; font-size: 0.8rem; border: none;">Cleared</a>
                <a href="?class_id=<?php echo $class_id; ?>&stream=<?php echo urlencode($stream); ?>&year_id=<?php echo $year_id; ?>&filter=debtors" class="btn btn-secondary <?php echo $filter == 'debtors' ? 'active' : ''; ?>" style="width: auto; padding: 0.4rem 1rem; font-size: 0.8rem; border: none;">Debtors</a>
            </div>
            <button onclick="window.print()" class="btn btn-primary" style="width: auto;">Print Official Report</button>
            <a href="view_class.php?class_id=<?php echo $class_id; ?>&stream=<?php echo urlencode($stream); ?>&year_id=<?php echo $year_id; ?>" class="btn btn-secondary" style="width: auto;">Return to Workspace</a>
        </div>
    </div>

    <div class="print-container">
        <div class="print-header">
            <h1 style="font-size: 2.5rem; letter-spacing: -2px; margin-bottom: 0.5rem;">GS NYAGISOZI</h1>
            <h3 style="color: #64748b; font-weight: 500; text-transform: uppercase; letter-spacing: 2px;">Class Financial Report</h3>
            <div style="margin-top: 1.5rem; display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; text-align: center; font-size: 0.9rem;">
                <div><strong>Year:</strong> <?php echo $yearData['year_name']; ?></div>
                <div><strong>Class:</strong> <?php echo "$className $stream ($section)"; ?></div>
                <div><strong>Report Type:</strong> <?php echo ucfirst($filter); ?> List</div>
            </div>
        </div>

        <table class="print-table">
            <thead>
                <tr>
                    <th width="50">#</th>
                    <th>Reg Number</th>
                    <th>Student Name</th>
                    <th>Gender</th>
                    <th style="text-align: right;">Required</th>
                    <th style="text-align: right;">Paid</th>
                    <th style="text-align: right;">Balance</th>
                    <th style="text-align: center;">Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($students as $idx => $s): 
                    $f = $s['financials'];
                ?>
                    <tr>
                        <td style="text-align: center; color: #94a3b8;"><?php echo $idx + 1; ?></td>
                        <td style="font-family: monospace; font-weight: 700;"><?php echo $s['reg_number']; ?></td>
                        <td style="font-weight: 600;"><?php echo htmlspecialchars($s['full_name']); ?></td>
                        <td style="text-align: center;"><?php echo substr($s['gender'], 0, 1); ?></td>
                        <td style="text-align: right;"><?php echo number_format($f['total_required']); ?></td>
                        <td style="text-align: right; font-weight: 700; color: #16a34a;"><?php echo number_format($f['total_paid']); ?></td>
                        <td style="text-align: right; font-weight: 800; color: <?php echo $f['balance'] >= 0 ? '#0ea5e9' : '#ef4444'; ?>;">
                            <?php echo number_format($f['balance']); ?>
                        </td>
                        <td style="text-align: center;">
                            <?php if ($f['balance'] >= 0): ?>
                                <span class="status-pill" style="background: #dcfce7; color: #166534;">CLEARED</span>
                            <?php else: ?>
                                <span class="status-pill" style="background: #fee2e2; color: #991b1b;">DEBTOR</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr class="totals-row">
                    <td colspan="4" style="text-align: right;">GRAND TOTALS:</td>
                    <td style="text-align: right;"><?php echo number_format($totals['required']); ?></td>
                    <td style="text-align: right; color: #16a34a;"><?php echo number_format($totals['paid']); ?></td>
                    <td style="text-align: right; color: <?php echo $totals['balance'] >= 0 ? '#0ea5e9' : '#ef4444'; ?>;"><?php echo number_format($totals['balance']); ?></td>
                    <td></td>
                </tr>
            </tfoot>
        </table>

        <div style="margin-top: 4rem; display: flex; justify-content: space-between;">
            <div style="text-align: center; width: 250px;">
                <p>__________________________</p>
                <p style="font-size: 0.8rem; margin-top: 0.5rem; font-weight: 700;">Class Teacher</p>
            </div>
            <div style="text-align: center; width: 250px;">
                <p>__________________________</p>
                <p style="font-size: 0.8rem; margin-top: 0.5rem; font-weight: 700;">School Bursar</p>
            </div>
            <div style="text-align: center; width: 250px;">
                <p>__________________________</p>
                <p style="font-size: 0.8rem; margin-top: 0.5rem; font-weight: 700;">Head of Institution</p>
            </div>
        </div>
        <p style="text-align: center; margin-top: 4rem; font-size: 0.75rem; color: #94a3b8;">This report was automatically generated from GSN Fees Management System on <?php echo date('d M Y, H:i'); ?></p>
    </div>
</body>
</html>
