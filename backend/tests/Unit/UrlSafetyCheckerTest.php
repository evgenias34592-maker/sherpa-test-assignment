<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Import\HostResolver;
use App\Import\ImageFetchException;
use App\Import\UrlSafetyChecker;
use PHPUnit\Framework\TestCase;

final class UrlSafetyCheckerTest extends TestCase
{
    private UrlSafetyChecker $checker;

    protected function setUp(): void
    {
        $resolver = new class () implements HostResolver {
            public function resolve(string $host): array
            {
                return match ($host) {
                    'example.com' => ['93.184.216.34'],
                    'internal.example' => ['10.1.2.3'],
                    'mixed.example' => ['93.184.216.34', '127.0.0.1'],
                    'mapped.example' => ['::ffff:10.1.2.3'],
                    'mapped-public.example' => ['::ffff:93.184.216.34'],
                    default => [],
                };
            }
        };
        $this->checker = new UrlSafetyChecker($resolver);
    }

    public function testAllowsPublicHttp(): void
    {
        $checked = $this->checker->check('http://example.com/pics/a.jpg');

        self::assertSame('93.184.216.34', $checked->ip);
        self::assertSame(80, $checked->port);
        self::assertSame('/pics/a.jpg', $checked->pathAndQuery);
    }

    public function testAllowsPublicHttps(): void
    {
        $checked = $this->checker->check('https://example.com/pics/a.jpg');

        self::assertSame('93.184.216.34', $checked->ip);
        self::assertSame(443, $checked->port);
    }

    public function testBlocksLoopbackLiteral(): void
    {
        $this->expectException(ImageFetchException::class);
        $this->checker->check('http://127.0.0.1/secret');
    }

    public function testBlocksMetadataAddress(): void
    {
        $this->expectException(ImageFetchException::class);
        $this->checker->check('http://169.254.169.254/latest/meta-data');
    }

    public function testBlocksPrivateDnsAnswer(): void
    {
        $this->expectException(ImageFetchException::class);
        $this->checker->check('https://internal.example/a.jpg');
    }

    public function testBlocksMixedPublicAndPrivateAnswers(): void
    {
        $this->expectException(ImageFetchException::class);
        $this->checker->check('https://mixed.example/a.jpg');
    }

    public function testBlocksCredentialsAndOddPorts(): void
    {
        $this->expectException(ImageFetchException::class);
        $this->checker->check('http://user:pass@example.com/a.jpg');
    }

    public function testBlocksNonStandardPort(): void
    {
        $this->expectException(ImageFetchException::class);
        $this->checker->check('https://example.com:8443/a.jpg');
    }

    public function testBlocksMappedLoopback(): void
    {
        $this->expectException(ImageFetchException::class);
        $this->checker->check('http://[::ffff:127.0.0.1]/secret');
    }

    public function testBlocksMappedMetadata(): void
    {
        $this->expectException(ImageFetchException::class);
        $this->checker->check('http://[::FFFF:169.254.169.254]/latest/meta-data');
    }

    public function testBlocksMappedLoopbackWrittenAsHex(): void
    {
        $this->expectException(ImageFetchException::class);
        $this->checker->check('http://[::ffff:7f00:1]/secret');
    }

    public function testBlocksExpandedMappedLoopback(): void
    {
        $this->expectException(ImageFetchException::class);
        $this->checker->check('http://[0:0:0:0:0:ffff:127.0.0.1]/secret');
    }

    public function testBlocksMappedDnsAnswer(): void
    {
        $this->expectException(ImageFetchException::class);
        $this->checker->check('https://mapped.example/a.jpg');
    }

    public function testTreatsMappedPublicAddressAsIpv4(): void
    {
        $checked = $this->checker->check('https://mapped-public.example/a.jpg');

        self::assertSame('93.184.216.34', $checked->ip);
        self::assertSame(443, $checked->port);
    }
}
