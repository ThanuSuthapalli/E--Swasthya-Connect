<?php
// Silent Database Setup - No Popups, Just Working Tables
require_once 'config.php';

// Set JSON response header
header('Content-Type: application/json');

$response = ['success' => false, 'message' => '', 'details' => []];

try {
    $pdo = getDBConnection();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Create medical_responses table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS medical_responses (
            id INT AUTO_INCREMENT PRIMARY KEY,
            problem_id INT NOT NULL,
            doctor_id INT NOT NULL,
            response TEXT NOT NULL,
            recommendations TEXT,
            follow_up_required TINYINT(1) DEFAULT 0,
            urgency_level VARCHAR(20) DEFAULT 'medium',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_problem_id (problem_id),
            INDEX idx_doctor_id (doctor_id),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");
    $response['details'][] = 'medical_responses table created';

    // Create notifications table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            problem_id INT DEFAULT NULL,
            title VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            type VARCHAR(20) DEFAULT 'info',
            is_read TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user_id (user_id),
            INDEX idx_problem_id (problem_id),
            INDEX idx_is_read (is_read),
            INDEX idx_type (type),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");
    $response['details'][] = 'notifications table created';

    // Add additional_info column to users table
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN additional_info JSON DEFAULT NULL");
        $response['details'][] = 'additional_info column added to users table';
    } catch (Exception $e) {
        $response['details'][] = 'additional_info column already exists';
    }

    // Get current data counts
    $stmt = $pdo->query("SELECT COUNT(*) FROM problems WHERE status = 'escalated'");
    $escalated_count = $stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) FROM medical_responses");
    $responses_count = $stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) FROM notifications");
    $notifications_count = $stmt->fetchColumn();

    // Create sample escalated cases if none exist
    if ($escalated_count == 0) {
        $stmt = $pdo->query("SELECT id FROM users WHERE role = 'villager' LIMIT 1");
        $villager_id = $stmt->fetchColumn();

        $stmt = $pdo->query("SELECT id FROM users WHERE role = 'avms' LIMIT 1");
        $avms_id = $stmt->fetchColumn();

        if ($villager_id && $avms_id) {
            $sample_cases = [
                [
                    'title' => 'High Fever with Difficulty Breathing',
                    'description' => 'Patient experiencing high fever (102°F) with difficulty breathing and chest pain. Symptoms started 2 days ago and are worsening.',
                    'category' => 'respiratory',
                    'priority' => 'urgent'
                ],
                [
                    'title' => 'Severe Abdominal Pain',
                    'description' => 'Acute onset of severe abdominal pain in right lower quadrant. Patient unable to eat and has been vomiting.',
                    'category' => 'gastrointestinal', 
                    'priority' => 'urgent'
                ],
                [
                    'title' => 'Chest Pain with Sweating',
                    'description' => 'Patient complaining of crushing chest pain radiating to left arm, associated with profuse sweating.',
                    'category' => 'cardiac',
                    'priority' => 'urgent'
                ],
                [
                    'title' => 'Deep Wound with Signs of Infection',
                    'description' => 'Deep laceration on lower leg showing signs of infection - redness, swelling, and pus discharge.',
                    'category' => 'wound',
                    'priority' => 'high'
                ],
                [
                    'title' => 'Persistent Headache with Vision Problems',
                    'description' => 'Severe headache for 3 days with blurred vision and sensitivity to light.',
                    'category' => 'neurological',
                    'priority' => 'high'
                ]
            ];

            foreach ($sample_cases as $case) {
                $stmt = $pdo->prepare("
                    INSERT INTO problems (title, description, category, priority, villager_id, assigned_to, status, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, 'escalated', NOW(), NOW())
                ");
                $stmt->execute([
                    $case['title'],
                    $case['description'],
                    $case['category'],
                    $case['priority'],
                    $villager_id,
                    $avms_id
                ]);
            }
            $response['details'][] = 'Created 5 sample escalated cases';
        }
    }

    $response['success'] = true;
    $response['message'] = 'Database setup completed successfully';
    $response['stats'] = [
        'escalated_cases' => $escalated_count,
        'medical_responses' => $responses_count,
        'notifications' => $notifications_count
    ];

} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = 'Database setup failed: ' . $e->getMessage();
    $response['error'] = $e->getMessage();
}

// If accessed via browser, show simple success page
if (isset($_GET['setup'])) {
    if ($response['success']) {
        echo "<!DOCTYPE html><html><head><title>Setup Complete</title></head><body>";
        echo "<h2>✅ Database Setup Completed Successfully!</h2>";
        echo "<p>All tables created and configured properly.</p>";
        echo "<a href='../doctor/dashboard.php'>Go to Doctor Dashboard</a>";
        echo "</body></html>";
    } else {
        echo "<!DOCTYPE html><html><head><title>Setup Failed</title></head><body>";
        echo "<h2>❌ Database Setup Failed</h2>";
        echo "<p>Error: " . htmlspecialchars($response['message']) . "</p>";
        echo "</body></html>";
    }
} else {
    // Return JSON response for AJAX calls
    echo json_encode($response);
}
?>