<?php
// Enhanced Admin Database Setup with Debugging
require_once '../includes/config.php';

echo "<h2>🔧 Enhanced Admin Database Setup & Debugging</h2>";

try {
    $pdo = getDBConnection();

    echo "<div style='background: #e8f5e8; padding: 15px; margin: 10px 0; border-radius: 5px;'>";
    echo "<h3>✅ Database Connection Successful</h3>";
    echo "<p>Connected to database successfully.</p>";
    echo "</div>";

    // Create system_settings table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS system_settings (
            id INT PRIMARY KEY AUTO_INCREMENT,
            setting_key VARCHAR(100) UNIQUE NOT NULL,
            setting_value TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_setting_key (setting_key)
        )
    ");
    echo "<p>✅ System settings table created/verified</p>";

    // Create admin_logs table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS admin_logs (
            id INT PRIMARY KEY AUTO_INCREMENT,
            admin_id INT NOT NULL,
            action VARCHAR(50) NOT NULL,
            target_type VARCHAR(50),
            target_id INT,
            details TEXT,
            ip_address VARCHAR(45) DEFAULT '',
            user_agent TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_admin_id (admin_id),
            INDEX idx_action (action),
            INDEX idx_created_at (created_at)
        )
    ");
    echo "<p>✅ Admin logs table created/verified</p>";

    // Check current users and their statuses
    echo "<div style='background: #fff3cd; padding: 15px; margin: 10px 0; border-radius: 5px;'>";
    echo "<h3>📊 Current User Status Analysis</h3>";

    $stmt = $pdo->query("
        SELECT 
            role,
            status,
            COUNT(*) as count
        FROM users 
        GROUP BY role, status
        ORDER BY role, status
    ");
    $user_analysis = $stmt->fetchAll();

    echo "<table border='1' style='border-collapse: collapse; width: 100%; margin: 10px 0;'>";
    echo "<tr style='background: #f8f9fa;'><th>Role</th><th>Status</th><th>Count</th></tr>";

    foreach ($user_analysis as $row) {
        $status_display = $row['status'] ?: 'NULL (pending)';
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['role']) . "</td>";
        echo "<td>" . htmlspecialchars($status_display) . "</td>";
        echo "<td>" . $row['count'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";

    // Check for users that should appear in pending approvals
    $stmt = $pdo->query("
        SELECT id, name, email, role, status, created_at 
        FROM users 
        WHERE (status = 'pending' OR status IS NULL OR status = '') AND role != 'admin'
        ORDER BY created_at DESC
    ");
    $pending_users = $stmt->fetchAll();

    echo "<h4>🔍 Users That Should Appear in Pending Approvals:</h4>";
    if (empty($pending_users)) {
        echo "<div style='background: #d4edda; padding: 10px; border-radius: 5px;'>";
        echo "✅ <strong>No pending users found.</strong> All users have been processed.";
        echo "</div>";
    } else {
        echo "<div style='background: #f8d7da; padding: 10px; border-radius: 5px; margin: 10px 0;'>";
        echo "⚠️ <strong>Found " . count($pending_users) . " user(s) that should appear in pending approvals:</strong>";
        echo "<ul>";
        foreach ($pending_users as $user) {
            $status_text = $user['status'] ?: 'NULL (should be pending)';
            echo "<li>" . htmlspecialchars($user['name']) . " (" . htmlspecialchars($user['email']) . ") - Role: " . $user['role'] . " - Status: " . $status_text . " - Registered: " . $user['created_at'] . "</li>";
        }
        echo "</ul>";
        echo "</div>";
    }
    echo "</div>";

    // Insert default system settings
    $default_settings = [
        ['site_name', 'Village Health Connect'],
        ['site_description', 'Connecting villages with healthcare professionals'],
        ['admin_email', 'admin@villagehealth.com'],
        ['maintenance_mode', '0'],
        ['user_registration', '1'],
        ['email_notifications', '1'],
        ['sms_notifications', '0'],
        ['notification_frequency', 'immediate'],
        ['password_min_length', '8'],
        ['session_timeout', '60'],
        ['max_login_attempts', '5'],
        ['require_email_verification', '0']
    ];

    $stmt = $pdo->prepare("
        INSERT IGNORE INTO system_settings (setting_key, setting_value) 
        VALUES (?, ?)
    ");

    $settings_inserted = 0;
    foreach ($default_settings as $setting) {
        $stmt->execute($setting);
        if ($stmt->rowCount() > 0) {
            $settings_inserted++;
        }
    }
    echo "<p>✅ Default system settings: $settings_inserted new settings inserted</p>";

    // Ensure admin user exists
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = 'admin'");
    $stmt->execute();
    $admin_count = $stmt->fetchColumn();

    echo "<div style='background: #e2e3e5; padding: 15px; margin: 10px 0; border-radius: 5px;'>";
    echo "<h3>👤 Admin User Status</h3>";

    if ($admin_count == 0) {
        $admin_password = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("
            INSERT INTO users (name, email, password, role, status, created_at) 
            VALUES ('System Administrator', 'admin@villagehealth.com', ?, 'admin', 'active', NOW())
        ");
        $stmt->execute([$admin_password]);
        echo "<div style='background: #fff3cd; padding: 10px; border-radius: 5px;'>";
        echo "✅ <strong>Default admin user created:</strong><br>";
        echo "📧 Email: admin@villagehealth.com<br>";
        echo "🔑 Password: admin123<br>";
        echo "<strong>⚠️ Please change the admin password immediately after login!</strong>";
        echo "</div>";
    } else {
        echo "<div style='background: #d4edda; padding: 10px; border-radius: 5px;'>";
        echo "✅ Admin user(s) already exist: $admin_count admin(s) found";
        echo "</div>";
    }
    echo "</div>";

    // Create backup directory if it doesn't exist
    $backup_dir = '../backups';
    if (!file_exists($backup_dir)) {
        mkdir($backup_dir, 0755, true);
        echo "<p>✅ Backup directory created: $backup_dir</p>";
    } else {
        echo "<p>✅ Backup directory exists: $backup_dir</p>";
    }

    // Test database queries that admin pages use
    echo "<div style='background: #cff4fc; padding: 15px; margin: 10px 0; border-radius: 5px;'>";
    echo "<h3>🧪 Testing Admin Page Queries</h3>";

    try {
        // Test dashboard query
        $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE (status = 'pending' OR status IS NULL OR status = '') AND role != 'admin'");
        $pending_count = $stmt->fetchColumn();
        echo "<p>✅ Dashboard pending count query: $pending_count pending users</p>";

        // Test manage users query
        $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE status != 'deleted' OR status IS NULL");
        $manageable_count = $stmt->fetchColumn();
        echo "<p>✅ Manage users query: $manageable_count manageable users</p>";

        // Test approvals query
        $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE (status = 'pending' OR status IS NULL OR status = '') AND role != 'admin'");
        $approvals_count = $stmt->fetchColumn();
        echo "<p>✅ Approvals page query: $approvals_count users for approval</p>";

    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Query test failed: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
    echo "</div>";

    echo "<div style='background: #d1e7dd; padding: 20px; margin: 20px 0; border-radius: 10px; border: 2px solid #198754;'>";
    echo "<h2>🎉 Setup Complete!</h2>";
    echo "<p><strong>Your admin system is ready to use:</strong></p>";
    echo "<ul>";
    echo "<li>📊 <a href='dashboard.php' style='color: #0d6efd; text-decoration: none;'><strong>Admin Dashboard</strong></a> - Main admin interface</li>";
    echo "<li>✅ <a href='approvals.php' style='color: #198754; text-decoration: none;'><strong>User Approvals</strong></a> - Approve new registrations</li>";
    echo "<li>👥 <a href='manage_users.php' style='color: #6f42c1; text-decoration: none;'><strong>Manage Users</strong></a> - User management with enhanced search</li>";
    echo "<li>📋 <a href='all_problems.php' style='color: #fd7e14; text-decoration: none;'><strong>All Problems</strong></a> - Problem management</li>";
    echo "<li>📈 <a href='reports.php' style='color: #20c997; text-decoration: none;'><strong>Reports</strong></a> - System analytics</li>";
    echo "</ul>";

    if ($pending_count > 0) {
        echo "<div style='background: #fff3cd; padding: 15px; margin: 10px 0; border-radius: 5px; border: 2px solid #ffc107;'>";
        echo "<h4>⚠️ Action Required!</h4>";
        echo "<p><strong>$pending_count user(s) are waiting for approval.</strong></p>";
        echo "<p><a href='approvals.php' class='btn' style='background: #ffc107; color: #000; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Review Pending Approvals Now →</a></p>";
        echo "</div>";
    }

    echo "</div>";

} catch (Exception $e) {
    echo "<div style='background: #f8d7da; padding: 20px; margin: 20px 0; border-radius: 10px; border: 2px solid #dc3545;'>";
    echo "<h3>❌ Setup Error:</h3>";
    echo "<p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><strong>Please check:</strong></p>";
    echo "<ul>";
    echo "<li>Database connection settings in config.php</li>";
    echo "<li>Database user permissions</li>";
    echo "<li>PHP error logs for more details</li>";
    echo "</ul>";
    echo "</div>";
}
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
h2, h3, h4 { color: #333; }
table { margin: 10px 0; }
th, td { padding: 8px; border: 1px solid #ddd; }
th { background-color: #f8f9fa; }
.btn { 
    display: inline-block; 
    padding: 8px 16px; 
    text-decoration: none; 
    border-radius: 4px; 
    font-weight: bold;
}
</style>