<?php
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

if (!isLoggedIn()) {
    redirect('../login.php');
}

$section = $_GET['section'];
$class_name = $_GET['class'];
$stream = $_GET['stream'];
$year = getCurrentYear($pdo);

$stmt = $pdo->prepare("SELECT * FROM students WHERE section = ? AND current_class = ? AND current_stream = ? ORDER BY full_name ASC");
$stmt->execute([$section, $class_name, $stream]);
$students = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Class Report - <?php echo "$class_name $stream"; ?></title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        body { background: white; padding: 0; }
        .print-header { text-align: center; margin-bottom: 2rem; border-bottom: 2px solid #000; padding-bottom: 1rem; }
        .print-table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        .print-table th, .print-table td { border: 1px solid #000; padding: 8px; text-align: left; font-size: 0.9rem; }
        .no-print { margin: 2rem; text-align: center; }
        @media print {
            .no-print { display: none; }
            .container { max-width: 100%; padding: 0; }
        }
    </style>
</head>
<body>
    <div class="no-print">
        <button onclick="window.print()" class="btn btn-primary" style="width: auto;">Confirm and Print List</button>
        <a href="manage_classes.php" class="btn btn-secondary" style="width: auto; text-decoration: none;">Back to Dashboard</a>
    </div>

    <div class="container">
        <div class="print-header">
            <h1>GS NYAGISOZI FEES MANAGEMENT</h1>
            <h2>OFFICIAL CLASS LIST & FEE STATUS</h2>
            <p><strong>Class:</strong> <?php echo "$class_name $stream"; ?> | <strong>Section:</strong> <?php echo $section; ?> | <strong>Year:</strong> <?php echo $year; ?></p>
        </div>

        <table class="print-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Reg Number</th>
                    <th>Full Name</th>
                    <th>Term 1</th>
                    <th>Term 2</th>
                    <th>Term 3</th>
                    <th>Total Paid</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($students as $index => $student): 
                    $status = getDetailedYearlyStatus($pdo, $student['id'], $year);
                ?>
                    <tr>
                        <td><?php echo $index + 1; ?></td>
                        <td><?php echo $student['reg_number']; ?></td>
                        <td><?php echo htmlspecialchars($student['full_name']); ?></td>
                        <td><?php echo getStudentPaymentStatus($pdo, $student['id'], $year, 1); ?></td>
                        <td><?php echo getStudentPaymentStatus($pdo, $student['id'], $year, 2); ?></td>
                        <td><?php echo getStudentPaymentStatus($pdo, $student['id'], $year, 3); ?></td>
                        <td><?php echo number_format($status['total_paid']); ?> FRW</td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div style="margin-top: 3rem; display: flex; justify-content: space-between;">
            <div>
                <p>__________________________</p>
                <p>Class Teacher Signature</p>
            </div>
            <div>
                <p>__________________________</p>
                <p>School Administrator</p>
            </div>
        </div>
        <p style="text-align: center; margin-top: 2rem; font-size: 0.8rem; color: #666;">Generated on: <?php echo date('Y-m-d H:i:s'); ?></p>
    </div>
</body>
</html>
