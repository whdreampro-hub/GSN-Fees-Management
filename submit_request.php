<?php
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

$reg = isset($_GET['reg']) ? $_GET['reg'] : '';
$year_id = isset($_GET['year']) ? (int)$_GET['year'] : 0;

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
    $req_type = $_POST['type'];
    $req_message = trim($_POST['message']);
    
    if ($student_id && $year_id && $req_type && $req_message) {
        try {
            $stmt = $pdo->prepare("INSERT INTO student_requests (student_id, academic_year_id, request_type, message) VALUES (?, ?, ?, ?)");
            $stmt->execute([$student_id, $year_id, $req_type, $req_message]);
            $message = "Your request has been submitted to administration. Thank you.";
        } catch (PDOException $e) { $error = "Submission failed: " . $e->getMessage(); }
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
            <p class="mb-4" style="color: var(--text-muted);">Submitting request for: <strong><?php echo htmlspecialchars($student_name); ?></strong> (<?php echo $reg; ?>)</p>

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
