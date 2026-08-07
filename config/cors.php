<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    // 'secure/*' added so the native (Capacitor) app can call the SPA API
    // cross-origin. Token auth only (no cookies), so credentials stay off and
    // wildcard origin is safe.
    // 'client/*' added so the bundled native app can cross-origin-fetch static
    // front-end assets from the backend — notably the SVG icon sprite
    // (client/assets/icons/merged.svg). Without CORS headers WKWebView blocks
    // that fetch, so every mat-icon (navbar buttons, share/rate icons) renders
    // blank. Public static files, so wildcard origin is safe.
    'paths' => ['api/*', 'secure/*', 'client/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => ['*'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
