<?php
require_once '../includes/config.php';
requireRole('admin');

$page_title = 'Admin Profile - Village Health Connect';

$error = '';
$success = '';

// Fetch current user
try {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'admin'");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    if (!$user) {
        setMessage('error', 'Admin user not found.');
        redirect(SITE_URL . '/admin/dashboard.php');
    }
} catch (Exception $e) {
    error_log('Admin profile fetch error: ' . $e->getMessage());
    setMessage('error', 'System error occurred.');
    redirect(SITE_URL . '/admin/dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Update profile
    if (isset($_POST['update_profile'])) {
        $name = sanitizeInput($_POST['name'] ?? '');
        $phone = sanitizeInput($_POST['phone'] ?? '');

        if (empty($name) || empty($phone)) {
            $error = 'Please fill in all required fields.';
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE users SET name = ?, phone = ?, updated_at = NOW() WHERE id = ?");
                $res = $stmt->execute([$name, $phone, $_SESSION['user_id']]);
                if ($res) {
                    $success = 'Profile updated successfully.';
                    $_SESSION['user_name'] = $name;
                    // Refresh user
                    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                    $stmt->execute([$_SESSION['user_id']]);
                    $user = $stmt->fetch();
                } else {
                    $error = 'Failed to update profile.';
                }
            } catch (Exception $e) {
                error_log('Admin profile update error: ' . $e->getMessage());
                $error = 'System error occurred while updating profile.';
            }
        }
    }

    // Change password
    if (isset($_POST['change_password'])) {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $error = 'All password fields are required.';
        } elseif ($new_password !== $confirm_password) {
            $error = 'New passwords do not match.';
        } elseif (strlen($new_password) < 6) {
            $error = 'New password must be at least 6 characters long.';
        } else {
            try {
                $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $row = $stmt->fetch();
                if ($row && password_verify($current_password, $row['password'])) {
                    $hashed = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$hashed, $_SESSION['user_id']]);
                    $success = 'Password changed successfully.';
                } else {
                    $error = 'Current password is incorrect.';
                }
            } catch (Exception $e) {
                error_log('Admin change password error: ' . $e->getMessage());
                $error = 'Failed to change password.';
            }
        }
    }
}

include '../includes/header.php';
?>

<div class="container-fluid">
    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
            <i class="fas fa-check-circle me-2"></i> <?php echo htmlspecialchars($success); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i> <?php echo htmlspecialchars($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-12">
            <div class="page-header mb-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1 class="mb-2 text-primary"><i class="fas fa-user-shield me-2"></i>Admin Profile</h1>
                        <p class="text-muted mb-0">Manage your account and administrative contact information</p>
                    </div>
                    <div>
                        <a href="dashboard.php" class="btn btn-outline-primary me-2"><i class="fas fa-arrow-left me-1"></i>Back to Dashboard</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-user me-2"></i>Personal Information</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label"><strong>Full Name</strong> <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="name" value="<?php echo htmlspecialchars($user['name'] ?? ''); ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label"><strong>Phone Number</strong> <span class="text-danger">*</span></label>
                                    <input type="tel" class="form-control" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" required>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label"><strong>Email Address</strong></label>
                            <input type="email" class="form-control" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" readonly>
                            <div class="form-text">Email cannot be changed for security reasons.</div>
                        </div>

                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <button type="submit" name="update_profile" class="btn btn-primary"><i class="fas fa-save me-2"></i>Update Personal Information</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card shadow">
                <div class="card-header bg-warning text-dark">
                    <h5 class="mb-0"><i class="fas fa-lock me-2"></i>Security Settings</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <div class="mb-3">
                            <label class="form-label"><strong>Current Password</strong> <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" name="current_password" required>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label"><strong>New Password</strong> <span class="text-danger">*</span></label>
                                    <input type="password" class="form-control" name="new_password" minlength="6" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label"><strong>Confirm New Password</strong> <span class="text-danger">*</span></label>
                                    <input type="password" class="form-control" name="confirm_password" minlength="6" required>
                                </div>
                            </div>
                        </div>

                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <button type="submit" name="change_password" class="btn btn-warning text-dark"><i class="fas fa-key me-2"></i>Change Password</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card shadow mb-3">
                <div class="card-header bg-info text-white">
                    <h6 class="mb-0"><i class="fas fa-user-circle me-2"></i>Profile Summary</h6>
                </div>
                <div class="card-body text-center">
                    <div class="profile-avatar-large mb-3">
                        <?php echo strtoupper(substr($_SESSION['user_name'], 0, 2)); ?>
                    </div>
                    <h5 class="mb-1"><?php echo htmlspecialchars($_SESSION['user_name']); ?></h5>
                    <p class="text-muted small mb-2">Administrator</p>
                    <div class="mb-3">
                        <small class="text-muted"><i class="fas fa-calendar"></i> Member since: <?php echo date('M Y', strtotime($user['registered_at'] ?? 'now')); ?></small>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>Quick Tips</h6>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled mb-0">
                        <li class="mb-2"><i class="fas fa-check text-success me-2"></i> Keep your contact details up to date.</li>
                        <li class="mb-2"><i class="fas fa-check text-success me-2"></i> Use a strong password to protect administrative access.</li>
                        <li><i class="fas fa-check text-success me-2"></i> Review pending approvals regularly.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
