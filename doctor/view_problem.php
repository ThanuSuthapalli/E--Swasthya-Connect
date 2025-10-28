<?php
require_once '../includes/config.php';
requireLogin();

if (!isset($_GET['id'])) {
    setMessage('error', 'No problem specified.');
    redirect(SITE_URL . '/' . $_SESSION['user_role'] . '/dashboard.php');
}

$problem_id = (int)$_GET['id'];

try {
    $pdo = getDBConnection();

    // Check access permission
    if (!canAccessProblem($problem_id, $_SESSION['user_id'], $_SESSION['user_role'])) {
        setMessage('error', 'You do not have permission to view this problem.');
        redirect(SITE_URL . '/' . $_SESSION['user_role'] . '/dashboard.php');
    }

    // Get problem details with all related information
    $stmt = $pdo->prepare("
        SELECT p.*, 
        villager.name as villager_name, villager.phone as villager_phone, villager.village as villager_village,
        avms.name as avms_name, avms.phone as avms_phone,
        doctor.name as doctor_name, doctor.phone as doctor_phone
        FROM problems p 
        JOIN users villager ON p.villager_id = villager.id 
        LEFT JOIN users avms ON p.assigned_to = avms.id
        LEFT JOIN users doctor ON p.escalated_to = doctor.id
        WHERE p.id = ?
    ");
    $stmt->execute([$problem_id]);
    $problem = $stmt->fetch();

    if (!$problem) {
        setMessage('error', 'Problem not found.');
        redirect(SITE_URL . '/' . $_SESSION['user_role'] . '/dashboard.php');
    }

    // Get problem update history
    $stmt = $pdo->prepare("
        SELECT pu.*, u.name as updated_by_name, u.role as updated_by_role
        FROM problem_updates pu
        JOIN users u ON pu.updated_by = u.id
        WHERE pu.problem_id = ?
        ORDER BY pu.timestamp DESC
    ");
    $stmt->execute([$problem_id]);
    $updates = $stmt->fetchAll();


    // Get medical responses from doctors
    $stmt = $pdo->prepare("\n        SELECT mr.*, u.name as doctor_name\n        FROM medical_responses mr\n        JOIN users u ON mr.doctor_id = u.id\n        WHERE mr.problem_id = ?\n        ORDER BY mr.created_at DESC\n    ");
    $stmt->execute([$problem_id]);
    $medical_responses = $stmt->fetchAll();

} catch (Exception $e) {
    error_log("View problem error: " . $e->getMessage());
    setMessage('error', 'System error occurred.');
    redirect(SITE_URL . '/' . $_SESSION['user_role'] . '/dashboard.php');
}

$page_title = 'View Problem Details - Village Health Connect';
include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="dashboard-header">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1 class="mb-1">Problem Details</h1>
                        <p class="text-muted mb-0">
                            Case ID: #<?php echo $problem['id']; ?> | 
                            Status: <span class="badge status-<?php echo $problem['status']; ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $problem['status'])); ?>
                            </span>
                        </p>
                    </div>
                    <div>
                        
                        <a href="<?php echo SITE_URL . '/' . $_SESSION['user_role'] . '/dashboard.php'; ?>" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Dashboard
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Problem Details -->
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-info-circle"></i> Problem Information
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-8">
                            <h4 class="mb-3">
                                <?php echo htmlspecialchars($problem['title']); ?>
                                <span class="badge priority-<?php echo $problem['priority']; ?> ms-2">
                                    <?php echo ucfirst($problem['priority']); ?> Priority
                                </span>
                            </h4>
                            <p class="lead"><?php echo htmlspecialchars($problem['description']); ?></p>

                            <div class="row mt-4">
                                <div class="col-sm-6">
                                    <strong>Category:</strong> <?php echo ucfirst($problem['category']); ?><br>
                                    <strong>Reported:</strong> <?php echo formatDate($problem['created_at']); ?><br>
                                    <strong>Last Updated:</strong> <?php echo formatDate($problem['updated_at']); ?>
                                </div>
                                <div class="col-sm-6">
                                    <?php if ($problem['location']): ?>
                                        <strong>Location:</strong> <?php echo htmlspecialchars($problem['location']); ?><br>
                                    <?php endif; ?>
                                    <?php if ($problem['resolved_at']): ?>
                                        <strong>Resolved:</strong> <?php echo formatDate($problem['resolved_at']); ?><br>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 text-center">
                            <?php if ($problem['photo']): ?>
                                <img src="<?php echo htmlspecialchars(getUploadUrl($problem['photo'], '..')); ?>" 
                                     class="img-fluid rounded mb-3" alt="Problem photo" 
                                     style="max-height: 200px;">
                            <?php else: ?>
                                <div class="bg-light p-4 rounded">
                                    <i class="fas fa-image fa-3x text-muted"></i>
                                    <p class="text-muted mt-2">No photo attached</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Medical Responses from Doctors -->
            <?php if (!empty($medical_responses)): ?>
                <div class="card mb-4">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-stethoscope me-2"></i>Medical Consultation & Advice
                            <span class="badge bg-light text-dark ms-2"><?php echo count($medical_responses); ?> Response(s)</span>
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php foreach ($medical_responses as $response): ?>
                            <div class="medical-response-item mb-4 p-3 border rounded <?php echo $response['urgency_level'] === 'critical' ? 'border-danger' : 'border-success'; ?>">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <div>
                                        <h6 class="mb-1">
                                            <i class="fas fa-user-md text-primary"></i> 
                                            Dr. <?php echo htmlspecialchars($response['doctor_name']); ?>
                                        </h6>
                                        <small class="text-muted">
                                            <i class="fas fa-clock"></i> <?php echo formatDate($response['created_at']); ?>
                                        </small>
                                    </div>
                                    <div>
                                        <span class="badge bg-<?php 
                                            echo $response['urgency_level'] === 'critical' ? 'danger' : 
                                                ($response['urgency_level'] === 'high' ? 'warning' : 
                                                ($response['urgency_level'] === 'medium' ? 'info' : 'secondary')); 
                                        ?>">
                                            <?php echo ucfirst($response['urgency_level']); ?> Priority
                                        </span>
                                        <?php if (!empty($response['follow_up_required'])): ?>
                                            <span class="badge bg-warning text-dark ms-1">
                                                <i class="fas fa-redo"></i> Follow-up Required
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="medical-response-content">
                                    <div class="mb-3">
                                        <strong class="text-primary"><i class="fas fa-notes-medical me-1"></i>Medical Assessment & Instructions:</strong>
                                        <div class="mt-2 p-3 bg-light rounded">
                                            <?php echo nl2br(htmlspecialchars($response['response'])); ?>
                                        </div>
                                    </div>
                                    
                                    <?php if (!empty($response['recommendations'])): ?>
                                        <div class="mb-2">
                                            <strong class="text-success"><i class="fas fa-prescription me-1"></i>Treatment Recommendations:</strong>
                                            <div class="mt-2 p-3 bg-light rounded">
                                                <?php echo nl2br(htmlspecialchars($response['recommendations'])); ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php if ($response !== end($medical_responses)): ?>
                                <hr class="my-3">
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Action Buttons for AVMS -->
            <?php if ($_SESSION['user_role'] === 'avms' && $problem['assigned_to'] == $_SESSION['user_id']): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-tools"></i> Actions</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <a href="update_status.php?id=<?php echo $problem['id']; ?>" class="btn btn-primary w-100 mb-2">
                                    <i class="fas fa-edit"></i> Update Status
                                </a>
                            </div>
                            <div class="col-md-4">
                                <a href="escalate_problem.php?id=<?php echo $problem['id']; ?>" class="btn btn-danger w-100 mb-2">
                                    <i class="fas fa-arrow-up"></i> Escalate to Doctor
                                </a>
                            </div>
                            <div class="col-md-4">
                                <a href="tel:<?php echo $problem['villager_phone']; ?>" class="btn btn-success w-100 mb-2">
                                    <i class="fas fa-phone"></i> Call Villager
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Problem History -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-history"></i> Problem History</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($updates)): ?>
                        <p class="text-muted">No updates yet.</p>
                    <?php else: ?>
                        <div class="timeline">
                            <?php foreach ($updates as $update): ?>
                                <?php 
                                    // Support multiple schemas: old_status/new_status or update_type/new_value
                                    $statusRaw = isset($update['new_status']) ? $update['new_status'] : (isset($update['new_value']) ? $update['new_value'] : 'updated');
                                    $statusClass = 'primary';
                                    if ($statusRaw === 'resolved') { $statusClass = 'success'; }
                                    elseif ($statusRaw === 'escalated' || $statusRaw === 'response_added') { $statusClass = 'danger'; }
                                    $statusLabel = ucfirst(str_replace('_', ' ', $statusRaw));
                                ?>
                                <div class="timeline-item">
                                    <div class="timeline-marker bg-<?php echo $statusClass; ?>"></div>
                                    <div class="timeline-content">
                                        <div class="timeline-header">
                                            <strong>
                                                <?php if (isset($update['new_status'])): ?>
                                                    Status changed to: <?php echo $statusLabel; ?>
                                                <?php elseif (isset($update['update_type'])): ?>
                                                    <?php echo ucfirst(str_replace('_', ' ', $update['update_type'])); ?>: <?php echo $statusLabel; ?>
                                                <?php else: ?>
                                                    Update: <?php echo $statusLabel; ?>
                                                <?php endif; ?>
                                            </strong>
                                            <small class="text-muted">
                                                by <?php echo htmlspecialchars($update['updated_by_name']); ?> (<?php echo ucfirst($update['updated_by_role']); ?>)
                                                - <?php echo isset($update['timestamp']) ? formatDate($update['timestamp']) : ''; ?>
                                            </small>
                                        </div>
                                        <?php if (!empty($update['notes'])): ?>
                                            <div class="timeline-body">
                                                <?php echo htmlspecialchars($update['notes']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            

                
        </div>

        <!-- Sidebar Information -->
        <div class="col-lg-4">
            <!-- People Involved -->
            <div class="card mb-3">
                <div class="card-header">
                    <h6><i class="fas fa-users"></i> People Involved</h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <strong><i class="fas fa-user text-primary"></i> Villager:</strong><br>
                        <?php echo htmlspecialchars($problem['villager_name']); ?><br>
                        <small class="text-muted">
                            <i class="fas fa-phone"></i> <?php echo htmlspecialchars($problem['villager_phone']); ?><br>
                            <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($problem['villager_village']); ?>
                        </small>
                    </div>

                    <?php if ($problem['avms_name']): ?>
                        <div class="mb-3">
                            <strong><i class="fas fa-user-tie text-success"></i> ANMS Officer:</strong><br>
                            <?php echo htmlspecialchars($problem['avms_name']); ?><br>
                            <small class="text-muted">
                                <i class="fas fa-phone"></i> <?php echo htmlspecialchars($problem['avms_phone']); ?>
                            </small>
                        </div>
                    <?php endif; ?>

                    <?php if ($problem['doctor_name']): ?>
                        <div class="mb-3">
                            <strong><i class="fas fa-user-md text-info"></i> Doctor:</strong><br>
                            Dr. <?php echo htmlspecialchars($problem['doctor_name']); ?><br>
                            <small class="text-muted">
                                <i class="fas fa-phone"></i> <?php echo htmlspecialchars($problem['doctor_phone']); ?>
                            </small>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Quick Contact -->
            <div class="card">
                <div class="card-header">
                    <h6><i class="fas fa-phone"></i> Quick Contact</h6>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="tel:<?php echo $problem['villager_phone']; ?>" class="btn btn-outline-primary btn-sm">
                            <i class="fas fa-phone"></i> Call Villager
                        </a>
                        <?php if ($problem['avms_phone'] && $_SESSION['user_role'] !== 'avms'): ?>
                            <a href="tel:<?php echo $problem['avms_phone']; ?>" class="btn btn-outline-success btn-sm">
                                <i class="fas fa-phone"></i> Call ANMS
                            </a>
                        <?php endif; ?>
                        <?php if ($problem['doctor_phone'] && $_SESSION['user_role'] === 'avms'): ?>
                            <a href="tel:<?php echo $problem['doctor_phone']; ?>" class="btn btn-outline-info btn-sm">
                                <i class="fas fa-phone"></i> Call Doctor
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.timeline {
    position: relative;
    padding-left: 30px;
}

.timeline::before {
    content: '';
    position: absolute;
    left: 15px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: #e9ecef;
}

.timeline-item {
    position: relative;
    margin-bottom: 25px;
}

.timeline-marker {
    position: absolute;
    left: -40px;
    top: 5px;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    border: 2px solid white;
}

.timeline-content {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 8px;
    border-left: 3px solid #007bff;
}

.timeline-header {
    margin-bottom: 8px;
}

.timeline-body {
    margin-top: 8px;
    font-size: 0.9em;
}
</style>

<?php include '../includes/footer.php'; ?>