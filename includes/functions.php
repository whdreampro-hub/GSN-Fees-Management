<?php
session_start();

function getGlobalDefaultYearData($pdo) {
    $stmt = $pdo->prepare("SELECT * FROM academic_years WHERE is_current = 1");
    $stmt->execute();
    $data = $stmt->fetch();
    return $data ?: ['id' => 0, 'year_name' => date('Y')];
}

function getActiveYearData($pdo) {
    if (isset($_SESSION['active_academic_year_id'])) {
        $data = getAcademicYearById($pdo, $_SESSION['active_academic_year_id']);
        if ($data) return $data;
    }
    
    $current = getGlobalDefaultYearData($pdo);
    if ($current && isset($current['id'])) {
        $_SESSION['active_academic_year_id'] = $current['id'];
    }
    return $current;
}

function getCurrentYearData($pdo) {
    return getActiveYearData($pdo);
}

function getCurrentYear($pdo) {
    $data = getActiveYearData($pdo);
    return $data['year_name'];
}

function getAcademicYearById($pdo, $id) {
    $stmt = $pdo->prepare("SELECT * FROM academic_years WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

function getAllAcademicYears($pdo) {
    $stmt = $pdo->query("SELECT * FROM academic_years ORDER BY year_name DESC");
    return $stmt->fetchAll();
}

function getClasses($pdo, $section = null) {
    $query = "SELECT * FROM classes";
    if ($section) {
        $query .= " WHERE section = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$section]);
    } else {
        $stmt = $pdo->query($query);
    }
    return $stmt->fetchAll();
}

function isLoggedIn() {
    return isset($_SESSION['admin_id']);
}

function redirect($url) {
    header("Location: $url");
    exit();
}

function generateRegNumber($pdo) {
    $year = date('Y');
    $stmt = $pdo->query("SELECT reg_number FROM students ORDER BY id DESC LIMIT 1");
    $lastReg = $stmt->fetchColumn();
    
    if (!$lastReg) {
        $nextNum = 1;
    } else {
        $parts = explode('-', $lastReg);
        if (count($parts) >= 3) {
            $lastNum = (int)$parts[2];
            $nextNum = $lastNum + 1;
        } else {
            $nextNum = 1;
        }
    }
    
    return "GSN-$year-" . str_pad($nextNum, 8, '0', STR_PAD_LEFT);
}

function getEnrollment($pdo, $student_id, $academic_year_id) {
    $stmt = $pdo->prepare("SELECT e.*, c.class_name FROM enrollments e 
                          JOIN classes c ON e.class_id = c.id 
                          WHERE e.student_id = ? AND e.academic_year_id = ?");
    $stmt->execute([$student_id, $academic_year_id]);
    return $stmt->fetch();
}

function enrollStudent($pdo, $student_id, $class_id, $stream, $academic_year_id, $section) {
    $stmt = $pdo->prepare("INSERT INTO enrollments (student_id, class_id, stream, academic_year_id, section) 
                          VALUES (?, ?, ?, ?, ?)");
    return $stmt->execute([$student_id, $class_id, $stream, $academic_year_id, $section]);
}

function getFeeAmount($pdo, $section, $term, $academic_year_id) {
    $stmt = $pdo->prepare("SELECT amount FROM fees_structure WHERE section = ? AND term = ? AND academic_year_id = ?");
    $stmt->execute([$section, $term, $academic_year_id]);
    return $stmt->fetchColumn() ?: 0;
}


function getDetailedYearlyStatus($pdo, $student_id, $academic_year_id) {
    $enrollment = getEnrollment($pdo, $student_id, $academic_year_id);
    if (!$enrollment) return ['total_required' => 0, 'total_paid' => 0, 'balance' => 0, 'enrollment' => null];

    $section = $enrollment['section'];
    
    // Fetch all 3 terms fees in one go
    $stmt = $pdo->prepare("SELECT SUM(amount) FROM fees_structure WHERE section = ? AND academic_year_id = ?");
    $stmt->execute([$section, $academic_year_id]);
    $totalRequired = $stmt->fetchColumn() ?: 0;
    
    $stmt = $pdo->prepare("SELECT SUM(amount_paid) FROM payments WHERE student_id = ? AND academic_year_id = ?");
    $stmt->execute([$student_id, $academic_year_id]);
    $totalPaid = $stmt->fetchColumn() ?: 0;
    
    $balance = $totalPaid - $totalRequired;
    $noFeesSet = ($totalRequired == 0);
    
    return [
        'total_required' => $totalRequired,
        'total_paid' => $totalPaid,
        'balance' => $balance,
        'enrollment' => $enrollment,
        'no_fees_set' => $noFeesSet
    ];
}
?>
