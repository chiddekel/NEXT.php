<?php

declare(strict_types=1);

return [
    'revalidate' => 60,
    'redirects' => [
        [
            'source' => '/old-pricing',
            'destination' => '/pricing',
            'permanent' => true,
        ],
    ],
    'rewrites' => [
        [
            'source' => '/custom-docs',
            'destination' => '/docs/getting-started',
        ],
    ],
    'headers' => [
        [
            'source' => '/:path*',
            'headers' => [
                'X-Powered-By' => 'NEXT.php',
            ],
        ],
    ],
];
