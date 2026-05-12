<?php
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

$current_system_year = getCurrentYear($pdo);
$search_reg = isset($_POST['reg_number']) ? trim($_POST['reg_number']) : '';
$search_year = isset($_POST['year']) && !empty($_POST['year']) ? trim($_POST['year']) : $current_system_year;

$student = null;
$status = null;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $search_reg) {
    $stmt = $pdo->prepare("SELECT * FROM students WHERE reg_number = ?");
    $stmt->execute([$search_reg]);
    $student = $stmt->fetch();

    if ($student) {
        $status = getDetailedYearlyStatus($pdo, $student['id'], $search_year);
    } else {
        $error = "No student found with Registration Number: $search_reg";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Portal - GSN Fees Management</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .portal-card { max-width: 600px; margin: 4rem auto; }
        .result-card { background: white; padding: 2rem; border-radius: 12px; margin-top: 2rem; box-shadow: var(--shadow-lg); }
        .status-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; border-bottom: 1px solid var(--border-color); padding-bottom: 1rem; }
    </style>
</head>
<body style="background: linear-gradient(135deg, #4f46e5 0%, #06b6d4 100%); min-height: 100vh;">
    <div class="container">
        <div class="card portal-card">
            <h1 class="text-center mb-4" style="color: var(--secondary-color);">Student Portal</h1>
            <p class="text-center mb-4" style="color: var(--text-muted);">Enter your details to check your payment status.</p>
            
            <?php if ($error): ?><div style="color: var(--danger); margin-bottom: 1rem; text-align: center;"><?php echo $error; ?></div><?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label>Registration Number</label>
                    <input type="text" name="reg_number" value="<?php echo htmlspecialchars($search_reg); ?>" placeholder="e.g. GSN-2026-00000001" required>
                </div>
                <div class="form-group">
                    <label>Academic Year Range</label>
                    <input type="text" name="year" value="<?php echo htmlspecialchars($search_year); ?>" placeholder="e.g. 2025-2026">
                </div>
                <button type="submit" class="btn btn-primary mt-2">Check My Status</button>
            </form>

            <div style="text-align: center; margin-top: 1.5rem;">
                <a href="login.php" style="color: var(--primary-color); text-decoration: none; font-size: 0.9rem;">Admin Login</a>
            </div>
        </div>

        <?php if ($student && $status): ?>
            <div class="result-card portal-card">
                <div class="status-header">
                    <div>
                        <h2><?php echo htmlspecialchars($student['full_name']); ?></h2>
                        <p style="color: var(--text-muted);">Year: <?php echo htmlspecialchars($search_year); ?></p>
                    </div>
                    <div style="text-align: right;">
                        <span class="status-badge <?php echo $status['balance'] >= 0 ? 'status-paid' : 'status-unpaid'; ?>">
                            <?php echo $status['balance'] >= 0 ? 'FULLY PAID' : 'DEBT RECORDED'; ?>
                        </span>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 2rem;">
                    <div style="padding: 1rem; background: #f8fafc; border-radius: 8px;">
                        <p style="font-size: 0.8rem; color: var(--text-muted);">Total Paid</p>
                        <p style="font-size: 1.25rem; font-weight: 700; color: var(--success);"><?php echo number_format($status['total_paid']); ?> FRW</p>
                    </div>
                    <div style="padding: 1rem; background: #f8fafc; border-radius: 8px;">
                        <p style="font-size: 0.8rem; color: var(--text-muted);"><?php echo $status['balance'] >= 0 ? 'Credit/Overpaid' : 'Balance Due'; ?></p>
                        <p style="font-size: 1.25rem; font-weight: 700; color: <?php echo $status['balance'] >= 0 ? 'var(--accent-color)' : 'var(--danger)'; ?>;">
                            <?php echo number_format(abs($status['balance'])); ?> FRW
                        </p>
                    </div>
                </div>

                <div style="text-align: center;">
                    <p style="font-size: 0.9rem; color: var(--text-muted);">Current Class: <?php echo $student['current_class'] . ' ' . $student['current_stream']; ?></p>
                    <button onclick="window.print()" class="btn btn-secondary mt-4" style="width: auto;">Print My Status Slip</button>
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
