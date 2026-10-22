<?php
// Boot AND serve real requests under PHP 8.3 in isolation, so routing,
// middleware, the database and our own services are all exercised before the
// live interpreter is switched. Read-only GETs only.
header('Content-Type: text/plain');

$root = dirname(__DIR__, 2);

register_shutdown_function(function () {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_COMPILE_ERROR, E_CORE_ERROR], true)) {
        echo "\nFATAL: " . $e['message'] . "\n  at " . $e['file'] . ':' . $e['line'] . "\n";
    }
});

echo 'php ' . PHP_VERSION . "\n\n";

try {
    require $root . '/vendor/autoload.php';
    $app = require $root . '/bootstrap/app.php';
    $kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
    echo "[ok] framework booted\n\n";

    // Representative of the real traffic mix: homepage rows (our computed
    // sections + DB), a title with its videos, the guide's settings lookup,
    // and a computed list page.
    $routes = [
        '/secure/homepage/lists',
        '/secure/titles/57825',
        '/secure/creator-guide',
        '/secure/lists/hvn-films',
        '/secure/bootstrap-data',
    ];

    foreach ($routes as $uri) {
        try {
            $request = Illuminate\Http\Request::create($uri, 'GET');
            $response = $kernel->handle($request);
            $code = $response->getStatusCode();
            $len = strlen($response->getContent());
            printf("[%s] %-28s %d  %d bytes\n", $code < 400 ? 'ok' : 'FAIL', $uri, $code, $len);
        } catch (Throwable $e) {
            printf("[FAIL] %-28s %s: %s\n  at %s:%d\n", $uri,
                get_class($e), $e->getMessage(), $e->getFile(), $e->getLine());
        }
    }
} catch (Throwable $e) {
    echo "\nTHROWN: " . get_class($e) . "\n  " . $e->getMessage()
       . "\n  at " . $e->getFile() . ':' . $e->getLine() . "\n";
}
