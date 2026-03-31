<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class Cors extends BaseConfig
{
    public array $default = [
        'allowedOrigins' => [],
        'allowedOriginsPatterns' => [],
        'supportsCredentials' => true,  // Set to true if using cookies/tokens

        // Ensure all necessary headers are allowed
        'allowedHeaders' => [
            'Content-Type', 'Authorization', 'X-Requested-With', 'Accept', 'Origin'
        ],
        
        // Expose headers (optional, for frontend to access specific headers)
        'exposedHeaders' => ['Authorization', 'Content-Type'],

        // Allowed HTTP methods, including OPTIONS for preflight requests
        'allowedMethods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],

        'maxAge' => 7200,
    ];
}
