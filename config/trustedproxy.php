<?php

$configured = env('TRUSTED_PROXIES');

return [
    // Keep null to ignore forwarded client IP headers. Production must list
    // the exact reverse-proxy addresses or CIDRs; never use a wildcard.
    'proxies' => filled($configured)
        ? array_values(array_filter(array_map('trim', explode(',', (string) $configured))))
        : null,
];
