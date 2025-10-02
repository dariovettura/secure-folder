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
        
        // Add custom page template
        add_filter('page_template', array($this, 'custom_page_template'));
        
        // Add custom query vars
        add_filter('query_vars', array($this, 'add_query_vars'));
        
        // Handle custom page requests
        add_action('template_redirect', array($this, 'handle_custom_page'));
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
            'show_upload_date' => 'true'
        ), $atts);
        
        $files = $this->get_user_accessible_files($atts);
        
        if (empty($files)) {
            return '<p>No files available for your access level.</p>';
        }
        
        ob_start();
        ?>
        <div class="sfm-frontend-files">
            <h3>Secure Files</h3>
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
                                <button class="sfm-download-btn" data-file-id="<?php echo esc_attr($file->id); ?>">
                                    Download
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <style>
        .sfm-frontend-files {
            max-width: 100%;
        }
        
        .sfm-files-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .sfm-file-item {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            background: #fff;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: box-shadow 0.3s ease;
        }
        
        .sfm-file-item:hover {
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }
        
        .sfm-file-icon {
            font-size: 48px;
            text-align: center;
            margin-bottom: 15px;
        }
        
        .sfm-file-name {
            margin: 0 0 10px 0;
            font-size: 16px;
            font-weight: bold;
        }
        
        .sfm-file-description {
            margin: 0 0 15px 0;
            color: #666;
            font-size: 14px;
        }
        
        .sfm-file-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 15px;
            font-size: 12px;
            color: #888;
        }
        
        .sfm-file-meta span {
            background: #f5f5f5;
            padding: 4px 8px;
            border-radius: 4px;
        }
        
        .sfm-download-btn {
            background: #0073aa;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }
        
        .sfm-download-btn:hover {
            background: #005a87;
        }
        </style>
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
        
        // Build query based on user roles
        $user_roles = $user->roles;
        $user_custom_roles = get_user_meta($user->ID, 'sfm_custom_roles', true);
        if (is_array($user_custom_roles)) {
            $user_roles = array_merge($user_roles, $user_custom_roles);
        }
        
        // Admin can see all files (only real administrators, not custom roles)
        if (current_user_can('sfm_manage_files') || (current_user_can('manage_options') && in_array('administrator', $user_roles))) {
            $query = "SELECT * FROM $table_name ORDER BY uploaded_at DESC";
            if (!empty($atts['limit'])) {
                $query .= " LIMIT " . intval($atts['limit']);
            }
            return $wpdb->get_results($query);
        }
        
        // Build role condition
        $role_conditions = array();
        foreach ($user_roles as $role) {
            $role_conditions[] = $wpdb->prepare("allowed_roles LIKE %s", '%' . $role . '%');
        }
        
        if (empty($role_conditions)) {
            return array();
        }
        
        $role_condition = '(' . implode(' OR ', $role_conditions) . ')';
        
        $query = "SELECT * FROM $table_name WHERE $role_condition ORDER BY uploaded_at DESC";
        if (!empty($atts['limit'])) {
            $query .= " LIMIT " . intval($atts['limit']);
        }
        
        return $wpdb->get_results($query);
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
        
        // Check if user has one of the allowed roles
        $user_roles = $user->roles;
        $user_custom_roles = get_user_meta($user->ID, 'sfm_custom_roles', true);
        if (is_array($user_custom_roles)) {
            $user_roles = array_merge($user_roles, $user_custom_roles);
        }
        
        // Check if user has any of the allowed roles
        foreach ($allowed_roles as $role) {
            if (in_array($role, $user_roles)) {
                return true;
            }
        }
        
        return false;
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
    
    /**
     * Add custom query vars
     */
    public function add_query_vars($vars) {
        $vars[] = 'sfm_download';
        return $vars;
    }
    
    /**
     * Handle custom page requests
     */
    public function handle_custom_page() {
        if (get_query_var('sfm_download')) {
            $this->handle_download_request();
        }
    }
    
    /**
     * Handle download request
     */
    private function handle_download_request() {
        $file_id = intval(get_query_var('sfm_download'));
        $nonce = sanitize_text_field($_GET['nonce']);
        
        // Verify nonce
        if (!wp_verify_nonce($nonce, 'sfm_download_' . $file_id)) {
            wp_die('Invalid download link');
        }
        
        // Check if user is logged in
        if (!is_user_logged_in()) {
            wp_die('You must be logged in to download files');
        }
        
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
        
        // Serve file
        header('Content-Type: ' . $file->mime_type);
        header('Content-Disposition: attachment; filename="' . $file->original_name . '"');
        header('Content-Length: ' . $file->file_size);
        header('Cache-Control: no-cache, must-revalidate');
        header('Expires: 0');
        
        readfile($file->file_path);
        exit;
    }
    
    /**
     * Custom page template
     */
    public function custom_page_template($template) {
        if (is_page('secure-files')) {
            $custom_template = SFM_PLUGIN_PATH . 'templates/page-secure-files.php';
            if (file_exists($custom_template)) {
                return $custom_template;
            }
        }
        return $template;
    }
    
    /**
     * Create secure files page
     */
    public function create_secure_files_page() {
        // Check if page already exists
        $page = get_page_by_path('secure-files');
        if ($page) {
            return $page->ID;
        }
        
        // Create page
        $page_id = wp_insert_post(array(
            'post_title' => 'Secure Files',
            'post_content' => '[secure_files]',
            'post_status' => 'publish',
            'post_type' => 'page',
            'post_name' => 'secure-files'
        ));
        
        return $page_id;
    }
}
