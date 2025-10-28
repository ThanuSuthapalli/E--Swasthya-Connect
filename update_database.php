<?php
require_once 'includes/config.php';
requireRole('admin');

try {
    $pdo = getDBConnection();
    
    // Read and execute the SQL update
    $sql = file_get_contents(__DIR__ . '/sql/update_problem_updates_table.sql');
    $pdo->exec($sql);
    
    echo "Successfully updated problem_updates table structure!";
    
} catch (PDOException $e) {
    echo "Error updating database structure: " . $e->getMessage();
}
?>