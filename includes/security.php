<?php
final class LiteupDatabaseSessionHandler implements SessionHandlerInterface
{
    private mysqli $connection;
    private bool $transactionOpen = false;

    public function __construct(mysqli $connection)
    {
        $this->connection = $connection;
    }

    public function open(string $path, string $name): bool
    {
        return true;
    }

    public function close(): bool
    {
        if ($this->transactionOpen) {
            $this->transactionOpen = false;
            try {
                $this->connection->commit();
            } catch (Throwable $exception) {
                $this->connection->rollback();
                error_log('LiteUp database session commit failed.');
                return false;
            }
        }
        return true;
    }

    public function read(string $id): string|false
    {
        $this->connection->begin_transaction();
        $this->transactionOpen = true;

        $create = $this->connection->prepare("INSERT IGNORE INTO app_sessions (session_id, payload, last_activity) VALUES (?, '', ?)");
        $now = time();
        $create->bind_param('si', $id, $now);
        $create->execute();

        $select = $this->connection->prepare('SELECT payload, last_activity FROM app_sessions WHERE session_id = ? FOR UPDATE');
        $select->bind_param('s', $id);
        $select->execute();
        $record = $select->get_result()->fetch_assoc();
        if (!$record || (int) $record['last_activity'] < $now - 1440) {
            $reset = $this->connection->prepare("UPDATE app_sessions SET payload = '', last_activity = ? WHERE session_id = ?");
            $reset->bind_param('is', $now, $id);
            $reset->execute();
            return '';
        }

        return (string) $record['payload'];
    }

    public function write(string $id, string $data): bool
    {
        $now = time();
        $write = $this->connection->prepare('INSERT INTO app_sessions (session_id, payload, last_activity) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE payload = VALUES(payload), last_activity = VALUES(last_activity)');
        $write->bind_param('ssi', $id, $data, $now);
        return $write->execute();
    }

    public function destroy(string $id): bool
    {
        $delete = $this->connection->prepare('DELETE FROM app_sessions WHERE session_id = ?');
        $delete->bind_param('s', $id);
        return $delete->execute();
    }

    public function gc(int $max_lifetime): int|false
    {
        $expiredBefore = time() - $max_lifetime;
        $delete = $this->connection->prepare('DELETE FROM app_sessions WHERE last_activity < ?');
        $delete->bind_param('i', $expiredBefore);
        if (!$delete->execute()) {
            return false;
        }
        return $delete->affected_rows;
    }
}

function liteup_is_vercel_request(): bool
{
    return getenv('VERCEL') === '1';
}

function liteup_request_is_https(): bool
{
    return liteup_is_vercel_request() || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
}

if (session_status() === PHP_SESSION_NONE) {
    if (liteup_is_vercel_request()) {
        require_once __DIR__ . '/db.php';
        session_set_save_handler(new LiteupDatabaseSessionHandler($conn), true);
    }
    ini_set('session.use_strict_mode', '1');
    session_set_cookie_params([
        'httponly' => true,
        'secure' => liteup_request_is_https(),
        'samesite' => 'Lax'
    ]);
    session_start();
}

if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header("Content-Security-Policy: default-src 'self'; script-src 'self' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; img-src 'self' data:; font-src 'self' data: https://cdn.jsdelivr.net; connect-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'");
    if (liteup_request_is_https()) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

function verify_csrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!$token || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        exit('Invalid security token. Please refresh the page and try again.');
    }
}

function validate_password(string $password): ?string
{
    if (strlen($password) < 8) {
        return 'Password must be at least 8 characters long.';
    }
    if (strlen($password) > 72) {
        return 'Password must be 72 bytes or fewer.';
    }
    if (!preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/[0-9]/', $password) || !preg_match('/[^A-Za-z0-9]/', $password)) {
        return 'Password must include uppercase, lowercase, number, and special character.';
    }

    return null;
}

function login_rate_limit_file(string $identity): string
{
    $key = hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . '|' . strtolower($identity));
    return sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'liteup_login_' . $key . '.json';
}

function login_rate_limit_key(string $identity): string
{
    $address = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    return hash('sha256', $address . '|' . strtolower(trim($identity)));
}

function consume_login_attempt(string $identity): bool
{
    if (liteup_is_vercel_request()) {
        global $conn;
        $key = login_rate_limit_key($identity);
        $now = time();
        $windowStart = $now - 900;
        $upsert = $conn->prepare('INSERT INTO login_rate_limits (rate_key, window_started_at, attempt_count) VALUES (?, ?, 1) ON DUPLICATE KEY UPDATE attempt_count = IF(window_started_at <= ?, 1, LEAST(attempt_count + 1, 6)), window_started_at = IF(window_started_at <= ?, VALUES(window_started_at), window_started_at)');
        $upsert->bind_param('siii', $key, $now, $windowStart, $windowStart);
        $upsert->execute();

        $select = $conn->prepare('SELECT attempt_count FROM login_rate_limits WHERE rate_key = ? LIMIT 1');
        $select->bind_param('s', $key);
        $select->execute();
        $attemptCount = (int) ($select->get_result()->fetch_assoc()['attempt_count'] ?? 6);

        if (random_int(1, 100) === 1) {
            $expireBefore = $now - 86400;
            $cleanup = $conn->prepare('DELETE FROM login_rate_limits WHERE window_started_at < ?');
            $cleanup->bind_param('i', $expireBefore);
            $cleanup->execute();
        }

        return $attemptCount > 5;
    }

    $file = login_rate_limit_file($identity);
    $now = time();
    $window = 900;
    $maxAttempts = 5;
    $handle = fopen($file, 'c+');

    if ($handle === false) {
        throw new RuntimeException('Unable to open the login rate-limit file.');
    }

    $locked = false;
    try {
        if (!flock($handle, LOCK_EX)) {
            throw new RuntimeException('Unable to lock the login rate-limit file.');
        }
        $locked = true;

        if (!rewind($handle)) {
            throw new RuntimeException('Unable to read the login rate-limit file.');
        }

        $contents = stream_get_contents($handle);
        if ($contents === false) {
            throw new RuntimeException('Unable to read the login rate-limit file.');
        }

        $attempts = [];
        if ($contents !== '') {
            $stored = json_decode($contents, true);
            if (!is_array($stored) || json_last_error() !== JSON_ERROR_NONE) {
                throw new RuntimeException('The login rate-limit file contains invalid data.');
            }
            $attempts = array_values(array_filter(
                $stored,
                static fn ($time): bool => is_int($time) && $time > $now - $window
            ));
        }

        if (count($attempts) >= $maxAttempts) {
            return true;
        }

        $attempts[] = $now;
        $encoded = json_encode($attempts);
        if ($encoded === false || !ftruncate($handle, 0) || !rewind($handle)) {
            throw new RuntimeException('Unable to update the login rate-limit file.');
        }

        $written = fwrite($handle, $encoded);
        if ($written !== strlen($encoded) || !fflush($handle)) {
            throw new RuntimeException('Unable to update the login rate-limit file.');
        }

        return false;
    } finally {
        if ($locked) {
            flock($handle, LOCK_UN);
        }
        fclose($handle);
    }
}

function clear_login_failures(string $identity): void
{
    if (liteup_is_vercel_request()) {
        global $conn;
        $key = login_rate_limit_key($identity);
        $delete = $conn->prepare('DELETE FROM login_rate_limits WHERE rate_key = ?');
        $delete->bind_param('s', $key);
        $delete->execute();
        return;
    }

    $file = login_rate_limit_file($identity);
    $handle = fopen($file, 'c+');

    if ($handle === false) {
        throw new RuntimeException('Unable to open the login rate-limit file.');
    }

    $locked = false;
    try {
        if (!flock($handle, LOCK_EX)) {
            throw new RuntimeException('Unable to lock the login rate-limit file.');
        }
        $locked = true;

        if (!ftruncate($handle, 0) || !fflush($handle)) {
            throw new RuntimeException('Unable to clear the login rate-limit file.');
        }
    } finally {
        if ($locked) {
            flock($handle, LOCK_UN);
        }
        fclose($handle);
    }
}
