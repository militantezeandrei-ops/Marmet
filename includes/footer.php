            </div> <!-- .page-content -->
        </div> <!-- .main-layout-wrapper -->
    </main>
    
    <footer class="footer">
        <div class="footer-container">
            <div class="footer-grid">
                <div class="footer-brand">
                    <a href="<?php echo APP_URL; ?>" class="footer-logo">
                        <i class="fas fa-gem"></i>
                        <span>MARMET</span>
                    </a>
                    <p>Your premier destination for fashion and lifestyle products.</p>
                </div>
                
                <div class="footer-links">
                    <h4>Quick Links</h4>
                    <a href="<?php echo APP_URL; ?>">Home</a>
                    <a href="<?php echo APP_URL; ?>/catalog.php">Shop</a>
                </div>
                
                <div class="footer-contact">
                    <h4>Contact</h4>
                    <p><i class="fas fa-phone"></i> +63 912 345 6789</p>
                    <p><i class="fas fa-envelope"></i> info@marmet.com</p>
                </div>
            </div>
            
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> MARMET. All rights reserved.</p>
            </div>
        </div>
    </footer>
    
    <script src="<?php echo APP_URL; ?>/assets/js/main.js"></script>
    <?php if (isset($extraJs)): ?>
    <script src="<?php echo $extraJs; ?>"></script>
    <?php endif; ?>
</body>
</html>
