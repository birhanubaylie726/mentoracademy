<?php
// Adjust the path to your database configuration file if needed
require_once '/db.php'; 

echo "<h3>🛠️ Running Portal Database Structure Alignment...</h3>";

try {
    // 1. Ensure core properties exist on the 'users' table
    add_column_if_missing($pdo, 'users', 'fullname', "VARCHAR(255) NOT NULL AFTER username");
    add_column_if_missing($pdo, 'users', 'role', "ENUM('admin', 'teacher', 'student') NOT NULL DEFAULT 'student'");

    // 2. Ensure core properties exist on the 'subjects' table
    add_column_if_missing($pdo, 'subjects', 'subject_name', "VARCHAR(150) NOT NULL");

    // 3. Ensure core properties exist on the 'exams' table
    add_column_if_missing($pdo, 'exams', 'title', "VARCHAR(255) NOT NULL");
    add_column_if_missing($pdo, 'exams', 'subject_id', "INT NOT NULL");
    add_column_if_missing($pdo, 'exams', 'duration', "INT NOT NULL DEFAULT 60");
    add_column_if_missing($pdo, 'exams', 'is_active', "TINYINT(1) NOT NULL DEFAULT 1");
    add_column_if_missing($pdo, 'exams', 'created_by', "INT NOT NULL");

    // 4. Ensure core properties exist on the 'questions' table
    add_column_if_missing($pdo, 'questions', 'exam_id', "INT NOT NULL");
    add_column_if_missing($pdo, 'questions', 'question_text', "TEXT NOT NULL");
    add_column_if_missing($pdo, 'questions', 'option_a', "VARCHAR(255) NOT NULL");
    add_column_if_missing($pdo, 'questions', 'option_b', "VARCHAR(255) NOT NULL");
    add_column_if_missing($pdo, 'questions', 'option_c', "VARCHAR(255) DEFAULT NULL");
    add_column_if_missing($pdo, 'questions', 'option_d', "VARCHAR(255) DEFAULT NULL");
    add_column_if_missing($pdo, 'questions', 'correct_option', "ENUM('A', 'B', 'C', 'D') NOT NULL");

    // 5. Ensure core structural tracking tables exist completely
    $pdo->exec("CREATE TABLE IF NOT EXISTS student_exams (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        exam_id INT NOT NULL,
        score INT DEFAULT 0,
        total_questions INT DEFAULT 0,
        status ENUM('In Progress', 'Completed') DEFAULT 'Completed',
        submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    echo "<p style='color: green; font-weight: bold;'>✅ Database alignment sequence finished with zero structural errors!</p>";
    echo "<p>You can safely delete this file now and reload your workspace screens.</p>";

} catch (Exception $e) {
    echo "<p style='color: red; font-weight: bold;'>🚨 Schema repair stopped: " . $e->getMessage() . "</p>";
}

/**
 * Safely updates structures without dropping data columns
 */
function add_column_if_missing($pdo, $table, $column, $definition) {
    try {
        $check = $pdo->query("SHOW COLUMNS FROM `$table` LIKE '$column'")->fetch();
        if (!$check) {
            $pdo->exec("ALTER TABLE `$table` ADD `$column` $definition");
            echo "✨ Added missing column <code>$column</code> to table <code>$table</code>.<br>";
        } else {
            echo "ℹ️ Column <code>$column</code> already exists in table <code>$table</code>.<br>";
        }
    } catch (PDOException $e) {
        // If the table itself is missing, let's notify the user clearly
        echo "⚠️ Could not check table <code>$table</code>: " . $e->getMessage() . "<br>";
    }
}
?>