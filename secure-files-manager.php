<?php
/**
 * Plugin Name: Secure Files Manager
 * Plugin URI: https://yoursite.com
 * Description: Gestisce file protetti con controllo accessi basato su ruoli personalizzati. Crea una cartella protetta separata con .htaccess dedicato.
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://yoursite.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: secure-files-manager
 * Domain Path: /languages
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('SFM_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SFM_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('SFM_PLUGIN_VERSION', '1.0.0');
define('SFM_PROTECTED_FOLDER', 'secure-files');
define('SFM_PROTECTED_PATH', WP_CONTENT_DIR . '/' . SFM_PROTECTED_FOLDER);

// Include required files
require_once SFM_PLUGIN_PATH . 'includes/class-sfm-activator.php';
require_once SFM_PLUGIN_PATH . 'includes/class-sfm-deactivator.php';
require_once SFM_PLUGIN_PATH . 'includes/class-sfm-core.php';
require_once SFM_PLUGIN_PATH . 'includes/class-sfm-file-manager.php';
require_once SFM_PLUGIN_PATH . 'includes/class-sfm-role-manager.php';
require_once SFM_PLUGIN_PATH . 'includes/class-sfm-frontend.php';
require_once SFM_PLUGIN_PATH . 'admin/class-sfm-admin.php';

// Activation and deactivation hooks
register_activation_hook(__FILE__, array('SFM_Activator', 'activate'));
register_deactivation_hook(__FILE__, array('SFM_Deactivator', 'deactivate'));

// Initialize the plugin
function sfm_init() {
    static $initialized = false;
    
    if ($initialized) {
        return;
    }
    
    $initialized = true;
    $plugin = SFM_Core::instance();
    $plugin->run();
}
add_action('plugins_loaded', 'sfm_init');

// Custom capabilities are handled by SFM_Activator class
