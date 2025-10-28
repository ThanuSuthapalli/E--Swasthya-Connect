<?php
require 'includes/config.php';

try {
    $pdo = getDBConnection();
    echo "Database connection: SUCCESS\n";
    
    $stmt = $pdo->query('SHOW TABLES');
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Tables found: " . implode(', ', $tables) . "\n";
    
    if (in_array('problems', $tables)) {
        $stmt = $pdo->query('DESCRIBE problems');
        echo "\nproblems table structure:\n";
        foreach ($stmt->fetchAll() as $col) {
            echo $col['Field'] . ' - ' . $col['Type'] . "\n";
        }
    } else {
        echo "\nWARNING: problems table NOT FOUND!\n";
    }

    // Try to insert a test problem
    if (in_array('problems', $tables)) {
        $stmt = $pdo->prepare("
            INSERT INTO problems (villager_id, title, description, category, priority, location, status) 
            VALUES (1, 'Test Problem', 'This is a test problem', 'health', 'medium', 'Test Location', 'pending')
        ");
        $result = $stmt->execute();
        if ($result) {
            echo "\nTest problem inserted successfully! ID: " . $pdo->lastInsertId() . "\n";
        } else {
            echo "\nFailed to insert test problem\n";
        }
    }
    
} catch(PDOException $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
?>