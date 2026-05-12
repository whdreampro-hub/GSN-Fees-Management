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

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount = $_POST['amount'];
    $term = $_POST['term'];
    $year = $_POST['year'];
    
    if ($amount > 0 && $term && $year) {
        try {
            $stmt = $pdo->prepare("INSERT INTO payments (student_id, year, term, amount_paid) VALUES (?, ?, ?, ?)");
            $stmt->execute([$id, $year, $term, $amount]);
            $message = "Payment recorded successfully.";
        } catch (PDOException $e) {
            $error = "Error: " . $e->getMessage();
        }
    } else {
        $error = "Please fill in all fields correctly.";
    }
}

// Fetch payment history
$stmt = $pdo->prepare("SELECT * FROM payments WHERE student_id = ? ORDER BY payment_date DESC");
$stmt->execute([$id]);
$payments = $stmt->fetchAll();

$fees = [
    1 => getFeeAmount($pdo, $student['section'], 1),
    2 => getFeeAmount($pdo, $student['section'], 2),
    3 => getFeeAmount($pdo, $student['section'], 3)
];
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
                        <label>Academic Year</label>
                        <input type="number" name="year" value="2026" required>
                    </div>
                    <div class="form-group">
                        <label>Term</label>
                        <select name="term" required>
                            <option value="1">Term 1 (Fee: <?php echo $fees[1]; ?>)</option>
                            <option value="2">Term 2 (Fee: <?php echo $fees[2]; ?>)</option>
                            <option value="3">Term 3 (Fee: <?php echo $fees[3]; ?>)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Amount (FRW)</label>
                        <input type="number" name="amount" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Save Payment</button>
                </form>
            </div>

            <div class="dashboard-card">
                <h3 class="mb-4">Payment History</h3>
                <div class="table-container" style="box-shadow: none; border: 1px solid var(--border-color);">
                    <table>
                        <thead>
                            <tr><th>Year</th><th>Term</th><th>Amount</th><th>Actions</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($payments as $p): ?>
                                <tr>
                                    <td><?php echo $p['year']; ?></td>
                                    <td>Term <?php echo $p['term']; ?></td>
                                    <td><?php echo number_format($p['amount_paid']); ?></td>
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
