<?php

namespace Cepi\Utils;

class RateLimiter {
    private $cacheDir;
    private $maxRequestsPerMinute;
    private $maxRequestsPerHour;
    
    public function __construct($maxRequestsPerMinute = 60, $maxRequestsPerHour = 1000) {
        $this->cacheDir = __DIR__ . '/../../cache/rate_limit';
        $this->maxRequestsPerMinute = $maxRequestsPerMinute;
        $this->maxRequestsPerHour = $maxRequestsPerHour;
        
        // Create cache directory if it doesn't exist
        if (!is_dir($this->cacheDir)) {
            @mkdir($this->cacheDir, 0755, true);
        }
    }
    
    /**
     * Get client IP address
     */
    private function getClientIp() {
        $ipKeys = [
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];
        
        foreach ($ipKeys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    /**
     * Get cache file path for IP
     */
    private function getCacheFile($ip) {
        $safeIp = preg_replace('/[^a-zA-Z0-9._-]/', '_', $ip);
        return $this->cacheDir . '/' . $safeIp . '.json';
    }
    
    /**
     * Load rate limit data for IP
     */
    private function loadData($ip) {
        $file = $this->getCacheFile($ip);
        
        if (!file_exists($file)) {
            return [
                'minute' => [],
                'hour' => []
            ];
        }
        
        $data = @json_decode(file_get_contents($file), true);
        
        if (!is_array($data)) {
            return [
                'minute' => [],
                'hour' => []
            ];
        }
        
        return $data;
    }
    
    /**
     * Save rate limit data for IP
     */
    private function saveData($ip, $data) {
        $file = $this->getCacheFile($ip);
        @file_put_contents($file, json_encode($data), LOCK_EX);
    }
    
    /**
     * Clean old entries from arrays
     */
    private function cleanOldEntries(&$array, $maxAge) {
        $now = time();
        $array = array_filter($array, function($timestamp) use ($now, $maxAge) {
            return ($now - $timestamp) < $maxAge;
        });
        $array = array_values($array); // Re-index
    }
    
    /**
     * Check if request is allowed
     * Returns array with 'allowed' (bool) and 'remaining' (int) and 'reset' (timestamp)
     */
    public function checkLimit() {
        $ip = $this->getClientIp();
        $data = $this->loadData($ip);
        $now = time();
        
        // Clean old entries
        $this->cleanOldEntries($data['minute'], 60); // Last 60 seconds
        $this->cleanOldEntries($data['hour'], 3600); // Last 3600 seconds
        
        // Check minute limit
        $minuteCount = count($data['minute']);
        $hourCount = count($data['hour']);
        
        $allowed = true;
        $remaining = 0;
        $resetTime = $now + 60;
        $limitType = 'minute';
        
        if ($minuteCount >= $this->maxRequestsPerMinute) {
            $allowed = false;
            $remaining = 0;
            // Find oldest request in minute array to calculate reset time
            if (!empty($data['minute'])) {
                $oldest = min($data['minute']);
                $resetTime = $oldest + 60;
            }
            $limitType = 'minute';
        } elseif ($hourCount >= $this->maxRequestsPerHour) {
            $allowed = false;
            $remaining = 0;
            // Find oldest request in hour array to calculate reset time
            if (!empty($data['hour'])) {
                $oldest = min($data['hour']);
                $resetTime = $oldest + 3600;
            }
            $limitType = 'hour';
        } else {
            // Calculate remaining based on the stricter limit
            $remainingMinute = max(0, $this->maxRequestsPerMinute - $minuteCount);
            $remainingHour = max(0, $this->maxRequestsPerHour - $hourCount);
            $remaining = min($remainingMinute, $remainingHour);
        }
        
        // If allowed, record this request
        if ($allowed) {
            $data['minute'][] = $now;
            $data['hour'][] = $now;
            $this->saveData($ip, $data);
        }
        
        return [
            'allowed' => $allowed,
            'remaining' => $remaining,
            'reset' => $resetTime,
            'limit' => $limitType === 'minute' ? $this->maxRequestsPerMinute : $this->maxRequestsPerHour,
            'limit_type' => $limitType
        ];
    }
    
    /**
     * Clean old cache files (older than 1 hour)
     */
    public function cleanOldCache() {
        if (!is_dir($this->cacheDir)) {
            return;
        }
        
        $files = glob($this->cacheDir . '/*.json');
        $now = time();
        
        foreach ($files as $file) {
            if (filemtime($file) < ($now - 3600)) {
                @unlink($file);
            }
        }
    }
}

