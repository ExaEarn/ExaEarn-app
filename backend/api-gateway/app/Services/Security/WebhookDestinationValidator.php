<?php

declare(strict_types=1);

namespace App\Services\Security;

use RuntimeException;

class WebhookDestinationValidator
{
    public function __construct(private readonly DnsResolver $dns) {}

    /** @return array{url:string,host:string,port:int,addresses:list<string>,pinned_address:string} */
    public function validate(string $url): array
    {
        if ($url === '' || preg_match('/[\x00-\x20\x7f]/', $url)) {
            throw new RuntimeException('Webhook destination is invalid.');
        }
        $parts = parse_url($url);
        if (!is_array($parts) || strtolower((string)($parts['scheme'] ?? '')) !== 'https' || empty($parts['host'])) {
            throw new RuntimeException('Webhook destinations must use HTTPS.');
        }
        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new RuntimeException('Webhook destination user information is not allowed.');
        }
        $host = strtolower(rtrim((string)$parts['host'], '.'));
        if ($host === '' || $host === 'localhost' || $host === 'localhost.localdomain' || str_contains($host, '%')) {
            throw new RuntimeException('Webhook destination host is not allowed.');
        }
        if (function_exists('idn_to_ascii') && !filter_var($host, FILTER_VALIDATE_IP)) {
            $ascii = idn_to_ascii($host, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);
            if ($ascii === false) throw new RuntimeException('Webhook destination host is invalid.');
            $host = strtolower($ascii);
        }
        $port = (int)($parts['port'] ?? 443);
        $allowedPorts = array_map('intval', (array)config('developer_api.webhooks.allowed_ports', [443]));
        if (!in_array($port, $allowedPorts, true)) {
            throw new RuntimeException('Webhook destination port is not allowed.');
        }
        $addresses = $this->dns->resolve($host);
        if ($addresses === []) throw new RuntimeException('Webhook destination could not be resolved.');
        foreach ($addresses as $address) $this->assertPublicAddress($address);

        $canonicalHost = str_contains($host, ':') ? "[{$host}]" : $host;
        $authority = $port === 443 ? $canonicalHost : "{$canonicalHost}:{$port}";
        $normalized = 'https://'.$authority.($parts['path'] ?? '/');
        if (isset($parts['query'])) $normalized .= '?'.$parts['query'];

        return ['url'=>$normalized,'host'=>$host,'port'=>$port,'addresses'=>$addresses,'pinned_address'=>$addresses[0]];
    }

    private function assertPublicAddress(string $address): void
    {
        $candidate = $address;
        if (str_starts_with(strtolower($candidate), '::ffff:')) {
            $candidate = substr($candidate, 7);
        }
        if (!filter_var($candidate, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            throw new RuntimeException('Webhook destination resolved to a prohibited network.');
        }
        if (in_array(strtolower($address), ['169.254.169.254', 'fd00:ec2::254'], true)) {
            throw new RuntimeException('Webhook destination resolved to a prohibited network.');
        }
    }
}
