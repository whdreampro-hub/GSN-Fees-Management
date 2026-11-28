<?php
// One-off schema changes must never be triggered through a public HTTP request.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/db_connect.php';

try {
    // Add unique constraint to fees_structure to make ON DUPLICATE KEY UPDATE work
    $pdo->exec("ALTER TABLE fees_structure ADD UNIQUE INDEX IF NOT EXISTS idx_section_term_year (section, term, academic_year_id)");
    echo "Database schema updated successfully!";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
