<?php
/**
 * Enhanced REST API endpoints for Nattevakten
 */

add_action('rest_api_init', 'nattevakten_register_enhanced_rest_routes');
function nattevakten_register_enhanced_rest_routes() {
    // Public data endpoint (existing)
    register_rest_route('nattevakten/v1', '/data', [
        'methods' => 'GET',
        'callback' => 'nattevakten_rest_get_data',
        'permission_callback' => '__return_true',
        'args' => [
            'limit' => [
                'default' => 10,
                'validate_callback' => function($param) {
                    return is_numeric($param) && $param > 0 && $param <= 50;
                }
            ]
        ]
    ]);
    
    // Admin-only generation endpoint
    register_rest_route('nattevakten/v1', '/generate', [
        'methods' => 'POST',
        'callback' => 'nattevakten_rest_generate_news',
        'permission_callback' => function() {
            return current_user_can('manage_options');
        }
    ]);
    
    // Status endpoint
    register_rest_route('nattevakten/v1', '/status', [
        'methods' => 'GET',
        'callback' => 'nattevakten_rest_get_status',
        'permission_callback' => function() {
            return current_user_can('manage_options');
        }
    ]);
    
    // Health check endpoint
    register_rest_route('nattevakten/v1', '/health', [
        'methods' => 'GET',
        'callback' => 'nattevakten_health_check',
        'permission_callback' => '__return_true',
        'args' => [
            'detailed' => [
                'default' => false,
                'validate_callback' => function($param) {
                    return is_bool($param) || in_array($param, ['true', 'false', '1', '0']);
                }
            ]
        ]
    ]);
}

function nattevakten_rest_get_data($request) {
    $limit = $request->get_param('limit');
    $json_file = NATTEVAKTEN_JSON_PATH . 'nattavis.json';
    
    if (!file_exists($json_file)) {
        return new WP_REST_Response([
            'error' => __('Ingen data funnet', 'nattevakten'),
            'fallback' => nattevakten_fallback_news()
        ], 404);
    }
    
    $content = file_get_contents($json_file, false, null, 0, 50000); // Max 50KB
    $data = json_decode($content, true);
    
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
        return new WP_REST_Response([
            'error' => __('Ugyldig data', 'nattevakten'),
            'fallback' => nattevakten_fallback_news()
        ], 500);
    }
    
    // Apply limit and sanitize
    $limited_data = array_slice($data, 0, $limit);
    $sanitized_data = array_map(function($item) {
        return [
            'tid' => sanitize_text_field($item['tid'] ?? ''),
            'tekst' => sanitize_text_field($item['tekst'] ?? ''),
            'score' => isset($item['score']) ? intval($item['score']) : 0
        ];
    }, $limited_data);
    
    return new WP_REST_Response($sanitized_data, 200);
}

function nattevakten_rest_generate_news($request) {
    if (!function_exists('nattevakten_generate_news')) {
        return new WP_REST_Response([
            'error' => __('Generator ikke tilgjengelig', 'nattevakten')
        ], 503);
    }
    
    $result = nattevakten_generate_news();
    
    if ($result === true) {
        return new WP_REST_Response([
            'success' => true,
            'message' => __('Nyheter generert', 'nattevakten'),
            'timestamp' => current_time('mysql')
        ], 200);
    } else {
        return new WP_REST_Response([
            'success' => false,
            'error' => is_string($result) ? $result : __('Ukjent feil', 'nattevakten')
        ], 500);
    }
}

function nattevakten_rest_get_status($request) {
    $status = [
        'version' => NATTEVAKTEN_VERSION,
        'wordpress_version' => get_bloginfo('version'),
        'php_version' => PHP_VERSION,
        'configuration' => [
            'api_key_set' => !empty(get_option('nattevakten_api_key')),
            'prompt_set' => !empty(get_option('nattevakten_prompt')),
            'temperature' => floatval(get_option('nattevakten_temp', 0.7))
        ],
        'directories' => [
            'json_writable' => is_writable(NATTEVAKTEN_JSON_PATH),
            'media_writable' => is_writable(NATTEVAKTEN_MEDIA_PATH)
        ],
        'last_generation' => null
    ];
    
    // Check last generation time
    $news_file = NATTEVAKTEN_JSON_PATH . 'nattavis.json';
    if (file_exists($news_file)) {
        $status['last_generation'] = date('Y-m-d H:i:s', filemtime($news_file));
    }
    
    // Check for recent errors
    if (function_exists('nattevakten_get_recent_errors')) {
        $recent_errors = nattevakten_get_recent_errors(5);
        $status['recent_errors_count'] = count($recent_errors);
    }
    
    return new WP_REST_Response($status, 200);
}

function nattevakten_health_check($request) {
    $detailed = $request->get_param('detailed');
    $detailed = in_array($detailed, [true, 'true', '1'], true);
    
    $health = [
        'status' => 'healthy',
        'timestamp' => current_time('c'),
        'version' => NATTEVAKTEN_VERSION
    ];
    
    if ($detailed && current_user_can('manage_options')) {
        $health['details'] = [
            'php_version' => PHP_VERSION,
            'wordpress_version' => get_bloginfo('version'),
            'multisite' => is_multisite(),
            'directories_writable' => [
                'json' => is_writable(NATTEVAKTEN_JSON_PATH),
                'media' => is_writable(NATTEVAKTEN_MEDIA_PATH)
            ],
            'api_configured' => !empty(get_option('nattevakten_api_key')),
            'last_generation' => null
        ];
        
        $news_file = NATTEVAKTEN_JSON_PATH . 'nattavis.json';
        if (file_exists($news_file)) {
            $health['details']['last_generation'] = date('c', filemtime($news_file));
        }
        
        // Quick error count
        $recent_errors = nattevakten_get_recent_errors(10);
        $health['details']['recent_errors'] = count($recent_errors);
        
        // Determine overall health status
        if (!$health['details']['directories_writable']['json'] || 
            !$health['details']['api_configured']) {
            $health['status'] = 'unhealthy';
        } elseif ($health['details']['recent_errors'] > 5) {
            $health['status'] = 'degraded';
        }
    }
    
    $http_status = ($health['status'] === 'healthy') ? 200 : 
                   (($health['status'] === 'degraded') ? 200 : 503);
    
    return new WP_REST_Response($health, $http_status);
}
?>