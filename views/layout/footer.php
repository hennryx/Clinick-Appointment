<!-- Bootstrap JS Bundle with Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- SweetAlert2 JS -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
<!-- Common JavaScript -->
<script>
    // Logout confirmation
    document.getElementById('logoutBtn')?.addEventListener('click', function(e) {
        e.preventDefault();
        const href = this.getAttribute('href');
        
        Swal.fire({
            title: 'Logout?',
            text: 'Are you sure you want to log out?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Yes, log out'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = href;
            }
        });
    });
    
    // Flash messages from session
    <?php if (isset($_SESSION['flash_message'])): ?>
        Swal.fire({
            icon: '<?= $_SESSION['flash_type'] ?? 'info' ?>',
            title: '<?= $_SESSION['flash_title'] ?? 'Notification' ?>',
            text: '<?= $_SESSION['flash_message'] ?>',
            timer: 3000,
            timerProgressBar: true
        });
        <?php 
        // Clear flash message after displaying
        unset($_SESSION['flash_message']); 
        unset($_SESSION['flash_type']); 
        unset($_SESSION['flash_title']); 
        ?>
    <?php endif; ?>
</script>
</body>
</html>