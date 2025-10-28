<?php
require_once '../includes/config.php';
requireRole('avms');

$page_title = 'Manage Problems - Village Health Connect';

try {
    $pdo = getDBConnection();

    // Get all problems in the system that AVMS can see
    $stmt = $pdo->prepare("
        SELECT p.*, 
               villager.name as villager_name, villager.phone as villager_phone, villager.village as villager_village,
               avms.name as avms_name,
               doc.name as doctor_name
        FROM problems p 
        JOIN users villager ON p.villager_id = villager.id 
        LEFT JOIN users avms ON p.assigned_to = avms.id
        LEFT JOIN users doc ON p.escalated_to = doc.id
        ORDER BY 
            CASE p.priority 
                WHEN 'urgent' THEN 1 
                WHEN 'high' THEN 2 
                WHEN 'medium' THEN 3 
                WHEN 'low' THEN 4 
            END,
            p.created_at DESC
    ");
    $stmt->execute();
    $all_problems = $stmt->fetchAll();

    // Filter problems by status for display
    $filter = $_GET['filter'] ?? 'all';
    $search = $_GET['search'] ?? '';

    if ($filter !== 'all') {
        $all_problems = array_filter($all_problems, function($problem) use ($filter) {
            return $problem['status'] === $filter;
        });
    }

    if (!empty($search)) {
        $all_problems = array_filter($all_problems, function($problem) use ($search) {
            return stripos($problem['title'], $search) !== false || 
                   stripos($problem['description'], $search) !== false ||
                   stripos($problem['villager_name'], $search) !== false;
        });
    }

} catch (Exception $e) {
    error_log("Manage problems error: " . $e->getMessage());
    $all_problems = [];
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
                            <i class="fas fa-tasks text-primary"></i> Manage Problems
                        </h1>
                        <p class="text-muted mb-0">Overview of all problems in the system</p>
                    </div>
                    <div>
                        <a href="dashboard.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Dashboard
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters and Search -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-4">
                            <label for="search" class="form-label">Search Problems</label>
                            <input type="text" class="form-control" id="search" name="search" 
                                   value="<?php echo htmlspecialchars($search); ?>" 
                                   placeholder="Search by title, description, or villager name...">
                        </div>
                        <div class="col-md-3">
                            <label for="filter" class="form-label">Filter by Status</label>
                            <select class="form-select" id="filter" name="filter">
                                <option value="all" <?php echo $filter === 'all' ? 'selected' : ''; ?>>All Problems</option>
                                <option value="pending" <?php echo $filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="assigned" <?php echo $filter === 'assigned' ? 'selected' : ''; ?>>Assigned</option>
                                <option value="in_progress" <?php echo $filter === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                <option value="resolved" <?php echo $filter === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                                <option value="escalated" <?php echo $filter === 'escalated' ? 'selected' : ''; ?>>Escalated</option>
                            </select>
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary me-2">
                                <i class="fas fa-search"></i> Search
                            </button>
                            <a href="manage_problems.php" class="btn btn-outline-secondary">
                                <i class="fas fa-times"></i> Clear
                            </a>
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <a href="reports.php" class="btn btn-success w-100">
                                <i class="fas fa-download"></i> Export
                            </a>
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
                        <i class="fas fa-list-alt"></i> 
                        All Problems (<?php echo count($all_problems); ?> results)
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($all_problems)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-search fa-3x text-muted mb-3"></i>
                            <h5>No problems found</h5>
                            <p class="text-muted">
                                <?php if (!empty($search) || $filter !== 'all'): ?>
                                    No problems match your search criteria. Try adjusting your filters.
                                <?php else: ?>
                                    No problems have been reported yet.
                                <?php endif; ?>
                            </p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Problem</th>
                                        <th>Villager</th>
                                        <th>Priority</th>
                                        <th>Status</th>
                                        <th>Assigned To</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($all_problems as $problem): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($problem['title']); ?></strong><br>
                                            <small class="text-muted">
                                                <?php echo htmlspecialchars(substr($problem['description'], 0, 60)) . '...'; ?>
                                            </small>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($problem['villager_name']); ?><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($problem['villager_village']); ?></small>
                                        </td>
                                        <td>
                                            <span class="badge priority-<?php echo $problem['priority']; ?>">
                                                <?php echo ucfirst($problem['priority']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge status-<?php echo $problem['status']; ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $problem['status'])); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($problem['avms_name']): ?>
                                                <?php echo htmlspecialchars($problem['avms_name']); ?>
                                                <?php if ($problem['doctor_name']): ?>
                                                    <br><small class="text-info">Dr. <?php echo htmlspecialchars($problem['doctor_name']); ?></small>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="text-muted">Unassigned</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <small><?php echo formatDate($problem['created_at']); ?></small>
                                        </td>
                                        <td>
                                            <div class="btn-group">
                                                <a href="view_problem.php?id=<?php echo $problem['id']; ?>" 
                                                   class="btn btn-sm btn-outline-primary" title="View Details">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <?php if ($problem['assigned_to'] === null): ?>
                                                    <a href="assign_problem.php?id=<?php echo $problem['id']; ?>" 
                                                       class="btn btn-sm btn-success" title="Assign to Me">
                                                        <i class="fas fa-hand-paper"></i>
                                                    </a>
                                                <?php elseif ($problem['assigned_to'] == $_SESSION['user_id']): ?>
                                                    <a href="update_status.php?id=<?php echo $problem['id']; ?>" 
                                                       class="btn btn-sm btn-primary" title="Update Status">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                <?php endif; ?>
                                                <?php if ($problem['villager_phone']): ?>
                                                    <a href="tel:<?php echo $problem['villager_phone']; ?>" 
                                                       class="btn btn-sm btn-outline-success" title="Call Villager">
                                                        <i class="fas fa-phone"></i>
                                                    </a>
                                                <?php endif; ?>
                                            </div>
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