<?php
/**
 * Plugin Deactivator
 * 
 * Handles plugin deactivation tasks
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFM_Deactivator {
    
    /**
     * Deactivate the plugin
     */
    public static function deactivate() {
        // Remove custom capabilities from all roles
        self::remove_capabilities();
        
        // Clean up scheduled events
        self::cleanup_scheduled_events();
        
        // Note: We don't delete the protected folder or files
        // as the admin might want to keep them
    }
    
    /**
     * Remove custom capabilities from all roles
     */
    private static function remove_capabilities() {
        $capabilities = array(
            'sfm_manage_files',
            'sfm_manage_roles',
            'sfm_view_secure_files',
            'sfm_upload_files',
            'sfm_download_files'
        );
        
        $roles = wp_roles()->get_names();
        
        foreach ($roles as $role_name => $role_display_name) {
            $role = get_role($role_name);
            if ($role) {
                foreach ($capabilities as $cap) {
                    $role->remove_cap($cap);
                }
            }
        }
    }
    
    /**
     * Clean up scheduled events
     */
    private static function cleanup_scheduled_events() {
        // Remove any scheduled events if they exist
        wp_clear_scheduled_hook('sfm_cleanup_old_files');
        wp_clear_scheduled_hook('sfm_send_notifications');
    }
}
