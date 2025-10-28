<?php
require_once 'includes/config.php';

try {
    $pdo = getDBConnection();
    
    // Execute the ALTER TABLE statement
    $sql = "ALTER TABLE problems DROP COLUMN category";
    $pdo->exec($sql);
    
    echo "Successfully removed category column from problems table.";
    
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>