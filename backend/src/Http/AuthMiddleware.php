<?php

declare(strict_types=1);

namespace App\Http;

use App\Repository\UserRepository;
use App\Security\JwtService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class AuthMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly JwtService $jwt,
        private readonly UserRepository $users,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $header = $request->getHeaderLine('Authorization');
        if (preg_match('/^Bearer\s+(\S+)$/i', $header, $matches) !== 1) {
            throw new ApiException('Требуется аутентификация', 401);
        }
        try {
            $userId = $this->jwt->userId($matches[1]);
        } catch (\Throwable) {
            throw new ApiException('Недействительный токен', 401);
        }
        $user = $this->users->find($userId);
        if ($user === null) {
            throw new ApiException('Недействительный токен', 401);
        }

        return $handler->handle($request->withAttribute('userId', $user->id()));
    }
}
