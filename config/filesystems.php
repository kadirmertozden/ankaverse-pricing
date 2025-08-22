<?php

return [

    'default' => env('FILESYSTEM_DISK', 'public'),

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root'   => storage_path('app'),
            'throw'  => false,
        ],

// filesystems.php 'disks' içine
'public_root' => [
    'driver' => 'local',
    'root' => public_path(''),
    'visibility' => 'public',
    'throw' => false,
],


        // İstersen exports için ayrı disk (opsiyonel):
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
