<?php

return [

    /*
      |--------------------------------------------------------------------------
      | Default Filesystem Disk
      |--------------------------------------------------------------------------
      |
      | Here you may specify the default filesystem disk that should be used
      | by the framework. The "local" disk, as well as a variety of cloud
      | based disks are available to your application. Just store away!
      |
      */

    'default' => env('FILESYSTEM_DRIVER', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Default Cloud Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Many applications store files both locally and in the cloud. For this
    | reason, you may specify a default "cloud" driver here. This driver
    | will be bound as the Cloud disk implementation in the container.
    |
    */

    'cloud' => env('FILESYSTEM_CLOUD', 's3'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Here you may configure as many filesystem "disks" as you wish, and you
    | may even configure multiple disks of the same driver. Defaults have
    | been setup for each driver as an example of the required options.
    |
    | Supported Drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [
        'local' => [
            'driver' => 'local',
            'root' => storage_path('app'),
        ],

        'uploads' => [
            'driver' => 'dynamic-uploads',
        ],

        'public' => [
            'driver' => 'dynamic-public',
            'url' => 'storage',
            'visibility' => 'public',
        ],

        // Cloudflare R2 — S3-compatible object storage for creator video.
        // Uploads go browser → R2 directly via a presigned PUT URL (so PHP
        // never touches the file and the 413 limit no longer applies).
        // Delivery is via R2's public URL (free egress); Plyr plays the MP4.
        'r2' => [
            'driver'                  => 's3',
            'key'                     => env('R2_ACCESS_KEY_ID'),
            'secret'                  => env('R2_SECRET_ACCESS_KEY'),
            'region'                  => 'auto',
            'bucket'                  => env('R2_BUCKET'),
            'endpoint'                => env('R2_ENDPOINT'),
            'use_path_style_endpoint' => true,
            // Public base URL for serving objects (R2.dev subdomain or a
            // custom domain bound to the bucket).
            'url'                     => env('R2_PUBLIC_URL'),
        ],
    ],

    /*
   |--------------------------------------------------------------------------
   | Symbolic Links
   |--------------------------------------------------------------------------
   |
   | Here you may configure the symbolic links that will be created when the
   | `storage:link` Artisan command is executed. The array keys should be
   | the locations of the links and the values should be their targets.
   |
   */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
