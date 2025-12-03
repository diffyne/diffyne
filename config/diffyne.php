<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Transport Mode
    |--------------------------------------------------------------------------
    |
    | Diffyne supports two transport modes:
    | - 'ajax': Standard HTTP requests (default)
    | - 'websocket': Real-time WebSocket connections
    |
    */

    'transport' => env('DIFFYNE_TRANSPORT', 'ajax'),

    /*
    |--------------------------------------------------------------------------
    | WebSocket Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for WebSocket server when using 'websocket' transport mode.
    | Uses Sockeon (https://sockeon.com) for high-performance WebSocket support.
    |
    */

    'websocket' => [
        'host' => env('DIFFYNE_WS_HOST', '127.0.0.1'),
        'port' => env('DIFFYNE_WS_PORT', 6001),
        'path' => env('DIFFYNE_WS_PATH', '/diffyne'),
        'key' => env('DIFFYNE_WS_KEY', 'd2c61c0f8393e8b5273e84879276cbe7'),
        'cors' => [
            'allowed_origins' => explode(',', env('DIFFYNE_WS_CORS_ORIGINS', '*')),
            'allowed_methods' => ['GET', 'POST', 'OPTIONS'],
            'allowed_headers' => ['Content-Type', 'Authorization', 'X-CSRF-TOKEN'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Component Namespace
    |--------------------------------------------------------------------------
    |
    | Default namespace for generating Diffyne components.
    |
    */

    'component_namespace' => 'App\\Diffyne',

    /*
    |--------------------------------------------------------------------------
    | View Path
    |--------------------------------------------------------------------------
    |
    | Default path for Diffyne component views.
    |
    */

    'view_path' => resource_path('views/diffyne'),

    /*
    |--------------------------------------------------------------------------
    | Route Prefix
    |--------------------------------------------------------------------------
    |
    | The prefix for Diffyne's internal routes.
    |
    */

    'route_prefix' => '_diffyne',

    /*
    |--------------------------------------------------------------------------
    | Middleware
    |--------------------------------------------------------------------------
    |
    | Middleware applied to Diffyne routes.
    |
    */

    'middleware' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Asset URL
    |--------------------------------------------------------------------------
    |
    | URL path for Diffyne's JavaScript assets.
    |
    */

    'asset_url' => '/vendor/diffyne',

    /*
    |--------------------------------------------------------------------------
    | Debug Mode
    |--------------------------------------------------------------------------
    |
    | Enable verbose logging and debugging information.
    |
    */

    'debug' => env('DIFFYNE_DEBUG', false),

    /*
    |--------------------------------------------------------------------------
    | Performance Options
    |--------------------------------------------------------------------------
    |
    | Fine-tune performance and caching behavior.
    |
    */

    'performance' => [
        'cache_rendered_views' => env('DIFFYNE_CACHE_VIEWS', true),
        'minify_patches' => env('DIFFYNE_MINIFY_PATCHES', true),
        'debounce_default' => 150, // milliseconds
        'max_request_size' => 512 * 1024, // 512KB max request
        'enable_compression' => env('DIFFYNE_COMPRESSION', true),
        'snapshot_cache_size' => 100, // Max components to cache snapshots for
    ],

];
