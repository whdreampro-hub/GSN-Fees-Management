<?php
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

if (!isLoggedIn()) {
    redirect('../login.php');
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$student_id = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;

$stmt = $pdo->prepare("SELECT * FROM payments WHERE id = ?");
$stmt->execute([$id]);
$payment = $stmt->fetch();

if (!$payment) {
    redirect("record_payment.php?id=$student_id");
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount = $_POST['amount'];
    $term = $_POST['term'];
    $year = $_POST['year'];
    
    if ($amount > 0 && $term && $year) {
        try {
            $stmt = $pdo->prepare("UPDATE payments SET amount_paid = ?, term = ?, year = ? WHERE id = ?");
            $stmt->execute([$amount, $term, $year, $id]);
            $message = "Payment updated successfully! <a href='record_payment.php?id=$student_id'>Go back</a>";
        } catch (PDOException $e) {
            $error = "Error: " . $e->getMessage();
        }
    } else {
        $error = "Please fill in all fields correctly.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Payment - GSN Fees Management</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <header>
        <div class="nav-container">
            <div class="logo">GSN <span>Fees Management</span></div>
            <nav class="nav-links">
                <a href="dashboard.php">Dashboard</a>
                <a href="logout.php" style="color: var(--danger);">Logout</a>
            </nav>
        </div>
    </header>

    <main class="container">
        <div class="card" style="margin: 0 auto; max-width: 500px;">
            <h2 class="mb-4">Edit Payment Record</h2>
            
            <?php if ($error): ?><div style="color: var(--danger); margin-bottom: 1rem;"><?php echo $error; ?></div><?php endif; ?>
            <?php if ($message): ?><div style="color: var(--success); margin-bottom: 1rem;"><?php echo $message; ?></div><?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label>Academic Year</label>
                    <input type="number" name="year" value="<?php echo $payment['year']; ?>" required>
                </div>
                <div class="form-group">
                    <label>Term</label>
                    <select name="term" required>
                        <option value="1" <?php echo $payment['term'] == 1 ? 'selected' : ''; ?>>Term 1</option>
                        <option value="2" <?php echo $payment['term'] == 2 ? 'selected' : ''; ?>>Term 2</option>
                        <option value="3" <?php echo $payment['term'] == 3 ? 'selected' : ''; ?>>Term 3</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Amount (FRW)</label>
                    <input type="number" name="amount" value="<?php echo $payment['amount_paid']; ?>" required>
                </div>
                <button type="submit" class="btn btn-primary">Update Payment</button>
            </form>
            <a href="record_payment.php?id=<?php echo $student_id; ?>" style="display: block; text-align: center; margin-top: 1rem; color: var(--text-muted); text-decoration: none;">Cancel and Go Back</a>
        </div>
    </main>
</body>
</html>
