<?php

/**
 * Diagnostic — verify creator video + settings state.
 * Run from project root:  php scripts/diag_creator_video.php
 *
 * Safe to delete after the issue is resolved.
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Common\Settings\Settings;

$line = str_repeat('-', 60);

echo $line . PHP_EOL;
echo "SETTINGS — DB row" . PHP_EOL;
echo $line . PHP_EOL;
foreach (['streaming.show_header_play', 'streaming.prefer_full', 'streaming.streaming.show_header_play'] as $k) {
    $v = DB::table('settings')->where('name', $k)->value('value');
    echo str_pad($k, 45) . ' => ' . var_export($v, true) . PHP_EOL;
}

echo PHP_EOL . $line . PHP_EOL;
echo "SETTINGS — resolved via Settings service (cached)" . PHP_EOL;
echo $line . PHP_EOL;
$s = app(Settings::class);
foreach (['streaming.show_header_play', 'streaming.prefer_full'] as $k) {
    echo str_pad($k, 45) . ' => ' . var_export($s->get($k), true) . PHP_EOL;
}

echo PHP_EOL . $line . PHP_EOL;
echo "TITLE: comando" . PHP_EOL;
echo $line . PHP_EOL;
$title = DB::table('titles')->where('name', 'comando')->orderByDesc('id')->first();
if (!$title) {
    echo "NOT FOUND" . PHP_EOL;
    exit;
}
echo "id:       " . $title->id . PHP_EOL;
echo "poster:   " . $title->poster . PHP_EOL;
echo "backdrop: " . var_export($title->backdrop, true) . PHP_EOL;

echo PHP_EOL . $line . PHP_EOL;
echo "VIDEO(S) for comando" . PHP_EOL;
echo $line . PHP_EOL;
$videos = DB::table('videos')->where('title_id', $title->id)->get();
if ($videos->isEmpty()) {
    echo "NO VIDEOS" . PHP_EOL;
} else {
    foreach ($videos as $v) {
        echo "id:       " . $v->id . PHP_EOL;
        echo "user_id:  " . var_export($v->user_id, true) . PHP_EOL;
        echo "type:     " . var_export($v->type, true) . PHP_EOL;
        echo "category: " . var_export($v->category, true) . PHP_EOL;
        echo "source:   " . var_export($v->source, true) . PHP_EOL;
        echo "approved: " . var_export($v->approved, true) . PHP_EOL;
        echo "url:      " . $v->url . PHP_EOL;
        echo "---" . PHP_EOL;
    }
}
