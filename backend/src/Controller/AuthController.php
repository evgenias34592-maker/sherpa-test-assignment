<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\ApiException;
use App\Http\JsonResponse;
use App\Security\Authenticator;
use App\Security\ClientIp;
use App\Security\JwtService;
use App\Security\RateLimitStore;
use App\Settings;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class AuthController
{
    public function __construct(
        private readonly Authenticator $authenticator,
        private readonly JwtService $jwt,
        private readonly JsonResponse $json,
        private readonly RateLimitStore $limits,
        private readonly Settings $settings,
        private readonly ClientIp $clientIp,
    ) {
    }

    public function login(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $bucket = 'login:ip:' . $this->clientIp->fromRequest($request);
        $limit = $this->limits->hit($bucket, time(), 60, $this->settings->loginRateLimit);
        if (!$limit->allowed) {
            throw new ApiException('Слишком много попыток входа. Повторите позже.', 429);
        }

        $body = $request->getParsedBody();
        if (!is_array($body)) {
            throw new ApiException('Некорректное тело запроса', 422);
        }
        $email = $body['email'] ?? null;
        $password = $body['password'] ?? null;
        if (!is_string($email) || !is_string($password)) {
            throw new ApiException('Укажите email и пароль', 422);
        }
        $email = trim($email);
        if ($email === '' || $password === '') {
            throw new ApiException('Укажите email и пароль', 422);
        }
        if (
            strlen($email) > 180
            || preg_match('/[\x00-\x1F\x7F]/', $email) === 1
            || filter_var($email, FILTER_VALIDATE_EMAIL) === false
        ) {
            throw new ApiException('Некорректный email', 422);
        }
        if (strlen($password) > 200) {
            throw new ApiException('Пароль слишком длинный', 422);
        }
        $user = $this->authenticator->authenticate($email, $password);
        if ($user === null) {
            throw new ApiException('Неверный email или пароль', 401);
        }

        return $this->json->write($response, [
            'token' => $this->jwt->issue($user->id(), $user->email()),
            'user' => [
                'id' => $user->id(),
                'email' => $user->email(),
                'name' => $user->name(),
            ],
        ]);
    }
}
