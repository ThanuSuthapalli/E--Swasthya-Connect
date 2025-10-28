<?php
require_once '../includes/config.php';
requireLogin();

// Handle AJAX request to mark notification as read
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['notification_id'])) {
    header('Content-Type: application/json');

    $notification_id = (int)$_POST['notification_id'];

    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
        $result = $stmt->execute([$notification_id, $_SESSION['user_id']]);

        if ($result) {
            echo json_encode(['success' => true, 'message' => 'Notification marked as read']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to mark notification as read']);
        }
    } catch (Exception $e) {
        error_log("Mark notification read error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'System error occurred']);
    }
    exit;
}

// If not AJAX, redirect to notifications page
redirect(SITE_URL . '/' . $_SESSION['user_role'] . '/notifications.php');
?>