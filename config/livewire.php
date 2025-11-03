<?php

return [
    'temporary_file_upload' => [
        'disk' => 'local',
        'rules' => ['required', 'file', 'max:51200'], // Ditingkatkan ke 50MB Max
        'directory' => 'livewire-tmp',
        'middleware' => null, // Remove throttle middleware
        'preview_mimes' => [
            'png', 'gif', 'bmp', 'svg', 'wav', 'mp4',
            'mov', 'avi', 'wmv', 'mp3', 'm4a',
            'jpg', 'jpeg', 'mpga', 'webp', 'wma',
        ],
        'max_upload_time' => 30, // Ditingkatkan menjadi 30 detik
    ],
    'asset_url' => null,
    'app_url' => env('APP_URL', 'https://sneakersflash.com'), // Domain diperbarui
    'middleware_group' => 'web',
    'manifest_path' => null,
];