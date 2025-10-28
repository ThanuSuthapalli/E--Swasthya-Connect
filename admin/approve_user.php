<?php
require_once '../includes/config.php';
requireRole('admin');

if (!isset($_GET['id']) || !isset($_GET['action'])) {
    setMessage('error', 'Invalid request parameters.');
    redirect(SITE_URL . '/admin/dashboard.php');
}

$user_id = (int)$_GET['id'];
$action = $_GET['action'];

if (!in_array($action, ['approve', 'reject'])) {
    setMessage('error', 'Invalid action specified.');
    redirect(SITE_URL . '/admin/dashboard.php');
}

try {
    $pdo = getDBConnection();

    // Get user details first
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND status = 'pending'");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    if (!$user) {
        setMessage('error', 'User not found or already processed.');
        redirect(SITE_URL . '/admin/dashboard.php');
    }

    if ($action === 'approve') {
        // Approve user
        $stmt = $pdo->prepare("UPDATE users SET status = 'active' WHERE id = ?");
        $result = $stmt->execute([$user_id]);

        if ($result) {
            // Send notification to approved user
            try {
                addNotification($user_id, null, 'Account Approved', 
                    "Your " . ucfirst($user['role']) . " account has been approved! You can now login and use all features.");
            } catch (Exception $e) {
                error_log("Notification error: " . $e->getMessage());
            }

            setMessage('success', "User {$user['name']} ({$user['role']}) has been approved successfully!");
        } else {
            setMessage('error', 'Failed to approve user. Please try again.');
        }
    } else {
        // Reject user - delete the account
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $result = $stmt->execute([$user_id]);

        if ($result) {
            setMessage('success', "User {$user['name']} has been rejected and removed from the system.");
        } else {
            setMessage('error', 'Failed to reject user. Please try again.');
        }
    }

} catch (Exception $e) {
    error_log("User approval error: " . $e->getMessage());
    setMessage('error', 'System error occurred while processing the request.');
}

// Add a small delay to ensure message is set before redirect
sleep(1);
redirect(SITE_URL . '/admin/dashboard.php');
?>