<?php
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

$reg = isset($_GET['reg']) && is_string($_GET['reg']) ? trim($_GET['reg']) : '';
$reg_display = htmlspecialchars($reg, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$year_id = isset($_GET['year']) ? filter_var($_GET['year'], FILTER_VALIDATE_INT) : 0;
$year_id = $year_id !== false ? (int) $year_id : 0;

$student_id = 0;
$student_name = '';

if ($reg) {
    $stmt = $pdo->prepare("SELECT id, full_name FROM students WHERE reg_number = ?");
    $stmt->execute([$reg]);
    $s = $stmt->fetch();
    if ($s) {
        $student_id = $s['id'];
        $student_name = $s['full_name'];
    }
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $req_type = isset($_POST['type']) && is_string($_POST['type']) ? trim($_POST['type']) : '';
    $req_message = isset($_POST['message']) && is_string($_POST['message']) ? trim($_POST['message']) : '';
    $allowedTypes = ['Financial Difficulty', 'Payment Dispute', 'Clearance Inquiry', 'Special Explanation'];
    
    if ($student_id && $year_id > 0 && in_array($req_type, $allowedTypes, true) && $req_message !== '' && strlen($req_message) <= 2000) {
        try {
            $stmt = $pdo->prepare("INSERT INTO student_requests (student_id, academic_year_id, request_type, message) VALUES (?, ?, ?, ?)");
            $stmt->execute([$student_id, $year_id, $req_type, $req_message]);
            $message = "Your request has been submitted to administration. Thank you.";
        } catch (PDOException $e) {
            error_log('Student request submission failed: ' . $e->getMessage());
            $error = 'Submission failed. Please try again later.';
        }
    } else {
        $error = "Please fill in all fields.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submit Request - GSN Fees Management</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <header>
        <div class="nav-container">
            <div class="logo">GSN <span>Student Requests</span></div>
            <nav class="nav-links">
                <a href="portal.php">Back to Portal</a>
            </nav>
        </div>
    </header>

    <main class="container">
        <div class="card" style="margin: 0 auto; max-width: 600px;">
            <h2 class="mb-2">Administrative Request</h2>
            <p class="mb-4" style="color: var(--text-muted);">Submitting request for: <strong><?php echo htmlspecialchars($student_name); ?></strong> (<?php echo $reg_display; ?>)</p>

            <?php if ($message): ?>
                <div style="background: #dcfce7; color: #166534; padding: 1.5rem; border-radius: 12px; text-align: center;">
                    <p style="font-weight: 700; font-size: 1.1rem;"><?php echo $message; ?></p>
                    <a href="portal.php" class="btn btn-primary mt-4" style="width: auto;">Return to Portal</a>
                </div>
            <?php else: ?>
                <?php if ($error): ?><div style="color: var(--danger); margin-bottom: 1rem;"><?php echo $error; ?></div><?php endif; ?>
                
                <form method="POST">
                    <div class="form-group">
                        <label>Request Type</label>
                        <select name="type" required>
                            <option value="Financial Difficulty">Financial Difficulty / Extension</option>
                            <option value="Payment Dispute">Payment Dispute / Missing Record</option>
                            <option value="Clearance Inquiry">Clearance Inquiry</option>
                            <option value="Special Explanation">Special Explanation</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Message / Explanation</label>
                        <textarea name="message" required style="width: 100%; height: 150px; padding: 1rem; border-radius: 12px; border: 1px solid var(--border-color); font-family: inherit; font-size: 1rem;"></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary mt-4">Send Request to Administration</button>
                </form>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>
