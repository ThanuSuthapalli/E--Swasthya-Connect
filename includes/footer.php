    </div> <!-- End container-fluid -->

    <footer class="navbar-dark bg-primary py-4 mt-5">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5><i class="fas fa-hospital-alt"></i> E- Swasthya Connect </h5>
                    <p class="mb-2">Bridging Villagers and Doctors through ANMS</p>
                    <small class="text">A community service initiative</small>
                </div>
                <div class="col-md-3">
                    <h6>Quick Links</h6>
                    <ul class="list-unstyled">
                        <?php if (isLoggedIn()): ?>
                            <li><a href="<?php echo SITE_URL . '/' . $_SESSION['user_role']; ?>/dashboard.php" class="text-light">Dashboard</a></li>
                            <li><a href="<?php echo SITE_URL . '/' . $_SESSION['user_role']; ?>/profile.php" class="text-light">Profile</a></li>
                        <?php else: ?>
                            <li><a href="<?php echo SITE_URL; ?>/login/login.php" class="text-light">Login</a></li>
                            <li><a href="<?php echo SITE_URL; ?>/login/register.php" class="text-light">Register</a></li>
                        <?php endif; ?>
                    </ul>
                </div>
                <div class="col-md-3">
                    <h6>Emergency Contacts</h6>
                    <ul class="list-unstyled">
                        <li><i class="fas fa-phone"></i> <a href="tel:108" class="text-light">108 - Ambulance</a></li>
                        <li><i class="fas fa-phone"></i> <a href="tel:102" class="text-light">102 - Medical</a></li>
                        <li><i class="fas fa-phone"></i> <a href="tel:100" class="text-light">100 - Police</a></li>
                    </ul>
                </div>
            </div>
            <hr class="my-3">
            <div class="row">
                <div class="col-md-6">
                    <p class="mb-0">&copy; <?php echo date('Y'); ?> E- Swasthya Connect. All rights reserved.</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <small class="text">
                        <?php if (isLoggedIn()): ?>
                            Welcome, <?php echo htmlspecialchars($_SESSION['user_name']) ?>
                        <?php endif; ?>
                    </small>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo SITE_URL; ?>/js/main.js"></script>

    <!-- Auto-hide alerts after 5 seconds -->
    <script>
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert:not(.alert-permanent)');
            alerts.forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
    </script>
</body>
</html>