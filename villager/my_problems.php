<?php
require_once '../includes/config.php';
requireRole('villager');

$page_title = 'My Problems - Village Health Connect';

try {
    $pdo = getDBConnection();

    // Get all problems for this villager
    $stmt = $pdo->prepare("
        SELECT p.*, 
        avms.name as avms_name, avms.phone as avms_phone,
        doc.name as doctor_name
        FROM problems p 
        LEFT JOIN users avms ON p.assigned_to = avms.id 
        LEFT JOIN users doc ON p.escalated_to = doc.id
        WHERE p.villager_id = ? 
        ORDER BY p.created_at DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $problems = $stmt->fetchAll();

} catch (Exception $e) {
    error_log("My problems error: " . $e->getMessage());
    $problems = [];
}

include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="dashboard-header">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1 class="mb-1">My Problems</h1>
                        <p class="text-muted mb-0">All problems you have reported and their current status</p>
                    </div>
                    <div>
                        <a href="dashboard.php" class="btn btn-outline-primary">
                            <i class="fas fa-arrow-left"></i> Back to Dashboard
                        </a>
                        <a href="report_problem.php" class="btn btn-primary">
                            <i class="fas fa-plus-circle"></i> Report New Problem
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <?php if (empty($problems)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-info-circle fa-3x text-muted mb-3"></i>
                            <h5>No problems reported yet</h5>
                            <p class="text-muted">When you need help with health or community issues, report them here.</p>
                            <a href="report_problem.php" class="btn btn-primary">
                                <i class="fas fa-plus-circle"></i> Report Your First Problem
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="row">
                            <?php foreach ($problems as $problem): ?>
                                <div class="col-lg-6 col-xl-4 mb-4">
                                    <div class="problem-card priority-<?php echo $problem['priority']; ?>">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <h6 class="mb-0"><?php echo htmlspecialchars($problem['title']); ?></h6>
                                            <span class="badge status-<?php echo $problem['status']; ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $problem['status'])); ?>
                                            </span>
                                        </div>

                                        <p class="text-muted mb-3">
                                            <?php echo htmlspecialchars(substr($problem['description'], 0, 100)) . '...'; ?>
                                        </p>

                                        <?php if ($problem['photo']): ?>
                                            <div class="mb-3">
                                                <img src="<?php echo htmlspecialchars(getUploadUrl($problem['photo'], '..')); ?>" 
                                                     class="problem-image" alt="Problem photo">
                                            </div>
                                        <?php endif; ?>

                                        <div class="mb-3">
                                            <small class="text-muted">
                                                <i class="fas fa-calendar"></i> <?php echo formatDate($problem['created_at']); ?><br>
                                                <i class="fas fa-flag"></i> <?php echo ucfirst($problem['priority']); ?> Priority<br>
                                                <?php if ($problem['avms_name']): ?>
                                                    <i class="fas fa-user-tie"></i> Handled by: <?php echo htmlspecialchars($problem['avms_name']); ?><br>
                                                <?php endif; ?>
                                                <?php if ($problem['doctor_name']): ?>
                                                    <i class="fas fa-user-md"></i> Doctor: <?php echo htmlspecialchars($problem['doctor_name']); ?>
                                                <?php endif; ?>
                                            </small>
                                        </div>

                                        <div class="d-grid">
                                            <a href="view_problem.php?id=<?php echo $problem['id']; ?>" 
                                               class="btn btn-outline-primary btn-sm">
                                                <i class="fas fa-eye"></i> View Full Details
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>