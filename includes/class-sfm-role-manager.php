<?php
/**
 * Role Manager Class
 * 
 * Handles custom role creation and management
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFM_Role_Manager {
    
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
        add_action('wp_ajax_sfm_create_custom_role', array($this, 'handle_create_custom_role'));
        add_action('wp_ajax_sfm_update_custom_role', array($this, 'handle_update_custom_role'));
        add_action('wp_ajax_sfm_delete_custom_role', array($this, 'handle_delete_custom_role'));
        
        // User registration hooks (removed - custom roles are managed through WordPress standard role system)
    }
    
    /**
     * Handle custom role creation via AJAX
     */
    public function handle_create_custom_role() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'sfm_admin_nonce')) {
            wp_die('Security check failed');
        }
        
        // Check permissions - only administrators can manage roles
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions - only administrators can manage roles');
        }
        
        $role_name = sanitize_text_field($_POST['role_name']);
        $role_display_name = sanitize_text_field($_POST['role_display_name']);
        $role_description = sanitize_textarea_field($_POST['role_description']);
        $capabilities = isset($_POST['capabilities']) ? array_map('sanitize_text_field', $_POST['capabilities']) : array();
        
        $result = $this->create_custom_role($role_name, $role_display_name, $role_description, $capabilities);
        
        if ($result['success']) {
            wp_send_json_success($result['message']);
        } else {
            wp_send_json_error($result['message']);
        }
    }
    
    /**
     * Create custom role
     */
    public function create_custom_role($role_name, $display_name, $description, $capabilities) {
        global $wpdb;
        
        // Validate role name
        if (empty($role_name) || empty($display_name)) {
            return array('success' => false, 'message' => 'Role name and display name are required');
        }
        
        // Check if role already exists
        if (get_role($role_name)) {
            return array('success' => false, 'message' => 'Role already exists');
        }
        
        // Check if custom role already exists in database
        $table_name = $wpdb->prefix . 'sfm_custom_roles';
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table_name WHERE role_name = %s",
            $role_name
        ));
        
        if ($existing) {
            return array('success' => false, 'message' => 'Custom role already exists');
        }
        
        // Create WordPress role with only view and download capabilities (no wp-admin access)
        $wp_role = add_role($role_name, $display_name, array(
            'read' => true,
            'sfm_view_secure_files' => true,
            'sfm_download_files' => true
        ));
        
        if (!$wp_role) {
            return array('success' => false, 'message' => 'Failed to create WordPress role');
        }
        
        // Custom roles only get view and download capabilities - no additional capabilities needed
        
        // Save to database with limited capabilities
        $limited_capabilities = array('sfm_view_secure_files', 'sfm_download_files');
        $result = $wpdb->insert($table_name, array(
            'role_name' => $role_name,
            'role_display_name' => $display_name,
            'role_description' => $description,
            'capabilities' => serialize($limited_capabilities),
            'created_by' => get_current_user_id()
        ));
        
        if ($result) {
            return array('success' => true, 'message' => 'Custom role created successfully');
        } else {
            // Remove WordPress role if database save failed
            remove_role($role_name);
            return array('success' => false, 'message' => 'Failed to save role to database');
        }
    }
    
    /**
     * Handle custom role update via AJAX
     */
    public function handle_update_custom_role() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'sfm_admin_nonce')) {
            wp_die('Security check failed');
        }
        
        // Check permissions - only administrators can manage roles
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions - only administrators can manage roles');
        }
        
        $role_id = intval($_POST['role_id']);
        $role_display_name = sanitize_text_field($_POST['role_display_name']);
        $role_description = sanitize_textarea_field($_POST['role_description']);
        $capabilities = isset($_POST['capabilities']) ? array_map('sanitize_text_field', $_POST['capabilities']) : array();
        
        $result = $this->update_custom_role($role_id, $role_display_name, $role_description, $capabilities);
        
        if ($result['success']) {
            wp_send_json_success($result['message']);
        } else {
            wp_send_json_error($result['message']);
        }
    }
    
    /**
     * Update custom role
     */
    public function update_custom_role($role_id, $display_name, $description, $capabilities) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'sfm_custom_roles';
        
        // Get current role info
        $role = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $role_id
        ));
        
        if (!$role) {
            return array('success' => false, 'message' => 'Role not found');
        }
        
        // Update WordPress role - custom roles always have limited capabilities
        $wp_role = get_role($role->role_name);
        if ($wp_role) {
            // Remove old capabilities
            $old_capabilities = unserialize($role->capabilities);
            foreach ($old_capabilities as $cap) {
                $wp_role->remove_cap($cap);
            }
            
            // Add only view and download capabilities
            $limited_capabilities = array('sfm_view_secure_files', 'sfm_download_files');
            foreach ($limited_capabilities as $cap) {
                $wp_role->add_cap($cap);
            }
        }
        
        // Update database with limited capabilities
        $limited_capabilities = array('sfm_view_secure_files', 'sfm_download_files');
        $result = $wpdb->update(
            $table_name,
            array(
                'role_display_name' => $display_name,
                'role_description' => $description,
                'capabilities' => serialize($limited_capabilities)
            ),
            array('id' => $role_id)
        );
        
        if ($result !== false) {
            return array('success' => true, 'message' => 'Custom role updated successfully');
        } else {
            return array('success' => false, 'message' => 'Failed to update role');
        }
    }
    
    /**
     * Handle custom role deletion via AJAX
     */
    public function handle_delete_custom_role() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'sfm_admin_nonce')) {
            wp_die('Security check failed');
        }
        
        // Check permissions - only administrators can manage roles
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions - only administrators can manage roles');
        }
        
        $role_id = intval($_POST['role_id']);
        $result = $this->delete_custom_role($role_id);
        
        if ($result['success']) {
            wp_send_json_success($result['message']);
        } else {
            wp_send_json_error($result['message']);
        }
    }
    
    /**
     * Delete custom role
     */
    public function delete_custom_role($role_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'sfm_custom_roles';
        
        // Get role info
        $role = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $role_id
        ));
        
        if (!$role) {
            return array('success' => false, 'message' => 'Role not found');
        }
        
        // Check if role is in use
        $users_with_role = get_users(array('role' => $role->role_name));
        if (!empty($users_with_role)) {
            return array('success' => false, 'message' => 'Cannot delete role that is assigned to users');
        }
        
        // Remove WordPress role
        remove_role($role->role_name);
        
        // Delete from database
        $result = $wpdb->delete($table_name, array('id' => $role_id));
        
        if ($result) {
            return array('success' => true, 'message' => 'Custom role deleted successfully');
        } else {
            return array('success' => false, 'message' => 'Failed to delete role');
        }
    }
    
    /**
     * Get all custom roles
     */
    public function get_custom_roles() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'sfm_custom_roles';
        
        return $wpdb->get_results(
            "SELECT * FROM $table_name WHERE is_active = 1 ORDER BY created_at DESC"
        );
    }
    
    // Removed custom profile fields - custom roles are managed through WordPress standard role system
    
    // Removed custom role meta management - using WordPress standard role system
    
    /**
     * Get available capabilities for custom roles
     */
    public function get_available_capabilities() {
        return array(
            'sfm_view_secure_files' => 'View Secure Files',
            'sfm_download_files' => 'Download Files'
        );
    }
}
