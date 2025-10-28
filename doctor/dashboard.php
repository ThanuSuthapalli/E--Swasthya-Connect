<?php
require_once '../includes/config.php';
requireRole('doctor');

$page_title = 'Doctor Dashboard - E-Swasthya Connect';

try {
    $pdo = getDBConnection();

    // Get escalated problems assigned to this doctor
    $stmt = $pdo->prepare("
        SELECT p.*, 
               villager.name as villager_name, villager.phone as villager_phone, villager.village as villager_village,
               avms.name as avms_name, avms.phone as avms_phone
        FROM problems p 
        JOIN users villager ON p.villager_id = villager.id 
        LEFT JOIN users avms ON p.assigned_to = avms.id
        WHERE p.status = 'escalated' AND (p.escalated_to = ? OR p.escalated_to IS NULL)
        ORDER BY 
            CASE p.priority 
                WHEN 'urgent' THEN 1 
                WHEN 'high' THEN 2 
                WHEN 'medium' THEN 3 
                WHEN 'low' THEN 4 
            END,
            p.updated_at ASC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $escalated_cases = $stmt->fetchAll();

    // Get medical responses I've provided
    $stmt = $pdo->prepare("
        SELECT mr.*, p.title as problem_title, u.name as villager_name
        FROM medical_responses mr
        JOIN problems p ON mr.problem_id = p.id
        JOIN users u ON p.villager_id = u.id
        WHERE mr.doctor_id = ?
        ORDER BY mr.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $my_responses = $stmt->fetchAll();

    // Get statistics
    $stats = [];
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM problems WHERE status = 'escalated'");
    $stmt->execute();
    $stats['total_escalated'] = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM problems WHERE status = 'escalated' AND priority = 'urgent'");
    $stmt->execute();
    $stats['urgent_cases'] = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM medical_responses WHERE doctor_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $stats['my_responses'] = $stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM problems p 
        JOIN medical_responses mr ON p.id = mr.problem_id 
        WHERE mr.doctor_id = ? AND p.status = 'completed'
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $stats['completed_cases'] = $stmt->fetchColumn();

} catch (Exception $e) {
    error_log("Doctor dashboard error: " . $e->getMessage());
    $escalated_cases = $my_responses = [];
    $stats = ['total_escalated' => 0, 'urgent_cases' => 0, 'my_responses' => 0, 'completed_cases' => 0];
}

include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="dashboard-header">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h1 class="mb-1">
                            <i class="fas fa-user-md text-primary"></i> 
                            Medical Dashboard
                        </h1>
                        <p class="text-muted mb-0">
                            Welcome, Dr. <?php echo htmlspecialchars($_SESSION['user_name']); ?>! 
                            Review escalated cases and provide medical guidance.
                        </p>
                    </div>
                    <div class="col-md-4 text-md-end">
                        <div class="btn-group">
                            <a href="all_escalated.php" class="btn btn-success">
                                <i class="fas fa-notes-medical"></i> Provide Advice
                            </a>
                            <a href="my_responses.php" class="btn btn-outline-primary">
                                <i class="fas fa-history"></i> My History
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="stats-card bg-gradient-danger text-white">
                <div class="stats-number"><?php echo $stats['total_escalated']; ?></div>
                <div class="stats-label">
                    <i class="fas fa-arrow-up"></i> Total Escalated Cases
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="stats-card bg-gradient-warning text-white">
                <div class="stats-number"><?php echo $stats['urgent_cases']; ?></div>
                <div class="stats-label">
                    <i class="fas fa-exclamation-triangle"></i> Urgent Cases
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="stats-card bg-gradient-info text-white">
                <div class="stats-number"><?php echo $stats['my_responses']; ?></div>
                <div class="stats-label">
                    <i class="fas fa-comments"></i> My Responses
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="stats-card bg-gradient-success text-white">
                <div class="stats-number"><?php echo $stats['completed_cases']; ?></div>
                <div class="stats-label">
                    <i class="fas fa-check-circle"></i> Cases Completed
                </div>
            </div>
        </div>
    </div>

    <!-- Urgent Cases Alert -->
    <?php 
    $urgent_cases = array_filter($escalated_cases, function($case) {
        return $case['priority'] === 'urgent';
    });

    if (!empty($urgent_cases)): 
    ?>
        <div class="alert alert-danger mb-4">
            <h5><i class="fas fa-exclamation-triangle"></i> Urgent Medical Cases</h5>
            <p>The following cases require immediate medical attention:</p>
            <?php foreach ($urgent_cases as $case): ?>
                <div class="d-flex justify-content-between align-items-center mt-2 p-2 bg-white rounded">
                    <div>
                        <strong><?php echo htmlspecialchars($case['title']); ?></strong> - 
                        Patient: <?php echo htmlspecialchars($case['villager_name']); ?> 
                        (<?php echo htmlspecialchars($case['villager_village']); ?>)
                    </div>
                    <div>
                        <a href="respond_case.php?id=<?php echo $case['id']; ?>" 
                           class="btn btn-sm btn-danger">
                            <i class="fas fa-stethoscope"></i> Respond Immediately
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Quick Actions -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">
                        <i class="fas fa-bolt text-primary"></i> Quick Medical Actions
                    </h5>
                    <div class="row">
                        <div class="col-lg-3 col-md-6 mb-2">
                            <a href="#escalated-cases" class="btn btn-danger w-100" onclick="scrollToUrgentCases()">
                                <i class="fas fa-exclamation-triangle"></i> Urgent Cases (<?php echo count($urgent_cases); ?>)
                            </a>
                        </div>
                        <div class="col-lg-3 col-md-6 mb-2">
                            <a href="all_escalated.php" class="btn btn-info w-100">
                                <i class="fas fa-list-alt"></i> All Escalated (<?php echo count($escalated_cases); ?>)
                            </a>
                        </div>
                        <div class="col-lg-3 col-md-6 mb-2">
                            <a href="my_responses.php" class="btn btn-success w-100">
                                <i class="fas fa-comments"></i> My Responses
                            </a>
                        </div>
                        <div class="col-lg-3 col-md-6 mb-2">
                            <a href="response_templates.php" class="btn btn-outline-primary w-100">
                                <i class="fas fa-file-medical"></i> Response Templates
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Escalated Cases -->
        <div class="col-lg-8">
            <div class="card" id="escalated-cases">
                <div class="card-header bg-danger text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-arrow-up"></i> 
                        Cases Requiring Medical Attention
                        <span class="badge bg-light text-dark ms-2"><?php echo count($escalated_cases); ?></span>
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($escalated_cases)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                            <h5>No escalated cases at the moment!</h5>
                            <p class="text-muted">All cases are being handled at the ANMS level. You'll be notified when medical expertise is needed.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($escalated_cases as $case): ?>
                            <div class="problem-card priority-<?php echo $case['priority']; ?>">
                                <div class="row align-items-center">
                                    <div class="col-md-6">
                                        <h6 class="mb-2">
                                            <?php echo htmlspecialchars($case['title']); ?>
                                            <span class="badge priority-<?php echo $case['priority']; ?> ms-2">
                                                <?php echo ucfirst($case['priority']); ?> Priority
                                            </span>
                                        </h6>
                                        <p class="mb-2"><?php echo htmlspecialchars($case['description']); ?></p>
                                        <small class="text-muted">
                                            <strong>Patient:</strong> <?php echo htmlspecialchars($case['villager_name']); ?><br>
                                            <strong>Location:</strong> <?php echo htmlspecialchars($case['villager_village'] ?? 'Not specified'); ?><br>
                                            <strong>ANMS Officer:</strong> <?php echo htmlspecialchars($case['avms_name'] ?? 'Not assigned'); ?><br>
                                            <strong>Escalated:</strong> <?php echo formatDate($case['updated_at']); ?>
                                        </small>
                                    </div>
                                    <div class="col-md-3 text-center">
                                        <?php if ($case['photo']): ?>
                                            <img src="<?php echo htmlspecialchars(getUploadUrl($case['photo'], '..')); ?>" 
                                                 class="problem-image mb-2" alt="Case photo">
                                        <?php else: ?>
                                            <div class="bg-light p-3 rounded">
                                                <i class="fas fa-image fa-2x text-muted"></i>
                                                <br><small class="text-muted">No photo</small>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-md-3 text-end">
                                        <div class="d-grid gap-2">
                                            <a href="respond_case.php?id=<?php echo $case['id']; ?>" 
                                               class="btn btn-primary btn-sm">
                                                <i class="fas fa-stethoscope"></i> Provide Medical Response
                                            </a>
                                            <a href="view_problem.php?id=<?php echo $case['id']; ?>" 
                                               class="btn btn-outline-info btn-sm">
                                                <i class="fas fa-eye"></i> View Full Details
                                            </a>
                                            <?php if ($case['villager_phone']): ?>
                                                <a href="tel:<?php echo $case['villager_phone']; ?>" 
                                                   class="btn btn-outline-success btn-sm">
                                                    <i class="fas fa-phone"></i> Call Patient
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Medical Resources & Recent Responses -->
        <div class="col-lg-4">
            <!-- Response Templates -->
            <div class="card mb-3">
                <div class="card-header">
                    <h6><i class="fas fa-clipboard"></i> Response Templates</h6>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <button class="btn btn-outline-info btn-sm" onclick="copyTemplate('visit')">
                            <i class="fas fa-copy"></i> Schedule Visit Template
                        </button>
                        <button class="btn btn-outline-success btn-sm" onclick="copyTemplate('medication')">
                            <i class="fas fa-pills"></i> Medication Advice
                        </button>
                        <button class="btn btn-outline-warning btn-sm" onclick="copyTemplate('referral')">
                            <i class="fas fa-hospital"></i> Hospital Referral
                        </button>
                        <button class="btn btn-outline-danger btn-sm" onclick="copyTemplate('emergency')">
                            <i class="fas fa-ambulance"></i> Emergency Protocol
                        </button>
                    </div>
                </div>
            </div>

            <!-- Recent Responses -->
            <div class="card">
                <div class="card-header">
                    <h6><i class="fas fa-history"></i> Recent Responses</h6>
                </div>
                <div class="card-body">
                    <?php if (empty($my_responses)): ?>
                        <p class="text-muted text-center">No responses yet</p>
                    <?php else: ?>
                        <?php foreach (array_slice($my_responses, 0, 3) as $response): ?>
                            <div class="border-bottom pb-2 mb-2">
                                <h6 class="mb-1"><?php echo htmlspecialchars($response['problem_title']); ?></h6>
                                <small class="text-muted">
                                    Patient: <?php echo htmlspecialchars($response['villager_name']); ?><br>
                                    Responded: <?php echo formatDate($response['created_at']); ?>
                                </small>
                            </div>
                        <?php endforeach; ?>
                        <a href="my_responses.php" class="btn btn-outline-primary btn-sm w-100 mt-2">
                            <i class="fas fa-list"></i> View All Responses
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Scroll to urgent cases section
function scrollToUrgentCases() {
    const urgentSection = document.getElementById('escalated-cases');
    if (urgentSection) {
        urgentSection.scrollIntoView({ behavior: 'smooth' });
        // Highlight urgent cases
        setTimeout(() => {
            const urgentCards = document.querySelectorAll('.priority-urgent');
            urgentCards.forEach(card => {
                card.style.animation = 'pulse 2s ease-in-out 3';
            });
        }, 500);
    }
}

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

// Copy template to clipboard
function copyTemplate(type) {
    var template = '';
    switch(type) {
        case 'visit':
            template = 'MEDICAL VISIT SCHEDULED\n\nI will visit the patient on [DATE] at [TIME].\n\nPlease ensure:\n• Patient is available at the specified time\n• Clean area prepared for examination\n• Medical history and current medications ready\n\nIf urgent, please call: [PHONE]';
            break;
        case 'medication':
            template = 'MEDICATION ADVICE\n\nBased on the symptoms, I recommend:\n\nMedication: [MEDICATION NAME]\nDosage: [DOSAGE]\nFrequency: [FREQUENCY]\nDuration: [DURATION]\n\nInstructions:\n• [SPECIFIC INSTRUCTIONS]\n\nWatch for side effects: [SIDE EFFECTS]\nContact immediately if symptoms worsen or new symptoms appear.';
            break;
        case 'referral':
            template = 'HOSPITAL REFERRAL REQUIRED\n\nThis case requires immediate referral to [HOSPITAL/SPECIALIST NAME].\n\nReason for referral: [MEDICAL REASON]\n\nUrgency: [URGENT/ROUTINE]\n\nPlease arrange:\n• Transportation to hospital\n• Carry all medical records and test reports\n• Contact hospital beforehand\n\nHospital contact: [PHONE]';
            break;
        case 'emergency':
            template = 'EMERGENCY MEDICAL PROTOCOL\n\n⚠️ IMMEDIATE ACTION REQUIRED\n\nThis is a medical emergency requiring:\n1. Call ambulance immediately: 108\n2. [IMMEDIATE FIRST AID STEPS]\n3. Prepare for hospital transport\n\nDO NOT DELAY - Act immediately\n\nI will coordinate with emergency services.\n\nContact me: [YOUR PHONE]';
            break;
    }

    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(template).then(function() {
            showToast('Template copied to clipboard!', 'success');
        }).catch(function() {
            // Fallback for older browsers
            var textArea = document.createElement('textarea');
            textArea.value = template;
            document.body.appendChild(textArea);
            textArea.select();
            document.execCommand('copy');
            document.body.removeChild(textArea);
            showToast('Template copied to clipboard!', 'success');
        });
    } else {
        // Fallback for older browsers
        var textArea = document.createElement('textarea');
        textArea.value = template;
        document.body.appendChild(textArea);
        textArea.select();
        document.execCommand('copy');
        document.body.removeChild(textArea);
        showToast('Template copied to clipboard!', 'success');
    }
}

// Add pulse animation for urgent cases
var style = document.createElement('style');
style.textContent = 
    '@keyframes pulse {' +
        '0% { transform: scale(1); box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.7); }' +
        '70% { transform: scale(1.05); box-shadow: 0 0 0 10px rgba(220, 53, 69, 0); }' +
        '100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(220, 53, 69, 0); }' +
    '}';
document.head.appendChild(style);

// Initialize page functionality
document.addEventListener('DOMContentLoaded', function() {
    // Add click handlers for case cards
    var caseCards = document.querySelectorAll('.problem-card');
    caseCards.forEach(function(card) {
        card.addEventListener('click', function(e) {
            if (!e.target.closest('a') && !e.target.closest('button')) {
                var respondLink = this.querySelector('a[href*="respond_case.php"]');
                if (respondLink && respondLink.href) {
                    var match = respondLink.href.match(/id=(\d+)/);
                    if (match && match[1]) {
                        window.location.href = 'view_problem.php?id=' + match[1];
                    }
                }
            }
        });
        
        // Add hover effects
        card.style.cursor = 'pointer';
    });
    
    // Auto-refresh urgent cases count every 30 seconds
    setInterval(function() {
        if (typeof fetch !== 'undefined') {
            fetch('dashboard.php', {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(function(response) {
                return response.text();
            })
            .then(function(html) {
                // Extract urgent cases count from response
                var parser = new DOMParser();
                var doc = parser.parseFromString(html, 'text/html');
                var urgentBadge = doc.querySelector('.btn-danger .fas.fa-exclamation-triangle');
                if (urgentBadge && urgentBadge.parentElement) {
                    var match = urgentBadge.parentElement.textContent.match(/\((\d+)\)/);
                    if (match && match[1]) {
                        var newCount = match[1];
                        var currentBadge = document.querySelector('.btn-danger .fas.fa-exclamation-triangle');
                        if (currentBadge && currentBadge.parentElement) {
                            var currentMatch = currentBadge.parentElement.textContent.match(/\((\d+)\)/);
                            if (currentMatch && currentMatch[1]) {
                                var currentCount = currentMatch[1];
                                if (currentCount !== newCount) {
                                    currentBadge.parentElement.innerHTML = currentBadge.parentElement.innerHTML.replace(/\(\d+\)/, '(' + newCount + ')');
                                    if (parseInt(newCount) > parseInt(currentCount)) {
                                        showToast('New urgent cases detected!', 'warning');
                                    }
                                }
                            }
                        }
                    }
                }
            })
            .catch(function(error) {
                console.log('Auto-refresh failed:', error);
            });
        }
    }, 30000);
});
</script>

<?php include '../includes/footer.php'; ?>