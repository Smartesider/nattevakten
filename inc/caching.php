<?php
/**
 * Advanced caching system for Nattevakten
 * Enterprise-ready with Redis/Memcached support
 */

// Detect and use advanced caching backends
function nattevakten_get_cache_backend() {
    static $backend = null;
    
    if ($backend === null) {
        if (class_exists('Redis') && defined('WP_REDIS_HOST')) {
            $backend = 'redis';
        } elseif (class_exists('Memcached') && defined('WP_CACHE_KEY_SALT')) {
            $backend = 'memcached';
        } elseif (function_exists('apcu_store')) {
            $backend = 'apcu';
        } else {
            $backend = 'transient';
        }
    }
    
    return $backend;
}

function nattevakten_cache_set($key, $data, $expiration = 3600) {
    $prefixed_key = 'nattevakt_' . md5($key . NONCE_SALT);
    $backend = nattevakten_get_cache_backend();
    
    switch ($backend) {
        case 'redis':
            try {
                $redis = new Redis();
                $redis->connect(WP_REDIS_HOST, WP_REDIS_PORT ?? 6379);
                if (defined('WP_REDIS_PASSWORD')) {
                    $redis->auth(WP_REDIS_PASSWORD);
                }
                $redis->setex($prefixed_key, $expiration, serialize($data));
                $redis->close();
                return true;
            } catch (Exception $e) {
                nattevakten_log_error('cache', 'redis_error', $e->getMessage(), 'warning');
                return nattevakten_cache_set_fallback($key, $data, $expiration);
            }
            
        case 'memcached':
            try {
                $memcached = new Memcached();
                $memcached->addServer('localhost', 11211);
                return $memcached->set($prefixed_key, $data, $expiration);
            } catch (Exception $e) {
                nattevakten_log_error('cache', 'memcached_error', $e->getMessage(), 'warning');
                return nattevakten_cache_set_fallback($key, $data, $expiration);
            }
            
        case 'apcu':
            return apcu_store($prefixed_key, $data, $expiration);
            
        default:
            return nattevakten_cache_set_fallback($key, $data, $expiration);
    }
}

function nattevakten_cache_get($key) {
    $prefixed_key = 'nattevakt_' . md5($key . NONCE_SALT);
    $backend = nattevakten_get_cache_backend();
    
    switch ($backend) {
        case 'redis':
            try {
                $redis = new Redis();
                $redis->connect(WP_REDIS_HOST, WP_REDIS_PORT ?? 6379);
                if (defined('WP_REDIS_PASSWORD')) {
                    $redis->auth(WP_REDIS_PASSWORD);
                }
                $data = $redis->get($prefixed_key);
                $redis->close();
                return $data === false ? false : unserialize($data);
            } catch (Exception $e) {
                return nattevakten_cache_get_fallback($key);
            }
            
        case 'memcached':
            try {
                $memcached = new Memcached();
                $memcached->addServer('localhost', 11211);
                return $memcached->get($prefixed_key);
            } catch (Exception $e) {
                return nattevakten_cache_get_fallback($key);
            }
            
        case 'apcu':
            return apcu_fetch($prefixed_key);
            
        default:
            return nattevakten_cache_get_fallback($key);
    }
}

function nattevakten_cache_set_fallback($key, $data, $expiration) {
    return set_transient('nattevakt_' . md5($key), $data, $expiration);
}

function nattevakten_cache_get_fallback($key) {
    return get_transient('nattevakt_' . md5($key));
}

/**
 * Distributed environment support - Load balancer compatible file operations
 */
function nattevakten_distributed_file_lock($filepath, $operation = 'generation') {
    // In distributed environments, use database-based locking
    if (defined('WP_CACHE_KEY_SALT') && wp_using_ext_object_cache()) {
        return nattevakten_database_lock($filepath, $operation);
    }
    
    // Standard file locking for single-server setups
    $lock_file = $filepath . '.lock';
    $lock = fopen($lock_file, 'w');
    
    if (!$lock) {
        return false;
    }
    
    // Non-blocking lock with timeout
    $timeout = time() + 30; // 30 second timeout
    while (time() < $timeout) {
        if (flock($lock, LOCK_EX | LOCK_NB)) {
            return ['handle' => $lock, 'file' => $lock_file, 'type' => 'file'];
        }
        usleep(100000); // 100ms
    }
    
    fclose($lock);
    return false;
}

function nattevakten_database_lock($filepath, $operation) {
    global $wpdb;
    
    $lock_name = 'nattevakt_' . md5($filepath . $operation);
    $timeout = 30;
    
    // MySQL GET_LOCK function for distributed locking
    $result = $wpdb->get_var($wpdb->prepare(
        "SELECT GET_LOCK(%s, %d)",
        $lock_name,
        $timeout
    ));
    
    if ($result == 1) {
        return ['name' => $lock_name, 'type' => 'database'];
    }
    
    return false;
}

function nattevakten_release_distributed_lock($lock_info) {
    if (!$lock_info) return;
    
    if ($lock_info['type'] === 'file') {
        flock($lock_info['handle'], LOCK_UN);
        fclose($lock_info['handle']);
        @unlink($lock_info['file']);
    } elseif ($lock_info['type'] === 'database') {
        global $wpdb;
        $wpdb->query($wpdb->prepare("SELECT RELEASE_LOCK(%s)", $lock_info['name']));
    }
}
?>