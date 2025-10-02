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
        
        // AJAX handlers for frontend
        add_action('wp_ajax_sfm_download_file', array($this, 'handle_file_download'));
        add_action('wp_ajax_nopriv_sfm_download_file', array($this, 'handle_file_download'));
        
        // Add endpoint for serving secure files
        add_action('wp_ajax_sfm_serve_file', array($this, 'handle_serve_file'));
        add_action('wp_ajax_nopriv_sfm_serve_file', array($this, 'handle_serve_file'));
        
        
        // Enqueue frontend styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_styles'));
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
                var nonce = '<?php echo wp_create_nonce('sfm_nonce'); ?>';
                
                // Create form and submit
                var form = $('<form method="post" action="<?php echo admin_url('admin-ajax.php'); ?>">');
                form.append($('<input type="hidden" name="action" value="sfm_download_file">'));
                form.append($('<input type="hidden" name="file_id" value="' + fileId + '">'));
                form.append($('<input type="hidden" name="nonce" value="' + nonce + '">'));
                $('body').append(form);
                form.submit();
                form.remove();
            });
        });
        </script>
        <?php
        
        return ob_get_clean();
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
        
        // Generate download URL
        $download_url = $this->generate_download_url($file);
        
        wp_send_json_success(array('download_url' => $download_url));
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
