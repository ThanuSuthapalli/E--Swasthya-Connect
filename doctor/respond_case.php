<?php
require_once '../includes/config.php';

// Include working medical functions
if (file_exists('../includes/working_medical_functions.php')) {
    require_once '../includes/working_medical_functions.php';
}

requireRole('doctor');

$page_title = 'Provide Medical Response - Village Health Connect';

// Get case ID from URL
$case_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$case_id) {
    header('Location: ' . SITE_URL . '/doctor/dashboard.php');
    exit();
}

// Handle form submission
$response_saved = false;
$error_message = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = trim($_POST['medical_response'] ?? '');
    $recommendations = trim($_POST['recommendations'] ?? '');
    $follow_up = isset($_POST['follow_up_required']) ? 1 : 0;
    $urgency = $_POST['urgency_level'] ?? 'medium';

    if (empty($response)) {
        $error_message = 'Medical response is required.';
    } else {
        // Save medical response
        if (function_exists('saveMedicalResponseActual')) {
            $result = saveMedicalResponseActual($case_id, $_SESSION['user_id'], $response, $recommendations, $follow_up, $urgency);
            if ($result['success']) {
                $response_saved = true;
                $success_message = $result['message'];
            } else {
                $error_message = $result['message'];
            }
        } else {
            $error_message = 'Medical response system not available.';
        }
    }
}

// Get case details
$case = null;
try {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("
        SELECT p.*, 
               v.name as villager_name, v.phone as villager_phone, v.village as villager_village, v.email as villager_email,
               a.name as avms_name, a.phone as avms_phone, a.email as avms_email
        FROM problems p 
        INNER JOIN users v ON p.villager_id = v.id 
        LEFT JOIN users a ON p.assigned_to = a.id
        WHERE p.id = ?
    ");
    $stmt->execute([$case_id]);
    $case = $stmt->fetch();
} catch (Exception $e) {
    error_log("Error fetching case: " . $e->getMessage());
}

if (!$case) {
    header('Location: ' . SITE_URL . '/doctor/dashboard.php');
    exit();
}

// Get previous responses for this case
$previous_responses = [];
if (function_exists('getMyResponsesActual')) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("
            SELECT mr.*, u.name as doctor_name 
            FROM medical_responses mr
            INNER JOIN users u ON mr.doctor_id = u.id
            WHERE mr.problem_id = ?
            ORDER BY mr.created_at DESC
        ");
        $stmt->execute([$case_id]);
        $previous_responses = $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Error fetching previous responses: " . $e->getMessage());
    }
}

include '../includes/header.php';
?>

<div class="container-fluid">
    <?php if ($response_saved): ?>
        <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
            <i class="fas fa-check-circle me-2"></i>
            <strong>Medical Response Submitted Successfully!</strong> <?php echo htmlspecialchars($success_message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($error_message): ?>
        <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <strong>Error:</strong> <?php echo htmlspecialchars($error_message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Page Header -->
    <div class="row">
        <div class="col-12">
            <div class="page-header mb-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1 class="mb-2 text-primary">
                            <i class="fas fa-stethoscope me-2"></i>Provide Medical Response
                        </h1>
                        <p class="text-muted mb-0">Case #<?php echo str_pad($case['id'], 3, '0', STR_PAD_LEFT); ?>: <?php echo htmlspecialchars($case['title']); ?></p>
                    </div>
                    <div>
                        <a href="<?php echo SITE_URL . '/doctor/dashboard.php'; ?>" class="btn btn-outline-secondary me-2">
                            <i class="fas fa-arrow-left me-1"></i>Back to Dashboard
                        </a>
                        <a href="all_escalated.php" class="btn btn-outline-primary">
                            <i class="fas fa-list me-1"></i>All Cases
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Case Details -->
        <div class="col-lg-6">
            <div class="card shadow mb-4">
                <div class="card-header bg-<?php echo $case['priority'] === 'urgent' ? 'danger' : ($case['priority'] === 'high' ? 'warning' : 'info'); ?> text-white">
                    <h5 class="mb-0 d-flex align-items-center">
                        <i class="fas fa-file-medical me-2"></i>Case Information
                        <span class="badge bg-light text-dark ms-2"><?php echo strtoupper($case['priority']); ?> PRIORITY</span>
                    </h5>
                </div>
                <div class="card-body">
                    <div class="case-details">
                        <h6 class="text-primary"><i class="fas fa-notes-medical me-2"></i>Medical Condition:</h6>
                        <div class="medical-condition mb-4">
                            <h5><?php echo htmlspecialchars($case['title']); ?></h5>
                            <p class="description"><?php echo nl2br(htmlspecialchars($case['description'])); ?></p>
                        </div>

                        <h6 class="text-info"><i class="fas fa-user me-2"></i>Patient Information:</h6>
                        <div class="patient-info mb-4">
                            <div class="row">
                                <div class="col-sm-6">
                                    <p><strong>Name:</strong> <?php echo htmlspecialchars($case['villager_name']); ?></p>
                                    <p><strong>Location:</strong> <?php echo htmlspecialchars($case['villager_village'] ?? 'Not specified'); ?></p>
                                </div>
                                <div class="col-sm-6">
                                    <?php if ($case['villager_phone']): ?>
                                        <p><strong>Phone:</strong> 
                                            <a href="tel:<?php echo $case['villager_phone']; ?>" class="btn btn-sm btn-outline-success">
                                                <i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($case['villager_phone']); ?>
                                            </a>
                                        </p>
                                    <?php endif; ?>
                                    
                                </div>
                            </div>
                        </div>

                        <h6 class="text-success"><i class="fas fa-user-tie me-2"></i>ANMS Officer:</h6>
                        <div class="avms-info">
                            <p><strong>Officer:</strong> <?php echo htmlspecialchars($case['avms_name'] ?? 'Not assigned'); ?></p>
                            <?php if ($case['avms_phone']): ?>
                                <p><strong>Contact:</strong> 
                                    <a href="tel:<?php echo $case['avms_phone']; ?>" class="btn btn-sm btn-outline-info">
                                        <i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($case['avms_phone']); ?>
                                    </a>
                                </p>
                            <?php endif; ?>
                        </div>

                        <div class="case-timeline mt-3">
                            <small class="text-muted">
                                <i class="fas fa-calendar me-1"></i>Created: <?php echo date('M j, Y g:i A', strtotime($case['created_at'])); ?>
                                <br>
                                <i class="fas fa-arrow-up me-1"></i>Escalated: <?php echo date('M j, Y g:i A', strtotime($case['updated_at'])); ?>
                            </small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Previous Responses -->
            <?php if (!empty($previous_responses)): ?>
                <div class="card shadow">
                    <div class="card-header bg-warning text-dark">
                        <h6 class="mb-0"><i class="fas fa-history me-2"></i>Previous Medical Responses (<?php echo count($previous_responses); ?>)</h6>
                    </div>
                    <div class="card-body">
                        <?php foreach ($previous_responses as $prev_response): ?>
                            <div class="previous-response mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <strong>Dr. <?php echo htmlspecialchars($prev_response['doctor_name']); ?></strong>
                                    <small class="text-muted">
                                        <?php echo date('M j, Y g:i A', strtotime($prev_response['created_at'])); ?>
                                        <span class="badge bg-<?php echo $prev_response['urgency_level'] === 'critical' ? 'danger' : 'info'; ?>">
                                            <?php echo ucfirst($prev_response['urgency_level']); ?>
                                        </span>
                                    </small>
                                </div>
                                <div class="response-content">
                                    <p><strong>Response:</strong> <?php echo nl2br(htmlspecialchars(substr($prev_response['response'], 0, 200))); ?>
                                    <?php if (strlen($prev_response['response']) > 200): ?>
                                        ...<button class="btn btn-link btn-sm p-0" onclick="showFullResponse(<?php echo $prev_response['id']; ?>)">Read More</button>
                                    <?php endif; ?>
                                    </p>
                                    <?php if ($prev_response['recommendations']): ?>
                                        <p><strong>Recommendations:</strong> <?php echo nl2br(htmlspecialchars(substr($prev_response['recommendations'], 0, 150))); ?>
                                        <?php echo strlen($prev_response['recommendations']) > 150 ? '...' : ''; ?>
                                        </p>
                                    <?php endif; ?>
                                    <?php if ($prev_response['follow_up_required']): ?>
                                        <span class="badge bg-warning text-dark"><i class="fas fa-redo me-1"></i>Follow-up Required</span>
                                    <?php endif; ?>
                                </div>
                                <hr>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Medical Response Form -->
        <div class="col-lg-6">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-stethoscope me-2"></i>Medical Response Form</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <div class="mb-3">
                            <label for="urgency_level" class="form-label"><strong>Urgency Assessment</strong></label>
                            <select class="form-select" id="urgency_level" name="urgency_level" required>
                                <option value="low">Low Priority - Routine follow-up</option>
                                <option value="medium" selected>Medium Priority - Monitor patient</option>
                                <option value="high">High Priority - Close monitoring required</option>
                                <option value="critical">Critical - Immediate medical attention</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="medical_response" class="form-label"><strong>Medical Response</strong> <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="medical_response" name="medical_response" rows="8" required 
                                      placeholder="Provide your professional medical assessment and immediate care instructions..."></textarea>
                            <div class="form-text">
                                Include: Clinical assessment, immediate treatment, diagnostic recommendations, and safety precautions.
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="recommendations" class="form-label"><strong>Treatment Recommendations</strong></label>
                            <textarea class="form-control" id="recommendations" name="recommendations" rows="4" 
                                      placeholder="Long-term treatment plan, medication recommendations, lifestyle changes..."></textarea>
                            <div class="form-text">
                                Include: Medications, follow-up care, patient education, and preventive measures.
                            </div>
                        </div>

                        <div class="mb-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="follow_up_required" name="follow_up_required">
                                <label class="form-check-label" for="follow_up_required">
                                    <strong>Follow-up Required</strong> - This case needs continued medical monitoring
                                </label>
                            </div>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" name="submit_response" class="btn btn-primary btn-lg">
                                <i class="fas fa-paper-plane me-2"></i>Submit Medical Response
                            </button>
                            <a href="<?php echo SITE_URL . '/doctor/dashboard.php'; ?>" class="btn btn-outline-secondary">
                                <i class="fas fa-times me-2"></i>Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Medical Reference Templates -->
            <div class="card shadow mt-4">
                <div class="card-header bg-info text-white">
                    <h6 class="mb-0"><i class="fas fa-book-medical me-2"></i>Quick Medical Templates</h6>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <button class="btn btn-outline-primary btn-sm" onclick="insertTemplate('fever')">
                            <i class="fas fa-thermometer-half me-2"></i>Fever Management
                        </button>
                        <button class="btn btn-outline-success btn-sm" onclick="insertTemplate('cardiac')">
                            <i class="fas fa-heartbeat me-2"></i>Cardiac Assessment
                        </button>
                        <button class="btn btn-outline-warning btn-sm" onclick="insertTemplate('respiratory')">
                            <i class="fas fa-lungs me-2"></i>Respiratory Care
                        </button>
                        <button class="btn btn-outline-danger btn-sm" onclick="insertTemplate('emergency')">
                            <i class="fas fa-ambulance me-2"></i>Emergency Protocol
                        </button>
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

.medical-condition {
    background: linear-gradient(135deg, #e3f2fd 0%, #f8f9fa 100%);
    border-left: 4px solid #2196f3;
    padding: 15px;
    border-radius: 8px;
}

.patient-info, .avms-info {
    background: linear-gradient(135deg, #f1f8e9 0%, #f8f9fa 100%);
    border-left: 4px solid #4caf50;
    padding: 15px;
    border-radius: 8px;
}

.previous-response {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 8px;
    border-left: 4px solid #ffc107;
}

.description {
    white-space: pre-wrap;
    line-height: 1.6;
}

.btn {
    border-radius: 8px;
    font-weight: 600;
    transition: all 0.3s ease;
}

.btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.form-control, .form-select {
    border-radius: 8px;
    border: 2px solid #e9ecef;
    transition: all 0.3s ease;
}

.form-control:focus, .form-select:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
}
</style>

<script>
function insertTemplate(type) {
    var templates = {
        fever: 'FEVER MANAGEMENT PROTOCOL:\n\n' +
            'ASSESSMENT:\n' +
            '- Current temperature and duration\n' +
            '- Associated symptoms (chills, headache, body aches)\n' +
            '- Patient\'s general condition and hydration status\n\n' +
            'IMMEDIATE CARE:\n' +
            '1. Temperature monitoring every 4 hours\n' +
            '2. Paracetamol 500mg every 6 hours (adults) for fever >38.5°C\n' +
            '3. Increase fluid intake - water, ORS, warm liquids\n' +
            '4. Rest and cool environment\n' +
            '5. Light, easily digestible food\n\n' +
            'MONITORING:\n' +
            '- Temperature trends\n' +
            '- Signs of dehydration\n' +
            '- Worsening symptoms\n\n' +
            'RED FLAGS - Seek immediate medical attention:\n' +
            '- Temperature >39.5°C persistent\n' +
            '- Difficulty breathing\n' +
            '- Severe headache with neck stiffness\n' +
            '- Persistent vomiting\n' +
            '- Signs of dehydration',

        cardiac: 'CARDIAC ASSESSMENT PROTOCOL:\n\n' +
            'IMMEDIATE ASSESSMENT:\n' +
            '- Chest pain characteristics (location, radiation, quality)\n' +
            '- Vital signs: BP, HR, respiratory rate, oxygen saturation\n' +
            '- Associated symptoms (shortness of breath, sweating, nausea)\n\n' +
            'EMERGENCY ACTIONS:\n' +
            '1. If chest pain suggestive of MI: Aspirin 325mg (if no allergies)\n' +
            '2. Rest in comfortable position\n' +
            '3. Oxygen if SpO2 <90%\n' +
            '4. Prepare for immediate transfer to cardiac facility\n\n' +
            'MONITORING:\n' +
            '- Continuous symptom assessment\n' +
            '- Vital signs every 15 minutes\n' +
            '- ECG if available\n\n' +
            'CRITICAL REFERRAL CRITERIA:\n' +
            '- Chest pain lasting >20 minutes\n' +
            '- ST elevation on ECG\n' +
            '- Hemodynamic instability\n' +
            '- Acute shortness of breath',

        respiratory: 'RESPIRATORY CARE PROTOCOL:\n\n' +
            'ASSESSMENT:\n' +
            '- Breathing difficulty level (1-10 scale)\n' +
            '- Oxygen saturation\n' +
            '- Respiratory rate and pattern\n' +
            '- Chest examination findings\n\n' +
            'IMMEDIATE INTERVENTIONS:\n' +
            '1. Position patient comfortably (sitting upright)\n' +
            '2. Ensure clear airway\n' +
            '3. Oxygen therapy if SpO2 <90%\n' +
            '4. Bronchodilator if wheezing present\n' +
            '5. Steam inhalation for congestion\n\n' +
            'MONITORING:\n' +
            '- Respiratory rate and effort\n' +
            '- Oxygen saturation\n' +
            '- Response to treatment\n\n' +
            'EMERGENCY REFERRAL:\n' +
            '- Severe respiratory distress\n' +
            '- SpO2 <85% on room air\n' +
            '- Cyanosis\n' +
            '- Inability to speak in full sentences',

        emergency: 'EMERGENCY PROTOCOL:\n\n' +
            'PRIMARY SURVEY (ABCDE):\n' +
            'A - Airway: Clear and patent\n' +
            'B - Breathing: Rate, effort, oxygen saturation\n' +
            'C - Circulation: Pulse, blood pressure, perfusion\n' +
            'D - Disability: Neurological assessment\n' +
            'E - Exposure: Full examination for injuries\n\n' +
            'IMMEDIATE ACTIONS:\n' +
            '1. Stabilize airway if compromised\n' +
            '2. Provide oxygen if hypoxic\n' +
            '3. IV access for fluid/medication administration\n' +
            '4. Control bleeding if present\n' +
            '5. Immobilize spine if trauma suspected\n\n' +
            'CRITICAL VITAL SIGNS:\n' +
            '- BP <90/60 or >180/110\n' +
            '- HR <50 or >120\n' +
            '- RR <10 or >30\n' +
            '- SpO2 <90%\n' +
            '- GCS <13\n\n' +
            'IMMEDIATE TRANSFER CRITERIA:\n' +
            '- Hemodynamic instability\n' +
            '- Respiratory failure\n' +
            '- Altered mental status\n' +
            '- Severe trauma'
    };

    if (templates[type]) {
        var textarea = document.getElementById('medical_response');
        if (textarea) {
            textarea.value = templates[type];
            textarea.focus();
            
            // Add visual feedback
            textarea.style.borderColor = '#28a745';
            textarea.style.boxShadow = '0 0 0 0.2rem rgba(40, 167, 69, 0.25)';
            setTimeout(function() {
                textarea.style.borderColor = '';
                textarea.style.boxShadow = '';
            }, 2000);
            
            showToast('Template inserted successfully!', 'success');
        }
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

function showFullResponse(responseId) {
    // This would normally make an AJAX call to get the full response
    alert('Full response view would be implemented here.');
}

// Form submission enhancement
document.addEventListener('DOMContentLoaded', function() {
    var form = document.querySelector('form');
    if (form) {
        form.addEventListener('submit', function(e) {
            var submitBtn = form.querySelector('button[type="submit"]');
            var originalText = submitBtn.innerHTML;
            
            // Show loading state
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting Response...';
            submitBtn.disabled = true;
            
            // Validate form
            var medicalResponse = document.getElementById('medical_response').value.trim();
            if (!medicalResponse) {
                e.preventDefault();
                showToast('Medical response is required!', 'danger');
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
                return;
            }
            
            // Add visual feedback
            showToast('Submitting medical response...', 'info', 3000);
        });
    }
    
    // Add character count for medical response
    var medicalResponseTextarea = document.getElementById('medical_response');
    if (medicalResponseTextarea) {
        var charCount = document.createElement('small');
        charCount.className = 'text-muted';
        charCount.style.display = 'block';
        charCount.style.textAlign = 'right';
        charCount.style.marginTop = '5px';
        medicalResponseTextarea.parentNode.appendChild(charCount);
        
        function updateCharCount() {
            var count = medicalResponseTextarea.value.length;
            charCount.textContent = count + ' characters';
            
            if (count > 2000) {
                charCount.className = 'text-warning';
            } else if (count > 1500) {
                charCount.className = 'text-info';
            } else {
                charCount.className = 'text-muted';
            }
        }
        
        medicalResponseTextarea.addEventListener('input', updateCharCount);
        updateCharCount();
        
        // Auto-save draft functionality
        var autoSaveTimer;
        medicalResponseTextarea.addEventListener('input', function() {
            clearTimeout(autoSaveTimer);
            autoSaveTimer = setTimeout(function() {
                // Auto-save draft functionality would go here
                console.log('Draft auto-saved');
            }, 3000);
        });
    }
    
    // Add template button click handlers
    var templateButtons = document.querySelectorAll('[onclick^="insertTemplate"]');
    templateButtons.forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            // Add visual feedback
            this.style.transform = 'scale(0.95)';
            var self = this;
            setTimeout(function() {
                self.style.transform = '';
            }, 150);
        });
    });
});
</script>

<?php include '../includes/footer.php'; ?>