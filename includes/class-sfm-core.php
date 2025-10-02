<?php
/**
 * Core Plugin Class
 * 
 * Main plugin class that coordinates all functionality
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFM_Core {
    
    /**
     * Plugin version
     */
    public $version = SFM_PLUGIN_VERSION;
    
    /**
     * Single instance of the class
     */
    protected static $_instance = null;
    
    /**
     * Plugin components
     */
    public $file_manager;
    public $role_manager;
    public $frontend;
    public $admin;
    
    /**
     * Main SFM_Core Instance
     */
    public static function instance() {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }
    
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
        add_action('init', array($this, 'init'), 0);
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
    }
    
    /**
     * Initialize the plugin
     */
    public function init() {
        // Load text domain
        $this->load_plugin_textdomain();
        
        // Ensure capabilities are added
        $this->ensure_capabilities();
        
        // Initialize components
        $this->init_components();
    }
    
    /**
     * Load plugin text domain
     */
    public function load_plugin_textdomain() {
        load_plugin_textdomain(
            'secure-files-manager',
            false,
            dirname(plugin_basename(__FILE__)) . '/languages'
        );
    }
    
    /**
     * Ensure capabilities are added to administrator role
     */
    private function ensure_capabilities() {
        $admin_role = get_role('administrator');
        if ($admin_role) {
            $capabilities = array(
                'sfm_manage_files',
                'sfm_manage_roles',
                'sfm_view_secure_files',
                'sfm_upload_files',
                'sfm_download_files'
            );
            
            foreach ($capabilities as $cap) {
                if (!$admin_role->has_cap($cap)) {
                    $admin_role->add_cap($cap);
                }
            }
        }
    }
    
    /**
     * Initialize plugin components
     */
    private function init_components() {
        // Initialize file manager
        if (!$this->file_manager) {
            $this->file_manager = new SFM_File_Manager();
        }
        
        // Initialize role manager
        if (!$this->role_manager) {
            $this->role_manager = new SFM_Role_Manager();
        }
        
        // Initialize frontend
        if (!$this->frontend) {
            $this->frontend = new SFM_Frontend();
        }
        
        // Initialize admin (only in admin area)
        if (is_admin() && !$this->admin) {
            $this->admin = new SFM_Admin();
        }
    }
    
    /**
     * Enqueue frontend scripts and styles
     */
    public function enqueue_scripts() {
        wp_enqueue_style(
            'sfm-frontend-style',
            SFM_PLUGIN_URL . 'assets/css/frontend.css',
            array(),
            $this->version
        );
        
        wp_enqueue_script(
            'sfm-frontend-script',
            SFM_PLUGIN_URL . 'assets/js/frontend.js',
            array('jquery'),
            $this->version,
            true
        );
        
        // Localize script
        wp_localize_script('sfm-frontend-script', 'sfm_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sfm_nonce'),
            'strings' => array(
                'error' => __('An error occurred. Please try again.', 'secure-files-manager'),
                'success' => __('Operation completed successfully.', 'secure-files-manager'),
                'confirm_delete' => __('Are you sure you want to delete this file?', 'secure-files-manager')
            )
        ));
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function admin_enqueue_scripts($hook) {
        // Only load on our admin pages
        if (strpos($hook, 'secure-files') === false) {
            return;
        }
        
        wp_enqueue_style(
            'sfm-admin-style',
            SFM_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            $this->version
        );
        
        wp_enqueue_script(
            'sfm-admin-script',
            SFM_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery', 'wp-util'),
            $this->version,
            true
        );
        
        // Localize script
        wp_localize_script('sfm-admin-script', 'sfm_admin_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sfm_admin_nonce'),
            'strings' => array(
                'error' => __('An error occurred. Please try again.', 'secure-files-manager'),
                'success' => __('Operation completed successfully.', 'secure-files-manager'),
                'confirm_delete' => __('Are you sure you want to delete this file?', 'secure-files-manager'),
                'confirm_delete_role' => __('Are you sure you want to delete this role?', 'secure-files-manager')
            )
        ));
    }
    
    /**
     * Run the plugin
     */
    public function run() {
        $this->init();
    }
    
    /**
     * Get file manager instance
     */
    public function get_file_manager() {
        return $this->file_manager;
    }
    
    /**
     * Get role manager instance
     */
    public function get_role_manager() {
        return $this->role_manager;
    }
    
    /**
     * Get frontend instance
     */
    public function get_frontend() {
        return $this->frontend;
    }
    
    /**
     * Get admin instance
     */
    public function get_admin() {
        return $this->admin;
    }
}
