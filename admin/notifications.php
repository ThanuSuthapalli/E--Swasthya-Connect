<?php
require_once '../includes/config.php';
requireRole('admin');

$page_title = 'Admin Notifications - Village Health Connect';

// Optional: mark all notifications as read
if (isset($_GET['action']) && $_GET['action'] === 'mark_all_read') {
    try {
        $pdo = getDBConnection();
        $pdo->exec("UPDATE notifications SET is_read = 1 WHERE is_read = 0");
        setMessage('success', 'All notifications marked as read.');
        redirect(SITE_URL . '/admin/notifications.php');
    } catch (Exception $e) {
        error_log('Admin mark all notifications read error: ' . $e->getMessage());
        setMessage('error', 'Failed to mark notifications as read.');
        redirect(SITE_URL . '/admin/notifications.php');
    }
}

// Filters
$type_filter = $_GET['type'] ?? '';
$role_filter = $_GET['role'] ?? '';
$search_query = isset($_GET['q']) ? trim((string)$_GET['q']) : '';

try {
    $pdo = getDBConnection();

    $where = [];
    $params = [];
    if ($type_filter !== '') {
        $where[] = 'n.type = ?';
        $params[] = $type_filter;
    }
    if ($role_filter !== '') {
        $where[] = 'u.role = ?';
        $params[] = $role_filter;
    }
    if ($search_query !== '') {
        // Search across notification title/message, recipient name, and problem title
        $where[] = '(n.title LIKE ? OR n.message LIKE ? OR u.name LIKE ? OR p.title LIKE ?)';
        $like = '%' . $search_query . '%';
        array_push($params, $like, $like, $like, $like);
    }

    $whereSql = !empty($where) ? ('WHERE ' . implode(' AND ', $where)) : '';

    $stmt = $pdo->prepare("
        SELECT n.*,
               u.name AS user_name, u.role AS user_role,
               p.title AS problem_title
        FROM notifications n
        LEFT JOIN users u ON n.user_id = u.id
        LEFT JOIN problems p ON n.problem_id = p.id
        $whereSql
        ORDER BY n.created_at DESC
        LIMIT 200
    ");
    $stmt->execute($params);
    $notifications = $stmt->fetchAll();
} catch (Exception $e) {
    error_log('Admin notifications fetch error: ' . $e->getMessage());
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
                            <i class="fas fa-bell text-primary"></i> Notifications
                        </h1>
                        <p class="text-muted mb-0">System-wide notifications (latest 200)</p>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="<?php echo SITE_URL . '/admin/dashboard.php'; ?>" class="btn btn-outline-primary">
                            <i class="fas fa-tachometer-alt me-1"></i>Back to Dashboard
                        </a>
                        <a href="?action=mark_all_read" class="btn btn-outline-secondary" onclick="return confirm('Mark all notifications as read?');">
                            <i class="fas fa-check-double me-1"></i>Mark All Read
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

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
                            <label for="role" class="form-label">Recipient Role</label>
                            <select class="form-select" id="role" name="role">
                                <option value="">All Roles</option>
                                <option value="villager" <?php echo $role_filter==='villager'?'selected':''; ?>>Villager</option>
                                <option value="avms" <?php echo $role_filter==='avms'?'selected':''; ?>>ANMS</option>
                                <option value="doctor" <?php echo $role_filter==='doctor'?'selected':''; ?>>Doctor</option>
                                <option value="admin" <?php echo $role_filter==='admin'?'selected':''; ?>>Admin</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="q" class="form-label">Keyword</label>
                            <input type="text" class="form-control" id="q" name="q" placeholder="Search title, message, recipient, problem" value="<?php echo htmlspecialchars($search_query, ENT_QUOTES, 'UTF-8'); ?>">
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
                    <h5 class="mb-0"><i class="fas fa-list"></i> All Notifications</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($notifications)): ?>
                        <p class="text-muted">No notifications found.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th>When</th>
                                        <th>Type</th>
                                        <th>Title</th>
                                        <th>Message</th>
                                        <th>Recipient</th>
                                        <th>Problem</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($notifications as $n): ?>
                                    <tr>
                                        <td>
                                            <small class="text-muted"><?php echo formatDate($n['created_at']); ?></small>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $n['type']==='error'?'danger':($n['type']==='warning'?'warning':($n['type']==='success'?'success':'info')); ?><?php echo $n['type']==='warning'?' text-dark':''; ?>">
                                                <?php echo ucfirst($n['type'] ?? 'info'); ?>
                                            </span>
                                        </td>
                                        <td><strong><?php echo htmlspecialchars($n['title'] ?? ''); ?></strong></td>
                                        <td><?php echo htmlspecialchars($n['message'] ?? ''); ?></td>
                                        <td>
                                            <?php echo htmlspecialchars($n['user_name'] ?? 'Unknown'); ?>
                                            <small class="text-muted">(<?php echo htmlspecialchars($n['user_role'] ?? 'n/a'); ?>)</small>
                                        </td>
                                        <td>
                                            <?php if (!empty($n['problem_id'])): ?>
                                                <a href="<?php echo SITE_URL . '/admin/view_problem.php?id=' . (int)$n['problem_id']; ?>" class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-eye me-1"></i><?php echo htmlspecialchars($n['problem_title'] ?? ('#' . (int)$n['problem_id'])); ?>
                                                </a>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($n['is_read'])): ?>
                                                <span class="badge bg-secondary">Read</span>
                                            <?php else: ?>
                                                <span class="badge bg-primary">Unread</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($n['problem_id'])): ?>
                                                <a href="<?php echo SITE_URL . '/admin/view_problem.php?id=' . (int)$n['problem_id']; ?>" class="btn btn-sm btn-outline-info">
                                                    <i class="fas fa-external-link-alt"></i>
                                                </a>
                                            <?php else: ?>
                                                <button class="btn btn-sm btn-outline-secondary" disabled>-</button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>


