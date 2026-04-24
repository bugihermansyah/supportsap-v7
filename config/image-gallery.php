<?php

// config for Alsaloul/ImageGallery

return [
    /*
    |--------------------------------------------------------------------------
    | Viewer.js Configuration
    |--------------------------------------------------------------------------
    |
    | Configure how Viewer.js is loaded. By default, it's loaded from CDN.
    | Set 'cdn' to false if you want to bundle it locally.
    |
    */
    'viewer_js' => [
        'cdn' => true,
        'version' => '1.11.6',
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Settings
    |--------------------------------------------------------------------------
    |
    | These are the default values used by the image gallery components.
    | You can override these when using the components.
    |
    */
    'defaults' => [
        'thumb_width' => 128,
        'thumb_height' => 128,
        'rounded' => 'rounded-lg',
        'gap' => 'gap-4',
        'stacked' => true,
        'limit' => 3,
    ],
];
