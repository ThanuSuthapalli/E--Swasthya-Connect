<?php
require 'includes/config.php';
require 'includes/working_medical_functions.php';

// Test data
$problem_id = 1; // You may need to adjust this
$doctor_id = 1;  // You may need to adjust this
$response = "Test medical response - patient should rest and take prescribed medication.";
$recommendations = "Follow up in 7 days, monitor temperature.";
$follow_up_required = 1;
$urgency_level = "medium";

echo "Testing saveMedicalResponseActual function...\n\n";

// Check if problem exists
try {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT id, title FROM problems LIMIT 1");
    $stmt->execute();
    $problem = $stmt->fetch();
    
    if ($problem) {
        $problem_id = $problem['id'];
        echo "Using problem ID: {$problem_id} - {$problem['title']}\n";
    } else {
        echo "ERROR: No problems found in database. Please create a problem first.\n";
        exit;
    }
    
    // Check if doctor exists
    $stmt = $pdo->prepare("SELECT id, name FROM users WHERE role = 'doctor' LIMIT 1");
    $stmt->execute();
    $doctor = $stmt->fetch();
    
    if ($doctor) {
        $doctor_id = $doctor['id'];
        echo "Using doctor ID: {$doctor_id} - {$doctor['name']}\n\n";
    } else {
        echo "ERROR: No doctors found in database. Please create a doctor user first.\n";
        exit;
    }
    
} catch (Exception $e) {
    echo "ERROR checking data: " . $e->getMessage() . "\n";
    exit;
}

// Test the function
$result = saveMedicalResponseActual($problem_id, $doctor_id, $response, $recommendations, $follow_up_required, $urgency_level);

echo "Result:\n";
print_r($result);

if ($result['success']) {
    echo "\n✅ SUCCESS! Medical response saved.\n";
    echo "Response ID: " . $result['response_id'] . "\n";
    echo "Notifications created: " . $result['notifications_created'] . "\n";
    
    // Verify in database
    $stmt = $pdo->prepare("SELECT * FROM medical_responses WHERE id = ?");
    $stmt->execute([$result['response_id']]);
    $saved_response = $stmt->fetch();
    
    echo "\nSaved response in database:\n";
    print_r($saved_response);
    
    // Check notifications
    $stmt = $pdo->prepare("SELECT * FROM notifications WHERE problem_id = ? ORDER BY created_at DESC LIMIT 5");
    $stmt->execute([$problem_id]);
    $notifications = $stmt->fetchAll();
    
    echo "\nNotifications created:\n";
    foreach ($notifications as $notif) {
        echo "- User {$notif['user_id']}: {$notif['title']}\n";
    }
    
} else {
    echo "\n❌ FAILED! Error: " . $result['message'] . "\n";
    if (isset($result['error'])) {
        echo "Details: " . $result['error'] . "\n";
    }
}