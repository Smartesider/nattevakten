<?php
/**
 * WordPress 6.5+ compatibility and modern features
 */

// Site Health integration
add_filter('site_status_tests', 'nattevakten_add_site_health_tests');
function nattevakten_add_site_health_tests($tests) {
    $tests['direct']['nattevakten_configuration'] = [
        'label' => __('Nattevakten Configuration', 'nattevakten'),
        'test' => 'nattevakten_site_health_test'
    ];
    
    return $tests;
}

function nattevakten_site_health_test() {
    $result = [
        'label' => __('Nattevakten Configuration', 'nattevakten'),
        'status' => 'good',
        'badge' => [
            'label' => __('Plugin', 'nattevakten'),
            'color' => 'blue',
        ],
        'description' => __('Nattevakten er korrekt konfigurert', 'nattevakten'),
        'actions' => '',
        'test' => 'nattevakten_configuration',
    ];
    
    $issues = [];
    
    // Check API key
    if (empty(get_option('nattevakten_api_key'))) {
        $issues[] = __('API-nøkkel mangler', 'nattevakten');
    }
    
    // Check directory permissions
    if (!is_writable(NATTEVAKTEN_JSON_PATH)) {
        $issues[] = __('JSON-mappen er ikke skrivbar', 'nattevakten');
    }
    
    // Check required PHP extensions
    $required_extensions = ['json', 'openssl', 'curl'];
    foreach ($required_extensions as $ext) {
        if (!extension_loaded($ext)) {
            $issues[] = sprintf(__('PHP-utvidelse %s mangler', 'nattevakten'), $ext);
        }
    }
    
    if (!empty($issues)) {
        $result['status'] = 'critical';
        $result['label'] = __('Nattevakten har konfigurasjonsproblemer', 'nattevakten');
        $result['description'] = implode(', ', $issues);
        $result['actions'] = sprintf(
            '<a href="%s">%s</a>',
            admin_url('admin.php?page=nattevakten'),
            __('Gå til innstillinger', 'nattevakten')
        );
    }
    
    return $result;
}

// Block editor integration (Gutenberg)
add_action('init', 'nattevakten_register_block');
function nattevakten_register_block() {
    if (!function_exists('register_block_type')) {
        return; // Gutenberg not available
    }
    
    wp_register_script(
        'nattevakten-block-editor',
        NATTEVAKTEN_URL . 'assets/block-editor.js',
        ['wp-blocks', 'wp-element', 'wp-editor', 'wp-components'],
        NATTEVAKTEN_VERSION,
        true
    );
    
    register_block_type('nattevakten/ticker', [
        'editor_script' => 'nattevakten-block-editor',
        'render_callback' => 'nattevakten_render_ticker',
        'attributes' => [
            'limit' => [
                'type' => 'number',
                'default' => 5
            ],
            'interval' => [
                'type' => 'number',
                'default' => 6000
            ],
            'autoPlay' => [
                'type' => 'boolean',
                'default' => true
            ],
            'showControls' => [
                'type' => 'boolean',
                'default' => true
            ]
        ]
    ]);
    
    // Inline script for block editor
    add_action('admin_footer', function() {
        if (!get_current_screen() || get_current_screen()->base !== 'post') {
            return;
        }
        ?>
        <script>
        (function() {
            if (typeof wp === 'undefined' || !wp.blocks) return;
            
            const { registerBlockType } = wp.blocks;
            const { InspectorControls, useBlockProps } = wp.blockEditor;
            const { PanelBody, RangeControl, ToggleControl } = wp.components;
            const { createElement: e } = wp.element;
            
            registerBlockType('nattevakten/ticker', {
                title: '<?php echo esc_js(__('Nattevakten Ticker', 'nattevakten')); ?>',
                icon: 'rss',
                category: 'widgets',
                attributes: {
                    limit: { type: 'number', default: 5 },
                    interval: { type: 'number', default: 6000 },
                    autoPlay: { type: 'boolean', default: true },
                    showControls: { type: 'boolean', default: true }
                },
                edit: function(props) {
                    const { attributes, setAttributes } = props;
                    const blockProps = useBlockProps();
                    
                    return e('div', blockProps,
                        e(InspectorControls, {},
                            e(PanelBody, { title: '<?php echo esc_js(__('Ticker innstillinger', 'nattevakten')); ?>' },
                                e(RangeControl, {
                                    label: '<?php echo esc_js(__('Antall nyheter', 'nattevakten')); ?>',
                                    value: attributes.limit,
                                    onChange: (value) => setAttributes({ limit: value }),
                                    min: 1,
                                    max: 20
                                }),
                                e(RangeControl, {
                                    label: '<?php echo esc_js(__('Intervall (ms)', 'nattevakten')); ?>',
                                    value: attributes.interval,
                                    onChange: (value) => setAttributes({ interval: value }),
                                    min: 1000,
                                    max: 30000,
                                    step: 1000
                                }),
                                e(ToggleControl, {
                                    label: '<?php echo esc_js(__('Automatisk avspilling', 'nattevakten')); ?>',
                                    checked: attributes.autoPlay,
                                    onChange: (value) => setAttributes({ autoPlay: value })
                                }),
                                e(ToggleControl, {
                                    label: '<?php echo esc_js(__('Vis kontroller', 'nattevakten')); ?>',
                                    checked: attributes.showControls,
                                    onChange: (value) => setAttributes({ showControls: value })
                                })
                            )
                        ),
                        e('div', {
                            style: {
                                padding: '20px',
                                border: '2px dashed #ccc',
                                borderRadius: '4px',
                                textAlign: 'center',
                                color: '#666'
                            }
                        }, '🕯 <?php echo esc_js(__('Nattevakten Ticker', 'nattevakten')); ?> (<?php echo esc_js(__('Forhåndsvisning i editor', 'nattevakten')); ?>)')
                    );
                },
                save: function() {
                    return null; // Server-side rendering
                }
            });
        })();
        </script>
        <?php
    });
}

// Application Passwords support for REST API
add_filter('wp_is_application_passwords_available', '__return_true');
add_filter('wp_is_application_passwords_available_for_user', function($available, $user) {
    if (user_can($user, 'manage_options')) {
        return true;
    }
    return $available;
}, 10, 2);

// Custom REST API authentication for external integrations
add_filter('rest_authentication_errors', 'nattevakten_rest_auth');
function nattevakten_rest_auth($result) {
    if (!empty($result)) {
        return $result;
    }
    
    // Check for custom API key in header
    $api_key = $_SERVER['HTTP_X_NATTEVAKTEN_API_KEY'] ?? '';
    if (!empty($api_key)) {
        $stored_key = get_option('nattevakten_external_api_key');
        if (!empty($stored_key) && hash_equals($stored_key, $api_key)) {
            return true; // Authenticated
        }
    }
    
    return $result;
}

// Performance optimization: Database query optimization
add_action('pre_get_posts', 'nattevakten_optimize_queries');
function nattevakten_optimize_queries($query) {
    // Don't interfere with admin queries or non-main queries
    if (is_admin() || !$query->is_main_query()) {
        return;
    }
    
    // Add database hints for better performance on large sites
    if (is_multisite() && get_blog_count() > 100) {
        add_filter('posts_request', function($request) {
            // Add SQL hints for better performance
            if (strpos($request, 'SELECT') === 0) {
                $request = str_replace('SELECT', 'SELECT /*+ USE_INDEX */', $request);
            }
            return $request;
        });
    }
}

// Advanced error monitoring with WordPress telemetry
add_action('wp_fatal_error_handler_enabled', 'nattevakten_setup_error_monitoring');
function nattevakten_setup_error_monitoring() {
    if (!defined('WP_DEBUG') || !WP_DEBUG) {
        return;
    }
    
    set_error_handler(function($severity, $message, $file, $line) {
        if (strpos($file, 'nattevakten') !== false) {
            nattevakten_log_error('php_error', 'runtime_error', 
                sprintf('%s in %s:%d', $message, basename($file), $line), 'error');
        }
        
        return false; // Let WordPress handle it
    });
}

// Modern WordPress coding standards compliance
add_action('wp_loaded', 'nattevakten_modern_wp_compliance');
function nattevakten_modern_wp_compliance() {
    // Ensure compatibility with WordPress's modern security features
    
    // Content Security Policy support
    if (function_exists('wp_get_inline_script_tag')) {
        add_filter('wp_inline_script_attributes', function($attributes, $javascript, $handle) {
            if (strpos($handle, 'nattevakten') !== false) {
                $attributes['nonce'] = wp_create_nonce('nattevakten_inline_script');
            }
            return $attributes;
        }, 10, 3);
    }
    
    // Support for WordPress's lazy loading
    add_filter('wp_lazy_loading_enabled', '__return_true');
    
    // Enhanced security headers
    if (function_exists('wp_is_serving_rest_request') && wp_is_serving_rest_request()) {
        add_action('rest_api_init', function() {
            header('X-Robots-Tag: noindex, nofollow, nosnippet, noarchive');
        });
    }
}

/**
 * Multisite support - Enhanced plugin for multisite environments
 */

// Multisite-aware activation/deactivation
add_action('wp_initialize_site', 'nattevakten_new_site_activation');
function nattevakten_new_site_activation($new_site) {
    if (is_plugin_active_for_network(plugin_basename(__FILE__))) {
        switch_to_blog($new_site->blog_id);
        nattevakten_activate_plugin();
        restore_current_blog();
    }
}

// Network admin integration
if (is_multisite()) {
    add_action('network_admin_menu', 'nattevakten_add_network_admin_menu');
    
    function nattevakten_add_network_admin_menu() {
        add_menu_page(
            __('Nattevakten Network', 'nattevakten'),
            '🕯 Nattevakten Network',
            'manage_network_options',
            'nattevakten-network',
            'nattevakten_network_admin_page',
            'dashicons-schedule',
            58
        );
    }
    
    function nattevakten_network_admin_page() {
        if (!current_user_can('manage_network_options')) {
            wp_die(__('Du har ikke tilgang til denne siden.', 'nattevakten'));
        }
        
        $sites = get_sites(['number' => 100]);
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Nattevakten Network Overview', 'nattevakten'); ?></h1>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php echo esc_html__('Site', 'nattevakten'); ?></th>
                        <th><?php echo esc_html__('Status', 'nattevakten'); ?></th>
                        <th><?php echo esc_html__('Last Generated', 'nattevakten'); ?></th>
                        <th><?php echo esc_html__('Actions', 'nattevakten'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sites as $site): ?>
                        <?php
                        switch_to_blog($site->blog_id);
                        $site_name = get_bloginfo('name');
                        $news_file = NATTEVAKTEN_JSON_PATH . 'nattavis.json';
                        $last_generated = file_exists($news_file) ? 
                            human_time_diff(filemtime($news_file), current_time('timestamp')) . __(' ago', 'nattevakten') : 
                            __('Never', 'nattevakten');
                        $api_configured = !empty(get_option('nattevakten_api_key'));
                        restore_current_blog();
                        ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html($site_name); ?></strong><br>
                                <small><?php echo esc_html($site->domain . $site->path); ?></small>
                            </td>
                            <td>
                                <span class="<?php echo $api_configured ? 'status-active' : 'status-inactive'; ?>">
                                    <?php echo $api_configured ? 
                                        esc_html__('Configured', 'nattevakten') : 
                                        esc_html__('Not Configured', 'nattevakten'); ?>
                                </span>
                            </td>
                            <td><?php echo esc_html($last_generated); ?></td>
                            <td>
                                <a href="<?php echo esc_url(get_admin_url($site->blog_id, 'admin.php?page=nattevakten')); ?>" 
                                   class="button button-small">
                                    <?php echo esc_html__('Configure', 'nattevakten'); ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <style>
            .status-active { color: #00a32a; font-weight: bold; }
            .status-inactive { color: #d63638; }
        </style>
        <?php
    }
}
?>