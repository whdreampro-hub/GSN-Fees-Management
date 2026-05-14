-- Academic Years Table
CREATE TABLE IF NOT EXISTS academic_years (
    id INT AUTO_INCREMENT PRIMARY KEY,
    year_name VARCHAR(20) NOT NULL UNIQUE,
    is_current BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Classes Table
CREATE TABLE IF NOT EXISTS classes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    class_name VARCHAR(20) NOT NULL UNIQUE,
    section ENUM('Primary', 'Secondary') NOT NULL
);

-- Student Enrollments (Links Student to Class and Year)
CREATE TABLE IF NOT EXISTS enrollments (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    student_id BIGINT NOT NULL,
    class_id INT NOT NULL,
    stream VARCHAR(10),
    academic_year_id INT NOT NULL,
    section ENUM('Primary', 'Secondary') NOT NULL,
    status ENUM('Active', 'Promoted', 'Graduated', 'Transferred', 'Repeated') DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (class_id) REFERENCES classes(id),
    FOREIGN KEY (academic_year_id) REFERENCES academic_years(id)
);

-- Student Requests Table
CREATE TABLE IF NOT EXISTS student_requests (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    student_id BIGINT NOT NULL,
    academic_year_id INT NOT NULL,
    request_type VARCHAR(50),
    message TEXT,
    status ENUM('Pending', 'Reviewed', 'Resolved') DEFAULT 'Pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (academic_year_id) REFERENCES academic_years(id)
);

-- Modify existing tables to support Academic Year IDs if necessary
-- Note: We will migrate data later if needed.

-- Add academic_year_id to payments if not exists
ALTER TABLE payments ADD COLUMN IF NOT EXISTS academic_year_id INT;
-- Add academic_year_id to fees_structure if not exists
ALTER TABLE fees_structure ADD COLUMN IF NOT EXISTS academic_year_id INT;

-- Insert default academic year if not exists
INSERT IGNORE INTO academic_years (year_name, is_current) VALUES ('2025-2026', TRUE);

-- Insert default classes
INSERT IGNORE INTO classes (class_name, section) VALUES 
('P1', 'Primary'), ('P2', 'Primary'), ('P3', 'Primary'), ('P4', 'Primary'), ('P5', 'Primary'), ('P6', 'Primary'),
('S1', 'Secondary'), ('S2', 'Secondary'), ('S3', 'Secondary'), ('S4', 'Secondary'), ('S5', 'Secondary'), ('S6', 'Secondary');
