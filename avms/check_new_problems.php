<?php
require_once '../includes/config.php';
requireRole('avms');

header('Content-Type: application/json');

try {
    $pdo = getDBConnection();
    
    // Get count of unassigned problems
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM problems WHERE assigned_to IS NULL AND status NOT IN ('resolved', 'completed', 'closed')");
    $stmt->execute();
    $new_problems = $stmt->fetchColumn();
    
    // Get count of urgent problems
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM problems WHERE priority = 'urgent' AND status NOT IN ('resolved', 'completed', 'closed')");
    $stmt->execute();
    $urgent_problems = $stmt->fetchColumn();
    
    // Get count of problems assigned to current user
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM problems WHERE assigned_to = ? AND status NOT IN ('resolved', 'completed', 'closed')");
    $stmt->execute([$_SESSION['user_id']]);
    $my_problems = $stmt->fetchColumn();
    
    echo json_encode([
        'success' => true,
        'new_problems' => (int)$new_problems,
        'urgent_problems' => (int)$urgent_problems,
        'my_problems' => (int)$my_problems,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    error_log("Check new problems error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Failed to get problem counts',
        'new_problems' => 0,
        'urgent_problems' => 0,
        'my_problems' => 0
    ]);
}
?>
