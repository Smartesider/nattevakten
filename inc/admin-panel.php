<?php
/**
 * Adminpanel for Nattevakten-plugin
 */

// Legg til adminmeny og dashboard-widget
function nattevakten_add_admin_menu() {
    add_menu_page(
        __('Nattevakten', 'nattevakten'),
        '🕯 Nattevakten',
        'manage_options',
        'nattevakten',
        'nattevakten_settings_page',
        'dashicons-schedule',
        58
    );
    add_submenu_page('nattevakten', __('Innstillinger', 'nattevakten'), __('Innstillinger', 'nattevakten'), 'manage_options', 'nattevakten', 'nattevakten_settings_page');
    add_submenu_page('nattevakten', __('Feillogg', 'nattevakten'), __('Feillogg', 'nattevakten'), 'manage_options', 'nattevakten_error_log', 'nattevakten_error_log_page');
    add_submenu_page('nattevakten', __('Tidligere feil', 'nattevakten'), __('Tidligere feil', 'nattevakten'), 'manage_options', 'nattevakten_prev_errors', 'nattevakten_prev_errors_page');
    add_submenu_page('nattevakten', __('Testkjør', 'nattevakten'), __('Testkjør', 'nattevakten'), 'manage_options', 'nattevakten_test', 'nattevakten_test_page');
    add_submenu_page('nattevakten', __('Modulstatus', 'nattevakten'), __('Modulstatus', 'nattevakten'), 'manage_options', 'nattevakten_module_status', 'nattevakten_module_status_page');
    add_submenu_page('nattevakten', __('Fallback-senter', 'nattevakten'), __('Fallback-senter', 'nattevakten'), 'manage_options', 'nattevakten_fallback', 'nattevakten_fallback_page');

    add_action('wp_dashboard_setup', 'nattevakten_add_dashboard_widget');
}

// Settings API – nå med felt!
function nattevakten_settings_init() {
    register_setting('nattevakten_settings', 'nattevakten_api_key');
    register_setting('nattevakten_settings', 'nattevakten_prompt');
    register_setting('nattevakten_settings', 'nattevakten_temp');

    add_settings_section(
        'nattevakten_main_section',
        __('Grunninnstillinger', 'nattevakten'),
        function() {
            echo '<p>' . esc_html__('Her legger du inn innstillinger for Nattevakten-pluginen. Feltene under må fylles ut for å aktivere alle funksjoner.', 'nattevakten') . '</p>';
        },
        'nattevakten'
    );

    add_settings_field(
        'nattevakten_api_key',
        __('API-nøkkel', 'nattevakten'),
        function() {
            $value = get_option('nattevakten_api_key');
            echo '<input type="text" name="nattevakten_api_key" value="' . esc_attr($value) . '" class="regular-text" autocomplete="off">';
        },
        'nattevakten',
        'nattevakten_main_section'
    );

    add_settings_field(
        'nattevakten_prompt',
        __('Standardprompt', 'nattevakten'),
        function() {
            $value = get_option('nattevakten_prompt');
            echo '<textarea name="nattevakten_prompt" rows="3" cols="50">' . esc_textarea($value) . '</textarea>';
        },
        'nattevakten',
        'nattevakten_main_section'
    );

    add_settings_field(
        'nattevakten_temp',
        __('AI temperatur', 'nattevakten'),
        function() {
            $value = get_option('nattevakten_temp', 0.7);
            echo '<input type="number" step="0.1" min="0" max="2" name="nattevakten_temp" value="' . esc_attr($value) . '" style="width:60px">';
        },
        'nattevakten',
        'nattevakten_main_section'
    );
}

// Render settings page
function nattevakten_settings_page() {
    ?>
    <div class="wrap">
        <h1>🕯 Nattevakten – Innstillinger</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('nattevakten_settings');
            do_settings_sections('nattevakten');
            submit_button(__('Lagre innstillinger', 'nattevakten'));
            ?>
        </form>
    </div>
    <?php
}

// Feillogg side
function nattevakten_error_log_page() {
    $logfile = NATTEVAKTEN_JSON_PATH . 'feil.log';
    ?>
    <div class="wrap">
        <h1>📝 Feillogg</h1>
        <pre><?php echo esc_html(file_exists($logfile) ? file_get_contents($logfile) : __('Ingen logg funnet.', 'nattevakten')); ?></pre>
        <form method="post">
            <?php wp_nonce_field('nattevakten_clear_log'); ?>
            <input type="submit" name="nattevakten_clear_log" class="button" value="<?php esc_attr_e('Tøm logg', 'nattevakten'); ?>">
        </form>
    </div>
    <?php
    if (isset($_POST['nattevakten_clear_log']) && check_admin_referer('nattevakten_clear_log') && current_user_can('manage_options')) {
        nattevakten_tøm_logg();
        wp_redirect(admin_url('admin.php?page=nattevakten_error_log'));
        exit;
    }
}

// Tidligere feil
function nattevakten_prev_errors_page() {
    $errors = nattevakten_hent_siste_feil(50);
    ?>
    <div class="wrap">
        <h1>📚 Tidligere feil</h1>
        <ul>
            <?php foreach ($errors as $e): ?>
                <li><?php echo esc_html("[{$e['tid']}] {$e['modul']} – {$e['type']} – {$e['beskrivelse']}"); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php
}

// Testkjør side
function nattevakten_test_page() {
    ?>
    <div class="wrap">
        <h1>🛠️ Testkjør</h1>
        <form method="post">
            <?php wp_nonce_field('nattevakt_test_nonce'); ?>
            <input type="submit" name="run_test" class="button button-primary" value="<?php esc_attr_e('Kjør generering nå', 'nattevakten'); ?>">
        </form>
    </div>
    <?php
    if (isset($_POST['run_test']) && check_admin_referer('nattevakt_test_nonce') && current_user_can('manage_options')) {
        $result = nattevakten_generate_news();
        $class = $result === true ? 'notice-success' : 'notice-error';
        $messages = [
            'missing_json_files'   => __('JSON-filer mangler', 'nattevakten'),
            'invalid_json'         => __('Ugyldig JSON-format', 'nattevakten'),
            'empty'                => __('Ingen nyheter ble generert', 'nattevakten'),
            'json_encode_failed'   => __('Kunne ikke serialisere til JSON', 'nattevakten'),
            'write_failed'         => __('Kunne ikke skrive nattavis.json', 'nattevakten'),
            'exception'            => __('Uventet unntak oppstod', 'nattevakten'),
            true                   => __('Nyheter generert!', 'nattevakten'),
            false                  => __('Ukjent feil', 'nattevakten'),
        ];
        $message = $messages[$result] ?? __('Ukjent respons', 'nattevakten');
        echo '<div class="notice ' . esc_attr($class) . ' is-dismissible'><p>' . esc_html($message) . '</p></div>';
    }
}

// Modulstatus-side
function nattevakten_module_status_page() {
    $mangler = function_exists('nattevakten_verify_module_integrity') ? nattevakten_verify_module_integrity() : [];
    $fix_resultat = null;

    if (isset($_POST['nattevakten_run_fix']) && check_admin_referer('nattevakten_run_fix') && current_user_can('manage_options')) {
        $fix_resultat = nattevakten_auto_fixer();
    }
    ?>
    <div class="wrap">
        <h1>📊 Modulstatus</h1>
        <p><?php echo empty($mangler) ? __('Alle moduler er tilgjengelige.', 'nattevakten') : __('Følgende moduler mangler:', 'nattevakten'); ?></p>
        <ul>
            <?php foreach ($mangler as $fil): ?>
                <li style="color: <?php echo $fil['kritisk'] ? 'red' : 'orange'; ?>;">
                    <?php echo esc_html("{$fil['fil']} (" . ($fil['kritisk'] ? 'kritisk' : 'valgfri') . ")"); ?>
                </li>
            <?php endforeach; ?>
        </ul>

        <?php if (is_array($fix_resultat)): ?>
            <h2>🔧 Resultat fra autofiks:</h2>
            <ul>
                <?php foreach ($fix_resultat as $res): ?>
                    <li>
                        <?php echo esc_html("{$res['fil']} – {$res['status']} kl. {$res['tid']}"); ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <form method="post">
            <?php wp_nonce_field('nattevakten_run_fix'); ?>
            <input type="submit" name="nattevakten_run_fix" class="button button-secondary" value="<?php esc_attr_e('Kjør autofiks nå', 'nattevakten'); ?>">
        </form>
    </div>
    <?php
}

// Fallback-side
function nattevakten_fallback_page() {
    $fallback = nattevakten_fallback_news();
    ?>
    <div class="wrap">
        <h1>🔄 Fallback-senter</h1>
        <ul>
            <?php foreach ($fallback as $item): ?>
                <li><?php echo esc_html("{$item['tid']} – {$item['tekst']}"); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php
}

// Dashboard-widget
function nattevakten_add_dashboard_widget() {
    wp_add_dashboard_widget(
        'nattevakten_dashboard_widget',
        __('🕯 Nattevakten – Siste generering', 'nattevakten'),
        'nattevakten_render_dashboard_widget'
    );
}

function nattevakten_render_dashboard_widget() {
    if (isset($_POST['nattevakten_generate_now']) && check_admin_referer('nattevakten_generate_now')) {
        if (function_exists('nattevakten_generate_news')) {
            $success = nattevakten_generate_news();
            echo '<div class="notice ' . ($success ? 'notice-success' : 'notice-error') . ' is-dismissible"><p>' . ($success ? __('Nyheter generert.', 'nattevakten') : __('Generering feilet.', 'nattevakten')) . '</p></div>';
        }
    }

    $file = NATTEVAKTEN_JSON_PATH . 'nattavis.json';
    if (!file_exists($file)) {
        echo '<p><em>' . esc_html__('Ingen nyheter generert enda.', 'nattevakten') . '</em></p>';
    } else {
        $raw = file_get_contents($file);
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            echo '<p><em>' . esc_html__('Ugyldig dataformat for nattavis.json.', 'nattevakten') . '</em></p>';
        } elseif (empty($data)) {
            echo '<p><em>' . esc_html__('Ingen nyheter generert enda.', 'nattevakten') . '</em></p>';
        } else {
            echo '<ul style="padding-left:1.2em">';
            foreach (array_slice($data, 0, 5) as $nyhet) {
                echo '<li>' . esc_html($nyhet['tid'] . ' – ' . $nyhet['tekst']) . '</li>';
            }
            echo '</ul>';
        }
    }

    echo '<form method="post">';
    wp_nonce_field('nattevakten_generate_now');
    echo '<input type="submit" name="nattevakten_generate_now" class="button button-primary" value="' . esc_attr__('Generer nyheter nå', 'nattevakten') . '"> ';
    echo '<a href="admin.php?page=nattevakten" class="button">' . esc_html__('Gå til Nattevakten', 'nattevakten') . '</a>';
    echo '</form>';
}
