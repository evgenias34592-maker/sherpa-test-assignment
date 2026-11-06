<?php

declare(strict_types=1);

namespace App\Import;

final class UrlSafetyChecker
{
    public function __construct(private readonly HostResolver $resolver)
    {
    }

    public function check(string $url): CheckedUrl
    {
        $url = trim($url);
        if (strlen($url) > 2000) {
            throw new ImageFetchException('Слишком длинная ссылка на изображение');
        }
        $parts = parse_url($url);
        if (!is_array($parts) || !isset($parts['scheme'], $parts['host'])) {
            throw new ImageFetchException('Некорректная ссылка на изображение');
        }
        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new ImageFetchException('Ссылка на изображение содержит учётные данные');
        }

        $scheme = strtolower((string) $parts['scheme']);
        if ($scheme !== 'http' && $scheme !== 'https') {
            throw new ImageFetchException('Разрешены только ссылки http и https');
        }

        $host = strtolower(rtrim((string) $parts['host'], '.'));
        if ($host === '' || $this->isBlockedHost($host)) {
            throw new ImageFetchException('Ссылка на изображение ведёт во внутреннюю сеть');
        }

        $port = isset($parts['port']) ? (int) $parts['port'] : ($scheme === 'https' ? 443 : 80);
        $expected = $scheme === 'https' ? 443 : 80;
        if ($port !== $expected) {
            throw new ImageFetchException('Разрешены только стандартные порты 80 и 443');
        }

        $ips = $this->addresses($host);
        foreach ($ips as $ip) {
            if (!$this->isPublic($ip)) {
                throw new ImageFetchException('Ссылка на изображение ведёт во внутреннюю сеть');
            }
        }

        $path = (string) ($parts['path'] ?? '/');
        if ($path === '') {
            $path = '/';
        }
        if (isset($parts['query'])) {
            $path .= '?' . $parts['query'];
        }

        return new CheckedUrl($url, $scheme, $host, $port, $path, $ips[0]);
    }

    public function resolveRedirect(CheckedUrl $current, string $location): CheckedUrl
    {
        $location = trim($location);
        if ($location === '') {
            throw new ImageFetchException('Пустой редирект изображения');
        }
        if (str_starts_with($location, 'http://') || str_starts_with($location, 'https://')) {
            return $this->check($location);
        }
        if (str_starts_with($location, '//')) {
            return $this->check($current->scheme . ':' . $location);
        }
        $path = str_starts_with($location, '/')
            ? $location
            : rtrim(str_contains($current->pathAndQuery, '/') ? substr($current->pathAndQuery, 0, (int) strrpos($current->pathAndQuery, '/')) : '', '/') . '/' . $location;

        return $this->check($current->scheme . '://' . $current->host . $path);
    }

    /** @return list<string> */
    private function addresses(string $host): array
    {
        $mapped = $this->mappedIpv4($host);
        if ($mapped !== null) {
            return [$mapped];
        }
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return [$host];
        }
        $ips = $this->resolver->resolve($host);
        if ($ips === []) {
            throw new ImageFetchException('Не удалось определить адрес изображения');
        }

        $normalized = [];
        foreach ($ips as $ip) {
            $normalized[] = $this->mappedIpv4($ip) ?? $ip;
        }

        return $normalized;
    }

    /**
     * IPv4-mapped IPv6 (::ffff:127.0.0.1 and the same address in brackets) is checked as IPv4.
     */
    private function mappedIpv4(string $value): ?string
    {
        $candidate = strtolower(trim($value));
        if (str_starts_with($candidate, '[') && str_ends_with($candidate, ']')) {
            $candidate = substr($candidate, 1, -1);
        }
        if ($candidate === '' || str_contains($candidate, '%')) {
            return null;
        }
        if (filter_var($candidate, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) === false) {
            return null;
        }
        $packed = inet_pton($candidate);
        if (!is_string($packed) || strlen($packed) !== 16) {
            return null;
        }
        if (substr($packed, 0, 12) !== "\0\0\0\0\0\0\0\0\0\0\xff\xff") {
            return null;
        }
        $ipv4 = inet_ntop(substr($packed, 12, 4));

        return is_string($ipv4) ? $ipv4 : null;
    }

    private function isBlockedHost(string $host): bool
    {
        if ($host === 'localhost' || str_ends_with($host, '.localhost') || str_ends_with($host, '.local') || str_ends_with($host, '.internal')) {
            return true;
        }
        if (preg_match('/^\d+$/', $host) === 1) {
            return true;
        }
        if (str_contains($host, '%') || str_contains($host, '\\')) {
            return true;
        }

        return false;
    }

    private function isPublic(string $ip): bool
    {
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        ) !== false;
    }
}
