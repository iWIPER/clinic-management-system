<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Timeout padrão para chamadas externas (segundos)
    |--------------------------------------------------------------------------
    */
    'http_timeout' => (int) env('FINANCIAL_HTTP_TIMEOUT', 15),

    /*
    |--------------------------------------------------------------------------
    | Circuit Breaker
    |--------------------------------------------------------------------------
    */
    'circuit_breaker' => [
        'failure_threshold' => (int) env('FINANCIAL_CB_FAILURES', 5),
        'open_seconds'      => (int) env('FINANCIAL_CB_OPEN_SECONDS', 120),
    ],

    /*
    |--------------------------------------------------------------------------
    | Instituições financeiras disponíveis no marketplace
    |--------------------------------------------------------------------------
    */
    'institutions' => [
        'banco_bv' => [
            'name'        => 'Banco BV',
            'product'     => 'Financiamento Saúde BV',
            'description' => 'Credit as a Service — financiamento para tratamentos odontológicos.',
            'auth'        => 'oauth2_client_credentials',
            'sandbox'     => [
                'token_url'     => env('BV_SANDBOX_TOKEN_URL', 'https://developers-sandbox.bvopen.com.br/oauth/token'),
                'api_base'      => env('BV_SANDBOX_API_BASE', 'https://developers-sandbox.bvopen.com.br/api'),
                'simulate_path' => '/financiamento-saude/v1/simulacoes',
                'proposal_path' => '/financiamento-saude/v1/propostas',
            ],
            'production' => [
                'token_url'     => env('BV_PRODUCTION_TOKEN_URL', 'https://api.bvopen.com.br/oauth/token'),
                'api_base'      => env('BV_PRODUCTION_API_BASE', 'https://api.bvopen.com.br/api'),
                'simulate_path' => '/financiamento-saude/v1/simulacoes',
                'proposal_path' => '/financiamento-saude/v1/propostas',
            ],
        ],
        'dr_cash' => [
            'name'        => 'Dr.Cash',
            'product'     => 'Crédito odontológico Dr.Cash',
            'description' => 'Antecipação e crédito para clínicas odontológicas.',
            'auth'        => 'oauth2_client_credentials',
            'sandbox'     => [
                'token_url'     => env('DR_CASH_SANDBOX_TOKEN_URL', 'https://sandbox-api.drcash.com.br/oauth/token'),
                'api_base'      => env('DR_CASH_SANDBOX_API_BASE', 'https://sandbox-api.drcash.com.br/v1'),
                'simulate_path' => '/simulacoes',
                'proposal_path' => '/propostas',
            ],
            'production' => [
                'token_url'     => env('DR_CASH_PRODUCTION_TOKEN_URL', 'https://api.drcash.com.br/oauth/token'),
                'api_base'      => env('DR_CASH_PRODUCTION_API_BASE', 'https://api.drcash.com.br/v1'),
                'simulate_path' => '/simulacoes',
                'proposal_path' => '/propostas',
            ],
        ],
        'dental_cred' => [
            'name'        => 'DentalCred',
            'product'     => 'DentalCred',
            'description' => 'Financiamento especializado para odontologia.',
            'auth'        => 'oauth2_client_credentials',
            'sandbox'     => [
                'token_url'     => env('DENTAL_CRED_SANDBOX_TOKEN_URL', 'https://sandbox.dentalcred.com.br/oauth/token'),
                'api_base'      => env('DENTAL_CRED_SANDBOX_API_BASE', 'https://sandbox.dentalcred.com.br/api/v1'),
                'simulate_path' => '/credit/simulate',
                'proposal_path' => '/credit/proposals',
            ],
            'production' => [
                'token_url'     => env('DENTAL_CRED_PRODUCTION_TOKEN_URL', 'https://api.dentalcred.com.br/oauth/token'),
                'api_base'      => env('DENTAL_CRED_PRODUCTION_API_BASE', 'https://api.dentalcred.com.br/api/v1'),
                'simulate_path' => '/credit/simulate',
                'proposal_path' => '/credit/proposals',
            ],
        ],
        'konsiga' => [
            'name'        => 'Konsiga',
            'product'     => 'Konsiga Saúde',
            'description' => 'Crédito e parcelamento para clínicas de saúde.',
            'auth'        => 'oauth2_client_credentials',
            'sandbox'     => [
                'token_url'     => env('KONSIGA_SANDBOX_TOKEN_URL', 'https://sandbox.konsiga.com.br/oauth/token'),
                'api_base'      => env('KONSIGA_SANDBOX_API_BASE', 'https://sandbox.konsiga.com.br/api/v1'),
                'simulate_path' => '/financing/simulate',
                'proposal_path' => '/financing/proposals',
            ],
            'production' => [
                'token_url'     => env('KONSIGA_PRODUCTION_TOKEN_URL', 'https://api.konsiga.com.br/oauth/token'),
                'api_base'      => env('KONSIGA_PRODUCTION_API_BASE', 'https://api.konsiga.com.br/api/v1'),
                'simulate_path' => '/financing/simulate',
                'proposal_path' => '/financing/proposals',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Mapeamento provider → classe Gateway
    |--------------------------------------------------------------------------
    */
    'gateways' => [
        'banco_bv'    => \App\Services\Financial\Gateways\BancoBVGateway::class,
        'dr_cash'     => \App\Services\Financial\Gateways\DrCashGateway::class,
        'dental_cred' => \App\Services\Financial\Gateways\DentalCredGateway::class,
        'konsiga'     => \App\Services\Financial\Gateways\KonsigaGateway::class,
    ],
];