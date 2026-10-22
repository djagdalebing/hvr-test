<?php
// Contains nothing sensitive on purpose: if the handler turns out to be
// load-bearing, this file gets served as plain source.
header('Content-Type: text/plain');
echo 'EXECUTED php ' . PHP_VERSION . "\n";
