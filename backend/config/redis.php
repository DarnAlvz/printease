<?php
require_once __DIR__ . "/env.php";

function getRedisClient(): ?Predis\Client {
    static $client = null;

    if ($client !== null) {
        return $client;
    }

    $host = envValue("REDIS_HOST", "127.0.0.1");
    $port = (int) envValue("REDIS_PORT", "6379");

    try {
        $client = new Predis\Client([
            'scheme' => 'tcp',
            'host' => $host,
            'port' => $port,
        ]);

        $client->ping();

        return $client;
    } catch (Throwable $e) {
        error_log("[PrintEase] Redis connection failed: " . $e->getMessage());
        $client = null;
        return null;
    }
}
