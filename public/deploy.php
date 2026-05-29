<?php
/**
 * Self-contained one-click deployer for shared hosting (no SSH / no cron / no cPanel).
 *
 * Upload your files via FTP, then open this page in the browser and run a deploy.
 * It performs the post-upload steps Laravel needs:
 *   - composer install (optional, runs an external `composer`)
 *   - php artisan key:generate (only if APP_KEY is empty)
 *   - config/route/view cache (re)build
 *   - php artisan migrate --force (optional)
 *   - php artisan storage:link (optional, with copy fallback for hosts without symlinks)
 *   - php artisan queue:restart
 *
 * Artisan commands run IN-PROCESS by booting the Laravel kernel, so this works
 * even when the `php` CLI binary or `bash` are not available on the host.
 *
 * SECURITY: set DEPLOY_TOKEN=<a-long-random-string> in your .env. When set, the
 * token is required to run a deploy. Delete this file when you are done if you
 * prefer not to leave a deploy endpoint exposed.
 */

set_time_limit(0);
ignore_user_abort(true);
@ini_set('memory_limit', '512M');

/** Locate the Laravel base path (works whether this file is in public/ or copied to project root). */
function locate_base_path(): ?string
{
    foreach ([dirname(__DIR__), __DIR__] as $candidate) {
        if (is_file($candidate . '/bootstrap/app.php')) {
            return $candidate;
        }
    }
    return null;
}

/** Read a single value from the .env file without booting Laravel. */
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

function h($s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Emit a line to the browser immediately. */
function out(string $line): void
{
    echo h($line) . "\n";
    @ob_flush();
    @flush();
}

$basePath = locate_base_path();
$envFile = $basePath ? $basePath . '/.env' : null;
$deployToken = $envFile ? read_env_value($envFile, 'DEPLOY_TOKEN') : null;

$isPost = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';

if ($isPost) {
    header('Content-Type: text/html; charset=utf-8');
    echo "<!doctype html><html><head><meta charset=\"utf-8\"><title>Deploy</title></head><body>";
    echo "<h2>Deploy output</h2>";
    echo "<pre style=\"white-space:pre-wrap;background:#111;color:#eee;padding:14px;border-radius:8px;font:13px/1.5 ui-monospace,Menlo,Consolas,monospace\">";
    @ob_flush();
    @flush();

    if ($basePath === null) {
        out('ERROR: Could not locate Laravel (bootstrap/app.php not found next to this file).');
        echo "</pre><p><a href=\"deploy.php\">Back</a></p></body></html>";
        exit;
    }

    // Token check (only enforced when DEPLOY_TOKEN is configured).
    if ($deployToken !== null && $deployToken !== '') {
        $provided = (string) ($_POST['token'] ?? '');
        if (!hash_equals($deployToken, $provided)) {
            http_response_code(403);
            out('Forbidden: invalid deploy token.');
            echo "</pre><p><a href=\"deploy.php\">Back</a></p></body></html>";
            exit;
        }
    }

    $doComposer = !empty($_POST['composer']);
    $doMigrate = !empty($_POST['migrate']);
    $doStorage = !empty($_POST['storage_link']);
    $doCache = !empty($_POST['cache']);
    $doQueue = !empty($_POST['queue_restart']);

    $startedAt = microtime(true);
    out('Laravel base path: ' . $basePath);
    out('Started at: ' . date('Y-m-d H:i:s'));
    out(str_repeat('-', 60));

    // 1) composer install (external process, streamed).
    if ($doComposer) {
        out('');
        out('==> composer install --no-dev --optimize-autoloader');
        $composerCmd = null;
        foreach (['composer', 'composer.phar', 'php composer.phar', '/usr/local/bin/composer'] as $candidate) {
            $composerCmd = $candidate; // We just try the first; proc_open will report failure if missing.
            break;
        }
        $cmd = $composerCmd . ' install --no-dev --optimize-autoloader --no-interaction 2>&1';
        $proc = @proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $basePath);
        if (is_resource($proc)) {
            stream_set_blocking($pipes[1], false);
            stream_set_blocking($pipes[2], false);
            while (true) {
                $status = proc_get_status($proc);
                foreach ([1, 2] as $i) {
                    $chunk = stream_get_contents($pipes[$i]);
                    if ($chunk !== '' && $chunk !== false) {
                        echo h($chunk);
                        @ob_flush();
                        @flush();
                    }
                }
                if (!$status['running']) {
                    break;
                }
                usleep(150000);
            }
            foreach ($pipes as $p) {
                if (is_resource($p)) {
                    fclose($p);
                }
            }
            $code = proc_close($proc);
            out("\ncomposer exited with code " . $code);
        } else {
            out('Could not start composer. Skipping. (Upload vendor/ via FTP, or ensure composer is on PATH.)');
        }
    }

    // Boot Laravel for the artisan steps.
    $autoload = $basePath . '/vendor/autoload.php';
    if (!is_file($autoload)) {
        out('');
        out('ERROR: vendor/autoload.php missing. Run composer install or upload vendor/ via FTP first.');
        echo "</pre><p><a href=\"deploy.php\">Back</a></p></body></html>";
        exit;
    }

    require $autoload;
    /** @var \Illuminate\Foundation\Application $app */
    $app = require $basePath . '/bootstrap/app.php';
    $kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);

    $runArtisan = static function (string $command, array $params = []) use ($kernel): void {
        out('');
        out('==> php artisan ' . trim($command . ' ' . implode(' ', array_keys($params))));
        try {
            $kernel->call($command, $params);
            $text = trim((string) $kernel->output());
            if ($text !== '') {
                echo h($text) . "\n";
            } else {
                out('(done)');
            }
        } catch (\Throwable $e) {
            out('ERROR: ' . $e->getMessage());
        }
        @ob_flush();
        @flush();
    };

    // 2) APP_KEY (generate only if blank).
    $appKey = read_env_value($envFile, 'APP_KEY');
    if ($appKey === null || $appKey === '') {
        $runArtisan('key:generate', ['--force' => true]);
    } else {
        out('');
        out('==> APP_KEY already set, skipping key:generate');
    }

    // 3) Caches.
    if ($doCache) {
        $runArtisan('config:clear');
        $runArtisan('route:clear');
        $runArtisan('view:clear');
        $runArtisan('config:cache');
        $runArtisan('route:cache');
        $runArtisan('view:cache');
    }

    // 4) Migrations.
    if ($doMigrate) {
        $runArtisan('migrate', ['--force' => true]);
    }

    // 5) Storage link (with copy fallback for hosts that disable symlinks).
    if ($doStorage) {
        $runArtisan('storage:link', ['--force' => true]);
        $publicStorage = $basePath . '/public/storage';
        $target = $basePath . '/storage/app/public';
        if (!is_link($publicStorage) && !is_dir($publicStorage) && is_dir($target)) {
            out('storage:link did not create a symlink — copying files as a fallback...');
            @mkdir($publicStorage, 0755, true);
            $it = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($target, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );
            foreach ($it as $item) {
                $dest = $publicStorage . DIRECTORY_SEPARATOR . $it->getSubPathName();
                if ($item->isDir()) {
                    @mkdir($dest, 0755, true);
                } else {
                    @copy($item->getPathname(), $dest);
                }
            }
            out('Copied storage/app/public -> public/storage');
        }
    }

    // 6) Restart queue workers (signals any running queue:work to reload).
    if ($doQueue) {
        $runArtisan('queue:restart');
    }

    out('');
    out(str_repeat('-', 60));
    out(sprintf('Deployment finished in %.1fs at %s', microtime(true) - $startedAt, date('Y-m-d H:i:s')));
    echo "</pre><p><a href=\"deploy.php\">Back</a></p></body></html>";
    exit;
}

// GET: render the form.
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Deploy Laravel Backend</title>
  <style>
    body{font:15px/1.6 system-ui,Segoe UI,Roboto,Arial;margin:0;background:#f5f6f8;color:#1f2937}
    .wrap{max-width:560px;margin:40px auto;background:#fff;padding:28px;border-radius:12px;box-shadow:0 1px 4px rgba(0,0,0,.08)}
    h1{margin:0 0 4px;font-size:20px}
    .note{color:#6b7280;font-size:13px;margin:0 0 18px}
    label.row{display:flex;align-items:flex-start;gap:10px;padding:10px;border:1px solid #e5e7eb;border-radius:8px;margin:8px 0;cursor:pointer}
    label.row:hover{background:#f9fafb}
    label.row input{margin-top:3px}
    .row b{display:block}
    .row span{color:#6b7280;font-size:13px}
    .token{margin:16px 0}
    .token input{width:100%;padding:10px;border:1px solid #d1d5db;border-radius:8px;box-sizing:border-box}
    button{margin-top:8px;width:100%;padding:12px;border:0;border-radius:8px;background:#2563eb;color:#fff;font-size:15px;font-weight:600;cursor:pointer}
    button:hover{background:#1d4ed8}
    .warn{background:#fef3c7;border:1px solid #fde68a;color:#92400e;padding:10px;border-radius:8px;font-size:13px;margin-bottom:16px}
  </style>
</head>
<body>
  <div class="wrap">
    <h1>Deploy Laravel Backend</h1>
    <p class="note">Upload files via FTP first, then run the steps you need below.</p>

    <?php if ($basePath === null): ?>
      <div class="warn">Could not find <code>bootstrap/app.php</code>. Place this file in <code>public/</code> or the project root.</div>
    <?php elseif ($deployToken === null || $deployToken === ''): ?>
      <div class="warn">No <code>DEPLOY_TOKEN</code> in .env — this endpoint is unsecured. Add <code>DEPLOY_TOKEN=your-long-random-secret</code> to <code>.env</code>.</div>
    <?php endif; ?>

    <form method="post">
      <label class="row"><input type="checkbox" name="composer" value="1" checked><span><b>Run composer install</b>Install/refresh PHP dependencies (--no-dev, optimized).</span></label>
      <label class="row"><input type="checkbox" name="cache" value="1" checked><span><b>Rebuild caches</b>Clear & cache config, routes, views.</span></label>
      <label class="row"><input type="checkbox" name="migrate" value="1"><span><b>Run migrations</b>php artisan migrate --force.</span></label>
      <label class="row"><input type="checkbox" name="storage_link" value="1"><span><b>Create storage link</b>Link/copy storage/app/public to public/storage.</span></label>
      <label class="row"><input type="checkbox" name="queue_restart" value="1" checked><span><b>Restart queue workers</b>Signal running workers to reload new code.</span></label>

      <?php if ($deployToken !== null && $deployToken !== ''): ?>
        <div class="token">
          <input type="password" name="token" placeholder="Deploy token" required>
        </div>
      <?php endif; ?>

      <button type="submit">Run Deploy</button>
    </form>
  </div>
</body>
</html>
