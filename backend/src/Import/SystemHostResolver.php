<?php

declare(strict_types=1);

namespace App\Import;

final class SystemHostResolver implements HostResolver
{
    public function resolve(string $host): array
    {
        $ips = [];
        $records = dns_get_record($host, DNS_A + DNS_AAAA);
        foreach ($records ?: [] as $record) {
            $ip = $record['ip'] ?? null;
            if (is_string($ip)) {
                $ips[] = $ip;
            }
            $ipv6 = $record['ipv6'] ?? null;
            if (is_string($ipv6)) {
                $ips[] = $ipv6;
            }
        }
        if ($ips === []) {
            $fallback = gethostbynamel($host);
            if (is_array($fallback)) {
                foreach ($fallback as $ip) {
                    $ips[] = $ip;
                }
            }
        }

        return array_values(array_unique($ips));
    }
}
