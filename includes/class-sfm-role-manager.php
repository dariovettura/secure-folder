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
        
        // User registration hooks
        add_action('user_register', array($this, 'handle_user_registration'));
        add_action('show_user_profile', array($this, 'add_custom_role_fields'));
        add_action('edit_user_profile', array($this, 'add_custom_role_fields'));
        add_action('personal_options_update', array($this, 'save_custom_role_fields'));
        add_action('edit_user_profile_update', array($this, 'save_custom_role_fields'));
        
        // Add custom roles to user registration form
        add_action('register_form', array($this, 'add_registration_fields'));
        add_action('user_register', array($this, 'handle_registration_custom_role'));
    }
    
    /**
     * Handle custom role creation via AJAX
     */
    public function handle_create_custom_role() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'sfm_admin_nonce')) {
            wp_die('Security check failed');
        }
        
        // Check permissions
        if (!current_user_can('sfm_manage_roles')) {
            wp_die('Insufficient permissions');
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
        
        // Create WordPress role
        $wp_role = add_role($role_name, $display_name, array(
            'read' => true,
            'sfm_view_secure_files' => true
        ));
        
        if (!$wp_role) {
            return array('success' => false, 'message' => 'Failed to create WordPress role');
        }
        
        // Add custom capabilities
        foreach ($capabilities as $cap) {
            $wp_role->add_cap($cap);
        }
        
        // Save to database
        $result = $wpdb->insert($table_name, array(
            'role_name' => $role_name,
            'role_display_name' => $display_name,
            'role_description' => $description,
            'capabilities' => serialize($capabilities),
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
        
        // Check permissions
        if (!current_user_can('sfm_manage_roles')) {
            wp_die('Insufficient permissions');
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
        
        // Update WordPress role
        $wp_role = get_role($role->role_name);
        if ($wp_role) {
            // Remove old capabilities
            $old_capabilities = unserialize($role->capabilities);
            foreach ($old_capabilities as $cap) {
                $wp_role->remove_cap($cap);
            }
            
            // Add new capabilities
            foreach ($capabilities as $cap) {
                $wp_role->add_cap($cap);
            }
        }
        
        // Update database
        $result = $wpdb->update(
            $table_name,
            array(
                'role_display_name' => $display_name,
                'role_description' => $description,
                'capabilities' => serialize($capabilities)
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
        
        // Check permissions
        if (!current_user_can('sfm_manage_roles')) {
            wp_die('Insufficient permissions');
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
    
    /**
     * Add custom role fields to user profile
     */
    public function add_custom_role_fields($user) {
        $custom_roles = $this->get_custom_roles();
        
        if (empty($custom_roles)) {
            return;
        }
        
        echo '<h3>Secure Files Access</h3>';
        echo '<table class="form-table">';
        echo '<tr>';
        echo '<th><label for="sfm_custom_roles">Additional File Access Roles</label></th>';
        echo '<td>';
        
        $user_custom_roles = get_user_meta($user->ID, 'sfm_custom_roles', true);
        if (!is_array($user_custom_roles)) {
            $user_custom_roles = array();
        }
        
        foreach ($custom_roles as $role) {
            $checked = in_array($role->role_name, $user_custom_roles) ? 'checked' : '';
            echo '<label>';
            echo '<input type="checkbox" name="sfm_custom_roles[]" value="' . esc_attr($role->role_name) . '" ' . $checked . '> ';
            echo esc_html($role->role_display_name);
            if (!empty($role->role_description)) {
                echo ' - ' . esc_html($role->role_description);
            }
            echo '</label><br>';
        }
        
        echo '<p class="description">Select additional roles for file access.</p>';
        echo '</td>';
        echo '</tr>';
        echo '</table>';
    }
    
    /**
     * Save custom role fields
     */
    public function save_custom_role_fields($user_id) {
        if (!current_user_can('edit_user', $user_id)) {
            return false;
        }
        
        $custom_roles = isset($_POST['sfm_custom_roles']) ? array_map('sanitize_text_field', $_POST['sfm_custom_roles']) : array();
        update_user_meta($user_id, 'sfm_custom_roles', $custom_roles);
    }
    
    /**
     * Add custom roles to registration form
     */
    public function add_registration_fields() {
        $custom_roles = $this->get_custom_roles();
        
        if (empty($custom_roles)) {
            return;
        }
        
        echo '<p>';
        echo '<label for="sfm_custom_roles">Additional File Access Roles (Optional)<br>';
        
        foreach ($custom_roles as $role) {
            echo '<label>';
            echo '<input type="checkbox" name="sfm_custom_roles[]" value="' . esc_attr($role->role_name) . '"> ';
            echo esc_html($role->role_display_name);
            if (!empty($role->role_description)) {
                echo ' - ' . esc_html($role->role_description);
            }
            echo '</label><br>';
        }
        
        echo '</label>';
        echo '</p>';
    }
    
    /**
     * Handle custom role assignment during registration
     */
    public function handle_registration_custom_role($user_id) {
        if (isset($_POST['sfm_custom_roles']) && is_array($_POST['sfm_custom_roles'])) {
            $custom_roles = array_map('sanitize_text_field', $_POST['sfm_custom_roles']);
            update_user_meta($user_id, 'sfm_custom_roles', $custom_roles);
        }
    }
    
    /**
     * Handle user registration
     */
    public function handle_user_registration($user_id) {
        // This is called after user registration
        // Custom roles are handled in handle_registration_custom_role
    }
    
    /**
     * Check if user has custom role
     */
    public function user_has_custom_role($user_id, $role_name) {
        $user_custom_roles = get_user_meta($user_id, 'sfm_custom_roles', true);
        return is_array($user_custom_roles) && in_array($role_name, $user_custom_roles);
    }
    
    /**
     * Get user's custom roles
     */
    public function get_user_custom_roles($user_id) {
        return get_user_meta($user_id, 'sfm_custom_roles', true);
    }
    
    /**
     * Get available capabilities for custom roles
     */
    public function get_available_capabilities() {
        return array(
            'sfm_view_secure_files' => 'View Secure Files',
            'sfm_download_files' => 'Download Files',
            'sfm_upload_files' => 'Upload Files',
            'sfm_manage_files' => 'Manage Files',
            'sfm_manage_roles' => 'Manage Roles'
        );
    }
}
