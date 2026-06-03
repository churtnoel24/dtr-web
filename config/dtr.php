<?php

return [
    'api_base_url' => env('DTR_API_BASE_URL', 'https://dtr2026-read.iamlance.site'),
    'api_timeout' => (int) env('DTR_API_TIMEOUT_SECONDS', 300),
    'api_jwt_key' => env('DTR_API_JWT_KEY', ''),
    'api_jwt_issuer' => env('DTR_API_JWT_ISSUER', 'IOT-API'),
    'api_jwt_audience' => env('DTR_API_JWT_AUDIENCE', 'IOT-Client'),
    'api_jwt_subject' => env('DTR_API_JWT_SUBJECT', 'laravel-dtr'),
    'api_jwt_expiration_minutes' => (int) env('DTR_API_JWT_EXPIRATION_MINUTES', 60),
];
