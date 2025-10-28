<?php
require_once '../includes/config.php';
requireRole('admin');

$page_title = 'Create Test User - Admin Tools';

$message = '';
$message_type = '';
$created_user_id = null;

if ($_POST && isset($_POST['create_user'])) {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'villager';
    $phone = trim($_POST['phone'] ?? '');
    $village = trim($_POST['village'] ?? '');
    $status = $_POST['status'] ?? null; // Leave NULL for pending approval

    if (empty($name) || empty($email) || empty($password)) {
        $message = 'Name, email, and password are required.';
        $message_type = 'danger';
    } else {
        try {
            $pdo = getDBConnection();

            // Check if email already exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $message = 'A user with this email already exists.';
                $message_type = 'warning';
            } else {
                // Create the user
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                $stmt = $pdo->prepare("
                    INSERT INTO users (name, email, password, role, phone, village, status, created_at, updated_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                ");
                $stmt->execute([$name, $email, $hashed_password, $role, $phone, $village, $status]);

                $created_user_id = $pdo->lastInsertId();

                // Log the creation
                $stmt = $pdo->prepare("
                    INSERT INTO admin_logs (admin_id, action, target_type, target_id, details, created_at) 
                    VALUES (?, 'create_test_user', 'user', ?, ?, NOW())
                ");
                $stmt->execute([$_SESSION['user_id'], $created_user_id, "Test user '{$name}' created"]);

                $status_text = $status ? $status : 'pending approval';
                $message = "Test user '{$name}' created successfully with status: {$status_text}";
                $message_type = 'success';
            }
        } catch (Exception $e) {
            $message = 'Error creating user: ' . $e->getMessage();
            $message_type = 'danger';
        }
    }
}

// Get current user counts for reference
$user_counts = [];
try {
    $pdo = getDBConnection();
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
            SUM(CASE WHEN status = 'pending' OR status IS NULL OR status = '' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN role = 'doctor' THEN 1 ELSE 0 END) as doctors,
            SUM(CASE WHEN role = 'villager' THEN 1 ELSE 0 END) as villagers,
            SUM(CASE WHEN role = 'avms' THEN 1 ELSE 0 END) as avms
        FROM users WHERE role != 'admin'
    ");
    $user_counts = $stmt->fetch();
} catch (Exception $e) {
    $user_counts = ['total' => 0, 'active' => 0, 'pending' => 0, 'doctors' => 0, 'villagers' => 0, 'avms' => 0];
}

include '../includes/header.php';
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="row">
        <div class="col-12">
            <div class="page-header mb-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1 class="mb-2 text-primary">
                            <i class="fas fa-user-plus me-2"></i>Create Test User
                        </h1>
                        <p class="text-muted mb-0">
                            Create test users to verify the approval system is working correctly
                        </p>
                    </div>
                    <div>
                        <a href="dashboard.php" class="btn btn-outline-primary me-2">
                            <i class="fas fa-arrow-left me-1"></i>Back to Dashboard
                        </a>
                        <a href="approvals.php" class="btn btn-warning">
                            <i class="fas fa-user-check me-1"></i>View Approvals
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
            <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : ($message_type === 'warning' ? 'exclamation-triangle' : 'times-circle'); ?> me-2"></i>
            <?php echo htmlspecialchars($message); ?>
            <?php if ($created_user_id): ?>
                <div class="mt-2">
                    <strong>User ID:</strong> <?php echo $created_user_id; ?>
                    <br>
                    <a href="approvals.php" class="btn btn-success btn-sm mt-1">
                        <i class="fas fa-eye me-1"></i>Check Approvals Page
                    </a>
                </div>
            <?php endif; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Current Stats -->
    <div class="row mb-4">
        <div class="col-md-2">
            <div class="stats-mini bg-primary text-white text-center p-3 rounded">
                <div class="h3 mb-0"><?php echo $user_counts['total']; ?></div>
                <small>Total Users</small>
            </div>
        </div>
        <div class="col-md-2">
            <div class="stats-mini bg-success text-white text-center p-3 rounded">
                <div class="h3 mb-0"><?php echo $user_counts['active']; ?></div>
                <small>Active</small>
            </div>
        </div>
        <div class="col-md-2">
            <div class="stats-mini bg-warning text-dark text-center p-3 rounded">
                <div class="h3 mb-0"><?php echo $user_counts['pending']; ?></div>
                <small>Pending</small>
            </div>
        </div>
        <div class="col-md-2">
            <div class="stats-mini bg-info text-white text-center p-3 rounded">
                <div class="h3 mb-0"><?php echo $user_counts['doctors']; ?></div>
                <small>Doctors</small>
            </div>
        </div>
        <div class="col-md-2">
            <div class="stats-mini bg-secondary text-white text-center p-3 rounded">
                <div class="h3 mb-0"><?php echo $user_counts['avms']; ?></div>
                <small>ANMS</small>
            </div>
        </div>
        <div class="col-md-2">
            <div class="stats-mini bg-dark text-white text-center p-3 rounded">
                <div class="h3 mb-0"><?php echo $user_counts['villagers']; ?></div>
                <small>Villagers</small>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Create User Form -->
        <div class="col-lg-8">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-user-plus me-2"></i>Create New Test User</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="name" class="form-label">Full Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="name" name="name" required
                                           placeholder="Dr. John Smith" value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email Address <span class="text-danger">*</span></label>
                                    <input type="email" class="form-control" id="email" name="email" required
                                           placeholder="doctor@example.com" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="password" class="form-label">Password <span class="text-danger">*</span></label>
                                    <input type="password" class="form-control" id="password" name="password" required
                                           placeholder="Minimum 6 characters" minlength="6">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="role" class="form-label">User Role</label>
                                    <select class="form-select" id="role" name="role">
                                        <option value="villager" <?php echo ($_POST['role'] ?? '') === 'villager' ? 'selected' : ''; ?>>Villager</option>
                                        <option value="doctor" <?php echo ($_POST['role'] ?? '') === 'doctor' ? 'selected' : ''; ?>>Doctor</option>
                                        <option value="avms" <?php echo ($_POST['role'] ?? '') === 'avms' ? 'selected' : ''; ?>>ANMS Officer</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="phone" class="form-label">Phone Number</label>
                                    <input type="tel" class="form-control" id="phone" name="phone"
                                           placeholder="1234567890" value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="village" class="form-label">Village</label>
                                    <input type="text" class="form-control" id="village" name="village"
                                           placeholder="Village name" value="<?php echo htmlspecialchars($_POST['village'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="status" class="form-label">Initial Status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="">Pending Approval (NULL - Default)</option>
                                <option value="pending">Pending (Explicit)</option>
                                <option value="active">Active (Pre-approved)</option>
                                <option value="inactive">Inactive</option>
                            </select>
                            <div class="form-text">
                                Choose "Pending Approval" to test the approval system. 
                                The user will appear in the approvals queue.
                            </div>
                        </div>

                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <button type="submit" name="create_user" class="btn btn-primary btn-lg">
                                <i class="fas fa-user-plus me-2"></i>Create Test User
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Quick Actions and Info -->
        <div class="col-lg-4">
            <!-- Quick Test Users -->
            <div class="card shadow mb-4">
                <div class="card-header bg-success text-white">
                    <h6 class="mb-0"><i class="fas fa-bolt me-2"></i>Quick Test Users</h6>
                </div>
                <div class="card-body">
                    <p class="small">Click to create pre-configured test users:</p>

                    <div class="d-grid gap-2">
                        <button class="btn btn-outline-success btn-sm" onclick="createQuickUser('doctor')">
                            <i class="fas fa-user-md me-1"></i>Create Test Doctor
                        </button>
                        <button class="btn btn-outline-primary btn-sm" onclick="createQuickUser('avms')">
                            <i class="fas fa-user-tie me-1"></i>Create Test ANMS
                        </button>
                        <button class="btn btn-outline-info btn-sm" onclick="createQuickUser('villager')">
                            <i class="fas fa-user me-1"></i>Create Test Villager
                        </button>
                    </div>
                </div>
            </div>

            <!-- Testing Guide -->
            <div class="card shadow">
                <div class="card-header bg-info text-white">
                    <h6 class="mb-0"><i class="fas fa-question-circle me-2"></i>Testing Guide</h6>
                </div>
                <div class="card-body">
                    <h6>How to Test Approvals:</h6>
                    <ol class="small">
                        <li><strong>Create a test user</strong> with "Pending Approval" status</li>
                        <li><strong>Check the dashboard</strong> - pending count should increase</li>
                        <li><strong>Visit approvals page</strong> - new user should appear</li>
                        <li><strong>Test approval buttons</strong> - approve or reject</li>
                        <li><strong>Verify the process</strong> - user status should update</li>
                    </ol>

                    <div class="alert alert-warning alert-sm">
                        <i class="fas fa-lightbulb me-1"></i>
                        <strong>Tip:</strong> Create users with NULL status to simulate real registrations.
                    </div>

                    <div class="mt-3">
                        <a href="approvals.php" class="btn btn-warning btn-sm w-100">
                            <i class="fas fa-eye me-1"></i>Check Approvals Page
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

.stats-mini {
    transition: all 0.3s ease;
}

.stats-mini:hover {
    transform: translateY(-3px);
    box-shadow: 0 6px 20px rgba(0,0,0,0.15);
}

.card {
    transition: all 0.3s ease;
}

.card:hover {
    transform: translateY(-2px);
}

.form-control:focus, .form-select:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
}

.alert-sm {
    padding: 0.5rem;
    font-size: 0.875rem;
}
</style>

<script>
function createQuickUser(role) {
    const timestamp = Date.now();
    const roleData = {
        'doctor': {
            name: 'Dr. Test User ' + timestamp,
            email: 'testdoctor' + timestamp + '@example.com',
            phone: '9876543210',
            village: 'Test Village'
        },
        'avms': {
            name: 'ANMS Officer ' + timestamp,
            email: 'testavms' + timestamp + '@example.com',
            phone: '9876543211',
            village: 'Test District'
        },
        'villager': {
            name: 'Test Villager ' + timestamp,
            email: 'testvillager' + timestamp + '@example.com',
            phone: '9876543212',
            village: 'Test Village'
        }
    };

    const data = roleData[role];
    if (data) {
        document.getElementById('name').value = data.name;
        document.getElementById('email').value = data.email;
        document.getElementById('password').value = 'test123';
        document.getElementById('role').value = role;
        document.getElementById('phone').value = data.phone;
        document.getElementById('village').value = data.village;
        document.getElementById('status').value = ''; // Pending approval

        // Auto-submit if user wants
        if (confirm(`Create ${role} user: ${data.name}?`)) {
            document.querySelector('form').submit();
        }
    }
}

// Generate random data for testing
document.addEventListener('DOMContentLoaded', function() {
    // Auto-generate password if empty
    document.getElementById('password').addEventListener('focus', function() {
        if (!this.value) {
            this.value = 'test123';
        }
    });

    // Auto-generate phone if empty
    document.getElementById('phone').addEventListener('focus', function() {
        if (!this.value) {
            this.value = '98765' + Math.floor(Math.random() * 90000 + 10000);
        }
    });
});
</script>

<?php include '../includes/footer.php'; ?>