<?php
require_once '../includes/config.php';

// Include working medical functions
if (file_exists('../includes/working_medical_functions.php')) {
    require_once '../includes/working_medical_functions.php';
}

requireRole('doctor');

$page_title = 'Doctor Profile - Village Health Connect';

// Handle form submissions
$success_message = '';
$error_message = '';

if ($_POST) {
    if (isset($_POST['update_profile'])) {
        // Update profile information
        $profile_data = [
            'name' => trim($_POST['name'] ?? ''),
            'phone' => trim($_POST['phone'] ?? ''),
            'specialization' => trim($_POST['specialization'] ?? ''),
            'license_number' => trim($_POST['license_number'] ?? ''),
            'qualifications' => trim($_POST['qualifications'] ?? ''),
            'experience_years' => (int)($_POST['experience_years'] ?? 0),
            'hospital_affiliation' => trim($_POST['hospital_affiliation'] ?? ''),
            'consultation_hours' => trim($_POST['consultation_hours'] ?? ''),
            'emergency_contact' => trim($_POST['emergency_contact'] ?? ''),
            'bio' => trim($_POST['bio'] ?? ''),
            'languages' => trim($_POST['languages'] ?? '')
        ];
        
        try {
            $pdo = getDBConnection();
            $pdo->beginTransaction();
            
            // Update basic user info
            $stmt = $pdo->prepare("UPDATE users SET name = ?, phone = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$profile_data['name'], $profile_data['phone'], $_SESSION['user_id']]);
            
            // Update additional info
            $additional_info = json_encode($profile_data);
            $stmt = $pdo->prepare("UPDATE users SET additional_info = ? WHERE id = ?");
            $stmt->execute([$additional_info, $_SESSION['user_id']]);
            
            $pdo->commit();
            $success_message = 'Profile updated successfully!';
            $_SESSION['user_name'] = $profile_data['name'];
        } catch (Exception $e) {
            if (isset($pdo)) $pdo->rollBack();
            $error_message = 'Failed to update profile: ' . $e->getMessage();
        }
    } elseif (isset($_POST['change_password'])) {
        // Change password
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $error_message = 'All password fields are required.';
        } elseif ($new_password !== $confirm_password) {
            $error_message = 'New passwords do not match.';
        } elseif (strlen($new_password) < 6) {
            $error_message = 'New password must be at least 6 characters long.';
        } else {
            try {
                $pdo = getDBConnection();
                
                // Verify current password
                $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $user = $stmt->fetch();
                
                if ($user && password_verify($current_password, $user['password'])) {
                    // Update password
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$hashed_password, $_SESSION['user_id']]);
                    
                    $success_message = 'Password changed successfully!';
                } else {
                    $error_message = 'Current password is incorrect.';
                }
            } catch (Exception $e) {
                $error_message = 'Failed to change password: ' . $e->getMessage();
            }
        }
    }
}

// Get current user profile
$user_profile = null;
try {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'doctor'");
    $stmt->execute([$_SESSION['user_id']]);
    $user_profile = $stmt->fetch();
    
    if ($user_profile && $user_profile['additional_info']) {
        $additional = json_decode($user_profile['additional_info'], true);
        if ($additional) {
            $user_profile = array_merge($user_profile, $additional);
        }
    }
} catch (Exception $e) {
    error_log("Error fetching profile: " . $e->getMessage());
}

// Get doctor statistics
$doctor_stats = ['total_responses' => 0, 'patients_helped' => 0, 'responses_today' => 0];
try {
    $pdo = getDBConnection();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM medical_responses WHERE doctor_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $doctor_stats['total_responses'] = (int)$stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT problem_id) FROM medical_responses WHERE doctor_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $doctor_stats['patients_helped'] = (int)$stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM medical_responses WHERE doctor_id = ? AND DATE(created_at) = CURDATE()");
    $stmt->execute([$_SESSION['user_id']]);
    $doctor_stats['responses_today'] = (int)$stmt->fetchColumn();
} catch (Exception $e) {
    // Keep defaults
}

include '../includes/header.php';
?>

<div class="container-fluid">
    <?php if ($success_message): ?>
        <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
            <i class="fas fa-check-circle me-2"></i>
            <strong>Success!</strong> <?php echo htmlspecialchars($success_message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($error_message): ?>
        <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <strong>Error:</strong> <?php echo htmlspecialchars($error_message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Page Header -->
    <div class="row">
        <div class="col-12">
            <div class="page-header mb-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1 class="mb-2 text-primary">
                            <i class="fas fa-user-md me-2"></i>Doctor Profile
                        </h1>
                        <p class="text-muted mb-0">
                            Manage your professional medical profile and account settings
                        </p>
                    </div>
                    <div>
                        <a href="dashboard.php" class="btn btn-outline-primary me-2">
                            <i class="fas fa-arrow-left me-1"></i>Back to Dashboard
                        </a>
                        <a href="my_responses.php" class="btn btn-success">
                            <i class="fas fa-history me-1"></i>My Response History
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Profile Information -->
        <div class="col-lg-8">
            <!-- Personal Information -->
            <div class="card shadow mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-user me-2"></i>Personal Information</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="name" class="form-label"><strong>Full Name</strong> <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="name" name="name" 
                                           value="<?php echo htmlspecialchars($user_profile['name'] ?? ''); ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="phone" class="form-label"><strong>Phone Number</strong> <span class="text-danger">*</span></label>
                                    <input type="tel" class="form-control" id="phone" name="phone" 
                                           value="<?php echo htmlspecialchars($user_profile['phone'] ?? ''); ?>" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="email" class="form-label"><strong>Email Address</strong></label>
                            <input type="email" class="form-control" id="email" 
                                   value="<?php echo htmlspecialchars($user_profile['email'] ?? ''); ?>" readonly>
                            <div class="form-text">Email cannot be changed for security reasons.</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="emergency_contact" class="form-label"><strong>Emergency Contact</strong></label>
                            <input type="tel" class="form-control" id="emergency_contact" name="emergency_contact" 
                                   value="<?php echo htmlspecialchars($user_profile['emergency_contact'] ?? ''); ?>"
                                   placeholder="Alternative contact number">
                        </div>
                        
                        <div class="mb-3">
                            <label for="languages" class="form-label"><strong>Languages Spoken</strong></label>
                            <input type="text" class="form-control" id="languages" name="languages" 
                                   value="<?php echo htmlspecialchars($user_profile['languages'] ?? ''); ?>"
                                   placeholder="English, Hindi, Local languages...">
                        </div>
                        
                        <div class="mb-3">
                            <label for="bio" class="form-label"><strong>Professional Bio</strong></label>
                            <textarea class="form-control" id="bio" name="bio" rows="3" 
                                      placeholder="Brief description of your medical background and expertise..."><?php echo htmlspecialchars($user_profile['bio'] ?? ''); ?></textarea>
                        </div>

                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <button type="submit" name="update_profile" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Update Personal Information
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Medical Credentials -->
            <div class="card shadow mb-4">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="fas fa-stethoscope me-2"></i>Medical Credentials</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="specialization" class="form-label"><strong>Medical Specialization</strong></label>
                                    <select class="form-select" id="specialization" name="specialization">
                                        <option value="">Select Specialization</option>
                                        <?php 
                                        $specializations = [
                                            'General Medicine', 'Internal Medicine', 'Family Medicine',
                                            'Emergency Medicine', 'Pediatrics', 'Cardiology',
                                            'Pulmonology', 'Gastroenterology', 'Neurology',
                                            'Orthopedics', 'Surgery', 'Gynecology',
                                            'Dermatology', 'Psychiatry', 'Radiology'
                                        ];
                                        foreach ($specializations as $spec): 
                                        ?>
                                            <option value="<?php echo $spec; ?>" 
                                                    <?php echo ($user_profile['specialization'] ?? '') === $spec ? 'selected' : ''; ?>>
                                                <?php echo $spec; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="license_number" class="form-label"><strong>Medical License Number</strong></label>
                                    <input type="text" class="form-control" id="license_number" name="license_number" 
                                           value="<?php echo htmlspecialchars($user_profile['license_number'] ?? ''); ?>"
                                           placeholder="MED-12345">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="qualifications" class="form-label"><strong>Qualifications</strong></label>
                                    <input type="text" class="form-control" id="qualifications" name="qualifications" 
                                           value="<?php echo htmlspecialchars($user_profile['qualifications'] ?? ''); ?>"
                                           placeholder="MBBS, MD, etc.">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="experience_years" class="form-label"><strong>Years of Experience</strong></label>
                                    <input type="number" class="form-control" id="experience_years" name="experience_years" 
                                           value="<?php echo $user_profile['experience_years'] ?? ''; ?>" min="0" max="50">
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="hospital_affiliation" class="form-label"><strong>Hospital/Clinic Affiliation</strong></label>
                            <input type="text" class="form-control" id="hospital_affiliation" name="hospital_affiliation" 
                                   value="<?php echo htmlspecialchars($user_profile['hospital_affiliation'] ?? ''); ?>"
                                   placeholder="Primary hospital or clinic name">
                        </div>
                        
                        <div class="mb-3">
                            <label for="consultation_hours" class="form-label"><strong>Consultation Hours</strong></label>
                            <input type="text" class="form-control" id="consultation_hours" name="consultation_hours" 
                                   value="<?php echo htmlspecialchars($user_profile['consultation_hours'] ?? ''); ?>"
                                   placeholder="Mon-Fri: 9AM-5PM, Emergency: 24/7">
                        </div>

                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <button type="submit" name="update_profile" class="btn btn-success">
                                <i class="fas fa-save me-2"></i>Update Medical Credentials
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Security Settings -->
            <div class="card shadow">
                <div class="card-header bg-warning text-dark">
                    <h5 class="mb-0"><i class="fas fa-lock me-2"></i>Security Settings</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Password Security:</strong> Use a strong password with at least 6 characters for account security.
                        </div>
                        
                        <div class="mb-3">
                            <label for="current_password" class="form-label"><strong>Current Password</strong> <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" id="current_password" name="current_password" required>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="new_password" class="form-label"><strong>New Password</strong> <span class="text-danger">*</span></label>
                                    <input type="password" class="form-control" id="new_password" name="new_password" 
                                           minlength="6" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="confirm_password" class="form-label"><strong>Confirm New Password</strong> <span class="text-danger">*</span></label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                                           minlength="6" required>
                                </div>
                            </div>
                        </div>

                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <button type="submit" name="change_password" class="btn btn-warning text-dark">
                                <i class="fas fa-key me-2"></i>Change Password
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Profile Sidebar -->
        <div class="col-lg-4">
            <!-- Profile Summary -->
            <div class="card shadow mb-4">
                <div class="card-header bg-info text-white">
                    <h6 class="mb-0"><i class="fas fa-user-circle me-2"></i>Profile Summary</h6>
                </div>
                <div class="card-body text-center">
                    <div class="profile-avatar-large mb-3">
                        <?php echo strtoupper(substr($_SESSION['user_name'], 0, 2)); ?>
                    </div>
                    
                    <h5 class="mb-1">Dr. <?php echo htmlspecialchars($_SESSION['user_name']); ?></h5>
                    <?php if (!empty($user_profile['specialization'])): ?>
                        <p class="text-muted mb-2"><?php echo htmlspecialchars($user_profile['specialization']); ?></p>
                    <?php endif; ?>
                    
                    <?php if (!empty($user_profile['hospital_affiliation'])): ?>
                        <p class="text-muted small mb-3">
                            <i class="fas fa-hospital me-1"></i><?php echo htmlspecialchars($user_profile['hospital_affiliation']); ?>
                        </p>
                    <?php endif; ?>
                    
                    <!-- Profile Completion -->
                    <?php 
                    $completion_fields = ['name', 'phone', 'specialization', 'qualifications', 'hospital_affiliation'];
                    $completed = 0;
                    foreach ($completion_fields as $field) {
                        if (!empty($user_profile[$field])) $completed++;
                    }
                    $completion_percentage = round(($completed / count($completion_fields)) * 100);
                    ?>
                    
                    <div class="profile-completion mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span><strong>Profile Completion</strong></span>
                            <span class="text-primary"><strong><?php echo $completion_percentage; ?>%</strong></span>
                        </div>
                        <div class="progress" style="height: 8px;">
                            <div class="progress-bar bg-primary" role="progressbar" 
                                 style="width: <?php echo $completion_percentage; ?>%"></div>
                        </div>
                        <?php if ($completion_percentage < 100): ?>
                            <small class="text-muted mt-1">Complete your profile for better patient trust</small>
                        <?php endif; ?>
                    </div>
                    
                    <div class="profile-badges">
                        <?php if (!empty($user_profile['license_number'])): ?>
                            <span class="badge bg-success mb-1"><i class="fas fa-certificate me-1"></i>Licensed</span>
                        <?php endif; ?>
                        <?php if (!empty($user_profile['experience_years']) && $user_profile['experience_years'] >= 5): ?>
                            <span class="badge bg-primary mb-1"><i class="fas fa-award me-1"></i>Experienced</span>
                        <?php endif; ?>
                        <?php if ($completion_percentage >= 90): ?>
                            <span class="badge bg-info mb-1"><i class="fas fa-check-circle me-1"></i>Verified Profile</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Professional Statistics -->
            <div class="card shadow mb-4">
                <div class="card-header bg-success text-white">
                    <h6 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Professional Statistics</h6>
                </div>
                <div class="card-body">
                    <div class="stats-item d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <i class="fas fa-comments text-primary me-2"></i>
                            <strong>Total Responses</strong>
                        </div>
                        <span class="badge bg-primary"><?php echo $doctor_stats['total_responses']; ?></span>
                    </div>
                    
                    <div class="stats-item d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <i class="fas fa-users text-success me-2"></i>
                            <strong>Patients Helped</strong>
                        </div>
                        <span class="badge bg-success"><?php echo $doctor_stats['patients_helped']; ?></span>
                    </div>
                    
                    <div class="stats-item d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <i class="fas fa-calendar-day text-info me-2"></i>
                            <strong>Today's Responses</strong>
                        </div>
                        <span class="badge bg-info"><?php echo $doctor_stats['responses_today']; ?></span>
                    </div>
                    
                    <div class="stats-item d-flex justify-content-between align-items-center">
                        <div>
                            <i class="fas fa-star text-warning me-2"></i>
                            <strong>Experience</strong>
                        </div>
                        <span class="badge bg-warning text-dark">
                            <?php echo (!empty($user_profile['experience_years']) ? $user_profile['experience_years'] . ' years' : 'Not set'); ?>
                        </span>
                    </div>
                    
                    <hr>
                    <div class="text-center">
                        <a href="my_responses.php" class="btn btn-outline-success btn-sm">
                            <i class="fas fa-history me-1"></i>View Response History
                        </a>
                    </div>
                </div>
            </div>

            <!-- Account Information -->
            <div class="card shadow">
                <div class="card-header bg-secondary text-white">
                    <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>Account Information</h6>
                </div>
                <div class="card-body">
                    <div class="account-info">
                        <p><strong>User ID:</strong> <?php echo $_SESSION['user_id']; ?></p>
                        <p><strong>Role:</strong> <span class="badge bg-primary">Doctor</span></p>
                        <p><strong>Member Since:</strong> 
                            <?php echo isset($user_profile['created_at']) ? date('M Y', strtotime($user_profile['created_at'])) : 'Unknown'; ?>
                        </p>
                        <p><strong>Last Updated:</strong> 
                            <?php echo isset($user_profile['updated_at']) ? date('M j, Y', strtotime($user_profile['updated_at'])) : 'Never'; ?>
                        </p>
                    </div>
                    
                    <hr>
                    
                    <div class="d-grid gap-2">
                        <a href="dashboard.php" class="btn btn-outline-primary btn-sm">
                            <i class="fas fa-tachometer-alt me-1"></i>Go to Dashboard
                        </a>
                        <a href="all_escalated.php" class="btn btn-outline-info btn-sm">
                            <i class="fas fa-list me-1"></i>View Medical Cases
                        </a>
                        <a href="../logout.php" class="btn btn-outline-danger btn-sm" onclick="return confirm('Are you sure you want to logout?')">
                            <i class="fas fa-sign-out-alt me-1"></i>Logout
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.page-header {
    background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
    border-radius: 15px;
    padding: 25px;
    border: 1px solid #e9ecef;
}

.profile-avatar-large {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto;
    color: white;
    font-size: 2rem;
    font-weight: bold;
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.card {
    border-radius: 15px;
    overflow: hidden;
    transition: all 0.3s ease;
}

.card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.1);
}

.form-control, .form-select {
    border-radius: 8px;
    border: 2px solid #e9ecef;
    transition: all 0.3s ease;
}

.form-control:focus, .form-select:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
}

.btn {
    border-radius: 8px;
    font-weight: 600;
    transition: all 0.3s ease;
}

.btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.progress {
    border-radius: 10px;
    background: #e9ecef;
}

.progress-bar {
    border-radius: 10px;
}

.profile-badges .badge {
    margin-right: 5px;
}

.stats-item {
    background: #f8f9fa;
    padding: 10px;
    border-radius: 8px;
    border-left: 4px solid #007bff;
}

.account-info p {
    margin-bottom: 8px;
}

@media (max-width: 768px) {
    .page-header {
        text-align: center;
    }
    
    .profile-avatar-large {
        width: 80px;
        height: 80px;
        font-size: 1.5rem;
    }
}
</style>

<script>
// Form validation
document.addEventListener('DOMContentLoaded', function() {
    // Password confirmation validation
    const newPassword = document.getElementById('new_password');
    const confirmPassword = document.getElementById('confirm_password');
    
    function validatePasswords() {
        if (newPassword.value !== confirmPassword.value) {
            confirmPassword.setCustomValidity("Passwords don't match");
        } else {
            confirmPassword.setCustomValidity('');
        }
    }
    
    if (newPassword && confirmPassword) {
        newPassword.addEventListener('change', validatePasswords);
        confirmPassword.addEventListener('keyup', validatePasswords);
    }
    
    // Phone number formatting
    const phoneInput = document.getElementById('phone');
    if (phoneInput) {
        phoneInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length >= 10) {
                value = value.substring(0, 10);
            }
            e.target.value = value;
        });
    }
});
</script>

<?php include '../includes/footer.php'; ?>
