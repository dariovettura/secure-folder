<?php
/**
 * Frontend Class
 * 
 * Handles frontend display and user interface
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFM_Frontend {
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Shortcode for displaying files
        add_shortcode('secure_files', array($this, 'display_secure_files_shortcode'));
        
        // Shortcode for custom login form
        add_shortcode('secure_login', array($this, 'display_custom_login_shortcode'));
        
        // AJAX handlers for frontend
        add_action('wp_ajax_sfm_download_file', array($this, 'handle_file_download'));
        add_action('wp_ajax_nopriv_sfm_download_file', array($this, 'handle_file_download'));
        
        // Add endpoint for serving secure files
        add_action('wp_ajax_sfm_serve_file', array($this, 'handle_serve_file'));
        add_action('wp_ajax_nopriv_sfm_serve_file', array($this, 'handle_serve_file'));
        
        // Custom login/logout handlers
        add_action('wp_ajax_sfm_custom_login', array($this, 'handle_custom_login'));
        add_action('wp_ajax_nopriv_sfm_custom_login', array($this, 'handle_custom_login'));
        add_action('wp_ajax_sfm_custom_logout', array($this, 'handle_custom_logout'));
        add_action('wp_ajax_nopriv_sfm_custom_logout', array($this, 'handle_custom_logout'));
        
        
        // Enqueue frontend styles and scripts
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_styles'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
        
        // Block wp-admin access for custom roles
        add_action('admin_init', array($this, 'block_custom_roles_from_admin'));
        
        // Hide admin bar for custom roles
        add_action('after_setup_theme', array($this, 'hide_admin_bar_for_custom_roles'));
    }
    
    /**
     * Enqueue frontend styles
     */
    public function enqueue_frontend_styles() {
        wp_enqueue_style(
            'sfm-frontend',
            SFM_PLUGIN_URL . 'assets/css/frontend.css',
            array(),
            SFM_PLUGIN_VERSION
        );
    }
    
    /**
     * Enqueue frontend scripts
     */
    public function enqueue_frontend_scripts() {
        wp_enqueue_script('jquery');
        
        // Create a custom script handle for better debugging
        wp_register_script('sfm-frontend', '', array('jquery'), SFM_PLUGIN_VERSION, true);
        wp_enqueue_script('sfm-frontend');
        
        wp_localize_script('sfm-frontend', 'sfm_ajax', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sfm_nonce')
        ));
    }
    
    /**
     * Block custom roles from accessing wp-admin
     */
    public function block_custom_roles_from_admin() {
        if (!is_user_logged_in()) {
            return;
        }
        
        $user = wp_get_current_user();
        $user_roles = $user->roles;
        
        // Check if user has only custom roles (no standard WordPress roles except subscriber)
        $has_admin_access = false;
        foreach ($user_roles as $role) {
            if (in_array($role, array('administrator', 'editor', 'author', 'contributor'))) {
                $has_admin_access = true;
                break;
            }
        }
        
        // If user has no admin access and is trying to access wp-admin, redirect them
        if (!$has_admin_access && is_admin() && !wp_doing_ajax()) {
            wp_redirect(home_url());
            exit;
        }
    }
    
    /**
     * Hide admin bar for custom roles
     */
    public function hide_admin_bar_for_custom_roles() {
        if (!is_user_logged_in()) {
            return;
        }
        
        $user = wp_get_current_user();
        $user_roles = $user->roles;
        
        // Check if user has only custom roles (no standard WordPress roles)
        $has_admin_access = false;
        foreach ($user_roles as $role) {
            if (in_array($role, array('administrator', 'editor', 'author', 'contributor'))) {
                $has_admin_access = true;
                break;
            }
        }
        
        // If user has no admin access, hide the admin bar
        if (!$has_admin_access) {
            show_admin_bar(false);
        }
    }
    
    /**
     * Display secure files shortcode
     */
    public function display_secure_files_shortcode($atts) {
        // Check if user is logged in
        if (!is_user_logged_in()) {
            return '<p>You must be logged in to view secure files.</p>';
        }
        
        $atts = shortcode_atts(array(
            'category' => '',
            'limit' => 20,
            'show_description' => 'true',
            'show_download_count' => 'true',
            'show_upload_date' => 'true',
            'show_view_button' => 'true',
            'show_download_button' => 'true',
            'layout' => 'grid', // grid, list
            'title' => 'Secure Files',
            'empty_message' => 'No files available for your access level.'
        ), $atts);
        
        $files = $this->get_user_accessible_files($atts);
        
        if (empty($files)) {
            return '<p>' . esc_html($atts['empty_message']) . '</p>';
        }
        
        ob_start();
        ?>
        <div class="sfm-frontend-files sfm-layout-<?php echo esc_attr($atts['layout']); ?>">
            <h3><?php echo esc_html($atts['title']); ?></h3>
            <div class="sfm-files-grid">
                <?php foreach ($files as $file): ?>
                    <div class="sfm-file-item" data-file-id="<?php echo esc_attr($file->id); ?>">
                        <div class="sfm-file-icon">
                            <?php echo $this->get_file_icon($file->mime_type); ?>
                        </div>
                        <div class="sfm-file-info">
                            <h4 class="sfm-file-name"><?php echo esc_html($file->original_name); ?></h4>
                            <?php if ($atts['show_description'] === 'true' && !empty($file->description)): ?>
                                <p class="sfm-file-description"><?php echo esc_html($file->description); ?></p>
                            <?php endif; ?>
                            <div class="sfm-file-meta">
                                <?php if ($atts['show_upload_date'] === 'true'): ?>
                                    <span class="sfm-upload-date">
                                        Uploaded: <?php echo date('M j, Y', strtotime($file->uploaded_at)); ?>
                                    </span>
                                <?php endif; ?>
                                <?php if ($atts['show_download_count'] === 'true'): ?>
                                    <span class="sfm-download-count">
                                        Downloads: <?php echo $file->download_count; ?>
                                    </span>
                                <?php endif; ?>
                                <span class="sfm-file-size">
                                    Size: <?php echo size_format($file->file_size); ?>
                                </span>
                            </div>
                            <div class="sfm-file-actions">
                                <?php if ($atts['show_view_button'] === 'true'): ?>
                                    <a href="<?php echo esc_url($this->get_file_view_url($file)); ?>" 
                                       class="sfm-view-btn" 
                                       target="_blank">
                                        View
                                    </a>
                                <?php endif; ?>
                                <?php if ($atts['show_download_button'] === 'true'): ?>
                                <button class="sfm-download-btn" data-file-id="<?php echo esc_attr($file->id); ?>">
                                    Download
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('.sfm-download-btn').on('click', function(e) {
                e.preventDefault();
                var fileId = $(this).data('file-id');
                var nonce = sfm_ajax.nonce;
                var btn = $(this);
                
                // Show loading state
                btn.text('Downloading...').prop('disabled', true);
                
                // Create form and submit
                var form = $('<form method="post" action="' + sfm_ajax.ajaxurl + '" style="display:none;">');
                form.append($('<input type="hidden" name="action" value="sfm_download_file">'));
                form.append($('<input type="hidden" name="file_id" value="' + fileId + '">'));
                form.append($('<input type="hidden" name="nonce" value="' + nonce + '">'));
                $('body').append(form);
                
                // Submit form
                form.submit();
                
                // Reset button immediately
                btn.text('Download').prop('disabled', false);
                form.remove();
            });
        });
        </script>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Display custom login form shortcode
     */
    public function display_custom_login_shortcode($atts) {
        $atts = shortcode_atts(array(
            'title' => 'Secure Login',
            'show_title' => 'true',
            'redirect_after_login' => '',
            'redirect_after_logout' => '',
            'button_text' => 'Login',
            'logout_text' => 'Logout'
        ), $atts);
        
        // Start output buffering
        ob_start();
        
        // If user is already logged in, show logout button
        if (is_user_logged_in()) {
            $user = wp_get_current_user();
            $user_roles = $user->roles;
            
            // Check if user has custom roles
            $has_custom_role = false;
            global $wpdb;
            $custom_roles_table = $wpdb->prefix . 'sfm_custom_roles';
            $custom_roles = $wpdb->get_results("SELECT role_name FROM $custom_roles_table WHERE is_active = 1");
            
            foreach ($user_roles as $role) {
                foreach ($custom_roles as $custom_role) {
                    if ($role === $custom_role->role_name) {
                        $has_custom_role = true;
                        break 2;
                    }
                }
            }
            
            // Only show logout for users with custom roles
            if ($has_custom_role) {
                ?>
                <div class="sfm-login-form sfm-logged-in">
                    <?php if ($atts['show_title'] === 'true'): ?>
                        <h3><?php echo esc_html($atts['title']); ?></h3>
                    <?php endif; ?>
                    
                    <div class="sfm-user-info">
                        <p>Welcome, <strong><?php echo esc_html($user->display_name); ?></strong>!</p>
                        <p>You are logged in with a custom role.</p>
                    </div>
                    
                    <button class="sfm-logout-btn" data-redirect="<?php echo esc_url($atts['redirect_after_logout']); ?>">
                        <?php echo esc_html($atts['logout_text']); ?>
                    </button>
                </div>
                <?php
            } else {
                // User is logged in but not with custom role, don't show anything
                return '';
            }
        } else {
            // User is not logged in, show login form
            ?>
            <div class="sfm-login-form sfm-not-logged-in">
                <?php if ($atts['show_title'] === 'true'): ?>
                    <h3><?php echo esc_html($atts['title']); ?></h3>
                <?php endif; ?>
                
                <form id="sfm-custom-login-form" method="post" action="<?php echo admin_url('admin-ajax.php'); ?>">
                    <input type="hidden" name="action" value="sfm_custom_login">
                    <input type="hidden" name="redirect_after_login" value="<?php echo esc_url($atts['redirect_after_login']); ?>">
                    <input type="hidden" name="nonce" value="<?php echo wp_create_nonce('sfm_custom_login'); ?>">
                    
                    <div class="sfm-form-group">
                        <label for="sfm-username">Username or Email:</label>
                        <input type="text" id="sfm-username" name="username" required>
                    </div>
                    
                    <div class="sfm-form-group">
                        <label for="sfm-password">Password:</label>
                        <input type="password" id="sfm-password" name="password" required>
                    </div>
                    
                    <div class="sfm-form-group">
                        <button type="submit" class="sfm-login-btn">
                            <?php echo esc_html($atts['button_text']); ?>
                        </button>
                    </div>
                    
                    <div class="sfm-login-message" style="display: none;"></div>
                </form>
            </div>
            <?php
        }
        ?>
        
        <!-- CSS and JavaScript (always loaded) -->
        <style>
        .sfm-login-form {
            max-width: 400px;
            margin: 20px auto;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 8px;
            background: #fff;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .sfm-login-form h3 {
            margin-top: 0;
            margin-bottom: 20px;
            color: #333;
            text-align: center;
        }
        
        .sfm-form-group {
            margin-bottom: 15px;
        }
        
        .sfm-form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #555;
        }
        
        .sfm-form-group input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .sfm-login-btn, .sfm-logout-btn {
            width: 100%;
            padding: 12px;
            background: #0073aa;
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 16px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        
        .sfm-login-btn:hover, .sfm-logout-btn:hover {
            background: #005a87;
        }
        
        .sfm-login-btn:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
        
        .sfm-user-info {
            text-align: center;
            margin-bottom: 20px;
            padding: 15px;
            background: #f0f8ff;
            border-radius: 4px;
        }
        
        .sfm-login-message {
            margin-top: 15px;
            padding: 10px;
            border-radius: 4px;
            text-align: center;
        }
        
        .sfm-login-message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .sfm-login-message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            // Handle login form submission
            $('#sfm-custom-login-form').on('submit', function(e) {
                e.preventDefault();
                
                var form = $(this);
                var btn = form.find('.sfm-login-btn');
                var message = form.find('.sfm-login-message');
                
                // Show loading state
                btn.text('Logging in...').prop('disabled', true);
                message.hide();
                
                // Get form data
                var formData = {
                    action: 'sfm_custom_login',
                    username: form.find('#sfm-username').val(),
                    password: form.find('#sfm-password').val(),
                    redirect_after_login: form.find('input[name="redirect_after_login"]').val(),
                    nonce: form.find('input[name="nonce"]').val()
                };
                
                // Send AJAX request
                $.post(sfm_ajax.ajaxurl, formData, function(response) {
                    if (response.success) {
                        message.removeClass('error').addClass('success').text('Login successful! Redirecting...').show();
                        
                        // Redirect immediately
                        if (response.data.redirect) {
                            window.location.href = response.data.redirect;
                        } else {
                            window.location.reload();
                        }
                    } else {
                        message.removeClass('success').addClass('error').text(response.data || 'Login failed').show();
                        btn.text('Login').prop('disabled', false);
                    }
                }).fail(function(xhr, status, error) {
                    message.removeClass('success').addClass('error').text('Login failed. Please try again.').show();
                    btn.text('Login').prop('disabled', false);
                });
            });
            
            // Handle logout button
            $('.sfm-logout-btn').on('click', function(e) {
                e.preventDefault();
                
                var btn = $(this);
                var redirect = btn.data('redirect');
                
                // Show loading state
                btn.text('Logging out...').prop('disabled', true);
                
                // Send AJAX request
                $.post(sfm_ajax.ajaxurl, {
                    action: 'sfm_custom_logout',
                    nonce: '<?php echo wp_create_nonce('sfm_custom_logout'); ?>'
                }, function(response) {
                    if (response.success) {
                        // Redirect after logout
                        if (redirect) {
                            window.location.href = redirect;
                        } else {
                            window.location.reload();
                        }
                    } else {
                        btn.text('Logout').prop('disabled', false);
                        alert('Logout failed. Please try again.');
                    }
                }).fail(function(xhr, status, error) {
                    btn.text('Logout').prop('disabled', false);
                    alert('Logout failed. Please try again.');
                });
            });
        });
        </script>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Handle custom login via AJAX
     */
    public function handle_custom_login() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'sfm_custom_login')) {
            wp_send_json_error('Security check failed');
        }
        
        $username = sanitize_text_field($_POST['username']);
        $password = $_POST['password'];
        $redirect = sanitize_url($_POST['redirect_after_login']);
        
        
        // Attempt login
        $user = wp_authenticate($username, $password);
        
        if (is_wp_error($user)) {
            wp_send_json_error('Invalid username or password');
        }
        
        // Refresh user object to get latest roles
        wp_cache_delete($user->ID, 'users');
        $user = get_user_by('id', $user->ID);
        
        // Check if user has custom roles
        $user_roles = $user->roles;
        $has_custom_role = false;
        
        global $wpdb;
        $custom_roles_table = $wpdb->prefix . 'sfm_custom_roles';
        
        // Check if table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$custom_roles_table'");
        if (!$table_exists) {
            wp_send_json_error('Custom roles table not found. Please contact administrator.');
        }
        
        $custom_roles = $wpdb->get_results("SELECT role_name FROM $custom_roles_table WHERE is_active = 1");
        
        foreach ($user_roles as $role) {
            foreach ($custom_roles as $custom_role) {
                if ($role === $custom_role->role_name) {
                    $has_custom_role = true;
                    break 2;
                }
            }
        }
        
        // Only allow login for users with custom roles
        if (!$has_custom_role) {
            if (empty($user_roles)) {
                wp_send_json_error('User has no roles assigned. Please contact administrator.');
            } else {
                wp_send_json_error('Access denied. This login form is only for custom role users. Your roles: ' . implode(', ', $user_roles));
            }
        }
        
        // Login successful
        wp_set_current_user($user->ID);
        wp_set_auth_cookie($user->ID, true);
        
        wp_send_json_success(array(
            'message' => 'Login successful',
            'redirect' => $redirect
        ));
    }
    
    /**
     * Handle custom logout via AJAX
     */
    public function handle_custom_logout() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'sfm_custom_logout')) {
            wp_send_json_error('Security check failed');
        }
        
        // Check if user is logged in
        if (!is_user_logged_in()) {
            wp_send_json_error('User not logged in');
        }
        
        // Check if user has custom roles
        $user = wp_get_current_user();
        $user_roles = $user->roles;
        $has_custom_role = false;
        
        global $wpdb;
        $custom_roles_table = $wpdb->prefix . 'sfm_custom_roles';
        $custom_roles = $wpdb->get_results("SELECT role_name FROM $custom_roles_table WHERE is_active = 1");
        
        foreach ($user_roles as $role) {
            foreach ($custom_roles as $custom_role) {
                if ($role === $custom_role->role_name) {
                    $has_custom_role = true;
                    break 2;
                }
            }
        }
        
        // Only allow logout for users with custom roles
        if (!$has_custom_role) {
            wp_send_json_error('Access denied');
        }
        
        // Logout successful
        wp_logout();
        
        wp_send_json_success(array(
            'message' => 'Logout successful'
        ));
    }
    
    /**
     * Get files accessible to current user
     */
    private function get_user_accessible_files($atts = array()) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'sfm_files';
        $user = wp_get_current_user();
        
        // Build query based on user roles (WordPress standard roles only)
        $user_roles = $user->roles;
        
        // Admin can see all files (only real administrators, not custom roles)
        if (current_user_can('sfm_manage_files') || (current_user_can('manage_options') && in_array('administrator', $user_roles))) {
            $query = "SELECT * FROM $table_name ORDER BY uploaded_at DESC";
            if (!empty($atts['limit'])) {
                $query .= " LIMIT " . intval($atts['limit']);
            }
            return $wpdb->get_results($query);
        }
        
        // Get all files and filter by roles in PHP for better security
        $query = "SELECT * FROM $table_name ORDER BY uploaded_at DESC";
        if (!empty($atts['limit'])) {
            $query .= " LIMIT " . intval($atts['limit']);
        }
        
        $all_files = $wpdb->get_results($query);
        
        // Filter files by user roles
        $accessible_files = array();
        foreach ($all_files as $file) {
            if ($this->user_can_access_file($file)) {
                $accessible_files[] = $file;
            }
        }
        
        return $accessible_files;
    }
    
    /**
     * Get file icon based on MIME type
     */
    private function get_file_icon($mime_type) {
        $icons = array(
            'application/pdf' => '📄',
            'application/msword' => '📝',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => '📝',
            'text/plain' => '📄',
            'image/jpeg' => '🖼️',
            'image/png' => '🖼️',
            'image/gif' => '🖼️',
            'application/zip' => '📦',
            'application/x-rar-compressed' => '📦'
        );
        
        return isset($icons[$mime_type]) ? $icons[$mime_type] : '📁';
    }
    
    /**
     * Handle file download via AJAX
     */
    public function handle_file_download() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'sfm_nonce')) {
            wp_die('Security check failed');
        }
        
        // Check if user is logged in
        if (!is_user_logged_in()) {
            wp_die('You must be logged in to download files');
        }
        
        $file_id = intval($_POST['file_id']);
        
        
        // Get file info
        global $wpdb;
        $table_name = $wpdb->prefix . 'sfm_files';
        $file = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $file_id
        ));
        
        if (!$file) {
            wp_die('File not found');
        }
        
        
        // Check access permissions
        if (!$this->user_can_access_file($file)) {
            wp_die('Access denied');
        }
        
        // Update download count
        $wpdb->query($wpdb->prepare(
            "UPDATE $table_name SET download_count = download_count + 1 WHERE id = %d",
            $file_id
        ));
        
        // Serve the file directly
        $file_path = SFM_PROTECTED_PATH . '/' . $file->filename;
        
        if (!file_exists($file_path)) {
            wp_die('File not found on disk');
        }
        
        // Set headers for file download
        header('Content-Type: ' . $file->mime_type);
        header('Content-Disposition: attachment; filename="' . $file->original_name . '"');
        header('Content-Length: ' . filesize($file_path));
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: no-cache');
        
        // Output file content
        readfile($file_path);
        exit;
    }
    
    /**
     * Check if user can access file
     */
    private function user_can_access_file($file) {
        $user = wp_get_current_user();
        $user_roles = $user->roles;
        
        // Admin can access all files (only real administrators, not custom roles)
        if (current_user_can('sfm_manage_files') || (current_user_can('manage_options') && in_array('administrator', $user_roles))) {
            return true;
        }
        
        // Check allowed roles
        $allowed_roles = unserialize($file->allowed_roles);
        
        if (empty($allowed_roles)) {
            // If no roles specified, only admin can access
            return current_user_can('sfm_manage_files') || (current_user_can('manage_options') && in_array('administrator', $user_roles));
        }
        
        // Check if user has any of the allowed roles (exact match)
        foreach ($allowed_roles as $allowed_role) {
            if (in_array($allowed_role, $user_roles)) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Handle secure file serving via AJAX endpoint
     */
    public function handle_serve_file() {
        // Check if user is logged in
        if (!is_user_logged_in()) {
            wp_die('Access denied - please log in', 'Access Denied', array('response' => 403));
        }
        
        // Get file name from request
        $file_name = sanitize_file_name($_GET['file'] ?? '');
        if (empty($file_name)) {
            wp_die('File not specified', 'Bad Request', array('response' => 400));
        }
        
        $this->serve_secure_file($file_name);
    }
    
    /**
     * Serve secure file with role checking
     */
    private function serve_secure_file($file_name) {
        global $wpdb;
        
        // Get file from database
        $table_name = $wpdb->prefix . 'sfm_files';
        $file = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE filename = %s",
            $file_name
        ));
        
        if (!$file) {
            wp_die('File not found', 'File Not Found', array('response' => 404));
        }
        
        // Check if user can access this file
        if (!$this->user_can_access_file($file)) {
            wp_die('Access denied - insufficient permissions', 'Access Denied', array('response' => 403));
        }
        
        // Serve the file
        $file_path = SFM_PROTECTED_PATH . '/' . $file->filename;
        
        if (!file_exists($file_path)) {
            wp_die('File not found on disk', 'File Not Found', array('response' => 404));
        }
        
        // Set headers for file viewing
        header('Content-Type: ' . $file->mime_type);
        header('Content-Disposition: inline; filename="' . $file->original_name . '"');
        header('Content-Length: ' . filesize($file_path));
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: no-cache');
        
        // Output file content
        readfile($file_path);
        exit;
    }
    
    /**
     * Generate secure file URL for viewing
     */
    public function get_file_view_url($file) {
        return add_query_arg(array(
            'action' => 'sfm_serve_file',
            'file' => $file->filename
        ), admin_url('admin-ajax.php'));
    }
    
    /**
     * Generate secure download URL
     */
    private function generate_download_url($file) {
        $nonce = wp_create_nonce('sfm_download_' . $file->id);
        return add_query_arg(array(
            'sfm_download' => $file->id,
            'nonce' => $nonce
        ), home_url());
    }
    
    
    
    
}
