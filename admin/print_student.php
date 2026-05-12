<?php
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

if (!isLoggedIn()) {
    redirect('../login.php');
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$year = getCurrentYear($pdo);

$stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
$stmt->execute([$id]);
$student = $stmt->fetch();

if (!$student) {
    redirect('dashboard.php');
}

$status = getDetailedYearlyStatus($pdo, $student['id'], $year);
$stmt = $pdo->prepare("SELECT * FROM payments WHERE student_id = ? AND year = ? ORDER BY payment_date ASC");
$stmt->execute([$id, $year]);
$payments = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Fee Slip - <?php echo htmlspecialchars($student['full_name']); ?></title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        body { background: white; padding: 0; }
        .slip-container { border: 2px solid #000; padding: 2rem; margin: 2rem auto; max-width: 700px; }
        .no-print { text-align: center; margin: 1rem; }
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin: 2rem 0; border-bottom: 1px solid #ddd; padding-bottom: 1rem; }
        @media print { .no-print { display: none; } .slip-container { border: none; margin: 0; max-width: 100%; } }
    </style>
</head>
<body>
    <div class="no-print">
        <button onclick="window.print()" class="btn btn-primary" style="width: auto;">Print Fee Slip</button>
        <a href="record_payment.php?id=<?php echo $id; ?>" class="btn btn-secondary" style="width: auto; text-decoration: none;">Back</a>
    </div>

    <div class="slip-container">
        <div style="text-align: center; border-bottom: 2px solid #000; padding-bottom: 1rem;">
            <h1>GS NYAGISOZI</h1>
            <h3>OFFICIAL STUDENT FEE SLIP</h3>
            <p>Academic Year: <?php echo $year; ?></p>
        </div>

        <div class="info-grid">
            <div>
                <p><strong>Student Name:</strong> <?php echo htmlspecialchars($student['full_name']); ?></p>
                <p><strong>Reg Number:</strong> <?php echo $student['reg_number']; ?></p>
            </div>
            <div style="text-align: right;">
                <p><strong>Class:</strong> <?php echo $student['current_class'] . ' ' . $student['current_stream']; ?></p>
                <p><strong>Section:</strong> <?php echo $student['section']; ?></p>
            </div>
        </div>

        <div style="background: #f8fafc; padding: 1rem; border-radius: 8px; margin-bottom: 2rem;">
            <h4 style="margin-bottom: 0.5rem;">Financial Summary</h4>
            <div style="display: flex; justify-content: space-between;">
                <span>Total Required: <?php echo number_format($status['total_required']); ?> FRW</span>
                <span>Total Paid: <?php echo number_format($status['total_paid']); ?> FRW</span>
            </div>
            <div style="font-size: 1.2rem; font-weight: 700; margin-top: 0.5rem; text-align: center;">
                <?php if ($status['balance'] < 0): ?>
                    <span style="color: #ef4444;">BALANCE DUE: <?php echo number_format(abs($status['balance'])); ?> FRW</span>
                <?php else: ?>
                    <span style="color: #10b981;">STATUS: FULLY PAID (Credit: <?php echo number_format($status['balance']); ?> FRW)</span>
                <?php endif; ?>
            </div>
        </div>

        <h4>Payment History</h4>
        <table style="width: 100%; border-collapse: collapse; margin-top: 1rem;">
            <thead>
                <tr style="border-bottom: 1px solid #000;">
                    <th style="text-align: left; padding: 8px;">Date</th>
                    <th style="text-align: left; padding: 8px;">Term</th>
                    <th style="text-align: right; padding: 8px;">Amount Paid</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($payments as $p): ?>
                    <tr style="border-bottom: 1px dotted #ccc;">
                        <td style="padding: 8px;"><?php echo date('Y-m-d', strtotime($p['payment_date'])); ?></td>
                        <td style="padding: 8px;">Term <?php echo $p['term']; ?></td>
                        <td style="padding: 8px; text-align: right;"><?php echo number_format($p['amount_paid']); ?> FRW</td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div style="margin-top: 4rem; text-align: center;">
            <p>This is an officially generated document.</p>
            <p style="margin-top: 2rem;">Official School Stamp / Signature</p>
            <p style="margin-top: 3rem;">...................................................</p>
        </div>
    </div>
</body>
</html>
