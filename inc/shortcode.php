<?php
/**
 * Shortcode rendering for Nattevakten ticker
 * Enhanced with accessibility and performance
 */

function nattevakten_render_ticker($atts = []) {
    $atts = shortcode_atts([
        'limit' => 5,
        'speed' => 5,
        'interval' => 6000,
        'auto_play' => true,
        'show_controls' => true,
        'aria_live' => 'polite'
    ], $atts, 'nattevakt_nyheter');
    
    // Generate unique ID to avoid conflicts with multiple instances
    $unique_id = 'nattevakt-app-' . wp_rand(1000, 9999);
    
    // Enqueue Vue.js with enhanced security and accessibility
    wp_enqueue_script(
        'vue-nattevakten-' . $unique_id, 
        'https://unpkg.com/vue@3.3.4/dist/vue.global.prod.js', 
        [], 
        '3.3.4', 
        true
    );
    
    // Enhanced accessibility CSS
    $accessibility_css = "
    .nattevakt-ticker-{$unique_id} {
        font-family: 'Courier New', Courier, monospace !important;
        background: linear-gradient(90deg, #222, #333) !important;
        color: #00ff99 !important;
        padding: 1rem !important;
        border: 2px solid #00ff99 !important;
        max-width: 100% !important;
        overflow: hidden !important;
        box-shadow: 0 0 10px #00ff99 !important;
        border-radius: 8px !important;
        transition: all 0.3s ease !important;
        position: relative !important;
        isolation: isolate !important;
    }
    
    .nattevakt-ticker-{$unique_id}:focus-within {
        outline: 3px solid #ffff00 !important;
        outline-offset: 2px !important;
    }
    
    .nattevakt-ticker-{$unique_id} .ticker-content {
        font-size: clamp(0.9rem, 2.5vw, 1.1rem) !important;
        line-height: 1.4 !important;
        padding: 0.5rem 0 !important;
    }
    
    .nattevakt-ticker-{$unique_id} .ticker-btn {
        min-width: 44px !important;
        min-height: 44px !important;
        font-size: 1.2rem !important;
        border-radius: 4px !important;
        transition: all 0.2s ease !important;
    }
    
    .nattevakt-ticker-{$unique_id} .ticker-btn:focus {
        outline: 2px solid #ffff00 !important;
        outline-offset: 2px !important;
    }
    
    .nattevakt-ticker-{$unique_id} .ticker-btn:disabled {
        opacity: 0.5 !important;
        cursor: not-allowed !important;
    }
    
    @media (prefers-reduced-motion: reduce) {
        .nattevakt-ticker-{$unique_id} .ticker-content {
            animation: none !important;
        }
    }
    
    @media (prefers-color-scheme: dark) {
        .nattevakt-ticker-{$unique_id} {
            background: #111 !important;
            color: #66ffcc !important;
            border-color: #66ffcc !important;
        }
    }
    
    @media (max-width: 768px) {
        .nattevakt-ticker-{$unique_id} {
            padding: 0.75rem !important;
        }
        .nattevakt-ticker-{$unique_id} .ticker-content {
            font-size: 0.9rem !important;
        }
    }
    ";
    
    wp_add_inline_style('wp-block-library', $accessibility_css);
    
    // Enhanced keyboard navigation and screen reader support
    ob_start();
    ?>
    <div id="<?php echo esc_attr($unique_id); ?>" 
         class="nattevakt-ticker-container nattevakt-ticker-<?php echo esc_attr($unique_id); ?>"
         role="region" 
         aria-label="<?php echo esc_attr__('Lokalnyheter fra Nattevakten', 'nattevakten'); ?>"
         tabindex="0">
        
        <div v-if="nyheter.length" class="ticker">
            <div class="ticker-content" 
                 :class="{ 'ticker-scrolling': isScrolling && autoPlay }"
                 @mouseenter="pauseScroll" 
                 @mouseleave="resumeScroll"
                 @focus="pauseScroll"
                 @blur="resumeScroll"
                 role="marquee"
                 :aria-live="ariaLive"
                 :aria-atomic="true"
                 :aria-label="'<?php echo esc_js(__('Aktuell nyhet:', 'nattevakten')); ?> ' + currentNewsText"
                 tabindex="0">
                <span v-html="sanitizedText"></span>
            </div>
            
            <div v-if="nyheter.length > 1 && showControls" 
                 class="ticker-controls" 
                 role="toolbar" 
                 :aria-label="'<?php echo esc_js(__('Nyhetskontroller', 'nattevakten')); ?>'">
                 
                <button @click="previousNews" 
                        @keydown.enter="previousNews"
                        @keydown.space.prevent="previousNews"
                        class="ticker-btn" 
                        type="button"
                        :aria-label="'<?php echo esc_js(__('Forrige nyhet', 'nattevakten')); ?>'"
                        :disabled="loading || nyheter.length <= 1"
                        tabindex="0">‹</button>
                        
                <button @click="toggleAutoPlay"
                        @keydown.enter="toggleAutoPlay"
                        @keydown.space.prevent="toggleAutoPlay"
                        class="ticker-btn ticker-play-pause"
                        type="button"
                        :aria-label="autoPlay ? '<?php echo esc_js(__('Pause automatisk avspilling', 'nattevakten')); ?>' : '<?php echo esc_js(__('Start automatisk avspilling', 'nattevakten')); ?>'"
                        :disabled="loading"
                        tabindex="0">{{ autoPlay ? '⏸' : '▶' }}</button>
                        
                <span class="ticker-indicator" 
                      :aria-live="ariaLive"
                      :aria-atomic="true"
                      role="status">{{ aktivIndex + 1 }}/{{ nyheter.length }}</span>
                      
                <button @click="nextNews" 
                        @keydown.enter="nextNews"
                        @keydown.space.prevent="nextNews"
                        class="ticker-btn" 
                        type="button"
                        :aria-label="'<?php echo esc_js(__('Neste nyhet', 'nattevakten')); ?>'"
                        :disabled="loading || nyheter.length <= 1"
                        tabindex="0">›</button>
            </div>
        </div>
        
        <div v-else-if="loading" 
             class="ticker-loading" 
             role="status" 
             :aria-live="ariaLive"
             aria-atomic="true">
            <span class="loading-spinner" aria-hidden="true">⏳</span>
            <?php echo esc_html__('Laster nyheter...', 'nattevakten'); ?>
        </div>
        
        <div v-else 
             class="ticker-error" 
             role="alert" 
             :aria-live="ariaLive"
             aria-atomic="true">
            <span class="error-icon" aria-hidden="true">⚠️</span>
            <?php echo esc_html__('Ingen nyheter tilgjengelig.', 'nattevakten'); ?>
        </div>
    </div>
    
    <script>
    (function() {
        'use strict';
        
        const tickerNamespace = 'nattevakt_<?php echo esc_js($unique_id); ?>';
        
        function initializeTicker() {
            if (typeof Vue === 'undefined') {
                console.error('<?php echo esc_js(__('Vue.js ikke lastet - ticker kan ikke initialiseres', 'nattevakten')); ?>');
                return;
            }
            
            const { createApp } = Vue;
            
            const app = createApp({
                data() {
                    return { 
                        nyheter: [], 
                        aktivIndex: 0,
                        loading: true,
                        isScrolling: false,
                        intervalId: null,
                        scrollTimeoutId: null,
                        isDestroyed: false,
                        autoPlay: <?php echo $atts['auto_play'] ? 'true' : 'false'; ?>,
                        showControls: <?php echo $atts['show_controls'] ? 'true' : 'false'; ?>,
                        ariaLive: '<?php echo esc_js($atts['aria_live']); ?>',
                        lastAnnouncedIndex: -1,
                        prefersReducedMotion: window.matchMedia('(prefers-reduced-motion: reduce)').matches
                    };
                },
                computed: {
                    sanitizedText() {
                        if (!this.nyheter[this.aktivIndex]) return '';
                        const text = this.nyheter[this.aktivIndex].tekst || '';
                        
                        // Comprehensive XSS protection with HTML entity encoding
                        return text
                            .replace(/&/g, '&amp;')
                            .replace(/</g, '&lt;')
                            .replace(/>/g, '&gt;')
                            .replace(/"/g, '&quot;')
                            .replace(/'/g, '&#x27;')
                            .replace(/\//g, '&#x2F;');
                    },
                    currentNewsText() {
                        return this.sanitizedText;
                    }
                },
                watch: {
                    aktivIndex(newIndex, oldIndex) {
                        // Announce news changes to screen readers
                        if (newIndex !== this.lastAnnouncedIndex && this.nyheter[newIndex]) {
                            this.announceToScreenReader(this.nyheter[newIndex].tekst);
                            this.lastAnnouncedIndex = newIndex;
                        }
                    }
                },
                mounted() {
                    this.loadNews();
                    if (this.autoPlay && !this.prefersReducedMotion) {
                        this.startAutoScroll();
                    }
                    
                    // Listen for reduced motion changes
                    const motionQuery = window.matchMedia('(prefers-reduced-motion: reduce)');
                    motionQuery.addEventListener('change', (e) => {
                        this.prefersReducedMotion = e.matches;
                        if (e.matches) {
                            this.stopAutoScroll();
                        } else if (this.autoPlay) {
                            this.startAutoScroll();
                        }
                    });
                    
                    // Keyboard navigation
                    this.$el.addEventListener('keydown', this.handleKeydown);
                },
                beforeUnmount() {
                    this.cleanup();
                },
                unmounted() {
                    this.cleanup();
                },
                methods: {
                    cleanup() {
                        this.isDestroyed = true;
                        this.stopAutoScroll();
                        if (this.scrollTimeoutId) {
                            clearTimeout(this.scrollTimeoutId);
                            this.scrollTimeoutId = null;
                        }
                        
                        if (this.$el) {
                            this.$el.removeEventListener('keydown', this.handleKeydown);
                        }
                    },
                    
                    handleKeydown(event) {
                        // Keyboard navigation support
                        switch(event.key) {
                            case 'ArrowLeft':
                                event.preventDefault();
                                this.previousNews();
                                break;
                            case 'ArrowRight':
                                event.preventDefault();
                                this.nextNews();
                                break;
                            case ' ':
                            case 'Spacebar':
							event.preventDefault();
                                this.toggleAutoPlay();
                                break;
                            case 'Home':
                                event.preventDefault();
                                this.aktivIndex = 0;
                                break;
                            case 'End':
                                event.preventDefault();
                                this.aktivIndex = this.nyheter.length - 1;
                                break;
                        }
                    },
                    
                    announceToScreenReader(text) {
                        // Create temporary element for screen reader announcement
                        const announcement = document.createElement('div');
                        announcement.setAttribute('aria-live', 'polite');
                        announcement.setAttribute('aria-atomic', 'true');
                        announcement.style.position = 'absolute';
                        announcement.style.left = '-10000px';
                        announcement.style.width = '1px';
                        announcement.style.height = '1px';
                        announcement.style.overflow = 'hidden';
                        
                        document.body.appendChild(announcement);
                        
                        setTimeout(() => {
                            announcement.textContent = '<?php echo esc_js(__('Ny nyhet:', 'nattevakten')); ?> ' + text;
                            
                            setTimeout(() => {
                                document.body.removeChild(announcement);
                            }, 1000);
                        }, 100);
                    },
                    
                    async loadNews() {
                        if (this.isDestroyed) return;
                        
                        const controller = new AbortController();
                        const timeoutId = setTimeout(() => controller.abort(), 15000); // 15 second timeout
                        
                        try {
                            const response = await fetch('<?php echo esc_url(rest_url('nattevakten/v1/data')); ?>', {
                                headers: {
                                    'X-WP-Nonce': '<?php echo wp_create_nonce('wp_rest'); ?>'
                                },
                                signal: controller.signal
                            });
                            
                            clearTimeout(timeoutId);
                            
                            if (!response.ok) {
                                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                            }
                            
                            const data = await response.json();
                            
                            if (this.isDestroyed) return;
                            
                            if (Array.isArray(data) && data.length > 0) {
                                this.nyheter = data.slice(0, <?php echo intval($atts['limit']); ?>);
                            } else if (data.fallback && Array.isArray(data.fallback)) {
                                this.nyheter = data.fallback.slice(0, <?php echo intval($atts['limit']); ?>);
                            } else {
                                this.nyheter = [{
                                    tekst: '<?php echo esc_js(__('Ingen nyheter tilgjengelig for øyeblikket.', 'nattevakten')); ?>'
                                }];
                            }
                            
                            // Announce first news item
                            if (this.nyheter.length > 0) {
                                this.announceToScreenReader(this.nyheter[0].tekst);
                            }
                            
                        } catch (error) {
                            if (this.isDestroyed) return;
                            
                            console.error('<?php echo esc_js(__('Feil ved lasting av nyheter:', 'nattevakten')); ?>', error);
                            this.nyheter = [{
                                tekst: '<?php echo esc_js(__('Kunne ikke laste nyheter.', 'nattevakten')); ?>'
                            }];
                        } finally {
                            clearTimeout(timeoutId);
                            if (!this.isDestroyed) {
                                this.loading = false;
                            }
                        }
                    },
                    
                    startAutoScroll() {
                        if (this.isDestroyed || this.nyheter.length <= 1 || this.intervalId || this.prefersReducedMotion) return;
                        
                        this.isScrolling = true;
                        this.intervalId = setInterval(() => {
                            if (!this.isDestroyed && this.autoPlay) {
                                this.nextNews();
                            }
                        }, <?php echo intval($atts['interval']); ?>);
                    },
                    
                    stopAutoScroll() {
                        this.isScrolling = false;
                        if (this.intervalId) {
                            clearInterval(this.intervalId);
                            this.intervalId = null;
                        }
                    },
                    
                    toggleAutoPlay() {
                        this.autoPlay = !this.autoPlay;
                        
                        if (this.autoPlay && !this.prefersReducedMotion) {
                            this.startAutoScroll();
                        } else {
                            this.stopAutoScroll();
                        }
                        
                        // Announce state change
                        const message = this.autoPlay ? 
                            '<?php echo esc_js(__('Automatisk avspilling startet', 'nattevakten')); ?>' : 
                            '<?php echo esc_js(__('Automatisk avspilling stoppet', 'nattevakten')); ?>';
                        this.announceToScreenReader(message);
                    },
                    
                    nextNews() {
                        if (this.isDestroyed || this.nyheter.length <= 1) return;
                        this.aktivIndex = (this.aktivIndex + 1) % this.nyheter.length;
                    },
                    
                    previousNews() {
                        if (this.isDestroyed || this.nyheter.length <= 1) return;
                        this.aktivIndex = this.aktivIndex === 0 ? this.nyheter.length - 1 : this.aktivIndex - 1;
                    },
                    
                    pauseScroll() {
                        if (this.isDestroyed) return;
                        this.stopAutoScroll();
                    },
                    
                    resumeScroll() {
                        if (this.isDestroyed || !this.autoPlay || this.prefersReducedMotion) return;
                        
                        if (this.scrollTimeoutId) {
                            clearTimeout(this.scrollTimeoutId);
                        }
                        
                        this.scrollTimeoutId = setTimeout(() => {
                            if (!this.isDestroyed && this.autoPlay) {
                                this.startAutoScroll();
                            }
                        }, 2000);
                    }
                }
            });
            
            try {
                const mountedApp = app.mount('#<?php echo esc_js($unique_id); ?>');
                
                window[tickerNamespace] = {
                    app: mountedApp,
                    cleanup: () => {
                        if (mountedApp && mountedApp.cleanup) {
                            mountedApp.cleanup();
                        }
                        delete window[tickerNamespace];
                    }
                };
                
            } catch (error) {
                console.error('<?php echo esc_js(__('Feil ved initialisering av ticker:', 'nattevakten')); ?>', error);
            }
        }
        
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initializeTicker);
        } else {
            initializeTicker();
        }
        
        window.addEventListener('beforeunload', function() {
            if (window[tickerNamespace] && window[tickerNamespace].cleanup) {
                window[tickerNamespace].cleanup();
            }
        });
    })();
    </script>
    <?php
    return ob_get_clean();
}

/**
 * SEO optimization and structured data
 */
add_action('wp_head', 'nattevakten_add_structured_data');
function nattevakten_add_structured_data() {
    // Only add structured data on pages where ticker is displayed
    global $post;
    if (!$post || !has_shortcode($post->post_content, 'nattevakt_nyheter')) {
        return;
    }
    
    $news_file = NATTEVAKTEN_JSON_PATH . 'nattavis.json';
    if (!file_exists($news_file)) {
        return;
    }
    
    $content = file_get_contents($news_file, false, null, 0, 50000);
    $news_data = json_decode($content, true);
    
    if (!is_array($news_data) || empty($news_data)) {
        return;
    }
    
    $structured_data = [
        '@context' => 'https://schema.org',
        '@type' => 'ItemList',
        'name' => __('Nattevakten Lokalnyheter', 'nattevakten'),
        'description' => __('Lokalnyheter fra Pjuskeby generert av AI', 'nattevakten'),
        'numberOfItems' => count(array_slice($news_data, 0, 5)),
        'itemListElement' => []
    ];
    
    foreach (array_slice($news_data, 0, 5) as $index => $item) {
        $structured_data['itemListElement'][] = [
            '@type' => 'NewsArticle',
            'position' => $index + 1,
            'headline' => esc_html($item['tekst'] ?? ''),
            'datePublished' => isset($item['generated_at']) ? 
                date('c', strtotime($item['generated_at'])) : 
                current_time('c'),
            'author' => [
                '@type' => 'Organization',
                'name' => 'Nattevakten AI'
            ],
            'publisher' => [
                '@type' => 'Organization',
                'name' => get_bloginfo('name'),
                'url' => home_url()
            ],
            'mainEntityOfPage' => get_permalink($post->ID),
            'articleSection' => 'Lokalnyheter'
        ];
    }
    
    echo '<script type="application/ld+json">' . wp_json_encode($structured_data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>' . "\n";
}
?>