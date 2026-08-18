<?php

$splitList = static fn (?string $value): array => array_values(array_filter(array_map(
    'trim',
    explode(',', (string) $value),
)));
$trustedHosts = $splitList(env('TRUSTED_HOSTS'));
$applicationHost = parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_HOST);

if ($trustedHosts === [] && is_string($applicationHost) && $applicationHost !== '') {
    $trustedHosts = ['^'.preg_quote($applicationHost).'$'];
}

return [
    /*
    | TrustProxies reads this configuration at request time, so it remains
    | compatible with Laravel's cached production configuration.
    */
    'proxies' => $splitList(env('TRUSTED_PROXIES')),

    /* Each entry is a Symfony trusted-host regular expression. */
    'hosts' => $trustedHosts,
];
