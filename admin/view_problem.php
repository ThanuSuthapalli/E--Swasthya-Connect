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
                                    $status = $update['new_status'] ?? 'pending';
                                    $statusClass = $status === 'resolved' ? 'success' : ($status === 'escalated' ? 'danger' : 'primary');
                                    $updatedByName = htmlspecialchars($update['updated_by_name'] ?? 'Unknown');
                                    $updatedByRole = ucfirst($update['updated_by_role'] ?? 'system');
                                    $notes = $update['notes'] ?? '';
                                ?>
                                <div class="timeline-item">
                                    <div class="timeline-marker bg-<?php echo $statusClass; ?>"></div>
                                    <div class="timeline-content">
                                        <div class="timeline-header">
                                            <strong>Status changed to: <?php echo ucfirst(str_replace('_', ' ', $status)); ?></strong>
                                            <small class="text-muted">
                                                by <?php echo $updatedByName; ?> (<?php echo $updatedByRole; ?>)
                                                - <?php echo formatDate($update['timestamp']); ?>
                                            </small>
                                        </div>
                                        <?php if (!empty($notes)): ?>
                                            <div class="timeline-body">
                                                <?php echo htmlspecialchars($notes); ?>
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
                        <button class="btn btn-outline-primary btn-sm" onclick="callUser('<?php echo $problem['villager_phone']; ?>', 'Villager')">
                            <i class="fas fa-phone"></i> Call Villager
                        </button>
                        <?php if ($problem['avms_phone'] && $_SESSION['user_role'] !== 'avms'): ?>
                            <button class="btn btn-outline-success btn-sm" onclick="callUser('<?php echo $problem['avms_phone']; ?>', 'ANMS Officer')">
                                <i class="fas fa-phone"></i> Call ANMS
                            </button>
                        <?php endif; ?>
                        <?php if ($problem['doctor_phone'] && $_SESSION['user_role'] === 'avms'): ?>
                            <button class="btn btn-outline-info btn-sm" onclick="callUser('<?php echo $problem['doctor_phone']; ?>', 'Doctor')">
                                <i class="fas fa-phone"></i> Call Doctor
                            </button>
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

<script>
// View Problem Functions
function openImageModal(imageSrc) {
    // Create modal for image viewing
    const modal = document.createElement('div');
    modal.className = 'modal fade';
    modal.id = 'imageModal';
    modal.innerHTML = `
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Problem Photo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <img src="${imageSrc}" class="img-fluid" alt="Problem photo">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <a href="${imageSrc}" class="btn btn-primary" download>Download</a>
                </div>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    const bootstrapModal = new bootstrap.Modal(modal);
    bootstrapModal.show();
    
    // Remove modal from DOM when hidden
    modal.addEventListener('hidden.bs.modal', function() {
        document.body.removeChild(modal);
    });
}

function callUser(phoneNumber, userType) {
    if (confirm(`Call ${userType} at ${phoneNumber}?`)) {
        showToast(`Initiating call to ${userType}...`, 'info', 2000);
        window.location.href = 'tel:' + phoneNumber;
    }
}

// Show toast notification
function showToast(message, type, duration) {
    type = type || 'info';
    duration = duration || 5000;
    
    const toast = document.createElement('div');
    toast.className = 'alert alert-' + type + ' alert-dismissible fade show position-fixed';
    toast.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
    toast.innerHTML = message + '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
    
    document.body.appendChild(toast);
    
    // Auto remove
    setTimeout(function() {
        if (toast.parentNode) {
            toast.remove();
        }
    }, duration);
}

// Add click handlers for action buttons
document.addEventListener('DOMContentLoaded', function() {
    // Add hover effects to action buttons
    const actionButtons = document.querySelectorAll('.btn');
    actionButtons.forEach(function(btn) {
        btn.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-1px)';
            this.style.transition = 'all 0.2s ease';
        });
        
        btn.addEventListener('mouseleave', function() {
            this.style.transform = '';
        });
    });
    
    // Add click effect to problem image
    const problemImage = document.querySelector('.problem-image');
    if (problemImage) {
        problemImage.style.cursor = 'pointer';
        problemImage.addEventListener('mouseenter', function() {
            this.style.transform = 'scale(1.02)';
            this.style.transition = 'transform 0.3s ease';
        });
        
        problemImage.addEventListener('mouseleave', function() {
            this.style.transform = '';
        });
    }
    
    // Add keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        // Escape key to close any open modals
        if (e.key === 'Escape') {
            const openModal = document.querySelector('.modal.show');
            if (openModal) {
                const modal = bootstrap.Modal.getInstance(openModal);
                if (modal) {
                    modal.hide();
                }
            }
        }
    });
});
</script>

<?php include '../includes/footer.php'; ?>