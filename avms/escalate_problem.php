<?php
require_once '../includes/config.php';
requireRole('avms');

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['problem_id'])) {
    header('Content-Type: application/json');

    $problem_id = (int)$_POST['problem_id'];
    $doctor_id = (int)($_POST['doctor_id'] ?? 0);
    $notes = sanitizeInput($_POST['notes'] ?? '');

    if ($doctor_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Please select a doctor.']);
        exit;
    }

    if (empty($notes)) {
        echo json_encode(['success' => false, 'message' => 'Please provide escalation notes.']);
        exit;
    }

    try {
        error_log("Attempting to escalate problem ID: $problem_id to doctor ID: $doctor_id");
        
        // Check if user can escalate this problem
        if (!canAccessProblem($problem_id, $_SESSION['user_id'], 'avms')) {
            error_log("Access denied for user {$_SESSION['user_id']} to escalate problem $problem_id");
            echo json_encode(['success' => false, 'message' => 'You do not have permission to escalate this problem.']);
            exit;
        }

        error_log("Access check passed, proceeding with escalation");
        $result = escalateProblemToDoctor($problem_id, $doctor_id, $_SESSION['user_id'], $notes);

        if ($result) {
            error_log("Successfully escalated problem $problem_id to doctor $doctor_id");
            echo json_encode(['success' => true, 'message' => 'Problem escalated to doctor successfully!']);
        } else {
            error_log("Failed to escalate problem $problem_id to doctor $doctor_id");
            echo json_encode(['success' => false, 'message' => 'Failed to escalate problem. Please try again or contact support.']);
        }
    } catch (Exception $e) {
        error_log("Escalation error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'System error occurred.']);
    }
    exit;
}

// Handle regular GET request - show escalation form
if (!isset($_GET['id'])) {
    setMessage('error', 'No problem specified.');
    redirect(SITE_URL . '/avms/dashboard.php');
}

$problem_id = (int)$_GET['id'];

try {
    $pdo = getDBConnection();

    // Get problem details
    $stmt = $pdo->prepare("
        SELECT p.*, u.name as villager_name, u.phone as villager_phone 
        FROM problems p 
        JOIN users u ON p.villager_id = u.id 
        WHERE p.id = ? AND p.assigned_to = ?
    ");
    $stmt->execute([$problem_id, $_SESSION['user_id']]);
    $problem = $stmt->fetch();

    if (!$problem) {
        setMessage('error', 'Problem not found or not assigned to you.');
        redirect(SITE_URL . '/avms/dashboard.php');
    }

    // Get available doctors
    $stmt = $pdo->prepare("SELECT id, name FROM users WHERE role = 'doctor' AND status = 'active'");
    $stmt->execute();
    $doctors = $stmt->fetchAll();

    if (empty($doctors)) {
        setMessage('error', 'No doctors are currently available for escalation.');
        redirect(SITE_URL . '/avms/dashboard.php');
    }

} catch (Exception $e) {
    error_log("Escalation page error: " . $e->getMessage());
    setMessage('error', 'System error occurred.');
    redirect(SITE_URL . '/avms/dashboard.php');
}

$page_title = 'Escalate to Doctor - Village Health Connect';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['problem_id'])) {
    $doctor_id = (int)$_POST['doctor_id'];
    $notes = sanitizeInput($_POST['notes'] ?? '');

    $result = escalateProblemToDoctor($problem_id, $doctor_id, $_SESSION['user_id'], $notes);

    if ($result) {
        setMessage('success', 'Problem escalated to doctor successfully!');
        redirect(SITE_URL . '/avms/dashboard.php');
    } else {
        setMessage('error', 'Failed to escalate problem to doctor.');
    }
}

include '../includes/header.php';
?>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="form-container">
                <h2 class="mb-4">
                    <i class="fas fa-arrow-up text-danger"></i> Escalate Problem to Doctor
                </h2>

                <!-- Problem Summary -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Problem Details</h5>
                    </div>
                    <div class="card-body">
                        <h6><?php echo htmlspecialchars($problem['title']); ?></h6>
                        <p class="text-muted"><?php echo htmlspecialchars($problem['description']); ?></p>
                        <?php if ($problem['photo']): ?>
                            <div class="mt-2">
                                <img src="<?php echo htmlspecialchars(getUploadUrl($problem['photo'], '..')); ?>" 
                                     class="image-preview" alt="Problem photo">
                            </div>
                        <?php endif; ?>
                        <small class="text-muted mt-2 d-block">
                            <strong>Reported by:</strong> <?php echo htmlspecialchars($problem['villager_name']); ?> |
                            <strong>Priority:</strong> 
                            <span class="badge priority-<?php echo $problem['priority']; ?>">
                                <?php echo ucfirst($problem['priority']); ?>
                            </span>
                        </small>
                    </div>
                </div>

                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    <strong>When to Escalate:</strong>
                    Escalate problems that require medical expertise, prescription medications, 
                    specialized treatment, or when the situation is beyond local ANMS capabilities.
                </div>

                <form method="POST" class="needs-validation" novalidate>
                    <div class="mb-3">
                        <label for="doctor_id" class="form-label">Select Doctor <span class="text-danger">*</span></label>
                        <select class="form-select" id="doctor_id" name="doctor_id" required>
                            <option value="">Choose a Doctor</option>
                            <?php foreach ($doctors as $doctor): ?>
                                <option value="<?php echo $doctor['id']; ?>">
                                    Dr. <?php echo htmlspecialchars($doctor['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="invalid-feedback">Please select a doctor.</div>
                    </div>

                    <div class="mb-4">
                        <label for="notes" class="form-label">Escalation Notes <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="notes" name="notes" rows="5" 
                                  placeholder="Provide detailed information for the doctor:
- What initial assessment did you make?
- What symptoms or conditions did you observe?
- Why is medical expertise needed?
- Any actions already taken?
- Urgency level and patient condition?"
                                  required></textarea>
                        <div class="form-text">Be thorough - doctors need complete information to provide proper guidance</div>
                        <div class="invalid-feedback">Please provide detailed escalation notes.</div>
                    </div>

                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <a href="<?php echo SITE_URL . '/avms/dashboard.php'; ?>" class="btn btn-secondary me-md-2">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-arrow-up"></i> Escalate to Doctor
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>