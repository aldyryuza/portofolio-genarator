<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Media uploads
    |--------------------------------------------------------------------------
    |
    | Every upload is stored on a private disk and served through the
    | authorizing MediaController. Livewire's temporary upload limit is 12 MB,
    | so keep the byte limits below that unless Livewire is configured too.
    |
    */

    'media' => [
        'disk' => env('PORTFOLIO_MEDIA_DISK', 'local'),

        'image' => [
            'max_kb' => (int) env('PORTFOLIO_MAX_IMAGE_KB', 8192),
            'min_dimension' => 200,
            'max_dimension' => 10000,
            // Guards against decompression bombs before GD decodes the file.
            'max_pixels' => 40_000_000,
            'variants' => [
                'large' => 1920,
                'thumb' => 480,
            ],
            'quality' => 82,
        ],

        'document' => [
            'max_kb' => (int) env('PORTFOLIO_MAX_DOCUMENT_KB', 10240),
        ],
    ],

];
