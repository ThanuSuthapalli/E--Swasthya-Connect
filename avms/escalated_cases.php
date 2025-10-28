<?php
require_once '../includes/config.php';
requireRole('avms');

$page_title = 'Escalated Cases - Village Health Connect';

try {
    $pdo = getDBConnection();

    // Get all escalated problems in the system
    $stmt = $pdo->prepare("
        SELECT p.*, 
               villager.name as villager_name, villager.phone as villager_phone, villager.village as villager_village,
               avms.name as avms_name,
               doc.name as doctor_name, doc.phone as doctor_phone
        FROM problems p 
        JOIN users villager ON p.villager_id = villager.id 
        LEFT JOIN users avms ON p.assigned_to = avms.id
        LEFT JOIN users doc ON p.escalated_to = doc.id
        WHERE p.status = 'escalated'
        ORDER BY 
            CASE p.priority 
                WHEN 'urgent' THEN 1 
                WHEN 'high' THEN 2 
                WHEN 'medium' THEN 3 
                WHEN 'low' THEN 4 
            END,
            p.updated_at ASC
    ");
    $stmt->execute();
    $escalated_cases = $stmt->fetchAll();

    // Get medical responses for escalated cases
    $stmt = $pdo->prepare("
        SELECT mr.*, p.id as problem_id, doc.name as doctor_name
        FROM medical_responses mr
        JOIN problems p ON mr.problem_id = p.id
        JOIN users doc ON mr.doctor_id = doc.id
        WHERE p.status = 'escalated'
        ORDER BY mr.created_at DESC
    ");
    $stmt->execute();
    $medical_responses = $stmt->fetchAll();

} catch (Exception $e) {
    error_log("Escalated cases error: " . $e->getMessage());
    $escalated_cases = [];
    $medical_responses = [];
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
                            <i class="fas fa-arrow-up text-danger"></i> Escalated Cases
                        </h1>
                        <p class="text-muted mb-0">Problems that have been escalated to doctors for medical attention</p>
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

    <!-- Statistics -->
    <div class="row mb-4">
        <div class="col-lg-3 col-md-6">
            <div class="stats-card">
                <div class="stats-number text-danger"><?php echo count($escalated_cases); ?></div>
                <div class="stats-label">
                    <i class="fas fa-arrow-up"></i> Total Escalated
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="stats-card">
                <div class="stats-number text-warning">
                    <?php echo count(array_filter($escalated_cases, function($case) { return $case['priority'] === 'urgent'; })); ?>
                </div>
                <div class="stats-label">
                    <i class="fas fa-exclamation-triangle"></i> Urgent Cases
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="stats-card">
                <div class="stats-number text-info"><?php echo count($medical_responses); ?></div>
                <div class="stats-label">
                    <i class="fas fa-comments"></i> Doctor Responses
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="stats-card">
                <div class="stats-number text-success">
                    <?php 
                    $my_escalated = count(array_filter($escalated_cases, function($case) { 
                        return $case['assigned_to'] == $_SESSION['user_id']; 
                    }));
                    echo $my_escalated;
                    ?>
                </div>
                <div class="stats-label">
                    <i class="fas fa-user-check"></i> My Escalated
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Escalated Cases List -->
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header bg-danger text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-arrow-up"></i> 
                        Cases Under Medical Review
                        <span class="badge bg-light text-dark ms-2"><?php echo count($escalated_cases); ?></span>
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($escalated_cases)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                            <h5>No escalated cases!</h5>
                            <p class="text-muted">All cases are being handled at the ANMS level. Great work!</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($escalated_cases as $case): ?>
                            <div class="problem-card priority-<?php echo $case['priority']; ?>">
                                <div class="row align-items-center">
                                    <div class="col-md-6">
                                        <h6 class="mb-2">
                                            <?php echo htmlspecialchars($case['title']); ?>
                                            <span class="badge priority-<?php echo $case['priority']; ?> ms-2">
                                                <?php echo ucfirst($case['priority']); ?>
                                            </span>
                                        </h6>
                                        <p class="mb-2"><?php echo htmlspecialchars(substr($case['description'], 0, 120)) . '...'; ?></p>
                                        <small class="text-muted">
                                            <strong>Patient:</strong> <?php echo htmlspecialchars($case['villager_name']); ?><br>
                                            <strong>ANMS:</strong> <?php echo htmlspecialchars($case['avms_name'] ?? 'Unknown'); ?><br>
                                            <strong>Doctor:</strong> <?php echo htmlspecialchars($case['doctor_name'] ?? 'Pending assignment'); ?><br>
                                            <strong>Escalated:</strong> <?php echo formatDate($case['updated_at']); ?>
                                        </small>
                                    </div>
                                    <div class="col-md-3 text-center">
                                        <?php if ($case['photo']): ?>
                                            
                                               
                                        <?php endif; ?>
                                        <div>
                                            <span class="badge bg-danger">Escalated</span>
                                        </div>
                                    </div>
                                    <div class="col-md-3 text-end">
                                        <div class="d-grid gap-2">
                                            <a href="view_problem.php?id=<?php echo $case['id']; ?>" 
                                               class="btn btn-outline-primary btn-sm">
                                                <i class="fas fa-eye"></i> View Details
                                            </a>

                                            <?php if ($case['villager_phone']): ?>
                                                <a href="tel:<?php echo $case['villager_phone']; ?>" 
                                                   class="btn btn-outline-success btn-sm">
                                                    <i class="fas fa-phone"></i> Call Patient
                                                </a>
                                            <?php endif; ?>

                                            <?php if ($case['doctor_phone']): ?>
                                                <a href="tel:<?php echo $case['doctor_phone']; ?>" 
                                                   class="btn btn-outline-info btn-sm">
                                                    <i class="fas fa-user-md"></i> Call Doctor
                                                </a>
                                            <?php endif; ?>

                                            <?php if ($case['assigned_to'] == $_SESSION['user_id']): ?>
                                                <small class="text-success">
                                                    <i class="fas fa-check"></i> Your case
                                                </small>
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

        <!-- Medical Responses Sidebar -->
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h6><i class="fas fa-comments text-info"></i> Recent Medical Responses</h6>
                </div>
                <div class="card-body">
                    <?php if (empty($medical_responses)): ?>
                        <p class="text-muted text-center">No medical responses yet</p>
                    <?php else: ?>
                        <?php foreach (array_slice($medical_responses, 0, 5) as $response): ?>
                            <div class="border-bottom pb-2 mb-2">
                                <h6 class="mb-1">Response from Dr. <?php echo htmlspecialchars($response['doctor_name']); ?></h6>
                                <p class="mb-1 small"><?php echo htmlspecialchars(substr($response['response'], 0, 80)) . '...'; ?></p>
                                <small class="text-muted">
                                    <i class="fas fa-calendar"></i> <?php echo formatDate($response['created_at']); ?>
                                </small>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Escalation Guidelines -->
            <div class="card mt-3">
                <div class="card-header">
                    <h6><i class="fas fa-lightbulb text-warning"></i> Escalation Guidelines</h6>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled mb-0">
                        <li class="mb-2">
                            <i class="fas fa-check text-success"></i> 
                            <small>Escalate when medical expertise is needed</small>
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-check text-success"></i> 
                            <small>Provide detailed symptoms and observations</small>
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-check text-success"></i> 
                            <small>Include photos when relevant</small>
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-check text-success"></i> 
                            <small>Follow up with villager after doctor response</small>
                        </li>
                        <li>
                            <i class="fas fa-check text-success"></i> 
                            <small>Coordinate visits if recommended by doctor</small>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="card mt-3">
                <div class="card-header">
                    <h6><i class="fas fa-bolt text-primary"></i> Quick Actions</h6>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="dashboard.php" class="btn btn-outline-primary btn-sm">
                            <i class="fas fa-home"></i> Back to Dashboard
                        </a>
                        <a href="my_assignments.php" class="btn btn-outline-info btn-sm">
                            <i class="fas fa-folder"></i> My Cases
                        </a>
                        <a href="manage_problems.php" class="btn btn-outline-secondary btn-sm">
                            <i class="fas fa-list"></i> All Problems
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>