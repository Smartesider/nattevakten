<?php
/**
 * Automatisk fixer-modul for Nattevakten
 * Utfører selvreparasjon og gjenoppretter tapte json- eller config-filer
 * Updated to handle new JSON data structure for enhanced Pjuskeby context
 */

function nattevakten_auto_fixer() {
    // Updated file list with new JSON structure and comprehensive defaults
    $filer = [
        [
            'fil' => 'nattavis.json',    
            'default' => [],
            'kritisk' => false,
            'beskrivelse' => 'Genererte nyheter (tom ved oppstart)'
        ],
        [
            'fil' => 'redaksjonen.json', 
            'default' => [
                'hovedperson' => 'Kåre Bjarne',
                'rolle' => 'Nattevakt',
                'beskrivelse' => 'Nattevakt og lokaljournalist i Pjuskeby',
                'arbeidssted' => 'Nattevakten Redaksjon',
                'erfaring' => '15 år som journalist',
                'spesialområde' => 'Lokalnyheter og samfunnsreportasjer'
            ],
            'kritisk' => true,
            'beskrivelse' => 'Redaksjonsinformasjon og hovedperson'
        ],
        [
            'fil' => 'bedrifter.json',
            'default' => [
                [
                    'navn' => 'Pjuskeby Bakeri',
                    'type' => 'bakeri',
                    'aktivitet' => 'baker brød og kaker',
                    'eier' => 'Solveig Bakersen',
                    'ansatte' => 3,
                    'adresse' => 'Storveien 15',
                    'etablert' => '1987'
                ],
                [
                    'navn' => 'Regnskap & Regn AS',
                    'type' => 'regnskapsbyrå',
                    'aktivitet' => 'regnskapsføring og rådgivning',
                    'eier' => 'Olav Tallknuser',
                    'ansatte' => 2,
                    'adresse' => 'Lilleveien 8',
                    'etablert' => '1995'
                ],
                [
                    'navn' => 'Pjuskeby Postkontor',
                    'type' => 'offentlig tjeneste',
                    'aktivitet' => 'postlevering og pakketjenester',
                    'eier' => 'Statens Postservice',
                    'ansatte' => 1,
                    'adresse' => 'Torget 1',
                    'etablert' => '1923'
                ],
                [
                    'navn' => 'Kåres Sykkelservice',
                    'type' => 'verksted',
                    'aktivitet' => 'sykkelreparasjoner',
                    'eier' => 'Kåre Hjulsen',
                    'ansatte' => 1,
                    'adresse' => 'Skogsveien 22',
                    'etablert' => '2010'
                ],
                [
                    'navn' => 'Pjuskeby Minimarked',
                    'type' => 'dagligvarehandel',
                    'aktivitet' => 'salg av dagligvarer',
                    'eier' => 'Familie Handelsen',
                    'ansatte' => 4,
                    'adresse' => 'Storveien 3',
                    'etablert' => '1978'
                ]
            ],
            'kritisk' => true,
            'beskrivelse' => 'Bedrifter og næringsliv i Pjuskeby'
        ],
        [
            'fil' => 'gatenavn.json',
            'default' => [
                [
                    'gate' => 'Storveien',
                    'nummer' => '12',
                    'beboer' => 'Kåre Bjarne',
                    'type' => 'enebolig',
                    'bygget' => '1965'
                ],
                [
                    'gate' => 'Lilleveien',
                    'nummer' => '5',
                    'beboer' => 'Solveig Bakersen',
                    'type' => 'rekkehus',
                    'bygget' => '1982'
                ],
                [
                    'gate' => 'Skogsveien',
                    'nummer' => '18',
                    'beboer' => 'Olav Tallknuser',
                    'type' => 'villa',
                    'bygget' => '1990'
                ],
                [
                    'gate' => 'Fjellveien',
                    'nummer' => '3',
                    'beboer' => 'Astrid Blomkvist',
                    'type' => 'leilighet',
                    'bygget' => '1975'
                ],
                [
                    'gate' => 'Storveien',
                    'nummer' => '24',
                    'beboer' => 'Per og Berit Haugen',
                    'type' => 'tomannsbolig',
                    'bygget' => '1988'
                ],
                [
                    'gate' => 'Kirkegata',
                    'nummer' => '7',
                    'beboer' => 'Gunnar Kirkebø',
                    'type' => 'enebolig',
                    'bygget' => '1954'
                ]
            ],
            'kritisk' => true,
            'beskrivelse' => 'Gatenavn, adresser og beboere'
        ],
        [
            'fil' => 'innsjo.json',
            'default' => [
                [
                    'navn' => 'Pjuskevatnet',
                    'type' => 'innsjø',
                    'størrelse' => '2.3 km²',
                    'dybde' => '45 meter',
                    'beskrivelse' => 'Det største vatnet i området med krystalklart vann',
                    'aktiviteter' => ['fisking', 'bading', 'båtturer', 'is-skating om vinteren'],
                    'fisk' => ['ørret', 'abbor', 'gjedde']
                ],
                [
                    'navn' => 'Lille Tjern',
                    'type' => 'tjern',
                    'størrelse' => '0.1 km²',
                    'dybde' => '8 meter',
                    'beskrivelse' => 'Et pittoresk lite tjern i skogen, populært for turgåere',
                    'aktiviteter' => ['fisking', 'fugletitting', 'naturopplevelser'],
                    'fisk' => ['ørret']
                ],
                [
                    'navn' => 'Dypeløkka',
                    'type' => 'dam',
                    'størrelse' => '0.05 km²',
                    'dybde' => '15 meter',
                    'beskrivelse' => 'Gammel mølledam fra 1800-tallet, nå et fredelig naturområde',
                    'aktiviteter' => ['historiske turer', 'fotografering'],
                    'historie' => 'Bygget i 1847 for Pjuskeby Mølle'
                ],
                [
                    'navn' => 'Bekken',
                    'type' => 'elv',
                    'lengde' => '12 km',
                    'beskrivelse' => 'Liten elv som renner gjennom Pjuskeby sentrum',
                    'aktiviteter' => ['ørretfiske', 'kanopadling'],
                    'fisk' => ['ørret', 'harr']
                ]
            ],
            'kritisk' => false,
            'beskrivelse' => 'Innsjøer, tjern og vannområder'
        ],
        [
            'fil' => 'rundtpjuskeby.json',
            'default' => [
                [
                    'navn' => 'Høyfjell',
                    'type' => 'fjellområde',
                    'avstand' => '15 km nord',
                    'høyde' => '1247 moh',
                    'beskrivelse' => 'Populært turområde med flott utsikt over hele distriktet',
                    'aktiviteter' => ['fjellvandring', 'bærplukking', 'skigåing'],
                    'sesong' => 'hele året'
                ],
                [
                    'navn' => 'Granskog',
                    'type' => 'skogsområde',
                    'avstand' => '8 km øst',
                    'størrelse' => '45 km²',
                    'beskrivelse' => 'Tett granskog med rike elgbestander og mange turløyper',
                    'aktiviteter' => ['jakt', 'sopp-/bærplukking', 'skigåing'],
                    'dyreliv' => ['elg', 'rådyr', 'rev', 'tiur']
                ],
                [
                    'navn' => 'Slettebygd',
                    'type' => 'naboby',
                    'avstand' => '25 km sør',
                    'innbyggere' => 850,
                    'beskrivelse' => 'Nærliggende tettsted med utvidet handel og servicetilbud',
                    'tilbud' => ['videregående skole', 'sykehus', 'kjøpesenter'],
                    'transport' => ['buss hver time', 'tog 3 ganger daglig']
                ],
                [
                    'navn' => 'Kystbygda',
                    'type' => 'fiskevær',
                    'avstand' => '40 km vest',
                    'innbyggere' => 320,
                    'beskrivelse' => 'Pittoresk fiskevær ved kysten, populært turistmål',
                    'aktiviteter' => ['fiske', 'båtturer', 'sjømat-restauranter'],
                    'spesialitet' => 'fersk torsk og reker'
                ]
            ],
            'kritisk' => false,
            'beskrivelse' => 'Nærliggende områder og nabobygder'
        ],
        [
            'fil' => 'sport.json',
            'default' => [
                [
                    'tullenavn' => 'Ekstrem Brevduva-racing',
                    'ektenavn' => 'Brevdue-konkurranse',
                    'sesong' => 'vår/sommer',
                    'deltakere' => ['Kåre Bjarne', 'Postmester Ola', 'Gunnar Kirkebø'],
                    'beskrivelse' => 'Årlig konkurranse i brevdue-hastighet over 50 km',
                    'rekord' => '1 time 23 minutter (Kåres due "Lynet")',
                    'premiering' => 'Gullmedalje og gratis posttjenester i ett år'
                ],
                [
                    'tullenavn' => 'Kampeloppsett i Rundball',
                    'ektenavn' => 'Fotball',
                    'sesong' => 'hele året',
                    'deltakere' => ['Pjuskeby IL', 'Slettebygd FK'],
                    'beskrivelse' => 'Lokalt fotballag med stor entusiasme og beskjeden ferdighet',
                    'hjemmebane' => 'Gressletta bak Minimarkedet',
                    'siste_seier' => '2019 mot Slettebygd (2-1)'
                ],
                [
                    'tullenavn' => 'Vinterslalåm på Søppelposer',
                    'ektenavn' => 'Improvisert slalåm',
                    'sesong' => 'vinter',
                    'deltakere' => ['Alle som har søppelposer og ski'],
                    'beskrivelse' => 'Kreativ vintersport med improvisert utstyr på Bakketoppen',
                    'regler' => 'Alt tillatt så lenge du kommer deg ned',
                    'populærhet' => 'Overraskende høy blant ungdommen'
                ],
                [
                    'tullenavn' => 'Ekstrem Hagearbeid-mesterskap',
                    'ektenavn' => 'Hageutstilling',
                    'sesong' => 'sommer',
                    'deltakere' => ['Astrid Blomkvist', 'Per Haugen', 'Solveig Bakersen'],
                    'beskrivelse' => 'Intens konkurranse om hvem som har flest blomster',
                    'kategorier' => ['roser', 'grønnsaker', 'kreativt design'],
                    'premie' => 'Vandrebuste av en gulrot'
                ]
            ],
            'kritisk' => false,
            'beskrivelse' => 'Sportsaktiviteter og konkurranser'
        ],
        [
            'fil' => 'turister.json',
            'default' => [
                [
                    'tullenavn' => 'Verdens Minste Rundkjøring',
                    'ektenavn' => 'Torget',
                    'type' => 'severdighet',
                    'beskrivelse' => 'En sirkel av steiner som teknisk sett kvalifiserer som rundkjøring',
                    'diameter' => '3.2 meter',
                    'besøkstid' => '5 minutter',
                    'instruksjoner' => 'Kjør rundt steinene og vent på applaus',
                    'rekord' => '47 runder på rad (Olav Tallknuser, 2018)'
                ],
                [
                    'tullenavn' => 'Det Skjeve Huset',
                    'ektenavn' => 'Gamle Rådhus',
                    'type' => 'historisk bygning',
                    'beskrivelse' => 'Bygningen heller 15 grader men brukes fortsatt til kommunestyremøter',
                    'bygget' => '1892',
                    'helningsgrad' => '15 grader mot øst',
                    'besøkstid' => '20 minutter',
                    'spesialitet' => 'Alle møter blir mer interessante når alt ruller til høyre'
                ],
                [
                    'tullenavn' => 'Mystiske Steinsirkelen',
                    'ektenavn' => 'Steinalderboplass',
                    'type' => 'arkeologisk',
                    'beskrivelse' => 'Gammel steinplassering av ukjent opprinnelse (eller Gunnar som bygde steinmur)',
                    'alder' => 'Ukjent (muligens 2003)',
                    'diameter' => '8 meter',
                    'besøkstid' => '30 minutter',
                    'mysterium' => 'Ingen vet hvem som bygde den (bortsett fra Gunnar)'
                ],
                [
                    'tullenavn' => 'Verdens Stilleste Fossefall',
                    'ektenavn' => 'Bekken ved Brua',
                    'type' => 'naturattraksjon',
                    'beskrivelse' => 'Et 30 cm høyt fossefall som knapt lager lyd',
                    'høyde' => '30 cm',
                    'bredde' => '1.2 meter',
                    'besøkstid' => '10 minutter',
                    'lyd' => 'Nærmest uhørlig plask'
                ]
            ],
            'kritisk' => false,
            'beskrivelse' => 'Turistattraksjoner og severdigheter'
        ],
        [
            'fil' => 'stederipjuskeby.json',
            'default' => [
                [
                    'sted' => 'Torget',
                    'description' => 'Sentrum av Pjuskeby med blomsterbed, benker og den berømte mini-rundkjøringen',
                    'type' => 'offentlig område',
                    'størrelse' => '200 m²',
                    'aktiviteter' => ['markedsdager', 'sosiale møter', 'rundkjøring-testing'],
                    'fasiliteter' => ['3 benker', '1 søppelbøtte', '1 mini-rundkjøring']
                ],
                [
                    'sted' => 'Pjuskeby Bibliotek',
                    'description' => 'Lille bygning med store vinduer, mange bøker og overraskende god kaffe',
                    'type' => 'kulturinstitusjon',
                    'størrelse' => '85 m²',
                    'samling' => '2847 bøker',
                    'aktiviteter' => ['lesing', 'bokklubber', 'foredrag', 'kaffeservering'],
                    'åpningstider' => 'Tirsdag-fredag 10-16, lørdag 10-13'
                ],
                [
                    'sted' => 'Gammelskogen',
                    'description' => 'Eldgammel skog like utenfor sentrum med 400 år gamle grantrær',
                    'type' => 'naturområde',
                    'størrelse' => '12 hektar',
                    'eldste_tre' => '412 år (Bestefar-granen)',
                    'aktiviteter' => ['turgåing', 'bær- og soppleting', 'fuglekikking'],
                    'stier' => '3 merka løyper'
                ],
                [
                    'sted' => 'Pjuskeby Stasjon',
                    'description' => 'Nedlagt jernbanestasjon fra 1923, nå omgjort til koselig kafé',
                    'type' => 'historisk/kafé',
                    'bygget' => '1923',
                    'stengt_som_stasjon' => '1987',
                    'gjenåpnet_som_kafé' => '2015',
                    'aktiviteter' => ['kaffe', 'hjemmelaget kake', 'nostalgi', 'togspotting'],
                    'spesialitet' => 'Konduktør-kaffe og stasjonsmester-kake'
                ],
                [
                    'sted' => 'Bakketoppen',
                    'description' => 'Høyeste punkt i Pjuskeby med utsikt over hele byen og omkringliggende områder',
                    'type' => 'utsiktspunkt',
                    'høyde' => '387 moh',
                    'utsikt' => 'Hele Pjuskeby, Pjuskevatnet og Høyfjell',
                    'aktiviteter' => ['turgåing', 'fotografering', 'søppelpose-slalåm om vinteren'],
                    'tilgang' => '1.2 km sti fra Skogsveien'
                ],
                [
                    'sted' => 'Pjuskeby Idrettsplass',
                    'description' => 'Gressbane med kridhvite linjer (etter at Kåre måka snøen), tribuneplass for 47 personer',
                    'type' => 'idrettsanlegg',
                    'størrelse' => '100x60 meter',
                    'tribuneplasser' => '47 (inkludert en liggestol)',
                    'aktiviteter' => ['fotball', 'friidrett', 'årlige 17. mai-konkurranser'],
                    'besonderhet' => 'Mål-stolpene er malt i forskjellige farger (blå og gul)'
                ]
            ],
            'kritisk' => false,
            'beskrivelse' => 'Viktige steder og lokasjoner i Pjuskeby'
        ]
    ];

    $resultater = [];

    foreach ($filer as $info) {
        $sti = NATTEVAKTEN_JSON_PATH . $info['fil'];
        $status = 'ok';

        if (!file_exists($sti) || !is_readable($sti)) {
            // Create directory if it doesn't exist
            if (wp_mkdir_p(dirname($sti))) {
                $json_content = wp_json_encode($info['default'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                if (file_put_contents($sti, $json_content) !== false) {
                    $status = 'opprettet';
                    nattevakten_log_error('auto_fixer', 'file_created', 
                        sprintf(__('Opprettet manglende fil: %s', 'nattevakten'), $info['fil']), 'info');
                } else {
                    $status = 'feilet';
                    nattevakten_log_error('auto_fixer', 'create_failed', 
                        sprintf(__('Kunne ikke opprette fil: %s', 'nattevakten'), $info['fil']), 'error');
                }
            } else {
                $status = 'feilet - katalog';
                nattevakten_log_error('auto_fixer', 'directory_failed', 
                    sprintf(__('Kunne ikke opprette katalog for: %s', 'nattevakten'), $info['fil']), 'error');
            }
        } else {
            // Validate existing file
            $innhold = file_get_contents($sti);
            $decoded = json_decode($innhold, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                // Fix corrupted JSON
                $json_content = wp_json_encode($info['default'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                if (file_put_contents($sti, $json_content) !== false) {
                    $status = 'reparert';
                    nattevakten_log_error('auto_fixer', 'json_repaired', 
                        sprintf(__('Reparerte korrupt JSON: %s', 'nattevakten'), $info['fil']), 'info');
                } else {
                    $status = 'feilet - reparasjon';
                }
            } elseif (empty($decoded) && !empty($info['default'])) {
                // File exists but is empty, populate with default
                $json_content = wp_json_encode($info['default'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                if (file_put_contents($sti, $json_content) !== false) {
                    $status = 'populert';
                    nattevakten_log_error('auto_fixer', 'file_populated', 
                        sprintf(__('Populerte tom fil: %s', 'nattevakten'), $info['fil']), 'info');
                }
            } else {
                // Additional validation for specific file types
                $validation_result = nattevakten_validate_json_file_structure_fix($sti, $info['fil']);
                if (!$validation_result['valid']) {
                    // File has structural issues, merge with defaults
                    $merged_data = nattevakten_merge_with_defaults($decoded, $info['default'], $info['fil']);
                    $json_content = wp_json_encode($merged_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                    if (file_put_contents($sti, $json_content) !== false) {
                        $status = 'forbedret';
                        nattevakten_log_error('auto_fixer', 'structure_improved', 
                            sprintf(__('Forbedret struktur: %s', 'nattevakten'), $info['fil']), 'info');
                    }
                }
            }
        }

        $resultater[] = [
            'fil' => $info['fil'],
            'status' => $status,
            'tid' => current_time('mysql'),
            'kritisk' => $info['kritisk'] ?? false,
            'beskrivelse' => $info['beskrivelse'] ?? ''
        ];
    }

    // Clean up old deprecated files
    $deprecated_files = ['pjuskeby.json', 'land.json', 'organisasjoner.json'];
    foreach ($deprecated_files as $old_filename) {
        $old_file = NATTEVAKTEN_JSON_PATH . $old_filename;
        if (file_exists($old_file)) {
            // Create backup before deletion
            $backup_dir = NATTEVAKTEN_JSON_PATH . 'backup/';
            wp_mkdir_p($backup_dir);
            $backup_file = $backup_dir . $old_filename . '.' . date('Ymd_His') . '.bak';
            
            if (copy($old_file, $backup_file) && unlink($old_file)) {
                $resultater[] = [
                    'fil' => $old_filename . ' (deprecated)',
                    'status' => 'slettet (backup laget)',
                    'tid' => current_time('mysql'),
                    'kritisk' => false,
                    'beskrivelse' => 'Foreldet fil fjernet'
                ];
                nattevakten_log_error('auto_fixer', 'deprecated_file_removed', 
                    sprintf(__('Fjernet foreldet fil: %s (backup laget)', 'nattevakten'), $old_filename), 'info');
            }
        }
    }

    return $resultater;
}

function nattevakten_check_module_integrity() {
    // Updated list of required files with enhanced structure
    $required_files = [
        NATTEVAKTEN_JSON_PATH . 'bedrifter.json',
        NATTEVAKTEN_JSON_PATH . 'gatenavn.json', 
        NATTEVAKTEN_JSON_PATH . 'innsjo.json',
        NATTEVAKTEN_JSON_PATH . 'rundtpjuskeby.json',
        NATTEVAKTEN_JSON_PATH . 'sport.json',
        NATTEVAKTEN_JSON_PATH . 'turister.json',
        NATTEVAKTEN_JSON_PATH . 'stederipjuskeby.json',
        NATTEVAKTEN_JSON_PATH . 'redaksjonen.json'
    ];
    
    $critical_files = [
        'bedrifter.json',
        'gatenavn.json',
        'redaksjonen.json'
    ];
    
    $issues = [];
    foreach ($required_files as $file) {
        $filename = basename($file);
        $is_critical = in_array($filename, $critical_files);
        
        if (!file_exists($file)) {
            $issues[] = [
                'file' => $filename,
                'critical' => $is_critical,
                'issue' => 'missing',
                'path' => $file
            ];
        } elseif (!is_readable($file)) {
            $issues[] = [
                'file' => $filename,
                'critical' => true, // Always critical if unreadable
                'issue' => 'unreadable',
                'path' => $file
            ];
        } else {
            // Check JSON validity and structure
            $validation = nattevakten_validate_json_file_structure_fix($file, $filename);
            if (!$validation['valid']) {
                $issues[] = [
                    'file' => $filename,
                    'critical' => $is_critical,
                    'issue' => 'invalid_structure',
                    'error' => $validation['error'],
                    'path' => $file
                ];
            }
        }
    }
    
    return $issues;
}

/**
 * Enhanced JSON file structure validation
 */
function nattevakten_validate_json_file_structure_fix($filepath, $filename) {
    $content = file_get_contents($filepath);
    $data = json_decode($content, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        return ['valid' => false, 'error' => 'Invalid JSON: ' . json_last_error_msg()];
    }
    
    // Specific validation for each file type
    switch ($filename) {
        case 'bedrifter.json':
            if (!is_array($data)) {
                return ['valid' => false, 'error' => 'Must be an array'];
            }
            foreach ($data as $index => $item) {
                if (!is_array($item) || empty($item['navn'])) {
                    return ['valid' => false, 'error' => "Item $index missing required field: navn"];
                }
            }
            break;
            
        case 'gatenavn.json':
            if (!is_array($data)) {
                return ['valid' => false, 'error' => 'Must be an array'];
            }
            foreach ($data as $index => $item) {
                if (!is_array($item) || empty($item['gate'])) {
                    return ['valid' => false, 'error' => "Item $index missing required field: gate"];
                }
            }
            break;
            
        case 'sport.json':
        case 'turister.json':
            if (!is_array($data)) {
                return ['valid' => false, 'error' => 'Must be an array'];
            }
            foreach ($data as $index => $item) {
                if (!is_array($item) || (empty($item['tullenavn']) && empty($item['ektenavn']))) {
                    return ['valid' => false, 'error' => "Item $index missing tullenavn or ektenavn"];
                }
            }
            break;
            
        case 'stederipjuskeby.json':
            if (!is_array($data)) {
                return ['valid' => false, 'error' => 'Must be an array'];
            }
            foreach ($data as $index => $item) {
                if (!is_array($item) || empty($item['sted'])) {
                    return ['valid' => false, 'error' => "Item $index missing required field: sted"];
                }
            }
            break;
            
        case 'redaksjonen.json':
            if (!is_array($data) || empty($data['hovedperson'])) {
                return ['valid' => false, 'error' => 'Must have hovedperson field'];
            }
            break;
            
        case 'innsjo.json':
        case 'rundtpjuskeby.json':
            if (!is_array($data)) {
                return ['valid' => false, 'error' => 'Must be an array'];
            }
            foreach ($data as $index => $item) {
                if (!is_array($item) || empty($item['navn'])) {
                    return ['valid' => false, 'error' => "Item $index missing required field: navn"];
                }
            }
            break;
    }
    
    return ['valid' => true, 'error' => null];
}

/**
 * Merge existing data with defaults to fix structural issues
 */
function nattevakten_merge_with_defaults($existing_data, $default_data, $filename) {
    if (!is_array($existing_data)) {
        return $default_data;
    }
    
    switch ($filename) {
        case 'redaksjonen.json':
            // For redaksjonen, merge fields
            return array_merge($default_data, $existing_data);
            
        case 'bedrifter.json':
        case 'gatenavn.json':
        case 'sport.json':
        case 'turister.json':
        case 'stederipjuskeby.json':
        case 'innsjo.json':
        case 'rundtpjuskeby.json':
            // For arrays, keep existing valid items and add defaults if needed
            $merged = [];
            
            // Add existing valid items
            if (is_array($existing_data)) {
                foreach ($existing_data as $item) {
                    if (is_array($item) && !empty($item)) {
                        $merged[] = $item;
                    }
                }
            }
            
            // If we don't have enough items, add from defaults
            if (count($merged) < 2 && is_array($default_data)) {
                foreach ($default_data as $default_item) {
                    if (count($merged) >= 5) break; // Don't add too many defaults
                    
                    // Check if this default item is already present
                    $exists = false;
                    foreach ($merged as $existing_item) {
                        if (isset($existing_item['navn']) && isset($default_item['navn']) && 
                            $existing_item['navn'] === $default_item['navn']) {
                            $exists = true;
                            break;
                        }
                    }
                    
                    if (!$exists) {
                        $merged[] = $default_item;
                    }
                }
            }
            
            return $merged;
            
        default:
            return !empty($existing_data) ? $existing_data : $default_data;
    }
}

/**
 * Get detailed auto-fixer report
 */
function nattevakten_get_auto_fixer_report() {
    $issues = nattevakten_check_module_integrity();
    
    $report = [
        'timestamp' => current_time('mysql'),
        'version' => NATTEVAKTEN_VERSION,
        'total_issues' => count($issues),
        'critical_issues' => 0,
        'fixable_issues' => 0,
        'issues' => $issues,
        'recommended_actions' => []
    ];
    
    foreach ($issues as $issue) {
        if ($issue['critical']) {
            $report['critical_issues']++;
        }
        
        // Most issues are fixable by auto-fixer
        if (in_array($issue['issue'], ['missing', 'invalid_structure', 'unreadable'])) {
            $report['fixable_issues']++;
        }
    }
    
    // Generate recommendations
    if ($report['critical_issues'] > 0) {
        $report['recommended_actions'][] = __('Kjør auto-fiks umiddelbart for å løse kritiske problemer', 'nattevakten');
    }
    
    if ($report['fixable_issues'] > 0) {
        $report['recommended_actions'][] = __('Auto-fiks kan løse ' . $report['fixable_issues'] . ' av problemene automatisk', 'nattevakten');
    }
    
    if ($report['total_issues'] === 0) {
        $report['recommended_actions'][] = __('Alle filer er i orden - ingen handling nødvendig', 'nattevakten');
    }
    
    return $report;
}
?>