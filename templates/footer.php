    </div><!-- End of main container -->

    <!-- Footer -->
    <footer class="bg-dark text-white py-4 mt-5">
        <div class="container">
            <div class="row">
                <div class="col-md-4">
                    <h5><i class="fas fa-leaf me-2"></i><?php echo APP_NAME; ?></h5>
                    <p>Reducing waste by connecting consumers with markets to purchase products nearing expiration date at discounted prices.</p>
                </div>
                <div class="col-md-4">
                    <h5>Quick Links</h5>
                    <ul class="list-unstyled">
                        <li><a href="<?php echo SITE_URL; ?>/index.php" class="text-white">Home</a></li>
                        <?php if (!isset($_SESSION['user_id'])): ?>
                            <li><a href="<?php echo SITE_URL; ?>/register.php" class="text-white">Register</a></li>
                            <li><a href="<?php echo SITE_URL; ?>/login.php" class="text-white">Login</a></li>
                        <?php endif; ?>
                    </ul>
                </div>
                <div class="col-md-4">
                    <h5>Contact</h5>
                    <address>
                        <i class="fas fa-map-marker-alt me-2"></i> 123 Sustainability St<br>
                        <i class="fas fa-phone me-2"></i> (123) 456-7890<br>
                        <i class="fas fa-envelope me-2"></i> contact@sustainability-ecommerce.com
                    </address>
                </div>
            </div>
            <hr>
            <div class="text-center">
                <p>&copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom JavaScript -->
    <script src="<?php echo SITE_URL; ?>/assets/js/script.js"></script>
</body>
</html> 