<?php

namespace CoderPress\Cache;

use Psr\SimpleCache\CacheInterface;

/**
 * Simple file PSR-16 compliant cache driver.
 * For more info and docblock comments find the reference. 
 * @link https://www.php-fig.org/psr/psr-16/
 * @package FileCache
 * 
 */

class FileCache implements CacheInterface
{
    private string $directory;

    private $ttl;

    public function __construct(string $directory)
    {
        $this->directory = $directory;
        if (!is_dir($this->directory)) {
            mkdir($this->directory, 0777, true);
        }
    }

    private function getFilePath(string $key): string
    {
        return $this->directory . '/' . $key . '.cache';
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $filePath = $this->getFilePath($key);
        if (!file_exists($filePath)) {
            return $default;
        }

        $expirationTime = fileatime($filePath) + (int) ($this->ttl ?? 0);
        if ($expirationTime < time()) {
            unlink($filePath);
            return $default;
        }

        $data = file_get_contents($filePath);
        if ($data === false) {
            return $default;
        }

        return $data;
    }

    public function set(string $key, mixed $value, null|int|\DateInterval $ttl = null): bool
    {
        $filePath = $this->getFilePath($key);
        $data = $value;

        if ($ttl !== null) {
            $ttl = $ttl instanceof \DateInterval ? $ttl->s : $ttl;
            touch($filePath, mtime: time() + $ttl, atime: time() + $ttl);
        }

        return file_put_contents($filePath, $data) !== false;
    }

    public function delete(string $key): bool
    {
        $filePath = $this->getFilePath($key);
        return unlink($filePath);
    }

    public function clear(): bool
    {
        $files = glob($this->directory . '/*.cache');
        foreach ($files as $file) {
            unlink($file);
        }
        return true;
    }

    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $results = [];
        foreach ($keys as $key) {
            $results[$key] = $this->get($key, $default);
        }
        return $results;
    }

    public function setMultiple(iterable $values, null|int|\DateInterval $ttl = null): bool  

    {
        $success = true;
        foreach ($values as $key => $value) {
            $success = $success && $this->set($key, $value, $ttl);
        }
        return $success;
    }

    public function deleteMultiple(iterable $keys): bool
    {
        $success = true;
        foreach ($keys as $key) {
            $success = $success && $this->delete($key);
        }
        return $success;
    }

    public function has(string $key): bool
    {
        $filePath = $this->getFilePath($key);
        return file_exists($filePath);
    }
}
