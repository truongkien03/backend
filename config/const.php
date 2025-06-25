<?php

return [
    'cost' => [
        'first_km' => 10000,
        'from_2nd_km' => 5000,
        'peak_hours' => [
            11, 12, 13, 17, 18, 19
        ],
        'peak_hour_addition_rate' => 0.2,
    ],
    'order' => [
        'status' => [
            'pending' => 1,
            'inprocess' => 2,
            'completed' => 3,
            'cancled_by_user' => 4,
            'cancled_by_driver' => 5,
            'cancled_by_system' => 6,
        ]
    ],
    'driver' => [
        'status' => [
            'free' => 1,
            'offline' => 2,
            'busy' => 3
        ]
    ]
];
