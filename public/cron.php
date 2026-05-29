<?php
/**
 * Queue + scheduler runner for shared hosting WITHOUT cron access.
 *
 * Since the host has no cron, point a free external scheduler at this URL to run
 * every 1 minute so queued jobs (WhatsApp send/receive, workflow execution) and
 * any scheduled tasks get processed:
 *
 *   https://your-domain.com/cron.php?token=YOUR_CRON_TOKEN
 *
 * Free pingers that work well here:
 *   - https://cron-job.org        (1-minute interval, free)
 *   - GitHub Actions scheduled workflow (free)
 *   - UptimeRobot (5-minute interval, free)
 *
 * What it does on each call:
 *   - queue:work --stop-when-empty  (drains pending jobs, then exits)
 *   - schedule:run                  (runs any due scheduled tasks)
 *
 * SECURITY: set CRON_TOKEN=<long-random-string> in .env. The token may be passed
 * as ?token=... or via the "X-Cron-Token" request header.
 */

set_time_limit(0);
ignore_user_abort(true);
@ini_set('memory_limit', '512M');
header('Content-Type: text/plain; charset=utf-8');

function locate_base_path(): ?string
{
    foreach ([dirname(__DIR__), __DIR__] as $candidate) {
        if (is_file($candidate . '/bootstrap/app.php')) {
            return $candidate;
        }
    }
    return null;
}

function read_env_value(string $envFile, string $key): ?string
{
    if (!is_file($envFile)) {
        return null;
    }
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
            continue;
        }
        [$k, $v] = explode('=', $line, 2);
        if (trim($k) === $key) {
            return trim(trim($v), "\"'");
        }
    }
    return null;
}

$basePath = locate_base_path();
if ($basePath === null) {
    http_response_code(500);
    echo "ERROR: could not locate Laravel (bootstrap/app.php not found).\n";
    exit;
}

$envFile = $basePath . '/.env';
$cronToken = read_env_value($envFile, 'CRON_TOKEN');

// Token is REQUIRED for this endpoint (it triggers work on every call).
if ($cronToken === null || $cronToken === '') {
    http_response_code(500);
    echo "ERROR: CRON_TOKEN is not set in .env. Add CRON_TOKEN=your-long-random-secret first.\n";
    exit;
}

$provided = $_GET['token']
    ?? $_SERVER['HTTP_X_CRON_TOKEN']
    ?? '';

if (!hash_equals($cronToken, (string) $provided)) {
    http_response_code(403);
    echo "Forbidden: invalid cron token.\n";
    exit;
}

// Prevent overlapping runs (in case the previous run is still draining the queue).
$lock = fopen($basePath . '/storage/framework/cron.lock', 'c');
if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
    echo "Skipped: a previous cron run is still in progress.\n";
    exit;
}

$autoload = $basePath . '/vendor/autoload.php';
if (!is_file($autoload)) {
    http_response_code(500);
    echo "ERROR: vendor/autoload.php missing. Deploy/composer install first.\n";
    exit;
}

require $autoload;
/** @var \Illuminate\Foundation\Application $app */
$app = require $basePath . '/bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);

$startedAt = microtime(true);
echo "[" . date('Y-m-d H:i:s') . "] cron run start\n";

try {
    // Drain all currently queued jobs, then stop. --max-time bounds a single request.
    $kernel->call('queue:work', [
        '--stop-when-empty' => true,
        '--max-time' => 50,
        '--tries' => 3,
        '--no-interaction' => true,
    ]);
    echo "-- queue:work --\n" . trim((string) $kernel->output()) . "\n";
} catch (\Throwable $e) {
    echo "queue:work ERROR: " . $e->getMessage() . "\n";
}

try {
    $kernel->call('schedule:run', ['--no-interaction' => true]);
    echo "-- schedule:run --\n" . trim((string) $kernel->output()) . "\n";
} catch (\Throwable $e) {
    echo "schedule:run ERROR: " . $e->getMessage() . "\n";
}

flock($lock, LOCK_UN);
fclose($lock);

printf("[%s] cron run done in %.1fs\n", date('Y-m-d H:i:s'), microtime(true) - $startedAt);
