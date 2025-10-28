<?php
require 'includes/config.php';

try {
    $pdo = getDBConnection();
    echo "Database connection: SUCCESS\n";
    
    $stmt = $pdo->query('SHOW TABLES');
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Tables found: " . implode(', ', $tables) . "\n";
    
    if (in_array('medical_responses', $tables)) {
        $stmt = $pdo->query('DESCRIBE medical_responses');
        echo "\nmedical_responses structure:\n";
        foreach ($stmt->fetchAll() as $col) {
            echo $col['Field'] . ' - ' . $col['Type'] . "\n";
        }
    } else {
        echo "\nWARNING: medical_responses table NOT FOUND!\n";
    }
    
    if (in_array('notifications', $tables)) {
        $stmt = $pdo->query('DESCRIBE notifications');
        echo "\nnotifications structure:\n";
        foreach ($stmt->fetchAll() as $col) {
            echo $col['Field'] . ' - ' . $col['Type'] . "\n";
        }
    } else {
        echo "\nWARNING: notifications table NOT FOUND!\n";
    }
    
    if (in_array('problem_updates', $tables)) {
        $stmt = $pdo->query('DESCRIBE problem_updates');
        echo "\nproblem_updates structure:\n";
        foreach ($stmt->fetchAll() as $col) {
            echo $col['Field'] . ' - ' . $col['Type'] . "\n";
        }
    } else {
        echo "\nWARNING: problem_updates table NOT FOUND!\n";
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage();
}