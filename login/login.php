<?php
require_once '../includes/config.php';

// If already logged in, redirect to dashboard
if (isLoggedIn()) {
    $role = $_SESSION['user_role'];
    redirect(SITE_URL . '/' . $role . '/dashboard.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitizeInput($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Please fill in all fields.';
    } else {
        try {
            $pdo = getDBConnection();
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND status = 'active'");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                // Set session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['user_village'] = $user['village'];

                // Update last login
                $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                $stmt->execute([$user['id']]);

                // Redirect to appropriate dashboard
                redirect(SITE_URL . '/' . $user['role'] . '/dashboard.php');
            } else {
                $error = 'Invalid email or password. Please check your credentials.';
            }
        } catch (PDOException $e) {
            error_log("Login error: " . $e->getMessage());
            $error = 'Login failed due to system error. Please try again.';
        }
    }
}

$page_title = 'Login - Village Health Connect';
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
            <div class="row justify-content-center align-items-center min-vh-100">
                <div class="col-lg-5 col-md-7">
                    <div class="auth-card">
                        <div class="text-center mb-4">
                            <div class="auth-logo">
                                <i class="fas fa-hospital-alt fa-3x text-primary mb-3"></i>
                            </div>
                            <h3 class="mb-2">Welcome Back</h3>
                            <p class="text-muted">Sign in to your Village Health Connect account</p>
                        </div>

                        <?php if ($error): ?>
                            <div class="alert alert-danger" role="alert">
                                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                            </div>
                        <?php endif; ?>

                        <?php if (isset($_GET['registered'])): ?>
                            <div class="alert alert-success" role="alert">
                                <i class="fas fa-check-circle"></i> Registration successful! You can now login.
                            </div>
                        <?php endif; ?>

                        <?php if (isset($_GET['approved'])): ?>
                            <div class="alert alert-success" role="alert">
                                <i class="fas fa-check-circle"></i> Your account has been approved! You can now login.
                            </div>
                        <?php endif; ?>

                        <form method="POST" class="needs-validation" novalidate>
                            <div class="mb-3">
                                <label for="email" class="form-label">Email Address</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                    <input type="email" class="form-control" id="email" name="email" 
                                        value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" 
                                        required>
                                </div>
                                <div class="invalid-feedback">Please enter a valid email address.</div>
                            </div>

                            <div class="mb-4">
                                <label for="password" class="form-label">Password</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                    <input type="password" class="form-control" id="password" name="password" required>
                                    <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <div class="invalid-feedback">Please enter your password.</div>
                            </div>

                            <div class="d-grid mb-3">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-sign-in-alt"></i> Login
                                </button>
                            </div>
                        </form>

                        <div class="text-center">
                            <p class="mb-2">Don't have an account? <a href="register.php" class="text-decoration-none">Register here</a></p>
                            <p><a href="../index.php" class="text-muted text-decoration-none"><i class="fas fa-arrow-left"></i> Back to Home</a></p>
                        </div>

                        <!-- Demo Accounts -->
                        <div class="mt-4 p-3 bg-light rounded">
                            <h6 class="mb-3"><i class="fas fa-key text-primary"></i> Demo Accounts</h6>
                            <div class="row">
                                <div class="col-6">
                                    <small class="d-block"><strong>Admin:</strong></small>
                                    <small class="text-muted">admin@villagehealth.com</small>
                                </div>
                                <div class="col-6">
                                    <small class="d-block"><strong>ANMS:</strong></small>
                                    <small class="text-muted">avms@villagehealth.com</small>
                                </div>
                                <div class="col-6 mt-2">
                                    <small class="d-block"><strong>Doctor:</strong></small>
                                    <small class="text-muted">doctor@villagehealth.com</small>
                                </div>
                                <div class="col-6 mt-2">
                                    <small class="d-block"><strong>Villager:</strong></small>
                                    <small class="text-muted">villager@villagehealth.com</small>
                                </div>
                            </div>
                            <small class="text-muted mt-2 d-block">Password for all: <strong>password</strong></small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle password visibility
        document.getElementById('togglePassword').addEventListener('click', function () {
            const password = document.getElementById('password');
            const icon = this.querySelector('i');

            if (password.type === 'password') {
                password.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                password.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });

        // Form validation
        (function() {
            'use strict';
            window.addEventListener('load', function() {
                const forms = document.getElementsByClassName('needs-validation');
                Array.prototype.filter.call(forms, function(form) {
                    form.addEventListener('submit', function(event) {
                        if (form.checkValidity() === false) {
                            event.preventDefault();
                            event.stopPropagation();
                        }
                        form.classList.add('was-validated');
                    }, false);
                });
            }, false);
        })();
    </script>
</body>
</html>