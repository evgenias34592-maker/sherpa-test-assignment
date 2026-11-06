<?php

declare(strict_types=1);

namespace App\Http;

use App\Security\ClientIp;
use App\Security\JwtService;
use App\Security\RateLimitStore;
use App\Settings;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class RateLimitMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly RateLimitStore $store,
        private readonly Settings $settings,
        private readonly ClientIp $clientIp,
        private readonly JwtService $jwt,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $userId = $request->getAttribute('userId');
        if (!is_int($userId)) {
            $header = $request->getHeaderLine('Authorization');
            if (preg_match('/^Bearer\s+(\S+)$/i', $header, $matches) === 1) {
                try {
                    $userId = $this->jwt->userId($matches[1]);
                } catch (\Throwable) {
                    $userId = null;
                }
            }
        }
        $bucket = is_int($userId)
            ? 'import:user:' . $userId
            : 'import:ip:' . $this->clientIp->fromRequest($request);
        $result = $this->store->hit($bucket, time(), $this->settings->importRateWindow, $this->settings->importRateLimit);
        if (!$result->allowed) {
            throw new ApiException('Слишком много запросов импорта. Повторите позже.', 429);
        }

        return $handler->handle($request);
    }
}
