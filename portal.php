<?php
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

$results = null;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['lookup'])) {
    $reg_number = trim($_POST['reg_number']);
    $year_id = (int)$_POST['year_id'];
    
    if ($reg_number && $year_id) {
        $stmt = $pdo->prepare("SELECT id, full_name FROM students WHERE reg_number = ?");
        $stmt->execute([$reg_number]);
        $student = $stmt->fetch();
        
        if ($student) {
            $results = getDetailedYearlyStatus($pdo, $student['id'], $year_id);
            $results['student_name'] = $student['full_name'];
            $results['reg_number'] = $reg_number;
            $results['year_id'] = $year_id;
            $results['student_id'] = $student['id'];
            
            // Get current year name
            $stmt = $pdo->prepare("SELECT year_name FROM academic_years WHERE id = ?");
            $stmt->execute([$year_id]);
            $results['year_name'] = $stmt->fetchColumn();
        } else {
            $error = "No student found with that Registration Number.";
        }
    } else {
        $error = "Please provide both Registration Number and Academic Year.";
    }
}

$academicYears = getAllAcademicYears($pdo);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Portal - GSN Fees Management</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .portal-container {
            max-width: 800px;
            margin: 3rem auto;
            padding: 2rem;
        }
        .status-hero {
            background: linear-gradient(135deg, #4f46e5 0%, #06b6d4 100%);
            color: white;
            padding: 3rem;
            border-radius: 24px;
            text-align: center;
            margin-bottom: 2rem;
            box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1);
        }
        .financial-card {
            background: white;
            padding: 2rem;
            border-radius: 20px;
            box-shadow: var(--shadow-lg);
            border: 1px solid var(--border-color);
        }
        .req-btn {
            display: inline-block;
            margin-top: 1.5rem;
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 600;
            border: 2px solid var(--primary-color);
            padding: 0.6rem 1.2rem;
            border-radius: 12px;
            transition: all 0.3s ease;
        }
        .req-btn:hover {
            background: var(--primary-color);
            color: white;
        }
    </style>
</head>
<body>
    <header>
        <div class="nav-container">
            <div class="logo">GSN <span>Student Portal</span></div>
            <nav class="nav-links">
                <a href="index.php">Return Home</a>
            </nav>
        </div>
    </header>

    <main class="portal-container">
        <?php if (!$results): ?>
            <div class="card" style="margin: 0 auto; max-width: 500px;">
                <h2 class="mb-4">Check Your Fee Status</h2>
                <p class="mb-4" style="color: var(--text-muted);">Enter your permanent registration number and select the academic year to view your records.</p>
                
                <?php if ($error): ?><div style="color: var(--danger); margin-bottom: 1.5rem; font-weight: 600;"><?php echo $error; ?></div><?php endif; ?>

                <form method="POST">
                    <div class="form-group">
                        <label>Registration Number</label>
                        <input type="text" name="reg_number" placeholder="GSN-XXXX-XXXXXXXX" required>
                    </div>
                    <div class="form-group">
                        <label>Academic Year</label>
                        <select name="year_id" required>
                            <?php foreach ($academicYears as $ay): ?>
                                <option value="<?php echo $ay['id']; ?>" <?php echo $ay['is_current'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($ay['year_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" name="lookup" class="btn btn-primary mt-4">Lookup My Record</button>
                </form>
            </div>
        <?php else: ?>
            <div class="status-hero">
                <h1 style="font-size: 2.5rem; margin-bottom: 0.5rem;">Hello, <?php echo explode(' ', $results['student_name'])[0]; ?>!</h1>
                <p style="opacity: 0.9; font-size: 1.1rem;">Academic Year: <?php echo htmlspecialchars($results['year_name']); ?></p>
                <div style="margin-top: 2rem; font-size: 1.25rem;">
                    Your Status: 
                    <span style="background: rgba(255,255,255,0.2); padding: 0.4rem 1rem; border-radius: 99px; font-weight: 700;">
                        <?php echo $results['balance'] >= 0 ? 'CLEARED' : 'PENDING BALANCE'; ?>
                    </span>
                </div>
            </div>

            <div class="financial-card">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; border-bottom: 1px solid var(--border-color); padding-bottom: 1rem;">
                    <h3>Financial Statement</h3>
                    <span style="font-weight: 600; color: var(--text-muted);"><?php echo $results['reg_number']; ?></span>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
                    <div>
                        <p style="color: var(--text-muted); font-size: 0.9rem; text-transform: uppercase;">Total Required</p>
                        <p style="font-size: 1.5rem; font-weight: 700;"><?php echo number_format($results['total_required']); ?> FRW</p>
                    </div>
                    <div>
                        <p style="color: var(--text-muted); font-size: 0.9rem; text-transform: uppercase;">Total Collected</p>
                        <p style="font-size: 1.5rem; font-weight: 700; color: var(--success);"><?php echo number_format($results['total_paid']); ?> FRW</p>
                    </div>
                </div>

                <div style="margin-top: 2rem; padding-top: 2rem; border-top: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <p style="color: var(--text-muted); font-size: 0.9rem; text-transform: uppercase;">Outstanding Balance</p>
                        <p style="font-size: 2rem; font-weight: 800; color: <?php echo $results['balance'] >= 0 ? 'var(--success)' : 'var(--danger)'; ?>;">
                            <?php echo number_format(abs($results['balance'])); ?> FRW
                            <?php if ($results['balance'] > 0): ?> (Overpaid)<?php endif; ?>
                        </p>
                    </div>
                    <div style="text-align: right;">
                        <a href="submit_request.php?reg=<?php echo $results['reg_number']; ?>&year=<?php echo $results['year_id']; ?>" class="req-btn">Submit Assistance Request</a>
                        <p style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.5rem;">Request clearance extension or dispute</p>
                    </div>
                </div>
            </div>

            <?php
            // Fetch student requests
            $stmt = $pdo->prepare("SELECT * FROM student_requests WHERE student_id = ? AND academic_year_id = ? ORDER BY created_at DESC");
            $stmt->execute([$results['student_id'], $results['year_id']]);
            $student_requests = $stmt->fetchAll();
            ?>
            
            <?php if (!empty($student_requests)): ?>
            <div style="margin-top: 2rem;">
                <h3 class="mb-4">Your Request History</h3>
                <?php foreach ($student_requests as $req): ?>
                    <div class="financial-card" style="margin-bottom: 1rem; padding: 1.5rem;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                            <span style="background: #eff6ff; color: #1e40af; padding: 0.2rem 0.6rem; border-radius: 4px; font-weight: 700; font-size: 0.75rem;"><?php echo htmlspecialchars($req['request_type']); ?></span>
                            <span style="font-size: 0.8rem; color: var(--text-muted);"><?php echo date('M d, Y H:i', strtotime($req['created_at'])); ?></span>
                        </div>
                        <p style="color: var(--text-main); margin-bottom: 1rem;"><?php echo nl2br(htmlspecialchars($req['message'])); ?></p>
                        
                        <?php if ($req['admin_reply']): ?>
                            <div style="background: #f0fdf4; border-left: 4px solid var(--success); padding: 1rem; border-radius: 8px;">
                                <span style="font-weight: 700; font-size: 0.8rem; color: #166534; text-transform: uppercase;">Admin Reply (<?php echo $req['status']; ?>):</span>
                                <p style="margin-top: 0.5rem; color: #166534; font-size: 0.95rem;">
                                    <?php echo nl2br(htmlspecialchars($req['admin_reply'])); ?>
                                </p>
                            </div>
                        <?php else: ?>
                            <div style="font-size: 0.85rem; color: #64748b; font-style: italic;">
                                Status: <?php echo $req['status']; ?>. Awaiting admin reply...
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <a href="portal.php" style="display: block; text-align: center; margin-top: 2rem; color: var(--text-muted); text-decoration: none;">&larr; Check another record</a>
        <?php endif; ?>
    </main>
</body>
</html>
