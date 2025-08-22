<?php

return [

    'default' => env('FILESYSTEM_DISK', 'public'),

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root'   => storage_path('app'),
            'throw'  => false,
        ],

        'public' => [
            'driver'     => 'local',
            'root'       => storage_path('app/public'),
            'url'        => env('APP_URL') . '/storage',
            'visibility' => 'public',
            'throw'      => false,
        ],

        // Cloudflare R2 / S3 uyumlu
        's3' => [
            'driver'                  => 's3',
            'key'                     => env('AWS_ACCESS_KEY_ID'),
            'secret'                  => env('AWS_SECRET_ACCESS_KEY'),
            'region'                  => env('AWS_DEFAULT_REGION', 'auto'),
            'bucket'                  => env('AWS_BUCKET'),
            // R2 için endpoint zorunlu
            'endpoint'                => env('AWS_ENDPOINT'), // örn: https://<account-id>.r2.cloudflarestorage.com
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', true),
            'throw'                   => false,
            // CDN veya Custom Domain kullanıyorsan (opsiyonel):
            // 'url'                  => env('AWS_URL'),
            'visibility'              => 'public',
        ],

        // (İstersen exports diye alias bir disk tanımlayabilirsin; şart değil)
        // 'exports' => [
        //     'driver'     => 'local',
        //     'root'       => storage_path('app/public/exports'),
        //     'url'        => env('APP_URL') . '/storage/exports',
        //     'visibility' => 'public',
        //     'throw'      => false,
        // ],
    ],

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
