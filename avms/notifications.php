<?php
require_once '../includes/config.php';
requireRole('avms');

$page_title = 'All Notifications - Village Health Connect';

// Handle mark all as read action
if (isset($_GET['action']) && $_GET['action'] === 'mark_all_read') {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$_SESSION['user_id']]);
        $_SESSION['flash_message'] = 'All notifications marked as read.';
        $_SESSION['flash_type'] = 'success';
        header('Location: notifications.php');
        exit;
    } catch (Exception $e) {
        error_log('ANMS mark all notifications read error: ' . $e->getMessage());
        $_SESSION['flash_message'] = 'Failed to mark notifications as read.';
        $_SESSION['flash_type'] = 'danger';
        header('Location: notifications.php');
        exit;
    }
}

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

    // Get all notifications for this AVMS user
    $stmt = $pdo->prepare("
        SELECT n.*, COALESCE(p.title, '') as problem_title, COALESCE(p.priority, '') as problem_priority
        FROM notifications n
        LEFT JOIN problems p ON n.problem_id = p.id
        $whereSql
        ORDER BY n.created_at DESC
        LIMIT 200
    ");
    $stmt->execute($params);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    error_log("AVMS notifications error: " . $e->getMessage());
    $notifications = [];
}

include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="dashboard-header">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1 class="mb-1">
                            <i class="fas fa-bell text-primary"></i> ANMS Notifications
                        </h1>
                        <p class="text-muted mb-0">System alerts, problem assignments, and important updates</p>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="dashboard.php" class="btn btn-outline-primary">
                            <i class="fas fa-arrow-left"></i> Back to Dashboard
                        </a>
                        <a href="?action=mark_all_read" class="btn btn-outline-secondary" onclick="return confirm('Mark all notifications as read?');">
                            <i class="fas fa-check-double me-1"></i>Mark All Read
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show mt-3" role="alert">
            <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'times-circle'; ?> me-2"></i>
            <?php echo htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row mb-3 mt-3">
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
                            <input type="text" class="form-control" id="q" name="q" placeholder="Search title, message, problem" value="<?php echo htmlspecialchars($search_query, ENT_QUOTES, 'UTF-8'); ?>">
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

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-list"></i> All Notifications
                        <span class="badge bg-primary ms-2"><?php echo count($notifications); ?></span>
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($notifications)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-bell-slash fa-3x text-muted mb-3"></i>
                            <h5>No notifications yet</h5>
                            <p class="text-muted">You'll receive notifications here when new problems are reported or require your attention.</p>
                        </div>
                    <?php else: ?>
                        <div class="row">
                            <?php foreach ($notifications as $notification): ?>
                                <div class="col-12 mb-3">
                                    <div class="card notification-card border-left-<?php 
                                        echo $notification['type'] === 'error' ? 'danger' : 
                                            ($notification['type'] === 'warning' ? 'warning' : 
                                            ($notification['type'] === 'success' ? 'success' : 'info')); 
                                    ?>">
                                        <div class="card-body">
                                            <div class="row align-items-center">
                                                <div class="col-md-8">
                                                    <h6 class="mb-2">
                                                        <i class="fas fa-<?php 
                                                            echo $notification['type'] === 'error' ? 'exclamation-circle text-danger' : 
                                                                ($notification['type'] === 'warning' ? 'exclamation-triangle text-warning' : 
                                                                ($notification['type'] === 'success' ? 'check-circle text-success' : 'info-circle text-info')); 
                                                        ?>"></i>
                                                        <?php echo htmlspecialchars($notification['title']); ?>

                                                        <?php if (!empty($notification['problem_priority'])): ?>
                                                            <span class="badge priority-<?php echo $notification['problem_priority']; ?> ms-2">
                                                                <?php echo ucfirst($notification['problem_priority']); ?>
                                                            </span>
                                                        <?php endif; ?>
                                                    </h6>
                                                    <p class="mb-2"><?php echo htmlspecialchars($notification['message']); ?></p>
                                                    <?php if (!empty($notification['problem_title'])): ?>
                                                        <small class="text-muted">
                                                            <i class="fas fa-link"></i> Related to: <?php echo htmlspecialchars($notification['problem_title']); ?>
                                                        </small>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="col-md-4 text-end">
                                                    <small class="text-muted d-block mb-2">
                                                        <i class="fas fa-calendar"></i> <?php echo formatDate($notification['created_at']); ?>
                                                    </small>

                                                    <?php if ($notification['problem_id']): ?>
                                                        <div class="btn-group">
                                                            <a href="view_problem.php?id=<?php echo $notification['problem_id']; ?>" 
                                                               class="btn btn-sm btn-outline-primary">
                                                                <i class="fas fa-eye"></i> View Problem
                                                            </a>

                                                            <?php
                                                            // Check if this problem needs assignment
                                                            try {
                                                                $stmt = $pdo->prepare("SELECT assigned_to FROM problems WHERE id = ?");
                                                                $stmt->execute([$notification['problem_id']]);
                                                                $problem_assignment = $stmt->fetch();

                                                                if ($problem_assignment && $problem_assignment['assigned_to'] === null):
                                                            ?>
                                                                <a href="assign_problem.php?id=<?php echo $notification['problem_id']; ?>" 
                                                                   class="btn btn-sm btn-success">
                                                                    <i class="fas fa-hand-paper"></i> Assign to Me
                                                                </a>
                                                            <?php 
                                                                endif;
                                                            } catch (Exception $e) {
                                                                // Ignore error, just don't show assign button
                                                            }
                                                            ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <?php if (count($notifications) > 10): ?>
                            <div class="text-center mt-4">
                                <p class="text-muted">Showing all <?php echo count($notifications); ?> notifications</p>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.notification-card {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.notification-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.border-left-info {
    border-left: 4px solid #17a2b8 !important;
}

.border-left-success {
    border-left: 4px solid #28a745 !important;
}

.border-left-warning {
    border-left: 4px solid #ffc107 !important;
}

.border-left-danger {
    border-left: 4px solid #dc3545 !important;
}
</style>

<?php include '../includes/footer.php'; ?>