<?php
require_once '../includes/config.php';
requireRole('doctor');

$page_title = 'Schedule Visit Template - Village Health Connect';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $visit_type = $_POST['visit_type'] ?? '';
    $urgency = $_POST['urgency'] ?? 'routine';
    $recommended_timeframe = $_POST['recommended_timeframe'] ?? '';
    $instructions = $_POST['instructions'] ?? '';
    $preparations = $_POST['preparations'] ?? '';

    // Generate visit template
    $visit_template = generateVisitTemplate($visit_type, $urgency, $recommended_timeframe, $instructions, $preparations);
}

function generateVisitTemplate($visit_type, $urgency, $timeframe, $instructions, $preparations) {
    $template = "MEDICAL VISIT RECOMMENDATION

";
    $template .= "Visit Type: " . ucfirst($visit_type) . "
";
    $template .= "Urgency Level: " . ucfirst($urgency) . "
";
    $template .= "Recommended Timeframe: " . $timeframe . "

";

    $template .= "VISIT INSTRUCTIONS:
" . $instructions . "

";

    if (!empty($preparations)) {
        $template .= "PATIENT PREPARATIONS:
" . $preparations . "

";
    }

    $template .= "Please coordinate with the ANMS officer to arrange this visit and ensure the patient is available at the scheduled time.";

    return $template;
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
                            <i class="fas fa-calendar-plus text-primary"></i> Schedule Visit Template
                        </h1>
                        <p class="text-muted mb-0">Generate standardized visit recommendations for escalated cases</p>
                    </div>
                    <div>
                        <a href="dashboard.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Dashboard
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <?php if (isset($visit_template)): ?>
                <!-- Generated Template -->
                <div class="card mb-4">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-check-circle"></i> Visit Template Generated
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="visit-template" id="visitTemplate">
                            <?php echo nl2br(htmlspecialchars($visit_template)); ?>
                        </div>

                        <div class="mt-4">
                            <div class="btn-group">
                                <button class="btn btn-primary" onclick="copyTemplate()">
                                    <i class="fas fa-copy"></i> Copy Template
                                </button>
                                <button class="btn btn-info" onclick="printTemplate()">
                                    <i class="fas fa-print"></i> Print Template
                                </button>
                                <a href="schedule_visit.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-plus"></i> Create New Template
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Visit Template Generator -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-calendar-alt"></i> Generate Visit Recommendation
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST" class="needs-validation" novalidate>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="visit_type" class="form-label">Type of Visit <span class="text-danger">*</span></label>
                                    <select class="form-select" id="visit_type" name="visit_type" required>
                                        <option value="">Select visit type...</option>
                                        <option value="follow_up">Follow-up Consultation</option>
                                        <option value="examination">Physical Examination</option>
                                        <option value="diagnostic">Diagnostic Assessment</option>
                                        <option value="treatment">Treatment Administration</option>
                                        <option value="monitoring">Progress Monitoring</option>
                                        <option value="emergency">Emergency Evaluation</option>
                                        <option value="referral">Specialist Referral</option>
                                    </select>
                                    <div class="invalid-feedback">Please select a visit type.</div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="urgency" class="form-label">Urgency Level</label>
                                    <select class="form-select" id="urgency" name="urgency">
                                        <option value="routine">Routine (Within 1-2 weeks)</option>
                                        <option value="urgent">Urgent (Within 24-48 hours)</option>
                                        <option value="emergency">Emergency (Immediate)</option>
                                        <option value="follow_up">Follow-up (As scheduled)</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="recommended_timeframe" class="form-label">Recommended Timeframe <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="recommended_timeframe" name="recommended_timeframe" 
                                   placeholder="e.g., Within 3 days, Tomorrow morning, Next week" required>
                            <div class="form-text">Specify when the visit should be scheduled</div>
                            <div class="invalid-feedback">Please specify the recommended timeframe.</div>
                        </div>

                        <div class="mb-3">
                            <label for="instructions" class="form-label">Visit Instructions <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="instructions" name="instructions" rows="6" 
                                      placeholder="Detailed instructions for the visit..." required></textarea>
                            <div class="form-text">Provide specific instructions for the ANMS officer and patient</div>
                            <div class="invalid-feedback">Please provide visit instructions.</div>
                        </div>

                        <div class="mb-4">
                            <label for="preparations" class="form-label">Patient Preparations (Optional)</label>
                            <textarea class="form-control" id="preparations" name="preparations" rows="4" 
                                      placeholder="Any preparations the patient should make before the visit..."></textarea>
                            <div class="form-text">List any preparations needed before the visit</div>
                        </div>

                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <a href="dashboard.php" class="btn btn-secondary me-md-2">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-calendar-plus"></i> Generate Visit Template
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- Common Visit Templates -->
            <div class="card mb-3">
                <div class="card-header">
                    <h6><i class="fas fa-list"></i> Common Visit Templates</h6>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <button class="btn btn-outline-primary btn-sm" onclick="fillTemplate('follow_up')">
                            <i class="fas fa-redo"></i> Follow-up Visit
                        </button>
                        <button class="btn btn-outline-info btn-sm" onclick="fillTemplate('examination')">
                            <i class="fas fa-stethoscope"></i> Physical Examination
                        </button>
                        <button class="btn btn-outline-warning btn-sm" onclick="fillTemplate('diagnostic')">
                            <i class="fas fa-search"></i> Diagnostic Tests
                        </button>
                        <button class="btn btn-outline-success btn-sm" onclick="fillTemplate('monitoring')">
                            <i class="fas fa-chart-line"></i> Progress Monitoring
                        </button>
                        <button class="btn btn-outline-danger btn-sm" onclick="fillTemplate('emergency')">
                            <i class="fas fa-ambulance"></i> Emergency Evaluation
                        </button>
                    </div>
                </div>
            </div>

            <!-- Visit Guidelines -->
            <div class="card mb-3">
                <div class="card-header">
                    <h6><i class="fas fa-info-circle"></i> Visit Guidelines</h6>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled mb-0">
                        <li class="mb-2">
                            <i class="fas fa-check text-success"></i>
                            <small>Specify clear objectives for the visit</small>
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-check text-success"></i>
                            <small>Include preparation instructions for patient</small>
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-check text-success"></i>
                            <small>Coordinate timing with ANMS officer</small>
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-check text-success"></i>
                            <small>Consider travel time and accessibility</small>
                        </li>
                        <li>
                            <i class="fas fa-check text-success"></i>
                            <small>Provide emergency contact information</small>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Emergency Protocols -->
            <div class="card">
                <div class="card-header bg-danger text-white">
                    <h6 class="mb-0"><i class="fas fa-exclamation-triangle"></i> Emergency Protocols</h6>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2 mb-3">
                        <a href="tel:108" class="btn btn-danger btn-sm">
                            <i class="fas fa-ambulance"></i> Call 108 - Emergency
                        </a>
                        <a href="tel:102" class="btn btn-outline-danger btn-sm">
                            <i class="fas fa-hospital"></i> Call 102 - Medical Help
                        </a>
                    </div>
                    <small class="text-muted">
                        For life-threatening emergencies, advise immediate hospital transport instead of scheduled visit
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.visit-template {
    background: #f8f9fa;
    border-left: 4px solid #007bff;
    padding: 20px;
    border-radius: 8px;
    font-family: 'Courier New', monospace;
    white-space: pre-wrap;
    line-height: 1.6;
}

.needs-validation .form-control:invalid,
.needs-validation .form-select:invalid {
    border-color: #dc3545;
}

.needs-validation .form-control:valid,
.needs-validation .form-select:valid {
    border-color: #28a745;
}
</style>

<script>
// Form validation
(function() {
    'use strict';
    window.addEventListener('load', function() {
        const forms = document.getElementsByClassName('needs-validation');
        Array.prototype.filter.call(forms, function(form) {
            form.addEventListener('submit', function(event) {
                if (form.checkValidity() === false) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            }, false);
        });
    }, false);
})();

// Template filling functions
function fillTemplate(type) {
    const templates = {
        follow_up: {
            visit_type: 'follow_up',
            urgency: 'routine',
            timeframe: 'Within 1 week',
            instructions: 'Follow-up visit to assess treatment progress and patient response:\n\n• Review current symptoms and any changes since last visit\n• Check vital signs and general condition\n• Assess medication adherence and effectiveness\n• Address any new concerns or side effects\n• Adjust treatment plan if necessary\n\nBring all current medications and any medical records from previous visits.',
            preparations: '• Continue current medications as prescribed\n• Note any changes in symptoms or side effects\n• Prepare list of questions or concerns\n• Bring previous medical reports if available'
        },
        examination: {
            visit_type: 'examination',
            urgency: 'urgent',
            timeframe: 'Within 2-3 days',
            instructions: 'Comprehensive physical examination required for proper diagnosis:\n\n• Detailed history taking\n• Complete physical examination\n• Vital signs measurement\n• Assessment of specific symptoms\n• Possible diagnostic tests if indicated\n\nEnsure patient comfort and privacy during examination.',
            preparations: '• Fast for 12 hours if blood tests may be required\n• Wear comfortable, easily removable clothing\n• Bring list of current medications\n• Note symptom timeline and triggers'
        },
        diagnostic: {
            visit_type: 'diagnostic',
            urgency: 'routine',
            timeframe: 'Within 5 days',
            instructions: 'Diagnostic assessment and possible testing required:\n\n• Clinical evaluation of symptoms\n• Ordering appropriate diagnostic tests\n• Interpretation of test results\n• Formulation of diagnosis\n• Treatment plan development\n\nCoordinate with nearest diagnostic facility if tests needed.',
            preparations: '• Follow fasting instructions if blood tests ordered\n• Bring previous test reports\n• List all current symptoms\n• Note family medical history if relevant'
        },
        monitoring: {
            visit_type: 'monitoring',
            urgency: 'routine',
            timeframe: 'As per schedule',
            instructions: 'Progress monitoring visit for ongoing condition:\n\n• Assessment of treatment effectiveness\n• Monitoring for side effects or complications\n• Vital signs and clinical parameters check\n• Adjustment of treatment if needed\n• Patient education and counseling\n\nDocument all changes and improvements.',
            preparations: '• Maintain symptom diary if requested\n• Continue medications as prescribed\n• Note any changes in condition\n• Prepare questions about treatment progress'
        },
        emergency: {
            visit_type: 'emergency',
            urgency: 'emergency',
            timeframe: 'Immediately',
            instructions: 'EMERGENCY MEDICAL EVALUATION REQUIRED:\n\n⚠️ This is an urgent medical situation\n\n• Immediate clinical assessment\n• Stabilization of patient condition\n• Emergency interventions if needed\n• Rapid decision on further treatment\n• Possible hospital referral\n\nDO NOT DELAY - Arrange immediate visit or hospital transport.',
            preparations: '• Keep patient calm and comfortable\n• Monitor vital signs if possible\n• Prepare for possible hospital transfer\n• Gather all medical records and medications'
        }
    };

    const template = templates[type];
    if (template) {
        document.getElementById('visit_type').value = template.visit_type;
        document.getElementById('urgency').value = template.urgency;
        document.getElementById('recommended_timeframe').value = template.timeframe;
        document.getElementById('instructions').value = template.instructions;
        document.getElementById('preparations').value = template.preparations;
    }
}

function copyTemplate() {
    const template = document.getElementById('visitTemplate').textContent;
    navigator.clipboard.writeText(template).then(function() {
        showToast('Visit template copied to clipboard!', 'success');
    });
}

function printTemplate() {
    const template = document.getElementById('visitTemplate').innerHTML;
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <html>
        <head>
            <title>Visit Template</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                .template { background: #f8f9fa; padding: 20px; border-left: 4px solid #007bff; }
            </style>
        </head>
        <body>
            <h2>Medical Visit Template</h2>
            <div class="template">${template}</div>
        </body>
        </html>
    `);
    printWindow.document.close();
    printWindow.print();
}

function showToast(message, type) {
    const toast = document.createElement('div');
    toast.className = `alert alert-${type} position-fixed`;
    toast.style.cssText = 'top: 20px; right: 20px; z-index: 9999; width: 300px;';
    toast.innerHTML = `<i class="fas fa-${type === 'success' ? 'check' : 'info'}-circle"></i> ${message}`;
    document.body.appendChild(toast);

    setTimeout(() => {
        toast.remove();
    }, 3000);
}
</script>

<?php include '../includes/footer.php'; ?>