<?php
require_once __DIR__ . "/security_headers.php";
require_once __DIR__ . "/../config/redis.php";

class RedisSessionHandler implements SessionHandlerInterface {
    private ?Predis\Client $redis;
    private string $prefix;
    private int $lifetime;

    public function __construct(Predis\Client $redis, int $lifetime = 604800) {
        $this->redis = $redis;
        $this->prefix = 'printease:session:';
        $this->lifetime = $lifetime;
    }

    public function open(string $path, string $name): bool {
        return true;
    }

    public function close(): bool {
        return true;
    }

    public function read(string $id): string {
        $data = $this->redis->get($this->prefix . $id);
        return $data !== null ? (string) $data : '';
    }

    public function write(string $id, string $data): bool {
        $this->redis->setex($this->prefix . $id, $this->lifetime, $data);
        return true;
    }

    public function destroy(string $id): bool {
        $this->redis->del($this->prefix . $id);
        return true;
    }

    public function gc(int $max_lifetime): int|false {
        return 0;
    }
}

function secureSession(): void {
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    session_set_cookie_params([
        'lifetime' => 86400,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => function_exists('isRequestSecure') ? isRequestSecure() : (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    ]);

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');

    $redis = getRedisClient();
    if ($redis !== null) {
        $handler = new RedisSessionHandler($redis);
        session_set_save_handler($handler, true);
        ini_set('session.gc_probability', '0');
    }

    session_start();
}
