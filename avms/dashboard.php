<?php
require_once '../includes/config.php';
requireRole('avms');

$page_title = 'ANMS Dashboard - E-Swasthya Connect';

try {
    $pdo = getDBConnection();

    // Get all problems that need attention (unassigned + assigned to this AVMS)
    $stmt = $pdo->prepare("
        SELECT p.*, u.name as villager_name, u.phone as villager_phone, u.village as villager_village
        FROM problems p 
        JOIN users u ON p.villager_id = u.id 
        WHERE (p.assigned_to IS NULL OR p.assigned_to = ?) 
        AND p.status NOT IN ('resolved', 'completed', 'closed')
        ORDER BY 
            CASE p.priority 
                WHEN 'urgent' THEN 1 
                WHEN 'high' THEN 2 
                WHEN 'medium' THEN 3 
                WHEN 'low' THEN 4 
            END,
            p.created_at ASC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $all_problems = $stmt->fetchAll();

    // Separate unassigned and assigned problems
    $unassigned_problems = array_filter($all_problems, function($p) {
        return $p['assigned_to'] === null;
    });

    $my_problems = array_filter($all_problems, function($p) {
        return $p['assigned_to'] == $_SESSION['user_id'];
    });

    // Get statistics
    $stats = getProblemStats($_SESSION['user_id'], 'avms');
    $stats['total_pending'] = count($unassigned_problems);
    $stats['my_total'] = count($my_problems);

    // Get doctors for escalation
    $stmt = $pdo->prepare("SELECT id, name FROM users WHERE role = 'doctor' AND status = 'active'");
    $stmt->execute();
    $doctors = $stmt->fetchAll();

} catch (Exception $e) {
    error_log("AVMS dashboard error: " . $e->getMessage());
    $all_problems = $unassigned_problems = $my_problems = [];
    $stats = ['total_pending' => 0, 'my_total' => 0];
    $doctors = [];
}

include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="dashboard-header">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h1 class="mb-1">
                            <i class="fas fa-user-tie text-primary"></i> 
                            ANMS Dashboard
                        </h1>
                        <p class="text-muted mb-0">
                            Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?>! 
                            <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($_SESSION['user_village'] ?? 'All Areas'); ?>
                        </p>
                    </div>
                    <div class="col-md-4 text-md-end">
                        <div class="btn-group">
                            <a href="manage_problems.php" class="btn btn-outline-primary">
                                <i class="fas fa-list"></i> All Problems
                            </a>
                            <a href="reports.php" class="btn btn-primary">
                                <i class="fas fa-chart-bar"></i> Reports
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-lg-3 col-md-6">
            <div class="stats-card">
                <div class="stats-number text-warning"><?php echo $stats['total_pending']; ?></div>
                <div class="stats-label">
                    <i class="fas fa-clock"></i> Unassigned Problems
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="stats-card">
                <div class="stats-number text-info"><?php echo $stats['my_total']; ?></div>
                <div class="stats-label">
                    <i class="fas fa-user-check"></i> My Assigned Cases
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="stats-card">
                <div class="stats-number text-success"><?php echo $stats['resolved'] ?? 0; ?></div>
                <div class="stats-label">
                    <i class="fas fa-check-circle"></i> Resolved by Me
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="stats-card">
                <div class="stats-number text-danger"><?php echo $stats['escalated'] ?? 0; ?></div>
                <div class="stats-label">
                    <i class="fas fa-arrow-up"></i> Escalated to Doctors
                </div>
            </div>
        </div>
    </div>

    <!-- Urgent Problems Alert -->
    <?php 
    $urgent_problems = array_filter($all_problems, function($p) {
        return $p['priority'] === 'urgent' && in_array($p['status'], ['pending', 'assigned', 'in_progress']);
    });

    if (!empty($urgent_problems)): 
    ?>
        <div class="alert alert-danger mb-4">
            <h5><i class="fas fa-exclamation-triangle"></i> Urgent Problems Requiring Immediate Attention</h5>
            <?php foreach ($urgent_problems as $problem): ?>
                <div class="d-flex justify-content-between align-items-center mt-2 p-2 bg-white rounded">
                    <div>
                        <strong><?php echo htmlspecialchars($problem['title']); ?></strong> - 
                        <?php echo htmlspecialchars($problem['villager_name']); ?> 
                        (<?php echo htmlspecialchars($problem['villager_village']); ?>)
                    </div>
                    <div>
                        <?php if ($problem['assigned_to'] === null): ?>
                            <a href="assign_problem.php?id=<?php echo $problem['id']; ?>" 
                               class="btn btn-sm btn-danger">
                                <i class="fas fa-hand-paper"></i> Take Case
                            </a>
                        <?php else: ?>
                            <a href="view_problem.php?id=<?php echo $problem['id']; ?>" 
                               class="btn btn-sm btn-outline-danger">
                                <i class="fas fa-eye"></i> Manage
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Quick Actions -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">
                        <i class="fas fa-bolt text-primary"></i> Quick Actions
                    </h5>
                    <div class="row">
                        <div class="col-lg-3 col-md-6 mb-2">
                            <a href="#unassigned-problems" class="btn btn-warning w-100">
                                <i class="fas fa-clock"></i> View Unassigned (<?php echo count($unassigned_problems); ?>)
                            </a>
                        </div>
                        <div class="col-lg-3 col-md-6 mb-2">
                            <a href="my_assignments.php" class="btn btn-primary w-100">
                                <i class="fas fa-folder-open"></i> My Cases (<?php echo count($my_problems); ?>)
                            </a>
                        </div>
                        <div class="col-lg-3 col-md-6 mb-2">
                            <a href="escalated_cases.php" class="btn btn-info w-100">
                                <i class="fas fa-arrow-up"></i> Escalated Cases
                            </a>
                        </div>
                        <div class="col-lg-3 col-md-6 mb-2">
                            <a href="reports.php" class="btn btn-success w-100">
                                <i class="fas fa-chart-line"></i> Generate Report
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Unassigned Problems -->
        <div class="col-lg-8">
            <div class="card" id="unassigned-problems">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-clock text-warning"></i> 
                        Unassigned Problems Needing Attention
                        <span class="badge bg-warning text-dark ms-2"><?php echo count($unassigned_problems); ?></span>
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($unassigned_problems)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                            <h5>All problems are assigned!</h5>
                            <p class="text-muted">There are no unassigned problems at the moment. Great work!</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($unassigned_problems as $problem): ?>
                            <div class="problem-card priority-<?php echo $problem['priority']; ?>" data-status="<?php echo $problem['status']; ?>">
                                <div class="row align-items-center">
                                    <div class="col-md-6">
                                        <h6 class="mb-2">
                                            <?php echo htmlspecialchars($problem['title']); ?>
                                            <span class="badge priority-<?php echo $problem['priority']; ?> ms-2">
                                                <?php echo ucfirst($problem['priority']); ?>
                                            </span>
                                        </h6>
                                        <p class="text-muted mb-2">
                                            <?php echo htmlspecialchars(substr($problem['description'], 0, 120)) . '...'; ?>
                                        </p>
                                        <small class="text-muted">
                                            <i class="fas fa-user"></i> <?php echo htmlspecialchars($problem['villager_name']); ?>
                                            | <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($problem['villager_village'] ?? 'Location not specified'); ?>
                                            | <i class="fas fa-calendar"></i> <?php echo formatDate($problem['created_at']); ?>
                                        </small>
                                    </div>
                                    <div class="col-md-3 text-center">
                                        <?php if ($problem['photo']): ?>
                                            <img src="<?php echo htmlspecialchars(getUploadUrl($problem['photo'], '..')); ?>" 
                                                 class="problem-image mb-2" alt="Problem photo">
                                        <?php endif; ?>
                                        <div>
                                            <span class="badge bg-secondary">Unassigned</span>
                                        </div>
                                    </div>
                                    <div class="col-md-3 text-end">
                                        <div class="d-grid gap-2">
                                            <a href="assign_problem.php?id=<?php echo $problem['id']; ?>" 
                                               class="btn btn-success btn-sm">
                                                <i class="fas fa-hand-paper"></i> Assign to Me
                                            </a>
                                            <a href="view_problem.php?id=<?php echo $problem['id']; ?>" 
                                               class="btn btn-outline-primary btn-sm">
                                                <i class="fas fa-eye"></i> View Details
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- My Recent Cases & Quick Info -->
        <div class="col-lg-4">
            <!-- My Active Cases -->
            <div class="card mb-3">
                <div class="card-header">
                    <h6><i class="fas fa-folder-open"></i> My Active Cases</h6>
                </div>
                <div class="card-body">
                    <?php if (empty($my_problems)): ?>
                        <p class="text-muted text-center">No assigned cases yet</p>
                    <?php else: ?>
                        <?php foreach (array_slice($my_problems, 0, 5) as $problem): ?>
                            <div class="border-bottom pb-2 mb-2">
                                <h6 class="mb-1"><?php echo htmlspecialchars($problem['title']); ?></h6>
                                <small class="text-muted">
                                    <?php echo htmlspecialchars($problem['villager_name']); ?> - 
                                    <span class="badge status-<?php echo $problem['status']; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $problem['status'])); ?>
                                    </span>
                                </small>
                            </div>
                        <?php endforeach; ?>
                        <a href="my_assignments.php" class="btn btn-outline-primary btn-sm w-100 mt-2">
                            <i class="fas fa-list"></i> View All My Cases
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Available Doctors -->
            <div class="card mb-3">
                <div class="card-header">
                    <h6><i class="fas fa-user-md"></i> Available Doctors</h6>
                </div>
                <div class="card-body">
                    <?php if (empty($doctors)): ?>
                        <p class="text-muted text-center">No doctors available</p>
                    <?php else: ?>
                        <?php foreach ($doctors as $doctor): ?>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span><?php echo htmlspecialchars($doctor['name']); ?></span>
                                <span class="badge bg-success">Available</span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- AVMS Guidelines -->
            <div class="card">
                <div class="card-header">
                    <h6><i class="fas fa-clipboard-list"></i> ANMS Guidelines</h6>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled mb-0">
                        <li class="mb-2">
                            <i class="fas fa-check text-success"></i> 
                            <small>Assign problems to yourself promptly</small>
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-check text-success"></i> 
                            <small>Visit villagers when possible</small>
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-check text-success"></i> 
                            <small>Try to resolve locally first</small>
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-check text-success"></i> 
                            <small>Escalate to doctors when needed</small>
                        </li>
                        <li>
                            <i class="fas fa-check text-success"></i> 
                            <small>Keep villagers updated on progress</small>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Auto-refresh every 2 minutes for new problems
setInterval(function() {
    if (!document.hidden) {
        // Check for new problems via AJAX
        fetch('check_new_problems.php')
            .then(response => response.json())
            .then(data => {
                if (data.new_problems > 0) {
                    showToast(`${data.new_problems} new problem(s) reported!`, 'info');
                    // Optionally reload after a delay
                    setTimeout(() => location.reload(), 3000);
                }
            })
            .catch(error => console.log('Check failed:', error));
    }
}, 120000); // 2 minutes
</script>

<?php include '../includes/footer.php'; ?>