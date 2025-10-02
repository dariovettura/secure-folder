<?php
/**
 * Plugin Activator
 * 
 * Handles plugin activation tasks
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFM_Activator {
    
    /**
     * Activate the plugin
     */
    public static function activate() {
        // Create protected folder
        self::create_protected_folder();
        
        // Create .htaccess for protected folder
        self::create_htaccess();
        
        // Create database tables
        self::create_tables();
        
        // Add custom capabilities
        self::add_capabilities();
        
        // Set default options
        self::set_default_options();
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Create the protected folder
     */
    private static function create_protected_folder() {
        $protected_path = SFM_PROTECTED_PATH;
        
        if (!file_exists($protected_path)) {
            wp_mkdir_p($protected_path);
            
            // Create index.php to prevent directory listing
            $index_content = "<?php\n// Silence is golden\n";
            file_put_contents($protected_path . '/index.php', $index_content);
        }
    }
    
    /**
     * Create .htaccess for protected folder
     */
    private static function create_htaccess() {
        $htaccess_content = "# Secure Files Manager - Protected Folder\n";
        $htaccess_content .= "RewriteEngine On\n";
        $htaccess_content .= "RewriteCond %{HTTP_COOKIE} !^.*wordpress_logged_in.*$ [NC]\n";
        $htaccess_content .= "RewriteRule . - [R=403,L]\n";
        $htaccess_content .= "\n# Deny direct access to PHP files\n";
        $htaccess_content .= "<Files \"*.php\">\n";
        $htaccess_content .= "Order Deny,Allow\n";
        $htaccess_content .= "Deny from all\n";
        $htaccess_content .= "</Files>\n";
        
        file_put_contents(SFM_PROTECTED_PATH . '/.htaccess', $htaccess_content);
    }
    
    /**
     * Create database tables
     */
    private static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Files table
        $table_files = $wpdb->prefix . 'sfm_files';
        $sql_files = "CREATE TABLE $table_files (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            filename varchar(255) NOT NULL,
            original_name varchar(255) NOT NULL,
            file_path varchar(500) NOT NULL,
            file_size bigint(20) NOT NULL,
            mime_type varchar(100) NOT NULL,
            uploaded_by bigint(20) NOT NULL,
            uploaded_at datetime DEFAULT CURRENT_TIMESTAMP,
            allowed_roles text,
            description text,
            download_count int(11) DEFAULT 0,
            PRIMARY KEY (id),
            KEY uploaded_by (uploaded_by),
            KEY uploaded_at (uploaded_at)
        ) $charset_collate;";
        
        // Custom roles table
        $table_roles = $wpdb->prefix . 'sfm_custom_roles';
        $sql_roles = "CREATE TABLE $table_roles (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            role_name varchar(100) NOT NULL,
            role_display_name varchar(100) NOT NULL,
            role_description text,
            capabilities text,
            created_by bigint(20) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            is_active tinyint(1) DEFAULT 1,
            PRIMARY KEY (id),
            UNIQUE KEY role_name (role_name)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_files);
        dbDelta($sql_roles);
    }
    
    /**
     * Add custom capabilities
     */
    private static function add_capabilities() {
        $capabilities = array(
            'sfm_manage_files',
            'sfm_manage_roles',
            'sfm_view_secure_files',
            'sfm_upload_files',
            'sfm_download_files'
        );
        
        $admin_role = get_role('administrator');
        if ($admin_role) {
            foreach ($capabilities as $cap) {
                $admin_role->add_cap($cap);
            }
        }
    }
    
    /**
     * Set default options
     */
    private static function set_default_options() {
        add_option('sfm_version', SFM_PLUGIN_VERSION);
        add_option('sfm_protected_folder', SFM_PROTECTED_FOLDER);
        add_option('sfm_max_file_size', 10485760); // 10MB
        add_option('sfm_allowed_file_types', array('pdf', 'doc', 'docx', 'txt', 'jpg', 'jpeg', 'png', 'gif'));
        add_option('sfm_require_login', true);
    }
}
