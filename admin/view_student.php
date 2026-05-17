<?php
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

if (!isLoggedIn()) {
    redirect('../login.php');
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$selectedYearId = isset($_GET['year_id']) ? (int)$_GET['year_id'] : 0;

$currentYearData = getCurrentYearData($pdo);
if (!$selectedYearId) $selectedYearId = $currentYearData['id'];

$stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
$stmt->execute([$id]);
$student = $stmt->fetch();

if (!$student) {
    redirect('dashboard.php');
}

$status = getDetailedYearlyStatus($pdo, $student['id'], $selectedYearId);
$activeEnrollment = $status['enrollment'];

// Fetch ALL Enrollments (Academic Journey)
$stmt = $pdo->prepare("SELECT e.*, ay.year_name, c.class_name 
                      FROM enrollments e 
                      JOIN academic_years ay ON e.academic_year_id = ay.id 
                      JOIN classes c ON e.class_id = c.id 
                      WHERE e.student_id = ? 
                      ORDER BY ay.year_name DESC");
$stmt->execute([$id]);
$journey = $stmt->fetchAll();

// Fetch All Payments for selected year
$stmt = $pdo->prepare("SELECT * FROM payments WHERE student_id = ? AND academic_year_id = ? ORDER BY payment_date DESC");
$stmt->execute([$id, $selectedYearId]);
$payments = $stmt->fetchAll();

$academicYears = getAllAcademicYears($pdo);
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
                <a href="manage_classes.php">Classes</a>
                <a href="logout.php" style="color: var(--danger);">Logout</a>
            </nav>
            <div class="year-selector" style="margin-left: 1rem;">
                <form action="switch_year.php" method="POST">
                    <select name="switch_year_id" onchange="this.form.submit()" style="padding: 0.3rem 0.5rem; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 0.85rem; font-weight: 700; color: var(--primary-color);">
                        <?php foreach ($academicYears as $y): ?>
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
        <div class="profile-header">
            <div class="avatar"><?php echo strtoupper(substr($student['full_name'], 0, 1)); ?></div>
            <div style="flex: 1;">
                <h1 style="margin-bottom: 0.25rem; font-weight: 800; letter-spacing: -1px;"><?php echo htmlspecialchars($student['full_name']); ?></h1>
                <p style="color: var(--text-muted); font-size: 0.9rem;">Permanent Reg No: <strong style="color: var(--primary-color);"><?php echo $student['reg_number']; ?></strong></p>
                <div style="margin-top: 1rem; display: flex; gap: 0.5rem;">
                    <span class="status-badge" style="background: var(--accent-color); color: white; padding: 0.4rem 0.8rem; border-radius: 8px; font-weight: 600;">
                        <?php echo $activeEnrollment ? $activeEnrollment['class_name'] . ' ' . $activeEnrollment['stream'] : 'Not Enrolled'; ?>
                    </span>
                    <span class="status-badge" style="background: #f1f5f9; color: #475569; padding: 0.4rem 0.8rem; border-radius: 8px; font-weight: 600; border: 1px solid #e2e8f0;">
                        <?php echo $activeEnrollment ? $activeEnrollment['section'] : 'N/A'; ?> Section
                    </span>
                </div>
            </div>
            <div class="year-context" style="background: #f8fafc; padding: 1.5rem; border-radius: 16px; border: 1px solid var(--border-color); text-align: center;">
                <p style="font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); font-weight: 700; margin-bottom: 0.5rem;">Viewing Context</p>
                <form method="GET">
                    <input type="hidden" name="id" value="<?php echo $id; ?>">
                    <select name="year_id" onchange="this.form.submit()" style="margin-bottom: 0; background: white; padding: 0.4rem; border-radius: 8px; border: 1px solid #cbd5e1;">
                        <?php foreach ($academicYears as $ay): ?>
                            <option value="<?php echo $ay['id']; ?>" <?php echo $ay['id'] == $selectedYearId ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($ay['year_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
            <!-- Academic Journey -->
            <div class="info-card">
                <h3 class="mb-4">Historical Academic Journey</h3>
                <div class="history-list" style="margin-top: 1rem;">
                    <?php foreach ($journey as $idx => $j): ?>
                        <div class="history-item" style="<?php echo $idx == count($journey)-1 ? 'border-left-color: transparent;' : ''; ?>">
                            <p style="font-size: 0.8rem; font-weight: 700; color: var(--primary-color);"><?php echo $j['year_name']; ?></p>
                            <p style="font-weight: 600;">Class: <?php echo $j['class_name'] . ' ' . $j['stream']; ?></p>
                            <p style="font-size: 0.75rem; color: var(--text-muted);">Status: <span style="color: <?php echo $j['status'] == 'Active' ? 'var(--success)' : 'var(--text-main)'; ?>;"><?php echo $j['status']; ?></span></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Financial Status -->
            <div>
                <div class="info-card" style="border-top: 4px solid var(--primary-color);">
                    <h3 class="mb-4">Financial Overview (<?php echo $status['enrollment'] ? getAcademicYearById($pdo, $selectedYearId)['year_name'] : 'N/A'; ?>)</h3>
                    <div style="display: flex; flex-direction: column; gap: 1rem;">
                        <div style="display: flex; justify-content: space-between; border-bottom: 1px dashed var(--border-color); padding-bottom: 0.5rem;">
                            <span style="color: var(--text-muted);">Total Required:</span>
                            <span style="font-weight: 600;"><?php echo number_format($status['total_required']); ?> FRW</span>
                        </div>
                        <div style="display: flex; justify-content: space-between; border-bottom: 1px dashed var(--border-color); padding-bottom: 0.5rem;">
                            <span style="color: var(--text-muted);">Total Collected:</span>
                            <span style="font-weight: 600; color: var(--success);"><?php echo number_format($status['total_paid']); ?> FRW</span>
                        </div>
                        <div style="display: flex; justify-content: space-between; padding-top: 0.5rem;">
                            <span style="font-weight: 700;">Remaining Balance:</span>
                            <span style="font-weight: 800; font-size: 1.2rem; color: <?php echo $status['balance'] >= 0 ? 'var(--success)' : 'var(--danger)'; ?>">
                                <?php if ($status['no_fees_set']): ?>
                                    <span style="color: #64748b;">Pending Config</span>
                                <?php else: ?>
                                    <?php echo number_format(abs($status['balance'])); ?> FRW
                                    <?php echo $status['balance'] >= 0 ? '<span style="font-size: 0.7rem; display: block; text-align: right;">(Cleared)</span>' : ''; ?>
                                <?php endif; ?>
                            </span>
                        </div>
                    </div>
                </div>

                <div class="info-card">
                    <h3 class="mb-4">Payment History (Context Year)</h3>
                    <table style="width: 100%; border-collapse: collapse;">
                        <tr style="border-bottom: 1px solid var(--border-color);">
                            <th style="text-align: left; padding: 0.5rem 0; font-size: 0.75rem;">Date</th>
                            <th style="text-align: center; padding: 0.5rem 0; font-size: 0.75rem;">Term</th>
                            <th style="text-align: right; padding: 0.5rem 0; font-size: 0.75rem;">Amount</th>
                        </tr>
                        <?php if (empty($payments)): ?>
                            <tr><td colspan="3" style="text-align: center; padding: 2rem; color: var(--text-muted);">No payments recorded for this year.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($payments as $p): ?>
                            <tr style="border-bottom: 1px dotted var(--border-color);">
                                <td style="padding: 0.75rem 0; font-size: 0.85rem;"><?php echo date('Y-m-d', strtotime($p['payment_date'])); ?></td>
                                <td style="padding: 0.75rem 0; text-align: center; font-size: 0.85rem;">T<?php echo $p['term']; ?></td>
                                <td style="padding: 0.75rem 0; text-align: right; font-weight: 600; font-size: 0.85rem;"><?php echo number_format($p['amount_paid']); ?> FRW</td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                    <a href="record_payment.php?id=<?php echo $id; ?>&year_id=<?php echo $selectedYearId; ?>" style="display: block; text-align: center; margin-top: 1.5rem; color: var(--primary-color); text-decoration: none; font-weight: 700; font-size: 0.85rem;">+ Record New Payment</a>
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
