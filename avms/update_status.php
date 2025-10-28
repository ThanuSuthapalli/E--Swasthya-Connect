<?php
require_once '../includes/config.php';
requireRole('avms');

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['problem_id']) && isset($_POST['ajax'])) {
    header('Content-Type: application/json');

    $problem_id = (int)$_POST['problem_id'];
    $new_status = sanitizeInput($_POST['status'] ?? '');
    $notes = sanitizeInput($_POST['notes'] ?? '');

    // Validate status
    $valid_statuses = ['assigned', 'in_progress', 'resolved', 'closed'];
    if (!in_array($new_status, $valid_statuses)) {
        echo json_encode(['success' => false, 'message' => 'Invalid status provided.']);
        exit;
    }

    try {
        // Check if user can update this problem
        if (!canAccessProblem($problem_id, $_SESSION['user_id'], 'avms')) {
            echo json_encode(['success' => false, 'message' => 'You do not have permission to update this problem.']);
            exit;
        }

        $result = updateProblemStatus($problem_id, $new_status, $_SESSION['user_id'], $notes);

        if ($result) {
            echo json_encode(['success' => true, 'message' => 'Status updated successfully!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update status.']);
        }
    } catch (Exception $e) {
        error_log("Status update error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'System error occurred.']);
    }
    exit;
}

// Handle regular GET request - show update form
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

} catch (Exception $e) {
    error_log("Update status page error: " . $e->getMessage());
    setMessage('error', 'System error occurred.');
    redirect(SITE_URL . '/avms/dashboard.php');
}

$page_title = 'Update Problem Status - Village Health Connect';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['problem_id'])) {
    $problem_id_post = (int)$_POST['problem_id'];
    $new_status = sanitizeInput($_POST['status'] ?? '');
    $notes = sanitizeInput($_POST['notes'] ?? '');

    if (!canAccessProblem($problem_id_post, $_SESSION['user_id'], 'avms')) {
        setMessage('error', 'You do not have permission to update this problem.');
    } else {
        $valid_statuses = ['assigned', 'in_progress', 'resolved', 'closed'];
        if (!in_array($new_status, $valid_statuses)) {
            setMessage('error', 'Invalid status provided.');
        } elseif (empty($notes)) {
            setMessage('error', 'Please provide update notes.');
        } else {
            $result = updateProblemStatus($problem_id_post, $new_status, $_SESSION['user_id'], $notes);

            if ($result) {
                setMessage('success', 'Problem status updated successfully!');
                redirect(SITE_URL . '/avms/dashboard.php');
            } else {
                setMessage('error', 'Failed to update problem status.');
            }
        }
    }
}

include '../includes/header.php';
?>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="form-container">
                <h2 class="mb-4">
                    <i class="fas fa-edit text-primary"></i> Update Problem Status
                </h2>

                <!-- Messages -->
                <?php 
                $error = getMessage('error');
                $success = getMessage('success');
                if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <strong>Error!</strong> <?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif;
                if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <strong>Success!</strong> <?php echo htmlspecialchars($success); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Problem Summary -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Problem Details</h5>
                    </div>
                    <div class="card-body">
                        <h6><?php echo htmlspecialchars($problem['title']); ?></h6>
                        <p class="text-muted"><?php echo htmlspecialchars($problem['description']); ?></p>
                        <small class="text-muted">
                            <strong>Reported by:</strong> <?php echo htmlspecialchars($problem['villager_name']); ?> |
                            <strong>Current Status:</strong> 
                            <span class="badge status-<?php echo $problem['status']; ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $problem['status'])); ?>
                            </span>
                        </small>
                    </div>
                </div>

                <form method="POST" class="needs-validation" novalidate>
                    <input type="hidden" name="problem_id" value="<?php echo $problem_id; ?>">
                    <div class="mb-3">
                        <label for="status" class="form-label">New Status <span class="text-danger">*</span></label>
                        <select class="form-select" id="status" name="status" required>
                            <option value="">Select New Status</option>
                            <option value="in_progress" <?php echo $problem['status'] == 'in_progress' ? 'selected' : ''; ?>>
                                In Progress - Working on the problem
                            </option>
                            <option value="resolved" <?php echo $problem['status'] == 'resolved' ? 'selected' : ''; ?>>
                                Resolved - Problem has been fixed locally
                            </option>
                        </select>
                        <div class="invalid-feedback">Please select a status.</div>
                    </div>

                    <div class="mb-4">
                        <label for="notes" class="form-label">Update Notes <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="notes" name="notes" rows="4" 
                                  placeholder="Describe what actions you have taken, current progress, or resolution details..."
                                  required></textarea>
                        <div class="form-text">Provide details about your actions and any follow-up needed</div>
                        <div class="invalid-feedback">Please provide update notes.</div>
                    </div>

                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <a href="<?php echo SITE_URL . '/avms/dashboard.php'; ?>" class="btn btn-secondary me-md-2">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Update Status
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>