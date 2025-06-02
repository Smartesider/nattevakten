<?php
/**
 * Enhanced module verification for Nattevakten
 * Finner manglende filer eller kritiske avvik i ny JSON-struktur
 * Production-ready with comprehensive validation and monitoring
 */

function nattevakten_verify_module_integrity() {
    // Core PHP modules that must exist for plugin functionality
    $php_moduler = [
        ['fil' => 'generator.php',              'kritisk' => true,  'beskrivelse' => 'AI news generation engine'],
        ['fil' => 'logger.php',                 'kritisk' => true,  'beskrivelse' => 'Error logging and monitoring'],
        ['fil' => 'admin-panel.php',            'kritisk' => true,  'beskrivelse' => 'WordPress admin interface'],
        ['fil' => 'nattevakten.php',            'kritisk' => true,  'beskrivelse' => 'Main admin functionality'],
        ['fil' => 'openai.php',                 'kritisk' => true,  'beskrivelse' => 'OpenAI API integration'],
        ['fil' => 'fix.php',                    'kritisk' => true,  'beskrivelse' => 'Auto-repair system'],
        ['fil' => 'integrity.php',              'kritisk' => true,  'beskrivelse' => 'Data integrity verification'],
        ['fil' => 'verify.php',                 'kritisk' => true,  'beskrivelse' => 'Module verification system'],
        ['fil' => 'security.php',               'kritisk' => true,  'beskrivelse' => 'Security and access control'],
        ['fil' => 'fallback.php',               'kritisk' => false, 'beskrivelse' => 'Fallback content system'],
        ['fil' => 'backup.php',                 'kritisk' => false, 'beskrivelse' => 'Backup functionality'],
        ['fil' => 'shortcode.php',              'kritisk' => false, 'beskrivelse' => 'Frontend display'],
        ['fil' => 'rest-api.php',               'kritisk' => false, 'beskrivelse' => 'REST API endpoints'],
        ['fil' => 'caching.php',                'kritisk' => false, 'beskrivelse' => 'Performance caching'],
        ['fil' => 'wordpress-integration.php',  'kritisk' => false, 'beskrivelse' => 'WordPress compatibility']
    ];

    // New enhanced JSON data files for Pjuskeby context
    $json_files = [
        [
            'fil' => 'bedrifter.json',      
            'kritisk' => true,  
            'beskrivelse' => 'Bedrifter og næringsliv i Pjuskeby',
            'min_items' => 3,
            'required_fields' => ['navn']
        ],
        [
            'fil' => 'gatenavn.json',       
            'kritisk' => true,  
            'beskrivelse' => 'Gatenavn, husnumre og beboere',
            'min_items' => 4,
            'required_fields' => ['gate']
        ],
        [
            'fil' => 'redaksjonen.json',    
            'kritisk' => true,  
            'beskrivelse' => 'Redaksjonsinformasjon og hovedperson',
            'min_items' => 0,
            'required_fields' => ['hovedperson']
        ],
        [
            'fil' => 'innsjo.json',         
            'kritisk' => false, 
            'beskrivelse' => 'Innsjøer og vannområder rundt Pjuskeby',
            'min_items' => 2,
            'required_fields' => ['navn']
        ],
        [
            'fil' => 'rundtpjuskeby.json',  
            'kritisk' => false, 
            'beskrivelse' => 'Områder og nabobygder rundt Pjuskeby',
            'min_items' => 2,
            'required_fields' => ['navn']
        ],
        [
            'fil' => 'sport.json',          
            'kritisk' => false, 
            'beskrivelse' => 'Sportsaktiviteter og konkurranser',
            'min_items' => 2,
            'required_fields' => ['tullenavn', 'ektenavn']
        ],
        [
            'fil' => 'turister.json',       
            'kritisk' => false, 
            'beskrivelse' => 'Turistattraksjoner og severdigheter',
            'min_items' => 2,
            'required_fields' => ['tullenavn', 'ektenavn']
        ],
        [
            'fil' => 'stederipjuskeby.json', 
            'kritisk' => false, 
            'beskrivelse' => 'Viktige steder og lokasjoner i Pjuskeby',
            'min_items' => 3,
            'required_fields' => ['sted']
        ],
        [
            'fil' => 'nattavis.json',       
            'kritisk' => false, 
            'beskrivelse' => 'Genererte nyheter (kan være tom)',
            'min_items' => 0,
            'required_fields' => []
        ]
    ];

    $mangler = [];

    // Verify PHP module files
    foreach ($php_moduler as $modul) {
        $path = NATTEVAKTEN_PATH . 'inc/' . $modul['fil'];
        $module_issues = nattevakten_verify_php_module($path, $modul);
        if (!empty($module_issues)) {
            $mangler = array_merge($mangler, $module_issues);
        }
    }

    // Verify JSON data files
    foreach ($json_files as $json_fil) {
        $path = NATTEVAKTEN_JSON_PATH . $json_fil['fil'];
        $json_issues = nattevakten_verify_json_file($path, $json_fil);
        if (!empty($json_issues)) {
            $mangler = array_merge($mangler, $json_issues);
        }
    }

    // Check for deprecated files that should be removed
    $deprecated_issues = nattevakten_check_deprecated_files();
    $mangler = array_merge($mangler, $deprecated_issues);

    // Perform comprehensive system checks
    $system_checks = nattevakten_perform_system_integrity_checks();
    $mangler = array_merge($mangler, $system_checks);

    // Additional security and performance checks
    $security_checks = nattevakten_perform_security_checks();
    $mangler = array_merge($mangler, $security_checks);

    return $mangler;
}

/**
 * Verify individual PHP module files
 */
function nattevakten_verify_php_module($path, $module_info) {
    $issues = [];
    $filename = $module_info['fil'];
    
    if (!file_exists($path)) {
        $issues[] = [
            'fil' => $filename,
            'type' => 'php_modul',
            'kritisk' => $module_info['kritisk'],
            'status' => 'mangler',
            'path' => $path,
            'beskrivelse' => $module_info['beskrivelse'] . ' - fil mangler',
            'fix_suggestion' => 'Installer pluginen på nytt eller last opp manglende fil'
        ];
    } elseif (!is_readable($path)) {
        $issues[] = [
            'fil' => $filename,
            'type' => 'php_modul',
            'kritisk' => true, // Always critical if unreadable
            'status' => 'ikke_lesbar',
            'path' => $path,
            'beskrivelse' => $module_info['beskrivelse'] . ' - kan ikke leses',
            'fix_suggestion' => 'Sjekk filtillatelser (chmod 644)'
        ];
    } else {
        // Additional PHP file validation
        $php_issues = nattevakten_validate_php_file($path, $filename);
        if (!empty($php_issues)) {
            $issues = array_merge($issues, $php_issues);
        }
    }
    
    return $issues;
}

/**
 * Verify individual JSON data files
 */
function nattevakten_verify_json_file($path, $file_info) {
    $issues = [];
    $filename = $file_info['fil'];
    
    if (!file_exists($path)) {
        $issues[] = [
            'fil' => $filename,
            'type' => 'json_data',
            'kritisk' => $file_info['kritisk'],
            'status' => 'mangler',
            'path' => $path,
            'beskrivelse' => $file_info['beskrivelse'] . ' - fil mangler',
            'fix_suggestion' => 'Kjør auto-fiks for å opprette standardfil'
        ];
    } elseif (!is_readable($path)) {
        $issues[] = [
            'fil' => $filename,
            'type' => 'json_data',
            'kritisk' => true, // Always critical if unreadable
            'status' => 'ikke_lesbar',
            'path' => $path,
            'beskrivelse' => $file_info['beskrivelse'] . ' - kan ikke leses',
            'fix_suggestion' => 'Sjekk filtillatelser (chmod 644)'
        ];
    } else {
        // Validate JSON structure and content
        $validation = nattevakten_validate_json_content_advanced($path, $file_info);
        if (!$validation['valid']) {
            $issues[] = [
                'fil' => $filename,
                'type' => 'json_data',
                'kritisk' => $file_info['kritisk'],
                'status' => 'ugyldig_json',
                'path' => $path,
                'beskrivelse' => $file_info['beskrivelse'] . ' - ' . $validation['error'],
                'error_details' => $validation['error'],
                'fix_suggestion' => $validation['fix_suggestion'] ?? 'Kjør auto-fiks for å reparere'
            ];
        }
    }
    
    return $issues;
}

/**
 * Advanced JSON content validation with detailed error reporting
 */
function nattevakten_validate_json_content_advanced($filepath, $file_info) {
    $content = file_get_contents($filepath, false, null, 0, 1048576); // Max 1MB
    if ($content === false) {
        return [
            'valid' => false, 
            'error' => __('Kunne ikke lese fil', 'nattevakten'),
            'fix_suggestion' => __('Sjekk filtillatelser', 'nattevakten')
        ];
    }

    // Check if file is empty (OK for some files like nattavis.json)
    if (trim($content) === '') {
        if ($file_info['fil'] === 'nattavis.json') {
            return ['valid' => true, 'error' => null]; // Empty is OK for generated content
        }
        return [
            'valid' => false, 
            'error' => __('Filen er tom', 'nattevakten'),
            'fix_suggestion' => __('Kjør auto-fiks for å populere med standarddata', 'nattevakten')
        ];
    }

    // Parse JSON with detailed error handling
    $data = json_decode($content, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return [
            'valid' => false, 
            'error' => __('Ugyldig JSON: ', 'nattevakten') . json_last_error_msg(),
            'fix_suggestion' => __('Valider JSON-syntax eller kjør auto-fiks', 'nattevakten')
        ];
    }

    $filename = basename($filepath);
    
    // Specific validation for each file type with detailed checking
    switch ($filename) {
        case 'bedrifter.json':
            return nattevakten_validate_bedrifter_advanced($data, $file_info);
            
        case 'gatenavn.json':
            return nattevakten_validate_gatenavn_advanced($data, $file_info);
            
        case 'sport.json':
        case 'turister.json':
            return nattevakten_validate_special_format_advanced($data, $file_info, $filename);
            
        case 'stederipjuskeby.json':
            return nattevakten_validate_steder_advanced($data, $file_info);
            
        case 'redaksjonen.json':
            return nattevakten_validate_redaksjonen_advanced($data, $file_info);
            
        case 'innsjo.json':
        case 'rundtpjuskeby.json':
            return nattevakten_validate_generic_array_advanced($data, $file_info);
            
        case 'nattavis.json':
            return nattevakten_validate_nattavis_advanced($data, $file_info);
            
        default:
            return ['valid' => true, 'error' => null]; // Unknown files pass
    }
}

/**
 * Advanced validation functions for specific file types
 */
function nattevakten_validate_bedrifter_advanced($data, $file_info) {
    if (!is_array($data)) {
        return [
            'valid' => false, 
            'error' => __('Bedrifter må være en array', 'nattevakten'),
            'fix_suggestion' => __('Konverter til array-format', 'nattevakten')
        ];
    }
    
    if (count($data) < $file_info['min_items']) {
        return [
            'valid' => false, 
            'error' => sprintf(__('Må ha minst %d bedrifter, fant %d', 'nattevakten'), $file_info['min_items'], count($data)),
            'fix_suggestion' => __('Legg til flere bedrifter eller kjør auto-fiks', 'nattevakten')
        ];
    }
    
    foreach ($data as $index => $bedrift) {
        if (!is_array($bedrift)) {
            return [
                'valid' => false, 
                'error' => sprintf(__('Bedrift #%d må være et objekt', 'nattevakten'), $index + 1),
                'fix_suggestion' => __('Korriger datastrukturen', 'nattevakten')
            ];
        }
        
        if (empty($bedrift['navn'])) {
            return [
                'valid' => false, 
                'error' => sprintf(__('Bedrift #%d mangler navn', 'nattevakten'), $index + 1),
                'fix_suggestion' => __('Legg til navn-felt for alle bedrifter', 'nattevakten')
            ];
        }
        
        // Additional quality checks
        if (isset($bedrift['ansatte']) && (!is_numeric($bedrift['ansatte']) || $bedrift['ansatte'] < 0)) {
            return [
                'valid' => false, 
                'error' => sprintf(__('Bedrift #%d: ansatte må være et positivt tall', 'nattevakten'), $index + 1),
                'fix_suggestion' => __('Korriger ansatte-feltet', 'nattevakten')
            ];
        }
    }
    
    return ['valid' => true, 'error' => null];
}

function nattevakten_validate_gatenavn_advanced($data, $file_info) {
    if (!is_array($data)) {
        return [
            'valid' => false, 
            'error' => __('Gatenavn må være en array', 'nattevakten'),
            'fix_suggestion' => __('Konverter til array-format', 'nattevakten')
        ];
    }
    
    if (count($data) < $file_info['min_items']) {
        return [
            'valid' => false, 
            'error' => sprintf(__('Må ha minst %d gateadresser, fant %d', 'nattevakten'), $file_info['min_items'], count($data)),
            'fix_suggestion' => __('Legg til flere adresser eller kjør auto-fiks', 'nattevakten')
        ];
    }
    
    $unique_addresses = [];
    foreach ($data as $index => $gate) {
        if (!is_array($gate)) {
            return [
                'valid' => false, 
                'error' => sprintf(__('Gate #%d må være et objekt', 'nattevakten'), $index + 1),
                'fix_suggestion' => __('Korriger datastrukturen', 'nattevakten')
            ];
        }
        
        if (empty($gate['gate'])) {
            return [
                'valid' => false, 
                'error' => sprintf(__('Gate #%d mangler gatenavn', 'nattevakten'), $index + 1),
                'fix_suggestion' => __('Legg til gate-felt', 'nattevakten')
            ];
        }
        
        // Check for duplicate addresses
        $address_key = $gate['gate'] . '-' . ($gate['nummer'] ?? '');
        if (in_array($address_key, $unique_addresses)) {
            return [
                'valid' => false, 
                'error' => sprintf(__('Duplikat adresse: %s %s', 'nattevakten'), $gate['gate'], $gate['nummer'] ?? ''),
                'fix_suggestion' => __('Fjern duplikate adresser', 'nattevakten')
            ];
        }
        $unique_addresses[] = $address_key;
        
        // Validate house number format
        if (isset($gate['nummer']) && !preg_match('/^\d+[a-zA-Z]?$/', $gate['nummer'])) {
            return [
                'valid' => false, 
                'error' => sprintf(__('Gate #%d: ugyldig husnummer format (%s)', 'nattevakten'), $index + 1, $gate['nummer']),
                'fix_suggestion' => __('Bruk format: tall eller tall+bokstav (f.eks. "12" eller "12A")', 'nattevakten')
            ];
        }
    }
    
    return ['valid' => true, 'error' => null];
}

function nattevakten_validate_special_format_advanced($data, $file_info, $filename) {
    if (!is_array($data)) {
        return [
            'valid' => false, 
            'error' => sprintf(__('%s må være en array', 'nattevakten'), ucfirst(str_replace('.json', '', $filename))),
            'fix_suggestion' => __('Konverter til array-format', 'nattevakten')
        ];
    }
    
    if (count($data) < $file_info['min_items']) {
        return [
            'valid' => false, 
            'error' => sprintf(__('Må ha minst %d elementer, fant %d', 'nattevakten'), $file_info['min_items'], count($data)),
            'fix_suggestion' => __('Legg til flere elementer eller kjør auto-fiks', 'nattevakten')
        ];
    }
    
    foreach ($data as $index => $item) {
        if (!is_array($item)) {
            return [
                'valid' => false, 
                'error' => sprintf(__('Element #%d må være et objekt', 'nattevakten'), $index + 1),
                'fix_suggestion' => __('Korriger datastrukturen', 'nattevakten')
            ];
        }
        
        if (empty($item['tullenavn']) && empty($item['ektenavn'])) {
            return [
                'valid' => false, 
                'error' => sprintf(__('Element #%d må ha tullenavn eller ektenavn', 'nattevakten'), $index + 1),
                'fix_suggestion' => __('Legg til tullenavn og/eller ektenavn', 'nattevakten')
            ];
        }
        
        // Quality check: both fields should ideally be present
        if (empty($item['tullenavn']) || empty($item['ektenavn'])) {
            // This is a warning, not an error, but we log it
            nattevakten_log_error('verify_data_quality', 'incomplete_special_format', 
                sprintf(__('Element #%d i %s mangler tullenavn eller ektenavn', 'nattevakten'), $index + 1, $filename), 'warning');
        }
    }
    
    return ['valid' => true, 'error' => null];
}

function nattevakten_validate_steder_advanced($data, $file_info) {
    if (!is_array($data)) {
        return [
            'valid' => false, 
            'error' => __('Steder må være en array', 'nattevakten'),
            'fix_suggestion' => __('Konverter til array-format', 'nattevakten')
        ];
    }
    
    if (count($data) < $file_info['min_items']) {
        return [
            'valid' => false, 
            'error' => sprintf(__('Må ha minst %d steder, fant %d', 'nattevakten'), $file_info['min_items'], count($data)),
            'fix_suggestion' => __('Legg til flere steder eller kjør auto-fiks', 'nattevakten')
        ];
    }
    
    $unique_places = [];
    foreach ($data as $index => $sted) {
        if (!is_array($sted)) {
            return [
                'valid' => false, 
                'error' => sprintf(__('Sted #%d må være et objekt', 'nattevakten'), $index + 1),
                'fix_suggestion' => __('Korriger datastrukturen', 'nattevakten')
            ];
        }
        
        if (empty($sted['sted'])) {
            return [
                'valid' => false, 
                'error' => sprintf(__('Sted #%d mangler stedsnavn', 'nattevakten'), $index + 1),
                'fix_suggestion' => __('Legg til sted-felt', 'nattevakten')
            ];
        }
        
        // Check for duplicate places
        if (in_array($sted['sted'], $unique_places)) {
            return [
                'valid' => false, 
                'error' => sprintf(__('Duplikat sted: %s', 'nattevakten'), $sted['sted']),
                'fix_suggestion' => __('Fjern duplikate steder', 'nattevakten')
            ];
        }
        $unique_places[] = $sted['sted'];
        
        // Validate aktiviteter field if present
        if (isset($sted['aktiviteter']) && !is_array($sted['aktiviteter'])) {
            return [
                'valid' => false, 
                'error' => sprintf(__('Sted #%d: aktiviteter må være en array', 'nattevakten'), $index + 1),
                'fix_suggestion' => __('Konverter aktiviteter til array-format', 'nattevakten')
            ];
        }
    }
    
    return ['valid' => true, 'error' => null];
}

function nattevakten_validate_redaksjonen_advanced($data, $file_info) {
    if (!is_array($data)) {
        return [
            'valid' => false, 
            'error' => __('Redaksjonen må være et objekt', 'nattevakten'),
            'fix_suggestion' => __('Konverter til objekt-format', 'nattevakten')
        ];
    }
    
    if (empty($data['hovedperson'])) {
        return [
            'valid' => false, 
            'error' => __('Redaksjonen må ha hovedperson', 'nattevakten'),
            'fix_suggestion' => __('Legg til hovedperson-felt', 'nattevakten')
        ];
    }
    
    // Quality checks for recommended fields
    $recommended_fields = ['rolle', 'beskrivelse', 'arbeidssted'];
    $missing_recommended = [];
    foreach ($recommended_fields as $field) {
        if (empty($data[$field])) {
            $missing_recommended[] = $field;
        }
    }
    
    if (!empty($missing_recommended)) {
        nattevakten_log_error('verify_data_quality', 'missing_recommended_fields', 
            sprintf(__('Redaksjonen mangler anbefalte felter: %s', 'nattevakten'), implode(', ', $missing_recommended)), 'info');
    }
    
    return ['valid' => true, 'error' => null];
}

function nattevakten_validate_generic_array_advanced($data, $file_info) {
    if (!is_array($data)) {
        return [
            'valid' => false, 
            'error' => sprintf(__('%s må være en array', 'nattevakten'), str_replace('.json', '', $file_info['fil'])),
            'fix_suggestion' => __('Konverter til array-format', 'nattevakten')
        ];
    }
    
    if (count($data) < $file_info['min_items']) {
        return [
            'valid' => false, 
            'error' => sprintf(__('Må ha minst %d elementer, fant %d', 'nattevakten'), $file_info['min_items'], count($data)),
            'fix_suggestion' => __('Legg til flere elementer eller kjør auto-fiks', 'nattevakten')
        ];
    }
    
    foreach ($data as $index => $item) {
        if (!is_array($item)) {
            return [
                'valid' => false, 
                'error' => sprintf(__('Element #%d må være et objekt', 'nattevakten'), $index + 1),
                'fix_suggestion' => __('Korriger datastrukturen', 'nattevakten')
            ];
        }
        
        if (empty($item['navn'])) {
            return [
                'valid' => false, 
                'error' => sprintf(__('Element #%d må ha navn', 'nattevakten'), $index + 1),
                'fix_suggestion' => __('Legg til navn-felt', 'nattevakten')
            ];
        }
    }
    
    return ['valid' => true, 'error' => null];
}

function nattevakten_validate_nattavis_advanced($data, $file_info) {
    if (!is_array($data)) {
        return [
            'valid' => false, 
            'error' => __('Nattavis må være en array', 'nattevakten'),
            'fix_suggestion' => __('Konverter til array-format', 'nattevakten')
        ];
    }
    
    // Empty is OK for nattavis.json (generated content)
    if (empty($data)) {
        return ['valid' => true, 'error' => null];
    }
    
    foreach ($data as $index => $nyhet) {
        if (!is_array($nyhet)) {
            return [
                'valid' => false, 
                'error' => sprintf(__('Nyhet #%d må være et objekt', 'nattevakten'), $index + 1),
                'fix_suggestion' => __('Korriger datastrukturen', 'nattevakten')
            ];
        }
        
        if (empty($nyhet['tekst'])) {
            return [
                'valid' => false, 
                'error' => sprintf(__('Nyhet #%d må ha tekst', 'nattevakten'), $index + 1),
                'fix_suggestion' => __('Legg til tekst-felt', 'nattevakten')
            ];
        }
        
        // Validate score if present
        if (isset($nyhet['score']) && (!is_numeric($nyhet['score']) || $nyhet['score'] < 0 || $nyhet['score'] > 100)) {
            return [
                'valid' => false, 
                'error' => sprintf(__('Nyhet #%d: score må være mellom 0 og 100', 'nattevakten'), $index + 1),
                'fix_suggestion' => __('Korriger score-verdien', 'nattevakten')
            ];
        }
        
        // Check for suspiciously short or long content
        $tekst_length = strlen($nyhet['tekst']);
        if ($tekst_length < 10) {
            nattevakten_log_error('verify_data_quality', 'short_news_content', 
                sprintf(__('Nyhet #%d er veldig kort (%d tegn)', 'nattevakten'), $index + 1, $tekst_length), 'warning');
        } elseif ($tekst_length > 1000) {
            nattevakten_log_error('verify_data_quality', 'long_news_content', 
                sprintf(__('Nyhet #%d er veldig lang (%d tegn)', 'nattevakten'), $index + 1, $tekst_length), 'warning');
        }
    }
    
    return ['valid' => true, 'error' => null];
}

/**
 * Validate PHP files for basic syntax and structure
 */
function nattevakten_validate_php_file($filepath, $filename) {
    $issues = [];
    
    // Check file size
    $size = filesize($filepath);
    if ($size === 0) {
        $issues[] = [
            'fil' => $filename,
            'type' => 'php_modul',
            'kritisk' => true,
            'status' => 'tom_fil',
            'path' => $filepath,
            'beskrivelse' => 'PHP-fil er tom',
            'fix_suggestion' => 'Gjeninstaller pluginen'
        ];
        return $issues;
    }
    
    // Check for PHP opening tag
    $content = file_get_contents($filepath, false, null, 0, 100);
    if (strpos($content, '<?php') !== 0) {
        $issues[] = [
            'fil' => $filename,
            'type' => 'php_modul',
            'kritisk' => true,
            'status' => 'ugyldig_php',
            'path' => $filepath,
            'beskrivelse' => 'Fil mangler gyldig PHP opening tag',
            'fix_suggestion' => 'Sjekk at filen starter med <?php'
        ];
    }
    
    // Basic syntax check (if php -l is available)
    if (function_exists('exec')) {
        $output = [];
        $return_code = 0;
        exec("php -l " . escapeshellarg($filepath) . " 2>&1", $output, $return_code);
        
        if ($return_code !== 0) {
            $issues[] = [
                'fil' => $filename,
                'type' => 'php_modul',
                'kritisk' => true,
                'status' => 'syntax_error',
                'path' => $filepath,
                'beskrivelse' => 'PHP syntax error: ' . implode(' ', $output),
                'fix_suggestion' => 'Reparer PHP syntax eller gjeninstaller pluginen'
            ];
        }
    }
    
    return $issues;
}

/**
 * Check for deprecated files that should be removed
 */
function nattevakten_check_deprecated_files() {
    $issues = [];
    $deprecated_files = [
        'pjuskeby.json' => __('Erstattet av nye separate JSON-filer', 'nattevakten'),
        'land.json' => __('Ikke lenger brukt i ny struktur', 'nattevakten'),
        'organisasjoner.json' => __('Integrert i bedrifter.json', 'nattevakten')
    ];
    
    foreach ($deprecated_files as $filename => $reason) {
        $path = NATTEVAKTEN_JSON_PATH . $filename;
        if (file_exists($path)) {
            $issues[] = [
                'fil' => $filename,
                'type' => 'deprecated',
                'kritisk' => false,
                'status' => 'foreldet',
                'path' => $path,
                'beskrivelse' => 'Foreldet fil: ' . $reason,
                'fix_suggestion' => 'Slett filen (backup vil bli laget automatisk)'
            ];
        }
    }
    
    return $issues;
}

/**
 * Comprehensive system integrity checks
 */
function nattevakten_perform_system_integrity_checks() {
    $issues = [];
    
    // Check WordPress version compatibility
    $wp_version = get_bloginfo('version');
    if (version_compare($wp_version, '5.0', '<')) {
        $issues[] = [
            'fil' => 'WordPress Version',
            'type' => 'system',
            'kritisk' => true,
            'status' => 'for_gammel',
            'path' => '',
            'beskrivelse' => sprintf(__('WordPress %s er for gammel (krever 5.0+)', 'nattevakten'), $wp_version),
            'fix_suggestion' => __('Oppgrader WordPress til nyeste versjon', 'nattevakten')
        ];
    }

    // Check PHP version compatibility
    if (version_compare(PHP_VERSION, '7.4', '<')) {
        $issues[] = [
            'fil' => 'PHP Version',
            'type' => 'system',
            'kritisk' => true,
            'status' => 'for_gammel',
            'path' => '',
            'beskrivelse' => sprintf(__('PHP %s er for gammel (krever 7.4+)', 'nattevakten'), PHP_VERSION),
            'fix_suggestion' => __('Oppgrader PHP til versjon 7.4 eller nyere', 'nattevakten')
        ];
    }

    // Check required PHP extensions
    $required_extensions = [
        'json' => __('JSON parsing og generering', 'nattevakten'),
        'curl' => __('HTTP requests til OpenAI API', 'nattevakten'),
        'openssl' => __('Sikker kommunikasjon', 'nattevakten'),
        'mbstring' => __('Multibyte string håndtering', 'nattevakten')
    ];
    
    foreach ($required_extensions as $ext => $purpose) {
        if (!extension_loaded($ext)) {
            $issues[] = [
                'fil' => "PHP Extension: $ext",
                'type' => 'system',
                'kritisk' => true,
                'status' => 'mangler',
                'path' => '',
                'beskrivelse' => sprintf(__('Påkrevd PHP-utvidelse mangler: %s (%s)', 'nattevakten'), $ext, $purpose),
                'fix_suggestion' => sprintf(__('Installer PHP-%s utvidelsen', 'nattevakten'), $ext)
            ];
        }
    }

    // Check directory permissions
    $required_dirs = [
        NATTEVAKTEN_JSON_PATH => __('JSON data filer', 'nattevakten'),
        NATTEVAKTEN_MEDIA_PATH => __('Media og cache filer', 'nattevakten')
    ];

    foreach ($required_dirs as $dir => $purpose) {
        if (!file_exists($dir)) {
            $issues[] = [
                'fil' => basename($dir),
                'type' => 'directory',
                'kritisk' => true,
                'status' => 'mangler',
                'path' => $dir,
                'beskrivelse' => sprintf(__('Påkrevd katalog mangler: %s (%s)', 'nattevakten'), $dir, $purpose),
                'fix_suggestion' => __('Opprett katalog med korrekte tillatelser', 'nattevakten')
            ];
        } elseif (!is_writable($dir)) {
            $issues[] = [
                'fil' => basename($dir),
                'type' => 'directory',
                'kritisk' => true,
                'status' => 'ikke_skrivbar',
                'path' => $dir,
                'beskrivelse' => sprintf(__('Katalog er ikke skrivbar: %s (%s)', 'nattevakten'), $dir, $purpose),
                'fix_suggestion' => __('Endre tillatelser til 755 eller 775', 'nattevakten')
            ];
        }
    }

    // Check memory limit
    $memory_limit = wp_convert_hr_to_bytes(ini_get('memory_limit'));
    $min_memory = 64 * 1024 * 1024; // 64MB
    if ($memory_limit < $min_memory) {
        $issues[] = [
            'fil' => 'PHP Memory Limit',
            'type' => 'system',
            'kritisk' => false,
            'status' => 'lav_memory',
            'path' => '',
            'beskrivelse' => sprintf(__('Lav minnegrense: %s (anbefaler 64MB+)', 'nattevakten'), size_format($memory_limit)),
            'fix_suggestion' => __('Øk memory_limit i php.ini', 'nattevakten')
        ];
    }

    return $issues;
}

/**
 * Security-focused integrity checks
 */
function nattevakten_perform_security_checks() {
    $issues = [];
    
    // Check API key configuration
    $api_key = get_option('nattevakten_api_key');
    if (empty($api_key)) {
        $issues[] = [
            'fil' => 'API Configuration',
            'type' => 'config',
            'kritisk' => true,
            'status' => 'ikke_konfigurert',
            'path' => '',
            'beskrivelse' => __('OpenAI API-nøkkel er ikke konfigurert', 'nattevakten'),
            'fix_suggestion' => __('Gå til innstillinger og legg inn gyldig API-nøkkel', 'nattevakten')
        ];
    } else {
        // Validate API key format (basic check)
        if (!preg_match('/^sk-[a-zA-Z0-9]{48,}$/', $api_key)) {
            $issues[] = [
                'fil' => 'API Configuration',
                'type' => 'config',
                'kritisk' => true,
                'status' => 'ugyldig_api_nokkel',
                'path' => '',
                'beskrivelse' => __('API-nøkkel har ugyldig format', 'nattevakten'),
                'fix_suggestion' => __('Sjekk at API-nøkkelen er korrekt formatert', 'nattevakten')
            ];
        }
    }
    
    // Check file permissions for security
    $security_sensitive_files = [
        NATTEVAKTEN_JSON_PATH . 'redaksjonen.json',
        NATTEVAKTEN_JSON_PATH . 'bedrifter.json'
    ];
    
    foreach ($security_sensitive_files as $file) {
        if (file_exists($file)) {
            $perms = fileperms($file);
            // Check if file is world-writable (potential security risk)
            if ($perms & 0002) {
                $issues[] = [
                    'fil' => basename($file),
                    'type' => 'security',
                    'kritisk' => false,
                    'status' => 'usikre_tillatelser',
                    'path' => $file,
                    'beskrivelse' => __('Fil er world-writable (sikkerhetsrisiko)', 'nattevakten'),
                    'fix_suggestion' => __('Endre tillatelser til 644', 'nattevakten')
                ];
            }
        }
    }
    
    return $issues;
}

/**
 * Get quick integrity status for dashboard display
 */
function nattevakten_get_integrity_status_summary() {
    $issues = nattevakten_verify_module_integrity();
    
    $summary = [
        'status' => 'ok',
        'total_issues' => count($issues),
        'critical_issues' => 0,
        'warnings' => 0,
        'missing_files' => 0,
        'json_errors' => 0,
        'system_errors' => 0,
        'deprecated_files' => 0,
        'security_issues' => 0,
        'last_check' => current_time('mysql'),
        'version' => NATTEVAKTEN_VERSION
    ];

    foreach ($issues as $issue) {
        if ($issue['kritisk']) {
            $summary['critical_issues']++;
        } else {
            $summary['warnings']++;
        }

        switch ($issue['status']) {
            case 'mangler':
                $summary['missing_files']++;
                break;
            case 'ugyldig_json':
                $summary['json_errors']++;
                break;
            case 'foreldet':
                $summary['deprecated_files']++;
                break;
        }

        if ($issue['type'] === 'system') {
            $summary['system_errors']++;
        } elseif ($issue['type'] === 'security') {
            $summary['security_issues']++;
        }
    }

    // Determine overall status
    if ($summary['critical_issues'] > 0) {
        $summary['status'] = 'critical';
    } elseif ($summary['system_errors'] > 0 || $summary['security_issues'] > 0) {
        $summary['status'] = 'warning';
    } elseif ($summary['warnings'] > 0) {
        $summary['status'] = 'needs_attention';
    }

    return $summary;
}

/**
 * Generate comprehensive integrity report for admin interface
 */
function nattevakten_generate_integrity_report() {
    $issues = nattevakten_verify_module_integrity();
    $summary = nattevakten_get_integrity_status_summary();
    
    $report = [
        'timestamp' => current_time('mysql'),
        'version' => NATTEVAKTEN_VERSION,
        'summary' => $summary,
        'issues' => $issues,
        'recommendations' => [],
        'system_info' => [
            'php_version' => PHP_VERSION,
            'wordpress_version' => get_bloginfo('version'),
            'plugin_version' => NATTEVAKTEN_VERSION,
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'json_path_writable' => is_writable(NATTEVAKTEN_JSON_PATH),
            'media_path_writable' => is_writable(NATTEVAKTEN_MEDIA_PATH),
            'required_extensions' => [
                'json' => extension_loaded('json'),
                'curl' => extension_loaded('curl'),
                'openssl' => extension_loaded('openssl'),
                'mbstring' => extension_loaded('mbstring')
            ]
        ]
    ];

    // Generate prioritized recommendations
    if ($summary['critical_issues'] > 0) {
        $report['recommendations'][] = [
            'priority' => 'critical',
            'action' => __('Løs kritiske problemer umiddelbart', 'nattevakten'),
            'description' => sprintf(__('%d kritiske feil hindrer normal drift', 'nattevakten'), $summary['critical_issues']),
            'urgency' => 'immediate'
        ];
    }

    if ($summary['missing_files'] > 0) {
        $report['recommendations'][] = [
            'priority' => 'high',
            'action' => __('Kjør auto-fiks for manglende filer', 'nattevakten'),
            'description' => sprintf(__('%d filer mangler og kan gjenopprettes', 'nattevakten'), $summary['missing_files']),
            'urgency' => 'today'
        ];
    }

    if ($summary['system_errors'] > 0) {
        $report['recommendations'][] = [
            'priority' => 'high',
            'action' => __('Løs systemkonfigurasjonsprobl emer', 'nattevakten'),
            'description' => sprintf(__('%d systemproblemer påvirker ytelse', 'nattevakten'), $summary['system_errors']),
            'urgency' => 'this_week'
        ];
    }

    if ($summary['json_errors'] > 0) {
        $report['recommendations'][] = [
            'priority' => 'medium',
            'action' => __('Reparer JSON-strukturfeil', 'nattevakten'),
            'description' => sprintf(__('%d filer har strukturproblemer', 'nattevakten'), $summary['json_errors']),
            'urgency' => 'this_week'
        ];
    }

    if ($summary['security_issues'] > 0) {
        $report['recommendations'][] = [
            'priority' => 'medium',
            'action' => __('Adresser sikkerhetsproblemer', 'nattevakten'),
            'description' => sprintf(__('%d sikkerhetsproblemer funnet', 'nattevakten'), $summary['security_issues']),
            'urgency' => 'this_week'
        ];
    }

    if ($summary['deprecated_files'] > 0) {
        $report['recommendations'][] = [
            'priority' => 'low',
            'action' => __('Rydd opp foreldede filer', 'nattevakten'),
            'description' => sprintf(__('%d foreldede filer kan fjernes', 'nattevakten'), $summary['deprecated_files']),
            'urgency' => 'when_convenient'
        ];
    }

    if ($summary['total_issues'] === 0) {
        $report['recommendations'][] = [
            'priority' => 'info',
            'action' => __('Systemet fungerer optimalt', 'nattevakten'),
            'description' => __('Alle integritetssjekker bestått - pluginen er klar for bruk', 'nattevakten'),
            'urgency' => 'none'
        ];
    }

    return $report;
}
?>