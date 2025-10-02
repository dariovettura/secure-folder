<?php
/**
 * Admin Class
 * 
 * Handles admin interface and functionality
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFM_Admin {
    
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
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'admin_init'));
        add_action('admin_notices', array($this, 'admin_notices'));
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        // Main menu
        add_menu_page(
            'Secure Files Manager',
            'Secure Files',
            'manage_options',
            'secure-files-manager',
            array($this, 'admin_page_files'),
            'dashicons-shield-alt',
            30
        );
        
        // Files submenu
        add_submenu_page(
            'secure-files-manager',
            'Files',
            'Files',
            'manage_options',
            'secure-files-manager',
            array($this, 'admin_page_files')
        );
        
        // Roles submenu
        add_submenu_page(
            'secure-files-manager',
            'Custom Roles',
            'Custom Roles',
            'manage_options',
            'secure-files-roles',
            array($this, 'admin_page_roles')
        );
        
        // Settings submenu
        add_submenu_page(
            'secure-files-manager',
            'Settings',
            'Settings',
            'manage_options',
            'secure-files-settings',
            array($this, 'admin_page_settings')
        );
    }
    
    /**
     * Admin initialization
     */
    public function admin_init() {
        // Register settings
        register_setting('sfm_settings', 'sfm_max_file_size');
        register_setting('sfm_settings', 'sfm_allowed_file_types');
        register_setting('sfm_settings', 'sfm_require_login');
        register_setting('sfm_settings', 'sfm_frontend_page_id');
    }
    
    /**
     * Admin notices
     */
    public function admin_notices() {
        // Check if protected folder exists
        if (!file_exists(SFM_PROTECTED_PATH)) {
            echo '<div class="notice notice-error"><p>';
            echo 'Secure Files Manager: Protected folder does not exist. Please deactivate and reactivate the plugin.';
            echo '</p></div>';
        }
        
        // Check if .htaccess exists
        if (!file_exists(SFM_PROTECTED_PATH . '/.htaccess')) {
            echo '<div class="notice notice-warning"><p>';
            echo 'Secure Files Manager: .htaccess file is missing from protected folder. Security may be compromised.';
            echo '</p></div>';
        }
    }
    
    /**
     * Files admin page
     */
    public function admin_page_files() {
        $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'list';
        
        switch ($action) {
            case 'upload':
                $this->render_upload_page();
                break;
            case 'edit':
                $this->render_edit_page();
                break;
            default:
                $this->render_files_list();
                break;
        }
    }
    
    /**
     * Render files list
     */
    private function render_files_list() {
        $file_manager = SFM_Core::instance()->get_file_manager();
        $files = $file_manager->get_files(array('limit' => 50));
        
        ?>
        <div class="wrap">
            <h1>Secure Files
                <a href="<?php echo admin_url('admin.php?page=secure-files-manager&action=upload'); ?>" class="page-title-action">
                    Add New File
                </a>
            </h1>
            
            <div class="sfm-admin-files">
                <?php if (empty($files)): ?>
                    <div class="sfm-empty-state">
                        <h3>No files uploaded yet</h3>
                        <p>Upload your first secure file to get started.</p>
                        <a href="<?php echo admin_url('admin.php?page=secure-files-manager&action=upload'); ?>" class="button button-primary">
                            Upload File
                        </a>
                    </div>
                <?php else: ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>File Name</th>
                                <th>Size</th>
                                <th>Type</th>
                                <th>Uploaded By</th>
                                <th>Upload Date</th>
                                <th>Downloads</th>
                                <th>Allowed Roles</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($files as $file): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo esc_html($file->original_name); ?></strong>
                                        <?php if (!empty($file->description)): ?>
                                            <br><small><?php echo esc_html($file->description); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo size_format($file->file_size); ?></td>
                                    <td><?php echo esc_html($file->mime_type); ?></td>
                                    <td><?php echo get_userdata($file->uploaded_by)->display_name; ?></td>
                                    <td><?php echo date('M j, Y H:i', strtotime($file->uploaded_at)); ?></td>
                                    <td><?php echo $file->download_count; ?></td>
                                    <td>
                                        <?php
                                        $allowed_roles = unserialize($file->allowed_roles);
                                        if (empty($allowed_roles)) {
                                            echo '<em>Admin only</em>';
                                        } else {
                                            echo implode(', ', $allowed_roles);
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <a href="<?php echo $this->get_file_view_url($file); ?>" class="button button-small" target="_blank">
                                            View
                                        </a>
                                        <a href="<?php echo admin_url('admin.php?page=secure-files-manager&action=edit&file_id=' . $file->id); ?>" class="button button-small">
                                            Edit
                                        </a>
                                        <button class="button button-small sfm-delete-file" data-file-id="<?php echo $file->id; ?>">
                                            Delete
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render upload page
     */
    private function render_upload_page() {
        // Get only custom roles created by the plugin
        global $wpdb;
        $custom_roles_table = $wpdb->prefix . 'sfm_custom_roles';
        $custom_roles = $wpdb->get_results("SELECT role_name, role_display_name FROM $custom_roles_table WHERE is_active = 1");
        
        ?>
        <div class="wrap">
            <h1>Upload Secure File</h1>
            
            <form id="sfm-upload-form" enctype="multipart/form-data">
                <?php wp_nonce_field('sfm_admin_nonce', 'sfm_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="file">Select File</label>
                        </th>
                        <td>
                            <input type="file" id="file" name="file" required>
                            <p class="description">Maximum file size: <?php echo size_format(get_option('sfm_max_file_size', 10485760)); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="description">Description</label>
                        </th>
                        <td>
                            <textarea id="description" name="description" rows="3" cols="50" placeholder="Optional file description"></textarea>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="allowed_roles">Allowed Roles</label>
                        </th>
                        <td>
                            <fieldset>
                                <legend class="screen-reader-text">Allowed Roles</legend>
                                
                                <?php if (empty($custom_roles)): ?>
                                    <p>No custom roles available. <a href="<?php echo admin_url('admin.php?page=secure-files-roles&action=add'); ?>">Create a custom role</a> first.</p>
                                <?php else: ?>
                                    <h4>Custom Roles:</h4>
                                    <?php foreach ($custom_roles as $role): ?>
                                        <label>
                                            <input type="checkbox" name="allowed_roles[]" value="<?php echo esc_attr($role->role_name); ?>">
                                            <?php echo esc_html($role->role_display_name); ?>
                                        </label><br>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                                
                                <p class="description">
                                    Select which custom roles can access this file. If no roles are selected, only administrators can access it.
                                </p>
                            </fieldset>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <input type="submit" class="button button-primary" value="Upload File">
                    <a href="<?php echo admin_url('admin.php?page=secure-files-manager'); ?>" class="button">
                        Cancel
                    </a>
                </p>
            </form>
        </div>
        <?php
    }
    
    /**
     * Render edit page
     */
    private function render_edit_page() {
        $file_id = intval($_GET['file_id']);
        $file_manager = SFM_Core::instance()->get_file_manager();
        
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
        
        // Get only custom roles created by the plugin
        global $wpdb;
        $custom_roles_table = $wpdb->prefix . 'sfm_custom_roles';
        $custom_roles = $wpdb->get_results("SELECT role_name, role_display_name FROM $custom_roles_table WHERE is_active = 1");
        
        $allowed_roles = unserialize($file->allowed_roles);
        
        ?>
        <div class="wrap">
            <h1>Edit File: <?php echo esc_html($file->original_name); ?></h1>
            
            <form id="sfm-edit-form">
                <?php wp_nonce_field('sfm_admin_nonce', 'sfm_nonce'); ?>
                <input type="hidden" name="file_id" value="<?php echo $file->id; ?>">
                
                <table class="form-table">
                    <tr>
                        <th scope="row">File Name</th>
                        <td>
                            <strong><?php echo esc_html($file->original_name); ?></strong>
                            <p class="description">Original filename cannot be changed</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">File Size</th>
                        <td><?php echo size_format($file->file_size); ?></td>
                    </tr>
                    
                    <tr>
                        <th scope="row">File Type</th>
                        <td><?php echo esc_html($file->mime_type); ?></td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="description">Description</label>
                        </th>
                        <td>
                            <textarea id="description" name="description" rows="3" cols="50"><?php echo esc_textarea($file->description); ?></textarea>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="allowed_roles">Allowed Roles</label>
                        </th>
                        <td>
                            <fieldset>
                                <legend class="screen-reader-text">Allowed Roles</legend>
                                
                                <?php if (empty($custom_roles)): ?>
                                    <p>No custom roles available. <a href="<?php echo admin_url('admin.php?page=secure-files-roles&action=add'); ?>">Create a custom role</a> first.</p>
                                <?php else: ?>
                                    <h4>Custom Roles:</h4>
                                    <?php foreach ($custom_roles as $role): ?>
                                        <label>
                                            <input type="checkbox" name="allowed_roles[]" value="<?php echo esc_attr($role->role_name); ?>" 
                                                   <?php checked(in_array($role->role_name, $allowed_roles)); ?>>
                                            <?php echo esc_html($role->role_display_name); ?>
                                        </label><br>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </fieldset>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <input type="submit" class="button button-primary" value="Update File">
                    <a href="<?php echo admin_url('admin.php?page=secure-files-manager'); ?>" class="button">
                        Cancel
                    </a>
                </p>
            </form>
        </div>
        <?php
    }
    
    /**
     * Roles admin page
     */
    public function admin_page_roles() {
        $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'list';
        
        switch ($action) {
            case 'add':
                $this->render_add_role_page();
                break;
            case 'edit':
                $this->render_edit_role_page();
                break;
            default:
                $this->render_roles_list();
                break;
        }
    }
    
    /**
     * Render roles list
     */
    private function render_roles_list() {
        $role_manager = SFM_Core::instance()->get_role_manager();
        $custom_roles = $role_manager->get_custom_roles();
        
        ?>
        <div class="wrap">
            <h1>Custom Roles
                <a href="<?php echo admin_url('admin.php?page=secure-files-roles&action=add'); ?>" class="page-title-action">
                    Add New Role
                </a>
            </h1>
            
            <div class="sfm-admin-roles">
                <?php if (empty($custom_roles)): ?>
                    <div class="sfm-empty-state">
                        <h3>No custom roles created yet</h3>
                        <p>Create custom roles to provide specific file access permissions.</p>
                        <a href="<?php echo admin_url('admin.php?page=secure-files-roles&action=add'); ?>" class="button button-primary">
                            Create Role
                        </a>
                    </div>
                <?php else: ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>Role Name</th>
                                <th>Display Name</th>
                                <th>Description</th>
                                <th>Capabilities</th>
                                <th>Created By</th>
                                <th>Created Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($custom_roles as $role): ?>
                                <tr>
                                    <td><strong><?php echo esc_html($role->role_name); ?></strong></td>
                                    <td><?php echo esc_html($role->role_display_name); ?></td>
                                    <td><?php echo esc_html($role->role_description); ?></td>
                                    <td>
                                        <?php
                                        $capabilities = unserialize($role->capabilities);
                                        echo implode(', ', $capabilities);
                                        ?>
                                    </td>
                                    <td><?php echo get_userdata($role->created_by)->display_name; ?></td>
                                    <td><?php echo date('M j, Y', strtotime($role->created_at)); ?></td>
                                    <td>
                                        <a href="<?php echo admin_url('admin.php?page=secure-files-roles&action=edit&role_id=' . $role->id); ?>" class="button button-small">
                                            Edit
                                        </a>
                                        <button class="button button-small sfm-delete-role" data-role-id="<?php echo $role->id; ?>">
                                            Delete
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render add role page
     */
    private function render_add_role_page() {
        $role_manager = new SFM_Role_Manager();
        $available_capabilities = $role_manager->get_available_capabilities();
        
        ?>
        <div class="wrap">
            <h1>Add Custom Role</h1>
            
            <form id="sfm-add-role-form">
                <?php wp_nonce_field('sfm_admin_nonce', 'sfm_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="role_name">Role Name</label>
                        </th>
                        <td>
                            <input type="text" id="role_name" name="role_name" required>
                            <p class="description">Internal role name (lowercase, no spaces)</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="role_display_name">Display Name</label>
                        </th>
                        <td>
                            <input type="text" id="role_display_name" name="role_display_name" required>
                            <p class="description">Human-readable role name</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="role_description">Description</label>
                        </th>
                        <td>
                            <textarea id="role_description" name="role_description" rows="3" cols="50"></textarea>
                            <p class="description">Optional description of the role</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label>Capabilities</label>
                        </th>
                        <td>
                            <p><strong>Custom roles have limited capabilities:</strong></p>
                            <ul>
                                <li>✅ View Secure Files</li>
                                <li>✅ Download Files</li>
                                <li>❌ Upload Files (Admin only)</li>
                                <li>❌ Manage Files (Admin only)</li>
                                <li>❌ Manage Roles (Admin only)</li>
                            </ul>
                            <p class="description">Custom roles can only view and download files assigned to them.</p>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <input type="submit" class="button button-primary" value="Create Role">
                    <a href="<?php echo admin_url('admin.php?page=secure-files-roles'); ?>" class="button">
                        Cancel
                    </a>
                </p>
            </form>
        </div>
        <?php
    }
    
    /**
     * Render edit role page
     */
    private function render_edit_role_page() {
        $role_id = intval($_GET['role_id']);
        
        // Get role info
        global $wpdb;
        $table_name = $wpdb->prefix . 'sfm_custom_roles';
        $role = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $role_id
        ));
        
        if (!$role) {
            wp_die('Role not found');
        }
        
        ?>
        <div class="wrap">
            <h1>Edit Role: <?php echo esc_html($role->role_display_name); ?></h1>
            
            <form id="sfm-update-role-form">
                <?php wp_nonce_field('sfm_admin_nonce', 'sfm_nonce'); ?>
                <input type="hidden" name="role_id" value="<?php echo $role->id; ?>">
                
                <table class="form-table">
                    <tr>
                        <th scope="row">Role Name</th>
                        <td>
                            <strong><?php echo esc_html($role->role_name); ?></strong>
                            <p class="description">Role name cannot be changed</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="role_display_name">Display Name</label>
                        </th>
                        <td>
                            <input type="text" id="role_display_name" name="role_display_name" value="<?php echo esc_attr($role->role_display_name); ?>" required>
                            <p class="description">Human-readable role name</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="role_description">Description</label>
                        </th>
                        <td>
                            <textarea id="role_description" name="role_description" rows="3" cols="50"><?php echo esc_textarea($role->role_description); ?></textarea>
                            <p class="description">Optional description of the role</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label>Capabilities</label>
                        </th>
                        <td>
                            <p><strong>Custom roles have limited capabilities:</strong></p>
                            <ul>
                                <li>✅ View Secure Files</li>
                                <li>✅ Download Files</li>
                                <li>❌ Upload Files (Admin only)</li>
                                <li>❌ Manage Files (Admin only)</li>
                                <li>❌ Manage Roles (Admin only)</li>
                            </ul>
                            <p class="description">Custom roles can only view and download files assigned to them.</p>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <input type="submit" class="button button-primary" value="Update Role">
                    <a href="<?php echo admin_url('admin.php?page=secure-files-roles'); ?>" class="button">
                        Cancel
                    </a>
                </p>
            </form>
        </div>
        <?php
    }
    
    /**
     * Settings admin page
     */
    public function admin_page_settings() {
        if (isset($_POST['submit'])) {
            $this->save_settings();
        }
        
        $max_file_size = get_option('sfm_max_file_size', 10485760);
        $allowed_file_types = get_option('sfm_allowed_file_types', array());
        $require_login = get_option('sfm_require_login', true);
        $frontend_page_id = get_option('sfm_frontend_page_id', '');
        
        ?>
        <div class="wrap">
            <h1>Secure Files Settings</h1>
            
            <form method="post" action="">
                <?php wp_nonce_field('sfm_settings'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="max_file_size">Maximum File Size</label>
                        </th>
                        <td>
                            <input type="number" id="max_file_size" name="max_file_size" value="<?php echo $max_file_size; ?>" min="1048576" max="104857600">
                            <p class="description">Maximum file size in bytes (1MB = 1048576 bytes)</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="allowed_file_types">Allowed File Types</label>
                        </th>
                        <td>
                            <input type="text" id="allowed_file_types" name="allowed_file_types" value="<?php echo implode(', ', $allowed_file_types); ?>" class="regular-text">
                            <p class="description">Comma-separated list of allowed file extensions (e.g., pdf, doc, docx, jpg, png)</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="require_login">Require Login</label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" id="require_login" name="require_login" value="1" <?php checked($require_login); ?>>
                                Users must be logged in to access files
                            </label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="frontend_page_id">Frontend Page</label>
                        </th>
                        <td>
                            <?php
                            wp_dropdown_pages(array(
                                'name' => 'frontend_page_id',
                                'selected' => $frontend_page_id,
                                'show_option_none' => 'Select a page',
                                'option_none_value' => ''
                            ));
                            ?>
                            <p class="description">Page where secure files will be displayed to users</p>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <input type="submit" name="submit" class="button button-primary" value="Save Settings">
                </p>
            </form>
            
            <!-- Shortcode Section -->
            <div class="sfm-shortcode-section" style="margin-top: 40px;">
                <h2>Shortcode Usage</h2>
                <p>Use the shortcode below to display secure files on any page or post. Users will only see files assigned to their role.</p>
                
                <div class="sfm-shortcode-examples">
                    <h3>Basic Usage</h3>
                    <div class="sfm-code-block">
                        <code id="basic-shortcode">[secure_files]</code>
                        <button class="button button-small sfm-copy-btn" data-target="basic-shortcode">Copy</button>
                    </div>
                    
                    <h3>Advanced Usage with Options</h3>
                    <div class="sfm-code-block">
                        <code id="advanced-shortcode">[secure_files title="My Files" layout="grid" limit="10" show_description="true" show_download_count="true" show_upload_date="true" show_view_button="true" show_download_button="true"]</code>
                        <button class="button button-small sfm-copy-btn" data-target="advanced-shortcode">Copy</button>
                    </div>
                    
                    <h3>List Layout</h3>
                    <div class="sfm-code-block">
                        <code id="list-shortcode">[secure_files layout="list" title="File List" limit="20"]</code>
                        <button class="button button-small sfm-copy-btn" data-target="list-shortcode">Copy</button>
                    </div>
                    
                    <h3>View Only (No Download)</h3>
                    <div class="sfm-code-block">
                        <code id="view-only-shortcode">[secure_files show_download_button="false" title="Preview Files"]</code>
                        <button class="button button-small sfm-copy-btn" data-target="view-only-shortcode">Copy</button>
                    </div>
                    
                    <h3>Download Only (No View)</h3>
                    <div class="sfm-code-block">
                        <code id="download-only-shortcode">[secure_files show_view_button="false" title="Download Files"]</code>
                        <button class="button button-small sfm-copy-btn" data-target="download-only-shortcode">Copy</button>
                    </div>
                </div>
                
                <div class="sfm-shortcode-options">
                    <h3>Available Options</h3>
                    <table class="widefat">
                        <thead>
                            <tr>
                                <th>Parameter</th>
                                <th>Default Value</th>
                                <th>Description</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><code>title</code></td>
                                <td>"Secure Files"</td>
                                <td>Title displayed above the file list</td>
                            </tr>
                            <tr>
                                <td><code>layout</code></td>
                                <td>"grid"</td>
                                <td>Layout style: "grid" or "list"</td>
                            </tr>
                            <tr>
                                <td><code>limit</code></td>
                                <td>20</td>
                                <td>Maximum number of files to display</td>
                            </tr>
                            <tr>
                                <td><code>show_description</code></td>
                                <td>"true"</td>
                                <td>Show file description: "true" or "false"</td>
                            </tr>
                            <tr>
                                <td><code>show_download_count</code></td>
                                <td>"true"</td>
                                <td>Show download count: "true" or "false"</td>
                            </tr>
                            <tr>
                                <td><code>show_upload_date</code></td>
                                <td>"true"</td>
                                <td>Show upload date: "true" or "false"</td>
                            </tr>
                            <tr>
                                <td><code>show_view_button</code></td>
                                <td>"true"</td>
                                <td>Show view button: "true" or "false"</td>
                            </tr>
                            <tr>
                                <td><code>show_download_button</code></td>
                                <td>"true"</td>
                                <td>Show download button: "true" or "false"</td>
                            </tr>
                            <tr>
                                <td><code>empty_message</code></td>
                                <td>"No files available..."</td>
                                <td>Message shown when no files are available</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <style>
        .sfm-shortcode-section {
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
            padding: 20px;
        }
        
        .sfm-shortcode-examples h3 {
            margin-top: 25px;
            margin-bottom: 10px;
            color: #23282d;
        }
        
        .sfm-code-block {
            background: #f1f1f1;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 10px;
            margin: 10px 0;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .sfm-code-block code {
            background: none;
            border: none;
            padding: 0;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            color: #333;
            flex: 1;
            word-break: break-all;
        }
        
        .sfm-copy-btn {
            margin-left: 10px;
            flex-shrink: 0;
        }
        
        .sfm-shortcode-options {
            margin-top: 30px;
        }
        
        .sfm-shortcode-options h3 {
            margin-bottom: 15px;
            color: #23282d;
        }
        
        .sfm-shortcode-options table {
            margin-top: 10px;
        }
        
        .sfm-shortcode-options th {
            background: #f9f9f9;
            font-weight: 600;
        }
        
        .sfm-shortcode-options code {
            background: #f1f1f1;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 12px;
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            $('.sfm-copy-btn').on('click', function() {
                var target = $(this).data('target');
                var code = $('#' + target).text();
                
                // Create temporary textarea
                var temp = $('<textarea>');
                $('body').append(temp);
                temp.val(code).select();
                document.execCommand('copy');
                temp.remove();
                
                // Show feedback
                var btn = $(this);
                var originalText = btn.text();
                btn.text('Copied!').addClass('button-primary');
                
                setTimeout(function() {
                    btn.text(originalText).removeClass('button-primary');
                }, 2000);
            });
        });
        </script>
        <?php
    }
    
    /**
     * Save settings
     */
    private function save_settings() {
        if (!wp_verify_nonce($_POST['_wpnonce'], 'sfm_settings')) {
            wp_die('Security check failed');
        }
        
        update_option('sfm_max_file_size', intval($_POST['max_file_size']));
        update_option('sfm_allowed_file_types', array_map('trim', explode(',', $_POST['allowed_file_types'])));
        update_option('sfm_require_login', isset($_POST['require_login']));
        update_option('sfm_frontend_page_id', intval($_POST['frontend_page_id']));
        
        echo '<div class="notice notice-success"><p>Settings saved successfully!</p></div>';
    }
    
    /**
     * Get file view URL
     */
    private function get_file_view_url($file) {
        $frontend = SFM_Core::instance()->get_frontend();
        return $frontend->get_file_view_url($file);
    }
}
