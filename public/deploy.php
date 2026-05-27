<?php
set_time_limit(0);
ignore_user_abort(true);

$backendRoot = dirname(__DIR__);
$envFile = $backendRoot . '/.env';
$deployToken = null;
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        if (strpos($line, '=') === false) continue;
        list($k, $v) = explode('=', $line, 2);
        $k = trim($k);
        $v = trim($v);
        $v = trim($v, "\"'");
        if ($k === 'DEPLOY_TOKEN') { $deployToken = $v; break; }
    }
}

function h($s) { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // If DEPLOY_TOKEN is set in .env, require it
    if ($deployToken !== null) {
        $provided = $_POST['token'] ?? '';
        if (!hash_equals($deployToken, $provided)) {
            http_response_code(403);
            echo '<pre>Forbidden: invalid token.</pre>';
            exit;
        }
    }

    $args = [];
    if (!empty($_POST['migrate'])) $args[] = '--migrate';
    if (!empty($_POST['storage_link'])) $args[] = '--storage-link';
    if (!empty($_POST['no_cache'])) $args[] = '--no-cache';

    $script = $backendRoot . '/deploy.sh';
    if (!file_exists($script)) {
        echo '<pre>deploy.sh not found in backend root.</pre>';
        exit;
    }

    // Build command safely: use bash to run script so execute bit not required
    $cmdParts = array_merge(['bash', $script], $args);
    $escaped = array_map('escapeshellarg', $cmdParts);
    $cmd = implode(' ', $escaped);

    echo "<html><head><meta charset=\"utf-8\"><title>Deploy</title></head><body>";
    echo "<h2>Deploy output</h2>";
    echo "<pre id=\"out\" style=\"white-space:pre-wrap;background:#111;color:#eee;padding:12px;border-radius:6px;\">";
    flush();

    // Run process and stream output
    $descriptors = [
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $process = proc_open($cmd, $descriptors, $pipes, $backendRoot);
    if (!is_resource($process)) {
        echo h("Failed to start process: $cmd");
        echo "</pre></body></html>";
        exit;
    }

    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);

    while (true) {
        $read = [];
        if (!feof($pipes[1])) $read[] = $pipes[1];
        if (!feof($pipes[2])) $read[] = $pipes[2];
        if (!$read) break;
        $r = $read; $w = $e = null;
        $num = stream_select($r, $w, $e, 2);
        if ($num === false) break;
        foreach ($r as $stream) {
            $data = fread($stream, 8192);
            if ($data !== false && $data !== '') {
                echo h($data);
                flush();
            }
        }
        $status = proc_get_status($process);
        if (!$status['running']) break;
    }

    // read any remaining
    foreach ($pipes as $p) {
        while (!feof($p)) {
            $d = fread($p, 8192);
            if ($d === false || $d === '') break;
            echo h($d);
            flush();
        }
        fclose($p);
    }

    $exit = proc_close($process);
    echo "\nProcess exited with code: " . (int)$exit;
    echo "</pre>";
    echo "<p><a href=\"deploy.php\">Back</a></p>";
    echo "</body></html>";
    exit;
}

// GET: show form
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Deploy Laravel</title>
  <style>
    body{font-family:system-ui,Segoe UI,Roboto,Arial;margin:24px}
    label{display:block;margin:8px 0}
    .note{color:#666;font-size:0.9em}
  </style>
</head>
<body>
  <h1>Deploy Laravel Backend</h1>
  <p class="note">This page will execute <code>deploy.sh</code> in the backend root. Ensure the web server user has appropriate permissions.</p>
  <?php if ($deployToken !== null): ?>
    <p class="note">DEPLOY_TOKEN is set — you must provide the token to run deploy.</p>
  <?php else: ?>
    <p class="note">No DEPLOY_TOKEN found in .env — deploy is unsecured. Consider adding <code>DEPLOY_TOKEN=yoursecret</code> to .env.</p>
  <?php endif; ?>

  <form method="post">
    <label><input type="checkbox" name="migrate" value="1"> Run migrations (--migrate)</label>
    <label><input type="checkbox" name="storage_link" value="1"> Create storage symlink (--storage-link)</label>
    <label><input type="checkbox" name="no_cache" value="1"> Skip cache build (--no-cache)</label>
    <?php if ($deployToken !== null): ?>
      <label>Token: <input type="password" name="token" required></label>
    <?php endif; ?>
    <button type="submit">Run Deploy</button>
  </form>
</body>
</html>
