<?php
require_once '../includes/config.php';
requireLogin();

$page_title = 'Emergency Contacts - Village Health Connect';
include '../includes/header.php';
?>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="dashboard-header">
                <h1 class="text-center">
                    <i class="fas fa-phone-alt text-danger"></i> Emergency Contacts
                </h1>
                <p class="text-center text-muted">Important numbers for immediate help</p>
            </div>

            <div class="row">
                <div class="col-md-6 mb-4">
                    <div class="card border-danger">
                        <div class="card-header bg-danger text-white text-center">
                            <h5><i class="fas fa-ambulance"></i> Medical Emergency</h5>
                        </div>
                        <div class="card-body text-center">
                            <h2 class="text-danger">108</h2>
                            <p>Ambulance & Emergency Medical Services</p>
                            <a href="tel:108" class="btn btn-danger btn-lg w-100">
                                <i class="fas fa-phone"></i> Call Now
                            </a>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 mb-4">
                    <div class="card border-danger">
                        <div class="card-header bg-info text-white text-center">
                            <h5><i class="fas fa-hospital"></i> Medical Help</h5>
                        </div>
                        <div class="card-body text-center">
                            <h2 class="text-info">102</h2>
                            <p>Medical Emergency Helpline</p>
                            <a href="tel:102" class="btn btn-info btn-lg w-100">
                                <i class="fas fa-phone"></i> Call Now
                            </a>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 mb-4">
                    <div class="card border-primary">
                        <div class="card-header bg-primary text-white text-center">
                            <h5><i class="fas fa-shield-alt"></i> Police</h5>
                        </div>
                        <div class="card-body text-center">
                            <h2 class="text-primary">100</h2>
                            <p>Police Emergency & Safety</p>
                            <a href="tel:100" class="btn btn-primary btn-lg w-100">
                                <i class="fas fa-phone"></i> Call Now
                            </a>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 mb-4">
                    <div class="card border-warning">
                        <div class="card-header bg-warning text-dark text-center">
                            <h5><i class="fas fa-fire"></i> Fire Emergency</h5>
                        </div>
                        <div class="card-body text-center">
                            <h2 class="text-warning">101</h2>
                            <p>Fire Department & Rescue</p>
                            <a href="tel:101" class="btn btn-warning btn-lg w-100">
                                <i class="fas fa-phone"></i> Call Now
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="alert alert-info text-center">
                <h5><i class="fas fa-info-circle"></i> Important Notes</h5>
                <ul class="list-unstyled mb-0">
                    <li>• These services are available 24/7 across India</li>
                    <li>• For non-emergency issues, use the problem reporting system</li>
                    <li>• Keep your location and contact details ready when calling</li>
                    <li>• Stay calm and provide clear information about the emergency</li>
                </ul>
            </div>

            <div class="text-center mt-4">
                <a href="dashboard.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>