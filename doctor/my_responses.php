<?php
require_once '../includes/config.php';

// Include working medical functions
if (file_exists('../includes/working_medical_functions.php')) {
    require_once '../includes/working_medical_functions.php';
}

requireRole('doctor');

$page_title = 'My Response History - Village Health Connect';

// Get filter parameters
$problem_id = isset($_GET['problem_id']) ? (int)$_GET['problem_id'] : null;
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 10;

// Get doctor's responses
$responses = [];
if (function_exists('getMyResponsesActual')) {
    $responses = getMyResponsesActual($_SESSION['user_id'], null, true);
} else {
    // Fallback
    try {
        $pdo = getDBConnection();
        $sql = "
            SELECT mr.*, 
            p.title as problem_title, p.priority, p.status as problem_status, p.category, p.created_at as case_created,
            v.name as villager_name, v.village as villager_village, v.phone as villager_phone,
            a.name as avms_name, a.phone as avms_phone
            FROM medical_responses mr
            INNER JOIN problems p ON mr.problem_id = p.id
            INNER JOIN users v ON p.villager_id = v.id
            LEFT JOIN users a ON p.assigned_to = a.id
            WHERE mr.doctor_id = ?
        ";

        if ($problem_id) {
            $sql .= " AND mr.problem_id = ?";
        }

        $sql .= " ORDER BY mr.created_at DESC";

        $stmt = $pdo->prepare($sql);
        if ($problem_id) {
            $stmt->execute([$_SESSION['user_id'], $problem_id]);
        } else {
            $stmt->execute([$_SESSION['user_id']]);
        }
        $responses = $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Error fetching responses: " . $e->getMessage());
    }
}

// Filter by specific problem if requested
if ($problem_id && $responses) {
    $responses = array_filter($responses, function($r) use ($problem_id) {
        return $r['problem_id'] == $problem_id;
    });
}

// Calculate pagination
$total_responses = count($responses);
$total_pages = ceil($total_responses / $per_page);
$offset = ($page - 1) * $per_page;
$responses_page = array_slice($responses, $offset, $per_page);

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
                            <i class="fas fa-history me-2"></i>My Response History
                        </h1>
                        <p class="text-muted mb-0">
                            Complete record of your medical consultations and patient guidance
                            <?php if ($problem_id): ?>
                                <span class="badge bg-info ms-2">Filtered by Case #<?php echo str_pad($problem_id, 3, '0', STR_PAD_LEFT); ?></span>
                            <?php endif; ?>
                        </p>
                    </div>
                    <div>
                        <div class="input-group mb-2" style="max-width: 300px;">
                            <input type="text" id="searchInput" class="form-control" placeholder="Search responses...">
                            <button class="btn btn-outline-secondary" type="button" onclick="searchResponses()">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                        <a href="dashboard.php" class="btn btn-outline-primary me-2">
                            <i class="fas fa-arrow-left me-1"></i>Back to Dashboard
                        </a>
                        <a href="all_escalated.php" class="btn btn-success">
                            <i class="fas fa-list me-1"></i>All Cases
                        </a>
                        <?php if ($problem_id): ?>
                            <a href="my_responses.php" class="btn btn-outline-secondary">
                                <i class="fas fa-eye me-1"></i>View All Responses
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Summary Statistics -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="stats-card bg-primary text-white">
                <div class="d-flex align-items-center">
                    <i class="fas fa-comments fa-2x me-3"></i>
                    <div>
                        <div class="h3 mb-0"><?php echo $total_responses; ?></div>
                        <small>Total Responses</small>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stats-card bg-success text-white">
                <div class="d-flex align-items-center">
                    <i class="fas fa-users fa-2x me-3"></i>
                    <div>
                        <div class="h3 mb-0"><?php echo count(array_unique(array_column($responses, 'problem_id'))); ?></div>
                        <small>Patients Helped</small>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stats-card bg-warning text-dark">
                <div class="d-flex align-items-center">
                    <i class="fas fa-exclamation-triangle fa-2x me-3"></i>
                    <div>
                        <div class="h3 mb-0"><?php echo count(array_filter($responses, function($r) { return $r['urgency_level'] === 'critical' || $r['urgency_level'] === 'high'; })); ?></div>
                        <small>Critical/High Priority</small>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stats-card bg-info text-white">
                <div class="d-flex align-items-center">
                    <i class="fas fa-redo fa-2x me-3"></i>
                    <div>
                        <div class="h3 mb-0"><?php echo count(array_filter($responses, function($r) { return $r['follow_up_required']; })); ?></div>
                        <small>Follow-ups Required</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Response History -->
    <div class="row">
        <div class="col-12">
            <?php if (empty($responses_page)): ?>
                <div class="card shadow">
                    <div class="card-body text-center py-5">
                        <i class="fas fa-clipboard-list fa-4x text-muted mb-4"></i>
                        <h4>No Medical Responses Yet</h4>
                        <p class="text-muted mb-4">
                            You haven't provided any medical consultations yet. Start by reviewing escalated cases and providing professional guidance.
                        </p>
                        <a href="all_escalated.php" class="btn btn-primary">
                            <i class="fas fa-stethoscope me-1"></i>Review Medical Cases
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($responses_page as $response): ?>
                    <div class="response-card mb-4">
                        <div class="card shadow">
                            <div class="card-header response-header-<?php echo $response['urgency_level']; ?>">
                                <div class="row align-items-center">
                                    <div class="col-md-8">
                                        <h5 class="mb-1 d-flex align-items-center">
                                            <span class="response-number me-3">#<?php echo str_pad($response['problem_id'], 3, '0', STR_PAD_LEFT); ?></span>
                                            <?php echo htmlspecialchars($response['problem_title']); ?>
                                            <span class="badge bg-<?php echo $response['urgency_level'] === 'critical' ? 'danger' : ($response['urgency_level'] === 'high' ? 'warning' : 'info'); ?> ms-2">
                                                <?php echo strtoupper($response['urgency_level']); ?>
                                            </span>
                                            <?php if ($response['follow_up_required']): ?>
                                                <span class="badge bg-warning text-dark ms-1">FOLLOW-UP</span>
                                            <?php endif; ?>
                                        </h5>
                                        <div class="response-meta">
                                            <span class="badge bg-secondary me-2"><?php echo ucfirst($response['category']); ?></span>
                                            <span class="badge bg-<?php echo $response['priority'] === 'urgent' ? 'danger' : 'primary'; ?>">
                                                <?php echo ucfirst($response['priority']); ?> Priority
                                            </span>
                                            <small class="text-light ms-3">
                                                <i class="fas fa-calendar me-1"></i>Responded: <?php echo date('M j, Y g:i A', strtotime($response['created_at'])); ?>
                                            </small>
                                        </div>
                                    </div>
                                    <div class="col-md-4 text-end">
                                        <div class="response-status">
                                            <small class="text-light">Response ID: <?php echo $response['id']; ?></small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-8">
                                        <div class="medical-response mb-3">
                                            <h6><i class="fas fa-stethoscope text-primary me-2"></i>Medical Response:</h6>
                                            <div class="response-content">
                                                <?php echo nl2br(htmlspecialchars($response['response'])); ?>
                                            </div>
                                        </div>

                                        <?php if (!empty($response['recommendations'])): ?>
                                            <div class="recommendations mb-3">
                                                <h6><i class="fas fa-clipboard-check text-success me-2"></i>Treatment Recommendations:</h6>
                                                <div class="recommendations-content">
                                                    <?php echo nl2br(htmlspecialchars($response['recommendations'])); ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>

                                        <div class="patient-details">
                                            <h6><i class="fas fa-user text-info me-2"></i>Patient Information:</h6>
                                            <div class="row">
                                                <div class="col-sm-6">
                                                    <p class="mb-1"><strong>Patient:</strong> <?php echo htmlspecialchars($response['villager_name']); ?></p>
                                                    <p class="mb-1"><strong>Location:</strong> <?php echo htmlspecialchars($response['villager_village'] ?? 'Not specified'); ?></p>
                                                </div>
                                                <div class="col-sm-6">
                                                    <p class="mb-1"><strong>ANMS Officer:</strong> <?php echo htmlspecialchars($response['avms_name'] ?? 'Not assigned'); ?></p>
                                                    <p class="mb-1"><strong>Case Status:</strong> <span class="badge bg-info"><?php echo ucfirst($response['problem_status']); ?></span></p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-md-4">
                                        <div class="response-actions">
                                            <h6><i class="fas fa-tools text-primary me-2"></i>Actions:</h6>
                                            <div class="d-grid gap-2">
                                                <a href="view_problem.php?id=<?php echo $response['problem_id']; ?>" 
                                                   class="btn btn-outline-info">
                                                    <i class="fas fa-eye me-2"></i>View Full Case
                                                </a>

                                                <a href="respond_case.php?id=<?php echo $response['problem_id']; ?>" 
                                                   class="btn btn-outline-success">
                                                    <i class="fas fa-plus me-2"></i>Add Follow-up
                                                </a>

                                                <?php if ($response['villager_phone']): ?>
                                                    <a href="tel:<?php echo $response['villager_phone']; ?>" 
                                                       class="btn btn-outline-warning">
                                                        <i class="fas fa-phone me-2"></i>Call Patient
                                                    </a>
                                                <?php endif; ?>

                                                <button class="btn btn-outline-secondary btn-sm" 
                                                        onclick="printResponse(<?php echo $response['id']; ?>)">
                                                    <i class="fas fa-print me-2"></i>Print Response
                                                </button>
                                            </div>
                                        </div>

                                        <div class="response-timeline mt-3">
                                            <small class="text-muted">
                                                <strong>Timeline:</strong><br>
                                                Case Created: <?php echo date('M j', strtotime($response['case_created'])); ?><br>
                                                Response Given: <?php echo date('M j', strtotime($response['created_at'])); ?><br>
                                                Days Ago: <?php echo floor((time() - strtotime($response['created_at'])) / 86400); ?> days
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="card-footer text-muted">
                                <div class="d-flex justify-content-between align-items-center">
                                    <small>
                                        Response submitted: <?php echo date('l, M j, Y \a\t g:i A', strtotime($response['created_at'])); ?>
                                    </small>
                                    <small>
                                        <?php if ($response['follow_up_required']): ?>
                                            <i class="fas fa-redo text-warning me-1"></i>Follow-up recommended
                                        <?php else: ?>
                                            <i class="fas fa-check-circle text-success me-1"></i>Treatment guidance complete
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
                            <nav aria-label="Response history pagination">
                                <ul class="pagination justify-content-center mb-0">
                                    <?php if ($page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?php echo $page-1; ?><?php echo $problem_id ? '&problem_id='.$problem_id : ''; ?>">
                                                <i class="fas fa-chevron-left me-1"></i>Previous
                                            </a>
                                        </li>
                                    <?php endif; ?>

                                    <?php for ($i = max(1, $page-2); $i <= min($total_pages, $page+2); $i++): ?>
                                        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $i; ?><?php echo $problem_id ? '&problem_id='.$problem_id : ''; ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>

                                    <?php if ($page < $total_pages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?php echo $page+1; ?><?php echo $problem_id ? '&problem_id='.$problem_id : ''; ?>">
                                                Next<i class="fas fa-chevron-right ms-1"></i>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                            <div class="text-center mt-2">
                                <small class="text-muted">
                                    Showing <?php echo ($offset + 1); ?>-<?php echo min($offset + $per_page, $total_responses); ?> of <?php echo $total_responses; ?> responses
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

.stats-card {
    padding: 20px;
    border-radius: 12px;
    margin-bottom: 15px;
    transition: all 0.3s ease;
}

.stats-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 6px 20px rgba(0,0,0,0.15);
}

.response-card {
    transition: all 0.3s ease;
}

.response-card:hover {
    transform: translateY(-3px);
}

.response-header-critical {
    background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
    color: white;
}

.response-header-high {
    background: linear-gradient(135deg, #fd7e14 0%, #e8590c 100%);
    color: white;
}

.response-header-medium {
    background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%);
    color: white;
}

.response-header-low {
    background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
    color: white;
}

.response-number {
    background: rgba(255,255,255,0.2);
    color: inherit;
    padding: 4px 12px;
    border-radius: 20px;
    font-weight: bold;
    font-size: 0.9rem;
}

.medical-response, .recommendations, .patient-details {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 15px;
}

.medical-response {
    border-left: 4px solid #007bff;
}

.recommendations {
    border-left: 4px solid #28a745;
}

.patient-details {
    border-left: 4px solid #17a2b8;
}

.response-content, .recommendations-content {
    background: white;
    padding: 12px;
    border-radius: 6px;
    border: 1px solid #dee2e6;
    white-space: pre-wrap;
    line-height: 1.6;
}

.response-timeline {
    background: #f8f9fa;
    padding: 10px;
    border-radius: 6px;
    border: 1px solid #e9ecef;
}
</style>

<script>
function printResponse(responseId) {
    // Attempt to find the relevant response card on the page
    const responseCard = document.querySelector(`[data-response-id="${responseId}"]`);
    if (responseCard) {
        // Create a print window
        const printWindow = window.open('', '_blank', 'width=800,height=600');
        const printContent = `
<!DOCTYPE html>
<html>
<head>
    <title>Medical Response - Print</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .header { border-bottom: 2px solid #007bff; padding-bottom: 10px; margin-bottom: 20px; }
        .response-content { background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .patient-info { background: #e3f2fd; padding: 10px; border-radius: 5px; }
        .recommendations { background: #f1f8e9; padding: 10px; border-radius: 5px; }
        .footer { margin-top: 30px; text-align: center; font-size: 12px; color: #666; }
        @media print { body { margin: 0; } .no-print { display: none; } }
    </style>
</head>
<body>
    <div class="header">
        <h2>Medical Response Report</h2>
        <p>Response ID: ${responseId} | Generated: ${new Date().toLocaleString()}</p>
    </div>
    ${responseCard.innerHTML}
    <div class="footer">
        <p>Village Health Connect - Medical Response System</p>
        <p>This document was generated on ${new Date().toLocaleDateString()}</p>
    </div>
    <script>
        window.onload = function() {
            document.querySelectorAll('.btn, button').forEach(btn => btn.style.display = 'none');
            window.print();
        };
    <\/script>
</body>
</html>
        `;
        printWindow.document.write(printContent);
        printWindow.document.close();
    } else {
        // Fallback: open server-side PDF/print response
        window.open('print_response.php?id=' + responseId, '_blank', 'width=800,height=600');
    }
}

// Show toast notification
function showToast(message, type = 'info', duration = 5000) {
    const toast = document.createElement('div');
    toast.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
    toast.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
    toast.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    document.body.appendChild(toast);
    // Auto remove after [duration] ms
    setTimeout(() => {
        if (toast.parentNode) {
            toast.remove();
        }
    }, duration);
}

// Add search functionality for responses
function searchResponses() {
    const searchTerm = document.getElementById('searchInput').value.toLowerCase().trim();
    const responseCards = document.querySelectorAll('.response-card');
    let visibleCount = 0;
    if (!searchTerm) {
        responseCards.forEach(card => {
            card.style.display = 'block';
            card.classList.remove('search-highlight');
            visibleCount++;
        });
        showToast(`Showing all ${visibleCount} responses`, 'info', 2000);
        return;
    }
    responseCards.forEach(card => {
        const text = card.textContent.toLowerCase();
        if (text.includes(searchTerm)) {
            card.style.display = 'block';
            card.classList.add('search-highlight');
            visibleCount++;
        } else {
            card.style.display = 'none';
            card.classList.remove('search-highlight');
        }
    });
    if (visibleCount === 0) {
        showToast('No responses found matching your search', 'warning');
    } else {
        showToast(`Found ${visibleCount} response(s) matching "${searchTerm}"`, 'success', 2000);
    }
}

// Enhanced search with real-time filtering and UI improvements
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        // Real-time search as user types
        searchInput.addEventListener('input', function() {
            clearTimeout(this.searchTimeout);
            this.searchTimeout = setTimeout(() => {
                searchResponses();
            }, 300);
        });
        // Keyboard shortcuts
        searchInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                searchResponses();
            } else if (e.key === 'Escape') {
                this.value = '';
                searchResponses();
            }
        });
    }
    // Action/print button feedback and data attributes for print
    const responseCards = document.querySelectorAll('.response-card');
    responseCards.forEach((card, index) => {
        const printBtn = card.querySelector('button[onclick*="printResponse"]');
        if (printBtn && !card.hasAttribute('data-response-id')) {
            // Use regex to extract the numeric ID from the printResponse call
            const matches = printBtn.getAttribute('onclick').match(/printResponse\\((\\d+)\\)/);
            if (matches) card.setAttribute('data-response-id', matches[1]);
        }
        // Add smooth feedback to action buttons
        const buttons = card.querySelectorAll('.response-actions .btn, button[onclick*="printResponse"]');
        buttons.forEach(btn => {
            btn.addEventListener('click', function(e) {
                this.style.transform = 'scale(0.95)';
                setTimeout(() => { this.style.transform = ''; }, 150);
            });
        });
    });
    // Add search highlight styles
    const style = document.createElement('style');
    style.textContent = `
        .search-highlight { animation: searchPulse 0.5s ease-in-out; }
        @keyframes searchPulse {
            0% { background-color: #fff3cd; }
            50% { background-color: #ffeaa7; }
            100% { background-color: transparent; }
        }
        .response-card:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(0,0,0,0.15); transition: all 0.3s ease; }
        .btn { transition: all 0.2s ease; }
        .btn:hover { transform: translateY(-1px); }
    `;
    document.head.appendChild(style);
});

</script>

<?php include '../includes/footer.php'; ?>