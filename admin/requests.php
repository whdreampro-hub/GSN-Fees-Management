<?php
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

if (!isLoggedIn()) {
    redirect('../login.php');
}

// Mark as reviewed if requested
if (isset($_GET['review_id'])) {
    $stmt = $pdo->prepare("UPDATE student_requests SET status = 'Reviewed' WHERE id = ?");
    $stmt->execute([(int)$_GET['review_id']]);
}

// Handle reply submission and editing/deleting
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['submit_reply'])) {
        $req_id = (int)$_POST['request_id'];
        $reply = trim($_POST['admin_reply']);
        if ($reply) {
            $stmt = $pdo->prepare("UPDATE student_requests SET status = 'Resolved', admin_reply = ? WHERE id = ?");
            $stmt->execute([$reply, $req_id]);
        }
    } elseif (isset($_POST['edit_reply'])) {
        $req_id = (int)$_POST['request_id'];
        $reply = trim($_POST['admin_reply']);
        if ($reply) {
            $stmt = $pdo->prepare("UPDATE student_requests SET admin_reply = ? WHERE id = ?");
            $stmt->execute([$reply, $req_id]);
        }
    } elseif (isset($_POST['delete_reply'])) {
        $req_id = (int)$_POST['request_id'];
        $stmt = $pdo->prepare("UPDATE student_requests SET status = 'Reviewed', admin_reply = NULL WHERE id = ?");
        $stmt->execute([$req_id]);
    }
}

$stmt = $pdo->query("SELECT r.*, s.full_name, s.reg_number, ay.year_name 
                    FROM student_requests r 
                    JOIN students s ON r.student_id = s.id 
                    JOIN academic_years ay ON r.academic_year_id = ay.id 
                    ORDER BY r.created_at DESC");
$requests = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Incoming Student Requests - Admin</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .request-card {
            background: white;
            padding: 1.5rem;
            border-radius: 16px;
            box-shadow: var(--shadow-md);
            border-left: 6px solid var(--accent-color);
            margin-bottom: 1.5rem;
            position: relative;
        }
        .request-card.reviewed {
            border-left-color: var(--success);
            opacity: 0.8;
        }
        .req-meta { display: flex; gap: 1rem; font-size: 0.8rem; color: var(--text-muted); margin-bottom: 1rem; }
        .req-type { background: #eff6ff; color: #1e40af; padding: 0.2rem 0.6rem; border-radius: 4px; font-weight: 700; font-size: 0.75rem; }
        .req-status { position: absolute; top: 1.5rem; right: 1.5rem; }
    </style>
</head>
<body>
    <header>
        <div class="nav-container">
            <div class="logo">GSN <span>Admin Panel</span></div>
            <nav class="nav-links">
                <a href="dashboard.php">Dashboard</a>
                <a href="requests.php">Requests</a>
                <a href="logout.php" style="color: var(--danger);">Logout</a>
            </nav>
        </div>
    </header>

    <main class="container">
        <h2 class="mb-4">Student-to-Administration Requests</h2>
        
        <?php if (empty($requests)): ?>
            <div class="card" style="text-align: center; color: var(--text-muted);">
                <p>No incoming requests at the moment.</p>
            </div>
        <?php endif; ?>

        <?php foreach ($requests as $r): ?>
            <div class="request-card <?php echo $r['status'] == 'Reviewed' ? 'reviewed' : ''; ?>">
                <div class="req-status">
                    <?php if ($r['status'] == 'Pending'): ?>
                        <a href="?review_id=<?php echo $r['id']; ?>" class="btn btn-sm" style="background: var(--success); color: white;">Mark as Reviewed</a>
                    <?php else: ?>
                        <span class="status-badge status-paid"><?php echo $r['status']; ?></span>
                    <?php endif; ?>
                </div>
                
                <h3 style="margin-bottom: 0.5rem;"><?php echo htmlspecialchars($r['full_name']); ?></h3>
                <div class="req-meta">
                    <span>Reg: <strong><?php echo $r['reg_number']; ?></strong></span>
                    <span>Year: <strong><?php echo $r['year_name']; ?></strong></span>
                    <span>Received: <?php echo date('M d, Y H:i', strtotime($r['created_at'])); ?></span>
                </div>
                
                <div style="margin-top: 1rem;">
                    <span class="req-type"><?php echo htmlspecialchars($r['request_type']); ?></span>
                    <p style="margin-top: 1rem; line-height: 1.8; color: var(--text-main); background: #f8fafc; padding: 1rem; border-radius: 8px;">
                        "<?php echo nl2br(htmlspecialchars($r['message'])); ?>"
                    </p>
                </div>
                
                <?php if ($r['admin_reply']): ?>
                    <div id="reply_display_<?php echo $r['id']; ?>" style="margin-top: 1rem; background: #f0fdf4; border-left: 4px solid var(--success); padding: 1rem; border-radius: 8px;">
                        <div style="display: flex; justify-content: space-between; align-items: start;">
                            <span style="font-weight: 700; font-size: 0.8rem; color: #166534; text-transform: uppercase;">Admin Reply:</span>
                            <div style="display: flex; gap: 0.5rem;">
                                <button type="button" onclick="document.getElementById('reply_edit_<?php echo $r['id']; ?>').style.display='flex'; document.getElementById('reply_display_<?php echo $r['id']; ?>').style.display='none';" class="btn" style="padding: 0.2rem 0.6rem; font-size: 0.75rem; background: #bbf7d0; color: #166534; border: 1px solid #86efac;">Edit</button>
                                <form method="POST" onsubmit="return confirm('Delete this reply?');" style="margin: 0;">
                                    <input type="hidden" name="request_id" value="<?php echo $r['id']; ?>">
                                    <button type="submit" name="delete_reply" class="btn" style="padding: 0.2rem 0.6rem; font-size: 0.75rem; background: #fecaca; color: #991b1b; border: 1px solid #fca5a5;">Delete</button>
                                </form>
                            </div>
                        </div>
                        <p style="margin-top: 0.5rem; color: #166534; font-size: 0.95rem;">
                            <?php echo nl2br(htmlspecialchars($r['admin_reply'])); ?>
                        </p>
                    </div>
                    
                    <form method="POST" id="reply_edit_<?php echo $r['id']; ?>" style="display: none; margin-top: 1rem; gap: 0.5rem;">
                        <input type="hidden" name="request_id" value="<?php echo $r['id']; ?>">
                        <input type="text" name="admin_reply" value="<?php echo htmlspecialchars($r['admin_reply']); ?>" required style="flex: 1; padding: 0.8rem; border-radius: 8px; border: 1px solid var(--border-color);">
                        <button type="submit" name="edit_reply" class="btn btn-primary" style="width: auto;">Save</button>
                        <button type="button" onclick="document.getElementById('reply_edit_<?php echo $r['id']; ?>').style.display='none'; document.getElementById('reply_display_<?php echo $r['id']; ?>').style.display='block';" class="btn" style="background: #e2e8f0; color: #475569; width: auto;">Cancel</button>
                    </form>
                <?php else: ?>
                    <form method="POST" style="margin-top: 1rem; display: flex; gap: 0.5rem;">
                        <input type="hidden" name="request_id" value="<?php echo $r['id']; ?>">
                        <input type="text" name="admin_reply" placeholder="Type your reply here..." required style="flex: 1; padding: 0.8rem; border-radius: 8px; border: 1px solid var(--border-color);">
                        <button type="submit" name="submit_reply" class="btn btn-primary" style="width: auto;">Reply</button>
                    </form>
                <?php endif; ?>
                
                <div style="margin-top: 1.5rem;">
                    <a href="view_student.php?id=<?php echo $r['student_id']; ?>&year_id=<?php echo $r['academic_year_id']; ?>" style="color: var(--primary-color); font-weight: 600; text-decoration: none; font-size: 0.9rem;">View Student Financial Profile &rarr;</a>
                </div>
            </div>
        <?php endforeach; ?>
    </main>
</body>
</html>
