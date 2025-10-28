<?php
require_once 'includes/config.php';

try {
    // Test database connection
    $pdo = getDBConnection();
    echo "Database connection successful!\n\n";

    // Check if problems table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'problems'");
    if ($stmt->rowCount() > 0) {
        echo "Problems table exists!\n\n";
        
        // Show table structure
        $stmt = $pdo->query("DESCRIBE problems");
        echo "Table structure:\n";
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo $row['Field'] . " - " . $row['Type'] . " - " . ($row['Null'] === 'YES' ? 'NULL' : 'NOT NULL') . "\n";
        }
        
        // Count existing records
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM problems");
        $count = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "\nNumber of existing problems: " . $count['count'] . "\n";
        
        // Test insert
        echo "\nTesting insert operation...\n";
        $stmt = $pdo->prepare("
            INSERT INTO problems (villager_id, title, description, priority, location, status) 
            VALUES (?, ?, ?, ?, ?, 'pending')
        ");
        
        $result = $stmt->execute([
            1, // Test user ID
            'Test Problem',
            'This is a test problem description',
            'medium',
            'Test Location'
        ]);
        
        if ($result) {
            $newId = $pdo->lastInsertId();
            echo "Test insert successful! New ID: " . $newId . "\n";
        } else {
            echo "Test insert failed!\n";
            print_r($stmt->errorInfo());
        }
    } else {
        echo "Problems table does not exist!\n";
    }
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>