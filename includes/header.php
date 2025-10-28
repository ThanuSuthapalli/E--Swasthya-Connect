<?php
if (!isset($page_title)) {
    $page_title = SITE_NAME;
}

// Get notifications if user is logged in
$notifications = [];
$unread_count = 0;
if (isLoggedIn()) {
    $notifications = getUnreadNotifications($_SESSION['user_id']);
    $unread_count = count($notifications);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="icon" type="image/svg+xml" href="https://www.svgrepo.com/show/421923/health-heart-heart-rate.svg">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/css/style.css">
    <link rel="icon" type="image/x-icon" href="<?php echo SITE_URL; ?>/images/favicon.ico">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand fw-bold" href="<?php echo SITE_URL; ?>">
                <i class="fas fa-hospital-alt"></i> E- Swasthya Connect 
            </a>

            <?php if (isLoggedIn()): ?>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                    <span class="navbar-toggler-icon"></span>
                </button>

                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav me-auto">
                        <?php
                        $role = $_SESSION['user_role'];
                        $base_url = SITE_URL . '/' . $role;
                        ?>

                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo $base_url; ?>/dashboard.php">
                                <i class="fas fa-tachometer-alt"></i> Dashboard
                            </a>
                        </li>

                        <?php if ($role === 'villager'): ?>
                            <li class="nav-item">
                                <a class="nav-link" href="<?php echo $base_url; ?>/report_problem.php">
                                    <i class="fas fa-plus-circle"></i> Report Problem
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="<?php echo $base_url; ?>/my_problems.php">
                                    <i class="fas fa-list"></i> My Problems
                                </a>
                            </li>
                        <?php elseif ($role === 'avms'): ?>
                            <li class="nav-item">
                                <a class="nav-link" href="<?php echo $base_url; ?>/manage_problems.php">
                                    <i class="fas fa-tasks"></i> Manage Problems
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="<?php echo $base_url; ?>/my_assignments.php">
                                    <i class="fas fa-folder-open"></i> My Cases
                                </a>
                            </li>
                        <?php elseif ($role === 'doctor'): ?>
                            <li class="nav-item">
                                <a class="nav-link" href="<?php echo $base_url; ?>/all_escalated.php">
                                    <i class="fas fa-arrow-up"></i> Escalated Cases
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="<?php echo $base_url; ?>/my_responses.php">
                                    <i class="fas fa-comments"></i> My Responses
                                </a>
                            </li>
                        <?php elseif ($role === 'admin'): ?>
                            <li class="nav-item">
                                <a class="nav-link" href="<?php echo $base_url; ?>/manage_users.php">
                                    <i class="fas fa-users"></i> Manage Users
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="<?php echo $base_url; ?>/all_problems.php">
                                    <i class="fas fa-list-alt"></i> All Problems
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>

                    <ul class="navbar-nav">
                        <!-- Notifications -->
                        <li class="nav-item dropdown">
                            <a class="nav-link position-relative" href="#" id="notificationDropdown" role="button" data-bs-toggle="dropdown">
                                <i class="fas fa-bell"></i>
                                <?php if ($unread_count > 0): ?>
                                    <span class="notification-badge position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                        <?php echo $unread_count > 9 ? '9+' : $unread_count; ?>
                                    </span>
                                <?php endif; ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end notification-dropdown">
                                <li><h6 class="dropdown-header">Notifications</h6></li>
                                <?php if (empty($notifications)): ?>
                                    <li><span class="dropdown-item-text text-muted">No new notifications</span></li>
                                <?php else: ?>
                                    <?php foreach (array_slice($notifications, 0, 5) as $notification): ?>
                                        <li>
                                            <a class="dropdown-item" href="<?php echo $base_url; ?>/view_problem.php?id=<?php echo $notification['problem_id']; ?>" data-notification-id="<?php echo $notification['id']; ?>" data-problem-id="<?php echo $notification['problem_id']; ?>">
                                                <strong><?php echo htmlspecialchars($notification['title']); ?></strong><br>
                                                <small class="text-muted"><?php echo htmlspecialchars(substr($notification['message'], 0, 50)) . '...'; ?></small>
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item text-center" href="<?php echo $base_url; ?>/notifications.php">View All</a></li>
                                <?php endif; ?>
                            </ul>
                        </li>

                        <!-- User Menu -->
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                                <i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($_SESSION['user_name']); ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><span class="dropdown-item-text"><small class="text-muted"><?php echo ucfirst($_SESSION['user_role']); ?></small></span></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="<?php echo $base_url; ?>/profile.php"><i class="fas fa-user-edit"></i> Profile</a></li>
                                <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/login/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                            </ul>
                        </li>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
    </nav>

    <!-- Alert Messages -->
    <?php
    $success = getMessage('success');
    $error = getMessage('error');
    $warning = getMessage('warning');
    $info = getMessage('info');
    ?>

    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show m-0" role="alert">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show m-0" role="alert">
            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($warning): ?>
        <div class="alert alert-warning alert-dismissible fade show m-0" role="alert">
            <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($warning); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($info): ?>
        <div class="alert alert-info alert-dismissible fade show m-0" role="alert">
            <i class="fas fa-info-circle"></i> <?php echo htmlspecialchars($info); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="container-fluid py-4">