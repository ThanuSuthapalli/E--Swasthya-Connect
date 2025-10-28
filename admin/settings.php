<?php
require_once '../includes/config.php';
requireRole('admin');

$page_title = 'System Settings - Administration';

// Handle settings updates
$message = '';
$message_type = '';

if ($_POST) {
    $action = $_POST['action'] ?? '';

    try {
        $pdo = getDBConnection();

        switch ($action) {
            case 'update_general':
                $settings = [
                    'site_name' => $_POST['site_name'] ?? 'Village Health Connect',
                    'site_description' => $_POST['site_description'] ?? '',
                    'admin_email' => $_POST['admin_email'] ?? '',
                    'maintenance_mode' => isset($_POST['maintenance_mode']) ? 1 : 0,
                    'user_registration' => isset($_POST['user_registration']) ? 1 : 0
                ];

                foreach ($settings as $key => $value) {
                    $stmt = $pdo->prepare("
                        INSERT INTO system_settings (setting_key, setting_value, updated_at) 
                        VALUES (?, ?, NOW()) 
                        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()
                    ");
                    $stmt->execute([$key, $value]);
                }

                $message = 'General settings updated successfully.';
                $message_type = 'success';
                break;

            case 'update_notifications':
                $settings = [
                    'email_notifications' => isset($_POST['email_notifications']) ? 1 : 0,
                    'sms_notifications' => isset($_POST['sms_notifications']) ? 1 : 0,
                    'urgent_alert_email' => $_POST['urgent_alert_email'] ?? '',
                    'notification_frequency' => $_POST['notification_frequency'] ?? 'immediate'
                ];

                foreach ($settings as $key => $value) {
                    $stmt = $pdo->prepare("
                        INSERT INTO system_settings (setting_key, setting_value, updated_at) 
                        VALUES (?, ?, NOW()) 
                        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()
                    ");
                    $stmt->execute([$key, $value]);
                }

                $message = 'Notification settings updated successfully.';
                $message_type = 'success';
                break;

            case 'update_security':
                $settings = [
                    'password_min_length' => max(6, (int)($_POST['password_min_length'] ?? 8)),
                    'session_timeout' => max(15, (int)($_POST['session_timeout'] ?? 60)),
                    'max_login_attempts' => max(3, (int)($_POST['max_login_attempts'] ?? 5)),
                    'require_email_verification' => isset($_POST['require_email_verification']) ? 1 : 0
                ];

                foreach ($settings as $key => $value) {
                    $stmt = $pdo->prepare("
                        INSERT INTO system_settings (setting_key, setting_value, updated_at) 
                        VALUES (?, ?, NOW()) 
                        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()
                    ");
                    $stmt->execute([$key, $value]);
                }

                $message = 'Security settings updated successfully.';
                $message_type = 'success';
                break;
        }

        // Log admin action
        if ($message_type === 'success') {
            $stmt = $pdo->prepare("
                INSERT INTO admin_logs (admin_id, action, target_type, target_id, details, created_at) 
                VALUES (?, ?, 'settings', 0, ?, NOW())
            ");
            $stmt->execute([$_SESSION['user_id'], $action, $message]);
        }

    } catch (Exception $e) {
        $message = 'Error updating settings: ' . $e->getMessage();
        $message_type = 'danger';
    }
}

// Get current settings
$current_settings = [];
try {
    $pdo = getDBConnection();
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
    while ($row = $stmt->fetch()) {
        $current_settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (Exception $e) {
    error_log("Error fetching settings: " . $e->getMessage());
}

// Default values
$defaults = [
    'site_name' => 'Village Health Connect',
    'site_description' => 'Connecting villages with healthcare professionals',
    'admin_email' => 'admin@example.com',
    'maintenance_mode' => 0,
    'user_registration' => 1,
    'email_notifications' => 1,
    'sms_notifications' => 0,
    'urgent_alert_email' => '',
    'notification_frequency' => 'immediate',
    'password_min_length' => 8,
    'session_timeout' => 60,
    'max_login_attempts' => 5,
    'require_email_verification' => 0
];

// Merge with current settings
foreach ($defaults as $key => $default_value) {
    if (!isset($current_settings[$key])) {
        $current_settings[$key] = $default_value;
    }
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
                            <i class="fas fa-cog me-2"></i>System Settings
                        </h1>
                        <p class="text-muted mb-0">
                            Configure system-wide settings and preferences for Village Health Connect
                        </p>
                    </div>
                    <div>
                        <a href="dashboard.php" class="btn btn-outline-primary me-2">
                            <i class="fas fa-arrow-left me-1"></i>Back to Dashboard
                        </a>
                        <a href="backup.php" class="btn btn-warning">
                            <i class="fas fa-database me-1"></i>Backup System
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
            <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'times-circle'; ?> me-2"></i>
            <?php echo htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <!-- Settings Navigation -->
        <div class="col-lg-3 mb-4">
            <div class="card shadow">
                <div class="card-header bg-secondary text-white">
                    <h5 class="mb-0">Settings Categories</h5>
                </div>
                <div class="list-group list-group-flush">
                    <a href="#general" class="list-group-item list-group-item-action active" data-bs-toggle="pill">
                        <i class="fas fa-cog me-2"></i>General Settings
                    </a>
                    <a href="#notifications" class="list-group-item list-group-item-action" data-bs-toggle="pill">
                        <i class="fas fa-bell me-2"></i>Notifications
                    </a>
                    <a href="#security" class="list-group-item list-group-item-action" data-bs-toggle="pill">
                        <i class="fas fa-shield-alt me-2"></i>Security
                    </a>
                    <a href="#maintenance" class="list-group-item list-group-item-action" data-bs-toggle="pill">
                        <i class="fas fa-tools me-2"></i>Maintenance
                    </a>
                </div>
            </div>

            <!-- System Information -->
            <div class="card shadow mt-4">
                <div class="card-header bg-info text-white">
                    <h6 class="mb-0">System Information</h6>
                </div>
                <div class="card-body">
                    <div class="system-info">
                        <p><strong>Version:</strong> 1.0.0</p>
                        <p><strong>PHP Version:</strong> <?php echo PHP_VERSION; ?></p>
                        <p><strong>Database:</strong> MySQL</p>
                        <p><strong>Server:</strong> <?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'; ?></p>
                        <p><strong>Last Backup:</strong> 
                            <span class="text-muted">No backup yet</span>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Settings Content -->
        <div class="col-lg-9">
            <div class="tab-content">
                <!-- General Settings -->
                <div class="tab-pane fade show active" id="general">
                    <div class="card shadow">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="fas fa-cog me-2"></i>General Settings</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="">
                                <input type="hidden" name="action" value="update_general">

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="site_name" class="form-label">Site Name</label>
                                            <input type="text" class="form-control" id="site_name" name="site_name" 
                                                   value="<?php echo htmlspecialchars($current_settings['site_name']); ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="admin_email" class="form-label">Admin Email</label>
                                            <input type="email" class="form-control" id="admin_email" name="admin_email" 
                                                   value="<?php echo htmlspecialchars($current_settings['admin_email']); ?>" required>
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="site_description" class="form-label">Site Description</label>
                                    <textarea class="form-control" id="site_description" name="site_description" rows="3"><?php echo htmlspecialchars($current_settings['site_description']); ?></textarea>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-check mb-3">
                                            <input class="form-check-input" type="checkbox" id="maintenance_mode" name="maintenance_mode" 
                                                   <?php echo $current_settings['maintenance_mode'] ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="maintenance_mode">
                                                <strong>Maintenance Mode</strong>
                                                <div class="form-text">Temporarily disable public access to the system</div>
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-check mb-3">
                                            <input class="form-check-input" type="checkbox" id="user_registration" name="user_registration" 
                                                   <?php echo $current_settings['user_registration'] ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="user_registration">
                                                <strong>Allow User Registration</strong>
                                                <div class="form-text">Enable new users to register accounts</div>
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-1"></i>Save General Settings
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Notification Settings -->
                <div class="tab-pane fade" id="notifications">
                    <div class="card shadow">
                        <div class="card-header bg-warning text-dark">
                            <h5 class="mb-0"><i class="fas fa-bell me-2"></i>Notification Settings</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="">
                                <input type="hidden" name="action" value="update_notifications">

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-check mb-3">
                                            <input class="form-check-input" type="checkbox" id="email_notifications" name="email_notifications" 
                                                   <?php echo $current_settings['email_notifications'] ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="email_notifications">
                                                <strong>Email Notifications</strong>
                                                <div class="form-text">Send email alerts for important events</div>
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-check mb-3">
                                            <input class="form-check-input" type="checkbox" id="sms_notifications" name="sms_notifications" 
                                                   <?php echo $current_settings['sms_notifications'] ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="sms_notifications">
                                                <strong>SMS Notifications</strong>
                                                <div class="form-text">Send SMS alerts for urgent cases</div>
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="urgent_alert_email" class="form-label">Urgent Alert Email</label>
                                            <input type="email" class="form-control" id="urgent_alert_email" name="urgent_alert_email" 
                                                   value="<?php echo htmlspecialchars($current_settings['urgent_alert_email']); ?>"
                                                   placeholder="admin@example.com">
                                            <div class="form-text">Email address to receive urgent case alerts</div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="notification_frequency" class="form-label">Notification Frequency</label>
                                            <select class="form-select" id="notification_frequency" name="notification_frequency">
                                                <option value="immediate" <?php echo $current_settings['notification_frequency'] === 'immediate' ? 'selected' : ''; ?>>Immediate</option>
                                                <option value="hourly" <?php echo $current_settings['notification_frequency'] === 'hourly' ? 'selected' : ''; ?>>Hourly Digest</option>
                                                <option value="daily" <?php echo $current_settings['notification_frequency'] === 'daily' ? 'selected' : ''; ?>>Daily Summary</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                    <button type="submit" class="btn btn-warning text-dark">
                                        <i class="fas fa-save me-1"></i>Save Notification Settings
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Security Settings -->
                <div class="tab-pane fade" id="security">
                    <div class="card shadow">
                        <div class="card-header bg-danger text-white">
                            <h5 class="mb-0"><i class="fas fa-shield-alt me-2"></i>Security Settings</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="">
                                <input type="hidden" name="action" value="update_security">

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="password_min_length" class="form-label">Minimum Password Length</label>
                                            <input type="number" class="form-control" id="password_min_length" name="password_min_length" 
                                                   value="<?php echo $current_settings['password_min_length']; ?>" min="6" max="20">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="session_timeout" class="form-label">Session Timeout (minutes)</label>
                                            <input type="number" class="form-control" id="session_timeout" name="session_timeout" 
                                                   value="<?php echo $current_settings['session_timeout']; ?>" min="15" max="480">
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="max_login_attempts" class="form-label">Max Login Attempts</label>
                                            <input type="number" class="form-control" id="max_login_attempts" name="max_login_attempts" 
                                                   value="<?php echo $current_settings['max_login_attempts']; ?>" min="3" max="10">
                                            <div class="form-text">Number of failed attempts before account lockout</div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-check mt-4">
                                            <input class="form-check-input" type="checkbox" id="require_email_verification" name="require_email_verification" 
                                                   <?php echo $current_settings['require_email_verification'] ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="require_email_verification">
                                                <strong>Require Email Verification</strong>
                                                <div class="form-text">Users must verify email before activation</div>
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                <div class="alert alert-warning">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    <strong>Warning:</strong> Changes to security settings will affect all users. Please ensure you understand the implications before making changes.
                                </div>

                                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                    <button type="submit" class="btn btn-danger">
                                        <i class="fas fa-save me-1"></i>Save Security Settings
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Maintenance -->
                <div class="tab-pane fade" id="maintenance">
                    <div class="card shadow">
                        <div class="card-header bg-secondary text-white">
                            <h5 class="mb-0"><i class="fas fa-tools me-2"></i>System Maintenance</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-4">
                                    <div class="maintenance-card">
                                        <div class="card border-primary">
                                            <div class="card-header bg-primary text-white">
                                                <h6 class="mb-0"><i class="fas fa-database me-2"></i>Database Maintenance</h6>
                                            </div>
                                            <div class="card-body">
                                                <p>Optimize database performance and clean up old data.</p>
                                                <div class="d-grid">
                                                    <button class="btn btn-primary" onclick="optimizeDatabase()">
                                                        <i class="fas fa-cog me-1"></i>Optimize Database
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-6 mb-4">
                                    <div class="maintenance-card">
                                        <div class="card border-success">
                                            <div class="card-header bg-success text-white">
                                                <h6 class="mb-0"><i class="fas fa-trash me-2"></i>Clear Cache</h6>
                                            </div>
                                            <div class="card-body">
                                                <p>Clear system cache to improve performance.</p>
                                                <div class="d-grid">
                                                    <button class="btn btn-success" onclick="clearCache()">
                                                        <i class="fas fa-broom me-1"></i>Clear Cache
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-6 mb-4">
                                    <div class="maintenance-card">
                                        <div class="card border-warning">
                                            <div class="card-header bg-warning text-dark">
                                                <h6 class="mb-0"><i class="fas fa-file-alt me-2"></i>System Logs</h6>
                                            </div>
                                            <div class="card-body">
                                                <p>View and manage system error logs.</p>
                                                <div class="d-grid">
                                                    <button class="btn btn-warning text-dark" onclick="viewLogs()">
                                                        <i class="fas fa-eye me-1"></i>View Logs
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-6 mb-4">
                                    <div class="maintenance-card">
                                        <div class="card border-danger">
                                            <div class="card-header bg-danger text-white">
                                                <h6 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i>Reset System</h6>
                                            </div>
                                            <div class="card-body">
                                                <p>Reset system to default settings (USE WITH CAUTION).</p>
                                                <div class="d-grid">
                                                    <button class="btn btn-danger" onclick="confirmReset()">
                                                        <i class="fas fa-redo me-1"></i>Reset System
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
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

.maintenance-card .card {
    transition: all 0.3s ease;
}

.maintenance-card .card:hover {
    transform: translateY(-3px);
    box-shadow: 0 6px 20px rgba(0,0,0,0.15);
}

.system-info p {
    margin-bottom: 8px;
    font-size: 0.9rem;
}

.list-group-item {
    transition: all 0.3s ease;
}

.list-group-item:hover {
    background-color: #f8f9fa;
    transform: translateX(5px);
}

.form-check-label .form-text {
    color: #6c757d;
    font-size: 0.875rem;
}

@media (max-width: 768px) {
    .maintenance-card {
        margin-bottom: 15px;
    }
}
</style>

<script>
function optimizeDatabase() {
    if (confirm('This will optimize the database. Continue?')) {
        // Show loading state
        const btn = event.target;
        const originalText = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Optimizing...';
        btn.disabled = true;

        // Simulate optimization (replace with actual AJAX call)
        setTimeout(() => {
            btn.innerHTML = '<i class="fas fa-check me-1"></i>Optimized!';
            btn.classList.remove('btn-primary');
            btn.classList.add('btn-success');

            setTimeout(() => {
                btn.innerHTML = originalText;
                btn.classList.remove('btn-success');
                btn.classList.add('btn-primary');
                btn.disabled = false;
            }, 2000);
        }, 3000);
    }
}

function clearCache() {
    if (confirm('Clear system cache?')) {
        const btn = event.target;
        const originalText = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Clearing...';
        btn.disabled = true;

        setTimeout(() => {
            btn.innerHTML = '<i class="fas fa-check me-1"></i>Cleared!';

            setTimeout(() => {
                btn.innerHTML = originalText;
                btn.disabled = false;
            }, 2000);
        }, 1500);
    }
}

function viewLogs() {
    window.open('system_logs.php', '_blank', 'width=1000,height=600');
}

function confirmReset() {
    if (confirm('WARNING: This will reset ALL system settings to defaults. This action cannot be undone. Continue?')) {
        if (confirm('Are you absolutely sure? This will affect all users and system configuration.')) {
            alert('System reset functionality would be implemented here with proper safeguards.');
        }
    }
}

// Initialize tabs
document.addEventListener('DOMContentLoaded', function() {
    const hash = window.location.hash;
    if (hash) {
        const tab = document.querySelector(`a[href="${hash}"]`);
        if (tab) {
            tab.click();
        }
    }

    // Update URL when tab changes
    document.querySelectorAll('a[data-bs-toggle="pill"]').forEach(tab => {
        tab.addEventListener('shown.bs.tab', function(e) {
            const href = e.target.getAttribute('href');
            if (href) {
                window.location.hash = href;
            }
        });
    });
});
</script>

<?php include '../includes/footer.php'; ?>