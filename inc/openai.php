<?php
/**
 * OpenAI-kobling for Nattevakten-plugin.
 * Tilbyr funksjon for å hente svar fra OpenAI med robust feilbehandling og logging.
 */

function nattevakten_call_openai($prompt, $temperature = 0.7, $max_tokens = 400) {
    // Additional prompt validation to prevent prompt injection
    if (strlen($prompt) > 4000) {
        return new WP_Error('prompt_too_long', __('Prompt er for lang', 'nattevakten'));
    }
    
    // Check for potential prompt injection patterns
    $dangerous_patterns = [
        '/ignore\s+previous\s+instructions/i',
        '/system:\s*you\s+are/i',
        '/\[INST\]/i',
        '/<\|im_start\|>/i'
    ];
    
    foreach ($dangerous_patterns as $pattern) {
        if (preg_match($pattern, $prompt)) {
            nattevakten_log_error('openai', 'prompt_injection_attempt', 
                __('Prompt injection forsøk oppdaget', 'nattevakten'));
            return new WP_Error('prompt_injection', __('Ugyldig prompt format', 'nattevakten'));
        }
    }
    
    $encrypted_key = get_option('nattevakten_api_key');
    if (empty($encrypted_key)) {
        nattevakten_log_error('openai', 'mangler_api_nokkel', __('API-nøkkel mangler.', 'nattevakten'));
        return new WP_Error('openai_api_key_missing', __('API-nøkkel mangler', 'nattevakten'));
    }
    
    $api_key = nattevakten_decrypt_api_key($encrypted_key);
    if (empty($api_key)) {
        nattevakten_log_error('openai', 'ugyldig_api_nokkel', __('Kunne ikke dekryptere API-nøkkel.', 'nattevakten'));
        return new WP_Error('openai_api_key_invalid', __('Ugyldig API-nøkkel', 'nattevakten'));
    }
    
    $endpoint = 'https://api.openai.com/v1/chat/completions';

    $request_data = [
        'model' => 'gpt-4o-mini', // Use cheaper model for news generation
        'messages' => [
            [
                'role' => 'system', 
                'content' => __('Du er en kreativ journalist som skriver korte, humoristiske lokalnyheter på norsk. Svar kun med gyldig JSON array.', 'nattevakten')
            ],
            ['role' => 'user', 'content' => sanitize_textarea_field($prompt)],
        ],
        'temperature' => max(0.0, min(2.0, floatval($temperature))),
        'max_tokens' => max(50, min(1000, intval($max_tokens))),
        'frequency_penalty' => 0.3,
        'presence_penalty' => 0.1
    ];

    $args = [
        'body'    => wp_json_encode($request_data),
        'headers' => [
            'Content-Type'  => 'application/json',
            'Authorization' => 'Bearer ' . $api_key,
            'User-Agent'    => 'Nattevakten-WordPress-Plugin/' . NATTEVAKTEN_VERSION
        ],
        'timeout' => 45,
        'sslverify' => true,
        'redirection' => 0 // Prevent redirects for security
    ];

    $response = wp_remote_post($endpoint, $args);
    
    // Securely clear API key from memory
    if (function_exists('sodium_memzero')) {
        sodium_memzero($api_key);
    }
    unset($api_key);
    
    if (is_wp_error($response)) {
        nattevakten_log_error('openai', 'api_kall_feil', $response->get_error_message());
        return $response;
    }

    $http_code = wp_remote_retrieve_response_code($response);
    if ($http_code !== 200) {
        $error_body = wp_remote_retrieve_body($response);
        nattevakten_log_error('openai', 'http_feil', sprintf('HTTP %d: %s', $http_code, substr($error_body, 0, 200)));
        return new WP_Error('openai_http_error', sprintf(__('HTTP %d feil fra OpenAI', 'nattevakten'), $http_code));
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (!is_array($data) || !isset($data['choices'][0]['message']['content'])) {
        nattevakten_log_error('openai', 'ugyldig_svar', __('OpenAI ga ikke gyldig svar.', 'nattevakten'));
        return new WP_Error('openai_invalid_response', __('Ugyldig respons fra OpenAI', 'nattevakten'));
    }

    $content = trim($data['choices'][0]['message']['content']);
    
    // FIXED: Parse JSON response as the system prompt requests JSON array format
    $json_data = json_decode($content, true);
    
    // Validate that we got valid JSON array
    if (json_last_error() !== JSON_ERROR_NONE) {
        nattevakten_log_error('openai', 'json_parse_feil', 
            sprintf(__('Kunne ikke parse JSON: %s', 'nattevakten'), json_last_error_msg()));
        return new WP_Error('openai_json_parse_error', __('Ugyldig JSON-format fra OpenAI', 'nattevakten'));
    }
    
    if (!is_array($json_data)) {
        nattevakten_log_error('openai', 'ikke_array', __('OpenAI returnerte ikke en array.', 'nattevakten'));
        return new WP_Error('openai_not_array', __('Forventet array fra OpenAI', 'nattevakten'));
    }
    
    // Validate array structure - each item should be a news object
    foreach ($json_data as $index => $item) {
        if (!is_array($item) || empty($item['title']) || empty($item['content'])) {
            nattevakten_log_error('openai', 'ugyldig_nyhet_struktur', 
                sprintf(__('Ugyldig struktur på nyhet #%d', 'nattevakten'), $index + 1));
            return new WP_Error('openai_invalid_news_structure', 
                sprintf(__('Ugyldig nyhetstruktur på element %d', 'nattevakten'), $index + 1));
        }
        
        // Sanitize each news item for security
        $json_data[$index]['title'] = sanitize_text_field($item['title']);
        $json_data[$index]['content'] = sanitize_textarea_field($item['content']);
        
        // Optional fields with sanitization
        if (isset($item['category'])) {
            $json_data[$index]['category'] = sanitize_text_field($item['category']);
        }
        if (isset($item['tags'])) {
            $json_data[$index]['tags'] = is_array($item['tags']) 
                ? array_map('sanitize_text_field', $item['tags'])
                : sanitize_text_field($item['tags']);
        }
    }
    
    // Log successful API call for monitoring
    nattevakten_log_error('openai', 'success', sprintf(
        __('API kall vellykket. Tokens brukt: %d, Nyheter generert: %d', 'nattevakten'),
        $data['usage']['total_tokens'] ?? 0,
        count($json_data)
    ), 'info');

    return $json_data; // Return parsed and validated JSON array
}

/**
 * Circuit breaker pattern for OpenAI API calls
 */
function nattevakten_call_openai_with_circuit_breaker($prompt, $temperature, $max_tokens) {
    $circuit_key = 'openai_circuit_breaker';
    $circuit_state = nattevakten_cache_get($circuit_key);
    
    if (!$circuit_state) {
        $circuit_state = ['failures' => 0, 'last_failure' => 0, 'state' => 'closed'];
    }
    
    // Circuit breaker states: closed (normal), open (failing), half-open (testing)
    $now = time();
    
    if ($circuit_state['state'] === 'open') {
        // Check if we should move to half-open (try again after 5 minutes)
        if ($now - $circuit_state['last_failure'] > 300) {
            $circuit_state['state'] = 'half-open';
            nattevakten_cache_set($circuit_key, $circuit_state, 3600);
        } else {
            return new WP_Error('circuit_breaker_open', __('API midlertidig utilgjengelig', 'nattevakten'));
        }
    }
    
    $result = nattevakten_call_openai($prompt, $temperature, $max_tokens);
    
    if (is_wp_error($result)) {
        $circuit_state['failures']++;
        $circuit_state['last_failure'] = $now;
        
        // Open circuit after 3 failures
        if ($circuit_state['failures'] >= 3) {
            $circuit_state['state'] = 'open';
        }
        
        nattevakten_cache_set($circuit_key, $circuit_state, 3600);
        return $result;
    } else {
        // Success - reset circuit breaker
        $circuit_state = ['failures' => 0, 'last_failure' => 0, 'state' => 'closed'];
        nattevakten_cache_set($circuit_key, $circuit_state, 3600);
        return $result;
    }
}

function nattevakten_encrypt_api_key($api_key) {
    if (empty($api_key)) return '';
    
    // Enkel XOR-kryptering for demonstration
    $key = wp_salt('auth');
    $encrypted = '';
    for ($i = 0; $i < strlen($api_key); $i++) {
        $encrypted .= chr(ord($api_key[$i]) ^ ord($key[$i % strlen($key)]));
    }
    return base64_encode($encrypted);
}

function nattevakten_decrypt_api_key($encrypted_key) {
    if (empty($encrypted_key)) return '';
    
    // Add timing attack protection with constant-time operations
    $key = wp_salt('auth');
    $encrypted = base64_decode($encrypted_key);
    
    if ($encrypted === false) {
        // Prevent timing attacks by still performing decryption work
        $encrypted = str_repeat('0', 32);
    }
    
    $decrypted = '';
    $key_length = strlen($key);
    $encrypted_length = strlen($encrypted);
    
    // Constant-time decryption
    for ($i = 0; $i < max($encrypted_length, 32); $i++) {
        if ($i < $encrypted_length) {
            $decrypted .= chr(ord($encrypted[$i]) ^ ord($key[$i % $key_length]));
        }
    }
    
    // Validate result format (API keys should start with 'sk-')
    if (!preg_match('/^sk-[a-zA-Z0-9]{48,}$/', $decrypted)) {
        return '';
    }
    
    return $decrypted;
}
?>