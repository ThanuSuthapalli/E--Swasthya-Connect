<?php
require_once '../includes/config.php';
requireRole('admin');

$page_title = 'Database Setup - System Administration';

$message = '';
$message_type = '';

// Handle database setup
if ($_POST && isset($_POST['setup_database'])) {
    try {
        $pdo = getDBConnection();
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Read and execute the setup.sql file
        $sqlFile = '../sql/setup.sql';
        if (!file_exists($sqlFile)) {
            throw new Exception('Setup SQL file not found at: ' . $sqlFile);
        }
        
        $sql = file_get_contents($sqlFile);
        if ($sql === false) {
            throw new Exception('Could not read setup SQL file');
        }
        
        // Split SQL into individual statements
        $statements = array_filter(array_map('trim', explode(';', $sql)));
        
        $successCount = 0;
        $errorCount = 0;
        $errors = [];
        
        foreach ($statements as $statement) {
            if (empty($statement) || strpos($statement, '--') === 0) {
                continue;
            }
            
            try {
                $pdo->exec($statement);
                $successCount++;
            } catch (Exception $e) {
                $errorCount++;
                $errors[] = $e->getMessage();
            }
        }
        
        if ($errorCount === 0) {
            $message = "Database setup completed successfully! $successCount statements executed.";
            $message_type = 'success';
        } else {
            $message = "Database setup completed with $errorCount errors. $successCount statements executed successfully.";
            $message_type = 'warning';
            if (!empty($errors)) {
                $message .= '<br><strong>Errors:</strong><br>' . implode('<br>', array_slice($errors, 0, 5));
                if (count($errors) > 5) {
                    $message .= '<br>... and ' . (count($errors) - 5) . ' more errors';
                }
            }
        }
        
    } catch (Exception $e) {
        $message = 'Database setup failed: ' . $e->getMessage();
        $message_type = 'danger';
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
                            <i class="fas fa-database me-2"></i>Database Setup
                        </h1>
                        <p class="text-muted mb-0">
                            Set up the Village Health Connect database and tables
                        </p>
                    </div>
                    <div>
                        <a href="dashboard.php" class="btn btn-outline-primary me-2">
                            <i class="fas fa-arrow-left me-1"></i>Back to Dashboard
                        </a>
                        <a href="all_problems.php" class="btn btn-success">
                            <i class="fas fa-clipboard-list me-1"></i>All Problems
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
            <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : ($message_type === 'warning' ? 'exclamation-triangle' : 'times-circle'); ?> me-2"></i>
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Database Setup Instructions -->
    <div class="row">
        <div class="col-md-8">
            <div class="card shadow">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-cogs me-2"></i>Database Setup Instructions
                    </h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <h6><i class="fas fa-info-circle me-2"></i>What this will do:</h6>
                        <ul class="mb-0">
                            <li>Create the <code>village_health_connect</code> database</li>
                            <li>Create all required tables (users, problems, notifications, etc.)</li>
                            <li>Insert sample data for testing</li>
                            <li>Set up default admin user</li>
                        </ul>
                    </div>

                    <h6>Option 1: Automatic Setup (Recommended)</h6>
                    <p>Click the button below to automatically set up the database:</p>
                    
                    <form method="POST" class="d-inline">
                        <button type="submit" name="setup_database" class="btn btn-primary btn-lg">
                            <i class="fas fa-magic me-2"></i>Setup Database Automatically
                        </button>
                    </form>

                    <hr>

                    <h6>Option 2: Manual Setup</h6>
                    <p>If you prefer to set up the database manually:</p>
                    <ol>
                        <li>Open phpMyAdmin in your browser</li>
                        <li>Go to the "Import" tab</li>
                        <li>Select the <code>sql/setup.sql</code> file</li>
                        <li>Click "Go" to execute the SQL</li>
                    </ol>

                    <div class="mt-3">
                        <a href="../sql/setup.sql" class="btn btn-outline-primary" target="_blank">
                            <i class="fas fa-download me-1"></i>Download Setup SQL File
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card shadow">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-info-circle me-2"></i>System Requirements
                    </h5>
                </div>
                <div class="card-body">
                    <h6>Database Requirements:</h6>
                    <ul class="list-unstyled">
                        <li><i class="fas fa-check text-success me-2"></i>MySQL 5.7+ or MariaDB 10.2+</li>
                        <li><i class="fas fa-check text-success me-2"></i>PHP 7.4+</li>
                        <li><i class="fas fa-check text-success me-2"></i>PDO MySQL extension</li>
                    </ul>

                    <h6>Default Login Credentials:</h6>
                    <div class="alert alert-warning">
                        <small>
                            <strong>Admin:</strong> admin@villagehealth.com / password<br>
                            <strong>ANMS:</strong> avms@villagehealth.com / password<br>
                            <strong>Doctor:</strong> doctor@villagehealth.com / password<br>
                            <strong>Villager:</strong> villager@villagehealth.com / password
                        </small>
                    </div>

                    <div class="mt-3">
                        <a href="../login/login.php" class="btn btn-success w-100">
                            <i class="fas fa-sign-in-alt me-1"></i>Go to Login
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
