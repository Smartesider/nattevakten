<?php

if (!class_exists('WP_CLI')) return;

/**
 * WP CLI-kommandoer for Nattevakten
 */
if (!class_exists('Nattevakten_CLI_Commands')) {
    class Nattevakten_CLI_Commands {

        /**
         * Generate news manually
         *
         * ## EXAMPLES
         * wp nattevakt generate-news
         */
        public function generate_news() {
            if (!function_exists('nattevakten_generate_news')) {
                WP_CLI::error("News generation function not available.");
                return;
            }
            
            $result = nattevakten_generate_news();
            if ($result === true) {
                WP_CLI::success("News generated and saved successfully.");
            } else {
                WP_CLI::error("Generation failed: " . (is_string($result) ? $result : 'Unknown error'));
            }
        }

        /**
         * View recent error log entries
         *
         * ## OPTIONS
         * [--count=<number>]
         * : Number of entries to show
         * ---
         * default: 10
         * ---
         *
         * ## EXAMPLES
         * wp nattevakt view-log
         * wp nattevakt view-log --count=20
         */
        public function view_log($args, $assoc_args) {
            $count = isset($assoc_args['count']) ? intval($assoc_args['count']) : 10;
            
            if (!function_exists('nattevakten_get_recent_errors')) {
                WP_CLI::error("Error logging system not available.");
                return;
            }
            
            $errors = nattevakten_get_recent_errors($count);
            
            if (empty($errors)) {
                WP_CLI::log("No errors found in log.");
                return;
            }
            
            WP_CLI::log("Recent errors (showing last $count):");
            WP_CLI::log(str_repeat('-', 80));
            
            foreach ($errors as $error) {
                $line = sprintf(
                    "[%s] %s - %s - %s - %s",
                    $error['tid'] ?? 'N/A',
                    strtoupper($error['nivå'] ?? 'UNKNOWN'),
                    $error['modul'] ?? 'N/A',
                    $error['type'] ?? 'N/A',
                    $error['beskrivelse'] ?? 'N/A'
                );
                WP_CLI::log($line);
            }
        }

        /**
         * Clear error logs
         *
         * ## EXAMPLES
         * wp nattevakt clear-log
         */
        public function clear_log() {
            if (!function_exists('nattevakten_clear_logs')) {
                WP_CLI::error("Log clearing function not available.");
                return;
            }
            
            nattevakten_clear_logs();
            WP_CLI::success("Error logs cleared successfully.");
        }

        /**
         * Run module integrity check
         *
         * ## EXAMPLES
         * wp nattevakt check-integrity
         */
        public function check_integrity() {
            if (!function_exists('nattevakten_check_module_integrity')) {
                WP_CLI::error("Integrity check function not available.");
                return;
            }
            
            $issues = nattevakten_check_module_integrity();
            
            if (empty($issues)) {
                WP_CLI::success("All modules are healthy.");
                return;
            }
            
            WP_CLI::warning("Found " . count($issues) . " integrity issues:");
            foreach ($issues as $issue) {
                $level = $issue['critical'] ? 'ERROR' : 'WARNING';
                WP_CLI::log("[$level] {$issue['file']} - {$issue['issue']}");
            }
        }

        /**
         * Run auto-fix for missing files
         *
         * ## EXAMPLES
         * wp nattevakt auto-fix
         */
        public function auto_fix() {
            if (!function_exists('nattevakten_auto_fixer')) {
                WP_CLI::error("Auto-fix function not available.");
                return;
            }
            
            $results = nattevakten_auto_fixer();
            
            WP_CLI::log("Auto-fix completed:");
            WP_CLI::log(str_repeat('-', 50));
            
            foreach ($results as $result) {
                $status_color = ($result['status'] === 'ok') ? '%G' : '%Y';
                WP_CLI::log(WP_CLI::colorize(
                    "$status_color{$result['fil']}%n - {$result['status']} ({$result['tid']})"
                ));
            }
            
            WP_CLI::success("Auto-fix process completed.");
        }

        /**
         * Show plugin status and configuration
         *
         * ## EXAMPLES
         * wp nattevakt status
         */
        public function status() {
            WP_CLI::log("Nattevakten Plugin Status");
            WP_CLI::log(str_repeat('=', 30));
            
            // Version info
            WP_CLI::log("Version: " . NATTEVAKTEN_VERSION);
            WP_CLI::log("WordPress: " . get_bloginfo('version'));
            WP_CLI::log("PHP: " . PHP_VERSION);
            
            // Configuration
            $api_key = get_option('nattevakten_api_key');
            WP_CLI::log("API Key: " . ($api_key ? 'Configured' : 'Not set'));
            
            $prompt = get_option('nattevakten_prompt');
            WP_CLI::log("Prompt: " . ($prompt ? 'Set' : 'Default'));
            
            $temp = get_option('nattevakten_temp', 0.7);
            WP_CLI::log("Temperature: " . $temp);
            
            // Directory status
            WP_CLI::log("\nDirectory Status:");
            $dirs = [
                'JSON Path' => NATTEVAKTEN_JSON_PATH,
                'Media Path' => NATTEVAKTEN_MEDIA_PATH
            ];
            
            foreach ($dirs as $name => $path) {
                $exists = file_exists($path);
                $writable = $exists ? is_writable($path) : false;
                $status = $exists ? ($writable ? 'OK' : 'Not writable') : 'Missing';
                WP_CLI::log("$name: $status");
            }
            
            // Recent news check
            $news_file = NATTEVAKTEN_JSON_PATH . 'nattavis.json';
            if (file_exists($news_file)) {
                $mtime = filemtime($news_file);
                $age = human_time_diff($mtime, current_time('timestamp'));
                WP_CLI::log("\nLast news generated: $age ago");
            } else {
                WP_CLI::log("\nNo news file found.");
            }
        }
    }

    WP_CLI::add_command('nattevakt', 'Nattevakten_CLI_Commands');
}
?>