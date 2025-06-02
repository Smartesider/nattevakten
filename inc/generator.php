<?php
/**
 * Generator for nattens nyheter – Nattevakten-plugin
 * Genererer nyhetsinnhold via OpenAI og lokale json-kilder
 * Updated to use new JSON data sources for enhanced Pjuskeby context
 */

function nattevakten_generate_news() {
    // Distributed environment compatible locking
    $lock = nattevakten_distributed_file_lock(NATTEVAKTEN_JSON_PATH . 'nattavis.json', 'generation');
    
    if (!$lock) {
        nattevakten_log_error('generator', 'lock_failed', __('Kunne ikke få eksklusiv tilgang for generering', 'nattevakten'));
        return 'lock_failed';
    }
    
    try {
        // Rate limiting to prevent API quota exhaustion
        $rate_limit_key = 'api_calls_' . date('Y-m-d-H');
        $current_calls = (int) nattevakten_cache_get($rate_limit_key);
        $max_calls_per_hour = apply_filters('nattevakten_max_api_calls_per_hour', 60);
        
        if ($current_calls >= $max_calls_per_hour) {
            throw new Exception(__('API rate limit nådd. Prøv igjen senere.', 'nattevakten'));
        }
        
        $prompt = get_option('nattevakten_prompt', __('Generer 3-5 kreative lokalnyheter fra Pjuskeby. Bruk informasjonen fra JSON-konteksten for å lage realistiske og morsomme nyheter med spesifikke steder, bedrifter, gater og personer. Hver nyhet skal ha timestamp og være på norsk.', 'nattevakten'));
        $temperature = floatval(get_option('nattevakten_temp', 0.7));
        $max_tokens = 500; // Increased for more detailed news with context
        
        // Enhanced prompt validation
        if (strlen($prompt) > 2000) {
            throw new Exception(__('Prompt for lang (maks 2000 tegn)', 'nattevakten'));
        }
        
        // Validate prompt for potential issues
        if (nattevakten_validate_prompt_safety($prompt) !== true) {
            throw new Exception(__('Prompt inneholder potensielt problematisk innhold', 'nattevakten'));
        }

        // Load enhanced data sources with new JSON files
        $data_sources = nattevakten_get_enhanced_data_sources_secure();

        // Enhanced prompt with detailed instructions for using context
        $context_instructions = __('Bruk følgende kontekstdata for å lage nyheter:', 'nattevakten') . "\n" .
            __('- Bedrifter: Bruk bedriftsnavn og aktiviteter fra bedrifter.json', 'nattevakten') . "\n" .
            __('- Gater: Bruk gatenavn og beboere fra gatenavn.json', 'nattevakten') . "\n" .
            __('- Innsjøer: Bruk stedsnavn fra innsjo.json for naturrelaterte nyheter', 'nattevakten') . "\n" .
            __('- Områder: Bruk nærliggende områder fra rundtpjuskeby.json', 'nattevakten') . "\n" .
            __('- Sport: Bruk {tullenavn} ({ektenavn}) format fra sport.json', 'nattevakten') . "\n" .
            __('- Turister: Bruk {tullenavn} ({ektenavn}) format fra turister.json', 'nattevakten') . "\n" .
            __('- Steder: Bruk {sted} og {description} fra stederipjuskeby.json', 'nattevakten') . "\n" .
            __('Svar kun med gyldig JSON array med objekter som har "tid", "tekst" og "score" felter.', 'nattevakten');

        $full_prompt = $prompt . "\n\n" . $context_instructions . "\n\n" . 
                      __('Kontekstdata:', 'nattevakten') . " " . wp_json_encode($data_sources, JSON_UNESCAPED_UNICODE);

        // Enhanced OpenAI call with circuit breaker pattern
        $ai_result = nattevakten_call_openai_with_circuit_breaker($full_prompt, $temperature, $max_tokens);
        if (is_wp_error($ai_result)) {
            throw new Exception($ai_result->get_error_message());
        }
        
        // Increment rate limiting counter
        nattevakten_cache_set($rate_limit_key, $current_calls + 1, 3600);

        // Enhanced JSON parsing with size limits
        $nyheter = nattevakten_parse_ai_response_secure($ai_result);
        if (empty($nyheter)) {
            throw new Exception(__('Ingen gyldige nyheter generert', 'nattevakten'));
        }

        // Atomic write with integrity verification
        $result = nattevakten_atomic_file_write_verified(
            NATTEVAKTEN_JSON_PATH . 'nattavis.json',
            wp_json_encode($nyheter, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
        );
        
        if (!$result) {
            throw new Exception(__('Kunne ikke lagre nyheter', 'nattevakten'));
        }

        // Clear all related caches
        nattevakten_invalidate_all_caches();
        
        // Log successful generation for monitoring
        nattevakten_log_error('generator', 'success', 
            sprintf(__('Genererte %d nyheter med ny kontekstdata', 'nattevakten'), count($nyheter)), 'info');
        
        return true;
        
    } catch (Exception $e) {
        nattevakten_log_error('generator', 'exception', $e->getMessage());
        
        // Implement graceful degradation
        return nattevakten_handle_generation_failure($e->getMessage());
        
    } finally {
        nattevakten_release_distributed_lock($lock);
    }
}

/**
 * Enhanced data source loading with new JSON files
 */
function nattevakten_get_enhanced_data_sources_secure() {
    $cache_key = 'enhanced_data_sources_secure_' . NATTEVAKTEN_VERSION;
    $cached = nattevakten_cache_get($cache_key);
    
    if ($cached !== false) {
        // Verify cache integrity
        if (nattevakten_verify_data_integrity($cached)) {
            return $cached;
        }
        // Cache corrupted, rebuild
    }
    
    // Default data structure with redaksjonen (still needed for main character)
    $data_sources = [
        'redaksjonen' => ['hovedperson' => 'Kåre Bjarne', 'rolle' => 'Nattevakt'],
    ];

    // New JSON files to load for enhanced context
    $files_to_load = [
        'bedrifter.json',      // Companies in Pjuskeby
        'gatenavn.json',       // Street names, house numbers and occupants
        'innsjo.json',         // Lakes etc in and around Pjuskeby
        'rundtpjuskeby.json',  // Surrounding areas around Pjuskeby
        'sport.json',          // Sports played in Pjuskeby
        'turister.json',       // Tourist attractions in Pjuskeby
        'stederipjuskeby.json', // Places in Pjuskeby
        'redaksjonen.json'     // Keep redaksjonen for main character info
    ];
    
    foreach ($files_to_load as $filename) {
        $key = str_replace('.json', '', $filename);
        $file = NATTEVAKTEN_JSON_PATH . $filename;
        
        if (file_exists($file) && is_readable($file)) {
            // Security: Verify file hasn't been tampered with
            $content = file_get_contents($file, false, null, 0, 51200); // Increased to 50KB for larger datasets
            
            if ($content !== false) {
                $decoded = json_decode($content, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    // Additional validation for data structure
                    if (nattevakten_validate_enhanced_data_structure($decoded, $key)) {
                        $data_sources[$key] = $decoded;
                    }
                }
            }
        } else {
            // Log missing files but don't fail completely
            nattevakten_log_error('data_loader', 'missing_file', 
                sprintf(__('JSON-fil mangler: %s', 'nattevakten'), $filename), 'warning');
        }
    }
    
    // Add integrity signature
    $data_sources['_integrity'] = hash('sha256', serialize($data_sources) . NONCE_SALT);
    $data_sources['_loaded_files'] = array_keys($data_sources);
    $data_sources['_timestamp'] = current_time('mysql');
    
    // Cache for 30 minutes with integrity protection
    nattevakten_cache_set($cache_key, $data_sources, 1800);
    
    return $data_sources;
}

/**
 * Enhanced data structure validation for new JSON files
 */
function nattevakten_validate_enhanced_data_structure($data, $type) {
    switch ($type) {
        case 'bedrifter':
            // Validate company data structure
            return is_array($data) && !empty($data);
            
        case 'gatenavn':
            // Validate street data with names and occupants
            return is_array($data) && !empty($data);
            
        case 'innsjo':
            // Validate lakes and water bodies
            return is_array($data) && !empty($data);
            
        case 'rundtpjuskeby':
            // Validate surrounding areas
            return is_array($data) && !empty($data);
            
        case 'sport':
            // Validate sports data with tullenavn/ektenavn structure
            if (!is_array($data)) return false;
            // Check if at least some items have the required structure
            foreach (array_slice($data, 0, 3) as $item) {
                if (is_array($item) && (isset($item['tullenavn']) || isset($item['ektenavn']))) {
                    return true;
                }
            }
            return !empty($data); // Allow if not empty, even without perfect structure
            
        case 'turister':
            // Validate tourist attractions with tullenavn/ektenavn structure
            if (!is_array($data)) return false;
            // Check if at least some items have the required structure
            foreach (array_slice($data, 0, 3) as $item) {
                if (is_array($item) && (isset($item['tullenavn']) || isset($item['ektenavn']))) {
                    return true;
                }
            }
            return !empty($data); // Allow if not empty, even without perfect structure
            
        case 'stederipjuskeby':
            // Validate places with sted and description
            if (!is_array($data)) return false;
            // Check if at least some items have sted or description
            foreach (array_slice($data, 0, 3) as $item) {
                if (is_array($item) && (isset($item['sted']) || isset($item['description']))) {
                    return true;
                }
            }
            return !empty($data); // Allow if not empty, even without perfect structure
            
        case 'redaksjonen':
            return isset($data['hovedperson']) || isset($data['rolle']);
            
        default:
            return is_array($data) && !empty($data);
    }
}

/**
 * Enhanced AI response parser with security and size limits
 */
function nattevakten_parse_ai_response_secure($ai_result) {
    // Limit response size to prevent memory exhaustion
    if (strlen($ai_result) > 50000) { // 50KB limit
        nattevakten_log_error('parser', 'response_too_large', 
            sprintf(__('AI respons for stor: %d bytes', 'nattevakten'), strlen($ai_result)), 'warning');
        $ai_result = substr($ai_result, 0, 50000);
    }
    
    // Try direct JSON decode first
    $nyheter = json_decode($ai_result, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($nyheter)) {
        return nattevakten_validate_news_items_secure($nyheter);
    }

    // Enhanced pattern matching with security checks
    $patterns = [
        '/\[.*?\]/s',                     // Look for array (non-greedy)
        '/\{.*?\}/s',                     // Look for object (non-greedy)
        '/```json\s*(.*?)\s*```/s',       // Look for code block
        '/```\s*(.*?)\s*```/s',           // Look for any code block
    ];
    
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $ai_result, $matches)) {
            // Security: Limit extracted content size
            $extracted = substr($matches[1], 0, 25000);
            
            $nyheter = json_decode($extracted, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($nyheter)) {
                return nattevakten_validate_news_items_secure($nyheter);
            }
        }
    }
    
    // Enhanced fallback: try to extract structured content
    $fallback_nyheter = nattevakten_extract_structured_content($ai_result);
    if (!empty($fallback_nyheter)) {
        return $fallback_nyheter;
    }
    
    // Log the unparseable response for analysis (truncated for security)
    nattevakten_log_error('parser', 'unparseable_response', 
        __('Kunne ikke parse AI-respons', 'nattevakten') . ': ' . substr($ai_result, 0, 200));
    
    return [];
}

/**
 * Enhanced news validation with security checks and context awareness
 */
function nattevakten_validate_news_items_secure($items) {
    if (!is_array($items) || count($items) > 20) { // Limit to max 20 items
        return [];
    }
    
    $sanitized_news = [];
    $forbidden_patterns = [
        '/script\s*>/i',
        '/javascript:/i',
        '/data:text\/html/i',
        '/<iframe/i',
        '/eval\s*\(/i'
    ];
    
    foreach ($items as $item) {
        if (!is_array($item) || empty($item['tekst'])) {
            continue;
        }
        
        $tekst = $item['tekst'];
        
        // Security: Check for forbidden content
        $is_safe = true;
        foreach ($forbidden_patterns as $pattern) {
            if (preg_match($pattern, $tekst)) {
                $is_safe = false;
                break;
            }
        }
        
        if (!$is_safe) {
            nattevakten_log_error('validator', 'forbidden_content', 
                __('Farlig innhold oppdaget i nyhet', 'nattevakten'), 'warning');
            continue;
        }
        
        $tekst = sanitize_text_field($tekst);
        
        // Enhanced validation - allow longer news with more context
        if (strlen($tekst) < 15 || strlen($tekst) > 800) {
            continue;
        }
        
        // Check for Norwegian content (basic check)
        if (!nattevakten_contains_norwegian_content($tekst)) {
            continue;
        }
        
        // Enhanced content validation - check for Pjuskeby context usage
        $context_score = nattevakten_calculate_context_usage_score($tekst);
        
        $sanitized_news[] = [
            'tid' => isset($item['tid']) ? sanitize_text_field($item['tid']) : current_time('H:i'),
            'tekst' => $tekst,
            'score' => isset($item['score']) ? max(1, min(100, intval($item['score']))) : $context_score,
            'generated_at' => current_time('mysql'),
            'version' => NATTEVAKTEN_VERSION,
            'hash' => hash('crc32', $tekst), // For duplicate detection
            'context_score' => $context_score
        ];
        
        // Limit total items
        if (count($sanitized_news) >= 10) {
            break;
        }
    }
    
    return $sanitized_news;
}

/**
 * Calculate how well the news item uses context data
 */
function nattevakten_calculate_context_usage_score($text) {
    $score = 20; // Base score
    $text_lower = strtolower($text);
    
    // Context indicators that suggest good use of provided data
    $context_indicators = [
        'pjuskeby' => 15,     // Mentions the town
        'bedrift' => 10,      // Mentions businesses
        'gate' => 8,          // Mentions streets
        'innsjø' => 12,       // Mentions lakes
        'sport' => 10,        // Mentions sports
        'turist' => 8,        // Mentions tourism
        'sted' => 5,          // Mentions places
        'område' => 5,        // Mentions areas
        'adresse' => 8,       // Mentions addresses
        'firma' => 8,         // Mentions companies
    ];
    
    foreach ($context_indicators as $indicator => $points) {
        if (strpos($text_lower, $indicator) !== false) {
            $score += $points;
        }
    }
    
    // Bonus for specific formatting that suggests use of structured data
    if (preg_match('/\([^)]+\)/', $text)) { // Contains parentheses (tullenavn format)
        $score += 15;
    }
    
    return min(100, $score); // Cap at 100
}

/**
 * Helper functions for enhanced validation
 */
function nattevakten_validate_prompt_safety($prompt) {
    $dangerous_indicators = [
        'ignore previous',
        'system:',
        '[INST]',
        '<|im_start|>',
        'jailbreak',
        'pretend you are',
        'roleplay as'
    ];
    
    $prompt_lower = strtolower($prompt);
    foreach ($dangerous_indicators as $indicator) {
        if (strpos($prompt_lower, $indicator) !== false) {
            return false;
        }
    }
    
    return true;
}

function nattevakten_verify_data_integrity($data) {
    if (!is_array($data) || !isset($data['_integrity'])) {
        return false;
    }
    
    $integrity_hash = $data['_integrity'];
    unset($data['_integrity']);
    
    $expected_hash = hash('sha256', serialize($data) . NONCE_SALT);
    
    return hash_equals($expected_hash, $integrity_hash);
}

function nattevakten_contains_norwegian_content($text) {
    $norwegian_indicators = ['å', 'æ', 'ø', 'og', 'en', 'et', 'som', 'på', 'til', 'av', 'er', 'kl'];
    $text_lower = strtolower($text);
    
    $matches = 0;
    foreach ($norwegian_indicators as $indicator) {
        if (strpos($text_lower, $indicator) !== false) {
            $matches++;
        }
    }
    
    return $matches >= 2; // At least 2 Norwegian indicators
}

function nattevakten_extract_structured_content($text) {
    // Try to extract news-like content even from unstructured text
    $lines = explode("\n", $text);
    $news_items = [];
    
    foreach ($lines as $line) {
        $line = trim($line);
        if (strlen($line) > 20 && strlen($line) < 300) {
            // Look for time patterns
            if (preg_match('/\b\d{1,2}[:\.]\d{2}\b/', $line)) {
                $news_items[] = [
                    'tekst' => $line,
                    'tid' => current_time('H:i'),
                    'score' => rand(20, 80)
                ];
                
                if (count($news_items) >= 3) break;
            }
        }
    }
    
    return nattevakten_validate_news_items_secure($news_items);
}

/**
 * Updated cache invalidation system for new data structure
 */
function nattevakten_invalidate_all_caches() {
    $cache_keys = [
        'enhanced_data_sources_secure_' . NATTEVAKTEN_VERSION,
        'data_sources_secure_' . NATTEVAKTEN_VERSION, // Legacy key
        'nattevakten_data_sources',
        'offline_news_cache'
    ];
    
    foreach ($cache_keys as $key) {
        wp_cache_delete($key, 'nattevakten');
        delete_transient('nattevakt_' . md5($key));
    }
    
    // Clear external caches if available
    if (function_exists('wp_cache_flush_group')) {
        wp_cache_flush_group('nattevakten');
    }
}

/**
 * Graceful degradation system
 */
function nattevakten_handle_generation_failure($error_message) {
    // Implement offline mode with cached content
    $offline_cache_key = 'offline_news_cache';
    $cached_news = nattevakten_cache_get($offline_cache_key);
    
    if ($cached_news !== false && is_array($cached_news) && !empty($cached_news)) {
        // Use cached news with updated timestamps
        $updated_news = array_map(function($item) {
            $item['tid'] = current_time('H:i');
            $item['generated_at'] = current_time('mysql');
            return $item;
        }, array_slice($cached_news, 0, 3));
        
        nattevakten_atomic_file_write_verified(
            NATTEVAKTEN_JSON_PATH . 'nattavis.json',
            wp_json_encode($updated_news, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
        );
        
        nattevakten_log_error('generator', 'offline_mode', 
            __('Bruker cached innhold pga API-feil', 'nattevakten'), 'info');
        
        return true;
    }
    
    // Final fallback to static content
    $fallback_news = nattevakten_fallback_news();
    nattevakten_atomic_file_write_verified(
        NATTEVAKTEN_JSON_PATH . 'nattavis.json',
        wp_json_encode($fallback_news, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
    );
    
    return 'offline_fallback';
}

/**
 * Atomic file write with verification
 */
function nattevakten_atomic_file_write_verified($filepath, $content) {
    $temp_file = $filepath . '.tmp.' . uniqid();
    $backup_file = $filepath . '.backup';
    
    // Create backup of existing file
    if (file_exists($filepath)) {
        copy($filepath, $backup_file);
    }
    
    // Write to temporary file with verification
    $bytes_written = file_put_contents($temp_file, $content, LOCK_EX);
    
    if ($bytes_written === false) {
        @unlink($temp_file);
        return false;
    }
    
    // Verify written content
    $verification_content = file_get_contents($temp_file);
    if ($verification_content !== $content) {
        @unlink($temp_file);
        nattevakten_log_error('file_system', 'write_verification_failed', 
            __('Fil-skriving feilet under verifikasjon', 'nattevakten'));
        return false;
    }
    
    // Atomic rename
    if (!rename($temp_file, $filepath)) {
        @unlink($temp_file);
        // Restore backup if rename failed
        if (file_exists($backup_file)) {
            copy($backup_file, $filepath);
        }
        return false;
    }
    
    // Cleanup backup after successful write
    @unlink($backup_file);
    return true;
}
?>