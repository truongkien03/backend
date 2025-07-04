<?php

return [
    'osrm' => [
        'base_url' => env('OSRM_BASE_URL', 'http://router.project-osrm.org'),
        'timeout' => env('OSRM_TIMEOUT', 10), // seconds
    ],
    
    'nominatim' => [
        'base_url' => env('NOMINATIM_BASE_URL', 'https://nominatim.openstreetmap.org'),
        'timeout' => env('NOMINATIM_TIMEOUT', 10),
        'rate_limit' => env('NOMINATIM_RATE_LIMIT', 1), // requests per second
    ],
    
    'tiles' => [
        'url_template' => env('OSM_TILE_URL', 'https://tile.openstreetmap.org/{z}/{x}/{y}.png'),
        'attribution' => '© OpenStreetMap contributors',
    ]
];
