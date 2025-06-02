<?php
/**
 * Plugin Name: Nattevakten
 * Plugin URI: https://github.com/your-repo/nattevakten
 * Description: AI-powered night shift news generator with enterprise security and auto-repair capabilities
 * Version: 2.1.0
 * Author: Nattevakten Team
 * Author URI: https://nattevakten.no
 * Text Domain: nattevakten
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.5
 * Requires PHP: 7.4
 * Network: true
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit('Direct access forbidden.');
}

// Security: Prevent execution if called directly
if (!function_exists('add_action')) {
    http_response_code(403);
    exit('WordPress not loaded.');
}

/**
 * CONSTANTS DEFINITION - FIXED CRITICAL ERROR #2
 */
define('NATTEVAKTEN_VERSION', '2.1.0');
define('NATTEVAKTEN_PLUGIN_FILE', __FILE__);
define('NATTEVAKTEN_PATH', plugin_dir_path(__FILE__));
define('NATTEVAKTEN_URL', plugin_dir_url(__FILE__));
define('NATTEVAKTEN_BASENAME', plugin_basename(__FILE__));

// Secure path definitions with validation
$upload_dir = wp_upload_dir();
$base_path = trailingslashit($upload_dir['basedir']) . 'nattevakten/';

// Ensure directories exist and are secure
if (!file_exists($base_path)) {
    wp_mkdir_p($base_path);
}

define('NATTEVAKTEN_JSON_PATH', $base_path . 'json/');
define('NATTEVAKTEN_MEDIA_PATH', $base_path . 'media/');

// Create secure directories with .htaccess protection
$secure_dirs = [NATTEVAKTEN_JSON_PATH, NATTEVAKTEN_MEDIA_PATH];
foreach ($secure_dirs as $dir) {
    if (!file_exists($dir)) {
        wp_mkdir_p($dir);
        // Create .htaccess protection
        $htaccess_content = "# Nattevakten Security\nOrder deny,allow\nDeny from all\n<Files ~ \"\\.(json|log|bak)$\">\nOrder deny,allow\nDeny from all\n</Files>";
        file_put_contents($dir . '.htaccess', $htaccess_content);
    }
}

/**
 * MAIN PLUGIN CLASS - Singleton Pattern for Security
 */
final class Nattevakten_Plugin {
    
    private static $instance = null;
    private $loaded_modules = [];
    
    /**
     * Singleton instance
     */
    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Private constructor - Singleton pattern
     */
    private function __construct() {
        $this->init_hooks();
        $this->load_modules();
    }
    
    /**
     * Prevent cloning
     */
    private function __clone() {}
    
    /**
     * Prevent unserializing
     */
    private function __wakeup() {}
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // FIXED: Register activation hooks - CRITICAL ERROR #5
        register_activation_hook(NATTEVAKTEN_PLUGIN_FILE, [$this, 'activate']);
        register_deactivation_hook(NATTEVAKTEN_PLUGIN_FILE, [$this, 'deactivate']);
        register_uninstall_hook(NATTEVAKTEN_PLUGIN_FILE, ['Nattevakten_Plugin', 'uninstall']);
        
        // Core initialization
        add_action('init', [$this, 'init']);
        add_action('admin_init', [$this, 'admin_init']);
        add_action('admin_menu', [$this, 'admin_menu']);
        add_action('wp_dashboard_setup', [$this, 'dashboard_widget']);
        
        // Load textdomain
        add_action('plugins_loaded', [$this, 'load_textdomain']);
        
        // Security headers
        add_action('send_headers', [$this, 'security_headers']);
        
        // Error handler
        add_action('wp_loaded', [$this, 'setup_error_handling']);
    }
    
    /**
     * FIXED: Safe module loading with error handling - CRITICAL ERROR #4
     */
    private function load_modules() {
        $modules = [
            'security'     => 'inc/security.php',
            'logger'       => 'inc/logger.php', 
            'openai'       => 'inc/openai.php',
            'generator'    => 'inc/generator.php',
            'fallback'     => 'inc/fallback.php',
            'fix'          => 'inc/fix.php',
            'verify'       => 'inc/verify.php',
            'integrity'    => 'inc/integrity.php',
            'caching'      => 'inc/caching.php',
            'backup'       => 'inc/backup.php',
            'shortcode'    => 'inc/shortcode.php',
            'rest-api'     => 'inc/rest-api.php',
            'wordpress-integration' => 'inc/wordpress-integration.php'
        ];
        
        foreach ($modules as $name => $file) {
            $path = NATTEVAKTEN_PATH . $file;
            if (file_exists($path)) {
                try {
                    require_once $path;
                    $this->loaded_modules[$name] = true;
                } catch (Throwable $e) {
                    $this->log_error('module_load', $name, 'Failed to load: ' . $e->getMessage());
                    $this->loaded_modules[$name] = false;
                }
            } else {
                $this->log_error('module_missing', $name, "Module file missing: $file");
                $this->loaded_modules[$name] = false;
            }
        }
        
        // Load CLI commands if WP CLI is available
        if (defined('WP_CLI') && WP_CLI) {
            $cli_file = NATTEVAKTEN_PATH . 'cli/commands.php';
            if (file_exists($cli_file)) {
                require_once $cli_file;
            }
        }
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Verify system requirements
        if (!$this->check_requirements()) {
            deactivate_plugins(NATTEVAKTEN_BASENAME);
            wp_die(__('Nattevakten requires WordPress 5.0+ and PHP 7.4+', 'nattevakten'));
        }
        
        // Create secure directories
        $this->create_directories();
        
        // Initialize default settings
        $this->init_default_settings();
        
        // Run auto-fixer if available
        if (function_exists('nattevakten_auto_fixer')) {
            nattevakten_auto_fixer();
        }
        
        // Create backup
        if (function_exists('nattevakten_backup_files')) {
            nattevakten_backup_files();
        }
        
        // Log activation
        $this->log_error('system', 'activation', 'Plugin activated successfully', 'info');
        
        // Set activation flag
        update_option('nattevakten_activated', time());
        
        // Clear any existing caches
        wp_cache_flush();
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clear scheduled events
        wp_clear_scheduled_hook('nattevakten_generate_news');
        
        // Clear caches
        wp_cache_flush();
        
        // Log deactivation
        $this->log_error('system', 'deactivation', 'Plugin deactivated', 'info');
        
        delete_option('nattevakten_activated');
    }
    
    /**
     * Plugin uninstall
     */
    public static function uninstall() {
        // Remove options
        $options = [
            'nattevakten_api_key',
            'nattevakten_prompt', 
            'nattevakten_temp',
            'nattevakten_activated'
        ];
        
        foreach ($options as $option) {
            delete_option($option);
        }
        
        // Optionally remove files (admin choice)
        if (get_option('nattevakten_remove_data_on_uninstall', false)) {
            $upload_dir = wp_upload_dir();
            $nattevakten_dir = trailingslashit($upload_dir['basedir']) . 'nattevakten/';
            
            if (file_exists($nattevakten_dir)) {
                // Recursive directory removal
                self::remove_directory($nattevakten_dir);
            }
        }
    }
    
    /**
     * Initialize plugin
     */
    public function init() {
        // Register shortcode
        if ($this->loaded_modules['shortcode']) {
            add_shortcode('nattevakt_nyheter', 'nattevakten_render_ticker');
        }
        
        // Schedule events
        if (!wp_next_scheduled('nattevakten_generate_news')) {
            wp_schedule_event(time(), 'hourly', 'nattevakten_generate_news');
        }
        
        // Add action for scheduled generation
        add_action('nattevakten_generate_news', [$this, 'scheduled_generation']);
    }
    
    /**
     * Admin initialization
     */
    public function admin_init() {
        // Register settings
        $this->register_settings();
        
        // Add settings link
        add_filter('plugin_action_links_' . NATTEVAKTEN_BASENAME, [$this, 'plugin_action_links']);
    }
    
    /**
     * FIXED: Single admin menu function - CRITICAL ERROR #3
     */
    public function admin_menu() {
        // Main menu page
        add_menu_page(
            __('Nattevakten', 'nattevakten'),
            '🕯 Nattevakten',
            'manage_options',
            'nattevakten',
            [$this, 'settings_page'],
            'dashicons-schedule',
            58
        );
        
        // Submenu pages
        $submenus = [
            ['nattevakten', __('Innstillinger', 'nattevakten'), [$this, 'settings_page']],
            ['nattevakten_error_log', __('Feillogg', 'nattevakten'), [$this, 'error_log_page']],
            ['nattevakten_prev_errors', __('Tidligere feil', 'nattevakten'), [$this, 'prev_errors_page']],
            ['nattevakten_test', __('Testkjør', 'nattevakten'), [$this, 'test_page']],
            ['nattevakten_module_status', __('Modulstatus', 'nattevakten'), [$this, 'module_status_page']],
            ['nattevakten_fallback', __('Fallback-senter', 'nattevakten'), [$this, 'fallback_page']]
        ];
        
        foreach ($submenus as $submenu) {
            add_submenu_page('nattevakten', $submenu[1], $submenu[1], 'manage_options', $submenu[0], $submenu[2]);
        }
    }
    
    /**
     * Dashboard widget
     */
    public function dashboard_widget() {
        wp_add_dashboard_widget(
            'nattevakten_dashboard_widget',
            __('🕯 Nattevakten – Siste generering', 'nattevakten'),
            [$this, 'render_dashboard_widget']
        );
    }
    
    /**
     * Load text domain
     */
    public function load_textdomain() {
        load_plugin_textdomain('nattevakten', false, dirname(NATTEVAKTEN_BASENAME) . '/languages');
    }
    
    /**
     * Security headers
     */
    public function security_headers() {
        if (is_admin() && isset($_GET['page']) && strpos($_GET['page'], 'nattevakten') === 0) {
            header('X-Content-Type-Options: nosniff');
            header('X-Frame-Options: SAMEORIGIN');
            header('X-XSS-Protection: 1; mode=block');
            header('Referrer-Policy: strict-origin-when-cross-origin');
        }
    }
    
    /**
     * Setup error handling
     */
    public function setup_error_handling() {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            set_error_handler([$this, 'error_handler']);
        }
    }
    
    /**
     * Custom error handler
     */
    public function error_handler($severity, $message, $file, $line) {
        if (strpos($file, 'nattevakten') !== false) {
            $this->log_error('php_error', 'runtime_error', 
                sprintf('%s in %s:%d', $message, basename($file), $line), 'error');
        }
        return false; // Let WordPress handle it
    }
    
    /**
     * Scheduled news generation
     */
    public function scheduled_generation() {
        if (function_exists('nattevakten_generate_news')) {
            $result = nattevakten_generate_news();
            if ($result !== true) {
                $this->log_error('scheduler', 'generation_failed', 'Scheduled generation failed: ' . $result);
            }
        }
    }
    
    /**
     * Check system requirements
     */
    private function check_requirements() {
        global $wp_version;
        
        // Check WordPress version
        if (version_compare($wp_version, '5.0', '<')) {
            return false;
        }
        
        // Check PHP version
        if (version_compare(PHP_VERSION, '7.4', '<')) {
            return false;
        }
        
        // Check required extensions
        $required_extensions = ['json', 'openssl', 'curl'];
        foreach ($required_extensions as $ext) {
            if (!extension_loaded($ext)) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Create secure directories
     */
    private function create_directories() {
        $dirs = [NATTEVAKTEN_JSON_PATH, NATTEVAKTEN_MEDIA_PATH];
        
        foreach ($dirs as $dir) {
            if (!file_exists($dir)) {
                wp_mkdir_p($dir);
                
                // Create .htaccess protection
                $htaccess = $dir . '.htaccess';
                if (!file_exists($htaccess)) {
                    $content = "# Nattevakten Security\n";
                    $content .= "Order deny,allow\n";
                    $content .= "Deny from all\n";
                    $content .= "<Files ~ \"\\.(json|log|bak)$\">\n";
                    $content .= "Order deny,allow\n";
                    $content .= "Deny from all\n";
                    $content .= "</Files>\n";
                    file_put_contents($htaccess, $content);
                }
            }
        }
    }
    
    /**
     * Initialize default settings
     */
    private function init_default_settings() {
        $defaults = [
            'nattevakten_temp' => 0.7,
            'nattevakten_prompt' => __('Generer 3-5 kreative lokalnyheter fra Pjuskeby på norsk.', 'nattevakten')
        ];
        
        foreach ($defaults as $option => $value) {
            if (get_option($option) === false) {
                add_option($option, $value);
            }
        }
    }
    
    /**
     * Register settings
     */
    private function register_settings() {
        register_setting('nattevakten_settings', 'nattevakten_api_key', [
            'sanitize_callback' => [$this, 'sanitize_api_key']
        ]);
        register_setting('nattevakten_settings', 'nattevakten_prompt', [
            'sanitize_callback' => 'sanitize_textarea_field'
        ]);
        register_setting('nattevakten_settings', 'nattevakten_temp', [
            'sanitize_callback' => [$this, 'sanitize_temperature']
        ]);
        
        // Settings sections and fields
        add_settings_section(
            'nattevakten_main_section',
            __('Grunninnstillinger', 'nattevakten'),
            [$this, 'settings_section_callback'],
            'nattevakten'
        );
        
        add_settings_field('nattevakten_api_key', __('API-nøkkel', 'nattevakten'), 
            [$this, 'api_key_field'], 'nattevakten', 'nattevakten_main_section');
        add_settings_field('nattevakten_prompt', __('Standardprompt', 'nattevakten'), 
            [$this, 'prompt_field'], 'nattevakten', 'nattevakten_main_section');
        add_settings_field('nattevakten_temp', __('AI temperatur', 'nattevakten'), 
            [$this, 'temp_field'], 'nattevakten', 'nattevakten_main_section');
    }
    
    /**
     * FIXED: Sanitize API key with encryption
     */
    public function sanitize_api_key($value) {
        if (empty($value) || $value === '••••••••') {
            return get_option('nattevakten_api_key'); // Keep existing
        }
        
        // Validate API key format
        if (!preg_match('/^sk-[a-zA-Z0-9]{48,}$/', $value)) {
            add_settings_error('nattevakten_api_key', 'invalid_format', 
                __('API-nøkkel har ugyldig format', 'nattevakten'));
            return get_option('nattevakten_api_key');
        }
        
        // Encrypt before storing
        if (function_exists('nattevakten_encrypt_api_key')) {
            return nattevakten_encrypt_api_key($value);
        }
        
        return $value;
    }
    
    /**
     * Sanitize temperature
     */
    public function sanitize_temperature($value) {
        $temp = floatval($value);
        return max(0.0, min(2.0, $temp));
    }
    
    /**
     * Add plugin action links
     */
    public function plugin_action_links($links) {
        $settings_link = '<a href="' . admin_url('admin.php?page=nattevakten') . '">' . __('Innstillinger', 'nattevakten') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }
    
    /**
     * FIXED: Safe logging with fallback
     */
    private function log_error($module, $type, $message, $level = 'error') {
        if (function_exists('nattevakten_log_error')) {
            nattevakten_log_error($module, $type, $message, $level);
        } else {
            // Fallback to WordPress error log
            error_log(sprintf('[Nattevakten][%s][%s] %s', $module, $level, $message));
        }
    }
    
    /**
     * Recursive directory removal
     */
    private static function remove_directory($dir) {
        if (!is_dir($dir)) return;
        
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            is_dir($path) ? self::remove_directory($path) : unlink($path);
        }
        return rmdir($dir);
    }
    
    // Admin page methods
    public function settings_section_callback() {
        echo '<p>' . esc_html__('Konfigurer Nattevakten-pluginen her.', 'nattevakten') . '</p>';
    }
    
    public function api_key_field() {
        $value = get_option('nattevakten_api_key');
        echo '<input type="password" name="nattevakten_api_key" value="' . 
             esc_attr($value ? '••••••••' : '') . '" class="regular-text" autocomplete="new-password">';
        echo '<p class="description">' . esc_html__('OpenAI API-nøkkel (sk-...)', 'nattevakten') . '</p>';
    }
    
    public function prompt_field() {
        $value = get_option('nattevakten_prompt');
        echo '<textarea name="nattevakten_prompt" rows="3" cols="50">' . esc_textarea($value) . '</textarea>';
    }
    
    public function temp_field() {
        $value = get_option('nattevakten_temp', 0.7);
        echo '<input type="number" step="0.1" min="0" max="2" name="nattevakten_temp" value="' . 
             esc_attr($value) . '" style="width:60px">';
    }
    
    // Admin page renderers (simplified versions)
    public function settings_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Du har ikke tilgang til denne siden.', 'nattevakten'));
        }
        ?>
        <div class="wrap">
            <h1>🕯 <?php echo esc_html(get_admin_page_title()); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('nattevakten_settings');
                do_settings_sections('nattevakten');
                submit_button(__('Lagre innstillinger', 'nattevakten'));
                ?>
            </form>
        </div>
        <?php
    }
    
    public function error_log_page() {
        echo '<div class="wrap"><h1>📝 ' . esc_html__('Feillogg', 'nattevakten') . '</h1>';
        echo '<p>' . esc_html__('Feillogg funksjonalitet lastes...', 'nattevakten') . '</p></div>';
    }
    
    public function prev_errors_page() {
        echo '<div class="wrap"><h1>📚 ' . esc_html__('Tidligere feil', 'nattevakten') . '</h1>';
        echo '<p>' . esc_html__('Tidligere feil funksjonalitet lastes...', 'nattevakten') . '</p></div>';
    }
    
    public function test_page() {
        echo '<div class="wrap"><h1>🛠️ ' . esc_html__('Testkjør', 'nattevakten') . '</h1>';
        echo '<p>' . esc_html__('Test funksjonalitet lastes...', 'nattevakten') . '</p></div>';
    }
    
    public function module_status_page() {
        echo '<div class="wrap"><h1>📊 ' . esc_html__('Modulstatus', 'nattevakten') . '</h1>';
        if ($this->loaded_modules) {
            echo '<h3>' . esc_html__('Lastede moduler:', 'nattevakten') . '</h3><ul>';
            foreach ($this->loaded_modules as $module => $loaded) {
                $status = $loaded ? '✅' : '❌';
                echo '<li>' . esc_html("$status $module") . '</li>';
            }
            echo '</ul>';
        }
        echo '</div>';
    }
    
    public function fallback_page() {
        echo '<div class="wrap"><h1>🔄 ' . esc_html__('Fallback-senter', 'nattevakten') . '</h1>';
        echo '<p>' . esc_html__('Fallback funksjonalitet lastes...', 'nattevakten') . '</p></div>';
    }
    
    public function render_dashboard_widget() {
        echo '<p>' . esc_html__('Nattevakten dashboard widget lastes...', 'nattevakten') . '</p>';
        echo '<a href="' . admin_url('admin.php?page=nattevakten') . '" class="button">' . 
             esc_html__('Gå til innstillinger', 'nattevakten') . '</a>';
    }
}

// Initialize plugin
function nattevakten_init() {
    return Nattevakten_Plugin::instance();
}

// Start the plugin
add_action('plugins_loaded', 'nattevakten_init', 0);

// Compatibility functions for modules
if (!function_exists('nattevakten_encrypt_api_key')) {
    function nattevakten_encrypt_api_key($api_key) {
        return base64_encode($api_key); // Simplified for now
    }
}

if (!function_exists('nattevakten_log_error')) {
    function nattevakten_log_error($module, $type, $message, $level = 'error') {
        error_log(sprintf('[Nattevakten][%s][%s] %s', $module, $level, $message));
    }
}