<?php
return [
    "paths" => ["api/*", "sanctum/csrf-cookie"],
    "allowed_methods" => ["*"],
    'allowed_origins' => [
    'http://localhost:5173',
    'https://localhost:5173',
    'http://localhost:5174',
    'https://localhost:5174',
    'https://192.168.253.42:5173',
    'https://192.168.253.42:5174',
],
    "allowed_origins_patterns" => [],
    "allowed_headers" => ["*"],
    "exposed_headers" => [],
    "max_age" => 0,
    "supports_credentials" => false,
];
