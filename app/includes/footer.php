            </div><!-- /.page-content -->
        </main><!-- /.main-content -->
    </div><!-- /.dashboard-container -->
    
    <script src="<?php echo BASE_URL; ?>public/js/instructor_dashboard.js"></script>
    <?php if (isset($include_assignments_js) && $include_assignments_js): ?>
    <script src="<?php echo BASE_URL; ?>public/js/assignments.js"></script>
    <?php endif; ?>
    
    <?php if (isset($additional_scripts) && !empty($additional_scripts)): ?>
        <?php foreach ($additional_scripts as $script): ?>
            <script src="<?php echo BASE_URL . $script; ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
    
    <?php if (isset($page_specific_script)): ?>
    <script>
        <?php echo $page_specific_script; ?>
    </script>
    <?php endif; ?>

    <script>
        // Default logout function if not defined in page_specific_script
        if (typeof handleLogout !== 'function') {
            function handleLogout() {
                window.location.href = "<?php echo BASE_URL; ?>auth/logout";
            }
        }
    </script>
</body>
</html> 