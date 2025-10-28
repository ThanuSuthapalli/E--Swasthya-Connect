<?php
require_once '../includes/config.php';
requireRole('admin');

$page_title = 'System Reports - Administration';

// Get date range from request
$start_date = $_GET['start_date'] ?? date('Y-m-01'); // First day of current month
$end_date = $_GET['end_date'] ?? date('Y-m-d'); // Today
$report_type = $_GET['report_type'] ?? 'overview';

try {
    $pdo = getDBConnection();

    // Generate comprehensive reports
    $reports = [];

    // 1. System Overview Report
    $stmt = $pdo->prepare("
        SELECT 
            (SELECT COUNT(*) FROM users WHERE role = 'villager' AND (status = 'active' OR status IS NULL)) as active_villagers,
            (SELECT COUNT(*) FROM users WHERE role = 'avms' AND (status = 'active' OR status IS NULL)) as active_avms,
            (SELECT COUNT(*) FROM users WHERE role = 'doctor' AND (status = 'active' OR status IS NULL)) as active_doctors,
            (SELECT COUNT(*) FROM problems WHERE created_at BETWEEN ? AND ?) as problems_period,
            (SELECT COUNT(*) FROM problems WHERE status = 'resolved' AND updated_at BETWEEN ? AND ?) as resolved_period,
            (SELECT COUNT(*) FROM medical_responses WHERE created_at BETWEEN ? AND ?) as responses_period,
            (SELECT COUNT(*) FROM problems WHERE priority = 'urgent') as urgent_problems,
            (SELECT AVG(TIMESTAMPDIFF(HOUR, created_at, updated_at)) FROM problems WHERE status = 'resolved' AND updated_at BETWEEN ? AND ?) as avg_resolution_time
    ");
    $stmt->execute([$start_date, $end_date, $start_date, $end_date, $start_date, $end_date, $start_date, $end_date]);
    $reports['overview'] = $stmt->fetch();

    // 2. Problems by Status
    $stmt = $pdo->prepare("
        SELECT status, COUNT(*) as count 
        FROM problems 
        WHERE created_at BETWEEN ? AND ?
        GROUP BY status
    ");
    $stmt->execute([$start_date, $end_date]);
    $reports['problems_by_status'] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    // 3. Problems by Priority
    $stmt = $pdo->prepare("
        SELECT priority, COUNT(*) as count 
        FROM problems 
        WHERE created_at BETWEEN ? AND ?
        GROUP BY priority
    ");
    $stmt->execute([$start_date, $end_date]);
    $reports['problems_by_priority'] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    // 4. Problems by Category
    $stmt = $pdo->prepare("
        SELECT category, COUNT(*) as count 
        FROM problems 
        WHERE created_at BETWEEN ? AND ?
        GROUP BY category
    ");
    $stmt->execute([$start_date, $end_date]);
    $reports['problems_by_category'] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    // 5. Top Performing AVMS
    $stmt = $pdo->prepare("
        SELECT u.name, COUNT(p.id) as problems_handled,
               AVG(TIMESTAMPDIFF(HOUR, p.created_at, p.updated_at)) as avg_response_time
        FROM users u
        LEFT JOIN problems p ON u.id = p.assigned_to AND p.created_at BETWEEN ? AND ?
        WHERE u.role = 'avms' AND (u.status = 'active' OR u.status IS NULL)
        GROUP BY u.id, u.name
        ORDER BY problems_handled DESC
        LIMIT 10
    ");
    $stmt->execute([$start_date, $end_date]);
    $reports['top_avms'] = $stmt->fetchAll();

    // 6. Doctor Response Statistics
    $stmt = $pdo->prepare("
        SELECT u.name, COUNT(mr.id) as responses_given,
               AVG(TIMESTAMPDIFF(HOUR, p.created_at, mr.created_at)) as avg_response_time
        FROM users u
        LEFT JOIN medical_responses mr ON u.id = mr.doctor_id AND mr.created_at BETWEEN ? AND ?
        LEFT JOIN problems p ON mr.problem_id = p.id
        WHERE u.role = 'doctor' AND (u.status = 'active' OR u.status IS NULL)
        GROUP BY u.id, u.name
        ORDER BY responses_given DESC
        LIMIT 10
    ");
    $stmt->execute([$start_date, $end_date]);
    $reports['doctor_stats'] = $stmt->fetchAll();

    // 7. Daily Activity Report
    $stmt = $pdo->prepare("
        SELECT DATE(created_at) as date,
               COUNT(*) as problems_reported
        FROM problems 
        WHERE created_at BETWEEN ? AND ?
        GROUP BY DATE(created_at)
        ORDER BY date DESC
        LIMIT 30
    ");
    $stmt->execute([$start_date, $end_date]);
    $reports['daily_activity'] = $stmt->fetchAll();

    // 8. Village-wise Statistics
    $stmt = $pdo->prepare("
        SELECT u.village, 
               COUNT(DISTINCT u.id) as total_villagers,
               COUNT(DISTINCT p.id) as total_problems,
               SUM(CASE WHEN p.status = 'resolved' THEN 1 ELSE 0 END) as resolved_problems
        FROM users u
        LEFT JOIN problems p ON u.id = p.villager_id AND p.created_at BETWEEN ? AND ?
        WHERE u.role = 'villager' AND u.village IS NOT NULL AND u.village != ''
        GROUP BY u.village
        ORDER BY total_problems DESC
        LIMIT 20
    ");
    $stmt->execute([$start_date, $end_date]);
    $reports['village_stats'] = $stmt->fetchAll();

} catch (Exception $e) {
    error_log("Error generating reports: " . $e->getMessage());
    $reports = [];
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
                            <i class="fas fa-chart-bar me-2"></i>System Reports
                        </h1>
                        <p class="text-muted mb-0">
                            Comprehensive analytics and reporting for the Village Health Connect system
                        </p>
                    </div>
                    <div>
                        <a href="<?php echo SITE_URL . '/admin/dashboard.php'; ?>" class="btn btn-outline-primary me-2">
                            <i class="fas fa-arrow-left me-1"></i>Back to Dashboard
                        </a>
                        <button class="btn btn-success" onclick="exportReport()">
                            <i class="fas fa-download me-1"></i>Export PDF
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Report Filters -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Report Configuration</h5>
                        <div class="ms-3" style="max-width: 280px; width: 100%;">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text"><i class="fas fa-search"></i></span>
                                <input type="text" id="reportSearch" class="form-control" placeholder="Search in report tables...">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <form method="GET" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="row g-3 align-items-end">
                        <div class="col-md-3">
                            <label for="start_date" class="form-label">Start Date</label>
                            <input type="date" class="form-control" id="start_date" name="start_date" 
                                   value="<?php echo htmlspecialchars($start_date); ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="end_date" class="form-label">End Date</label>
                            <input type="date" class="form-control" id="end_date" name="end_date" 
                                   value="<?php echo htmlspecialchars($end_date); ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="report_type" class="form-label">Report Type</label>
                            <select class="form-select" id="report_type" name="report_type">
                                <option value="overview" <?php echo $report_type === 'overview' ? 'selected' : ''; ?>>System Overview</option>
                                <option value="problems" <?php echo $report_type === 'problems' ? 'selected' : ''; ?>>Problems Analysis</option>
                                <option value="performance" <?php echo $report_type === 'performance' ? 'selected' : ''; ?>>Performance Metrics</option>
                                <option value="village" <?php echo $report_type === 'village' ? 'selected' : ''; ?>>Village Statistics</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-chart-line me-1"></i>Generate Report
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Report Summary Cards -->
    <div class="row mb-4">
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="report-card bg-gradient-primary text-white">
                <div class="card-body text-center">
                    <div class="report-icon mb-2">
                        <i class="fas fa-users fa-2x"></i>
                    </div>
                    <div class="report-number h3 mb-1"><?php echo ($reports['overview']['active_villagers'] ?? 0) + ($reports['overview']['active_avms'] ?? 0) + ($reports['overview']['active_doctors'] ?? 0); ?></div>
                    <div class="report-label">Total Active Users</div>
                    <div class="report-breakdown small mt-2">
                        V: <?php echo $reports['overview']['active_villagers'] ?? 0; ?> | 
                        A: <?php echo $reports['overview']['active_avms'] ?? 0; ?> | 
                        D: <?php echo $reports['overview']['active_doctors'] ?? 0; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6 mb-3">
            <div class="report-card bg-gradient-info text-white">
                <div class="card-body text-center">
                    <div class="report-icon mb-2">
                        <i class="fas fa-clipboard-list fa-2x"></i>
                    </div>
                    <div class="report-number h3 mb-1"><?php echo $reports['overview']['problems_period'] ?? 0; ?></div>
                    <div class="report-label">Problems Reported</div>
                    <div class="report-breakdown small mt-2">
                        Period: <?php echo date('M j', strtotime($start_date)); ?> - <?php echo date('M j', strtotime($end_date)); ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6 mb-3">
            <div class="report-card bg-gradient-success text-white">
                <div class="card-body text-center">
                    <div class="report-icon mb-2">
                        <i class="fas fa-check-circle fa-2x"></i>
                    </div>
                    <div class="report-number h3 mb-1"><?php echo $reports['overview']['resolved_period'] ?? 0; ?></div>
                    <div class="report-label">Problems Resolved</div>
                    <div class="report-breakdown small mt-2">
                        Success Rate: 
                        <?php 
                        $total = $reports['overview']['problems_period'] ?? 0;
                        $resolved = $reports['overview']['resolved_period'] ?? 0;
                        echo $total > 0 ? round(($resolved / $total) * 100, 1) : 0; 
                        ?>%
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6 mb-3">
            <div class="report-card bg-gradient-warning text-dark">
                <div class="card-body text-center">
                    <div class="report-icon mb-2">
                        <i class="fas fa-clock fa-2x"></i>
                    </div>
                    <div class="report-number h3 mb-1">
                        <?php echo $reports['overview']['avg_resolution_time'] ? round($reports['overview']['avg_resolution_time'], 1) : 0; ?>h
                    </div>
                    <div class="report-label">Avg Resolution Time</div>
                    <div class="report-breakdown small mt-2">
                        Urgent Cases: <?php echo $reports['overview']['urgent_problems'] ?? 0; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Detailed Reports -->
    <div class="row">
        <!-- Problems Analysis -->
        <div class="col-lg-6 mb-4">
            <div class="card shadow">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i>Problems by Status</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($reports['problems_by_status'])): ?>
                        <div class="table-responsive">
                            <table class="table table-sm report-table">
                                <thead>
                                    <tr>
                                        <th>Status</th>
                                        <th class="text-end">Count</th>
                                        <th class="text-end">Percentage</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $total_problems = array_sum($reports['problems_by_status']);
                                    foreach ($reports['problems_by_status'] as $status => $count): 
                                    ?>
                                    <tr>
                                        <td>
                                            <span class="badge bg-<?php echo $status === 'resolved' ? 'success' : ($status === 'escalated' ? 'danger' : 'primary'); ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $status)); ?>
                                            </span>
                                        </td>
                                        <td class="text-end"><strong><?php echo $count; ?></strong></td>
                                        <td class="text-end">
                                            <?php echo $total_problems > 0 ? round(($count / $total_problems) * 100, 1) : 0; ?>%
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-muted">No data available for the selected period.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Priority Analysis -->
        <div class="col-lg-6 mb-4">
            <div class="card shadow">
                <div class="card-header bg-warning text-dark">
                    <h5 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i>Problems by Priority</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($reports['problems_by_priority'])): ?>
                        <div class="table-responsive">
                            <table class="table table-sm report-table">
                                <thead>
                                    <tr>
                                        <th>Priority</th>
                                        <th class="text-end">Count</th>
                                        <th class="text-end">Percentage</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $total_priority = array_sum($reports['problems_by_priority']);
                                    $priority_order = ['urgent', 'high', 'medium', 'low'];
                                    foreach ($priority_order as $priority): 
                                        if (!isset($reports['problems_by_priority'][$priority])) continue;
                                        $count = $reports['problems_by_priority'][$priority];
                                    ?>
                                    <tr>
                                        <td>
                                            <span class="badge bg-<?php echo $priority === 'urgent' ? 'danger' : ($priority === 'high' ? 'warning' : ($priority === 'medium' ? 'info' : 'success')); ?>">
                                                <?php echo ucfirst($priority); ?>
                                            </span>
                                        </td>
                                        <td class="text-end"><strong><?php echo $count; ?></strong></td>
                                        <td class="text-end">
                                            <?php echo $total_priority > 0 ? round(($count / $total_priority) * 100, 1) : 0; ?>%
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-muted">No data available for the selected period.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- AVMS Performance -->
        <div class="col-lg-6 mb-4">
            <div class="card shadow">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="fas fa-user-tie me-2"></i>Top Performing ANMS</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($reports['top_avms'])): ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>ANMS Officer</th>
                                        <th class="text-end">Problems</th>
                                        <th class="text-end">Avg Time (hrs)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (array_slice($reports['top_avms'], 0, 8) as $avms): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($avms['name']); ?></td>
                                        <td class="text-end">
                                            <span class="badge bg-primary"><?php echo $avms['problems_handled'] ?? 0; ?></span>
                                        </td>
                                        <td class="text-end">
                                            <?php echo $avms['avg_response_time'] ? round($avms['avg_response_time'], 1) : '-'; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-muted">No ANMS activity data available.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Doctor Performance -->
        <div class="col-lg-6 mb-4">
            <div class="card shadow">
                <div class="card-header bg-danger text-white">
                    <h5 class="mb-0"><i class="fas fa-user-md me-2"></i>Doctor Response Statistics</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($reports['doctor_stats'])): ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Doctor</th>
                                        <th class="text-end">Responses</th>
                                        <th class="text-end">Avg Time (hrs)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (array_slice($reports['doctor_stats'], 0, 8) as $doctor): ?>
                                    <tr>
                                        <td>Dr. <?php echo htmlspecialchars($doctor['name']); ?></td>
                                        <td class="text-end">
                                            <span class="badge bg-success"><?php echo $doctor['responses_given'] ?? 0; ?></span>
                                        </td>
                                        <td class="text-end">
                                            <?php echo $doctor['avg_response_time'] ? round($doctor['avg_response_time'], 1) : '-'; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-muted">No doctor response data available.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Village Statistics -->
        <div class="col-12 mb-4">
            <div class="card shadow">
                <div class="card-header bg-secondary text-white">
                    <h5 class="mb-0"><i class="fas fa-map-marker-alt me-2"></i>Village-wise Statistics</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($reports['village_stats'])): ?>
                        <div class="table-responsive">
                            <table class="table report-table">
                                <thead>
                                    <tr>
                                        <th>Village</th>
                                        <th class="text-end">Total Villagers</th>
                                        <th class="text-end">Problems Reported</th>
                                        <th class="text-end">Problems Resolved</th>
                                        <th class="text-end">Resolution Rate</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($reports['village_stats'] as $village): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($village['village']); ?></strong></td>
                                        <td class="text-end"><?php echo $village['total_villagers']; ?></td>
                                        <td class="text-end">
                                            <span class="badge bg-info"><?php echo $village['total_problems']; ?></span>
                                        </td>
                                        <td class="text-end">
                                            <span class="badge bg-success"><?php echo $village['resolved_problems']; ?></span>
                                        </td>
                                        <td class="text-end">
                                            <div class="progress" style="width: 60px; height: 20px;">
                                                <?php 
                                                $rate = $village['total_problems'] > 0 ? 
                                                       ($village['resolved_problems'] / $village['total_problems']) * 100 : 0;
                                                ?>
                                                <div class="progress-bar bg-<?php echo $rate >= 80 ? 'success' : ($rate >= 60 ? 'warning' : 'danger'); ?>" 
                                                     style="width: <?php echo $rate; ?>%">
                                                    <?php echo round($rate); ?>%
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-muted">No village data available.</p>
                    <?php endif; ?>
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

.report-card {
    border-radius: 15px;
    transition: all 0.3s ease;
    border: none;
    overflow: hidden;
}

.report-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 25px rgba(0,0,0,0.15);
}

.bg-gradient-primary {
    background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
}

.bg-gradient-info {
    background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
}

.bg-gradient-success {
    background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%);
}

.bg-gradient-warning {
    background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%);
}

.report-breakdown {
    opacity: 0.9;
}

.table th {
    border-top: none;
    font-weight: 600;
}

.progress {
    border-radius: 10px;
}

.card {
    transition: all 0.3s ease;
}

.card:hover {
    transform: translateY(-2px);
}

@media (max-width: 768px) {
    .report-card {
        margin-bottom: 20px;
    }

    .table-responsive {
        font-size: 0.875rem;
    }
}
</style>

<script>
function exportReport() {
    showToast('Preparing print-friendly report...', 'info', 2000);
    const params = new URLSearchParams(window.location.search);
    params.set('print', '1');
    const url = window.location.pathname + '?' + params.toString();
    window.open(url, '_blank', 'noopener');
}

// Auto-refresh reports every 5 minutes
setInterval(function() {
    if (document.visibilityState === 'visible') {
        // Only refresh if on overview report
        if (new URLSearchParams(window.location.search).get('report_type') === 'overview') {
            // Uncomment to enable auto-refresh
            // location.reload();
        }
    }
}, 300000);

// Show toast notification
function showToast(message, type, duration) {
    type = type || 'info';
    duration = duration || 5000;
    
    const toast = document.createElement('div');
    toast.className = 'alert alert-' + type + ' alert-dismissible fade show position-fixed';
    toast.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
    toast.innerHTML = message + '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
    
    document.body.appendChild(toast);
    
    // Auto remove
    setTimeout(function() {
        if (toast.parentNode) {
            toast.remove();
        }
    }, duration);
}

// Initialize date inputs with better UX
document.addEventListener('DOMContentLoaded', function() {
    const startDate = document.getElementById('start_date');
    const endDate = document.getElementById('end_date');
    const reportType = document.getElementById('report_type');
    const generateBtn = document.querySelector('button[type="submit"]');
    const searchInput = document.getElementById('reportSearch');

    // Ensure end date is not before start date
    startDate.addEventListener('change', function() {
        if (endDate.value && this.value > endDate.value) {
            endDate.value = this.value;
            showToast('End date adjusted to match start date', 'info', 2000);
        }
        endDate.min = this.value;
    });

    endDate.addEventListener('change', function() {
        if (startDate.value && this.value < startDate.value) {
            startDate.value = this.value;
            showToast('Start date adjusted to match end date', 'info', 2000);
        }
        startDate.max = this.value;
    });
    
    // Add loading state to generate button
    const form = document.querySelector('form');
    if (form) {
        form.addEventListener('submit', function() {
            generateBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Generating...';
            generateBtn.disabled = true;
            showToast('Generating report...', 'info', 3000);
        });
    }
    
    // Add quick date range buttons
    addQuickDateButtons();

    // Hook up search filter
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const query = this.value.trim().toLowerCase();
            const tables = document.querySelectorAll('.report-table');
            tables.forEach(function(table) {
                const rows = table.querySelectorAll('tbody tr');
                rows.forEach(function(row) {
                    const text = row.textContent.toLowerCase();
                    row.style.display = (query === '' || text.indexOf(query) !== -1) ? '' : 'none';
                });
            });
        });
    }
});

function addQuickDateButtons() {
    const form = document.querySelector('form');
    if (!form) return;
    
    const quickButtons = document.createElement('div');
    quickButtons.className = 'mt-3';
    quickButtons.innerHTML = `
        <div class="btn-group" role="group">
            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="setDateRange('today')">Today</button>
            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="setDateRange('week')">This Week</button>
            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="setDateRange('month')">This Month</button>
            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="setDateRange('quarter')">This Quarter</button>
            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="setDateRange('year')">This Year</button>
        </div>
    `;
    
    form.appendChild(quickButtons);
}

function setDateRange(range) {
    const startDate = document.getElementById('start_date');
    const endDate = document.getElementById('end_date');
    const today = new Date();
    
    let start, end;
    
    switch(range) {
        case 'today':
            start = end = today.toISOString().split('T')[0];
            break;
        case 'week':
            start = new Date(today.setDate(today.getDate() - today.getDay())).toISOString().split('T')[0];
            end = new Date().toISOString().split('T')[0];
            break;
        case 'month':
            start = new Date(today.getFullYear(), today.getMonth(), 1).toISOString().split('T')[0];
            end = new Date().toISOString().split('T')[0];
            break;
        case 'quarter':
            const quarter = Math.floor(today.getMonth() / 3);
            start = new Date(today.getFullYear(), quarter * 3, 1).toISOString().split('T')[0];
            end = new Date().toISOString().split('T')[0];
            break;
        case 'year':
            start = new Date(today.getFullYear(), 0, 1).toISOString().split('T')[0];
            end = new Date().toISOString().split('T')[0];
            break;
    }
    
    startDate.value = start;
    endDate.value = end;
    
    showToast('Date range set to ' + range, 'success', 2000);
}
</script>

<?php include '../includes/footer.php'; ?>

<?php if (!empty($_GET['print']) && $_GET['print'] == '1'): ?>
<style>
    /* Print friendly adjustments */
    @media print {
        nav, .btn, .btn-group, .page-header div:nth-child(2) { display: none !important; }
        body { -webkit-print-color-adjust: exact; }
    }
    /* Ensure the report uses full width when printing */
    body { background: #fff; }
</style>
<script>
    // Give the page a moment to render before calling print
    window.addEventListener('load', function() {
        setTimeout(function() {
            window.print();
        }, 500);
    });
</script>
<?php endif; ?>