<?php
// Emits ONLY the version and build date. phpinfo() in full would expose
// paths, extensions and environment on a public URL, so it is captured into
// a buffer and everything except the Build Date line is discarded.
header('Content-Type: text/plain');
ob_start();
phpinfo(INFO_GENERAL);
$info = ob_get_clean();
$build = 'unknown';
if (preg_match('/Build Date\s*(?:<\/td><td[^>]*>)?\s*([^<\n]+)/i', $info, $m)) {
    $build = trim($m[1]);
}
echo 'php ' . PHP_VERSION . "\n";
echo 'build date ' . $build . "\n";
