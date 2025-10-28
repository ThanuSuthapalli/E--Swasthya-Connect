<?php
require_once '../includes/config.php';

// Include working medical functions
if (file_exists('../includes/working_medical_functions.php')) {
    require_once '../includes/working_medical_functions.php';
}

requireRole('villager');

$page_title = 'Medical Health Guide - Village Health Connect';

// Medical Response Templates
$templates = [
    'fever_management' => [
        'title' => 'Fever Management Protocol',
        'category' => 'General Medicine',
        'description' => 'Comprehensive fever assessment and treatment guidelines',
        'template' => 'FEVER MANAGEMENT PROTOCOL:

PATIENT ASSESSMENT:
- Current temperature: ___°F/°C
- Duration of fever: ___ hours/days
- Associated symptoms: Check for chills, headache, body aches, nausea
- Hydration status: Assess skin turgor, mucous membranes, urine output
- General condition: Alert/lethargic, appetite, activity level

IMMEDIATE INTERVENTIONS:
1. Temperature Control:
   • Adults: Paracetamol 500-1000mg every 6 hours (max 4g/day)
   • Children: Paracetamol 10-15mg/kg every 6 hours
   • Ibuprofen 400mg every 8 hours (adults) if no contraindications
   • Physical cooling: Light clothing, tepid sponging, fan

2. Hydration Management:
   • Increase fluid intake: Water, ORS, clear liquids
   • Target: 8-10 glasses/day for adults
   • Monitor urine color and frequency

3. Supportive Care:
   • Complete bed rest
   • Light, easily digestible diet (BRAT diet if nausea)
   • Cool, well-ventilated environment

MONITORING INSTRUCTIONS:
- Temperature every 4 hours
- Fluid intake/output chart
- Symptom progression tracking
- Response to medication

RED FLAG SYMPTOMS - IMMEDIATE MEDICAL ATTENTION:
⚠️ Temperature >39.5°C (103°F) persistent despite treatment
⚠️ Signs of dehydration: Dry mouth, decreased urination, dizziness
⚠️ Difficulty breathing or chest pain
⚠️ Severe headache with neck stiffness
⚠️ Persistent vomiting preventing fluid intake
⚠️ Altered mental status or confusion
⚠️ Rash development
⚠️ No improvement after 48-72 hours

FOLLOW-UP:
- Review in 24-48 hours if fever persists
- Return immediately if red flag symptoms develop
- Complete rest for 24 hours after fever subsides

PATIENT EDUCATION:
- Fever is body s natural response to infection
- Continue medications as prescribed even if feeling better
- Maintain hydration throughout illness
- Gradual return to normal activities'
    ],

    'cardiac_assessment' => [
        'title' => 'Cardiac Emergency Assessment',
        'category' => 'Emergency Medicine',
        'description' => 'Chest pain evaluation and cardiac emergency protocol',
        'template' => 'CARDIAC EMERGENCY ASSESSMENT:

IMMEDIATE TRIAGE (ABC):
A - Airway: Clear and patent ✓/✗
B - Breathing: Rate ___ /min, SpO2 ___%, Effort ___
C - Circulation: HR ___ bpm, BP ___/__ mmHg, Peripheral pulses ___

CHEST PAIN ASSESSMENT:
1. Pain Characteristics:
   • Location: Substernal/Left chest/Radiating to ___
   • Quality: Crushing/Squeezing/Sharp/Burning
   • Severity: ___/10 scale
   • Duration: ___ minutes/hours
   • Radiation: Left arm/Jaw/Back/Epigastrium

2. Associated Symptoms:
   □ Shortness of breath    □ Nausea/Vomiting
   □ Diaphoresis           □ Lightheadedness
   □ Palpitations          □ Fatigue

3. Risk Factors Assessment:
   □ Age >45 (men) />55 (women)  □ Hypertension
   □ Diabetes                     □ Smoking history
   □ Family history of CAD        □ High cholesterol
   □ Previous heart disease       □ Obesity

IMMEDIATE INTERVENTIONS:
1. If Suspected Acute MI:
   ⚡ ASPIRIN 325mg chewed (if no allergies/bleeding risk)
   ⚡ Nitroglycerin 0.4mg sublingual (if BP >90 systolic)
   ⚡ Oxygen if SpO2 <90%
   ⚡ IV access - large bore
   ⚡ Continuous cardiac monitoring

2. Pain Management:
   • Morphine 2-4mg IV if severe pain and BP stable
   • Avoid NSAIDs in suspected MI

3. Diagnostic Tests Needed:
   ⚡ 12-lead ECG IMMEDIATELY
   ⚡ Chest X-ray
   ⚡ Cardiac enzymes (Troponin, CK-MB)
   ⚡ Complete metabolic panel

CRITICAL TRANSFER CRITERIA:
🚨 ST elevation on ECG (STEMI)
🚨 Hemodynamic instability (BP <90 systolic)
🚨 Severe chest pain >20 minutes
🚨 Arrhythmias with hemodynamic compromise
🚨 Acute heart failure signs

PRE-HOSPITAL CARE:
- Patient positioning: Semi-fowler s or comfortable
- NPO (nothing by mouth)
- Reassurance and emotional support
- Prepare for rapid transport

MONITORING:
- Vital signs every 5-15 minutes
- Continuous ECG monitoring
- Pain scale assessment
- Response to interventions

PATIENT/FAMILY EDUCATION:
- Explain need for immediate evaluation
- Importance of not delaying treatment
- What to expect during transport/ED evaluation'
    ],

    'respiratory_distress' => [
        'title' => 'Respiratory Distress Management',
        'category' => 'Pulmonology',
        'description' => 'Assessment and management of breathing difficulties',
        'template' => 'RESPIRATORY DISTRESS ASSESSMENT:

PRIMARY ASSESSMENT:
• Respiratory Rate: ___ breaths/minute (Normal: 12-20)
• Oxygen Saturation: ___% on room air
• Breathing Pattern: Regular/Irregular, Shallow/Deep
• Use of accessory muscles: Yes/No
• Cyanosis: Central/Peripheral/None
• Mental status: Alert/Confused/Agitated

SYMPTOM EVALUATION:
1. Onset: Acute (<24hrs) / Gradual (>24hrs)
2. Severity (1-10): ___
3. Associated symptoms:
   □ Chest pain         □ Fever
   □ Cough (productive/dry)  □ Wheezing
   □ Leg swelling       □ Palpitations

PHYSICAL EXAMINATION:
• Position: Sitting upright/Tripod position
• Speech: Full sentences/Short phrases/Single words
• Chest inspection: Symmetry, retractions
• Auscultation: Clear/Crackles/Wheezes/Decreased sounds

IMMEDIATE INTERVENTIONS:
1. Positioning:
   • Semi-fowler s or high-fowler s position
   • Leaning forward on bedside table if preferred

2. Oxygen Therapy:
   • If SpO2 <90%: Start O2 2-4L/min via nasal cannula
   • If SpO2 <85%: Consider non-rebreather mask
   • Target SpO2: 92-96% (88-92% in COPD patients)

3. Bronchodilator Therapy (if wheezing):
   • Salbutamol (Albuterol) 2.5mg nebulized
   • Can repeat every 20 minutes x3 if needed
   • Monitor heart rate and tremors

4. Supportive Care:
   • IV access for medications/fluids
   • Calm, reassuring environment
   • Avoid sedatives unless intubated

DIAGNOSTIC CONSIDERATIONS:
□ Asthma exacerbation    □ COPD exacerbation
□ Pneumonia             □ Pulmonary edema
□ Pneumothorax          □ Pulmonary embolism
□ Anaphylaxis           □ Foreign body aspiration

CRITICAL SIGNS - IMMEDIATE INTERVENTION:
🚨 SpO2 <85% despite oxygen
🚨 Respiratory rate >30 or <8
🚨 Use of accessory muscles
🚨 Inability to speak in full sentences
🚨 Cyanosis (central)
🚨 Altered mental status
🚨 Silent chest (no air movement)

MONITORING:
- Vital signs every 15 minutes initially
- Continuous pulse oximetry
- Peak flow if available and patient able
- Response to bronchodilators

PATIENT POSITIONING OPTIONS:
• High-fowler s: 60-90° elevation
• Tripod: Sitting, leaning forward on arms
• Orthopneic: Sitting upright, feet dependent

DISCHARGE CRITERIA (if stable):
- SpO2 >92% on room air
- Respiratory rate <25
- Able to speak in full sentences
- Good response to bronchodilators
- Stable vital signs x 2 hours

FOLLOW-UP INSTRUCTIONS:
- Return if breathing worsens
- Continue prescribed medications
- Avoid triggers (if asthma)
- Primary care follow-up in 24-48 hours'
    ],

    'wound_care' => [
        'title' => 'Basic Wound Care Protocol',
        'category' => 'General Surgery',
        'description' => 'Assessment and treatment of cuts, lacerations, and wounds',
        'template' => 'WOUND CARE ASSESSMENT & TREATMENT:

WOUND ASSESSMENT:
1. Location: ________________
2. Size: Length ___cm x Width ___cm x Depth ___cm
3. Type: □ Laceration □ Puncture □ Abrasion □ Burn □ Other: ___
4. Mechanism of injury: ________________
5. Time since injury: ___ hours

WOUND CHARACTERISTICS:
• Edges: Clean/Jagged/Gaping
• Contamination: Clean/Contaminated/Infected
• Bleeding: Active/Controlled/Oozing
• Foreign bodies visible: Yes/No - Describe: ___
• Surrounding tissue: Normal/Swollen/Red/Warm

INFECTION ASSESSMENT:
□ Redness extending from wound
□ Warmth around wound
□ Pus or purulent drainage
□ Red streaking (lymphangitis)
□ Fever or chills
□ Increased pain or tenderness

IMMEDIATE CARE:
1. Bleeding Control:
   • Direct pressure with clean gauze
   • Elevation if possible
   • Pressure points if needed
   • Tourniquet only for life-threatening hemorrhage

2. Wound Cleaning:
   • Irrigate with normal saline or clean water
   • Remove visible debris gently
   • DO NOT remove embedded objects
   • Clean from center outward

3. Pain Management:
   • Topical anesthetic if available
   • Oral pain medication as appropriate
   • Ice pack around (not on) wound

TREATMENT PROTOCOL:
1. Small Cuts (<2cm, shallow):
   • Clean thoroughly
   • Apply antibiotic ointment
   • Cover with adhesive bandage
   • Keep dry for 24 hours

2. Larger Wounds (>2cm or deep):
   • May require sutures/staples
   • Consider steri-strips if appropriate
   • Apply sterile dressing
   • Splint if over joint

3. Puncture Wounds:
   • Do not close
   • Clean and irrigate carefully
   • Apply loose dressing
   • Monitor for signs of infection

TETANUS PROPHYLAXIS:
□ Last tetanus shot: _____ (year)
□ Clean minor wound: Tetanus shot if >10 years
□ Dirty/major wound: Tetanus shot if >5 years
□ Unknown immunization: Give tetanus shot

DRESSING INSTRUCTIONS:
• Initial: Sterile gauze + tape or bandage
• Change daily or if soaked/dirty
• Keep wound clean and dry
• Apply thin layer antibiotic ointment

SIGNS REQUIRING IMMEDIATE MEDICAL ATTENTION:
🚨 Uncontrolled bleeding
🚨 Signs of infection (redness, warmth, pus, fever)
🚨 Loss of function/numbness
🚨 Red streaking from wound
🚨 Foreign object embedded
🚨 Animal or human bite
🚨 Wound edges gaping wide

HOME CARE INSTRUCTIONS:
1. Keep wound clean and dry
2. Change dressing daily
3. Watch for signs of infection
4. Take pain medication as directed
5. Return for suture removal in __ days (if applicable)

ACTIVITY RESTRICTIONS:
• Avoid soaking (baths, swimming) for 48 hours
• Light activity only if wound over joint
• No heavy lifting if upper extremity wound

FOLLOW-UP:
- Return in 24-48 hours if signs of infection
- Suture removal in 5-14 days (location dependent)
- Primary care follow-up as needed'
    ],

    'medication_advice' => [
        'title' => 'Medication Counseling Template',
        'category' => 'Pharmacy',
        'description' => 'General medication advice and counseling points',
        'template' => 'MEDICATION COUNSELING GUIDE:

PATIENT INFORMATION:
• Patient Name: ________________
• Age: _____ Weight: _____ kg
• Allergies: ________________
• Current medications: ________________

PRESCRIBED MEDICATION:
• Drug name: ________________
• Strength: ________________
• Dosage form: Tablet/Capsule/Liquid/Injection
• Quantity: ________________
• Directions: ________________

MEDICATION EDUCATION:

1. PURPOSE & INDICATION:
"This medication is prescribed to treat/prevent: ________________
It works by: ________________"

2. DOSING INSTRUCTIONS:
• Take ___ times per day
• Take with/without food
• Best time to take: Morning/Evening/With meals
• If you miss a dose: ________________
• Duration of treatment: ___ days/weeks/months

3. IMPORTANT PRECAUTIONS:
□ Do not crush or chew (if extended-release)
□ Take with full glass of water
□ Complete full course even if feeling better
□ Do not share with others
□ Store in cool, dry place

4. COMMON SIDE EFFECTS:
Most common (inform patient):
• ________________
• ________________
• ________________

"These are usually mild and improve as your body adjusts."

5. SERIOUS SIDE EFFECTS - SEEK MEDICAL ATTENTION:
⚠️ ________________
⚠️ ________________
⚠️ ________________

6. DRUG INTERACTIONS:
• Avoid alcohol: Yes/No
• Foods to avoid: ________________
• Other medications to avoid: ________________
• Inform other doctors about this medication

7. MONITORING REQUIREMENTS:
□ Blood tests needed: ________________
□ Blood pressure monitoring
□ Weight monitoring
□ Other: ________________

LIFESTYLE MODIFICATIONS:
• Diet: ________________
• Exercise: ________________
• Other: ________________

PATIENT UNDERSTANDING CHECK:
"Let me make sure Ive explained everything clearly:
• What is this medication for?
• How often will you take it?
• What time of day?
• What should you do if you miss a dose?
• What side effects should you watch for?"

FOLLOW-UP PLAN:
• Next appointment: ________________
• When to call if problems: ________________
• Pharmacy contact for questions: ________________

MEDICATION ADHERENCE TIPS:
1. Set daily alarms/reminders
2. Use pill organizer for multiple medications
3. Keep medication diary
4. Don t stop suddenly without consulting doctor
5. Get refills before running out

SPECIAL INSTRUCTIONS:
• For antibiotics: Take exactly as prescribed, complete full course
• For pain medication: Use only as needed, don t exceed recommended dose
• For chronic conditions: This is long-term therapy, take daily
• For PRN medications: Use only when symptoms occur

COST AND INSURANCE:
• Insurance coverage: ________________
• Generic alternative available: Yes/No
• Patient assistance programs if needed
• Estimated cost: ________________

EMERGENCY CONTACT:
"If you have severe side effects or allergic reaction:
• Call emergency services: ___
• Go to nearest emergency room
• Contact prescribing physician: ___"

Patient signature: ________________ Date: ________
Counselor signature: ________________'
    ],

    'schedule_visit' => [
        'title' => 'Schedule Visit Template',
        'category' => 'Administrative',
        'description' => 'Template for scheduling follow-up visits and appointments',
        'template' => 'MEDICAL VISIT SCHEDULING TEMPLATE:

PATIENT INFORMATION:
• Patient Name: ________________
• Case ID: #___
• Contact Number: ________________
• Preferred contact method: Phone/SMS/Email
• Village/Location: ________________

CURRENT VISIT SUMMARY:
• Date of consultation: ________________
• Chief complaint: ________________
• Diagnosis/Assessment: ________________
• Treatment provided: ________________

FOLLOW-UP REQUIREMENTS:
1. Reason for follow-up:
   □ Monitor treatment response
   □ Medication adjustment needed
   □ Test results review
   □ Symptom reassessment
   □ Chronic disease management
   □ Preventive care
   □ Other: ________________

2. Urgency Level:
   □ Routine (within 2-4 weeks)
   □ Semi-urgent (within 1 week)
   □ Urgent (within 24-48 hours)
   □ Emergency (immediate)

RECOMMENDED TIMING:
• Next visit recommended in: ___ days/weeks
• Specific date if critical: ________________
• Best day of week for patient: ________________
• Preferred time: Morning/Afternoon/Evening

PRE-VISIT INSTRUCTIONS:
□ Continue current medications as prescribed
□ Complete laboratory tests before visit:
  - Blood tests: ________________
  - Urine tests: ________________
  - Other: ________________
□ Bring all current medications
□ Bring previous medical records
□ Fast for ___ hours if blood work needed
□ Measure and record: Blood pressure/Weight/Temperature

WHAT TO MONITOR UNTIL NEXT VISIT:
1. Symptoms to track:
   • ________________: Improvement/Same/Worse
   • ________________: Frequency/Severity
   • ________________: Duration/Pattern

2. Measurements to record:
   □ Daily weight (if heart/kidney condition)
   □ Blood pressure (if hypertension)
   □ Blood sugar (if diabetes)
   □ Temperature (if infection)
   □ Pain scale (1-10)

3. Medication compliance:
   □ Keep medication diary
   □ Note any side effects
   □ Record missed doses

WARNING SIGNS - CONTACT IMMEDIATELY:
🚨 ________________
🚨 ________________
🚨 ________________
"Do not wait for scheduled visit if these occur"

CONTACT INFORMATION:
• Primary contact (ANMS): ________________
• Doctor contact (if available): ________________
• Emergency contact: ________________
• Clinic/Health center: ________________

APPOINTMENT CONFIRMATION:
□ Patient understands follow-up timing
□ Patient has transportation arranged
□ Patient has contact information
□ Patient knows what to bring
□ Patient understands warning signs

BACKUP PLAN:
If patient cannot make scheduled appointment:
• Contact ___ days in advance
• Alternative dates: ________________
• Telemedicine option: Available/Not available
• Home visit if needed: Possible/Contact ANMS

COORDINATION WITH OTHER PROVIDERS:
□ ANMS officer informed of follow-up plan
□ Referral to specialist: Yes/No - ________________
□ Coordination with family members needed
□ Community health worker follow-up

DOCUMENTATION REQUIREMENTS:
□ Update patient record after follow-up
□ Note compliance with treatment
□ Document any changes in condition
□ Update medication list
□ Record vital signs and assessments

PATIENT EDUCATION REINFORCEMENT:
Visit will include review of:
□ Disease/condition education
□ Medication counseling
□ Lifestyle modifications
□ Prevention strategies
□ Self-care instructions

SPECIAL CONSIDERATIONS:
• Transportation difficulties: ________________
• Language barriers: ________________
• Cultural considerations: ________________
• Economic constraints: ________________
• Family support system: ________________

This follow-up visit is essential for:
✓ Monitoring your progress
✓ Adjusting treatment as needed
✓ Preventing complications
✓ Ensuring best possible outcomes

Patient acknowledgment: "I understand the importance of this follow-up visit and will contact the healthcare team if I have any concerns before then."

Scheduled by: ________________ Date: ________'
    ]
];

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
                            <i class="fas fa-book-medical me-2"></i>Medical Health Guide
                        </h1>
                        <p class="text-muted mb-0">
                            Educational health information to help you understand common health conditions and self-care guidance
                        </p>
                    </div>
                    <div>
                        <a href="dashboard.php" class="btn btn-outline-primary me-2">
                            <i class="fas fa-arrow-left me-1"></i>Back to Dashboard
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Template Categories -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="template-categories">
                <div class="d-flex flex-wrap gap-2 justify-content-center">
                    <button class="btn btn-outline-primary category-filter active" data-category="all">
                        <i class="fas fa-th-large me-1"></i>All Guides
                    </button>
                    <button class="btn btn-outline-info category-filter" data-category="General Medicine">
                        <i class="fas fa-user-md me-1"></i>General Health
                    </button>
                    <button class="btn btn-outline-danger category-filter" data-category="Emergency Medicine">
                        <i class="fas fa-ambulance me-1"></i>Emergency
                    </button>
                    <button class="btn btn-outline-warning category-filter" data-category="Pulmonology">
                        <i class="fas fa-lungs me-1"></i>Respiratory
                    </button>
                    <button class="btn btn-outline-success category-filter" data-category="General Surgery">
                        <i class="fas fa-cut me-1"></i>Wound Care
                    </button>
                    <button class="btn btn-outline-secondary category-filter" data-category="Administrative">
                        <i class="fas fa-calendar-check me-1"></i>Medications
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Templates Grid -->
    <div class="row">
        <?php foreach ($templates as $template_id => $template): ?>
            <div class="col-lg-6 col-xl-4 mb-4 template-card" data-category="<?php echo $template['category']; ?>">
                <div class="card shadow h-100">
                    <div class="card-header bg-<?php echo $template['category'] === 'Emergency Medicine' ? 'danger' : ($template['category'] === 'General Medicine' ? 'primary' : ($template['category'] === 'Pulmonology' ? 'warning' : ($template['category'] === 'General Surgery' ? 'success' : 'info'))); ?> text-white">
                        <h5 class="mb-0 d-flex align-items-center">
                            <i class="fas fa-<?php echo $template['category'] === 'Emergency Medicine' ? 'ambulance' : ($template['category'] === 'General Medicine' ? 'user-md' : ($template['category'] === 'Pulmonology' ? 'lungs' : ($template['category'] === 'General Surgery' ? 'cut' : 'clipboard-list'))); ?> me-2"></i>
                            <?php echo $template['title']; ?>
                        </h5>
                    </div>
                    <div class="card-body d-flex flex-column">
                        <div class="template-info mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="badge bg-secondary"><?php echo $template['category']; ?></span>
                                <small class="text-muted">
                                    <i class="fas fa-file-alt me-1"></i><?php echo strlen($template['template']); ?> chars
                                </small>
                            </div>
                            <p class="text-muted"><?php echo $template['description']; ?></p>
                        </div>

                        <div class="template-preview mb-3 flex-grow-1">
                            <h6><i class="fas fa-eye text-info me-2"></i>Preview:</h6>
                            <div class="preview-content">
                                <?php echo nl2br(htmlspecialchars(substr($template['template'], 0, 200))); ?>
                                <?php if (strlen($template['template']) > 200): ?>
                                    <span class="text-muted">... <strong>(<?php echo strlen($template['template']) - 200; ?> more characters)</strong></span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="template-actions mt-auto">
                            <div class="d-grid gap-2">
                                <button class="btn btn-primary" onclick="viewFullTemplate('<?php echo $template_id; ?>')">
                                    <i class="fas fa-eye me-2"></i>Read Full Guide
                                </button>
                                <button class="btn btn-outline-info btn-sm" onclick="copyTemplate('<?php echo $template_id; ?>')">
                                    <i class="fas fa-copy me-2"></i>Copy to Clipboard
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Template Modal -->
<div class="modal fade" id="templateModal" tabindex="-1" aria-labelledby="templateModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="templateModalLabel">
                    <i class="fas fa-book-open me-2"></i>Health Guide
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="templateContent">
                    <!-- Template content will be loaded here -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="copyTemplateBtn">
                    <i class="fas fa-copy me-1"></i>Copy to Clipboard
                </button>
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

.template-categories {
    background: white;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.category-filter {
    border-radius: 20px;
    font-weight: 600;
    transition: all 0.3s ease;
}

.category-filter:hover, .category-filter.active {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.template-card {
    transition: all 0.3s ease;
}

.template-card:hover {
    transform: translateY(-5px);
}

.card {
    border-radius: 15px;
    overflow: hidden;
    transition: all 0.3s ease;
}

.card:hover {
    box-shadow: 0 10px 30px rgba(0,0,0,0.15);
}

.template-preview {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-radius: 8px;
    padding: 15px;
    border-left: 4px solid #007bff;
}

.preview-content {
    font-family: 'Courier New', monospace;
    font-size: 0.85rem;
    line-height: 1.4;
    color: #495057;
    white-space: pre-wrap;
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

.modal-dialog {
    max-width: 90%;
}

.modal-body {
    max-height: 70vh;
    overflow-y: auto;
}

#templateContent {
    font-family: 'Courier New', monospace;
    font-size: 0.9rem;
    line-height: 1.6;
    white-space: pre-wrap;
    background: #f8f9fa;
    padding: 20px;
    border-radius: 8px;
    border-left: 4px solid #007bff;
}
</style>

<script>
const templates = <?php echo json_encode($templates); ?>;

function filterTemplates(category) {
    const cards = document.querySelectorAll('.template-card');
    const buttons = document.querySelectorAll('.category-filter');

    // Update active button
    buttons.forEach(btn => btn.classList.remove('active'));
    document.querySelector(`[data-category="${category}"]`).classList.add('active');

    // Filter cards
    cards.forEach(card => {
        if (category === 'all' || card.dataset.category === category) {
            card.style.display = 'block';
            card.style.animation = 'fadeIn 0.5s ease';
        } else {
            card.style.display = 'none';
        }
    });
}

function viewFullTemplate(templateId) {
    const template = templates[templateId];
    if (template) {
        document.getElementById('templateModalLabel').innerHTML = 
            `<i class="fas fa-book-open me-2"></i>${template.title}`;
        document.getElementById('templateContent').textContent = template.template;

        // Set up action buttons
        document.getElementById('copyTemplateBtn').onclick = () => copyTemplate(templateId);

        new bootstrap.Modal(document.getElementById('templateModal')).show();
    }
}

function copyTemplate(templateId) {
    const template = templates[templateId];
    if (template) {
        navigator.clipboard.writeText(template.template).then(() => {
            // Show success message
            const btn = document.getElementById('copyTemplateBtn');
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-check me-1"></i>Copied!';
            btn.classList.remove('btn-primary');
            btn.classList.add('btn-success');

            setTimeout(() => {
                btn.innerHTML = originalText;
                btn.classList.remove('btn-success');
                btn.classList.add('btn-primary');
            }, 2000);
        }).catch(() => {
            alert('Failed to copy guide to clipboard');
        });
    }
}

// Add event listeners for category filtering
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.category-filter').forEach(btn => {
        btn.addEventListener('click', function() {
            filterTemplates(this.dataset.category);
        });
    });
});

// Add CSS animation
const style = document.createElement('style');
style.textContent = `
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }
`;
document.head.appendChild(style);
</script>

<?php include '../includes/footer.php'; ?>
