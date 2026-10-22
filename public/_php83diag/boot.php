<?php
// Boot Laravel under PHP 8.3 in isolation to capture what fails, without
// switching the live site over. Emits only class/message/file/line — never a
// full trace, which can carry argument values.
header('Content-Type: text/plain');

$root = dirname(__DIR__, 2);

register_shutdown_function(function () {
    $e = error_get_last();
    // Catches compile-time fatals, which try/catch cannot.
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_COMPILE_ERROR, E_CORE_ERROR], true)) {
        echo "\nFATAL: " . $e['message'] . "\n  at " . $e['file'] . ':' . $e['line'] . "\n";
    }
});

echo 'php ' . PHP_VERSION . "\n\n";

try {
    require $root . '/vendor/autoload.php';
    echo "[ok] autoload\n";

    $app = require $root . '/bootstrap/app.php';
    echo "[ok] bootstrap/app.php\n";

    $kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
    echo "[ok] http kernel resolved\n";

    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    echo "[ok] framework bootstrapped\n";
} catch (Throwable $e) {
    echo "\nTHROWN: " . get_class($e) . "\n  " . $e->getMessage()
       . "\n  at " . $e->getFile() . ':' . $e->getLine() . "\n";
}
