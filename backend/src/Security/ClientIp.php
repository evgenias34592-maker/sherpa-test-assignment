<?php

declare(strict_types=1);

namespace App\Security;

use Psr\Http\Message\ServerRequestInterface;

final class ClientIp
{
    public function fromRequest(ServerRequestInterface $request): string
    {
        $server = $request->getServerParams();
        $remote = $server['REMOTE_ADDR'] ?? '';
        if (!is_string($remote) || $remote === '') {
            return 'unknown';
        }

        return substr($remote, 0, 64);
    }
}
