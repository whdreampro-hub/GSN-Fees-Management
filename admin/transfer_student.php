<?php
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

if (!isLoggedIn()) {
    redirect('../login.php');
}

$student_id = (int)$_GET['id'];
$year_id = (int)$_GET['year_id'];

$stmt = $pdo->prepare("SELECT s.*, e.class_id, e.stream, e.id as enrollment_id FROM students s JOIN enrollments e ON s.id = e.student_id WHERE s.id = ? AND e.academic_year_id = ?");
$stmt->execute([$student_id, $year_id]);
$data = $stmt->fetch();

if (!$data) {
    redirect('manage_classes.php');
}

$classes = getClasses($pdo);
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $target_class_id = (int)$_POST['class_id'];
    $target_stream = trim($_POST['stream']);
    
    if ($target_class_id) {
        try {
            $stmt = $pdo->prepare("UPDATE enrollments SET class_id = ?, stream = ? WHERE id = ?");
            $stmt->execute([$target_class_id, $target_stream, $data['enrollment_id']]);
            $message = "Student transferred successfully!";
            $data['class_id'] = $target_class_id;
            $data['stream'] = $target_stream;
        } catch (PDOException $e) { $error = "Error: " . $e->getMessage(); }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transfer Student - <?php echo htmlspecialchars($data['full_name']); ?></title>
    <link rel="stylesheet" href="../css/style.css">
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
        </div>
    </header>

    <main class="container">
        <div class="card" style="max-width: 600px; margin: 0 auto;">
            <h2 class="mb-2">Transfer Student</h2>
            <p class="mb-4" style="color: var(--text-muted); font-size: 0.9rem;">Move <strong><?php echo htmlspecialchars($data['full_name']); ?></strong> to a different class within the <strong><?php echo getAcademicYearById($pdo, $year_id)['year_name']; ?></strong> workspace.</p>
            
            <?php if ($message): ?><div style="background: #dcfce7; color: #166534; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem;"><?php echo $message; ?></div><?php endif; ?>
            <?php if ($error): ?><div style="background: #fee2e2; color: #991b1b; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem;"><?php echo $error; ?></div><?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label>Target Class</label>
                    <select name="class_id" required>
                        <?php foreach ($classes as $c): ?>
                            <option value="<?php echo $c['id']; ?>" <?php echo $c['id'] == $data['class_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($c['class_name']); ?> (<?php echo $c['section']; ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Target Stream</label>
                    <input type="text" name="stream" value="<?php echo htmlspecialchars($data['stream']); ?>" required>
                </div>
                <div style="display: flex; gap: 1rem; margin-top: 2rem;">
                    <button type="submit" class="btn btn-primary">Complete Transfer</button>
                    <a href="view_class.php?class_id=<?php echo $data['class_id']; ?>&stream=<?php echo urlencode($data['stream']); ?>&year_id=<?php echo $year_id; ?>" class="btn btn-secondary" style="text-decoration: none; text-align: center;">Done</a>
                </div>
            </form>
        </div>
    </main>
</body>
</html>
