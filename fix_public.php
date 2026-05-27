<?php
// fix_public.php
// Place this file in the Laravel project root (backend/) and open it in the browser.
// It will copy files from `public/` into the current directory and adjust index.php paths
// so the app can run when the webserver serves the project root directly (shared hosting).

set_time_limit(0);
$root = __DIR__;
$public = $root . DIRECTORY_SEPARATOR . 'public';

function h($s){ return htmlspecialchars($s, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }

if (!is_dir($public)) {
    echo "<p><strong>Error:</strong> public/ folder not found at " . h($public) . "</p>";
    exit;
}

$action = $_POST['action'] ?? null;

if ($action === 'preview') {
    // Show diagnostics
    $vendor_exists = is_dir($root . DIRECTORY_SEPARATOR . 'vendor');
    $env_exists = file_exists($root . DIRECTORY_SEPARATOR . '.env');
    $storage_writable = is_writable($root . DIRECTORY_SEPARATOR . 'storage');
    $bootstrap_writable = is_writable($root . DIRECTORY_SEPARATOR . 'bootstrap' . DIRECTORY_SEPARATOR . 'cache');

    echo "<h2>Diagnostics</h2>";
    echo "<ul>";
    echo "<li>Project root: " . h($root) . "</li>";
    echo "<li>public folder: " . h($public) . "</li>";
    echo "<li>vendor exists: " . ($vendor_exists ? '<strong style=\"color:green\">yes</strong>' : '<strong style=\"color:red\">no</strong>') . "</li>";
    echo "<li>.env exists: " . ($env_exists ? '<strong style=\"color:green\">yes</strong>' : '<strong style=\"color:red\">no</strong>') . "</li>";
    echo "<li>storage writable: " . ($storage_writable ? '<strong style=\"color:green\">yes</strong>' : '<strong style=\"color:red\">no</strong>') . "</li>";
    echo "<li>bootstrap/cache writable: " . ($bootstrap_writable ? '<strong style=\"color:green\">yes</strong>' : '<strong style=\"color:red\">no</strong>') . "</li>";
    echo "</ul>";

    echo "<h3>Files in public (top-level)</h3>";
    $top = array_filter(scandir($public), function($f){ return $f !== '.' && $f !== '..'; });
    echo "<pre>" . h(implode("\n", $top)) . "</pre>";

    echo "<p><a href=\"fix_public.php\">Back</a></p>";
    exit;
}

if ($action === 'run') {
    $copied = [];
    $errors = [];

    $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($public, RecursiveDirectoryIterator::SKIP_DOTS));
    foreach ($rii as $file) {
        $srcPath = $file->getPathname();
        $rel = substr($srcPath, strlen($public) + 1);
        $destPath = $root . DIRECTORY_SEPARATOR . $rel;

        // Skip if fix_public.php itself would be overwritten
        if (basename($destPath) === basename(__FILE__)) {
            continue;
        }

        if ($file->isDir()) {
            if (!is_dir($destPath)) {
                if (!mkdir($destPath, 0755, true)) {
                    $errors[] = "Failed to create directory: $destPath";
                }
            }
            continue;
        }

        // Copy file
        if (!copy($srcPath, $destPath)) {
            $errors[] = "Failed to copy $srcPath to $destPath";
        } else {
            $copied[] = $rel;
        }
    }

    // Adjust index.php if it exists in $root (copied from public)
    $rootIndex = $root . DIRECTORY_SEPARATOR . 'index.php';
    if (file_exists($rootIndex)) {
        $idx = file_get_contents($rootIndex);
        // replace vendor and bootstrap paths from public/index.php style to new locations
        $idx = str_replace("__DIR__.'/../vendor/autoload.php'", "__DIR__.'/vendor/autoload.php'", $idx);
        $idx = str_replace('__DIR__ . "/../vendor/autoload.php"', "__DIR__.'/vendor/autoload.php'", $idx);
        $idx = str_replace("__DIR__.'/../bootstrap/app.php'", "__DIR__.'/bootstrap/app.php'", $idx);
        $idx = str_replace('__DIR__ . "/../bootstrap/app.php"', "__DIR__.'/bootstrap/app.php'", $idx);
        file_put_contents($rootIndex, $idx);
    }

    echo "<h2>Copy result</h2>";
    echo "<p>Copied " . count($copied) . " files.</p>";
    if ($errors) {
        echo "<h3>Errors</h3><pre>" . h(implode("\n", $errors)) . "</pre>";
    }
    echo "<p>Index adjustments done (if index.php present).</p>";
    echo "<p>Next steps:<ul>";
    echo "<li>Ensure <code>vendor/</code> is uploaded (composer dependencies). If missing, upload the <code>vendor</code> directory via FTP from your local machine.</li>";
    echo "<li>Open the site URL and check for errors. If you see an exception about permissions, set writable permissions on <code>storage/</code> and <code>bootstrap/cache</code>.</li>";
    echo "</ul></p>";
    echo "<p><a href=\"fix_public.php\">Back</a></p>";
    exit;
}

// UI
?>
<!doctype html>
<html>
<head><meta charset="utf-8"><title>Laravel public fixer</title></head>
<body>
  <h1>Laravel public/ -> project root helper</h1>
  <p>This helper copies files from <code>public/</code> into the project root and adjusts paths in <code>index.php</code> so Laravel can run when the webserver serves the project root directly (shared hosting scenario).</p>
  <p><strong>Warning:</strong> This will make your project root web-accessible. For security, remove this file after use.</p>

  <form method="post">
    <button name="action" value="preview" type="submit">Run diagnostics</button>
    <button name="action" value="run" type="submit" onclick="return confirm('Copy public files into project root? This may overwrite files. Proceed?')">Copy public files and adjust index.php</button>
  </form>

</body>
</html>
