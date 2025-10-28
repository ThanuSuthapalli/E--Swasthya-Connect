<?php
// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'village_health_connect');
define('DB_USER', 'root');
define('DB_PASS', '');

// Site Configuration
define('SITE_NAME', 'Village Health Connect');
// Dynamically detect SITE_URL so the same code works on localhost and on deployed servers.
// We take the protocol and host from the request and compute the project root as two levels
// up from the requested script (this repository places this file in includes/).
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$basePath = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/\\');
// If basePath is '/', omit it so SITE_URL becomes protocol://host
define('SITE_URL', $protocol . '://' . $host . ($basePath && $basePath !== '/' ? $basePath : ''));
define('UPLOAD_DIR', 'uploads/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Database connection function
function getDBConnection() {
    try {
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $pdo;
    } catch(PDOException $e) {
        die("Database connection failed: " . $e->getMessage());
    }
}

// Authentication functions
function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['user_role']);
}

function hasRole($role) {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === $role;
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ' . SITE_URL . '/login/login.php');
        exit();
    }
}

function requireRole($role) {
    requireLogin();
    if (!hasRole($role)) {
        $_SESSION['error'] = 'Access denied. Insufficient permissions.';
        header('Location: ' . SITE_URL . '/index.php');
        exit();
    }
}

// Utility functions
function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// Build a correct public URL for an uploaded file stored in DB
// $storedPath may be like "uploads/filename.jpg" or just "filename.jpg"
// $depth is the relative prefix from the current script to the web root (e.g., '..')
function getUploadUrl($storedPath, $depth = '..') {
    if (empty($storedPath)) {
        return '';
    }
    $normalized = ltrim($storedPath, '/');
    if (strpos($normalized, UPLOAD_DIR) === 0) {
        // Already prefixed with uploads/
        return rtrim($depth, '/').'/'.$normalized;
    }
    // Just a filename stored – prefix with uploads/
    return rtrim($depth, '/').'/'.UPLOAD_DIR.$normalized;
}

function redirect($url) {
    header("Location: " . $url);
    exit();
}

function setMessage($type, $message) {
    $_SESSION[$type] = $message;
}

function getMessage($type) {
    if (isset($_SESSION[$type])) {
        $message = $_SESSION[$type];
        unset($_SESSION[$type]);
        return $message;
    }
    return '';
}

// Problem management functions
function assignProblemToAVMS($problem_id, $avms_id) {
    try {
        $pdo = getDBConnection();

        // Update problem assignment and status
        $stmt = $pdo->prepare("UPDATE problems SET assigned_to = ?, status = 'assigned', updated_at = NOW() WHERE id = ? AND assigned_to IS NULL");
        $result = $stmt->execute([$avms_id, $problem_id]);

        if ($result && $stmt->rowCount() > 0) {
            // Log the assignment in problem_updates
            $stmt = $pdo->prepare("INSERT INTO problem_updates (problem_id, updated_by, old_status, new_status, notes) VALUES (?, ?, 'pending', 'assigned', 'Problem assigned to AVMS member')");
            $stmt->execute([$problem_id, $avms_id]);

            // Create notification for villager
            $stmt = $pdo->prepare("SELECT villager_id FROM problems WHERE id = ?");
            $stmt->execute([$problem_id]);
            $villager_id = $stmt->fetchColumn();

            if ($villager_id) {
                addNotification($villager_id, $problem_id, 'Problem Assigned', 'Your problem has been assigned to an ANMS member and is being reviewed.');
            }

            return true;
        }
        return false;
    } catch (PDOException $e) {
        error_log("Error assigning problem: " . $e->getMessage());
        return false;
    }
}

function updateProblemStatus($problem_id, $new_status, $updated_by, $notes = '') {
    try {
        $pdo = getDBConnection();
        $pdo->beginTransaction();

        // Get current status and villager_id
        $stmt = $pdo->prepare("SELECT status, villager_id FROM problems WHERE id = ?");
        $stmt->execute([$problem_id]);
        $problem = $stmt->fetch();

        if (!$problem) {
            error_log("Problem not found with ID: " . $problem_id);
            return false;
        }

        $old_status = $problem['status'];
        error_log("Update problem $problem_id: current=$old_status, new=$new_status, updated_by=$updated_by");

        // Always update the problem to ensure updated_at is set
        $resolved_at = ($new_status === 'resolved' || $new_status === 'completed') ? date('Y-m-d H:i:s') : null;
        $stmt = $pdo->prepare("UPDATE problems SET status = ?, updated_at = NOW(), resolved_at = ? WHERE id = ?");
        $stmt->execute([$new_status, $resolved_at, $problem_id]);
        
        // Always log the status change attempt in problem_updates with correct columns
        $stmt = $pdo->prepare("INSERT INTO problem_updates 
            (problem_id, updated_by, update_type, old_value, new_value, notes) 
            VALUES (?, ?, 'status', ?, ?, ?)");
        $stmt->execute([
            $problem_id, 
            $updated_by, 
            $old_status, 
            $new_status, 
            $notes
        ]);

        // Always send notification if there are notes or status changed
        if ($problem['villager_id'] && ($notes || $old_status !== $new_status)) {
            $message = "Your problem has been updated";
            if ($old_status !== $new_status) {
                $message = "Your problem status has been updated to: " . ucfirst(str_replace('_', ' ', $new_status));
            }
            if ($notes) {
                $message .= ". Note: " . $notes;
            }
            addNotification($problem['villager_id'], $problem_id, 'Status Update', $message);
        }

        $pdo->commit();
        return true;
    } catch (PDOException $e) {
        if ($pdo && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Error updating problem status: " . $e->getMessage());
        return false;
    }
}

function escalateProblemToDoctor($problem_id, $doctor_id, $avms_id, $notes = '') {
    try {
        $pdo = getDBConnection();
        $pdo->beginTransaction();

        // Get current status
        $stmt = $pdo->prepare("SELECT status, villager_id FROM problems WHERE id = ?");
        $stmt->execute([$problem_id]);
        $problem = $stmt->fetch();

        if (!$problem) {
            error_log("Problem not found with ID: " . $problem_id);
            return false;
        }

        $old_status = $problem['status'];

        // Update problem to escalated status
        $stmt = $pdo->prepare("UPDATE problems SET status = 'escalated', escalated_to = ?, updated_at = NOW() WHERE id = ?");
        $result = $stmt->execute([$doctor_id, $problem_id]);

        if (!$result) {
            $pdo->rollBack();
            error_log("Failed to update problem status to escalated");
            return false;
        }

        // Log the escalation in problem_updates with correct columns
        $stmt = $pdo->prepare("INSERT INTO problem_updates 
            (problem_id, updated_by, update_type, old_value, new_value, notes) 
            VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $problem_id,
            $avms_id,
            'escalation',
            $old_status,
            'escalated',
            'Escalated to doctor: ' . $notes
        ]);

        // Notify doctor
        addNotification(
            $doctor_id, 
            $problem_id, 
            'Case Escalated', 
            'A new case has been escalated to you for medical review. Notes: ' . $notes
        );

        // Notify villager
        if ($problem['villager_id']) {
            addNotification(
                $problem['villager_id'], 
                $problem_id, 
                'Case Escalated to Doctor', 
                'Your case has been escalated to a doctor for medical review. Notes: ' . $notes
            );
        }

        $pdo->commit();
        return true;

    } catch (PDOException $e) {
        if ($pdo && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Error escalating problem: " . $e->getMessage());
        return false;
    }
}

// Notification functions
function addNotification($user_id, $problem_id, $title, $message, $type = 'info') {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("INSERT INTO notifications (user_id, problem_id, title, message, type) VALUES (?, ?, ?, ?, ?)");
        return $stmt->execute([$user_id, $problem_id, $title, $message, $type]);
    } catch (PDOException $e) {
        error_log("Error adding notification: " . $e->getMessage());
        return false;
    }
}

function getUnreadNotifications($user_id) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? AND is_read = 0 ORDER BY created_at DESC");
        $stmt->execute([$user_id]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error getting notifications: " . $e->getMessage());
        return [];
    }
}

// File upload function
function handleFileUpload($file, $allowed_types = ['jpg', 'jpeg', 'png', 'gif']) {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return false;
    }

    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($file_extension, $allowed_types)) {
        throw new Exception('Invalid file type. Only ' . implode(', ', $allowed_types) . ' files are allowed.');
    }

    if ($file['size'] > MAX_FILE_SIZE) {
        throw new Exception('File size too large. Maximum size is 5MB.');
    }

    $upload_dir = __DIR__ . '/../' . UPLOAD_DIR;
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $new_filename = 'upload_' . time() . '_' . rand(1000, 9999) . '.' . $file_extension;
    $upload_path = $upload_dir . $new_filename;

    if (move_uploaded_file($file['tmp_name'], $upload_path)) {
        return UPLOAD_DIR . $new_filename;
    }

    throw new Exception('Failed to upload file.');
}

// Get user information
function getUserInfo($user_id) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Error getting user info: " . $e->getMessage());
        return false;
    }
}

// Format date function
function formatDate($date) {
    return date('M j, Y g:i A', strtotime($date));
}

// Get problem statistics
function getProblemStats($user_id = null, $role = null) {
    try {
        $pdo = getDBConnection();
        $stats = [];

        if ($role === 'villager' && $user_id) {
            $stmt = $pdo->prepare("SELECT status, COUNT(*) as count FROM problems WHERE villager_id = ? GROUP BY status");
            $stmt->execute([$user_id]);
        } elseif ($role === 'avms' && $user_id) {
            $stmt = $pdo->prepare("SELECT status, COUNT(*) as count FROM problems WHERE assigned_to = ? GROUP BY status");
            $stmt->execute([$user_id]);
        } elseif ($role === 'doctor' && $user_id) {
            $stmt = $pdo->prepare("SELECT status, COUNT(*) as count FROM problems WHERE escalated_to = ? GROUP BY status");
            $stmt->execute([$user_id]);
        } else {
            $stmt = $pdo->prepare("SELECT status, COUNT(*) as count FROM problems GROUP BY status");
            $stmt->execute();
        }

        $results = $stmt->fetchAll();
        foreach ($results as $row) {
            $stats[$row['status']] = $row['count'];
        }

        return $stats;
    } catch (PDOException $e) {
        error_log("Error getting problem stats: " . $e->getMessage());
        return [];
    }
}

// Check if user can access problem
function canAccessProblem($problem_id, $user_id, $user_role) {
    try {
        $pdo = getDBConnection();

        switch ($user_role) {
            case 'admin':
                return true; // Admin can access all problems
            case 'villager':
                $stmt = $pdo->prepare("SELECT id FROM problems WHERE id = ? AND villager_id = ?");
                $stmt->execute([$problem_id, $user_id]);
                return $stmt->fetchColumn() !== false;
            case 'avms':
                $stmt = $pdo->prepare("SELECT id FROM problems WHERE id = ? AND (assigned_to = ? OR assigned_to IS NULL)");
                $stmt->execute([$problem_id, $user_id]);
                return $stmt->fetchColumn() !== false;
            case 'doctor':
                $stmt = $pdo->prepare("SELECT id FROM problems WHERE id = ? AND (escalated_to = ? OR status = 'escalated')");
                $stmt->execute([$problem_id, $user_id]);
                return $stmt->fetchColumn() !== false;
            default:
                return false;
        }
    } catch (PDOException $e) {
        error_log("Error checking problem access: " . $e->getMessage());
        return false;
    }
}
?>