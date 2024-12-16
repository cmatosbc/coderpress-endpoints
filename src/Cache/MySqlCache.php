<?php

namespace CoderPress\Cache;

use Psr\SimpleCache\CacheInterface;
use CoderPress\Utilities\Time;
use wpdb;

/**
 * MySQL cache driver implementing PSR-16 interface. 
 * Stores and retrieves cached data using a MySQL database table.
 * 
 * @package CoderPress\Cache
 */
class MySqlCache implements CacheInterface
{
    /**
     * @var wpdb The WordPress database object.
     */
    private wpdb $wpdb;

    /**
     * @var string The name of the cache table.
     */
    private string $tableName;

    /**
     * @var int The default expiration time for cached items (in seconds).
     */
    private int $defaultTtl;

    /**
     * Constructs a new MysqlCache instance.
     *
     * @param int $defaultTtl The default expiration time for cached items (in seconds).
     * @param string $dbTableName Custom table name (excluding the WP predefined prefix)
     */
    public function __construct(int $defaultTtl = 3600, string $dbTableName = 'cached_requests')
    {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->tableName = $wpdb->prefix . $dbTableName;
        $this->defaultTtl = $defaultTtl;

        $this->createCacheTable();
    }

    /**
     * Creates the wp_cached_requests table if it does not already exist.
     */
    private function createCacheTable(): void
    {
        $charsetCollate = $this->wpdb->get_charset_collate();
        $sql = "CREATE TABLE IF NOT EXISTS {$this->tableName} (
            `cache_key` VARCHAR(255) NOT NULL PRIMARY KEY,
            `cache_value` MEDIUMTEXT NOT NULL,
            `expiration` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) $charsetCollate;";

        $this->wpdb->query($sql);
    }

    /**
     * Retrieves a value from the cache.
     *
     * @param string $key The cache key.
     * @param mixed $default The default value to return if the key is not found.
     * @return mixed The cached value, or the default value if not found.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $sql = "SELECT cache_value FROM {$this->tableName} WHERE cache_key = %s AND expiration > NOW();";
        $prepared = $this->wpdb->prepare($sql, $key);
        $result = $this->wpdb->get_var($prepared);

        return $result !== false ? unserialize($result) : $default;
    }

    /**
     * Stores a value in the cache.
     *
     * @param string $key The cache key.
     * @param mixed $value The value to store.
     * @param null|int|\DateInterval $ttl The time to live for the cached value, in seconds.
     * @return bool True if the value was stored successfully, false otherwise.
     */
    public function set(string $key, mixed $value, null|int|\DateInterval $ttl = 3600): bool
    {
        $data = serialize($value);
        $expiration = Time::getExpireTime($ttl);

        $sql = "INSERT INTO {$this->tableName} (cache_key, cache_value, expiration) VALUES (%s, %s, %s) 
                ON DUPLICATE KEY UPDATE cache_value = %s, expiration = %s;";
        $prepared = $this->wpdb->prepare($sql, $key, $data, $expiration, $data, $expiration);

        return $this->wpdb->query($prepared) !== false;
    }

    /**
     * Deletes a value from the cache.
     *
     * @param string $key The cache key.
     * @return bool True if the value was deleted successfully, false otherwise.
     */
    public function delete(string $key): bool
    {
        $sql = "DELETE FROM {$this->tableName} WHERE cache_key = %s;";
        $prepared = $this->wpdb->prepare($sql, $key);

        return $this->wpdb->delete($this->tableName, ['cache_key' => $key]) !== false;
    }

    /**
     * Clears the entire cache.
     *
     * @return bool True if the cache was cleared successfully, false otherwise.
     */
    public function clear(): bool
    {
        $sql = "DELETE FROM {$this->tableName};";
        return $this->wpdb->query($sql) !== false;
    }

    /**
     * Retrieves multiple values from the cache.
     *
     * @param iterable $keys The cache keys.
     * @param mixed $default The default value to return if a key is not found.
     * @return iterable An iterable containing the cached values, or the default value if not found.
     */
    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $keyList = implode(',', array_map(function ($key) {
            return $this->wpdb->prepare('%s', $key);
        }, $keys));

        $sql = "SELECT cache_key, cache_value FROM {$this->tableName} WHERE cache_key IN ($keyList) AND expiration > NOW();";
        $results = $this->wpdb->get_results($sql, ARRAY_A);

        $cachedValues = [];
        foreach ($results as $result) {
            $cachedValues[$result['cache_key']] = unserialize($result['cache_value']);
        }

        foreach ($keys as $key) {
            if (!isset($cachedValues[$key])) {
                $cachedValues[$key] = $default;
            }
        }

        return $cachedValues;
    }

    /**
     * Stores multiple values in the cache.
     *
     * @param iterable $values The values to store.
     * @param null|int|\DateInterval $ttl The time to live for the cached values, in seconds.
     * @return bool True if all values were stored successfully, false otherwise.
     */
    public function setMultiple(iterable $values, null|int|\DateInterval $ttl = 3600): bool
    {
        $sql = "INSERT INTO {$this->tableName} (cache_key, cache_value, expiration) VALUES ";
        $placeholders = [];
        $data = [];
        foreach ($values as $key => $value) {
            $data[] = serialize($value);
            $placeholders[] = "(%s, %s, %s)";
        }

        $expiration = Time::getExpireTime($ttl);
        $sql .= implode(', ', $placeholders) . " ON DUPLICATE KEY UPDATE cache_value = VALUES(cache_value), expiration = VALUES(expiration);";

        $prepared = $this->wpdb->prepare($sql, array_merge(array_keys($values), $data, array_fill(0, count($values), $expiration)));

        return $this->wpdb->query($prepared) !== false;
    }

    /**
     * Deletes multiple values from the cache.
     *
     * @param iterable $keys The cache keys.
     * @return bool True if all values were deleted successfully, false otherwise.
     */
    public function deleteMultiple(iterable $keys): bool
    {
        $keyList = implode(',', array_map(function ($key) {
            return $this->wpdb->prepare('%s', $key);
        }, $keys));

        $sql = "DELETE FROM {$this->tableName} WHERE cache_key IN ($keyList);";
        return $this->wpdb->query($sql) !== false;
    }

    /**
     * Checks if a value exists in the cache.
     *
     * @param string $key The cache key.
     * @return bool True if the value exists, false otherwise.
     */
    public function has(string $key): bool
    {
        $sql = "SELECT 1 FROM {$this->tableName} WHERE cache_key = %s AND expiration > NOW();";
        $prepared = $this->wpdb->prepare($sql, $key);
        return $this->wpdb->get_var($prepared) !== false;
    }
}
