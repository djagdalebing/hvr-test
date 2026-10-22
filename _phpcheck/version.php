<?php
// Temporary diagnostic. Inherits the root .htaccess, including the cPanel
// AddHandler line, so this reports the version actually serving the site.
header('Content-Type: text/plain');
echo 'EXECUTED php ' . PHP_VERSION . "\n";
echo 'build ' . php_uname('s') . "\n";
