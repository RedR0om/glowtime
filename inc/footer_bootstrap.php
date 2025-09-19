    </div> <!-- container -->
    
    <!-- Footer -->
    <footer class="footer-salon text-light mt-5 py-4">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5><i class="bi bi-flower1"></i> Glowtime Salon</h5>
                    <p class="mb-0">Your beauty, our passion.</p>
                    <small class="text-muted">Professional salon management system</small>
                </div>
                <div class="col-md-6 text-md-end">
                    <p class="mb-1">&copy; <?= date('Y') ?> Glowtime Salon. All rights reserved.</p>
                    <small class="text-muted">Powered by Bootstrap 5</small>
                </div>
            </div>
        </div>
    </footer>
    
    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom JS -->
    <script>
        // Add fade-in animation to cards
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.card');
            cards.forEach((card, index) => {
                setTimeout(() => {
                    card.classList.add('fade-in');
                }, index * 100);
            });
        });
        
        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                if (bootstrap.Alert.getOrCreateInstance) {
                    const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
                    bsAlert.close();
                }
            });
        }, 5000);
        
        // Confirm dialogs for delete actions
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('btn-danger') || e.target.closest('.btn-danger')) {
                if (!confirm('Are you sure you want to perform this action?')) {
                    e.preventDefault();
                    return false;
                }
            }
        });
    </script>
</body>
</html>
