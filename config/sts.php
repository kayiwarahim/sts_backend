<?php

return [

    'base_url' => env('STS_API_BASE_URL'),

    'user_id' => env('STS_API_USER_ID'),

    'password' => env('STS_API_PASSWORD'),

    'meter_type' => (int) env('STS_METER_TYPE', 2),

    'vending_type' => (int) env('STS_VENDING_TYPE', 1),

    'timeout' => 30,

];
