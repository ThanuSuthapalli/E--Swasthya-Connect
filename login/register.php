<?php
require_once '../includes/config.php';

// If already logged in, redirect to dashboard
if (isLoggedIn()) {
    $role = $_SESSION['user_role'];
    redirect(SITE_URL . '/' . $role . '/dashboard.php');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitizeInput($_POST['name'] ?? '');
    $email = sanitizeInput($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $role = $_POST['role'] ?? '';
    $phone = sanitizeInput($_POST['phone'] ?? '');
    $village = sanitizeInput($_POST['village'] ?? '');

    // Validation
    if (empty($name) || empty($email) || empty($password) || empty($role)) {
        $error = 'Please fill in all required fields.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (!in_array($role, ['villager', 'avms', 'doctor'])) {
        $error = 'Please select a valid role.';
    } else {
        try {
            $pdo = getDBConnection();

            // Check if email already exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);

            if ($stmt->rowCount() > 0) {
                $error = 'Email address is already registered. Please use a different email or try logging in.';
            } else {
                // Hash password and insert user
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $status = ($role === 'villager') ? 'active' : 'pending'; // Auto-approve villagers

                $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role, phone, village, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $result = $stmt->execute([$name, $email, $hashed_password, $role, $phone, $village, $status]);

                if ($result) {
                    if ($status === 'active') {
                        $success = 'Registration successful! You can now login with your credentials.';
                        // Optionally auto-login villagers
                        // $_SESSION['user_id'] = $pdo->lastInsertId();
                        // ... set other session variables
                        // redirect(SITE_URL . '/villager/dashboard.php');
                    } else {
                        $success = 'Registration successful! Your account is pending admin approval. You will be notified when approved.';

                        // Notify admin about new registration
                        $stmt = $pdo->prepare("SELECT id FROM users WHERE role = 'admin' AND status = 'active'");
                        $stmt->execute();
                        $admins = $stmt->fetchAll();

                        foreach ($admins as $admin) {
                            addNotification($admin['id'], null, 'New Registration', 
                                "New {$role} registration: {$name} ({$email}) is waiting for approval.");
                        }
                    }
                } else {
                    $error = 'Registration failed. Please try again.';
                }
            }
        } catch (PDOException $e) {
            error_log("Registration error: " . $e->getMessage());
            $error = 'Registration failed due to system error. Please try again.';
        }
    }
}

$page_title = 'Register - Village Health Connect';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
</head>
<body class="auth-body">
    <div class="auth-container">
        <div class="container">
            <div class="row justify-content-center align-items-center min-vh-100 py-4">
                <div class="col-lg-8 col-md-10">
                    <div class="auth-card">
                        <div class="text-center mb-4">
                            <div class="auth-logo">
                                <i class="fas fa-user-plus fa-3x text-primary mb-3"></i>
                            </div>
                            <h3 class="mb-2">Create Account</h3>
                            <p class="text-muted">Join the Village Health Connect community</p>
                        </div>

                        <?php if ($error): ?>
                            <div class="alert alert-danger" role="alert">
                                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($success): ?>
                            <div class="alert alert-success" role="alert">
                                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                                <div class="mt-2">
                                    <a href="login.php" class="btn btn-success btn-sm">Go to Login</a>
                                </div>
                            </div>
                        <?php endif; ?>

                        <form method="POST" class="needs-validation" novalidate>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="name" class="form-label">Full Name *</label>
                                        <input type="text" class="form-control" id="name" name="name" 
                                               value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>" 
                                               required>
                                        <div class="invalid-feedback">Please enter your full name.</div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="email" class="form-label">Email Address *</label>
                                        <input type="email" class="form-control" id="email" name="email" 
                                               value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" 
                                               required>
                                        <div class="invalid-feedback">Please enter a valid email address.</div>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="password" class="form-label">Password *</label>
                                        <input type="password" class="form-control" id="password" name="password" 
                                               minlength="6" required>
                                        <div class="form-text">Minimum 6 characters</div>
                                        <div class="invalid-feedback">Password must be at least 6 characters long.</div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="confirm_password" class="form-label">Confirm Password *</label>
                                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                                               minlength="6" required>
                                        <div class="invalid-feedback">Passwords must match.</div>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="role" class="form-label">Account Type *</label>
                                        <select class="form-select" id="role" name="role" required>
                                            <option value="">Select Your Role</option>
                                            <option value="villager" <?php echo (isset($_POST['role']) && $_POST['role'] == 'villager') ? 'selected' : ''; ?>>
                                                Villager - Report health/community issues
                                            </option>
                                            <option value="avms" <?php echo (isset($_POST['role']) && $_POST['role'] == 'avms') ? 'selected' : ''; ?>>
                                                ANMS Member - Manage community problems
                                            </option>
                                            <option value="doctor" <?php echo (isset($_POST['role']) && $_POST['role'] == 'doctor') ? 'selected' : ''; ?>>
                                                Doctor - Provide medical guidance
                                            </option>
                                        </select>
                                        <div class="invalid-feedback">Please select your role.</div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="phone" class="form-label">Phone Number</label>
                                        <input type="tel" class="form-control" id="phone" name="phone" 
                                               value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>" 
                                               placeholder="Enter your phone number">
                                    </div>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label for="village" class="form-label">Village/Area</label>
                                <input type="text" class="form-control" id="village" name="village" 
                                       value="<?php echo isset($_POST['village']) ? htmlspecialchars($_POST['village']) : ''; ?>" 
                                       placeholder="Enter your village or area name">
                                <div class="form-text">This helps us connect you with local services</div>
                            </div>

                            <!-- Account Approval Info -->
                            <div class="alert alert-info">
                                <h6><i class="fas fa-info-circle"></i> Account Approval Process</h6>
                                <ul class="mb-0">
                                    <li><strong>Villager accounts:</strong> Activated immediately</li>
                                    <li><strong>ANMS & Doctor accounts:</strong> Require admin approval for security</li>
                                </ul>
                            </div>

                            <div class="d-grid mb-3">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-user-plus"></i> Create Account
                                </button>
                            </div>
                        </form>

                        <div class="text-center">
                            <p class="mb-2">Already have an account? <a href="login.php" class="text-decoration-none">Login here</a></p>
                            <p><a href="../index.php" class="text-muted text-decoration-none"><i class="fas fa-arrow-left"></i> Back to Home</a></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Form validation
        (function() {
            'use strict';
            window.addEventListener('load', function() {
                const forms = document.getElementsByClassName('needs-validation');
                Array.prototype.filter.call(forms, function(form) {
                    form.addEventListener('submit', function(event) {
                        // Check password confirmation
                        const password = document.getElementById('password');
                        const confirmPassword = document.getElementById('confirm_password');

                        if (password.value !== confirmPassword.value) {
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

        // Real-time password confirmation validation
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('password');
            const confirmPassword = this;

            if (password.value !== confirmPassword.value) {
                confirmPassword.setCustomValidity("Passwords don't match");
            } else {
                confirmPassword.setCustomValidity("");
            }
        });
    </script>
</body>
</html>