<?php
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

if (!isLoggedIn()) {
    redirect('../login.php');
}

$class_id = (int)$_GET['class_id'];
$stream = $_GET['stream'];
$year_id = (int)$_GET['year_id'];
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';

$yearData = getAcademicYearById($pdo, $year_id);
$stmt = $pdo->prepare("SELECT class_name, section FROM classes WHERE id = ?");
$stmt->execute([$class_id]);
$classData = $stmt->fetch();

if (!$classData) {
    redirect('manage_classes.php');
}

$className = $classData['class_name'];
$section = $classData['section'];

// Base query
$query = "SELECT s.*, e.id as enrollment_id, e.status as enrollment_status 
          FROM students s 
          JOIN enrollments e ON s.id = e.student_id 
          WHERE e.class_id = ? AND e.stream = ? AND e.academic_year_id = ?";
$params = [$class_id, $stream, $year_id];

if ($search) {
    $query .= " AND (s.full_name LIKE ? OR s.reg_number LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$query .= " ORDER BY s.full_name ASC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$rawStudents = $stmt->fetchAll();

// Process students for financial status and filtering
$students = [];
$stats = [
    'total' => 0,
    'cleared' => 0,
    'debtors' => 0,
    'total_required' => 0,
    'total_paid' => 0,
    'male' => 0,
    'female' => 0
];

foreach ($rawStudents as $s) {
    $fin = getDetailedYearlyStatus($pdo, $s['id'], $year_id);
    $s['financials'] = $fin;
    
    // Stats accumulation
    $stats['total']++;
    $stats['total_required'] += $fin['total_required'];
    $stats['total_paid'] += $fin['total_paid'];
    if ($s['gender'] == 'Male') $stats['male']++; else $stats['female']++;
    
    if ($fin['no_fees_set']) {
        // Optionally add a 'not_set' counter if you want to track them
    } elseif ($fin['balance'] >= 0) {
        $stats['cleared']++;
    } else {
        $stats['debtors']++;
    }

    // Apply Filter
    if ($fin['no_fees_set']) {
        if ($filter != 'all') continue;
    } else {
        if ($filter == 'cleared' && $fin['balance'] < 0) continue;
        if ($filter == 'debtors' && $fin['balance'] >= 0) continue;
    }
    
    $students[] = $s;
}

$percent = $stats['total_required'] > 0 ? round(($stats['total_paid'] / $stats['total_required']) * 100) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo "$className $stream"; ?> Workspace - Operational Hub</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        :root { --primary-gradient: linear-gradient(135deg, #4f46e5 0%, #06b6d4 100%); }
        .workspace-header { background: white; padding: 2rem; border-radius: 24px; box-shadow: var(--shadow-lg); margin-bottom: 2rem; border: 1px solid rgba(0,0,0,0.05); }
        .analytics-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; margin-top: 1.5rem; }
        .analytic-item { background: #f8fafc; padding: 1rem; border-radius: 16px; border: 1px solid #f1f5f9; }
        .analytic-item h5 { font-size: 0.7rem; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.5rem; letter-spacing: 0.5px; }
        .analytic-item .val { font-size: 1.25rem; font-weight: 800; color: #1e293b; }
        
        .tab-filters { display: flex; gap: 0.5rem; margin-bottom: 1.5rem; background: #f1f5f9; padding: 0.4rem; border-radius: 12px; width: fit-content; }
        .tab-link { padding: 0.5rem 1.25rem; border-radius: 8px; text-decoration: none; font-size: 0.85rem; font-weight: 700; color: #64748b; transition: all 0.2s; }
        .tab-link.active { background: white; color: var(--primary-color); box-shadow: var(--shadow-sm); }
        
        .student-row:hover { background-color: #f8fafc !important; }
        .action-icon-btn { width: 32px; height: 32px; display: inline-flex; align-items: center; justify-content: center; border-radius: 8px; transition: all 0.2s; text-decoration: none; font-size: 0.9rem; }
        .action-icon-btn:hover { transform: scale(1.1); }
        
        .search-wrapper { position: relative; flex: 1; }
        .search-wrapper input { padding-left: 2.5rem; border-radius: 12px; margin-bottom: 0; border: 1px solid #e2e8f0; }
        .search-wrapper i { position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: #94a3b8; }
    </style>
</head>
<body>
    <header>
        <div class="nav-container">
            <div class="logo">GSN <span>Fees Management</span></div>
            <nav class="nav-links">
                <a href="dashboard.php">Dashboard</a>
                <a href="manage_classes.php">Classes</a>
                <a href="requests.php">Requests</a>
                <a href="settings.php">Settings</a>
                <a href="logout.php" style="color: var(--danger);">Logout</a>
            </nav>
        </div>
    </header>

    <main class="container" style="max-width: 1400px;">
        <!-- Workspace Header -->
        <div class="workspace-header">
            <div style="display: flex; justify-content: space-between; align-items: start;">
                <div>
                    <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.5rem;">
                        <span style="background: var(--primary-color); color: white; padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.7rem; font-weight: 800;"><?php echo $yearData['year_name']; ?> Workspace</span>
                        <span style="color: var(--text-muted); font-size: 0.85rem;">Class Registry • <?php echo $section; ?></span>
                    </div>
                    <h1 style="font-size: 2.5rem; font-weight: 900; letter-spacing: -2px; color: #0f172a;"><?php echo "$className $stream"; ?> <span style="font-weight: 400; color: #94a3b8;">Operational Hub</span></h1>
                </div>
                <div style="display: flex; gap: 0.5rem;">
                    <button onclick="window.print()" class="btn btn-secondary" style="width: auto; background: white; border: 1px solid #e2e8f0; color: #0f172a">Print View</button>
                    <a href="add_student.php?mode=bulk&class_id=<?php echo $class_id; ?>&stream=<?php echo urlencode($stream); ?>&year_id=<?php echo $year_id; ?>&section=<?php echo $section; ?>" class="btn btn-secondary" style="width: auto; background: #334155; color: white;">Bulk Enrollment</a>
                    <a href="class_promotion.php?class_id=<?php echo $class_id; ?>&stream=<?php echo urlencode($stream); ?>&year_id=<?php echo $year_id; ?>" class="btn btn-primary" style="width: auto;">Promote All</a>
                </div>
            </div>

            <div class="analytics-grid">
                <div class="analytic-item" style="border-left: 4px solid var(--primary-color);">
                    <h5>Total Enrolled</h5>
                    <div class="val"><?php echo $stats['total']; ?> <small style="font-size: 0.7rem; color: #94a3b8;">(<?php echo $stats['male']; ?>M / <?php echo $stats['female']; ?>F)</small></div>
                </div>
                <div class="analytic-item" style="border-left: 4px solid var(--success);">
                    <h5>Cleared Students</h5>
                    <div class="val" style="color: var(--success);"><?php echo $stats['cleared']; ?></div>
                </div>
                <div class="analytic-item" style="border-left: 4px solid var(--danger);">
                    <h5>Debtors</h5>
                    <div class="val" style="color: var(--danger);"><?php echo $stats['debtors']; ?></div>
                </div>
                <div class="analytic-item">
                    <h5>Collection Rate</h5>
                    <div class="val"><?php echo $percent; ?>%</div>
                    <div style="height: 4px; background: #e2e8f0; border-radius: 2px; margin-top: 0.4rem; overflow: hidden;">
                        <div style="width: <?php echo $percent; ?>%; height: 100%; background: var(--success);"></div>
                    </div>
                </div>
                <div class="analytic-item">
                    <h5>Expected Revenue</h5>
                    <div class="val" style="font-size: 1.1rem;"><?php echo number_format($stats['total_required']); ?> <small>FRW</small></div>
                </div>
            </div>
        </div>

        <!-- Operations Bar -->
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; gap: 2rem;">
            <div class="tab-filters">
                <a href="?class_id=<?php echo $class_id; ?>&stream=<?php echo urlencode($stream); ?>&year_id=<?php echo $year_id; ?>&filter=all" class="tab-link <?php echo $filter == 'all' ? 'active' : ''; ?>">All Students</a>
                <a href="?class_id=<?php echo $class_id; ?>&stream=<?php echo urlencode($stream); ?>&year_id=<?php echo $year_id; ?>&filter=cleared" class="tab-link <?php echo $filter == 'cleared' ? 'active' : ''; ?>">Cleared</a>
                <a href="?class_id=<?php echo $class_id; ?>&stream=<?php echo urlencode($stream); ?>&year_id=<?php echo $year_id; ?>&filter=debtors" class="tab-link <?php echo $filter == 'debtors' ? 'active' : ''; ?>">Debtors</a>
            </div>

            <form method="GET" style="flex: 1; display: flex; gap: 0.75rem;">
                <input type="hidden" name="class_id" value="<?php echo $class_id; ?>">
                <input type="hidden" name="stream" value="<?php echo htmlspecialchars($stream); ?>">
                <input type="hidden" name="year_id" value="<?php echo $year_id; ?>">
                <input type="hidden" name="filter" value="<?php echo $filter; ?>">
                <div class="search-wrapper">
                    <input type="text" name="search" placeholder="Quick Search by Name or Reg Number..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <button type="submit" class="btn btn-secondary" style="width: auto; margin-bottom: 0;">Filter</button>
            </form>
        </div>

        <!-- Student Workspace Table -->
        <div class="table-container" style="border-radius: 20px; box-shadow: var(--shadow-md);">
            <table style="border-collapse: separate; border-spacing: 0;">
                <thead>
                    <tr style="background: #f8fafc;">
                        <th style="padding: 1.25rem 1rem;">ID / Gender</th>
                        <th>Student Full Name</th>
                        <th>Status</th>
                        <th>Required</th>
                        <th>Collected</th>
                        <th>Balance</th>
                        <th style="text-align: right; padding-right: 1.5rem;">Operational Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($students as $s): 
                        $f = $s['financials'];
                    ?>
                        <tr class="student-row" style="border-bottom: 1px solid #f1f5f9;">
                            <td style="padding: 1.25rem 1rem;">
                                <div style="font-family: monospace; font-weight: 700; color: #64748b; font-size: 0.75rem;"><?php echo $s['reg_number']; ?></div>
                                <div style="font-size: 0.65rem; text-transform: uppercase; font-weight: 800; color: <?php echo $s['gender'] == 'Male' ? '#3b82f6' : '#ec4899'; ?>;"><?php echo $s['gender']; ?></div>
                            </td>
                            <td>
                                <div style="font-weight: 700; color: #1e293b; font-size: 1rem;"><?php echo htmlspecialchars($s['full_name']); ?></div>
                                <div style="font-size: 0.7rem; color: #94a3b8;"><?php echo $s['enrollment_status']; ?> Enrollment</div>
                            </td>
                            <td>
                                <?php if ($f['no_fees_set']): ?>
                                    <span style="background: #f1f5f9; color: #64748b; padding: 0.35rem 0.75rem; border-radius: 8px; font-size: 0.7rem; font-weight: 800; text-transform: uppercase;">Fees Not Set</span>
                                <?php elseif ($f['balance'] >= 0): ?>
                                    <span style="background: #dcfce7; color: #166534; padding: 0.35rem 0.75rem; border-radius: 8px; font-size: 0.7rem; font-weight: 800; text-transform: uppercase;">Cleared</span>
                                <?php elseif ($f['total_paid'] > 0): ?>
                                    <span style="background: #fef9c3; color: #854d0e; padding: 0.35rem 0.75rem; border-radius: 8px; font-size: 0.7rem; font-weight: 800; text-transform: uppercase;">Partial</span>
                                <?php else: ?>
                                    <span style="background: #fee2e2; color: #991b1b; padding: 0.35rem 0.75rem; border-radius: 8px; font-size: 0.7rem; font-weight: 800; text-transform: uppercase;">Debtor</span>
                                <?php endif; ?>
                            </td>
                            <td style="font-weight: 600; color: #475569;"><?php echo number_format($f['total_required']); ?></td>
                            <td style="font-weight: 700; color: #16a34a;"><?php echo number_format($f['total_paid']); ?></td>
                            <td style="font-weight: 800; color: <?php echo $f['balance'] >= 0 ? '#0ea5e9' : '#ef4444'; ?>;">
                                <?php echo number_format($f['balance']); ?>
                            </td>
                            <td style="text-align: right; padding-right: 1.5rem;">
                                <div style="display: flex; gap: 0.4rem; justify-content: flex-end;">
                                    <a href="record_payment.php?id=<?php echo $s['id']; ?>&year_id=<?php echo $year_id; ?>" class="btn btn-primary" style="padding: 0.4rem 0.8rem; font-size: 0.75rem; width: auto; background: var(--primary-color);">Pay</a>
                                    <a href="view_student.php?id=<?php echo $s['id']; ?>&year_id=<?php echo $year_id; ?>" class="btn btn-secondary" style="padding: 0.4rem 0.8rem; font-size: 0.75rem; width: auto; background: #f1f5f9; color: #334155; border: 1px solid #e2e8f0;">Profile</a>
                                    <a href="print_student.php?id=<?php echo $s['id']; ?>&year_id=<?php echo $year_id; ?>" class="btn btn-secondary" style="padding: 0.4rem 0.8rem; font-size: 0.75rem; width: auto; background: #f8fafc; color: #64748b;">Slip</a>
                                    
                                    <div class="dropdown-wrapper" style="position: relative; display: inline-block;">
                                        <button class="btn btn-secondary" style="padding: 0.4rem 0.6rem; font-size: 0.75rem; width: auto;" onclick="this.nextElementSibling.classList.toggle('show')">•••</button>
                                        <div class="dropdown-content" style="display: none; position: absolute; right: 0; background: white; min-width: 150px; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); border-radius: 8px; z-index: 50; border: 1px solid #f1f5f9;">
                                            <a href="edit_student.php?id=<?php echo $s['id']; ?>" style="display: block; padding: 0.75rem; font-size: 0.8rem; text-decoration: none; color: #334155;">Edit Identity</a>
                                            <a href="transfer_student.php?id=<?php echo $s['id']; ?>&year_id=<?php echo $year_id; ?>" style="display: block; padding: 0.75rem; font-size: 0.8rem; text-decoration: none; color: #334155;">Transfer Class</a>
                                            <a href="remove_enrollment.php?id=<?php echo $s['enrollment_id']; ?>&return=view_class.php?class_id=<?php echo $class_id; ?>&stream=<?php echo $stream; ?>&year_id=<?php echo $year_id; ?>" style="display: block; padding: 0.75rem; font-size: 0.8rem; text-decoration: none; color: #ef4444;" onclick="return confirm('Remove student from this class? This will not delete their identity.')">Remove</a>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>

    <script>
        // Close dropdowns when clicking outside
        window.onclick = function(event) {
            if (!event.target.matches('.btn-secondary')) {
                var dropdowns = document.getElementsByClassName("dropdown-content");
                for (var i = 0; i < dropdowns.length; i++) {
                    dropdowns[i].classList.remove('show');
                }
            }
        }
        
        // Inline CSS toggle for dropdown
        const style = document.createElement('style');
        style.innerHTML = '.show { display: block !important; }';
        document.head.appendChild(style);
    </script>
</body>
</html>
