<?php
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

if (!isLoggedIn()) {
    redirect('../login.php');
}

$id = (int)$_GET['id'];
$stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
$stmt->execute([$id]);
$student = $stmt->fetch();

if (!$student) {
    redirect('dashboard.php');
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name']);
    $gender = $_POST['gender'];
    $reg_number = trim($_POST['reg_number']);

    if ($full_name && $gender && $reg_number) {
        try {
            $stmt = $pdo->prepare("UPDATE students SET full_name = ?, gender = ?, reg_number = ? WHERE id = ?");
            $stmt->execute([$full_name, $gender, $reg_number, $id]);
            $message = "Student identity updated successfully!";
            $student['full_name'] = $full_name;
            $student['gender'] = $gender;
            $student['reg_number'] = $reg_number;
        } catch (PDOException $e) { $error = "Error: " . $e->getMessage(); }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Student - <?php echo htmlspecialchars($student['full_name']); ?></title>
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
            <h2 class="mb-4">Edit Student Identity</h2>
            
            <?php if ($message): ?><div style="background: #dcfce7; color: #166534; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem;"><?php echo $message; ?></div><?php endif; ?>
            <?php if ($error): ?><div style="background: #fee2e2; color: #991b1b; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem;"><?php echo $error; ?></div><?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label>Registration Number</label>
                    <input type="text" name="reg_number" value="<?php echo htmlspecialchars($student['reg_number']); ?>" required>
                </div>
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="full_name" value="<?php echo htmlspecialchars($student['full_name']); ?>" required>
                </div>
                <div class="form-group">
                    <label>Gender</label>
                    <select name="gender" required>
                        <option value="Male" <?php echo $student['gender'] == 'Male' ? 'selected' : ''; ?>>Male</option>
                        <option value="Female" <?php echo $student['gender'] == 'Female' ? 'selected' : ''; ?>>Female</option>
                    </select>
                </div>
                <div style="display: flex; gap: 1rem; margin-top: 2rem;">
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                    <a href="view_student.php?id=<?php echo $id; ?>" class="btn btn-secondary" style="text-decoration: none; text-align: center;">Cancel</a>
                </div>
            </form>
        </div>
    </main>
</body>
</html>
