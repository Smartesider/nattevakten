<?php

if (!class_exists('WP_CLI')) return;

/**
 * WP CLI-kommandoer for Nattevakten
 */
class Nattevakten_CLI {

    /**
     * Generer nyheter manuelt
     *
     * ## EXAMPLES
     * wp nattevakt:generate-news
     */
    public function generate_news() {
        if (function_exists('nattevakten_generate_news')) {
            $resultat = nattevakten_generate_news();
            if ($resultat === true) {
                WP_CLI::success("Nyheter generert og lagret.");
            } else {
                WP_CLI::error("Generering feilet: $resultat");
            }
        } else {
            WP_CLI::error("Funksjonen nattevakten_generate_news finnes ikke.");
        }
    }

    /**
     * Vis siste 5 loggførte feil
     *
     * ## EXAMPLES
     * wp nattevakt:view-log
     */
    public function view_log() {
        $file = NATTEVAKTEN_JSON_PATH . 'tidligerefeil.json';
        if (!file_exists($file)) {
            WP_CLI::warning("Ingen feilfil funnet.");
            return;
        }
        $data = json_decode(file_get_contents($file), true);
        if (!is_array($data)) {
            WP_CLI::error("Ugyldig JSON-data i logg.");
            return;
        }
        $siste = array_slice($data, -5);
        foreach ($siste as $e) {
            WP_CLI::line("[{$e['tid']}] {$e['modul']} – {$e['type']} – {$e['beskrivelse']}");
        }
    }

    /**
     * Tøm feilloggen
     *
     * ## EXAMPLES
     * wp nattevakt:clear-log
     */
    public function clear_log() {
        if (function_exists('nattevakten_tøm_logg')) {
            nattevakten_tøm_logg();
            WP_CLI::success("Feillogg tømt.");
        } else {
            WP_CLI::error("Funksjonen nattevakten_tøm_logg finnes ikke.");
        }
    }

    /**
     * Simuler feil
     *
     * ## EXAMPLES
     * wp nattevakt:simulate-error
     */
    public function simulate_error() {
        if (function_exists('nattevakten_log_error')) {
            nattevakten_log_error('cli-test', 'Testfeil', 'Simulert feil fra CLI');
            WP_CLI::success("Feil logget.");
        } else {
            WP_CLI::error("Logger ikke tilgjengelig.");
        }
    }

    /**
     * Verifiser at nødvendige moduler finnes
     *
     * ## EXAMPLES
     * wp nattevakt:verify-integrity
     */
    public function verify_integrity() {
        if (function_exists('nattevakten_verify_module_integrity')) {
            $mangler = nattevakten_verify_module_integrity();
            if (empty($mangler)) {
                WP_CLI::success("Alle nødvendige moduler er tilgjengelige.");
            } else {
                foreach ($mangler as $fil) {
                    $filnavn = is_array($fil) ? $fil['fil'] : $fil;
                    WP_CLI::warning("Mangler: $filnavn");
                }
            }
        } else {
            WP_CLI::error("Verify-funksjon mangler.");
        }
    }

    /**
     * Kjør automatisk selvreparasjon
     *
     * ## EXAMPLES
     * wp nattevakt:auto-fix
     */
    public function auto_fix() {
        if (function_exists('nattevakten_auto_fixer')) {
            $resultat = nattevakten_auto_fixer();
            foreach ($resultat as $entry) {
                WP_CLI::line("{$entry['fil']} – {$entry['status']} ({$entry['tid']})");
            }
            WP_CLI::success("Auto-fix fullført.");
        } else {
            WP_CLI::error("Auto-fix funksjon ikke tilgjengelig.");
        }
    }
}

WP_CLI::add_command('nattevakt', 'Nattevakten_CLI');
