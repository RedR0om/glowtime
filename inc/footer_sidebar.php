        </div> <!-- container-fluid -->
        
        <!-- Footer -->
        <footer class="mt-5 py-4 text-center">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-12">
                        <p class="text-muted mb-1">&copy; <?= date('Y') ?> Glowtime Salon. All rights reserved.</p>
                        <small class="text-muted">Your beauty, our passion.</small>
                    </div>
                </div>
            </div>
        </footer>
    </main> <!-- main-content -->
    
    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom JS -->
    <script>
        // Sidebar toggle functionality
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            
            if (window.innerWidth <= 768) {
                // Mobile behavior
                sidebar.classList.toggle('show');
                overlay.classList.toggle('show');
            } else {
                // Desktop behavior
                sidebar.classList.toggle('collapsed');
                document.getElementById('mainContent').classList.toggle('expanded');
            }
        }
        
        // Close sidebar when clicking on overlay
        document.getElementById('sidebarOverlay').addEventListener('click', function() {
            toggleSidebar();
        });
        
        // Handle window resize
        window.addEventListener('resize', function() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            
            if (window.innerWidth > 768) {
                // Desktop: remove mobile classes
                sidebar.classList.remove('show');
                overlay.classList.remove('show');
            }
        });
        
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
        
        // Active navigation highlighting
        document.addEventListener('DOMContentLoaded', function() {
            const currentPath = window.location.pathname.split('/').pop();
            const navLinks = document.querySelectorAll('.sidebar-nav .nav-link');
            
            navLinks.forEach(link => {
                const href = link.getAttribute('href');
                if (href && href.includes(currentPath)) {
                    link.classList.add('active');
                } else {
                    link.classList.remove('active');
                }
            });
        });
    </script>
</body>
</html>
