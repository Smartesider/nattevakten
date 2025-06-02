<?php
/**
 * Logger for Nattevakten
 * Lagrer feil, status og forsøk på selvreparasjon i tidligerefeil.json og feil.log
 */

function nattevakten_log_error($modul, $feiltype, $beskrivelse = '', $nivå = 'error') {
    // Prevent log injection attacks
    $modul = nattevakten_sanitize_log_input($modul);
    $feiltype = nattevakten_sanitize_log_input($feiltype);
    $beskrivelse = nattevakten_sanitize_log_input($beskrivelse);
    $nivå = in_array($nivå, ['error', 'warning', 'info', 'debug']) ? $nivå : 'error';
    
    $loggfil_json = NATTEVAKTEN_JSON_PATH . 'tidligerefeil.json';
    $loggfil_txt  = NATTEVAKTEN_JSON_PATH . 'feil.log';

    $ny_feil = [
        'tid' => current_time('mysql'),
        'modul' => $modul,
        'type' => $feiltype,
        'beskrivelse' => $beskrivelse,
        'status' => 'registrert',
        'nivå' => $nivå,
        'site_id' => is_multisite() ? get_current_blog_id() : 1,
        'user_id' => get_current_user_id(),
        'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
        'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 200)
    ];

    // Thread-safe JSON file writing
    $lock_file = $loggfil_json . '.lock';
    $lock = fopen($lock_file, 'w');
    
    if (flock($lock, LOCK_EX)) {
        try {
            $eksisterende = [];
            if (file_exists($loggfil_json) && is_readable($loggfil_json)) {
                $innhold = file_get_contents($loggfil_json, false, null, 0, 1048576); // Max 1MB
                if ($innhold !== false) {
                    $eksisterende = json_decode($innhold, true);
                    if (!is_array($eksisterende)) {
                        $eksisterende = [];
                    }
                }
            }
            
            $eksisterende[] = $ny_feil;
            
            // Keep only last 1000 entries to prevent excessive disk usage
            if (count($eksisterende) > 1000) {
                $eksisterende = array_slice($eksisterende, -1000);
            }

            nattevakten_atomic_file_write_verified($loggfil_json, wp_json_encode($eksisterende, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
            @unlink($lock_file);
        }
    } else {
        fclose($lock);
    }

    // Simplified text log
    $linje = sprintf(
        "[%s] %s – %s – %s – %s\n",
        $ny_feil['tid'],
        strtoupper($nivå),
        $modul,
        $feiltype,
        $beskrivelse
    );
    
    // Thread-safe text file writing
    file_put_contents($loggfil_txt, $linje, FILE_APPEND | LOCK_EX);

    // PHP error_log with context
    error_log(sprintf(
        "[Nattevakten][Site:%d][%s][%s] %s – %s",
        is_multisite() ? get_current_blog_id() : 1,
        $modul,
        $nivå,
        $feiltype,
        $beskrivelse
    ));
}

/**
 * Sanitize log input to prevent injection attacks
 */
function nattevakten_sanitize_log_input($input) {
    // Remove null bytes, carriage returns, line feeds, and other control characters
    $input = str_replace(["\0", "\r", "\n", "\t"], ' ', $input);
    
    // Remove ANSI escape sequences
    $input = preg_replace('/\x1b\[[0-9;]*m/', '', $input);
    
    // Limit length
    $input = substr($input, 0, 500);
    
    // Basic sanitization
    return sanitize_text_field($input);
}

/**
 * Hent siste x loggposter (fra JSON)
 */
function nattevakten_get_recent_errors($antall = 50) {
    $loggfil_json = NATTEVAKTEN_JSON_PATH . 'tidligerefeil.json';
    if (!file_exists($loggfil_json)) return [];

    $innhold = file_get_contents($loggfil_json);
    $feil = json_decode($innhold, true);

    if (!is_array($feil)) {
        nattevakten_log_error('logger', 'JSON-feil i loggfil', json_last_error_msg(), 'warning');
        return [];
    }

    return array_slice(array_reverse($feil), 0, $antall);
}

/**
 * Helper functions
 */
function nattevakten_clear_logs() {
    $files = [
        NATTEVAKTEN_JSON_PATH . 'tidligerefeil.json',
        NATTEVAKTEN_JSON_PATH . 'feil.log'
    ];
    
    foreach ($files as $file) {
        if (file_exists($file)) {
            file_put_contents($file, '');
        }
    }
}
?>