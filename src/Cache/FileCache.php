<?php

/**
 * M-Pesa SDK File Cache
 * 
 * Simple file-based cache implementation for storing tokens and other data.
 * 
 * @package MpesaSDK\Cache
 * @author Abuti Martin <abutimartin778@gmail.com>
 * @version 1.0.0
 * @license MIT
 * @since 1.0.0
 */

namespace MpesaSDK\Cache;

class FileCache
{
    private $cacheDir;

    public function __construct(string $cacheDir = null)
    {
        $this->cacheDir = $cacheDir ?: sys_get_temp_dir() . '/mpesa_cache';
        $this->ensureCacheDir();
    }

    public function get(string $key): ?array
    {
        $file = $this->getCacheFile($key);
        
        if (!file_exists($file)) {
            return null;
        }

        $data = json_decode(file_get_contents($file), true);
        
        if (!$data || $data['expires_at'] < time()) {
            $this->delete($key);
            return null;
        }

        return $data['value'];
    }

    public function set(string $key, array $value, int $ttl): void
    {
        $data = [
            'value' => $value,
            'expires_at' => time() + $ttl
        ];

        file_put_contents($this->getCacheFile($key), json_encode($data), LOCK_EX);
    }

    public function delete(string $key): void
    {
        $file = $this->getCacheFile($key);
        if (file_exists($file)) {
            unlink($file);
        }
    }

    private function getCacheFile(string $key): string
    {
        return $this->cacheDir . '/' . md5($key) . '.cache';
    }

    private function ensureCacheDir(): void
    {
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }
}