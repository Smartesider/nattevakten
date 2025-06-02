<?php
/**
 * Enhanced integrity verification for Nattevakten
 * Verifiserer at viktige modulfiler og nye json-data er tilgjengelige
 * Updated for new JSON data structure with comprehensive validation
 */

function nattevakten_verify_integrity() {
    // Updated list of required JSON files for new structure
    $required_files = [
        NATTEVAKTEN_JSON_PATH . 'bedrifter.json',      // Companies in Pjuskeby  
        NATTEVAKTEN_JSON_PATH . 'gatenavn.json',       // Street names and occupants
        NATTEVAKTEN_JSON_PATH . 'innsjo.json',         // Lakes and water bodies
        NATTEVAKTEN_JSON_PATH . 'rundtpjuskeby.json',  // Surrounding areas
        NATTEVAKTEN_JSON_PATH . 'sport.json',          // Sports activities
        NATTEVAKTEN_JSON_PATH . 'turister.json',       // Tourist attractions
        NATTEVAKTEN_JSON_PATH . 'stederipjuskeby.json', // Places in Pjuskeby
        NATTEVAKTEN_JSON_PATH . 'redaksjonen.json'     // Editorial/character info
    ];

    $critical_files = [
        NATTEVAKTEN_JSON_PATH . 'bedrifter.json',
        NATTEVAKTEN_JSON_PATH . 'gatenavn.json',
        NATTEVAKTEN_JSON_PATH . 'redaksjonen.json'
    ];

    $integrity_issues = [];

    // Check file existence and readability
    foreach ($required_files as $file) {
        $filename = basename($file);
        $is_critical = in_array($file, $critical_files);
        
        if (!file_exists($file)) {
            nattevakten_log_error('verify_integrity', 'missing_file', 
                sprintf(__('Mangler påkrevd fil: %s', 'nattevakten'), $filename), 
                $is_critical ? 'error' : 'warning');
            
            $integrity_issues[] = [
                'file' => $filename,
                'issue' => 'missing',
                'critical' => $is_critical,
                'path' => $file,
                'description' => sprintf(__('Fil %s mangler', 'nattevakten'), $filename)
            ];
        } elseif (!is_readable($file)) {
            nattevakten_log_error('verify_integrity', 'unreadable_file', 
                sprintf(__('Kan ikke lese fil: %s', 'nattevakten'), $filename), 'error');
            
            $integrity_issues[] = [
                'file' => $filename,
                'issue' => 'unreadable',
                'critical' => true,
                'path' => $file,
                'description' => sprintf(__('Fil %s kan ikke leses', 'nattevakten'), $filename)
            ];
        }
    }

    // Validate JSON structure and content for existing files
    foreach ($required_files as $file) {
        if (file_exists($file) && is_readable($file)) {
            $validation_result = nattevakten_validate_json_file_structure($file);
            if (!$validation_result['valid']) {
                $filename = basename($file);
                $is_critical = in_array($file, $critical_files);
                
                nattevakten_log_error('verify_integrity', 'invalid_json', 
                    sprintf(__('Ugyldig JSON i %s: %s', 'nattevakten'), $filename, $validation_result['error']), 
                    $is_critical ? 'error' : 'warning');
                
                $integrity_issues[] = [
                    'file' => $filename,
                    'issue' => 'invalid_json',
                    'critical' => $is_critical,
                    'error' => $validation_result['error'],
                    'path' => $file,
                    'description' => sprintf(__('Ugyldig JSON-struktur i %s: %s', 'nattevakten'), $filename, $validation_result['error'])
                ];
            }
        }
    }

    // Additional comprehensive integrity checks
    $additional_checks = nattevakten_perform_additional_integrity_checks();
    $integrity_issues = array_merge($integrity_issues, $additional_checks);

    return $integrity_issues;
}

/**
 * Comprehensive JSON file structure validation
 */
function nattevakten_validate_json_file_structure($filepath) {
    $content = file_get_contents($filepath, false, null, 0, 102400); // Max 100KB
    if ($content === false) {
        return ['valid' => false, 'error' => __('Kunne ikke lese fil', 'nattevakten')];
    }
    
    // Check for empty files
    if (trim($content) === '') {
        return ['valid' => false, 'error' => __('Filen er tom', 'nattevakten')];
    }
    
    $data = json_decode($content, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return ['valid' => false, 'error' => __('Ugyldig JSON: ', 'nattevakten') . json_last_error_msg()];
    }
    
    $filename = basename($filepath);
    
    // Specific validation for each new JSON file type
    switch ($filename) {
        case 'bedrifter.json':
            return nattevakten_validate_bedrifter_structure($data);
            
        case 'gatenavn.json':
            return nattevakten_validate_gatenavn_structure($data);
            
        case 'innsjo.json':
            return nattevakten_validate_innsjo_structure($data);
            
        case 'rundtpjuskeby.json':
            return nattevakten_validate_rundtpjuskeby_structure($data);
            
        case 'sport.json':
            return nattevakten_validate_sport_structure($data);
            
        case 'turister.json':
            return nattevakten_validate_turister_structure($data);
            
        case 'stederipjuskeby.json':
            return nattevakten_validate_steder_structure($data);
            
        case 'redaksjonen.json':
            return nattevakten_validate_redaksjonen_structure($data);
            
        case 'nattavis.json':
            return nattevakten_validate_nattavis_structure($data);
            
        default:
            // Generic validation for unknown files
            return is_array($data) ? 
                ['valid' => true, 'error' => null] : 
                ['valid' => false, 'error' => __('Data må være en array eller objekt', 'nattevakten')];
    }
}

/**
 * Validate bedrifter.json structure
 */
function nattevakten_validate_bedrifter_structure($data) {
    if (!is_array($data)) {
        return ['valid' => false, 'error' => __('Bedrifter må være en array', 'nattevakten')];
    }
    
    if (empty($data)) {
        return ['valid' => false, 'error' => __('Bedrifter kan ikke være tom', 'nattevakten')];
    }
    
    $required_fields = ['navn'];
    $recommended_fields = ['type', 'aktivitet', 'eier'];
    
    foreach ($data as $index => $bedrift) {
        if (!is_array($bedrift)) {
            return ['valid' => false, 'error' => sprintf(__('Bedrift #%d må være et objekt', 'nattevakten'), $index + 1)];
        }
        
        // Check required fields
        foreach ($required_fields as $field) {
            if (empty($bedrift[$field])) {
                return ['valid' => false, 'error' => sprintf(__('Bedrift #%d mangler påkrevd felt: %s', 'nattevakten'), $index + 1, $field)];
            }
        }
        
        // Validate field types
        if (isset($bedrift['ansatte']) && !is_numeric($bedrift['ansatte'])) {
            return ['valid' => false, 'error' => sprintf(__('Bedrift #%d: ansatte må være et tall', 'nattevakten'), $index + 1)];
        }
    }
    
    return ['valid' => true, 'error' => null];
}

/**
 * Validate gatenavn.json structure
 */
function nattevakten_validate_gatenavn_structure($data) {
    if (!is_array($data)) {
        return ['valid' => false, 'error' => __('Gatenavn må være en array', 'nattevakten')];
    }
    
    if (empty($data)) {
        return ['valid' => false, 'error' => __('Gatenavn kan ikke være tom', 'nattevakten')];
    }
    
    $required_fields = ['gate'];
    $recommended_fields = ['nummer', 'beboer'];
    
    foreach ($data as $index => $gate) {
        if (!is_array($gate)) {
            return ['valid' => false, 'error' => sprintf(__('Gate #%d må være et objekt', 'nattevakten'), $index + 1)];
        }
        
        foreach ($required_fields as $field) {
            if (empty($gate[$field])) {
                return ['valid' => false, 'error' => sprintf(__('Gate #%d mangler påkrevd felt: %s', 'nattevakten'), $index + 1, $field)];
            }
        }
        
        // Validate house number format
        if (isset($gate['nummer']) && !preg_match('/^\d+[a-zA-Z]?$/', $gate['nummer'])) {
            return ['valid' => false, 'error' => sprintf(__('Gate #%d: ugyldig husnummer format', 'nattevakten'), $index + 1)];
        }
    }
    
    return ['valid' => true, 'error' => null];
}

/**
 * Validate sport.json structure
 */
function nattevakten_validate_sport_structure($data) {
    if (!is_array($data)) {
        return ['valid' => false, 'error' => __('Sport må være en array', 'nattevakten')];
    }
    
    foreach ($data as $index => $sport) {
        if (!is_array($sport)) {
            return ['valid' => false, 'error' => sprintf(__('Sport #%d må være et objekt', 'nattevakten'), $index + 1)];
        }
        
        if (empty($sport['tullenavn']) && empty($sport['ektenavn'])) {
            return ['valid' => false, 'error' => sprintf(__('Sport #%d må ha tullenavn eller ektenavn', 'nattevakten'), $index + 1)];
        }
        
        // Validate deltakere field if present
        if (isset($sport['deltakere']) && !is_array($sport['deltakere'])) {
            return ['valid' => false, 'error' => sprintf(__('Sport #%d: deltakere må være en array', 'nattevakten'), $index + 1)];
        }
    }
    
    return ['valid' => true, 'error' => null];
}

/**
 * Validate turister.json structure
 */
function nattevakten_validate_turister_structure($data) {
    if (!is_array($data)) {
        return ['valid' => false, 'error' => __('Turister må være en array', 'nattevakten')];
    }
    
    foreach ($data as $index => $attraksjon) {
        if (!is_array($attraksjon)) {
            return ['valid' => false, 'error' => sprintf(__('Attraksjon #%d må være et objekt', 'nattevakten'), $index + 1)];
        }
        
        if (empty($attraksjon['tullenavn']) && empty($attraksjon['ektenavn'])) {
            return ['valid' => false, 'error' => sprintf(__('Attraksjon #%d må ha tullenavn eller ektenavn', 'nattevakten'), $index + 1)];
        }
        
        // Validate besøkstid if present
        if (isset($attraksjon['besøkstid']) && !is_string($attraksjon['besøkstid'])) {
            return ['valid' => false, 'error' => sprintf(__('Attraksjon #%d: besøkstid må være tekst', 'nattevakten'), $index + 1)];
        }
    }
    
    return ['valid' => true, 'error' => null];
}

/**
 * Validate stederipjuskeby.json structure
 */
function nattevakten_validate_steder_structure($data) {
    if (!is_array($data)) {
        return ['valid' => false, 'error' => __('Steder må være en array', 'nattevakten')];
    }
    
    $required_fields = ['sted'];
    $recommended_fields = ['description', 'type'];
    
    foreach ($data as $index => $sted) {
        if (!is_array($sted)) {
            return ['valid' => false, 'error' => sprintf(__('Sted #%d må være et objekt', 'nattevakten'), $index + 1)];
        }
        
        foreach ($required_fields as $field) {
            if (empty($sted[$field])) {
                return ['valid' => false, 'error' => sprintf(__('Sted #%d mangler påkrevd felt: %s', 'nattevakten'), $index + 1, $field)];
            }
        }
        
        // Validate aktiviteter field if present
        if (isset($sted['aktiviteter']) && !is_array($sted['aktiviteter'])) {
            return ['valid' => false, 'error' => sprintf(__('Sted #%d: aktiviteter må være en array', 'nattevakten'), $index + 1)];
        }
    }
    
    return ['valid' => true, 'error' => null];
}

/**
 * Validate other JSON structures
 */
function nattevakten_validate_innsjo_structure($data) {
    if (!is_array($data)) {
        return ['valid' => false, 'error' => __('Innsjøer må være en array', 'nattevakten')];
    }
    
    foreach ($data as $index => $innsjo) {
        if (!is_array($innsjo)) {
            return ['valid' => false, 'error' => sprintf(__('Innsjø #%d må være et objekt', 'nattevakten'), $index + 1)];
        }
        
        if (empty($innsjo['navn'])) {
            return ['valid' => false, 'error' => sprintf(__('Innsjø #%d må ha navn', 'nattevakten'), $index + 1)];
        }
        
        // Validate aktiviteter if present
        if (isset($innsjo['aktiviteter']) && !is_array($innsjo['aktiviteter'])) {
            return ['valid' => false, 'error' => sprintf(__('Innsjø #%d: aktiviteter må være en array', 'nattevakten'), $index + 1)];
        }
    }
    
    return ['valid' => true, 'error' => null];
}

function nattevakten_validate_rundtpjuskeby_structure($data) {
    if (!is_array($data)) {
        return ['valid' => false, 'error' => __('Områder må være en array', 'nattevakten')];
    }
    
    foreach ($data as $index => $omrade) {
        if (!is_array($omrade)) {
            return ['valid' => false, 'error' => sprintf(__('Område #%d må være et objekt', 'nattevakten'), $index + 1)];
        }
        
        if (empty($omrade['navn'])) {
            return ['valid' => false, 'error' => sprintf(__('Område #%d må ha navn', 'nattevakten'), $index + 1)];
        }
        
        // Validate numeric fields
        if (isset($omrade['innbyggere']) && !is_numeric($omrade['innbyggere'])) {
            return ['valid' => false, 'error' => sprintf(__('Område #%d: innbyggere må være et tall', 'nattevakten'), $index + 1)];
        }
    }
    
    return ['valid' => true, 'error' => null];
}

function nattevakten_validate_redaksjonen_structure($data) {
    if (!is_array($data)) {
        return ['valid' => false, 'error' => __('Redaksjonen må være et objekt', 'nattevakten')];
    }
    
    $required_fields = ['hovedperson'];
    $recommended_fields = ['rolle', 'beskrivelse'];
    
    foreach ($required_fields as $field) {
        if (empty($data[$field])) {
            return ['valid' => false, 'error' => sprintf(__('Redaksjonen mangler påkrevd felt: %s', 'nattevakten'), $field)];
        }
    }
    
    return ['valid' => true, 'error' => null];
}

function nattevakten_validate_nattavis_structure($data) {
    if (!is_array($data)) {
        return ['valid' => false, 'error' => __('Nattavis må være en array', 'nattevakten')];
    }
    
    // Empty is OK for nattavis.json (generated content)
    if (empty($data)) {
        return ['valid' => true, 'error' => null];
    }
    
    foreach ($data as $index => $nyhet) {
        if (!is_array($nyhet)) {
            return ['valid' => false, 'error' => sprintf(__('Nyhet #%d må være et objekt', 'nattevakten'), $index + 1)];
        }
        
        if (empty($nyhet['tekst'])) {
            return ['valid' => false, 'error' => sprintf(__('Nyhet #%d må ha tekst', 'nattevakten'), $index + 1)];
        }
        
        // Validate score if present
        if (isset($nyhet['score']) && (!is_numeric($nyhet['score']) || $nyhet['score'] < 0 || $nyhet['score'] > 100)) {
            return ['valid' => false, 'error' => sprintf(__('Nyhet #%d: score må være mellom 0 og 100', 'nattevakten'), $index + 1)];
        }
    }
    
    return ['valid' => true, 'error' => null];
}

/**
 * Perform additional comprehensive integrity checks
 */
function nattevakten_perform_additional_integrity_checks() {
    $issues = [];
    
    // Check if old deprecated files still exist
    $deprecated_files = ['pjuskeby.json', 'land.json', 'organisasjoner.json'];
    foreach ($deprecated_files as $deprecated) {
        $old_file = NATTEVAKTEN_JSON_PATH . $deprecated;
        if (file_exists($old_file)) {
            $issues[] = [
                'file' => $deprecated,
                'issue' => 'deprecated_file_exists',
                'critical' => false,
                'error' => __('Foreldet fil bør fjernes', 'nattevakten'),
                'path' => $old_file,
                'description' => sprintf(__('Foreldet fil %s eksisterer fortsatt og bør fjernes', 'nattevakten'), $deprecated)
            ];
            
            nattevakten_log_error('verify_integrity', 'deprecated_file', 
                sprintf(__('Foreldet fil %s eksisterer fortsatt', 'nattevakten'), $deprecated), 'warning');
        }
    }
    
    // Check file sizes to detect potential corruption
    $json_files = [
        'bedrifter.json', 'gatenavn.json', 'innsjo.json', 'rundtpjuskeby.json',
        'sport.json', 'turister.json', 'stederipjuskeby.json', 'redaksjonen.json', 'nattavis.json'
    ];
    
    foreach ($json_files as $filename) {
        $filepath = NATTEVAKTEN_JSON_PATH . $filename;
        if (file_exists($filepath)) {
            $size = filesize($filepath);
            
            // Flag suspiciously small files (less than 10 bytes, except nattavis.json which can be empty)
            if ($size < 10 && $filename !== 'nattavis.json') {
                $issues[] = [
                    'file' => $filename,
                    'issue' => 'suspiciously_small',
                    'critical' => false,
                    'error' => sprintf(__('Fil er suspekt liten (%d bytes)', 'nattevakten'), $size),
                    'path' => $filepath,
                    'description' => sprintf(__('Fil %s er bare %d bytes, noe som kan indikere korrupsjon', 'nattevakten'), $filename, $size)
                ];
            }
            
            // Flag suspiciously large files (more than 1MB)
            if ($size > 1048576) {
                $issues[] = [
                    'file' => $filename,
                    'issue' => 'suspiciously_large',
                    'critical' => false,
                    'error' => sprintf(__('Fil er suspekt stor (%d bytes)', 'nattevakten'), $size),
                    'path' => $filepath,
                    'description' => sprintf(__('Fil %s er %s, noe som er uventet stort', 'nattevakten'), $filename, size_format($size))
                ];
            }
        }
    }
    
    // Check directory permissions
    $required_dirs = [NATTEVAKTEN_JSON_PATH, NATTEVAKTEN_MEDIA_PATH];
    foreach ($required_dirs as $dir) {
        if (!file_exists($dir)) {
            $issues[] = [
                'file' => basename($dir),
                'issue' => 'directory_missing',
                'critical' => true,
                'error' => __('Påkrevd katalog mangler', 'nattevakten'),
                'path' => $dir,
                'description' => sprintf(__('Katalog %s mangler og må opprettes', 'nattevakten'), $dir)
            ];
        } elseif (!is_writable($dir)) {
            $issues[] = [
                'file' => basename($dir),
                'issue' => 'directory_not_writable',
                'critical' => true,
                'error' => __('Katalog er ikke skrivbar', 'nattevakten'),
                'path' => $dir,
                'description' => sprintf(__('Katalog %s er ikke skrivbar', 'nattevakten'), $dir)
            ];
        }
    }
    
    // Check data consistency across files
    $consistency_issues = nattevakten_check_data_consistency();
    $issues = array_merge($issues, $consistency_issues);
    
    return $issues;
}

/**
 * Check data consistency across different JSON files
 */
function nattevakten_check_data_consistency() {
    $issues = [];
    
    // Load all JSON files
    $files = [
        'bedrifter' => NATTEVAKTEN_JSON_PATH . 'bedrifter.json',
        'gatenavn' => NATTEVAKTEN_JSON_PATH . 'gatenavn.json',
        'redaksjonen' => NATTEVAKTEN_JSON_PATH . 'redaksjonen.json'
    ];
    
    $data = [];
    foreach ($files as $key => $file) {
        if (file_exists($file) && is_readable($file)) {
            $content = file_get_contents($file);
            $decoded = json_decode($content, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $data[$key] = $decoded;
            }
        }
    }
    
    // Check if main character exists consistently
    if (isset($data['redaksjonen']['hovedperson'])) {
        $hovedperson = $data['redaksjonen']['hovedperson'];
        
        // Check if hovedperson appears in gatenavn
        $found_in_gatenavn = false;
        if (isset($data['gatenavn'])) {
            foreach ($data['gatenavn'] as $gate) {
                if (isset($gate['beboer']) && strpos($gate['beboer'], $hovedperson) !== false) {
                    $found_in_gatenavn = true;
                    break;
                }
            }
        }
        
        if (!$found_in_gatenavn) {
            $issues[] = [
                'file' => 'data_consistency',
                'issue' => 'character_consistency',
                'critical' => false,
                'error' => __('Hovedperson ikke funnet i gatenavn', 'nattevakten'),
                'path' => '',
                'description' => sprintf(__('Hovedperson %s fra redaksjonen.json er ikke registrert som beboer i gatenavn.json', 'nattevakten'), $hovedperson)
            ];
        }
    }
    
    return $issues;
}

/**
 * Get comprehensive integrity summary for admin display
 */
function nattevakten_get_integrity_summary() {
    $issues = nattevakten_verify_integrity();
    
    $summary = [
        'total_issues' => count($issues),
        'critical_issues' => 0,
        'warning_issues' => 0,
        'missing_files' => 0,
        'json_errors' => 0,
        'deprecated_files' => 0,
        'directory_issues' => 0,
        'status' => 'healthy',
        'last_check' => current_time('mysql'),
        'version' => NATTEVAKTEN_VERSION
    ];
    
    foreach ($issues as $issue) {
        if ($issue['critical']) {
            $summary['critical_issues']++;
        } else {
            $summary['warning_issues']++;
        }
        
        switch ($issue['issue']) {
            case 'missing':
                $summary['missing_files']++;
                break;
            case 'invalid_json':
                $summary['json_errors']++;
                break;
            case 'deprecated_file_exists':
                $summary['deprecated_files']++;
                break;
            case 'directory_missing':
            case 'directory_not_writable':
                $summary['directory_issues']++;
                break;
        }
    }
    
    // Determine overall status
    if ($summary['critical_issues'] > 0) {
        $summary['status'] = 'critical';
    } elseif ($summary['warning_issues'] > 3) {
        $summary['status'] = 'warning';
    } elseif ($summary['warning_issues'] > 0) {
        $summary['status'] = 'needs_attention';
    }
    
    return $summary;
}

/**
 * Generate detailed integrity report for admin interface
 */
function nattevakten_generate_detailed_integrity_report() {
    $issues = nattevakten_verify_integrity();
    $summary = nattevakten_get_integrity_summary();
    
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
            'json_path_writable' => is_writable(NATTEVAKTEN_JSON_PATH),
            'media_path_writable' => is_writable(NATTEVAKTEN_MEDIA_PATH)
        ]
    ];
    
    // Generate specific recommendations based on issues
    if ($summary['critical_issues'] > 0) {
        $report['recommendations'][] = [
            'priority' => 'high',
            'action' => __('Løs kritiske problemer umiddelbart', 'nattevakten'),
            'description' => __('Kritiske feil kan hindre pluginen fra å fungere korrekt', 'nattevakten')
        ];
    }
    
    if ($summary['missing_files'] > 0) {
        $report['recommendations'][] = [
            'priority' => 'high',
            'action' => __('Kjør auto-fiks for å gjenopprette manglende filer', 'nattevakten'),
            'description' => sprintf(__('%d filer mangler og kan opprettes automatisk', 'nattevakten'), $summary['missing_files'])
        ];
    }
    
    if ($summary['json_errors'] > 0) {
        $report['recommendations'][] = [
            'priority' => 'medium',
            'action' => __('Reparer JSON-filer med strukturfeil', 'nattevakten'),
            'description' => sprintf(__('%d filer har JSON-strukturfeil', 'nattevakten'), $summary['json_errors'])
        ];
    }
    
    if ($summary['deprecated_files'] > 0) {
        $report['recommendations'][] = [
            'priority' => 'low',
            'action' => __('Fjern foreldede filer', 'nattevakten'),
            'description' => sprintf(__('%d foreldede filer bør fjernes', 'nattevakten'), $summary['deprecated_files'])
        ];
    }
    
    if ($summary['total_issues'] === 0) {
        $report['recommendations'][] = [
            'priority' => 'info',
            'action' => __('Alle systemer fungerer normalt', 'nattevakten'),
            'description' => __('Ingen problemer funnet - pluginen er klar for bruk', 'nattevakten')
        ];
    }
    
    return $report;
}
?>