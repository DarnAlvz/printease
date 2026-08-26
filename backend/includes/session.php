<?php
require_once __DIR__ . "/security_headers.php";
require_once __DIR__ . "/../config/redis.php";

function sessionLifetimeSeconds(): int {
    $days = (int) envValue('SESSION_LIFETIME_DAYS', '30');
    if ($days < 1) {
        $days = 30;
    }
    return $days * 86400;
}

class RedisSessionHandler implements SessionHandlerInterface {
    private ?Predis\Client $redis;
    private string $prefix;
    private int $lifetime;

    public function __construct(Predis\Client $redis, int $lifetime = 2592000) {
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
        $key = $this->prefix . $id;
        $data = $this->redis->get($key);
        if ($data === null) {
            return '';
        }
        $this->redis->expire($key, $this->lifetime);
        return (string) $data;
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

    $lifetime_seconds = sessionLifetimeSeconds();

    session_set_cookie_params([
        'lifetime' => $lifetime_seconds,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => function_exists('isRequestSecure') ? isRequestSecure() : (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    ]);

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');

    $redis = getRedisClient();
    if ($redis !== null) {
        $handler = new RedisSessionHandler($redis, $lifetime_seconds);
        session_set_save_handler($handler, true);
        ini_set('session.gc_probability', '0');
    } else {
        ini_set('session.gc_maxlifetime', (string) $lifetime_seconds);
        ini_set('session.gc_probability', '1');
        ini_set('session.gc_divisor', '1000');

        $save_path = __DIR__ . '/../runtime/sessions';
        if (!is_dir($save_path)) {
            @mkdir($save_path, 0775, true);
        }
        if (is_dir($save_path) && is_writable($save_path)) {
            session_save_path($save_path);
        }
    }

    session_start();
}
