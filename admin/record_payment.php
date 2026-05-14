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

$enrollment = getEnrollment($pdo, $id, $selectedYearId);
if (!$enrollment) {
    // If not enrolled in the selected year, we might want to enroll them or show an error
    $error = "Warning: This student is not enrolled in the selected academic year.";
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_payment'])) {
    $amount = $_POST['amount'];
    $term = $_POST['term'];
    $academic_year_id = (int)$_POST['academic_year_id'];
    
    if ($amount > 0 && $term && $academic_year_id) {
        try {
            $stmt = $pdo->prepare("INSERT INTO payments (student_id, academic_year_id, term, amount_paid) VALUES (?, ?, ?, ?)");
            $stmt->execute([$id, $academic_year_id, $term, $amount]);
            $message = "Payment of FRW " . number_format($amount) . " recorded successfully.";
        } catch (PDOException $e) {
            $error = "Error: " . $e->getMessage();
        }
    } else {
        $error = "Please fill in all fields correctly.";
    }
}

// Fetch payment history for the selected year
$stmt = $pdo->prepare("SELECT p.*, ay.year_name FROM payments p 
                      JOIN academic_years ay ON p.academic_year_id = ay.id 
                      WHERE p.student_id = ? AND p.academic_year_id = ? 
                      ORDER BY p.payment_date DESC");
$stmt->execute([$id, $selectedYearId]);
$payments = $stmt->fetchAll();

$financials = getDetailedYearlyStatus($pdo, $id, $selectedYearId);
$section = $enrollment ? $enrollment['section'] : $student['section'];

$fees = [
    1 => getFeeAmount($pdo, $section, 1, $selectedYearId),
    2 => getFeeAmount($pdo, $section, 2, $selectedYearId),
    3 => getFeeAmount($pdo, $section, 3, $selectedYearId)
];

$academicYears = getAllAcademicYears($pdo);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Record Payment - GSN Fees Management</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <header>
        <div class="nav-container">
            <div class="logo">GSN <span>Fees Management</span></div>
            <nav class="nav-links">
                <a href="dashboard.php">Dashboard</a>
                <a href="add_student.php">Register Student</a>
                <a href="logout.php" style="color: var(--danger);">Logout</a>
            </nav>
        </div>
    </header>

    <main class="container">
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
            <div class="dashboard-card">
                <h3 class="mb-4">Record Payment: <?php echo htmlspecialchars($student['full_name']); ?></h3>
                <?php if ($error): ?><div style="color: var(--danger); margin-bottom: 1rem;"><?php echo $error; ?></div><?php endif; ?>
                <?php if ($message): ?><div style="color: var(--success); margin-bottom: 1rem;"><?php echo $message; ?></div><?php endif; ?>

                <form method="POST">
                    <div class="form-group">
                        <label>Academic Year Workspace</label>
                        <select name="academic_year_id" onchange="window.location.href='?id=<?php echo $id; ?>&year_id=' + this.value">
                            <?php foreach ($academicYears as $ay): ?>
                                <option value="<?php echo $ay['id']; ?>" <?php echo $ay['id'] == $selectedYearId ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($ay['year_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Target Term</label>
                        <select name="term" required>
                            <option value="1">Term 1 (FRW <?php echo number_format($fees[1]); ?>)</option>
                            <option value="2">Term 2 (FRW <?php echo number_format($fees[2]); ?>)</option>
                            <option value="3">Term 3 (FRW <?php echo number_format($fees[3]); ?>)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Amount (FRW)</label>
                        <input type="number" name="amount" required placeholder="Enter amount paid">
                    </div>
                    <button type="submit" name="save_payment" class="btn btn-primary">Submit Payment Record</button>
                </form>

                <div style="margin-top: 2rem; padding: 1rem; background: #eff6ff; border-radius: 12px; border: 1px solid #dbeafe;">
                    <h4 style="font-size: 0.85rem; color: #1e40af; text-transform: uppercase; margin-bottom: 0.5rem;">Financial Summary (<?php echo $selectedYearId == $currentYearData['id'] ? 'Current Year' : 'Selected Year'; ?>)</h4>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.25rem;">
                        <span>Total Required:</span>
                        <strong><?php echo number_format($financials['total_required']); ?> FRW</strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.25rem;">
                        <span>Total Paid:</span>
                        <strong style="color: var(--success);"><?php echo number_format($financials['total_paid']); ?> FRW</strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; border-top: 1px solid #dbeafe; padding-top: 0.5rem; margin-top: 0.5rem;">
                        <span>Balance:</span>
                        <strong style="color: <?php echo $financials['balance'] >= 0 ? 'var(--success)' : 'var(--danger)'; ?>;">
                            <?php echo number_format($financials['balance']); ?> FRW
                        </strong>
                    </div>
                </div>
            </div>

            <div class="dashboard-card">
                <h3 class="mb-4">Payment History</h3>
                <div class="table-container" style="box-shadow: none; border: 1px solid var(--border-color);">
                    <table>
                        <thead>
                            <tr><th>Year</th><th>Term</th><th>Amount</th><th>Actions</th></tr>
                        </thead>
                        <tbody>
                                    <tr>
                                        <td><?php echo htmlspecialchars($p['year_name']); ?></td>
                                        <td>Term <?php echo $p['term']; ?></td>
                                        <td><strong><?php echo number_format($p['amount_paid']); ?></strong></td>
                                    <td>
                                        <a href="edit_payment.php?id=<?php echo $p['id']; ?>&student_id=<?php echo $id; ?>" style="color: var(--primary-color); text-decoration: none; font-size: 0.85rem; margin-right: 0.5rem;">Edit</a>
                                        <a href="delete_payment.php?id=<?php echo $p['id']; ?>&student_id=<?php echo $id; ?>" style="color: var(--danger); text-decoration: none; font-size: 0.85rem;" onclick="return confirm('Delete this record?')">Delete</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
