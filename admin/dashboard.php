<?php
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

if (!isLoggedIn()) {
    redirect('../login.php');
}

$years = getAllAcademicYears($pdo);
$currentYearData = getCurrentYearData($pdo);

$selectedYearId = isset($_GET['year_id']) ? (int)$_GET['year_id'] : $currentYearData['id'];
$selectedYear = getAcademicYearById($pdo, $selectedYearId);

if (!$selectedYear) {
    $selectedYear = $currentYearData;
    $selectedYearId = $currentYearData['id'];
}

$yearName = $selectedYear['year_name'];

// 1. SCHOOL SUMMARY DATA (Context-Aware)
$stmt = $pdo->prepare("SELECT COUNT(*) FROM enrollments WHERE academic_year_id = ? AND status = 'Active'");
$stmt->execute([$selectedYearId]);
$totalStudents = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT SUM(amount_paid) FROM payments WHERE academic_year_id = ?");
$stmt->execute([$selectedYearId]);
$totalCollected = $stmt->fetchColumn() ?: 0;

// Calculate Total Required for all enrolled students in the selected year
$stmt = $pdo->prepare("SELECT e.section, COUNT(*) as count FROM enrollments e WHERE e.academic_year_id = ? GROUP BY e.section");
$stmt->execute([$selectedYearId]);
$sections = $stmt->fetchAll();

$totalRequired = 0;
foreach ($sections as $sec) {
    for ($term = 1; $term <= 3; $term++) {
        $stmt = $pdo->prepare("SELECT amount FROM fees_structure WHERE section = ? AND term = ? AND academic_year_id = ?");
        $stmt->execute([$sec['section'], $term, $selectedYearId]);
        $amount = $stmt->fetchColumn() ?: 0;
        $totalRequired += ($amount * $sec['count']);
    }
}

$totalOwed = $totalRequired - $totalCollected;

// 2. SEARCH / STUDENT LIST
$search = isset($_GET['search']) ? $_GET['search'] : '';
$query = "SELECT s.*, e.class_id, e.stream, c.class_name 
          FROM students s 
          JOIN enrollments e ON s.id = e.student_id 
          JOIN classes c ON e.class_id = c.id 
          WHERE e.academic_year_id = ?";
$params = [$selectedYearId];

if ($search) {
    $query .= " AND (s.reg_number LIKE ? OR s.full_name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
$query .= " ORDER BY s.full_name ASC";
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
        .summary-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; }
        .summary-card { 
            background: white; 
            padding: 1.5rem; 
            border-radius: 16px; 
            box-shadow: var(--shadow-md); 
            border-bottom: 4px solid var(--primary-color);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .summary-card:hover { transform: translateY(-5px); box-shadow: var(--shadow-lg); }
        .summary-card h4 { font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 0.75rem; }
        .summary-card .value { font-size: 1.8rem; font-weight: 700; color: var(--text-main); }
        .summary-card.owed { border-bottom-color: var(--danger); }
        .summary-card.collected { border-bottom-color: var(--success); }
        
        .year-selector {
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(10px);
            padding: 1rem;
            border-radius: 12px;
            display: flex;
            align-items: center;
            gap: 1rem;
            border: 1px solid var(--border-color);
            margin-bottom: 2rem;
        }
        
        .search-bar { display: flex; gap: 1rem; margin-bottom: 1.5rem; }
        .search-bar input { flex: 1; border-radius: 12px; padding: 0.8rem 1.2rem; }
        .btn-sm { padding: 0.5rem 0.8rem; font-size: 0.75rem; font-weight: 600; text-decoration: none; border-radius: 8px; display: inline-block; }
        
        .status-pill {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
        }
    </style>
</head>
<body>
    <header>
        <div class="nav-container">
            <div class="logo">GSN <span>Fees Management</span></div>
            <nav class="nav-links">
                <a href="dashboard.php">Dashboard</a>
                <a href="manage_classes.php">Classes</a>
                <a href="requests.php">Requests</a>
                <a href="settings.php">Settings</a>
                <a href="add_student.php">Register</a>
                <a href="logout.php" style="color: var(--danger);">Logout</a>
            </nav>
        </div>
    </header>

    <main class="container">
        <div class="year-selector">
            <span style="font-weight: 600; color: var(--text-muted);">Switch Workspace:</span>
            <form method="GET" style="display: flex; gap: 0.5rem; align-items: center;">
                <select name="year_id" onchange="this.form.submit()" style="padding: 0.5rem; border-radius: 8px; width: auto; margin-bottom: 0;">
                    <?php foreach ($years as $y): ?>
                        <option value="<?php echo $y['id']; ?>" <?php echo $y['id'] == $selectedYearId ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($y['year_name']); ?> <?php echo $y['is_current'] ? '(Active)' : ''; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if ($search): ?>
                    <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
                <?php endif; ?>
            </form>
        </div>

        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
            <h2>Financial Workspace: <span style="color: var(--primary-color);"><?php echo htmlspecialchars($yearName); ?></span></h2>
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
                        <?php $s_status = getDetailedYearlyStatus($pdo, $student['id'], $selectedYearId); ?>
                        <tr>
                            <td><strong><?php echo $student['reg_number']; ?></strong></td>
                            <td>
                                <div style="font-weight: 600;"><?php echo htmlspecialchars($student['full_name']); ?></div>
                                <div style="font-size: 0.75rem; color: var(--text-muted);"><?php echo $student['section']; ?> Section</div>
                            </td>
                            <td><span style="background: #eff6ff; color: #1e40af; padding: 0.2rem 0.6rem; border-radius: 4px; font-weight: 600; font-size: 0.85rem;"><?php echo $student['class_name'] . ' ' . $student['stream']; ?></span></td>
                            <td>
                                <?php if ($s_status['balance'] >= 0): ?>
                                    <span class="status-pill status-paid">Cleared</span>
                                <?php elseif ($s_status['total_paid'] > 0): ?>
                                    <span class="status-pill status-pending">Partial</span>
                                <?php else: ?>
                                    <span class="status-pill status-unpaid">Debtor</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div style="display: flex; gap: 0.4rem;">
                                    <a href="view_student.php?id=<?php echo $student['id']; ?>&year_id=<?php echo $selectedYearId; ?>" class="btn btn-secondary btn-sm" style="background: var(--secondary-color);">Manage</a>
                                    <a href="record_payment.php?id=<?php echo $student['id']; ?>&year_id=<?php echo $selectedYearId; ?>" class="btn btn-primary btn-sm" style="width: auto;">Pay</a>
                                    <a href="print_student.php?id=<?php echo $student['id']; ?>&year_id=<?php echo $selectedYearId; ?>" class="btn btn-secondary btn-sm" style="background: var(--accent-color);">Slip</a>
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
