<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\JsonResponse;
use App\Settings;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class HealthController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Settings $settings,
        private readonly JsonResponse $json,
    ) {
    }

    public function show(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        unset($request);
        $database = $this->database();
        $queue = $this->settings->messengerTransport === 'in-memory' || $this->queue();
        $ok = $database && $queue;

        return $this->json->write($response, [
            'status' => $ok ? 'ok' : 'degraded',
            'database' => $database ? 'ok' : 'down',
            'queue' => $queue ? 'ok' : 'down',
        ], $ok ? 200 : 503);
    }

    private function database(): bool
    {
        try {
            $this->em->getConnection()->executeQuery('SELECT 1');

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function queue(): bool
    {
        $parts = parse_url($this->settings->rabbitDsn);
        if (!is_array($parts) || !isset($parts['host'])) {
            return false;
        }
        $host = (string) $parts['host'];
        $port = (int) ($parts['port'] ?? 5672);
        $socket = stream_socket_client('tcp://' . $host . ':' . $port, $errno, $error, 1.0);
        if (!is_resource($socket)) {
            return false;
        }
        fclose($socket);

        return true;
    }
}
