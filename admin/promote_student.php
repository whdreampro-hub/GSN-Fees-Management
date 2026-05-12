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
    $new_class = trim($_POST['current_class']);
    $new_stream = trim($_POST['current_stream']);
    $new_section = $_POST['section'];
    
    if ($new_class && $new_stream && $new_section) {
        try {
            $pdo->beginTransaction();
            
            // 1. Log to history before updating
            $hist = $pdo->prepare("INSERT INTO academic_history (student_id, old_section, old_class, old_stream, new_section, new_class, new_stream) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $hist->execute([$id, $student['section'], $student['current_class'], $student['current_stream'], $new_section, $new_class, $new_stream]);
            
            // 2. Update current status
            $stmt = $pdo->prepare("UPDATE students SET current_class = ?, current_stream = ?, section = ? WHERE id = ?");
            $stmt->execute([$new_class, $new_stream, $new_section, $id]);
            
            $pdo->commit();
            $message = "Student promoted successfully! Academic history updated.";
            
            // Refresh data
            $student['current_class'] = $new_class;
            $student['current_stream'] = $new_stream;
            $student['section'] = $new_section;
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = "Error: " . $e->getMessage();
        }
    } else {
        $error = "All fields are required.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Promote Student - GSN Fees Management</title>
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
            <h2 class="mb-4">Promote / Move Student</h2>
            <p class="mb-4">Student: <strong><?php echo htmlspecialchars($student['full_name']); ?></strong><br>
            Current Status: <?php echo $student['section']; ?> - <?php echo $student['current_class']; ?> <?php echo $student['current_stream']; ?></p>
            
            <?php if ($error): ?><div style="color: var(--danger); margin-bottom: 1rem;"><?php echo $error; ?></div><?php endif; ?>
            <?php if ($message): ?><div style="color: var(--success); margin-bottom: 1rem;"><?php echo $message; ?></div><?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label>New Section</label>
                    <select name="section" required>
                        <option value="Primary" <?php echo $student['section'] == 'Primary' ? 'selected' : ''; ?>>Primary</option>
                        <option value="Secondary" <?php echo $student['section'] == 'Secondary' ? 'selected' : ''; ?>>Secondary</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>New Class (e.g. S1, P6)</label>
                    <input type="text" name="current_class" value="<?php echo htmlspecialchars($student['current_class']); ?>" required>
                </div>
                <div class="form-group">
                    <label>New Stream (A, B, C...)</label>
                    <input type="text" name="current_stream" value="<?php echo htmlspecialchars($student['current_stream']); ?>" required>
                </div>
                <button type="submit" class="btn btn-primary mt-4">Confirm Promotion</button>
            </form>
            <a href="dashboard.php" style="display: block; text-align: center; margin-top: 1rem; color: var(--text-muted); text-decoration: none;">Back to Dashboard</a>
        </div>
    </main>
</body>
</html>
