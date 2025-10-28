<?php
require_once '../includes/config.php';
requireRole('villager');

$page_title = 'My Profile - Village Health Connect';
$error = '';
$success = '';

// Get user information
try {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user) {
        setMessage('error', 'User not found.');
        redirect(SITE_URL . '/villager/dashboard.php');
    }
} catch (Exception $e) {
    error_log("Profile error: " . $e->getMessage());
    setMessage('error', 'System error occurred.');
    redirect(SITE_URL . '/villager/dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitizeInput($_POST['name'] ?? '');
    $phone = sanitizeInput($_POST['phone'] ?? '');
    $village = sanitizeInput($_POST['village'] ?? '');
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($name) || empty($phone) || empty($village)) {
        $error = 'Please fill in all required fields.';
    } else {
        try {
            // Update basic info
            $stmt = $pdo->prepare("UPDATE users SET name = ?, phone = ?, village = ? WHERE id = ?");
            $result = $stmt->execute([$name, $phone, $village, $_SESSION['user_id']]);

            // Update password if provided
            if (!empty($new_password)) {
                if (empty($current_password)) {
                    $error = 'Please enter your current password to change it.';
                } elseif ($new_password !== $confirm_password) {
                    $error = 'New passwords do not match.';
                } elseif (strlen($new_password) < 6) {
                    $error = 'New password must be at least 6 characters long.';
                } elseif (!password_verify($current_password, $user['password'])) {
                    $error = 'Current password is incorrect.';
                } else {
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $stmt->execute([$hashed_password, $_SESSION['user_id']]);
                }
            }

            if (empty($error) && $result) {
                $_SESSION['user_name'] = $name;
                $_SESSION['user_village'] = $village;
                $success = 'Profile updated successfully!';

                // Refresh user data
                $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $user = $stmt->fetch();
            } elseif (empty($error)) {
                $error = 'Failed to update profile. Please try again.';
            }
        } catch (Exception $e) {
            error_log("Profile update error: " . $e->getMessage());
            $error = 'System error occurred while updating profile.';
        }
    }
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
                            <i class="fas fa-user-edit text-primary"></i> My Profile
                        </h1>
                        <p class="text-muted mb-0">Manage your personal information and account settings</p>
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
                    </div>
                <?php endif; ?>

                <form method="POST" class="needs-validation" novalidate>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="name" class="form-label">Full Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="name" name="name" 
                                       value="<?php echo htmlspecialchars($user['name']); ?>" required>
                                <div class="invalid-feedback">Please enter your full name.</div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email Address</label>
                                <input type="email" class="form-control" id="email" 
                                       value="<?php echo htmlspecialchars($user['email']); ?>" readonly>
                                <small class="text-muted">Email cannot be changed for security reasons</small>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="phone" class="form-label">Phone Number <span class="text-danger">*</span></label>
                                <input type="tel" class="form-control" id="phone" name="phone" 
                                       value="<?php echo htmlspecialchars($user['phone']); ?>" required>
                                <div class="invalid-feedback">Please enter your phone number.</div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="village" class="form-label">Village/Area <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="village" name="village" 
                                       value="<?php echo htmlspecialchars($user['village']); ?>" required>
                                <div class="invalid-feedback">Please enter your village or area name.</div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="role" class="form-label">Account Type</label>
                                <input type="text" class="form-control" value="<?php echo ucfirst($user['role']); ?>" readonly>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="registered" class="form-label">Member Since</label>
                                <input type="text" class="form-control" value="<?php echo formatDate($user['registered_at']); ?>" readonly>
                            </div>
                        </div>
                    </div>

                    <hr class="my-4">

                    <h5 class="mb-3">
                        <i class="fas fa-lock text-warning"></i> Change Password (Optional)
                    </h5>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="current_password" class="form-label">Current Password</label>
                                <input type="password" class="form-control" id="current_password" name="current_password">
                                <small class="text-muted">Required only if changing password</small>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="new_password" class="form-label">New Password</label>
                                <input type="password" class="form-control" id="new_password" name="new_password" minlength="6">
                                <small class="text-muted">Minimum 6 characters</small>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">Confirm New Password</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                            </div>
                        </div>
                    </div>

                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <a href="dashboard.php" class="btn btn-secondary me-md-2">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Update Profile
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Profile Information Sidebar -->
        <div class="col-lg-4">
            <div class="card bg-light">
                <div class="card-body">
                    <h6 class="card-title">
                        <i class="fas fa-info-circle text-primary"></i> Profile Information
                    </h6>
                    <ul class="list-unstyled mb-0">
                        <li class="mb-2">
                            <i class="fas fa-check text-success"></i>
                            <small>Keep your contact information updated for better service</small>
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-check text-success"></i>
                            <small>Your phone number helps ANMS officers contact you</small>
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-check text-success"></i>
                            <small>Village information helps assign problems to local officers</small>
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-check text-success"></i>
                            <small>Use a strong password to keep your account secure</small>
                        </li>
                        <li>
                            <i class="fas fa-check text-success"></i>
                            <small>Your email is used for login and cannot be changed</small>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Profile Statistics -->
            <div class="card mt-3">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-chart-bar text-info"></i> Your Activity
                    </h6>
                </div>
                <div class="card-body">
                    <?php
                    try {
                        // Get user statistics
                        $stmt = $pdo->prepare("SELECT status, COUNT(*) as count FROM problems WHERE villager_id = ? GROUP BY status");
                        $stmt->execute([$_SESSION['user_id']]);
                        $problem_stats = $stmt->fetchAll();

                        $total_problems = 0;
                        foreach ($problem_stats as $stat) {
                            $total_problems += $stat['count'];
                        }
                    } catch (Exception $e) {
                        $problem_stats = [];
                        $total_problems = 0;
                    }
                    ?>

                    <div class="mb-2">
                        <div class="d-flex justify-content-between">
                            <span>Total Problems Reported:</span>
                            <span class="badge bg-primary"><?php echo $total_problems; ?></span>
                        </div>
                    </div>

                    <?php foreach ($problem_stats as $stat): ?>
                        <div class="mb-2">
                            <div class="d-flex justify-content-between">
                                <span><?php echo ucfirst(str_replace('_', ' ', $stat['status'])); ?>:</span>
                                <span class="badge status-<?php echo $stat['status']; ?>"><?php echo $stat['count']; ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <div class="mt-3">
                        <small class="text-muted">
                            <i class="fas fa-calendar"></i> Member since: <?php echo date('M Y', strtotime($user['registered_at'])); ?>
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Form validation
(function() {
    'use strict';
    window.addEventListener('load', function() {
        const forms = document.getElementsByClassName('needs-validation');
        Array.prototype.filter.call(forms, function(form) {
            form.addEventListener('submit', function(event) {
                // Check password confirmation
                const newPassword = document.getElementById('new_password');
                const confirmPassword = document.getElementById('confirm_password');

                if (newPassword.value && newPassword.value !== confirmPassword.value) {
                    confirmPassword.setCustomValidity("Passwords don't match");
                } else {
                    confirmPassword.setCustomValidity("");
                }

                if (form.checkValidity() === false) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            }, false);
        });
    }, false);
})();

// Real-time password confirmation
document.getElementById('confirm_password').addEventListener('input', function() {
    const newPassword = document.getElementById('new_password');
    const confirmPassword = this;

    if (newPassword.value && newPassword.value !== confirmPassword.value) {
        confirmPassword.setCustomValidity("Passwords don't match");
    } else {
        confirmPassword.setCustomValidity("");
    }
});
</script>

<?php include '../includes/footer.php'; ?>