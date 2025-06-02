<?php
/**
 * Lager sikkerhetskopi av relevante filer ved aktivering
 */
function nattevakten_backup_files() {
    $files_to_backup = [
        NATTEVAKTEN_JSON_PATH . 'nattavis.json',
        NATTEVAKTEN_JSON_PATH . 'pjuskeby.json',
        NATTEVAKTEN_JSON_PATH . 'redaksjonen.json'
    ];

    $backup_dir = NATTEVAKTEN_JSON_PATH . 'backup/';
    if (!file_exists($backup_dir)) {
        mkdir($backup_dir, 0755, true);
    }

    foreach ($files_to_backup as $file) {
        if (file_exists($file)) {
            $basename = basename($file);
            copy($file, $backup_dir . $basename . '.' . date('Ymd_His') . '.bak');
        }
    }
}
?>