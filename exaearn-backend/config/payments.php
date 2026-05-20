<?php

declare(strict_types=1);

return [
    'nomba_supported_countries' => array_values(array_filter(array_map(
        static fn (string $code): string => strtoupper(trim($code)),
        explode(',', (string) env('NOMBA_SUPPORTED_COUNTRIES', 'NG,GH,KE'))
    ))),
];
