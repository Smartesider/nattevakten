<?php
/**
 * Fallback-funksjoner for Nattevakten
 * Sikrer at pluginen gir noe meningsfullt selv ved alvorlige feil
 */

function nattevakten_fallback_news() {
    $eksempel = [
        [
            'tid' => current_time('H:i'),
            'tekst' => 'kl ' . current_time('H:i') . ' – Pjuskeby – Nattevakten holder vakt mens byen sover.',
            'score' => 50
        ],
        [
            'tid' => current_time('H:i', strtotime('+1 minute')),
            'tekst' => 'kl ' . current_time('H:i', strtotime('+1 minute')) . ' – Pjuskeby – Alt er rolig i nattens stillhet.',
            'score' => 25
        ]
    ];

    return $eksempel;
}

function nattevakten_load_or_fallback() {
    $json_file = NATTEVAKTEN_JSON_PATH . 'nattavis.json';

    if (!file_exists($json_file)) {
        nattevakten_log_error('fallback', 'Manglende nattavis.json');
        return nattevakten_fallback_news();
    }

    $innhold = json_decode(file_get_contents($json_file), true);

    if (json_last_error() !== JSON_ERROR_NONE || !is_array($innhold)) {
        nattevakten_log_error('fallback', 'Ugyldig JSON i nattavis.json');
        return nattevakten_fallback_news();
    }

    return $innhold;
}
?>