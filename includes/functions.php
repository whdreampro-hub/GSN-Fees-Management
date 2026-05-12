<?php
session_start();

function getCurrentYear($pdo) {
    $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'current_year'");
    $stmt->execute();
    return $stmt->fetchColumn() ?: date('Y');
}

function isLoggedIn() {
    return isset($_SESSION['admin_id']);
}

function redirect($url) {
    header("Location: $url");
    exit();
}

function generateRegNumber($pdo) {
    $year = date('2026'); // As requested, GSN-2026
    $stmt = $pdo->query("SELECT reg_number FROM students ORDER BY id DESC LIMIT 1");
    $lastReg = $stmt->fetchColumn();
    
    if (!$lastReg) {
        $nextNum = 1;
    } else {
        $parts = explode('-', $lastReg);
        $lastNum = (int)$parts[2];
        $nextNum = $lastNum + 1;
    }
    
    return "GSN-$year-" . str_pad($nextNum, 12, '0', STR_PAD_LEFT);
}

function getFeeAmount($pdo, $section, $term) {
    $stmt = $pdo->prepare("SELECT amount FROM fees_structure WHERE section = ? AND term = ?");
    $stmt->execute([$section, $term]);
    return $stmt->fetchColumn() ?: 0;
}

/**
 * Cumulative Status Logic:
 * Checks if the total paid for the year covers the cumulative required fees up to a specific term.
 */
function getStudentPaymentStatus($pdo, $student_id, $year, $term) {
    $sectionStmt = $pdo->prepare("SELECT section FROM students WHERE id = ?");
    $sectionStmt->execute([$student_id]);
    $section = $sectionStmt->fetchColumn();
    
    // Calculate cumulative required fees for the year up to the requested term
    $cumulativeRequired = 0;
    for ($i = 1; $i <= $term; $i++) {
        $cumulativeRequired += getFeeAmount($pdo, $section, $i);
    }

    $requiredForThisTerm = getFeeAmount($pdo, $section, $term);
    $requiredUpToPrevious = $cumulativeRequired - $requiredForThisTerm;
    
    // Total paid for the whole year
    $stmt = $pdo->prepare("SELECT SUM(amount_paid) FROM payments WHERE student_id = ? AND year = ?");
    $stmt->execute([$student_id, $year]);
    $totalPaid = $stmt->fetchColumn() ?: 0;
    
    if ($totalPaid >= $cumulativeRequired) {
        return 'Paid';
    } elseif ($totalPaid > $requiredUpToPrevious) {
        return 'Partial';
    } else {
        return 'Unpaid';
    }
}

function getDetailedYearlyStatus($pdo, $student_id, $year) {
    $sectionStmt = $pdo->prepare("SELECT section FROM students WHERE id = ?");
    $sectionStmt->execute([$student_id]);
    $section = $sectionStmt->fetchColumn();
    
    $totalRequired = 0;
    for ($i = 1; $i <= 3; $i++) {
        $totalRequired += getFeeAmount($pdo, $section, $i);
    }
    
    $stmt = $pdo->prepare("SELECT SUM(amount_paid) FROM payments WHERE student_id = ? AND year = ?");
    $stmt->execute([$student_id, $year]);
    $totalPaid = $stmt->fetchColumn() ?: 0;
    
    $balance = $totalPaid - $totalRequired;
    
    return [
        'total_required' => $totalRequired,
        'total_paid' => $totalPaid,
        'balance' => $balance
    ];
}
?>
