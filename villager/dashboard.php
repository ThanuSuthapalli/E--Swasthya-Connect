<?php
require_once '../includes/config.php';
requireRole('villager');

$page_title = 'Villager Dashboard - E-Swasthya Connect';

try {
    $pdo = getDBConnection();

    // Get villager's problems with assigned user info
    $stmt = $pdo->prepare("
        SELECT p.*, 
               avms.name as avms_name, 
               avms.phone as avms_phone,
               doc.name as doctor_name
        FROM problems p 
        LEFT JOIN users avms ON p.assigned_to = avms.id 
        LEFT JOIN users doc ON p.escalated_to = doc.id
        WHERE p.villager_id = ? 
        ORDER BY p.created_at DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $problems = $stmt->fetchAll();

    // Get statistics
    $stats = getProblemStats($_SESSION['user_id'], 'villager');
    $stats['total'] = count($problems);

    // Get recent notifications
    $notifications = getUnreadNotifications($_SESSION['user_id']);

} catch (Exception $e) {
    error_log("Villager dashboard error: " . $e->getMessage());
    $problems = [];
    $stats = ['total' => 0];
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
                            <i class="fas fa-user-circle text-primary"></i> 
                            Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?>!
                        </h1>
                        <p class="text-muted mb-0">
                            <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($_SESSION['user_village'] ?? 'Village not specified'); ?>
                            <?php if (!empty($notifications)): ?>
                                | <i class="fas fa-bell text-warning"></i> You have <?php echo count($notifications); ?> new notification<?php echo count($notifications) > 1 ? 's' : ''; ?>
                            <?php endif; ?>
                        </p>
                    </div>
                    <div class="col-md-4 text-md-end">
                        <a href="report_problem.php" class="btn btn-primary btn-lg">
                            <i class="fas fa-plus-circle"></i> Report New Problem
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-lg-3 col-md-6">
            <div class="stats-card">
                <div class="stats-number"><?php echo $stats['total']; ?></div>
                <div class="stats-label">
                    <i class="fas fa-list-alt"></i> Total Problems
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="stats-card">
                <div class="stats-number text-warning"><?php echo $stats['pending'] ?? 0; ?></div>
                <div class="stats-label">
                    <i class="fas fa-clock"></i> Pending Review
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="stats-card">
                <div class="stats-number text-info"><?php echo ($stats['assigned'] ?? 0) + ($stats['in_progress'] ?? 0); ?></div>
                <div class="stats-label">
                    <i class="fas fa-cogs"></i> Being Handled
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="stats-card">
                <div class="stats-number text-success"><?php echo ($stats['resolved'] ?? 0) + ($stats['completed'] ?? 0); ?></div>
                <div class="stats-label">
                    <i class="fas fa-check-circle"></i> Resolved
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">
                        <i class="fas fa-bolt text-primary"></i> Quick Actions
                    </h5>
                    <div class="row">
                        <div class="col-lg-3 col-md-6 mb-2">
                            <a href="report_problem.php" class="btn btn-primary w-100">
                                <i class="fas fa-plus-circle"></i> Report New Problem
                            </a>
                        </div>
                        <div class="col-lg-3 col-md-6 mb-2">
                            <a href="my_problems.php" class="btn btn-outline-primary w-100">
                                <i class="fas fa-list"></i> View All Problems
                            </a>
                        </div>
                        <div class="col-lg-3 col-md-6 mb-2">
                            <a href="profile.php" class="btn btn-outline-secondary w-100">
                                <i class="fas fa-user-edit"></i> Update Profile
                            </a>
                        </div>
                        
                        <div class="col-lg-3 col-md-6 mb-2">
                            <a href="response_templates.php" class="btn btn-outline-info w-100">
                                <i class="fas fa-book-medical"></i> Medical Health Guide
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Recent Problems -->
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-history"></i> Your Recent Problems
                    </h5>
                    <?php if (count($problems) > 5): ?>
                        <a href="my_problems.php" class="btn btn-sm btn-outline-primary">View All</a>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php if (empty($problems)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-info-circle fa-3x text-muted mb-3"></i>
                            <h5>No problems reported yet</h5>
                            <p class="text-muted">When you need help with health or community issues, report them here and get connected with local assistance.</p>
                            <a href="report_problem.php" class="btn btn-primary">
                                <i class="fas fa-plus-circle"></i> Report Your First Problem
                            </a>
                        </div>
                    <?php else: ?>
                        <?php foreach (array_slice($problems, 0, 6) as $problem): ?>
                            <div class="problem-card priority-<?php echo $problem['priority']; ?>" data-status="<?php echo $problem['status']; ?>">
                                <div class="row align-items-center">
                                    <div class="col-md-6">
                                        <h6 class="mb-2">
                                            <?php echo htmlspecialchars($problem['title']); ?>
                                            <span class="badge priority-<?php echo $problem['priority']; ?> ms-2">
                                                <?php echo ucfirst($problem['priority']); ?>
                                            </span>
                                        </h6>
                                        <p class="text-muted mb-2">
                                            <?php echo htmlspecialchars(substr($problem['description'], 0, 100)) . '...'; ?>
                                        </p>
                                        <small class="text-muted">
                                            <i class="fas fa-calendar"></i> <?php echo formatDate($problem['created_at']); ?>
                                            <?php if ($problem['location']): ?>
                                                | <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($problem['location']); ?>
                                            <?php endif; ?>
                                        </small>
                                    </div>
                                    <div class="col-md-3">
                                        <?php if ($problem['photo']): ?>
                                            <img src="<?php echo htmlspecialchars(getUploadUrl($problem['photo'], '..')); ?>" 
                                                 class="problem-image" alt="Problem photo">
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-md-3 text-end">
                                        <div class="mb-2">
                                            <span class="badge status-<?php echo $problem['status']; ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $problem['status'])); ?>
                                            </span>
                                        </div>

                                        <?php if ($problem['avms_name']): ?>
                                            <small class="text-muted d-block">
                                                <i class="fas fa-user-tie"></i> <?php echo htmlspecialchars($problem['avms_name']); ?>
                                            </small>
                                        <?php endif; ?>

                                        <?php if ($problem['doctor_name']): ?>
                                            <small class="text-info d-block">
                                                <i class="fas fa-user-md"></i> <?php echo htmlspecialchars($problem['doctor_name']); ?>
                                            </small>
                                        <?php endif; ?>

                                        <div class="mt-2">
                                            <a href="view_problem.php?id=<?php echo $problem['id']; ?>" 
                                               class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-eye"></i> View Details
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Information Panel -->
        <div class="col-lg-4">
            <!-- Status Guide -->
            <div class="card mb-3">
                <div class="card-header">
                    <h6><i class="fas fa-info-circle"></i> Status Guide</h6>
                </div>
                <div class="card-body">
                    <div class="status-guide">
                        <div class="d-flex align-items-center mb-2">
                            <span class="badge status-pending me-2">Pending</span>
                            <small>Waiting for ANMS assignment</small>
                        </div>
                        <div class="d-flex align-items-center mb-2">
                            <span class="badge status-assigned me-2">Assigned</span>
                            <small>ANMS member reviewing your case</small>
                        </div>
                        <div class="d-flex align-items-center mb-2">
                            <span class="badge status-in_progress me-2">In Progress</span>
                            <small>ANMS working on your problem</small>
                        </div>
                        <div class="d-flex align-items-center mb-2">
                            <span class="badge status-escalated me-2">Escalated</span>
                            <small>Referred to doctor for medical help</small>
                        </div>
                        <div class="d-flex align-items-center">
                            <span class="badge status-resolved me-2">Resolved</span>
                            <small>Problem has been solved</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Emergency Contacts -->
            <div class="card mb-3">
                <div class="card-header bg-danger text-white">
                    <h6 class="mb-0"><i class="fas fa-phone"></i> Emergency Contacts</h6>
                </div>
                <div class="card-body">
                    <div class="emergency-contact mb-2">
                        <a href="tel:108" class="btn btn-outline-danger btn-sm w-100">
                            <i class="fas fa-ambulance"></i> 108 - Ambulance
                        </a>
                    </div>
                    <div class="emergency-contact mb-2">
                        <a href="tel:102" class="btn btn-outline-danger btn-sm w-100">
                            <i class="fas fa-hospital"></i> 102 - Medical Emergency
                        </a>
                    </div>
                    <div class="emergency-contact mb-2">
                        <a href="tel:102" class="btn btn-outline-danger btn-sm w-100">
                            <i class="fas fa-fire"></i> 101 - Fire Department & Rescue
                        </a>
                    </div>
                    <div class="emergency-contact">
                        <a href="tel:100" class="btn btn-outline-danger btn-sm w-100">
                            <i class="fas fa-shield-alt"></i> 100 - Police
                        </a>
                    </div>
                </div>
            </div>

            <!-- Quick Medical References -->
            <div class="card mb-3">
                <div class="card-header">
                    <h6><i class="fas fa-book-medical"></i> Quick Medical References</h6>
                </div>
                <div class="card-body">
                    <div class="accordion" id="medicalAccordion">
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#fever">
                                    Common Fever Relief
                                </button>
                            </h2>
                            <div id="fever" class="accordion-collapse collapse" data-bs-parent="#medicalAccordion">
                                <div class="accordion-body">
                                    <small>
                                        • Get plenty of rest and stay hydrated<br>
                                        • Take paracetamol 500mg if fever is high (consult doctor if unsure)<br>
                                        • Sponge bath with lukewarm water to reduce temperature<br>
                                        • Seek urgent medical care if fever is above 103°F (39.4°C)<br>
                                        • Watch for warning signs: breathing trouble, confusion, severe headache
                                    </small>
                                </div>
                            </div>
                        </div>

                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#diarrhea">
                                    Managing Diarrhea at Home
                                </button>
                            </h2>
                            <div id="diarrhea" class="accordion-collapse collapse" data-bs-parent="#medicalAccordion">
                                <div class="accordion-body">
                                    <small>
                                        • Drink Oral Rehydration Solution (ORS) or salted rice water frequently<br>
                                        • Eat light foods like bananas, rice, and toast<br>
                                        • Avoid spicy or oily foods until you recover<br>
                                        • Visit a clinic if you see blood in stool or feel dizzy<br>
                                        • Children may need zinc supplements—consult a health worker
                                    </small>
                                </div>
                            </div>
                        </div>

                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#wounds">
                                    Caring for Small Wounds
                                </button>
                            </h2>
                            <div id="wounds" class="accordion-collapse collapse" data-bs-parent="#medicalAccordion">
                                <div class="accordion-body">
                                    <small>
                                        • Wash hands or use sanitizer before touching the wound<br>
                                        • Press gently with a clean cloth to stop bleeding<br>
                                        • Rinse with clean water or mild saline solution<br>
                                        • Apply antiseptic if available and cover with a clean bandage<br>
                                        • Get a tetanus shot if the wound is deep or caused by rusty objects
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Health Tips -->
            <div class="card">
                <div class="card-header">
                    <h6><i class="fas fa-lightbulb"></i> Health Tips</h6>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled mb-0">
                        <li class="mb-2">
                            <i class="fas fa-check text-success"></i> 
                            <small>Drink clean, boiled water to prevent waterborne diseases</small>
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-check text-success"></i> 
                            <small>Wash hands frequently with soap and water</small>
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-check text-success"></i> 
                            <small>Keep your surroundings clean and free from stagnant water</small>
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-check text-success"></i> 
                            <small>Report health issues early for better treatment</small>
                        </li>
                        <li>
                            <i class="fas fa-check text-success"></i> 
                            <small>Follow vaccination schedules for children</small>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>