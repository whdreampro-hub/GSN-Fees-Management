<?php
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

// 1. Get Current Academic Year ID
$stmt = $pdo->query("SELECT id FROM academic_years WHERE is_current = 1 LIMIT 1");
$year_id = $stmt->fetchColumn();

if (!$year_id) {
    die("No current academic year found. Please run schema update first.");
}

// 2. Migrate Students to Enrollments
$stmt = $pdo->query("SELECT * FROM students");
$students = $stmt->fetchAll();

foreach ($students as $student) {
    // Check if already enrolled
    $check = $pdo->prepare("SELECT id FROM enrollments WHERE student_id = ? AND academic_year_id = ?");
    $check->execute([$student['id'], $year_id]);
    if (!$check->fetch()) {
        // Find or create class
        $className = strtoupper($student['current_class']);
        $section = $student['section'];
        
        $classStmt = $pdo->prepare("SELECT id FROM classes WHERE class_name = ?");
        $classStmt->execute([$className]);
        $class_id = $classStmt->fetchColumn();
        
        if (!$class_id) {
            // Create class if not exists (though schema script should have created standard ones)
            $insertClass = $pdo->prepare("INSERT INTO classes (class_name, section) VALUES (?, ?)");
            $insertClass->execute([$className, $section]);
            $class_id = $pdo->lastInsertId();
        }
        
        // Enroll
        $enroll = $pdo->prepare("INSERT INTO enrollments (student_id, class_id, stream, academic_year_id, section) VALUES (?, ?, ?, ?, ?)");
        $enroll->execute([$student['id'], $class_id, $student['current_stream'], $year_id, $section]);
        echo "Enrolled student {$student['full_name']} in $className\n";
    }
}

// 3. Update Payments with Academic Year ID
$pdo->exec("UPDATE payments SET academic_year_id = $year_id WHERE academic_year_id IS NULL");

// 4. Update Fees Structure with Academic Year ID
$pdo->exec("UPDATE fees_structure SET academic_year_id = $year_id WHERE academic_year_id IS NULL");

echo "Migration complete.\n";
?>
