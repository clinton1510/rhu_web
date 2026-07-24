<?php
// Shared database connection for PHP components (PDO).
// Values set by the web-server environment take precedence over .env values.
if (!function_exists('rhuEnv')) {
    function rhuEnv(string $key, ?string $default = null): ?string {
        $value = getenv($key);
        if ($value !== false && $value !== '') return $value;

        static $environment = null;
        if ($environment === null) {
            $environment = [];
            $envFile = dirname(__DIR__, 3) . '/.env';
            if (is_readable($envFile)) {
                foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
                    $line = trim($line);
                    if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
                    [$envKey, $envValue] = explode('=', $line, 2);
                    $environment[trim($envKey)] = trim(trim($envValue), "\"'");
                }
            }
        }
        return $environment[$key] ?? $default;
    }
}

$DB_HOST = rhuEnv('DB_HOST', '127.0.0.1');
$DB_NAME = rhuEnv('DB_NAME', 'rhu');
$DB_USER = rhuEnv('DB_USER', 'root');
$DB_PASS = rhuEnv('DB_PASS', rhuEnv('DB_PASSWORD', ''));
$DB_PORT = rhuEnv('DB_PORT', '3306');

try {
    $pdo = new PDO("mysql:host={$DB_HOST};port={$DB_PORT};dbname={$DB_NAME};charset=utf8mb4", $DB_USER, $DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_TIMEOUT => (int) rhuEnv('DB_TIMEOUT', '1'),
    ]);
} catch (PDOException $e) {
    // If the DB connection fails, leave $pdo unset so pages can fall back to mock data.
    error_log('db.php: Could not connect to DB: ' . $e->getMessage());
    $pdo = null;
}

return;
