<?php
/**
 * File Manager Class
 * 
 * Handles file upload, download, and access control
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFM_File_Manager {
    
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
        // AJAX handlers
        add_action('wp_ajax_sfm_upload_file', array($this, 'handle_file_upload'));
        add_action('wp_ajax_sfm_delete_file', array($this, 'handle_file_delete'));
        add_action('wp_ajax_sfm_update_file_roles', array($this, 'handle_update_file_roles'));
        
        // File access control
        add_action('template_redirect', array($this, 'handle_file_access'));
        
        // Admin hooks
        add_action('admin_init', array($this, 'admin_init'));
    }
    
    /**
     * Admin initialization
     */
    public function admin_init() {
        // Add meta boxes for file management
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
    }
    
    /**
     * Handle file upload via AJAX
     */
    public function handle_file_upload() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'sfm_admin_nonce')) {
            wp_die('Security check failed');
        }
        
        // Check permissions - only administrators can upload files
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions - only administrators can upload files');
        }
        
        // Handle file upload
        if (!empty($_FILES['file'])) {
            $result = $this->upload_file($_FILES['file'], $_POST);
            
            if ($result['success']) {
                wp_send_json_success($result['message']);
            } else {
                wp_send_json_error($result['message']);
            }
        } else {
            wp_send_json_error('No file uploaded');
        }
    }
    
    /**
     * Upload file to protected folder
     */
    public function upload_file($file, $data = array()) {
        // Validate file
        $validation = $this->validate_file($file);
        if (!$validation['valid']) {
            return array('success' => false, 'message' => $validation['message']);
        }
        
        // Generate unique filename
        $file_info = pathinfo($file['name']);
        $filename = sanitize_file_name($file_info['filename']);
        $extension = strtolower($file_info['extension']);
        $unique_filename = $filename . '_' . time() . '.' . $extension;
        
        // Set upload path
        $upload_path = SFM_PROTECTED_PATH . '/' . $unique_filename;
        
        // Move uploaded file
        if (move_uploaded_file($file['tmp_name'], $upload_path)) {
            // Save file info to database
            $file_id = $this->save_file_to_database($file, $unique_filename, $upload_path, $data);
            
            if ($file_id) {
                return array(
                    'success' => true, 
                    'message' => 'File uploaded successfully',
                    'file_id' => $file_id
                );
            } else {
                // Remove file if database save failed
                unlink($upload_path);
                return array('success' => false, 'message' => 'Failed to save file information');
            }
        } else {
            return array('success' => false, 'message' => 'Failed to upload file');
        }
    }
    
    /**
     * Validate uploaded file
     */
    private function validate_file($file) {
        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return array('valid' => false, 'message' => 'File upload error');
        }
        
        // Check file size
        $max_size = get_option('sfm_max_file_size', 10485760); // 10MB default
        if ($file['size'] > $max_size) {
            return array('valid' => false, 'message' => 'File too large');
        }
        
        // Check file type
        $allowed_types = get_option('sfm_allowed_file_types', array());
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (!empty($allowed_types) && !in_array($file_extension, $allowed_types)) {
            return array('valid' => false, 'message' => 'File type not allowed');
        }
        
        // Check MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        // Basic security check
        $dangerous_mimes = array(
            'application/x-php',
            'text/x-php',
            'application/x-executable',
            'application/x-msdownload'
        );
        
        if (in_array($mime_type, $dangerous_mimes)) {
            return array('valid' => false, 'message' => 'File type not allowed for security reasons');
        }
        
        return array('valid' => true, 'message' => 'File is valid');
    }
    
    /**
     * Save file information to database
     */
    private function save_file_to_database($file, $filename, $file_path, $data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'sfm_files';
        
        $file_data = array(
            'filename' => $filename,
            'original_name' => sanitize_file_name($file['name']),
            'file_path' => $file_path,
            'file_size' => $file['size'],
            'mime_type' => $file['type'],
            'uploaded_by' => get_current_user_id(),
            'allowed_roles' => isset($data['allowed_roles']) ? serialize($data['allowed_roles']) : '',
            'description' => isset($data['description']) ? sanitize_textarea_field($data['description']) : ''
        );
        
        $result = $wpdb->insert($table_name, $file_data);
        
        if ($result) {
            return $wpdb->insert_id;
        }
        
        return false;
    }
    
    /**
     * Handle file deletion via AJAX
     */
    public function handle_file_delete() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'sfm_admin_nonce')) {
            wp_die('Security check failed');
        }
        
        // Check permissions - only administrators can delete files
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions - only administrators can delete files');
        }
        
        $file_id = intval($_POST['file_id']);
        $result = $this->delete_file($file_id);
        
        if ($result['success']) {
            wp_send_json_success($result['message']);
        } else {
            wp_send_json_error($result['message']);
        }
    }
    
    /**
     * Delete file
     */
    public function delete_file($file_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'sfm_files';
        
        // Get file info
        $file = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $file_id
        ));
        
        if (!$file) {
            return array('success' => false, 'message' => 'File not found');
        }
        
        // Delete physical file
        if (file_exists($file->file_path)) {
            unlink($file->file_path);
        }
        
        // Delete database record
        $result = $wpdb->delete($table_name, array('id' => $file_id));
        
        if ($result) {
            return array('success' => true, 'message' => 'File deleted successfully');
        } else {
            return array('success' => false, 'message' => 'Failed to delete file record');
        }
    }
    
    /**
     * Handle file roles update via AJAX
     */
    public function handle_update_file_roles() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'sfm_admin_nonce')) {
            wp_die('Security check failed');
        }
        
        // Check permissions - only administrators can update file roles
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions - only administrators can update file roles');
        }
        
        $file_id = intval($_POST['file_id']);
        $allowed_roles = isset($_POST['allowed_roles']) ? array_map('sanitize_text_field', $_POST['allowed_roles']) : array();
        
        // Update file roles in database
        global $wpdb;
        $table_name = $wpdb->prefix . 'sfm_files';
        
        $result = $wpdb->update(
            $table_name,
            array('allowed_roles' => serialize($allowed_roles)),
            array('id' => $file_id),
            array('%s'),
            array('%d')
        );
        
        if ($result !== false) {
            wp_send_json_success('File roles updated successfully');
        } else {
            wp_send_json_error('Failed to update file roles');
        }
    }
    
    /**
     * Handle file access control
     */
    public function handle_file_access() {
        // Check if this is a request for a secure file
        if (strpos($_SERVER['REQUEST_URI'], '/wp-content/' . SFM_PROTECTED_FOLDER . '/') === false) {
            return;
        }
        
        // Extract filename from URL
        $file_path = $_SERVER['REQUEST_URI'];
        $filename = basename(parse_url($file_path, PHP_URL_PATH));
        
        // Get file info from database
        $file = $this->get_file_by_filename($filename);
        
        if (!$file) {
            wp_die('File not found', 'File Not Found', array('response' => 404));
        }
        
        // Check if user has access
        if (!$this->user_can_access_file($file)) {
            wp_die('Access denied', 'Access Denied', array('response' => 403));
        }
        
        // Serve the file
        $this->serve_file($file);
    }
    
    /**
     * Get file by filename
     */
    private function get_file_by_filename($filename) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'sfm_files';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE filename = %s",
            $filename
        ));
    }
    
    /**
     * Check if user can access file
     */
    private function user_can_access_file($file) {
        // Check if user is logged in
        if (!is_user_logged_in()) {
            return false;
        }
        
        // Admin can access all files (only real administrators, not custom roles)
        $user = wp_get_current_user();
        $user_roles = $user->roles;
        if (current_user_can('sfm_manage_files') || (current_user_can('manage_options') && in_array('administrator', $user_roles))) {
            return true;
        }
        
        // Check allowed roles
        $allowed_roles = unserialize($file->allowed_roles);
        
        if (empty($allowed_roles)) {
            // If no roles specified, only admin can access
            return current_user_can('sfm_manage_files') || (current_user_can('manage_options') && in_array('administrator', $user_roles));
        }
        
        // Check if user has one of the allowed roles (exact match)
        foreach ($allowed_roles as $allowed_role) {
            if (in_array($allowed_role, $user_roles)) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Serve file to user
     */
    private function serve_file($file) {
        // Update download count
        $this->increment_download_count($file->id);
        
        // Set headers
        header('Content-Type: ' . $file->mime_type);
        header('Content-Disposition: attachment; filename="' . $file->original_name . '"');
        header('Content-Length: ' . $file->file_size);
        header('Cache-Control: no-cache, must-revalidate');
        header('Expires: 0');
        
        // Output file
        readfile($file->file_path);
        exit;
    }
    
    /**
     * Increment download count
     */
    private function increment_download_count($file_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'sfm_files';
        
        $wpdb->query($wpdb->prepare(
            "UPDATE $table_name SET download_count = download_count + 1 WHERE id = %d",
            $file_id
        ));
    }
    
    /**
     * Get all files
     */
    public function get_files($args = array()) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'sfm_files';
        
        $defaults = array(
            'limit' => 20,
            'offset' => 0,
            'orderby' => 'uploaded_at',
            'order' => 'DESC'
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $query = "SELECT * FROM $table_name ORDER BY {$args['orderby']} {$args['order']} LIMIT %d OFFSET %d";
        
        return $wpdb->get_results($wpdb->prepare($query, $args['limit'], $args['offset']));
    }
    
    /**
     * Add meta boxes
     */
    public function add_meta_boxes() {
        add_meta_box(
            'sfm_file_management',
            'Secure Files Management',
            array($this, 'file_management_meta_box'),
            'dashboard',
            'normal',
            'high'
        );
    }
    
    /**
     * File management meta box
     */
    public function file_management_meta_box() {
        $files = $this->get_files(array('limit' => 10));
        
        echo '<div class="sfm-file-management">';
        echo '<h3>Recent Files</h3>';
        
        if (empty($files)) {
            echo '<p>No files uploaded yet.</p>';
        } else {
            echo '<table class="wp-list-table widefat fixed striped">';
            echo '<thead><tr><th>File Name</th><th>Size</th><th>Uploaded</th><th>Downloads</th><th>Actions</th></tr></thead>';
            echo '<tbody>';
            
            foreach ($files as $file) {
                echo '<tr>';
                echo '<td>' . esc_html($file->original_name) . '</td>';
                echo '<td>' . size_format($file->file_size) . '</td>';
                echo '<td>' . date('Y-m-d H:i', strtotime($file->uploaded_at)) . '</td>';
                echo '<td>' . $file->download_count . '</td>';
                echo '<td>';
                echo '<a href="' . $this->get_file_view_url($file) . '" class="button button-small" target="_blank">View</a> ';
                echo '<a href="' . admin_url('admin.php?page=secure-files-manager&action=edit&file_id=' . $file->id) . '" class="button button-small">Edit</a> ';
                echo '<button class="button button-small sfm-delete-file" data-file-id="' . $file->id . '">Delete</button>';
                echo '</td>';
                echo '</tr>';
            }
            
            echo '</tbody></table>';
        }
        
        echo '</div>';
    }
    
    /**
     * Get file view URL
     */
    private function get_file_view_url($file) {
        $frontend = SFM_Core::instance()->get_frontend();
        return $frontend->get_file_view_url($file);
    }
}
