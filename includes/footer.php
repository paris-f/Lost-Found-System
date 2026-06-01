</main>
    <footer>
        <div class="footer-content">
            <p>&copy; <?php echo date("Y"); ?> Deadlock Lost & Found Tracking System. All rights reserved.</p>
            
            <nav class="footer-nav">
                
                <?php if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true): ?>
                    <a href="logout.php">Logout</a> |
                    <?php if ($_SESSION['role'] === 'Admin'): ?>
                        <a href="admin/admin_dashboard.php">Admin</a> 
                    <?php endif; ?>
                <?php else: ?>
                    
                <?php endif; ?>
                
            </nav>
        </div>
    </footer>
</body>
</html>