<?php
require_once '../includes/config.php';

// Include working medical functions
if (file_exists('../includes/working_medical_functions.php')) {
    require_once '../includes/working_medical_functions.php';
}

requireRole('doctor');

function getNotificationTableColumns(PDO $pdo): array {
    static $columnsCache = null;

    if ($columnsCache === null) {
        $stmt = $pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'notifications'");
        $stmt->execute();
        $columnsCache = array_map('strtolower', array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'column_name'));
    }

    return $columnsCache;
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        $pdo = getDBConnection();
        
        switch ($_POST['action']) {
            case 'mark_all_read':
                $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
                $stmt->execute([$_SESSION['user_id']]);
                $_SESSION['flash_message'] = 'All notifications marked as read.';
                $_SESSION['flash_type'] = 'success';
                echo json_encode(['success' => true, 'message' => 'All notifications marked as read', 'reload' => true]);
                break;
                
            case 'load_more':
                // Get current count from URL parameter
                $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 50;
                $limit = 25;
                
                $stmt = $pdo->prepare("
                    SELECT n.*, p.title as problem_title
                    FROM notifications n
                    LEFT JOIN problems p ON n.problem_id = p.id
                    WHERE n.user_id = ?
                    ORDER BY n.created_at DESC
                    LIMIT ? OFFSET ?
                ");
                $stmt->execute([$_SESSION['user_id'], $limit, $offset]);
                $more_notifications = $stmt->fetchAll();
                
                if (empty($more_notifications)) {
                    echo json_encode(['success' => false, 'message' => 'No more notifications']);
                } else {
                    // Generate HTML for new notifications
                    $html = '';
                    foreach ($more_notifications as $notification) {
                        $html .= generateNotificationHTML($notification);
                    }
                    echo json_encode(['success' => true, 'notifications' => $html]);
                }
                break;
                
            default:
                echo json_encode(['success' => false, 'message' => 'Invalid action']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

function generateNotificationHTML($notification) {
    $html = '<div class="notification-item notification-' . $notification['type'] . ' mb-3">';
    $html .= '<div class="row align-items-center">';
    $html .= '<div class="col-md-1 text-center">';
    $html .= '<div class="notification-icon notification-' . $notification['type'] . '">';
    
    if ($notification['type'] === 'success') {
        $html .= '<i class="fas fa-check-circle fa-2x"></i>';
    } elseif ($notification['type'] === 'warning') {
        $html .= '<i class="fas fa-exclamation-triangle fa-2x"></i>';
    } elseif ($notification['type'] === 'error') {
        $html .= '<i class="fas fa-exclamation-circle fa-2x"></i>';
    } else {
        $html .= '<i class="fas fa-info-circle fa-2x"></i>';
    }
    
    $html .= '</div></div>';
    $html .= '<div class="col-md-8">';
    $html .= '<div class="notification-content">';
    $html .= '<h6 class="notification-title mb-2">';
    $html .= htmlspecialchars($notification['title']);
    if (!$notification['is_read']) {
        $html .= '<span class="badge bg-danger ms-2">New</span>';
    }
    $html .= '</h6>';
    $html .= '<p class="notification-message mb-2">' . htmlspecialchars($notification['message']) . '</p>';
    
    $problemTitle = $notification['problem_title'] ?? '';
    if ($problemTitle !== '') {
        $html .= '<div class="notification-case">';
        $html .= '<small class="text-muted">';
        $html .= '<i class="fas fa-file-medical"></i> <strong>Related Case:</strong> ' . htmlspecialchars($problemTitle);
        $html .= '</small></div>';
    }
    
    $html .= '</div></div>';
    $html .= '<div class="col-md-3 text-end">';
    $html .= '<div class="notification-meta">';
    $html .= '<div class="notification-time mb-2">';
    $html .= '<small class="text-muted"><i class="fas fa-clock"></i> ';
    
    $time_diff = time() - strtotime($notification['created_at']);
    if ($time_diff < 60) {
        $html .= "Just now";
    } elseif ($time_diff < 3600) {
        $html .= floor($time_diff / 60) . " minutes ago";
    } elseif ($time_diff < 86400) {
        $html .= floor($time_diff / 3600) . " hours ago";
    } else {
        $html .= date('M j, g:i A', strtotime($notification['created_at']));
    }
    
    $html .= '</small></div>';
    
    if ($notification['problem_id']) {
        $html .= '<div class="notification-actions">';
        $html .= '<div class="btn-group-vertical btn-group-sm">';
        $html .= '<a href="view_problem.php?id=' . $notification['problem_id'] . '" class="btn btn-outline-info btn-sm">';
        $html .= '<i class="fas fa-eye"></i> View Case</a>';
        $html .= '<a href="respond_case.php?id=' . $notification['problem_id'] . '" class="btn btn-success btn-sm">';
        $html .= '<i class="fas fa-stethoscope"></i> Respond</a>';
        $html .= '</div></div>';
    }
    
    $html .= '</div></div></div></div>';
    
    return $html;
}

$page_title = 'Medical Notifications - Village Health Connect';

// Get flash messages
$message = '';
$message_type = '';
if (isset($_SESSION['flash_message'])) {
    $message = $_SESSION['flash_message'];
    $message_type = $_SESSION['flash_type'];
    unset($_SESSION['flash_message']);
    unset($_SESSION['flash_type']);
}

// Filters
$type_filter = $_GET['type'] ?? '';
$status_filter = $_GET['status'] ?? '';
$search_query = isset($_GET['q']) ? trim((string)$_GET['q']) : '';

// Get all notifications for this doctor
$notifications = [];
$unread_count = 0;

try {
    $pdo = getDBConnection();

    $where = ['n.user_id = ?'];
    $params = [$_SESSION['user_id']];
    
    if ($type_filter !== '') {
        $where[] = 'n.type = ?';
        $params[] = $type_filter;
    }
    
    if ($status_filter === 'unread') {
        $where[] = 'n.is_read = 0';
    } elseif ($status_filter === 'read') {
        $where[] = 'n.is_read = 1';
    }
    
    if ($search_query !== '') {
        $where[] = '(n.title LIKE ? OR n.message LIKE ? OR p.title LIKE ?)';
        $like = '%' . $search_query . '%';
        array_push($params, $like, $like, $like);
    }

    $whereSql = 'WHERE ' . implode(' AND ', $where);

    // Determine available notification columns so queries stay portable
    $columnStmt = $pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'notifications'");
    $columnStmt->execute();
    $notificationColumns = array_map('strtolower', array_column($columnStmt->fetchAll(PDO::FETCH_ASSOC), 'COLUMN_NAME'));

    $selectColumns = ['n.id', 'n.user_id', 'n.title', 'n.message', 'n.type', 'n.created_at', 'n.problem_id', 'n.is_read'];
    $availableColumns = [];

    foreach ($selectColumns as $column) {
        $columnName = strtolower(str_replace('n.', '', $column));
        if (in_array($columnName, $notificationColumns, true)) {
            $availableColumns[] = $column;
        }
    }

    if (empty($availableColumns)) {
        $availableColumns[] = 'n.id';
    }

    $selectSql = implode(',', $availableColumns);

    // Get notifications using only available columns
    $stmt = $pdo->prepare("
        SELECT $selectSql, p.title as problem_title
        FROM notifications n
        LEFT JOIN problems p ON n.problem_id = p.id
        $whereSql
        ORDER BY n.created_at DESC
        LIMIT 200
    ");
    $stmt->execute($params);
    $notifications = $stmt->fetchAll();

    // Ensure required keys exist even if unavailable in DB
    foreach ($notifications as &$notification) {
        foreach (['title', 'message', 'type', 'created_at', 'problem_id', 'is_read'] as $expectedKey) {
            if (!array_key_exists($expectedKey, $notification)) {
                $notification[$expectedKey] = null;
            }
        }
    }
    unset($notification);

    // Get unread count only if column exists
    if (in_array('is_read', $notificationColumns, true)) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$_SESSION['user_id']]);
        $unread_count = $stmt->fetchColumn();
    }

} catch (Exception $e) {
    error_log("Notifications error: " . $e->getMessage());
    $notifications = [];
}

include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="dashboard-header mb-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1 class="mb-1 text-primary">
                            <i class="fas fa-bell"></i> Medical Notifications
                        </h1>
                        <p class="text-muted mb-0">
                            Case escalations, system alerts, and medical updates
                        </p>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="dashboard.php" class="btn btn-outline-primary">
                            <i class="fas fa-arrow-left"></i> Back to Dashboard
                        </a>
                        <button type="button" class="btn btn-outline-secondary" onclick="markAllRead()">
                            <i class="fas fa-check-double me-1"></i>Mark All Read
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
            <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'times-circle'; ?> me-2"></i>
            <?php echo htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Search and Filters -->
    <div class="row mb-3">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <label for="type" class="form-label">Type</label>
                            <select class="form-select" id="type" name="type">
                                <option value="">All Types</option>
                                <option value="info" <?php echo $type_filter==='info'?'selected':''; ?>>Info</option>
                                <option value="warning" <?php echo $type_filter==='warning'?'selected':''; ?>>Warning</option>
                                <option value="error" <?php echo $type_filter==='error'?'selected':''; ?>>Error</option>
                                <option value="success" <?php echo $type_filter==='success'?'selected':''; ?>>Success</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="">All Status</option>
                                <option value="unread" <?php echo $status_filter==='unread'?'selected':''; ?>>Unread</option>
                                <option value="read" <?php echo $status_filter==='read'?'selected':''; ?>>Read</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="q" class="form-label">Keyword</label>
                            <input type="text" class="form-control" id="q" name="q" placeholder="Search title, message, case" value="<?php echo htmlspecialchars($search_query, ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                        <div class="col-md-2 align-self-end">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-search me-1"></i>Search
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Notification Stats -->
    <div class="row mb-4">
        <div class="col-lg-3 col-md-6">
            <div class="stats-card bg-primary text-white">
                <div class="card-body text-center">
                    <div class="stats-number h2"><?php echo count($notifications); ?></div>
                    <div class="stats-label">Total Notifications</div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="stats-card bg-info text-white">
                <div class="card-body text-center">
                    <div class="stats-number h2">
                        <?php echo count(array_filter($notifications, function($n) { return $n['type'] === 'info'; })); ?>
                    </div>
                    <div class="stats-label">Information</div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="stats-card bg-success text-white">
                <div class="card-body text-center">
                    <div class="stats-number h2">
                        <?php echo count(array_filter($notifications, function($n) { return $n['type'] === 'success'; })); ?>
                    </div>
                    <div class="stats-label">Success</div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="stats-card bg-warning text-white">
                <div class="card-body text-center">
                    <div class="stats-number h2">
                        <?php echo count(array_filter($notifications, function($n) { return $n['type'] === 'warning'; })); ?>
                    </div>
                    <div class="stats-label">Warnings</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Notifications List -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow">
                <div class="card-header bg-gradient-primary text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-history"></i> 
                        Notification History
                        <span class="badge bg-light text-dark ms-2"><?php echo count($notifications); ?></span>
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($notifications)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-bell-slash fa-4x text-muted mb-3"></i>
                            <h4>No notifications yet</h4>
                            <p class="text-muted">You'll receive notifications here when cases are escalated to you or require your medical attention.</p>
                            <a href="dashboard.php" class="btn btn-primary">
                                <i class="fas fa-home"></i> Back to Dashboard
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="notifications-timeline">
                            <?php foreach ($notifications as $notification): ?>
                                <div class="notification-item notification-<?php echo $notification['type']; ?> mb-3">
                                    <div class="row align-items-center">
                                        <div class="col-md-1 text-center">
                                            <div class="notification-icon notification-<?php echo $notification['type']; ?>">
                                                <?php if ($notification['type'] === 'success'): ?>
                                                    <i class="fas fa-check-circle fa-2x"></i>
                                                <?php elseif ($notification['type'] === 'warning'): ?>
                                                    <i class="fas fa-exclamation-triangle fa-2x"></i>
                                                <?php elseif ($notification['type'] === 'error'): ?>
                                                    <i class="fas fa-exclamation-circle fa-2x"></i>
                                                <?php else: ?>
                                                    <i class="fas fa-info-circle fa-2x"></i>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="col-md-8">
                                            <div class="notification-content">
                                                <h6 class="notification-title mb-2">
                                                    <?php echo htmlspecialchars($notification['title']); ?>
                                                    <?php if (!$notification['is_read'] || (time() - strtotime($notification['created_at']) < 3600)): ?>
                                                        <span class="badge bg-danger ms-2">New</span>
                                                    <?php endif; ?>
                                                </h6>
                                                <p class="notification-message mb-2">
                                                    <?php echo htmlspecialchars($notification['message']); ?>
                                                </p>
                                                <?php $problemTitle = $notification['problem_title'] ?? ''; ?>
                                                <?php if ($problemTitle !== ''): ?>
                                                    <div class="notification-case">
                                                        <small class="text-muted">
                                                            <i class="fas fa-file-medical"></i> 
                                                            <strong>Related Case:</strong> <?php echo htmlspecialchars($problemTitle); ?>
                                                        </small>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="col-md-3 text-end">
                                            <div class="notification-meta">
                                                <div class="notification-time mb-2">
                                                    <small class="text-muted">
                                                        <i class="fas fa-clock"></i>
                                                        <?php 
                                                        $time_diff = time() - strtotime($notification['created_at']);
                                                        if ($time_diff < 60) {
                                                            echo "Just now";
                                                        } elseif ($time_diff < 3600) {
                                                            echo floor($time_diff / 60) . " minutes ago";
                                                        } elseif ($time_diff < 86400) {
                                                            echo floor($time_diff / 3600) . " hours ago";
                                                        } else {
                                                            echo date('M j, g:i A', strtotime($notification['created_at']));
                                                        }
                                                        ?>
                                                    </small>
                                                </div>

                                                <?php if ($notification['problem_id']): ?>
                                                    <div class="notification-actions">
                                                        <div class="btn-group-vertical btn-group-sm">
                                                            <a href="view_problem.php?id=<?php echo $notification['problem_id']; ?>" 
                                                               class="btn btn-outline-info btn-sm">
                                                                <i class="fas fa-eye"></i> View Case
                                                            </a>

                                                            <?php
                                                            // Check if this case still needs response
                                                            try {
                                                                $pdo = getDBConnection();
                                                                $stmt = $pdo->prepare("SELECT status FROM problems WHERE id = ? AND status = 'escalated'");
                                                                $stmt->execute([$notification['problem_id']]);
                                                                $case_status = $stmt->fetchColumn();

                                                                if ($case_status):
                                                            ?>
                                                                <a href="respond_case.php?id=<?php echo $notification['problem_id']; ?>" 
                                                                   class="btn btn-success btn-sm">
                                                                    <i class="fas fa-stethoscope"></i> Respond
                                                                </a>
                                                            <?php 
                                                                endif;
                                                            } catch (Exception $e) {
                                                                // Ignore error
                                                            }
                                                            ?>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <?php if (count($notifications) >= 50): ?>
                            <div class="text-center mt-4">
                                <p class="text-muted">Showing latest 50 notifications</p>
                                <button class="btn btn-outline-primary" onclick="loadMoreNotifications()">
                                    <i class="fas fa-plus"></i> Load More
                                </button>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card bg-light">
                <div class="card-body text-center">
                    <h6 class="mb-3">
                        <i class="fas fa-bolt text-primary"></i> Quick Actions
                    </h6>
                    <div class="btn-group-responsive">
                        <a href="all_escalated.php" class="btn btn-primary me-2 mb-2">
                            <i class="fas fa-list"></i> View All Cases
                        </a>
                        <a href="my_responses.php" class="btn btn-success me-2 mb-2">
                            <i class="fas fa-history"></i> My Response History
                        </a>
                        <a href="dashboard.php" class="btn btn-info me-2 mb-2">
                            <i class="fas fa-home"></i> Dashboard
                        </a>
                        <button class="btn btn-outline-secondary mb-2" onclick="markAllAsRead()">
                            <i class="fas fa-check-double"></i> Mark All Read
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.stats-card {
    border-radius: 10px;
    transition: all 0.3s ease;
    border: none;
}

.stats-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 15px rgba(0,0,0,0.1);
}

.bg-gradient-primary {
    background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
}

.notification-item {
    border: 1px solid #e0e0e0;
    border-radius: 12px;
    padding: 20px;
    background: white;
    transition: all 0.3s ease;
    position: relative;
}

.notification-item:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    transform: translateX(5px);
}

.notification-item.notification-success {
    border-left: 5px solid #28a745;
    background: linear-gradient(135deg, #f8fff8 0%, white 100%);
}

.notification-item.notification-info {
    border-left: 5px solid #17a2b8;
    background: linear-gradient(135deg, #f0f8ff 0%, white 100%);
}

.notification-item.notification-warning {
    border-left: 5px solid #ffc107;
    background: linear-gradient(135deg, #fffdf0 0%, white 100%);
}

.notification-item.notification-error {
    border-left: 5px solid #dc3545;
    background: linear-gradient(135deg, #fff5f5 0%, white 100%);
}

.notification-icon {
    padding: 15px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 60px;
    height: 60px;
}

.notification-icon.notification-success {
    background: #28a745;
    color: white;
}

.notification-icon.notification-info {
    background: #17a2b8;
    color: white;
}

.notification-icon.notification-warning {
    background: #ffc107;
    color: #212529;
}

.notification-icon.notification-error {
    background: #dc3545;
    color: white;
}

.notification-title {
    color: #495057;
    font-weight: 600;
    font-size: 1.1rem;
}

.notification-message {
    color: #6c757d;
    line-height: 1.5;
    font-size: 0.95rem;
}

.notification-case {
    background: #f8f9fa;
    border-radius: 6px;
    padding: 8px 12px;
    border: 1px solid #e9ecef;
}

.notification-meta {
    text-align: center;
}

.notification-time {
    background: #f8f9fa;
    border-radius: 20px;
    padding: 4px 12px;
    display: inline-block;
}

.notifications-timeline {
    position: relative;
}

.notifications-timeline::before {
    content: '';
    position: absolute;
    left: 30px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: linear-gradient(180deg, #007bff 0%, #17a2b8 50%, #28a745 100%);
    opacity: 0.3;
}

.btn-group-responsive .btn {
    margin: 2px;
}

@media (max-width: 768px) {
    .notification-item {
        padding: 15px;
    }

    .notification-icon {
        width: 40px;
        height: 40px;
        padding: 8px;
    }

    .notification-icon i {
        font-size: 1.2rem;
    }

    .notifications-timeline::before {
        left: 20px;
    }
}

.card {
    transition: transform 0.2s ease;
}

.card:hover {
    transform: translateY(-2px);
}
</style>

<script>
function markAllAsRead() {
    // Show loading state
    const button = event.target.closest('button');
    const originalText = button.innerHTML;
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Marking as read...';
    button.disabled = true;

    // Make AJAX request to mark all as read
    fetch('notifications.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=mark_all_read'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Visual feedback
            const notifications = document.querySelectorAll('.notification-item');
            notifications.forEach(item => {
                const newBadge = item.querySelector('.badge.bg-danger');
                if (newBadge) {
                    newBadge.remove();
                }
            });

            showToast('All notifications marked as read!', 'success');
        } else {
            showToast('Error: ' + data.message, 'danger');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Error marking notifications as read', 'danger');
    })
    .finally(() => {
        // Reset button
        button.innerHTML = originalText;
        button.disabled = false;
    });
}

function markAllRead() {
    if (!confirm('Mark all notifications as read?')) {
        return;
    }

    // Make AJAX request to mark all as read
    fetch('notifications.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=mark_all_read'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            if (data.reload) {
                location.reload();
            }
        } else {
            alert('Error: ' + (data.message || 'Failed to mark notifications as read'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error marking notifications as read');
    });
}

function loadMoreNotifications() {
    const button = event.target.closest('button');
    const originalText = button.innerHTML;
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading more...';
    button.disabled = true;

    // Make AJAX request to load more notifications
    fetch('notifications.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=load_more'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.notifications) {
            // Append new notifications to the timeline
            const timeline = document.querySelector('.notifications-timeline');
            if (timeline && data.notifications) {
                timeline.insertAdjacentHTML('beforeend', data.notifications);
                showToast('More notifications loaded!', 'success');
            }
        } else {
            showToast('No more notifications to load', 'info');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Error loading more notifications', 'danger');
    })
    .finally(() => {
        // Reset button
        button.innerHTML = originalText;
        button.disabled = false;
    });
}

// Auto-refresh notifications every 5 minutes
setInterval(function() {
    if (document.visibilityState === 'visible') {
        // Reload to get latest notifications
        location.reload();
    }
}, 300000);

// Add slide-in animation
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
`;
document.head.appendChild(style);

// Show toast notification
function showToast(message, type = 'info', duration = 5000) {
    const toast = document.createElement('div');
    toast.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
    toast.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
    toast.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    document.body.appendChild(toast);
    
    // Auto remove
    setTimeout(() => {
        if (toast.parentNode) {
            toast.remove();
        }
    }, duration);
}

// Mark page as visited (for analytics)
document.addEventListener('DOMContentLoaded', function() {
    // Could send analytics data here
    console.log('Notifications page loaded with <?php echo count($notifications); ?> notifications');
    
    // Add click handlers for notification actions
    const notificationActions = document.querySelectorAll('.notification-actions .btn');
    notificationActions.forEach(btn => {
        btn.addEventListener('click', function(e) {
            // Add visual feedback
            this.style.transform = 'scale(0.95)';
            setTimeout(() => {
                this.style.transform = '';
            }, 150);
        });
    });
});
</script>

<?php include '../includes/footer.php'; ?>