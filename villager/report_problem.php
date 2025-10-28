<?php
require_once '../includes/config.php';
requireRole('villager');

$page_title = 'Report Problem - Village Health Connect';
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log("Form submitted - Starting processing");
    $title = sanitizeInput($_POST['title'] ?? '');
    $description = sanitizeInput($_POST['description'] ?? '');
    $priority = $_POST['priority'] ?? '';
    $location = sanitizeInput($_POST['location'] ?? '');
    
    error_log("Received data - Title: " . $title . ", Priority: " . $priority);

    // Validation
    if (empty($title) || empty($description) || empty($priority)) {
        $error = 'Please fill in all required fields.';
        error_log("Validation failed - Empty fields detected");
    } elseif (strlen($title) < 5) {
        $error = 'Problem title must be at least 5 characters long.';
        error_log("Validation failed - Title too short");
    } elseif (strlen($description) < 20) {
        $error = 'Please provide a detailed description (at least 20 characters).';
        error_log("Validation failed - Description too short");
    } else {
        try {
            error_log("Validation passed - Attempting database connection");
            $pdo = getDBConnection();

            // Handle file upload
            $photo_path = null;
            if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
                try {
                    $photo_path = handleFileUpload($_FILES['photo']);
                } catch (Exception $e) {
                    $error = $e->getMessage();
                }
            }

            if (empty($error)) {
                // Insert problem
                $sql = "INSERT INTO problems (villager_id, title, description, priority, location, photo, status) 
                    VALUES (?, ?, ?, ?, ?, ?, 'pending')";
                error_log("Preparing SQL: " . $sql);
                error_log("User ID: " . $_SESSION['user_id']);
                
                $stmt = $pdo->prepare($sql);
                $params = [
                    $_SESSION['user_id'],
                    $title,
                    $description,
                    $priority,
                    $location,
                    $photo_path
                ];
                error_log("Parameters: " . print_r($params, true));
                
                $result = $stmt->execute($params);

                if ($result) {
                    $problem_id = $pdo->lastInsertId();

                    // Create initial update record
                    $stmt = $pdo->prepare("
                        INSERT INTO problem_updates (problem_id, updated_by, old_status, new_status, notes) 
                        VALUES (?, ?, NULL, 'pending', 'Problem reported by villager')
                    ");
                    $stmt->execute([$problem_id, $_SESSION['user_id']]);

                    // Notify AVMS officers in the same village/area
                    $stmt = $pdo->prepare("
                        SELECT id FROM users 
                        WHERE role = 'avms' 
                        AND status = 'active' 
                        AND (village = ? OR village IS NULL OR village = '')
                    ");
                    $stmt->execute([$_SESSION['user_village'] ?? 'General']);
                    $avms_officers = $stmt->fetchAll();

                    foreach ($avms_officers as $officer) {
                        addNotification(
                            $officer['id'], 
                            $problem_id, 
                            'New Problem Reported', 
                            "New " . $priority . " priority problem reported by " . $_SESSION['user_name'] . ": " . $title,
                            $priority === 'urgent' ? 'error' : 'info'
                        );
                    }

                    $success = 'Problem reported successfully! ANMS officers have been notified and will review your case soon.';

                    // Clear form data
                    $_POST = [];
                } else {
                    $error = 'Failed to submit problem report. Please try again.';
                }
            }
        } catch (Exception $e) {
            error_log("Problem submission error: " . $e->getMessage());
            $error = 'System error occurred. Please try again later.';
        }
    }
}

include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="dashboard-header">
                <h1>
                    <i class="fas fa-plus-circle text-primary"></i> Report a Problem
                </h1>
                <p class="text-muted mb-0">
                    Describe your health or community issue and get connected with local assistance
                </p>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="form-container">
                <?php if ($error): ?>
                    <div class="alert alert-danger" role="alert">
                        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success" role="alert">
                        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                        <div class="mt-3">
                            <a href="<?php echo SITE_URL . '/villager/dashboard.php'; ?>" class="btn btn-success me-2">
                                <i class="fas fa-home"></i> Back to Dashboard
                            </a>
                            <a href="<?php echo SITE_URL . '/villager/my_problems.php'; ?>" class="btn btn-outline-success">
                                <i class="fas fa-list"></i> View My Problems
                            </a>
                        </div>
                    </div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data" class="needs-validation" novalidate id="reportForm">
                    <div class="row">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label for="title" class="form-label">Problem Title <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="title" name="title" 
                                       value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>" 
                                       placeholder="Brief, clear description of the problem" 
                                       minlength="5" maxlength="255" required>
                                <div class="form-text">Example: "High fever for 3 days" or "No clean water supply"</div>
                                <div class="invalid-feedback">Please provide a clear problem title (5-255 characters).</div>
                            </div>
                        </div>
                        
                        <div class="col-md-5">
                            <div class="mb-3">
                                <label for="priority" class="form-label">Priority Level <span class="text-danger">*</span></label>
                                <select class="form-select" id="priority" name="priority" required>
                                    <option value="">Select Priority</option>
                                    <option value="low" <?php echo (isset($_POST['priority']) && $_POST['priority'] == 'low') ? 'selected' : ''; ?>>
                                        Low - Can wait a few days
                                    </option>
                                    <option value="medium" <?php echo (isset($_POST['priority']) && $_POST['priority'] == 'medium') ? 'selected' : ''; ?>>
                                        Medium - Should be addressed soon
                                    </option>
                                    <option value="high" <?php echo (isset($_POST['priority']) && $_POST['priority'] == 'high') ? 'selected' : ''; ?>>
                                        High - Needs prompt attention
                                    </option>
                                    <option value="urgent" <?php echo (isset($_POST['priority']) && $_POST['priority'] == 'urgent') ? 'selected' : ''; ?>>
                                        Urgent - Immediate help needed
                                    </option>
                                </select>
                                <div class="invalid-feedback">Please select a priority level.</div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label">Detailed Description <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="description" name="description" rows="5" 
                                  placeholder="Please provide detailed information about the problem, including:
- What exactly is the issue?
- When did it start?
- How long has it been going on?
- What symptoms or effects are you experiencing?
- Have you tried anything to fix it?"
                                  minlength="20" maxlength="2000" required><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                        <div class="form-text">Provide as much detail as possible to help us understand and address your problem</div>
                        <div class="invalid-feedback">Please provide a detailed description (20-2000 characters).</div>
                    </div>

                    <div class="row">
                        
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="location" class="form-label">Specific Location</label>
                                <input type="text" class="form-control" id="location" name="location" 
                                       value="<?php echo isset($_POST['location']) ? htmlspecialchars($_POST['location']) : htmlspecialchars($_SESSION['user_village'] ?? ''); ?>" 
                                       placeholder="House number, street, landmark, etc." maxlength="100">
                                <div class="form-text">Help us locate the problem more precisely</div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="photo" class="form-label">Upload Photo (Optional)</label>
                        <input type="file" class="form-control" id="photo" name="photo" accept="image/jpeg,image/png,image/gif">
                        <div class="form-text">
                            Upload a clear photo to help explain the problem better. 
                            <strong>Max size: 5MB. Formats: JPG, PNG, GIF</strong>
                        </div>
                    </div>

                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <a href="<?php echo SITE_URL . '/villager/dashboard.php'; ?>" class="btn btn-secondary me-md-2">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane"></i> Submit Problem Report
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Sidebar Information -->
        <div class="col-lg-4">
            <!-- Reporting Guidelines -->
            <div class="card mb-3">
                <div class="card-header">
                    <h6><i class="fas fa-lightbulb"></i> Reporting Guidelines</h6>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled mb-0">
                        <li class="mb-2">
                            <i class="fas fa-check text-success"></i> 
                            <small>Be specific about symptoms or issues</small>
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-check text-success"></i> 
                            <small>Include timing - when did it start?</small>
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-check text-success"></i> 
                            <small>Upload clear photos if relevant</small>
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-check text-success"></i> 
                            <small>Set appropriate priority level</small>
                        </li>
                        <li>
                            <i class="fas fa-check text-success"></i> 
                            <small>Provide exact location details</small>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- What Happens Next -->
            <div class="card mb-3">
                <div class="card-header">
                    <h6><i class="fas fa-route"></i> What Happens Next?</h6>
                </div>
                <div class="card-body">
                    <div class="process-step mb-3">
                        <div class="d-flex align-items-start">
                            <div class="step-number bg-primary text-white">1</div>
                            <div class="step-content">
                                <strong>Problem Submitted</strong>
                                <p class="mb-0 small text-muted">Your problem is logged and ANMS officers are notified</p>
                            </div>
                        </div>
                    </div>
                    <div class="process-step mb-3">
                        <div class="d-flex align-items-start">
                            <div class="step-number bg-info text-white">2</div>
                            <div class="step-content">
                                <strong>ANMS Review</strong>
                                <p class="mb-0 small text-muted">An ANMS officer will review and assign themselves</p>
                            </div>
                        </div>
                    </div>
                    <div class="process-step mb-3">
                        <div class="d-flex align-items-start">
                            <div class="step-number bg-warning text-white">3</div>
                            <div class="step-content">
                                <strong>Resolution or Escalation</strong>
                                <p class="mb-0 small text-muted">ANMS will help directly or escalate to a doctor</p>
                            </div>
                        </div>
                    </div>
                    <div class="process-step">
                        <div class="d-flex align-items-start">
                            <div class="step-number bg-success text-white">4</div>
                            <div class="step-content">
                                <strong>Problem Resolved</strong>
                                <p class="mb-0 small text-muted">Case closed with your feedback</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Emergency Notice -->
            <div class="card border-danger">
                <div class="card-header bg-danger text-white">
                    <h6 class="mb-0"><i class="fas fa-exclamation-triangle"></i> Emergency Situations</h6>
                </div>
                <div class="card-body">
                    <p class="mb-2">For life-threatening emergencies, call immediately:</p>
                    <div class="d-grid gap-2">
                        <a href="tel:108" class="btn btn-danger btn-sm">
                            <i class="fas fa-phone"></i> 108 - Ambulance
                        </a>
                        <a href="tel:102" class="btn btn-outline-danger btn-sm">
                            <i class="fas fa-hospital"></i> 102 - Medical Help
                        </a>
                    </div>
                    <small class="text-muted mt-2 d-block">
                        Use this form for non-emergency situations that need attention
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.step-number {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    margin-right: 15px;
    flex-shrink: 0;
}

.step-content {
    flex: 1;
}
</style>

<script>
// Auto-save form data
setupAutoSave('reportForm');

// Character counters
document.getElementById('title').addEventListener('input', function() {
    updateCharCounter(this, 255);
});

document.getElementById('description').addEventListener('input', function() {
    updateCharCounter(this, 2000);
});

function updateCharCounter(element, maxLength) {
    const current = element.value.length;
    const remaining = maxLength - current;

    let counter = element.parentNode.querySelector('.char-counter');
    if (!counter) {
        counter = document.createElement('small');
        counter.className = 'char-counter text-muted';
        element.parentNode.appendChild(counter);
    }

    counter.textContent = `${current}/${maxLength} characters`;

    if (remaining < 50) {
        counter.classList.remove('text-muted');
        counter.classList.add('text-warning');
    } else {
        counter.classList.remove('text-warning');
        counter.classList.add('text-muted');
    }
}
</script>

<?php include '../includes/footer.php'; ?>