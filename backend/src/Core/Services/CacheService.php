<?php

declare(strict_types=1);

namespace App\Core\Services;

use Predis\Client as RedisClient;

class CacheService
{
    public function __construct(
        private readonly RedisClient $redis,
        private readonly string $prefix = 'kyuubisoft:'
    ) {}

    /**
     * Get a value from cache
     */
    public function get(string $key): mixed
    {
        $value = $this->redis->get($this->prefixKey($key));

        if ($value === null) {
            return null;
        }

        return $this->unserialize($value);
    }

    /**
     * Set a value in cache
     */
    public function set(string $key, mixed $value, int $ttl = 3600): bool
    {
        $result = $this->redis->setex(
            $this->prefixKey($key),
            $ttl,
            $this->serialize($value)
        );

        return $result !== null;
    }

    /**
     * Remember a value (get from cache or compute and store)
     */
    public function remember(string $key, int $ttl, callable $callback): mixed
    {
        $value = $this->get($key);

        if ($value !== null) {
            return $value;
        }

        $value = $callback();
        $this->set($key, $value, $ttl);

        return $value;
    }

    /**
     * Delete a value from cache
     */
    public function delete(string $key): bool
    {
        return $this->redis->del($this->prefixKey($key)) > 0;
    }

    /**
     * Delete multiple values by pattern
     */
    public function deletePattern(string $pattern): int
    {
        $keys = $this->redis->keys($this->prefixKey($pattern));

        if (empty($keys)) {
            return 0;
        }

        return $this->redis->del($keys);
    }

    /**
     * Check if key exists
     */
    public function has(string $key): bool
    {
        return $this->redis->exists($this->prefixKey($key)) > 0;
    }

    /**
     * Increment a value
     */
    public function increment(string $key, int $value = 1): int
    {
        return $this->redis->incrby($this->prefixKey($key), $value);
    }

    /**
     * Decrement a value
     */
    public function decrement(string $key, int $value = 1): int
    {
        return $this->redis->decrby($this->prefixKey($key), $value);
    }

    /**
     * Flush all cache
     */
    public function flush(): bool
    {
        $this->deletePattern('*');
        return true;
    }

    /**
     * Publish a message to a Redis channel
     */
    public function publish(string $channel, mixed $message): int
    {
        return $this->redis->publish(
            $this->prefixKey($channel),
            is_string($message) ? $message : json_encode($message)
        );
    }

    /**
     * Get the Redis client for advanced operations (like pub/sub subscribe)
     */
    public function getRedisClient(): RedisClient
    {
        return $this->redis;
    }

    /**
     * Get the prefix for external use
     */
    public function getPrefix(): string
    {
        return $this->prefix;
    }

    private function prefixKey(string $key): string
    {
        return $this->prefix . $key;
    }

    private function serialize(mixed $value): string
    {
        return serialize($value);
    }

    private function unserialize(string $value): mixed
    {
        return unserialize($value);
    }
}
