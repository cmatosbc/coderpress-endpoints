<?php

namespace CoderPress\Cache;

use Psr\SimpleCache\CacheInterface;
use CoderPress\Exceptions\MissingExtensionException;
use CoderPress\Exceptions\DriverException;
use Redis;

/**
 * Redis PSR-16 compliant cache driver.
 * For more info and docblock comments find the reference. 
 * @link https://www.php-fig.org/psr/psr-16/
 * @package RedisCache
 * 
 */

class RedisCache implements CacheInterface
{
    private Redis $redis;

    public function __construct(string $host, int $port, string $password = null, int $database = 0)
    {
        try {
            if (!extension_loaded('redis')) {
                throw new MissingExtensionException('Redis extension is not loaded.');
            }

            $this->redis = new \Redis();
            $this->redis->connect($host, $port);
            if (!empty($password)) {
                $this->redis->auth($password);
            }
            $this->redis->select($database);
        } catch (\RedisException $e) {
            throw new DriverException('Failed to connect to Redis: ' . $e->getMessage());
        }

        $this->redis->connect($host, $port);
        if (!empty($password)) {
            $this->redis->auth($password);
        }
        $this->redis->select($database);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->redis->get($key);
        return $value !== false ? $value : $default;
    }

    public function set(string $key, mixed $value, null|int|\DateInterval $ttl = null): bool
    {
        if ($ttl !== null) {
            $ttl = $ttl instanceof \DateInterval ? $ttl->s : $ttl;
            return $this->redis->setex($key, $ttl, $value);
        } else {
            return $this->redis->set($key, $value);
        }
    }

    public function delete(string $key): bool
    {
        return $this->redis->del($key) > 0;
    }

    public function clear(): bool
    {
        return $this->redis->flushAll();
    }

    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $values = $this->redis->mget(array_values($keys));
        $results = [];
        foreach ($keys as $key => $originalKey) {
            $results[$originalKey] = isset($values[$key]) ? $values[$key] : $default;
        }
        return $results;
    }

    public function setMultiple(iterable $values, null|int|\DateInterval $ttl = null): bool
    {
        $pipeline = $this->redis->pipeline();
        foreach ($values as $key => $value) {
            if ($ttl !== null) {
                $ttl = $ttl instanceof \DateInterval ? $ttl->s : $ttl;
                $pipeline->setex($key, $ttl, $value);
            } else {
                $pipeline->set($key, $value);
            }
        }
        return $pipeline->execute() === array_fill(0, count($values), true);
    }

    public function deleteMultiple(iterable $keys): bool
    {
        return $this->redis->del(array_values($keys)) > 0;
    }

    public function has(string $key): bool
    {
        return $this->redis->exists($key);
    }
}
