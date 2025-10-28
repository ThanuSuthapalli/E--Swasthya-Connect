<?php
require_once '../includes/config.php';

// Include working medical functions
if (file_exists('../includes/working_medical_functions.php')) {
    require_once '../includes/working_medical_functions.php';
}

requireRole('doctor');

$page_title = 'All Escalated Cases - Village Health Connect';

// Get filter parameters
$priority_filter = $_GET['priority'] ?? 'all';
$search = $_GET['search'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 10;

// Get all escalated cases with pagination
$all_cases = [];
if (function_exists('getEscalatedCasesActual')) {
    $all_cases = getEscalatedCasesActual($priority_filter, $search, null, $_SESSION['user_id']);
}

// Calculate pagination
$total_cases = count($all_cases);
$total_pages = ceil($total_cases / $per_page);
$offset = ($page - 1) * $per_page;
$cases_page = array_slice($all_cases, $offset, $per_page);

// Get statistics for filters
$stats = [];
if (function_exists('getDashboardStatsActual')) {
    $stats = getDashboardStatsActual($_SESSION['user_id']);
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
                            <i class="fas fa-list-alt me-2"></i>All Escalated Cases
                        </h1>
                        <p class="text-muted mb-0">
                            Review and manage all medical cases requiring professional consultation
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

    <!-- Filters and Search -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header">
                    <h5 class="mb-0 d-flex align-items-center">
                        <i class="fas fa-filter me-2"></i>Filter & Search Cases
                    </h5>
                </div>
                <div class="card-body">
                    <form method="GET" class="row g-3 align-items-end">
                        <div class="col-md-3">
                            <label for="priority" class="form-label">Priority Level</label>
                            <select class="form-select" id="priority" name="priority">
                                <option value="all" <?php echo $priority_filter === 'all' ? 'selected' : ''; ?>>All Priorities</option>
                                <option value="urgent" <?php echo $priority_filter === 'urgent' ? 'selected' : ''; ?>>
                                    🔴 Urgent (<?php echo $stats['urgent_cases'] ?? 0; ?>)
                                </option>
                                <option value="high" <?php echo $priority_filter === 'high' ? 'selected' : ''; ?>>
                                    🟠 High (<?php echo $stats['high_priority'] ?? 0; ?>)
                                </option>
                                <option value="medium" <?php echo $priority_filter === 'medium' ? 'selected' : ''; ?>>🟡 Medium</option>
                                <option value="low" <?php echo $priority_filter === 'low' ? 'selected' : ''; ?>>🟢 Low</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="search" class="form-label">Search Cases</label>
                            <input type="text" class="form-control" id="search" name="search" 
                                   value="<?php echo htmlspecialchars($search); ?>"
                                   placeholder="Search by title, patient name, or description...">
                        </div>
                        <div class="col-md-3">
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search me-1"></i>Search
                                </button>
                                <a href="all_escalated.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-refresh me-1"></i>Reset
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics Summary -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="stats-summary-card bg-danger text-white">
                <div class="d-flex align-items-center">
                    <i class="fas fa-exclamation-triangle fa-2x me-3"></i>
                    <div>
                        <div class="h3 mb-0"><?php echo $stats['urgent_cases'] ?? 0; ?></div>
                        <small>Urgent Cases</small>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stats-summary-card bg-info text-white">
                <div class="d-flex align-items-center">
                    <i class="fas fa-list fa-2x me-3"></i>
                    <div>
                        <div class="h3 mb-0"><?php echo $total_cases; ?></div>
                        <small>Total Cases</small>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stats-summary-card bg-success text-white">
                <div class="d-flex align-items-center">
                    <i class="fas fa-check-circle fa-2x me-3"></i>
                    <div>
                        <div class="h3 mb-0"><?php echo $stats['my_responses'] ?? 0; ?></div>
                        <small>My Responses</small>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stats-summary-card bg-warning text-dark">
                <div class="d-flex align-items-center">
                    <i class="fas fa-clock fa-2x me-3"></i>
                    <div>
                        <div class="h3 mb-0"><?php echo count(array_filter($all_cases, function($c) { return $c['response_count'] == 0; })); ?></div>
                        <small>Need Response</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Cases List -->
    <div class="row">
        <div class="col-12">
            <?php if (empty($cases_page)): ?>
                <div class="card shadow">
                    <div class="card-body text-center py-5">
                        <?php if ($priority_filter !== 'all' || !empty($search)): ?>
                            <i class="fas fa-search fa-4x text-muted mb-4"></i>
                            <h4>No Cases Found</h4>
                            <p class="text-muted mb-4">No cases match your current filters. Try adjusting your search criteria.</p>
                            <a href="all_escalated.php" class="btn btn-primary">
                                <i class="fas fa-refresh me-1"></i>View All Cases
                            </a>
                        <?php else: ?>
                            <i class="fas fa-check-circle fa-4x text-success mb-4"></i>
                            <h4 class="text-success">No Medical Cases Currently Escalated</h4>
                            <p class="text-muted mb-4">All cases are being handled at the ANMS level. New escalated cases will appear here when medical expertise is required.</p>
                            <div class="d-flex justify-content-center gap-3">
                                
                                <a href="dashboard.php" class="btn btn-outline-primary">
                                    <i class="fas fa-home me-1"></i>Back to Dashboard
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($cases_page as $index => $case): ?>
                    <div class="case-card mb-4 priority-<?php echo $case['priority']; ?> <?php echo $case['is_urgent'] ? 'urgent-case' : ''; ?>">
                        <div class="card shadow">
                            <div class="card-header case-header-<?php echo $case['priority']; ?>">
                                <div class="row align-items-center">
                                    <div class="col-md-8">
                                        <h5 class="mb-1 d-flex align-items-center">
                                            <span class="case-number me-3">#<?php echo str_pad($case['id'], 3, '0', STR_PAD_LEFT); ?></span>
                                            <?php echo htmlspecialchars($case['title']); ?>
                                            <?php if ($case['priority'] === 'urgent'): ?>
                                                <span class="badge bg-danger ms-2 blink">URGENT</span>
                                            <?php elseif ($case['priority'] === 'high'): ?>
                                                <span class="badge bg-warning text-dark ms-2">HIGH PRIORITY</span>
                                            <?php endif; ?>
                                            <?php if ($case['is_urgent']): ?>
                                                <span class="badge bg-dark ms-1">TIME CRITICAL</span>
                                            <?php endif; ?>
                                        </h5>
                                        <div class="case-meta">
                                            <span class="badge bg-secondary me-2"><?php echo ucfirst($case['category']); ?></span>
                                            <span class="badge bg-<?php echo $case['risk_level'] === 'critical' ? 'danger' : ($case['risk_level'] === 'high' ? 'warning' : 'info'); ?>">
                                                <?php echo ucfirst($case['risk_level']); ?> Risk
                                            </span>
                                            <small class="text-muted ms-3">
                                                <i class="fas fa-clock me-1"></i>Escalated <?php echo $case['hours_since_escalation']; ?>h ago
                                            </small>
                                        </div>
                                    </div>
                                    <div class="col-md-4 text-end">
                                        <div class="case-status">
                                            <?php if ($case['response_count'] == 0): ?>
                                                <span class="badge bg-warning text-dark p-2">
                                                    <i class="fas fa-exclamation-triangle me-1"></i>No Response Yet
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-success p-2">
                                                    <i class="fas fa-check-circle me-1"></i><?php echo $case['response_count']; ?> Response(s)
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-8">
                                        <div class="medical-description mb-3">
                                            <h6><i class="fas fa-notes-medical text-primary me-2"></i>Medical Condition:</h6>
                                            <p class="mb-0"><?php echo nl2br(htmlspecialchars($case['description'])); ?></p>
                                        </div>

                                        <div class="patient-info">
                                            <h6><i class="fas fa-user text-info me-2"></i>Patient Information:</h6>
                                            <div class="row">
                                                <div class="col-sm-6">
                                                    <p class="mb-1"><strong>Name:</strong> <?php echo htmlspecialchars($case['villager_name']); ?></p>
                                                    <p class="mb-1"><strong>Location:</strong> <?php echo htmlspecialchars($case['villager_village'] ?? 'Not specified'); ?></p>
                                                </div>
                                                <div class="col-sm-6">
                                                    <?php if ($case['villager_phone']): ?>
                                                        <p class="mb-1"><strong>Contact:</strong> <?php echo htmlspecialchars($case['villager_phone']); ?></p>
                                                    <?php endif; ?>
                                                    <p class="mb-1"><strong>ANMS Officer:</strong> <?php echo htmlspecialchars($case['avms_name'] ?? 'Not assigned'); ?></p>
                                                </div>
                                            </div>
                                        </div>

                                        <?php if ($case['response_count'] > 0): ?>
                                            <div class="response-summary mt-3">
                                                <h6><i class="fas fa-stethoscope text-success me-2"></i>Previous Responses:</h6>
                                                <div class="alert alert-info">
                                                    <small>
                                                        <strong><?php echo $case['response_count']; ?> medical response(s)</strong> by: 
                                                        <?php echo htmlspecialchars($case['responding_doctors'] ?? 'Unknown doctors'); ?>
                                                        <br>
                                                        Last response: <?php echo $case['last_response_date'] ? date('M j, Y g:i A', strtotime($case['last_response_date'])) : 'N/A'; ?>
                                                        <?php if ($case['highest_response_urgency']): ?>
                                                            | Highest urgency: <span class="badge bg-<?php echo $case['highest_response_urgency'] === 'critical' ? 'danger' : 'warning'; ?>"><?php echo ucfirst($case['highest_response_urgency']); ?></span>
                                                        <?php endif; ?>
                                                    </small>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <div class="col-md-4">
                                        <div class="case-actions">
                                            <h6><i class="fas fa-tools text-primary me-2"></i>Actions:</h6>
                                            <div class="d-grid gap-2">
                                                <a href="respond_case.php?id=<?php echo $case['id']; ?>" 
                                                   class="btn btn-<?php echo $case['priority'] === 'urgent' ? 'danger' : 'primary'; ?> btn-lg">
                                                    <i class="fas fa-stethoscope me-2"></i>
                                                    <?php if ($case['response_count'] == 0): ?>
                                                        Provide Medical Response
                                                    <?php else: ?>
                                                        Add Follow-up Response
                                                    <?php endif; ?>
                                                </a>

                                                <a href="view_problem.php?id=<?php echo $case['id']; ?>" 
                                                   class="btn btn-outline-info">
                                                    <i class="fas fa-eye me-2"></i>View Full Details
                                                </a>

                                                <?php if ($case['villager_phone']): ?>
                                                    <a href="tel:<?php echo $case['villager_phone']; ?>" 
                                                       class="btn btn-outline-success">
                                                        <i class="fas fa-phone me-2"></i>Call Patient
                                                    </a>
                                                <?php endif; ?>

                                                <?php if ($case['response_count'] > 0): ?>
                                                    <a href="my_responses.php?problem_id=<?php echo $case['id']; ?>" 
                                                       class="btn btn-outline-warning">
                                                        <i class="fas fa-history me-2"></i>View Response History
                                                    </a>
                                                <?php endif; ?>
                                            </div>

                                            <?php if ($case['needs_response']): ?>
                                                <div class="alert alert-warning mt-3 p-2">
                                                    <small>
                                                        <i class="fas fa-exclamation-triangle me-1"></i>
                                                        <strong>Action Required:</strong> This case needs medical attention
                                                    </small>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="card-footer text-muted">
                                <div class="d-flex justify-content-between align-items-center">
                                    <small>
                                        Created: <?php echo date('M j, Y g:i A', strtotime($case['created_at'])); ?>
                                        | Updated: <?php echo date('M j, Y g:i A', strtotime($case['updated_at'])); ?>
                                    </small>
                                    <small class="text-<?php echo $case['is_urgent'] ? 'danger' : 'muted'; ?>">
                                        Case Age: <?php echo $case['hours_since_escalation']; ?> hours
                                        <?php if ($case['is_urgent']): ?>
                                            <i class="fas fa-exclamation-triangle text-danger ms-1"></i>
                                        <?php endif; ?>
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <nav aria-label="Cases pagination">
                                <ul class="pagination justify-content-center mb-0">
                                    <?php if ($page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?php echo $page-1; ?>&priority=<?php echo urlencode($priority_filter); ?>&search=<?php echo urlencode($search); ?>">
                                                <i class="fas fa-chevron-left me-1"></i>Previous
                                            </a>
                                        </li>
                                    <?php endif; ?>

                                    <?php for ($i = max(1, $page-2); $i <= min($total_pages, $page+2); $i++): ?>
                                        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $i; ?>&priority=<?php echo urlencode($priority_filter); ?>&search=<?php echo urlencode($search); ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>

                                    <?php if ($page < $total_pages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?php echo $page+1; ?>&priority=<?php echo urlencode($priority_filter); ?>&search=<?php echo urlencode($search); ?>">
                                                Next<i class="fas fa-chevron-right ms-1"></i>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                            <div class="text-center mt-2">
                                <small class="text-muted">
                                    Showing <?php echo ($offset + 1); ?>-<?php echo min($offset + $per_page, $total_cases); ?> of <?php echo $total_cases; ?> cases
                                </small>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
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

.stats-summary-card {
    padding: 20px;
    border-radius: 12px;
    margin-bottom: 15px;
    transition: all 0.3s ease;
}

.stats-summary-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 6px 20px rgba(0,0,0,0.15);
}

.case-card {
    transition: all 0.3s ease;
}

.case-card:hover {
    transform: translateY(-3px);
}

.case-header-urgent {
    background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
    color: white;
}

.case-header-high {
    background: linear-gradient(135deg, #fd7e14 0%, #e8590c 100%);
    color: white;
}

.case-header-medium {
    background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%);
    color: #212529;
}

.case-header-low {
    background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%);
    color: white;
}

.case-number {
    background: rgba(255,255,255,0.2);
    color: inherit;
    padding: 4px 12px;
    border-radius: 20px;
    font-weight: bold;
    font-size: 0.9rem;
}

.medical-description {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-left: 4px solid #17a2b8;
    padding: 15px;
    border-radius: 8px;
}

.patient-info {
    background: linear-gradient(135deg, #e8f4fd 0%, #f8f9fa 100%);
    border-left: 4px solid #28a745;
    padding: 15px;
    border-radius: 8px;
}

.urgent-case {
    animation: urgent-pulse 2s infinite;
    border: 2px solid #dc3545;
    border-radius: 15px;
}

@keyframes urgent-pulse {
    0%, 100% { box-shadow: 0 0 20px rgba(220, 53, 69, 0.3); }
    50% { box-shadow: 0 0 30px rgba(220, 53, 69, 0.5); }
}

.blink {
    animation: blink 1.5s infinite;
}

@keyframes blink {
    0%, 50% { opacity: 1; }
    51%, 100% { opacity: 0.7; }
}
</style>

<script>
// Auto-refresh every 5 minutes for urgent cases
setInterval(function() {
    if (document.visibilityState === 'visible' && 
        (<?php echo json_encode($priority_filter); ?> === 'urgent' || 
         <?php echo ($stats['urgent_cases'] ?? 0); ?> > 0)) {
        location.reload();
    }
}, 300000);

// Add search functionality
document.getElementById('search').addEventListener('input', function() {
    clearTimeout(this.searchTimeout);
    this.searchTimeout = setTimeout(function() {
        document.querySelector('form').submit();
    }, 1000);
});
</script>

<?php include '../includes/footer.php'; ?>