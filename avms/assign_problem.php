<?php
require_once '../includes/config.php';
requireRole('avms');

// Check if problem ID is provided
if (!isset($_GET['id']) && !isset($_POST['problem_id'])) {
    setMessage('error', 'No problem specified.');
    redirect(SITE_URL . '/avms/dashboard.php');
}

$problem_id = (int)($_GET['id'] ?? $_POST['problem_id']);
$avms_id = $_SESSION['user_id'];

// Handle AJAX request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['problem_id'])) {
    header('Content-Type: application/json');

    try {
        $result = assignProblemToAVMS($problem_id, $avms_id);

        if ($result) {
            echo json_encode(['success' => true, 'message' => 'Problem assigned successfully!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Problem is already assigned or does not exist.']);
        }
    } catch (Exception $e) {
        error_log("Assignment error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'System error occurred.']);
    }
    exit;
}

// Handle regular request
try {
    $result = assignProblemToAVMS($problem_id, $avms_id);

    if ($result) {
        setMessage('success', 'Problem assigned to you successfully! You can now work on resolving it.');
    } else {
        setMessage('error', 'Problem is already assigned or does not exist.');
    }
} catch (Exception $e) {
    error_log("Assignment error: " . $e->getMessage());
    setMessage('error', 'Failed to assign problem due to system error.');
}

redirect(SITE_URL . '/avms/dashboard.php');
?>