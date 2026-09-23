<?php

return [

    'driver' => env('SCOUT_DRIVER', 'collection'),

    'prefix' => env('SCOUT_PREFIX', ''),

    'queue' => env('SCOUT_QUEUE', false),

    'after_commit' => false,

    'chunk' => [
        'searchable' => 500,
        'unsearchable' => 500,
    ],

    'soft_delete' => false,

    'identify' => env('SCOUT_IDENTIFY', false),

    'algolia' => [
        'id' => env('ALGOLIA_APP_ID', ''),
        'secret' => env('ALGOLIA_SECRET', ''),
        'index-settings' => [
        ],
    ],

    'meilisearch' => [
        'host' => env('MEILISEARCH_HOST', 'http://localhost:7700'),
        'key' => env('MEILISEARCH_KEY'),
        'index-settings' => [
            'listings' => [
                'searchableAttributes' => ['search_text'],
            ],
        ],
        'model-settings' => [
        ],
    ],

    'typesense' => [
        'client-settings' => [
            'api_key' => env('TYPESENSE_API_KEY', 'xyz'),
            'nodes' => [
                [
                    'host' => env('TYPESENSE_HOST', 'localhost'),
                    'port' => env('TYPESENSE_PORT', '8108'),
                    'path' => env('TYPESENSE_PATH', ''),
                    'protocol' => env('TYPESENSE_PROTOCOL', 'http'),
                ],
            ],
            'nearest_node' => [
                'host' => env('TYPESENSE_HOST', 'localhost'),
                'port' => env('TYPESENSE_PORT', '8108'),
                'path' => env('TYPESENSE_PATH', ''),
                'protocol' => env('TYPESENSE_PROTOCOL', 'http'),
            ],
            'connection_timeout_seconds' => env('TYPESENSE_CONNECTION_TIMEOUT_SECONDS', 2),
            'healthcheck_interval_seconds' => env('TYPESENSE_HEALTHCHECK_INTERVAL_SECONDS', 30),
            'num_retries' => env('TYPESENSE_NUM_RETRIES', 3),
            'retry_interval_seconds' => env('TYPESENSE_RETRY_INTERVAL_SECONDS', 1),
        ],
        'model-settings' => [
        ],
        'import_action' => env('TYPESENSE_IMPORT_ACTION', 'upsert'),
    ],

    'turbopuffer' => [
        'api_key' => env('TURBOPUFFER_API_KEY'),
        'region' => env('TURBOPUFFER_REGION', 'gcp-us-central1'),
        'base_url' => env('TURBOPUFFER_BASE_URL'),
        'timeout' => env('TURBOPUFFER_TIMEOUT', 60),
        'connect_timeout' => env('TURBOPUFFER_CONNECT_TIMEOUT', 5),
        'retries' => env('TURBOPUFFER_RETRIES', 3),
        'model-settings' => [
        ],
    ],

];
