<?php
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

if (!isLoggedIn()) {
    redirect('../login.php');
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
$stmt->execute([$id]);
$student = $stmt->fetch();

if (!$student) {
    redirect('dashboard.php');
}

$year = getCurrentYear($pdo);
$status = getDetailedYearlyStatus($pdo, $student['id'], $year);

// Fetch Academic History
$stmt = $pdo->prepare("SELECT * FROM academic_history WHERE student_id = ? ORDER BY change_date DESC");
$stmt->execute([$id]);
$history = $stmt->fetchAll();

// Fetch All-time Payments
$stmt = $pdo->prepare("SELECT * FROM payments WHERE student_id = ? ORDER BY payment_date DESC");
$stmt->execute([$id]);
$payments = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Profile - <?php echo htmlspecialchars($student['full_name']); ?></title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .profile-header { background: white; padding: 2rem; border-radius: 12px; margin-bottom: 2rem; display: flex; align-items: center; gap: 2rem; box-shadow: var(--shadow-sm); }
        .avatar { width: 80px; height: 80px; background: var(--primary-color); color: white; display: flex; align-items: center; justify-content: center; font-size: 2rem; font-weight: 700; border-radius: 50%; }
        .info-card { background: white; padding: 1.5rem; border-radius: 12px; margin-bottom: 1.5rem; box-shadow: var(--shadow-sm); }
        .history-item { padding: 1rem 0; border-left: 2px solid var(--border-color); padding-left: 1.5rem; position: relative; margin-left: 0.5rem; }
        .history-item::before { content: ''; width: 12px; height: 12px; background: var(--primary-color); border-radius: 50%; position: absolute; left: -7px; top: 1.25rem; }
    </style>
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
        <div class="profile-header">
            <div class="avatar"><?php echo strtoupper(substr($student['full_name'], 0, 1)); ?></div>
            <div>
                <h1 style="margin-bottom: 0.25rem;"><?php echo htmlspecialchars($student['full_name']); ?></h1>
                <p style="color: var(--text-muted);">Registration Number: <strong><?php echo $student['reg_number']; ?></strong></p>
                <p style="margin-top: 0.5rem;"><span class="status-badge" style="background: var(--accent-color); color: white;"><?php echo $student['section']; ?> - <?php echo $student['current_class']; ?> <?php echo $student['current_stream']; ?></span></p>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
            <!-- Academic Journey -->
            <div class="info-card">
                <h3 class="mb-4">Academic Journey (Where studied before)</h3>
                <?php if (empty($history)): ?>
                    <p style="color: var(--text-muted);">Joined the school in <strong><?php echo $student['current_class']; ?></strong>. No promotions recorded yet.</p>
                <?php else: ?>
                    <div class="history-list">
                        <?php foreach ($history as $h): ?>
                            <div class="history-item">
                                <p style="font-size: 0.8rem; color: var(--text-muted);"><?php echo date('M d, Y', strtotime($h['change_date'])); ?></p>
                                <p>Moved from <strong><?php echo $h['old_class']; ?> <?php echo $h['old_stream']; ?></strong> to <strong><?php echo $h['new_class']; ?> <?php echo $h['new_stream']; ?></strong></p>
                            </div>
                        <?php endforeach; ?>
                        <div class="history-item" style="border-left-color: transparent;">
                            <p style="font-size: 0.8rem; color: var(--text-muted);"><?php echo date('M d, Y', strtotime($student['created_at'])); ?></p>
                            <p>Initial Registration: <strong>First joined the school.</strong></p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Financial Status -->
            <div>
                <div class="info-card">
                    <h3 class="mb-4">Current Financial Status (<?php echo $year; ?>)</h3>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                        <span>Total Paid:</span>
                        <strong><?php echo number_format($status['total_paid']); ?> FRW</strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                        <span>Balance:</span>
                        <strong style="color: <?php echo $status['balance'] >= 0 ? 'var(--success)' : 'var(--danger)'; ?>">
                            <?php echo number_format($status['balance']); ?> FRW
                        </strong>
                    </div>
                </div>

                <div class="info-card">
                    <h3 class="mb-4">Recent Payments</h3>
                    <table style="width: 100%; border-collapse: collapse;">
                        <tr style="border-bottom: 1px solid var(--border-color);">
                            <th style="text-align: left; padding: 0.5rem 0;">Date</th>
                            <th style="text-align: right; padding: 0.5rem 0;">Amount</th>
                        </tr>
                        <?php foreach (array_slice($payments, 0, 5) as $p): ?>
                            <tr style="border-bottom: 1px dotted var(--border-color);">
                                <td style="padding: 0.5rem 0;"><?php echo date('Y-m-d', strtotime($p['payment_date'])); ?></td>
                                <td style="padding: 0.5rem 0; text-align: right;"><?php echo number_format($p['amount_paid']); ?> FRW</td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                    <a href="record_payment.php?id=<?php echo $id; ?>" style="display: block; text-align: center; margin-top: 1rem; color: var(--primary-color); text-decoration: none; font-size: 0.85rem;">View Full Payment History</a>
                </div>
            </div>
        </div>
        
        <div style="text-align: center; margin-top: 2rem;">
            <a href="dashboard.php" class="btn btn-secondary" style="width: auto;">Back to Dashboard</a>
            <a href="print_student.php?id=<?php echo $id; ?>" class="btn btn-primary" style="width: auto;">Print Profile & Slip</a>
        </div>
    </main>
</body>
</html>
