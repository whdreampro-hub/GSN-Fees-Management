<?php
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

if (!isLoggedIn()) {
    redirect('../login.php');
}

$search = isset($_GET['search']) ? $_GET['search'] : '';
$year = getCurrentYear($pdo);

// 1. SCHOOL SUMMARY DATA
$stmt = $pdo->query("SELECT COUNT(*) FROM students");
$totalStudents = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT SUM(amount_paid) FROM payments WHERE year = ?");
$stmt->execute([$year]);
$totalCollected = $stmt->fetchColumn() ?: 0;

$p_fee = getFeeAmount($pdo, 'Primary', 1) * 3;
$s_fee = getFeeAmount($pdo, 'Secondary', 1) * 3;

$stmt = $pdo->query("SELECT 
    SUM(CASE WHEN section = 'Primary' THEN 1 ELSE 0 END) as p_count,
    SUM(CASE WHEN section = 'Secondary' THEN 1 ELSE 0 END) as s_count
    FROM students");
$counts = $stmt->fetch();
$totalRequired = ($counts['p_count'] * $p_fee) + ($counts['s_count'] * $s_fee);
$totalOwed = $totalRequired - $totalCollected;

// 2. SEARCH / STUDENT LIST
$query = "SELECT * FROM students";
$params = [];
if ($search) {
    $query .= " WHERE reg_number LIKE ? OR full_name LIKE ?";
    $params = ["%$search%", "%$search%"];
}
$query .= " ORDER BY created_at DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$students = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - GSN Fees Management</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .summary-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; }
        .summary-card { background: white; padding: 1.5rem; border-radius: 12px; box-shadow: var(--shadow-sm); border-left: 5px solid var(--primary-color); }
        .summary-card h4 { font-size: 0.85rem; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.5rem; }
        .summary-card .value { font-size: 1.75rem; font-weight: 700; color: var(--text-main); }
        .summary-card.owed { border-left-color: var(--danger); }
        .summary-card.collected { border-left-color: var(--success); }
        .search-bar { display: flex; gap: 1rem; margin-bottom: 2rem; }
        .search-bar input { flex: 1; }
        .btn-sm { padding: 0.4rem 0.6rem; font-size: 0.7rem; text-decoration: none; border-radius: 6px; display: inline-block; }
    </style>
</head>
<body>
    <header>
        <div class="nav-container">
            <div class="logo">GSN <span>Fees Management</span></div>
            <nav class="nav-links">
                <a href="dashboard.php">Dashboard</a>
                <a href="manage_classes.php">Manage Classes</a>
                <a href="settings.php">Settings</a>
                <a href="add_student.php">Register Student</a>
                <a href="logout.php" style="color: var(--danger);">Logout</a>
            </nav>
        </div>
    </header>

    <main class="container">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
            <h2>Financial Overview</h2>
            <p>Academic Year: <span style="background: var(--accent-color); color: white; padding: 0.2rem 0.6rem; border-radius: 4px;"><?php echo $year; ?></span></p>
        </div>

        <div class="summary-grid">
            <div class="summary-card">
                <h4>Total Students</h4>
                <div class="value"><?php echo number_format($totalStudents); ?></div>
            </div>
            <div class="summary-card collected">
                <h4>Total Collected (FRW)</h4>
                <div class="value"><?php echo number_format($totalCollected); ?></div>
            </div>
            <div class="summary-card owed">
                <h4>Outstanding Debt (FRW)</h4>
                <div class="value"><?php echo number_format($totalOwed > 0 ? $totalOwed : 0); ?></div>
            </div>
        </div>

        <h2 class="mb-4">Student Quick Lookup</h2>
        <form method="GET" class="search-bar">
            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search by Reg Number or Name...">
            <button type="submit" class="btn btn-secondary" style="width: auto;">Search</button>
        </form>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Reg Number</th>
                        <th>Full Name</th>
                        <th>Class</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($students as $student): ?>
                        <?php $s_status = getDetailedYearlyStatus($pdo, $student['id'], $year); ?>
                        <tr>
                            <td><strong><?php echo $student['reg_number']; ?></strong></td>
                            <td><?php echo htmlspecialchars($student['full_name']); ?></td>
                            <td><?php echo $student['current_class'] . ' ' . $student['current_stream']; ?></td>
                            <td>
                                <?php if ($s_status['balance'] >= 0): ?>
                                    <span class="status-badge status-paid">Paid</span>
                                <?php elseif ($s_status['total_paid'] > 0): ?>
                                    <span class="status-badge status-pending">Partial</span>
                                <?php else: ?>
                                    <span class="status-badge status-unpaid">Unpaid</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div style="display: flex; gap: 0.3rem;">
                                    <a href="view_student.php?id=<?php echo $student['id']; ?>" class="btn btn-secondary btn-sm" style="background: var(--primary-color);">Info</a>
                                    <a href="record_payment.php?id=<?php echo $student['id']; ?>" class="btn btn-primary btn-sm">Pay</a>
                                    <a href="print_student.php?id=<?php echo $student['id']; ?>" class="btn btn-secondary btn-sm" style="background: var(--secondary-color);">Slip</a>
                                    <a href="promote_student.php?id=<?php echo $student['id']; ?>" class="btn btn-secondary btn-sm" style="background: var(--accent-color);">Move</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>
</body>
</html>
