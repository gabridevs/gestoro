<?php

return [
    'api_prezzi' => [
        'provider_primario' => env('METAL_API_PRIMARY', 'metalpriceapi'),
        'api_key' => env('METAL_API_KEY'),
        'cache_minuti' => env('METAL_CACHE_MINUTES', 5),
        'timeout_secondi' => 10
    ],

    'margini_commerciali' => [
        'oro' => [
            '999' => 0.02, // 2%
            '750' => 0.03, // 3%  
            '585' => 0.04, // 4%
            'default' => 0.05
        ],
        'argento' => [
            '999' => 0.03,
            '925' => 0.04,
            'default' => 0.05
        ]
    ],

    'soglie_antiriciclaggio' => [
        'contanti_max' => 2999.99,
        'verifica_documenti_giorni' => 365,
        'comunicazione_bancaitalia_soglia' => 10000
    ],

    'giacenza_obbligatoria' => [
        'giorni_default' => 10,
        'giorni_alto_valore' => 30
    ]
];
