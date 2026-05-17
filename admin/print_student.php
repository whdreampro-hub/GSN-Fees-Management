<?php
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

if (!isLoggedIn()) {
    redirect('../login.php');
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$year_id = isset($_GET['year_id']) ? (int)$_GET['year_id'] : 0;

$currentYearData = getCurrentYearData($pdo);
if (!$year_id) $year_id = $currentYearData['id'];

$stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
$stmt->execute([$id]);
$student = $stmt->fetch();

if (!$student) {
    redirect('dashboard.php');
}

$status = getDetailedYearlyStatus($pdo, $student['id'], $year_id);
$enrollment = $status['enrollment'];
$yearData = getAcademicYearById($pdo, $year_id);

$stmt = $pdo->prepare("SELECT * FROM payments WHERE student_id = ? AND academic_year_id = ? ORDER BY payment_date ASC");
$stmt->execute([$id, $year_id]);
$payments = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Fee Slip - <?php echo htmlspecialchars($student['full_name']); ?></title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        body { background: white; padding: 0; font-family: 'Outfit', sans-serif; }
        .slip-container { border: 2px solid #334155; padding: 3rem; margin: 2rem auto; max-width: 800px; border-radius: 16px; position: relative; }
        .no-print { text-align: center; margin: 1rem; position: sticky; top: 0; z-index: 100; background: rgba(255,255,255,0.8); backdrop-filter: blur(8px); padding: 1rem; }
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin: 2rem 0; padding-bottom: 1.5rem; border-bottom: 1px solid #e2e8f0; }
        .stamp-area { margin-top: 4rem; display: flex; justify-content: space-between; align-items: flex-end; }
        @media print { 
            .no-print { display: none; } 
            .slip-container { border: none; margin: 0; max-width: 100%; box-shadow: none; } 
            body { padding: 0; }
        }
    </style>
</head>
<body>
    <div class="no-print">
        <button onclick="window.print()" class="btn btn-primary" style="width: auto;">Print Official Slip</button>
        <a href="view_student.php?id=<?php echo $id; ?>&year_id=<?php echo $year_id; ?>" class="btn btn-secondary" style="width: auto; text-decoration: none;">Back to Profile</a>
    </div>

    <div class="slip-container">
        <div style="text-align: center; border-bottom: 4px double #334155; padding-bottom: 1.5rem; margin-bottom: 2rem;">
            <h1 style="font-size: 2.5rem; letter-spacing: -2px; color: #1e293b;">GS NYAGISOZI</h1>
            <h3 style="color: #64748b; font-weight: 500; text-transform: uppercase; letter-spacing: 2px;">Official Student Fee Slip</h3>
            <p style="margin-top: 0.5rem; font-weight: 600;">Academic Workspace: <?php echo htmlspecialchars($yearData['year_name']); ?></p>
        </div>

        <div class="info-grid">
            <div>
                <p style="color: #64748b; font-size: 0.8rem; text-transform: uppercase; font-weight: 700;">Student Identity</p>
                <p style="font-size: 1.2rem; font-weight: 700;"><?php echo htmlspecialchars($student['full_name']); ?></p>
                <p style="font-family: monospace; color: #334155;">REG: <?php echo $student['reg_number']; ?></p>
            </div>
            <div style="text-align: right;">
                <p style="color: #64748b; font-size: 0.8rem; text-transform: uppercase; font-weight: 700;">Placement Details</p>
                <?php if ($enrollment): ?>
                    <p style="font-size: 1.2rem; font-weight: 700;"><?php echo $enrollment['class_name'] . ' ' . $enrollment['stream']; ?></p>
                    <p><?php echo $enrollment['section']; ?> Section</p>
                <?php else: ?>
                    <p style="color: var(--danger);">No Enrollment Found</p>
                <?php endif; ?>
            </div>
        </div>

        <div style="background: #f8fafc; padding: 2rem; border-radius: 12px; margin-bottom: 2rem; border: 1px solid #e2e8f0;">
            <h4 style="margin-bottom: 1rem; color: #1e293b;">Financial Summary Overview</h4>
            <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                <span style="color: #64748b;">Total Yearly Requirement:</span>
                <span style="font-weight: 600;"><?php echo number_format($status['total_required']); ?> FRW</span>
            </div>
            <div style="display: flex; justify-content: space-between; margin-bottom: 1rem;">
                <span style="color: #64748b;">Total Amount Collected:</span>
                <span style="font-weight: 600; color: #16a34a;"><?php echo number_format($status['total_paid']); ?> FRW</span>
            </div>
            <div style="font-size: 1.5rem; font-weight: 800; padding-top: 1rem; border-top: 2px solid #fff; display: flex; justify-content: space-between; align-items: center;">
                <span style="font-size: 1rem;">Closing Status:</span>
                <?php if ($status['no_fees_set']): ?>
                    <span style="color: #64748b;">PENDING CONFIGURATION</span>
                <?php elseif ($status['balance'] < 0): ?>
                    <span style="color: #ef4444;">DUE: <?php echo number_format(abs($status['balance'])); ?> FRW</span>
                <?php else: ?>
                    <span style="color: #10b981;">CLEARED <?php echo $status['balance'] > 0 ? '(Credit: '.number_format($status['balance']).')' : ''; ?></span>
                <?php endif; ?>
            </div>
        </div>

        <h4 style="color: #1e293b; margin-bottom: 1rem;">Payment Transaction History</h4>
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="background: #f1f5f9;">
                    <th style="text-align: left; padding: 12px;">Transaction Date</th>
                    <th style="text-align: center; padding: 12px;">Term</th>
                    <th style="text-align: right; padding: 12px;">Amount Paid</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($payments)): ?>
                    <tr><td colspan="3" style="text-align: center; padding: 2rem; color: #94a3b8;">No payment records found for this workspace.</td></tr>
                <?php endif; ?>
                <?php foreach ($payments as $p): ?>
                    <tr style="border-bottom: 1px solid #e2e8f0;">
                        <td style="padding: 12px;"><?php echo date('d M, Y', strtotime($p['payment_date'])); ?></td>
                        <td style="padding: 12px; text-align: center;">Term <?php echo $p['term']; ?></td>
                        <td style="padding: 12px; text-align: right; font-weight: 700;"><?php echo number_format($p['amount_paid']); ?> FRW</td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="stamp-area">
            <div style="text-align: left;">
                <p style="font-size: 0.75rem; color: #94a3b8;">Generated on: <?php echo date('Y-m-d H:i'); ?></p>
            </div>
            <div style="text-align: center;">
                <p>Official School Stamp & Signature</p>
                <div style="margin-top: 4rem; width: 250px; border-top: 1px solid #000;"></div>
            </div>
        </div>
    </div>
</body>
</html>
