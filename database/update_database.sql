-- Run this ONCE in phpMyAdmin: click attendancedb > SQL tab > paste > Go
-- (Your tables stay the same. We only make the attendance table ready to use.)

USE attendancedb;

-- 1. Allow 'Late' as a status (Present / Absent / Late)
ALTER TABLE attendance MODIFY status ENUM('Present','Absent','Late') NOT NULL;

-- 2. teacher_id can be empty (for example when the admin marks attendance)
ALTER TABLE attendance MODIFY teacher_id INT NULL;

-- 3. One record per student per day (saving again just updates it)
ALTER TABLE attendance ADD UNIQUE KEY unique_student_day (student_id, attendance_date);

-- Note: the `reports` table is not needed. The report page calculates
-- everything from the `attendance` table. You can leave it as it is.
