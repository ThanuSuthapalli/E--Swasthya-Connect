<?php
require_once '../includes/config.php';
requireRole('avms');

$page_title = 'Generate Reports - Village Health Connect';

try {
    $pdo = getDBConnection();

    // Get report data
    $report_data = [];

    // Overall statistics
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM problems");
    $stmt->execute();
    $report_data['total_problems'] = $stmt->fetchColumn();

    // Status breakdown
    $stmt = $pdo->prepare("SELECT status, COUNT(*) as count FROM problems GROUP BY status");
    $stmt->execute();
    $status_counts = $stmt->fetchAll();

    // Priority breakdown
    $stmt = $pdo->prepare("SELECT priority, COUNT(*) as count FROM problems GROUP BY priority");
    $stmt->execute();
    $priority_counts = $stmt->fetchAll();

    // Monthly breakdown (last 6 months)
    $stmt = $pdo->prepare("
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as month,
            COUNT(*) as count
        FROM problems 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY month DESC
    ");
    $stmt->execute();
    $monthly_counts = $stmt->fetchAll();

    // AVMS performance
    $stmt = $pdo->prepare("
        SELECT 
            u.name,
            COUNT(p.id) as assigned_problems,
            SUM(CASE WHEN p.status = 'resolved' THEN 1 ELSE 0 END) as resolved_problems,
            SUM(CASE WHEN p.status = 'escalated' THEN 1 ELSE 0 END) as escalated_problems
        FROM users u
        LEFT JOIN problems p ON u.id = p.assigned_to
        WHERE u.role = 'avms' AND u.status = 'active'
        GROUP BY u.id, u.name
        ORDER BY assigned_problems DESC
    ");
    $stmt->execute();
    $avms_performance = $stmt->fetchAll();

    // Village breakdown
    $stmt = $pdo->prepare("
        SELECT 
            u.village,
            COUNT(p.id) as problems_reported
        FROM users u
        JOIN problems p ON u.id = p.villager_id
        GROUP BY u.village
        ORDER BY problems_reported DESC
        LIMIT 10
    ");
    $stmt->execute();
    $village_breakdown = $stmt->fetchAll();

} catch (Exception $e) {
    error_log("Reports error: " . $e->getMessage());
    $report_data = ['total_problems' => 0];
    $status_counts = $priority_counts = $monthly_counts = $avms_performance = $village_breakdown = [];
}

// Handle CSV export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="village_health_report_' . date('Y-m-d') . '.csv"');

    $output = fopen('php://output', 'w');

    // Export problems data
    fputcsv($output, ['Problem ID', 'Title', 'Description', 'Villager', 'Village', 'Priority', 'Status', 'Created Date', 'AVMS Assigned', 'Doctor Assigned']);

    $stmt = $pdo->prepare("
        SELECT p.id, p.title, p.description, v.name as villager_name, v.village, 
               p.priority, p.status, p.created_at, a.name as avms_name, d.name as doctor_name
        FROM problems p
        JOIN users v ON p.villager_id = v.id
        LEFT JOIN users a ON p.assigned_to = a.id
        LEFT JOIN users d ON p.escalated_to = d.id
        ORDER BY p.created_at DESC
    ");
    $stmt->execute();
    $problems = $stmt->fetchAll();

    foreach ($problems as $problem) {
        fputcsv($output, [
            $problem['id'],
            $problem['title'],
            $problem['description'],
            $problem['villager_name'],
            $problem['village'],
            $problem['priority'],
            $problem['status'],
            $problem['created_at'],
            $problem['avms_name'],
            $problem['doctor_name']
        ]);
    }

    fclose($output);
    exit;
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
                            <i class="fas fa-chart-bar text-success"></i> System Reports
                        </h1>
                        <p class="text-muted mb-0">Analytics and performance data for Village Health Connect</p>
                    </div>
                    <div>
                        <div class="btn-group">
                            <a href="?export=csv" class="btn btn-success">
                                <i class="fas fa-download"></i> Export CSV
                            </a>
                            <a href="dashboard.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> Back
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-lg-3 col-md-6">
            <div class="stats-card">
                <div class="stats-number"><?php echo $report_data['total_problems']; ?></div>
                <div class="stats-label">
                    <i class="fas fa-list-alt"></i> Total Problems
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="stats-card">
                <div class="stats-number text-success">
                    <?php 
                    $resolved = array_sum(array_column(array_filter($status_counts, function($s) { 
                        return in_array($s['status'], ['resolved', 'completed']); 
                    }), 'count'));
                    echo $resolved;
                    ?>
                </div>
                <div class="stats-label">
                    <i class="fas fa-check-circle"></i> Problems Resolved
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="stats-card">
                <div class="stats-number text-info">
                    <?php echo count(array_filter($avms_performance, function($a) { return $a['assigned_problems'] > 0; })); ?>
                </div>
                <div class="stats-label">
                    <i class="fas fa-user-tie"></i> Active ANMS
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="stats-card">
                <div class="stats-number text-primary"><?php echo count($village_breakdown); ?></div>
                <div class="stats-label">
                    <i class="fas fa-map-marker-alt"></i> Villages Served
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Status Distribution -->
        <div class="col-lg-6">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-pie-chart"></i> Problems by Status
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($status_counts)): ?>
                        <?php foreach ($status_counts as $status): ?>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <div>
                                    <span class="badge status-<?php echo $status['status']; ?> me-2">
                                        <?php echo ucfirst(str_replace('_', ' ', $status['status'])); ?>
                                    </span>
                                </div>
                                <div>
                                    <span class="fw-bold"><?php echo $status['count']; ?></span>
                                    <small class="text-muted">
                                        (<?php echo round(($status['count'] / $report_data['total_problems']) * 100, 1); ?>%)
                                    </small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-muted text-center">No data available</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Priority Distribution -->
        <div class="col-lg-6">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-exclamation-triangle"></i> Problems by Priority
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($priority_counts)): ?>
                        <?php foreach ($priority_counts as $priority): ?>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <div>
                                    <span class="badge priority-<?php echo $priority['priority']; ?> me-2">
                                        <?php echo ucfirst($priority['priority']); ?>
                                    </span>
                                </div>
                                <div>
                                    <span class="fw-bold"><?php echo $priority['count']; ?></span>
                                    <small class="text-muted">
                                        (<?php echo round(($priority['count'] / $report_data['total_problems']) * 100, 1); ?>%)
                                    </small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-muted text-center">No data available</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Monthly Trends -->
    <div class="row">
        <div class="col-lg-6">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-chart-line"></i> Monthly Trends (Last 6 Months)
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($monthly_counts)): ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Month</th>
                                        <th>Problems Reported</th>
                                        <th>Trend</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($monthly_counts as $index => $month): ?>
                                        <tr>
                                            <td><?php echo date('M Y', strtotime($month['month'] . '-01')); ?></td>
                                            <td><?php echo $month['count']; ?></td>
                                            <td>
                                                <?php if ($index > 0): ?>
                                                    <?php 
                                                    $prev_count = $monthly_counts[$index - 1]['count'];
                                                    $change = $month['count'] - $prev_count;
                                                    $arrow = $change > 0 ? 'up' : ($change < 0 ? 'down' : 'right');
                                                    $color = $change > 0 ? 'success' : ($change < 0 ? 'danger' : 'secondary');
                                                    ?>
                                                    <i class="fas fa-arrow-<?php echo $arrow; ?> text-<?php echo $color; ?>"></i>
                                                    <?php echo abs($change); ?>
                                                <?php else: ?>
                                                    -
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-muted text-center">No monthly data available</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Village Breakdown -->
        <div class="col-lg-6">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-map-marker-alt"></i> Top Villages (by Problems Reported)
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($village_breakdown)): ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Village</th>
                                        <th>Problems</th>
                                        <th>Percentage</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($village_breakdown as $village): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($village['village'] ?? 'Not specified'); ?></td>
                                            <td><?php echo $village['problems_reported']; ?></td>
                                            <td>
                                                <?php echo round(($village['problems_reported'] / $report_data['total_problems']) * 100, 1); ?>%
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-muted text-center">No village data available</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- AVMS Performance -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-users"></i> ANMS Performance Summary
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($avms_performance)): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>ANMS Officer</th>
                                        <th>Assigned Problems</th>
                                        <th>Resolved</th>
                                        <th>Escalated</th>
                                        <th>Resolution Rate</th>
                                        <th>Performance</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($avms_performance as $avms): ?>
                                        <tr>
                                            <td>
                                                <?php echo htmlspecialchars($avms['name']); ?>
                                                <?php if ($avms['name'] === $_SESSION['user_name']): ?>
                                                    <span class="badge bg-primary">You</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo $avms['assigned_problems']; ?></td>
                                            <td>
                                                <span class="text-success"><?php echo $avms['resolved_problems']; ?></span>
                                            </td>
                                            <td>
                                                <span class="text-info"><?php echo $avms['escalated_problems']; ?></span>
                                            </td>
                                            <td>
                                                <?php 
                                                if ($avms['assigned_problems'] > 0) {
                                                    $rate = round(($avms['resolved_problems'] / $avms['assigned_problems']) * 100, 1);
                                                    echo $rate . '%';
                                                } else {
                                                    echo 'N/A';
                                                }
                                                ?>
                                            </td>
                                            <td>
                                                <?php 
                                                if ($avms['assigned_problems'] > 0) {
                                                    $rate = ($avms['resolved_problems'] / $avms['assigned_problems']) * 100;
                                                    if ($rate >= 80) {
                                                        echo '<span class="badge bg-success">Excellent</span>';
                                                    } elseif ($rate >= 60) {
                                                        echo '<span class="badge bg-primary">Good</span>';
                                                    } elseif ($rate >= 40) {
                                                        echo '<span class="badge bg-warning">Average</span>';
                                                    } else {
                                                        echo '<span class="badge bg-danger">Needs Improvement</span>';
                                                    }
                                                } else {
                                                    echo '<span class="badge bg-secondary">No Cases</span>';
                                                }
                                                ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-muted text-center">No ANMS performance data available</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Report Generation Info -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="alert alert-info">
                <h6><i class="fas fa-info-circle"></i> Report Information</h6>
                <p class="mb-2">This report was generated on <strong><?php echo date('F j, Y 	 g:i A'); ?></strong></p>
                <p class="mb-0">
                    <strong>Data includes:</strong> All problems from system inception to current date. 
                    Click "Export CSV" to download detailed data for further analysis.
                </p>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>