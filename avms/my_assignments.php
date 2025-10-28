<?php
require_once '../includes/config.php';
requireRole('avms');

$page_title = 'My Cases - Village Health Connect';

try {
    $pdo = getDBConnection();

    // Get problems assigned to this AVMS officer
    $stmt = $pdo->prepare("
        SELECT p.*, 
               villager.name as villager_name, villager.phone as villager_phone, villager.village as villager_village,
               doc.name as doctor_name
        FROM problems p 
        JOIN users villager ON p.villager_id = villager.id 
        LEFT JOIN users doc ON p.escalated_to = doc.id
        WHERE p.assigned_to = ? 
        ORDER BY 
            CASE p.priority 
                WHEN 'urgent' THEN 1 
                WHEN 'high' THEN 2 
                WHEN 'medium' THEN 3 
                WHEN 'low' THEN 4 
            END,
            p.created_at DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $my_cases = $stmt->fetchAll();

    // Get statistics for my cases
    $stats = [];
    foreach (['assigned', 'in_progress', 'resolved', 'escalated'] as $status) {
        $count = count(array_filter($my_cases, function($case) use ($status) {
            return $case['status'] === $status;
        }));
        $stats[$status] = $count;
    }

} catch (Exception $e) {
    error_log("My assignments error: " . $e->getMessage());
    $my_cases = [];
    $stats = ['assigned' => 0, 'in_progress' => 0, 'resolved' => 0, 'escalated' => 0];
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
                            <i class="fas fa-folder-open text-primary"></i> My Assigned Cases
                        </h1>
                        <p class="text-muted mb-0">Problems assigned to you for resolution</p>
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

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-lg-3 col-md-6">
            <div class="stats-card">
                <div class="stats-number text-info"><?php echo $stats['assigned']; ?></div>
                <div class="stats-label">
                    <i class="fas fa-user-check"></i> Assigned
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="stats-card">
                <div class="stats-number text-primary"><?php echo $stats['in_progress']; ?></div>
                <div class="stats-label">
                    <i class="fas fa-cogs"></i> In Progress
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="stats-card">
                <div class="stats-number text-success"><?php echo $stats['resolved']; ?></div>
                <div class="stats-label">
                    <i class="fas fa-check-circle"></i> Resolved
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="stats-card">
                <div class="stats-number text-danger"><?php echo $stats['escalated']; ?></div>
                <div class="stats-label">
                    <i class="fas fa-arrow-up"></i> Escalated
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-tasks"></i> 
                        My Cases (<?php echo count($my_cases); ?>)
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($my_cases)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                            <h5>No assigned cases</h5>
                            <p class="text-muted">You haven't been assigned any problems yet. Check the unassigned problems on your dashboard.</p>
                            <a href="dashboard.php" class="btn btn-primary">
                                <i class="fas fa-home"></i> Go to Dashboard
                            </a>
                        </div>
                    <?php else: ?>
                        <?php foreach ($my_cases as $case): ?>
                            <div class="problem-card priority-<?php echo $case['priority']; ?>" data-status="<?php echo $case['status']; ?>">
                                <div class="row align-items-center">
                                    <div class="col-md-6">
                                        <h6 class="mb-2">
                                            <?php echo htmlspecialchars($case['title']); ?>
                                            <span class="badge priority-<?php echo $case['priority']; ?> ms-2">
                                                <?php echo ucfirst($case['priority']); ?> Priority
                                            </span>
                                        </h6>
                                        <p class="mb-2"><?php echo htmlspecialchars(substr($case['description'], 0, 120)) . '...'; ?></p>
                                        <small class="text-muted">
                                            <strong>Patient:</strong> <?php echo htmlspecialchars($case['villager_name']); ?><br>
                                            <strong>Location:</strong> <?php echo htmlspecialchars($case['villager_village'] ?? 'Not specified'); ?><br>
                                            <strong>Assigned:</strong> <?php echo formatDate($case['updated_at']); ?>
                                            <?php if ($case['doctor_name']): ?>
                                                <br><strong>Doctor:</strong> <?php echo htmlspecialchars($case['doctor_name']); ?>
                                            <?php endif; ?>
                                        </small>
                                    </div>
                                    <div class="col-md-3 text-center">
                                        <?php if ($case['photo']): ?>
                                            
                                        <?php endif; ?>
                                        <div>
                                            <span class="badge status-<?php echo $case['status']; ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $case['status'])); ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="col-md-3 text-end">
                                        <div class="d-grid gap-2">
                                            <a href="view_problem.php?id=<?php echo $case['id']; ?>" 
                                               class="btn btn-outline-primary btn-sm">
                                                <i class="fas fa-eye"></i> View Details
                                            </a>

                                            <?php if ($case['status'] !== 'resolved' && $case['status'] !== 'escalated'): ?>
                                                <a href="update_status.php?id=<?php echo $case['id']; ?>" 
                                                   class="btn btn-primary btn-sm">
                                                    <i class="fas fa-edit"></i> Update Status
                                                </a>

                                                <a href="escalate_problem.php?id=<?php echo $case['id']; ?>" 
                                                   class="btn btn-danger btn-sm">
                                                    <i class="fas fa-arrow-up"></i> Escalate to Doctor
                                                </a>
                                            <?php endif; ?>

                                            <?php if ($case['villager_phone']): ?>
                                                <a href="tel:<?php echo $case['villager_phone']; ?>" 
                                                   class="btn btn-success btn-sm">
                                                    <i class="fas fa-phone"></i> Call Patient
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>