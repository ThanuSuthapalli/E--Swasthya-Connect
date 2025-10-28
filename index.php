<?php
require_once 'includes/config.php';

// If user is already logged in, redirect to their dashboard
if (isLoggedIn()) {
    $role = $_SESSION['user_role'];
    redirect(SITE_URL . '/' . $role . '/dashboard.php');
}

$page_title = 'Welcome to E- Swasthya Connect ';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container">
            <div class="row align-items-center min-vh-100">
                <div class="col-lg-6">
                    <div class="hero-content text-white">
                        <h1 class="display-3 fw-bold mb-4">
                            <i class="fas fa-hospital-alt"></i> E- Swasthya Connect
                        </h1>
                        <h2>Bridging Villagers and Doctors through ANMS</h2>
                        <p class="lead mb-4">
                            Connecting villagers, ANMS officers, and doctors for better community healthcare. 
                            Report health issues, get timely assistance, and ensure no one is left behind.
                        </p>
                        <div class="d-grid gap-2 d-md-flex">
                            <a href="login/login.php" class="btn btn-light btn-lg me-md-2">
                                <i class="fas fa-sign-in-alt"></i> Login
                            </a>
                            <a href="login/register.php" class="btn btn-outline-light btn-lg">
                                <i class="fas fa-user-plus"></i> Register
                            </a>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="text-center">
                        <div class="hero-icon">
                            <i class="fas fa-users-medical display-1 text-white mb-4"></i>
                        </div>
                        <h3 class="text-white">Community Health Made Simple</h3>
                        <p class="text-white-50">Report → Assign → Resolve → Care</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- How It Works Section -->
    <section class="py-5 bg-light">
        <div class="container">
            <div class="row text-center mb-5">
                <div class="col-lg-12">
                    <h2 class="display-5 mb-3">How It Works</h2>
                    <p class="lead text-muted">Simple steps to get help when you need it</p>
                </div>
            </div>

            <div class="row">
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="card h-100 text-center border-0 shadow-sm">
                        <div class="card-body p-4">
                            <div class="feature-icon bg-primary text-white rounded-circle mx-auto mb-3">
                                <i class="fas fa-user-injured fa-2x"></i>
                            </div>
                            <h5 class="card-title">1. Report Issue</h5>
                            <p class="card-text">Villagers report health problems with photos and detailed descriptions</p>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="card h-100 text-center border-0 shadow-sm">
                        <div class="card-body p-4">
                            <div class="feature-icon bg-success text-white rounded-circle mx-auto mb-3">
                                <i class="fas fa-user-tie fa-2x"></i>
                            </div>
                            <h5 class="card-title">2. ANMS Response</h5>
                            <p class="card-text">ANMS officers review cases and provide initial assistance or escalate</p>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="card h-100 text-center border-0 shadow-sm">
                        <div class="card-body p-4">
                            <div class="feature-icon bg-info text-white rounded-circle mx-auto mb-3">
                                <i class="fas fa-user-md fa-2x"></i>
                            </div>
                            <h5 class="card-title">3. Medical Care</h5>
                            <p class="card-text">If needed, doctors provide expert medical advice and treatment plans</p>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="card h-100 text-center border-0 shadow-sm">
                        <div class="card-body p-4">
                            <div class="feature-icon bg-warning text-white rounded-circle mx-auto mb-3">
                                <i class="fas fa-check-circle fa-2x"></i>
                            </div>
                            <h5 class="card-title">4. Resolution</h5>
                            <p class="card-text">Problems are tracked until complete resolution and follow-up</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- User Roles Section -->
    <section class="py-5">
        <div class="container">
            <div class="row text-center mb-5">
                <div class="col-lg-12">
                    <h2 class="display-5 mb-3">Who Can Use This System?</h2>
                    <p class="lead text-muted">Different roles, unified healthcare</p>
                </div>
            </div>

            <div class="row">
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="card role-card border-primary">
                        <div class="card-header bg-primary text-white text-center">
                            <i class="fas fa-user fa-3x mb-2"></i>
                            <h5>Villager</h5>
                        </div>
                        <div class="card-body">
                            <ul class="list-unstyled">
                                <li><i class="fas fa-check text-primary"></i> Report health issues</li>
                                <li><i class="fas fa-check text-primary"></i> Upload photos</li>
                                <li><i class="fas fa-check text-primary"></i> Track progress</li>
                                <li><i class="fas fa-check text-primary"></i> Get updates</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="card role-card border-success">
                        <div class="card-header bg-success text-white text-center">
                            <i class="fas fa-building fa-3x mb-2"></i>
                            <h5>ANMS Officer</h5>
                        </div>
                        <div class="card-body">
                            <ul class="list-unstyled">
                                <li><i class="fas fa-check text-success"></i> Manage cases</li>
                                <li><i class="fas fa-check text-success"></i> Visit villagers</li>
                                <li><i class="fas fa-check text-success"></i> Resolve locally</li>
                                <li><i class="fas fa-check text-success"></i> Escalate to doctors</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="card role-card border-info">
                        <div class="card-header bg-info text-white text-center">
                            <i class="fas fa-stethoscope fa-3x mb-2"></i>
                            <h5>Doctor</h5>
                        </div>
                        <div class="card-body">
                            <ul class="list-unstyled">
                                <li><i class="fas fa-check text-info"></i> Review escalated cases</li>
                                <li><i class="fas fa-check text-info"></i> Provide medical advice</li>
                                <li><i class="fas fa-check text-info"></i> Schedule visits</li>
                                <li><i class="fas fa-check text-info"></i> Follow-up care</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="card role-card border-warning">
                        <div class="card-header bg-warning text-white text-center">
                            <i class="fas fa-user-shield fa-3x mb-2"></i>
                            <h5>Admin</h5>
                        </div>
                        <div class="card-body">
                            <ul class="list-unstyled">
                                <li><i class="fas fa-check text-warning"></i> System oversight</li>
                                <li><i class="fas fa-check text-warning"></i> User management</li>
                                <li><i class="fas fa-check text-warning"></i> Generate reports</li>
                                <li><i class="fas fa-check text-warning"></i> Monitor performance</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-12 text-center">
                    <h4 class="mb-3">Ready to Get Started?</h4>
                    <a href="login/register.php" class="btn btn-primary btn-lg me-3">
                        <i class="fas fa-user-plus"></i> Create Account
                    </a>
                    <a href="login/login.php" class="btn btn-outline-primary btn-lg">
                        <i class="fas fa-sign-in-alt"></i> Login
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Demo Accounts Section -->
    <section class="py-5 bg-light">
        <div class="container">
            <div class="row">
                <div class="col-lg-8 mx-auto">
                    <div class="card border-0 shadow">
                        <div class="card-body p-4">
                            <h4 class="text-center mb-4">
                                <i class="fas fa-key text-primary"></i> Demo Accounts for Testing
                            </h4>
                            <div class="row">
                                <div class="col-md-6">
                                    <h6><i class="fas fa-user-shield text-warning"></i> Admin</h6>
                                    <p class="mb-1"><strong>Email:</strong> admin@villagehealth.com</p>
                                    <p class="mb-3"><strong>Password:</strong> password</p>

                                    <h6><i class="fas fa-building text-success"></i> ANMS Officer</h6>
                                    <p class="mb-1"><strong>Email:</strong> avms@villagehealth.com</p>
                                    <p><strong>Password:</strong> password</p>
                                </div>
                                <div class="col-md-6">
                                    <h6><i class="fas fa-user-md text-info"></i> Doctor</h6>
                                    <p class="mb-1"><strong>Email:</strong> doctor@villagehealth.com</p>
                                    <p class="mb-3"><strong>Password:</strong> password</p>

                                    <h6><i class="fas fa-user text-primary"></i> Villager</h6>
                                    <p class="mb-1"><strong>Email:</strong> villager@villagehealth.com</p>
                                    <p><strong>Password:</strong> password</p>
                                </div>
                            </div>
                            <div class="alert alert-info mt-3">
                                <i class="fas fa-info-circle"></i>
                                <strong>Note:</strong> Please change the admin password after first login for security.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <footer class="bg-dark text-light py-4">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5><i class="fas fa-hospital-alt"></i> E- Swasthya Connect </h5>
                    <p class="mb-0">Bridging Villagers and Doctors through ANMS</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <p class="mb-0">&copy; <?php echo date('Y'); ?> E- Swasthya Connect .</p>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>