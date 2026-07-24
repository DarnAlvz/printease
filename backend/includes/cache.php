<?php
require_once __DIR__ . "/../config/redis.php";

function cacheGet(string $key): mixed {
    $redis = getRedisClient();
    if ($redis === null) {
        return null;
    }

    $raw = $redis->get($key);
    if ($raw === null) {
        return null;
    }

    return json_decode($raw, true);
}

function cacheSet(string $key, mixed $value, int $ttl): void {
    $redis = getRedisClient();
    if ($redis === null) {
        return;
    }

    $redis->setex($key, $ttl, json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
}

function cacheInvalidate(string $key): void {
    $redis = getRedisClient();
    if ($redis === null) {
        return;
    }

    $redis->del($key);
}

function cacheInvalidatePattern(string $pattern): void {
    $redis = getRedisClient();
    if ($redis === null) {
        return;
    }

    $cursor = 0;
    do {
        $result = $redis->scan($cursor, [
            'match' => $pattern,
            'count' => 100,
        ]);

        if (!is_array($result) || count($result) !== 2) {
            return;
        }

        [$cursor, $keys] = $result;
        if (!empty($keys)) {
            $redis->del(...$keys);
        }
    } while ((string) $cursor !== '0');
}
