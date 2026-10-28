<?php

// Thread workers wherever ext-parallel is loaded (a ZTS PHP), so the async tests' thread cases run there.
return [
    'pool_workers' => [
        'process' => [
            'enabled' => true,
            'max_workers' => 4,
        ],
        'threads' => [
            'enabled' => extension_loaded('parallel'),
            'max_workers' => 4,
        ],
    ],
];
