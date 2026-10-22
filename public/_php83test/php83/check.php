<?php
// Trivial on purpose: if a handler turns out to be unrecognised, this file
// gets served as plain source, so it must contain nothing sensitive.
header('Content-Type: text/plain');
echo 'EXECUTED php ' . PHP_VERSION . "\n";
